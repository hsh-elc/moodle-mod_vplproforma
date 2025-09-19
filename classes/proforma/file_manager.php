<?php
require_once(dirname(__FILE__).'/../../vpl_submission.class.php');

use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;

/**
 * File manager with file operations for VPL-ProFormA-Integration operations
 */
class proforma_file_manager {
    private \mod_vpl $vpl;
    private object $execution_fgm;
    private object $required_fgm;

    public function __construct(\mod_vpl $vpl) {
        $this->vpl = $vpl;
        $this->execution_fgm = $vpl->get_execution_fgm();
        $this->required_fgm = $vpl->get_required_fgm();
    }

    /**
     * Adds a file to the "Execution files" tab
     * 
     * @param bool $keepfilewhenrunning if true, also add the file to the "Files to keep when running" tab
     */
    public function add_execution_file(string $filename, string $filecontent, bool $keepfilewhenrunning = false): void {
        $success = $this->execution_fgm->addFile($filename, $filecontent);
        if (!$success) {
            throw new moodle_exception('File "' . $filename . '" could not be added to the Execution files tab.');
        }
        if ($keepfilewhenrunning) {
            $this->add_execution_file_to_keep_list($filename);
        }
    }

    /**
     * Marks a file from the "Execution files" tab as "keep" in the "Files to keep when running" tab
     */
    public function add_execution_file_to_keep_list(string $filename): void {
        $executionfiles = $this->execution_fgm->getfilelist();
        if (!in_array($filename, $executionfiles)) {
            throw new invalid_parameter_exception('File "' . $filename . '" not found in Execution files tab');
        }

        $keepfiles = $this->execution_fgm->getfilekeeplist();
        $keepfiles[] = $filename;
        $this->execution_fgm->setfilekeeplist($keepfiles);
    }

    /**
     * Clears all entries from the "Files to keep when running" tab
     */
    public function clear_execution_files_keep_list(): void {
        $this->execution_fgm->setfilekeeplist(array());
    }

    /**
     * Deletes all files from the "Execution files" tab
     */
    public function delete_all_execution_files(bool $clearkeepfileswhenrunning = false): void {
        $this->execution_fgm->deleteallfiles();
        if ($clearkeepfileswhenrunning) {
            $this->clear_execution_files_keep_list();
        }
    }

    /**
     * Adds a file to the "Requested files" tab
     */
    public function add_required_file(string $filename, string $filecontent): void {
        $success = $this->required_fgm->addFile($filename, $filecontent);
        if (!$success) {
            throw new moodle_exception(
                'File "' . $filename . '" could not be added to the Requested files tab. Check if "Maximum number of files" in VPL activity settings is large enough.'
            );
        }
    }

    /**
     * Deletes all files from the "Requested files" tab
     */
    public function delete_all_required_files(): void {
        $this->required_fgm->deleteallfiles();
    }

    /**
     * Deletes all user submissions in the given VPL activity
     */
    public function delete_all_user_submissions(): void {
        $submissions = $this->vpl->all_user_submission();
        foreach ($submissions as $submission) {
            $subm = new mod_vpl_submission($this->vpl, $submission);
            $subm->delete();
        }
    }
}