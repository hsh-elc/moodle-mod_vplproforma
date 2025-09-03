<?php
require_once(dirname(__FILE__).'/../../../config.php');
require_once(dirname(__FILE__).'/../locallib.php');
require_once(dirname(__FILE__).'/../vpl.class.php');
global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/filelib.php');

use core\context\user;
use core\exception\invalid_parameter_exception;

define('PROFORMA_TASK_XML_NAMESPACES', [/* First namespace is default namespace. */'urn:proforma:v2.1']);

class mod_vpl_proforma_submission_form extends moodleform {
    protected $vpl;
    public function __construct($page, $vpl) {
        $this->vpl = $vpl;
        parent::__construct( $page );
    }
    function definition() {
        global $COURSE;
        $mform = &$this->_form;
        $id = $this->vpl->get_course_module()->id;
        $mform->addElement( 'hidden', 'id', $id );
        $mform->setType( 'id', PARAM_INT );

        $mform->addElement('header', 'taskfile', "ProForma Task File");
        $mform->addElement('filemanager', 'proformataskfileupload', 'ProForma task file',
            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 1));

        $mform->addElement( 'submit', 'saveoptions', get_string( 'saveoptions', VPL ) );

    }
}

require_login();
$id = required_param('id', PARAM_INT);
$vpl = new mod_vpl( $id );
$vpl->prepare_page('forms/proforma_submission.php', [ 'id' => $id ]);
$vpl->require_capability(VPL_MANAGE_CAPABILITY);
// Display page.
$vpl->print_header( get_string( 'execution', VPL ) );
$vpl->print_heading_with_help( 'executionoptions' );

$mform = new mod_vpl_proforma_submission_form('proforma_submission.php', $vpl);

// If save button is clicked, run script
if ($fromform = $mform->get_data()) {
    if (isset($fromform->saveoptions)) {
        setup_proforma_task($vpl);
    }
}

$mform->display();
$vpl->print_footer();

function setup_proforma_task(\mod_vpl $vpl): void {
    global $USER;
    // fetch draft item id and user context
    $draftitemid = file_get_submitted_draft_itemid('proformataskfileupload');
    $usercontext = user::instance($USER->id);

    // Fetch file from draft area
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
    // Check if a file was uploaded
    if (count($files) <= 0) {
        throw new invalid_parameter_exception('No ProFormA task file has been uploaded');
    }

    $file = reset($files); // First file in the list
    $filename = $file->get_filename();
    $filecontent = $file->get_content();

    // Check if file type is valid
    $filetype = extract_filetype($filename);
    if ($filetype != 'zip' && $filetype != 'xml') {
        throw new invalid_parameter_exception('Supplied file must be a xml or zip file.');
    }

    replace_vpl_task_files($vpl, $filename, $filecontent); // Replace current task file(s) with new one

    if ($filetype == 'zip') {
        $filecontent = extract_task_xml_from_zip($file, $draftitemid);
    }

    // Create a new document with the task.xml
    $doc = new DOMDocument();
    $doc->loadXML($filecontent);

    // Find ProFormA namespace
    $namespace = find_proforma_namespace($doc);

    // Extract needed info from xml
    $titleElement = $doc->getElementsByTagNameNS($namespace, 'title')[0];
    $descriptionElement = $doc->getElementsByTagNameNS($namespace, 'description')[0];

    // Update the title and description values of the vpl instance.
    $instance = $vpl->get_instance();
    $instance->name = $titleElement->nodeValue;
    $instance->intro = $descriptionElement->nodeValue;
    $vpl->update();

    replace_vpl_required_files($vpl, $doc, $namespace, $draftitemid);
    // Clean up files in the draft file area.
    clear_draft_filearea($draftitemid);
    // Clear course cache, so changes will be present on reload
    rebuild_course_cache($instance->course, true);
}

/**
 * Extracts a given zip file and returns the contained task.xml file
 * 
 * @throws invalid_parameter_exception if zip doesn't contain a task.xml file
 */
