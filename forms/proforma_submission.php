<?php
require_once(dirname(__FILE__).'/../../../config.php');
require_once(dirname(__FILE__).'/../locallib.php');
require_once(dirname(__FILE__).'/../vpl.class.php');
require_once(dirname(__FILE__).'/../classes/proforma/file_manager.php');
require_once(dirname(__FILE__).'/../classes/proforma/form_options.php');
require_once(dirname(__FILE__).'/../classes/proforma/release_fetcher.php');
require_once(dirname(__FILE__).'/../classes/proforma/task_doc.php');
global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/filelib.php');

use core\context\user;
use core\exception\invalid_parameter_exception;
use core\exception\invalid_state_exception;

/**
 * Hard-coded repo owner and name, so that modified HTTP data won't download data from anywhere
 */
define('VPL_PROFORMA_INTEGRATION_REPO_OWNER', 'levipalait');
define('VPL_PROFORMA_INTEGRATION_REPO_NAME', 'release-test'); // TODO: Change Repo Owner and Name. This right now is only for testing!!!
define('PROFORMA_SETTINGS_SHELL_FILENAME', 'proforma_settings.sh');

/**
 * Form elements
 */
define('PROFORMA_TASK_FILE_UPLOAD_ELEM', 'proformataskfileupload');
define('PROFORMA_SAVE_BUTTON', 'proformasaveoptionsbutton');

class mod_vpl_proforma_submission_form extends moodleform {
    protected \mod_vpl $vpl;
    private proforma_release_fetcher $releasefetcher;

    public function __construct($page, $vpl) {
        $this->vpl = $vpl;
        $this->releasefetcher = new proforma_release_fetcher(VPL_PROFORMA_INTEGRATION_REPO_OWNER, VPL_PROFORMA_INTEGRATION_REPO_NAME);
        parent::__construct($page);
    }

