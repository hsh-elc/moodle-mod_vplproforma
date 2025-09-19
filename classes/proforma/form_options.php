<?php

use core\exception\invalid_parameter_exception;
use core\exception\invalid_state_exception;

/**
 * Release selector
 */
define('PROFORMA_SETTINGS_RELEASE_SELECTOR_ELEM', 'proformasettingsreleaseselect');

/**
 * Form element names
 */
define('PROFORMA_SETTINGS_SERVICE_URL_ELEM', 'proformasettingsserviceurltext');
define('PROFORMA_SETTINGS_LMS_ID_ELEM', 'proformasettingslmsidtext');
define('PROFORMA_SETTINGS_LMS_PASSWORD_ELEM', 'proformasettingslmspasswordpasswordunmask');
define('PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_ELEM', 'proformasettingsacceptsscselect');
define('PROFORMA_SETTINGS_GRADER_NAME_ELEM', 'proformasettingsgradernametext');
define('PROFORMA_SETTINGS_GRADER_VERSION_ELEM', 'proformasettingsgraderversiontext');
define('PROFORMA_SETTINGS_FEEDBACK_FORMAT_ELEM', 'proformasettingsfeedbackformatselect');
define('PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_ELEM', 'proformasettingsfeedbackstructureselect');
define('PROFORMA_SETTINGS_STUDENT_FEEDBACK_ELEM', 'proformasettingsstudentfeedbackselect');
define('PROFORMA_SETTINGS_TEACHER_FEEDABCK_ELEM', 'proformasettingsteacherfeedbackselect');

/**
 * Placeholders in proforma_settings.sh
 */
define('PROFORMA_SERVICE_URL_PLACEHOLDER', '<VPL_PROFORMA_SERVICE_URL>');
define('PROFORMA_LMS_ID_PLACEHOLDER', '<VPL_PROFORMA_LMS_ID>');
define('PROFORMA_LMS_PASSWORD_PLACEHOLDER', '<VPL_PROFORMA_LMS_PASSWORD>');
define('PROFORMA_ACCEPT_SELF_SIGNED_PLACEHOLDER', '<VPL_PROFORMA_ACCEPT_SELF_SIGNED_CERTS>');
define('PROFORMA_GRADER_NAME_PLACEHOLDER', '<VPL_PROFORMA_GRADER_NAME>');
define('PROFORMA_GRADER_VERSION_PLACEHOLDER', '<VPL_PROFORMA_GRADER_VERSION>');
define('PROFORMA_FEEDBACK_FORMAT_PLACEHOLDER', '<VPL_PROFORMA_FEEDBACK_FORMAT>');
define('PROFORMA_FEEDBACK_STRUCTURE_PLACEHOLDER', '<VPL_PROFORMA_FEEDBACK_STRUCTURE>');
define('PROFORMA_STUDENT_FEEDBACK_PLACEHOLDER', '<VPL_PROFORMA_STUDENT_FEEDBACK_LEVEL>');
define('PROFORMA_TEACHER_FEEDBACK_PLACEHOLDER', '<VPL_PROFORMA_TEACHER_FEEDBACK_LEVEL>');

/**
 * Dropdown select options
 */
define('PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_SELECT_OPTIONS', array('No', 'Yes'));
define('PROFORMA_SETTINGS_FEEDBACK_FORMAT_SELECT_OPTIONS', array('zip', 'xml'));
define('PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_SELECT_OPTIONS', array('separate-test-feedback', 'merged-test-feedback'));
define('PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS', array('debug', 'info', 'warn', 'error'));

/**
 * Extracts all proforma settings attributes from given form data
 */
class proforma_form_options {
    private string $releaseindex;               // Index into release names array
    private string $serviceurl;
    private string $lmsid;
    private string $lmspassword;
    private string $acceptselfsigned;           // Indexed from PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_SELECT_OPTIONS
    private string $gradername;
    private string $graderversion;
    private string $feedbackformat;             // Indexed from PROFORMA_SETTINGS_FEEDBACK_FORMAT_SELECT_OPTIONS
    private string $feedbackstructure;          // Indexed from PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_SELECT_OPTIONS
    private string $studentfeedbacklevel;       // Indexed from PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS
    private string $teacherfeedbacklevel;       // Indexed from PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS
    