function extract_task_xml_from_zip(\stored_file $file, int $draftitemid): string {
    global $USER;
    $zipfilename = $file->get_filename();
    $result = array('zip' => $zipfilename);

    // Unzip file - basically copied from draftfiles_ajax.php.
    $zipper = get_file_packer('application/zip');

    // Find unused name for directory to extract the archive.
    $fs = get_file_storage();
    $usercontext = user::instance($USER->id);
    $temppath = $fs->get_unused_dirname($usercontext->id, 'user', 'draft', $draftitemid, "/" . pathinfo($zipfilename,
            PATHINFO_FILENAME) . '/');
    $donotremovedirs = array();
    $doremovedirs = array($temppath);
    // Extract archive and move all files from $temppath to $filepath.
    if ($file->extract_to_storage($zipper, $usercontext->id, 'user', 'draft', $draftitemid, $temppath, $USER->id)) {
        $extractedfiles = $fs->get_directory_files($usercontext->id, 'user', 'draft', $draftitemid, $temppath, true);
        $xtemppath = preg_quote($temppath, '|');
        foreach ($extractedfiles as $exfile) {
            $realpath = preg_replace('|^' . $xtemppath . '|', '/', $exfile->get_filepath());
            if (!$exfile->is_directory()) {
                // Set the source to the extracted file to indicate that it came from archive.
                $exfile->set_source(serialize((object)array('source' => '/')));
            }
            if (!$fs->file_exists($usercontext->id, 'user', 'draft', $draftitemid, $realpath, $exfile->get_filename())) {
                // File or directory did not exist, just move it.
                $exfile->rename($realpath, $exfile->get_filename());
            } else if (!$exfile->is_directory()) {
                // File already existed, overwrite it.
                repository::overwrite_existing_draftfile($draftitemid, $realpath, $exfile->get_filename(), $exfile->get_filepath(),
                    $exfile->get_filename());
            } else {
                // Directory already existed, remove temporary dir but make sure we don't remove the existing dir.
                $doremovedirs[] = $exfile->get_filepath();
                $donotremovedirs[] = $realpath;
            }
            if (!$exfile->is_directory() && $realpath == '/' && $exfile->get_filename() == 'task.xml') {
                $result['xml'] = $exfile->get_filename();
            }
        }
    }
    // Remove remaining temporary directories.
    foreach (array_diff($doremovedirs, $donotremovedirs) as $filepath) {
        $file = $fs->get_file($usercontext->id, 'user', 'draft', $draftitemid, $filepath, '.');
        if ($file) {
            $file->delete();
        }
    }

    if (!array_key_exists('xml', $result)) {
        throw new invalid_parameter_exception('Supplied zip file must contain the file task.xml.');
    }

    $file = $fs->get_file($usercontext->id, 'user', 'draft', $draftitemid, "/", $result['xml']);

    if (!$file) {
        throw new invalid_parameter_exception('Supplied zip file doesn\'t contain task.xml file.');
    }
    return $file->get_content();
}

function find_proforma_namespace(\DOMDocument $doc): string {
    foreach (PROFORMA_TASK_XML_NAMESPACES as $namespace) {
        if ($doc->getElementsByTagNameNS($namespace, "task")->length != 0) {
            return $namespace;
        }
    }
    return '';
}

/**
 * Deletes all task/... files from execution files and puts the new one
 */
function replace_vpl_task_files(\mod_vpl $vpl, string $newfilename, string $newfilecontent): void {
    $execution_fgm = $vpl->get_execution_fgm();
    $filelist = $execution_fgm->getfilelist();
    foreach ($filelist as $executionfile) {
        if (str_starts_with($executionfile, 'task/')) {
            // Delete file
            $execution_fgm->addFile($executionfile, null);
            // Update filelist
            $index = array_search($executionfile, $filelist);
            if ($index) {
                unset($filelist[$index]);
                $execution_fgm->setfilelist(array_values($filelist));
            }
        }
    }
    $execution_fgm->addFile("task/" . $newfilename, $newfilecontent);
}