    function definition() {
        //Init
        global $COURSE;
        $mform = &$this->_form;
        $id = $this->vpl->get_course_module()->id;
        $mform->addElement( 'hidden', 'id', $id );
        $mform->setType( 'id', PARAM_INT );

        // Teacher guide
        $mform->addElement('header', 'teacherguideheader', get_string('teacherguideheader', VPL));
        $mform->setExpanded('teacherguideheader', true);
        $mform->addElement('static', 'teacherguideselectrelease', get_string('teacherguideselectrelease:title', VPL), get_string('teacherguideselectrelease:text', VPL));
        $mform->addElement('static', 'teacherguideconfiguregrader', get_string('teacherguideconfiguregrader:title', VPL), get_string('teacherguideconfiguregrader:text', VPL));
        $mform->addElement('static', 'teacherguideuploadtask', get_string('teacherguideuploadtask:title', VPL), get_string('teacherguideuploadtask:text', VPL));
        $mform->addElement('static', 'teacherguidesummary', get_string('teacherguidesummary:title', VPL), get_string('teacherguidesummary:text', VPL));

        // Download VPL-ProFormA-Release
        $mform->addElement('header', 'vplproformaheader', get_string('vplproformaheader', VPL));
        $mform->setExpanded('vplproformaheader', true);
        $mform->addElement('select', PROFORMA_SETTINGS_RELEASE_SELECTOR_ELEM, get_string('releaseselect', VPL), $this->releasefetcher->get_release_names());
        $mform->addHelpButton(PROFORMA_SETTINGS_RELEASE_SELECTOR_ELEM, 'releaseselect', VPL);

        // ProFormA grader settings
        $mform->addElement('header', 'proformasettingsheader', get_string('gradersettingsheader', VPL));
        $mform->setExpanded('proformasettingsheader', true);

        $mform->addElement('text', PROFORMA_SETTINGS_SERVICE_URL_ELEM, get_string('serviceurl', VPL));
        $mform->addHelpButton(PROFORMA_SETTINGS_SERVICE_URL_ELEM, 'serviceurl', VPL);

        $mform->addElement('text', PROFORMA_SETTINGS_LMS_ID_ELEM, get_string('lmsid', VPL));
        $mform->addHelpButton(PROFORMA_SETTINGS_LMS_ID_ELEM, 'lmsid', VPL);

        $mform->addElement('passwordunmask', PROFORMA_SETTINGS_LMS_PASSWORD_ELEM, get_string('lmspassword', VPL));
        $mform->addHelpButton(PROFORMA_SETTINGS_LMS_PASSWORD_ELEM, 'lmspassword', VPL);

        $mform->addElement('select', PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_ELEM, get_string('acceptcertificates', VPL), PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_SELECT_OPTIONS);
        $mform->addHelpButton(PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_ELEM, 'acceptselfsigned', VPL);

        $mform->addElement('text', PROFORMA_SETTINGS_GRADER_NAME_ELEM, get_string('gradername', VPL));
        $mform->addHelpButton(PROFORMA_SETTINGS_GRADER_NAME_ELEM, 'gradername', VPL);

        $mform->addElement('text', PROFORMA_SETTINGS_GRADER_VERSION_ELEM, get_string('graderversion', VPL));
        $mform->addHelpButton(PROFORMA_SETTINGS_GRADER_VERSION_ELEM, 'graderversion', VPL);

        $mform->addElement('select', PROFORMA_SETTINGS_FEEDBACK_FORMAT_ELEM, get_string('feedbackformat', VPL), PROFORMA_SETTINGS_FEEDBACK_FORMAT_SELECT_OPTIONS);
        $mform->addHelpButton(PROFORMA_SETTINGS_FEEDBACK_FORMAT_ELEM, 'feedbackformat', VPL);

        $mform->addElement('select', PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_ELEM, get_string('feedbackstructure', VPL), PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_SELECT_OPTIONS);
        $mform->addHelpButton(PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_ELEM, 'feedbackstructure', VPL);

        $mform->addElement('select', PROFORMA_SETTINGS_STUDENT_FEEDBACK_ELEM, get_string('studentfeedbacklevel', VPL), PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS);
        $mform->addHelpButton(PROFORMA_SETTINGS_STUDENT_FEEDBACK_ELEM, 'studentfeedbacklevel', VPL);
        $mform->setDefault(PROFORMA_SETTINGS_STUDENT_FEEDBACK_ELEM, 1);

        $mform->addElement('select', PROFORMA_SETTINGS_TEACHER_FEEDABCK_ELEM, get_string('teacherfeedbacklevel', VPL), PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS);
        $mform->addHelpButton(PROFORMA_SETTINGS_TEACHER_FEEDABCK_ELEM, 'teacherfeedbacklevel', VPL);

        // ProFormA task file
        $mform->addElement('header', 'taskfile', get_string('proformataskfile', VPL));
        $mform->setExpanded('taskfile', true);
        $mform->addElement('filemanager', PROFORMA_TASK_FILE_UPLOAD_ELEM, get_string('proformataskfile', VPL),
            null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 1));
        $mform->addHelpButton(PROFORMA_TASK_FILE_UPLOAD_ELEM, 'proformataskfile', VPL);