    public function __construct(stdClass $formdata) {
        // Non-Indexed:
        $this->releaseindex = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_RELEASE_SELECTOR_ELEM});
        $this->serviceurl = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_SERVICE_URL_ELEM});
        $this->lmsid = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_LMS_ID_ELEM});
        $this->lmspassword = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_LMS_PASSWORD_ELEM});
        $this->gradername = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_GRADER_NAME_ELEM});
        $this->graderversion = $this->set_string_or_throw($formdata->{PROFORMA_SETTINGS_GRADER_VERSION_ELEM});
        
        // Indexed:
        $this->acceptselfsigned = $this->set_string_in_array_or_throw(
            $formdata->{PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_ELEM},
            PROFORMA_SETTINGS_ACCEPT_SELF_SIGNED_SELECT_OPTIONS
        );
        $this->feedbackformat = $this->set_string_in_array_or_throw(
            $formdata->{PROFORMA_SETTINGS_FEEDBACK_FORMAT_ELEM},
            PROFORMA_SETTINGS_FEEDBACK_FORMAT_SELECT_OPTIONS
        );
        $this->feedbackstructure = $this->set_string_in_array_or_throw(
            $formdata->{PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_ELEM},
            PROFORMA_SETTINGS_FEEDBACK_STRUCTURE_SELECT_OPTIONS
        );
        $this->studentfeedbacklevel = $this->set_string_in_array_or_throw(
            $formdata->{PROFORMA_SETTINGS_STUDENT_FEEDBACK_ELEM},
            PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS
        );
        $this->teacherfeedbacklevel = $this->set_string_in_array_or_throw(
            $formdata->{PROFORMA_SETTINGS_TEACHER_FEEDABCK_ELEM},
            PROFORMA_SETTINGS_FEEDBACK_LEVEL_SELECT_OPTIONS
        );
    }

    /**
     * Checks if a value is a non-empty string and returns it if true
     * Returns an invalid_parameter_exception if the value isn't a non-empty string
     */
    private function set_string_or_throw(mixed $value): string {
        if (isset($value) && is_string($value) && $value !== '') {
            return $value;
        } else {
            throw new invalid_parameter_exception('Expected non-empty string. Found "' . $value . '"');
        }
    }

    /**
     * Cheks if a value is a non-empty string that is contained as key in $array
     */
    private function set_string_in_array_or_throw(mixed $key, array $array): string {
        $index = $this->set_string_or_throw($key);
        if (array_key_exists($index, $array)) {
            $result = $array[$index];
            if (is_string($result)) {
                return $result;
            } else {
                throw new invalid_state_exception('Array should contain strings. Found "' . $result . '"');
            }
        } else {
            throw new invalid_parameter_exception('Expected non-empty string that is contained in array' . $array);
        }
    }

    /**
     * Takes the contents of the proforma_settings.sh and replaces the placeholders with
     * the respective values from form_options
     */
    public function format_proforma_settings_shell_file(string $proformasettingsfilecontent): string {
        $replaced = $proformasettingsfilecontent;
        $replaced = str_replace(PROFORMA_SERVICE_URL_PLACEHOLDER, escapeshellarg($this->serviceurl), $replaced);
        $replaced = str_replace(PROFORMA_LMS_ID_PLACEHOLDER, escapeshellarg($this->lmsid), $replaced);
        $replaced = str_replace(PROFORMA_LMS_PASSWORD_PLACEHOLDER, escapeshellarg($this->lmspassword), $replaced);
        $replaced = str_replace(PROFORMA_ACCEPT_SELF_SIGNED_PLACEHOLDER, escapeshellarg($this->acceptselfsigned), $replaced);
        $replaced = str_replace(PROFORMA_GRADER_NAME_PLACEHOLDER, escapeshellarg($this->gradername), $replaced);
        $replaced = str_replace(PROFORMA_GRADER_VERSION_PLACEHOLDER, escapeshellarg($this->graderversion), $replaced);
        $replaced = str_replace(PROFORMA_FEEDBACK_FORMAT_PLACEHOLDER, escapeshellarg($this->feedbackformat), $replaced);
        $replaced = str_replace(PROFORMA_FEEDBACK_STRUCTURE_PLACEHOLDER, escapeshellarg($this->feedbackstructure), $replaced);
        $replaced = str_replace(PROFORMA_STUDENT_FEEDBACK_PLACEHOLDER, escapeshellarg($this->studentfeedbacklevel), $replaced);
        $replaced = str_replace(PROFORMA_TEACHER_FEEDBACK_PLACEHOLDER, escapeshellarg($this->teacherfeedbacklevel), $replaced);
        return $replaced;
    }

    /**
     * Returns the element of $releasenames that has been selected in the proforma task form
     */
    public function get_selected_release(array $releasenames): string {
        return $this->set_string_in_array_or_throw($this->releaseindex, $releasenames);
    }
}