/**
 * Deletes all required files and replaces them with all attached and embedded files
 * $draftitemid needed for fetching attached files from draft area
 */
function replace_vpl_required_files(\mod_vpl $vpl, \DOMDocument $doc, string $namespace, int $draftitemid): void {
    // Check if there are files visible by students and if yes, add them to the requested files list.
    // get required files group manager
    $required_fgm = $vpl->get_required_fgm();
    // delete all existing required files
    $required_fgm->deleteallfiles();
    $filesElement = $doc->getElementsByTagNameNS($namespace, 'files')[0];
    foreach ($filesElement->childNodes as $fileElement) {
        if ($fileElement->nodeType === XML_ELEMENT_NODE) {
            $visible = $fileElement->getAttribute('visible') === 'yes';
            if ($visible) {
                $attachedBinFiles = $fileElement->getElementsByTagNameNS($namespace, 'attached-bin-file');
                $attachedTxtFiles = $fileElement->getElementsByTagNameNS($namespace, 'attached-txt-file');
                $embeddedBinFiles = $fileElement->getElementsByTagNameNS($namespace, 'embedded-bin-file');
                $embeddedTxtFiles = $fileElement->getElementsByTagNameNS($namespace, 'embedded-txt-file');
                if ($attachedBinFiles->length > 0 || $attachedTxtFiles->length > 0) {
                    add_attached_file_to_required_files($required_fgm, $attachedBinFiles, $attachedTxtFiles, $draftitemid);
                } elseif ($embeddedBinFiles->length > 0 || $embeddedTxtFiles->length > 0) {
                    add_embedded_file_to_required_files($required_fgm, $embeddedBinFiles, $embeddedTxtFiles);
                }
            }
        }
    }
}

/**
 * Adds an attached file to the required files
 */
function add_attached_file_to_required_files(object $required_fgm, mixed $attachedBinFiles, mixed $attachedTxtFiles, int $draftitemid): void {
    global $USER;
    $fs = get_file_storage();
    $usercontext = user::instance($USER->id);
    $attachedFileValue = '';
    if ($attachedBinFiles->length > 0) {
        $attachedFileValue = $attachedBinFiles[0]->nodeValue;
    } elseif ($attachedTxtFiles->length > 0) {
        $attachedFileValue = $attachedTxtFiles[0]->nodeValue;
    }
    $pathInfo = pathinfo($attachedFileValue);
    $file = $fs->get_file($usercontext->id, 'user', 'draft', $draftitemid, $pathInfo['dirname'] . '/', $pathInfo['basename']);
    if (!$file) {
        throw new invalid_parameter_exception('File with name ' . $attachedFileValue . ' not found');
    }
    $required_fgm->addFile($attachedFileValue, $file->get_content());
}

/**
 * Adds an embedded file to the required files
 */
function add_embedded_file_to_required_files(object $required_fgm, mixed $embeddedBinFiles, mixed $embeddedTxtFiles): void {
    $embeddedFileName = '';
    $embeddedFileValue = '';
    if ($embeddedBinFiles->length > 0) {
        $embeddedFileName = $embeddedBinFiles[0]->getAttribute('filename');
        $embeddedFileValue = $embeddedBinFiles[0]->nodeValue;
    } elseif ($embeddedTxtFiles->length > 0) {
        $embeddedFileName = $embeddedTxtFiles[0]->getAttribute('filename');
        $embeddedFileValue = $embeddedTxtFiles[0]->nodeValue;
    }
    $required_fgm->addFile($embeddedFileName, $embeddedFileValue);
}

/**
 * Deletes all files from draft file area
 */
function clear_draft_filearea(int $draftitemid): void {
    global $USER;
    $fs = get_file_storage();
    $usercontext = user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid);
    foreach ($files as $fi) {
        $fi->delete();
    }
}

/**
 * Extract file type from file name through it's extension
 */
function extract_filetype(string $filename): string {
    $fileinfo = pathinfo($filename);
    return strtolower($fileinfo['extension']);
}