        // Submit Button
        $mform->addElement( 'submit', PROFORMA_SAVE_BUTTON, get_string('submitproformatask', VPL));
    }

    public function get_release_fetcher(): proforma_release_fetcher {
        return $this->releasefetcher;
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
$fromform = $mform->get_data();
if (isset($fromform->{PROFORMA_SAVE_BUTTON})) {
    setup_proforma_task($vpl, $mform);
} else {
    $mform->display();
    $vpl->print_footer();
}

/**
 * Entry-Point after 'Save' is clicked
 */
function setup_proforma_task(mod_vpl $vpl, mod_vpl_proforma_submission_form $mform): void {
    $formoptions = new proforma_form_options($mform->get_data());
    $releasefetcher = $mform->get_release_fetcher();
    $filemgr = new proforma_file_manager($vpl);

    // Get uploaded file
    $file = get_first_file_from_draft_area();
    $filename = $file->get_filename();
    $filecontent = $file->get_content();

    // Check if file type is valid
    $filetype = extract_filetype($filename);
    if ($filetype != 'zip' && $filetype != 'xml') {
        throw new invalid_parameter_exception('Supplied file must be a xml or zip file.');
    }

    // Replace execution files with task file
    $filemgr->delete_all_execution_files(true);
    $filemgr->add_execution_file('task/' . $filename, $filecontent, true); // Add task file to "Execution files" tab

    if ($filetype == 'zip') {
        $filecontent = extract_task_xml_from_zip($file);
    }

    // Get task info from task file content
    $taskdoc = new proforma_task_doc($filecontent);

    $instance = $vpl->get_instance();
    $instance->name = $taskdoc->get_task_title();
    $instance->intro = $taskdoc->get_task_description();
    $vpl->update();

    // Add visible files in task file to the "Requested Files" tab
    $filemgr->delete_all_required_files();
    $requiredfiles = $taskdoc->get_visible_files(file_get_submitted_draft_itemid(PROFORMA_TASK_FILE_UPLOAD_ELEM));
    foreach ($requiredfiles as $file) {
        $filemgr->add_required_file($file['name'], $file['content']);
    }

    // Download release assets
    $releases = $releasefetcher->get_release_names();
    $selectedrelease = $formoptions->get_selected_release($releases);
    $assets = $releasefetcher->get_release_assets($selectedrelease);

    foreach ($assets as $asset) {
        $assetfilecontent = download_file($asset['url']);
        if ($asset['name'] === PROFORMA_SETTINGS_SHELL_FILENAME) {
            $assetfilecontent = $formoptions->format_proforma_settings_shell_file($assetfilecontent);
        }
        $filemgr->add_execution_file($asset['name'], $assetfilecontent, true);
    }

    $filemgr->delete_all_user_submissions();

    // Clean up files in the draft file area.
    clear_draft_filearea();
    // Clear course cache, so changes will be present on reload
    rebuild_course_cache($instance->course, true);
    // Redirect user to execution options page
    vpl_inmediate_redirect(vpl_mod_href('forms/executionoptions.php', 'id', $vpl->get_course_module()->id));
}

/**
 * Retreives the first file from the task upload draft area
 */
function get_first_file_from_draft_area(): \stored_file {
    global $USER;
    $draftitemid = file_get_submitted_draft_itemid(PROFORMA_TASK_FILE_UPLOAD_ELEM);
    $usercontext = user::instance($USER->id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
    if (count($files) <= 0) {
        throw new invalid_parameter_exception('No ProFormA task file has been uploaded');
    }
    return reset($files);
}

/**
 * Extracts a given zip file and returns the contained task.xml file
 * 
 * @throws invalid_parameter_exception if zip doesn't contain a task.xml file
 */
function extract_task_xml_from_zip(\stored_file $file): string {
    global $USER;
    $zipfilename = $file->get_filename();
    $result = array('zip' => $zipfilename);

    // Unzip file - basically copied from draftfiles_ajax.php.
    $zipper = get_file_packer('application/zip');

    // Find unused name for directory to extract the archive.
    $fs = get_file_storage();
    $draftitemid = file_get_submitted_draft_itemid(PROFORMA_TASK_FILE_UPLOAD_ELEM);
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

/**
 * Deletes all files from draft file area
 */
function clear_draft_filearea(): void {
    global $USER;
    $fs = get_file_storage();
    $draftitemid = file_get_submitted_draft_itemid(PROFORMA_TASK_FILE_UPLOAD_ELEM);
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

/**
 * Downloads a file from provided url while following redirects
 */
function download_file(string $url): string {
    $curl = new curl();
    $options = ['CURLOPT_FOLLOWLOCATION' => true];
    $response = $curl->get($url, [], $options);
    $info = $curl->get_info();

    if (!isset($info['http_code']) || $info['http_code'] != 200) {
        throw new invalid_state_exception('Download failed: HTTP status ' . $info['http_code'] . ' for URL ' . $url);
    }
    return $response;
}