<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Similarity form
 *
 * @package mod_vpl
 * @copyright 2012 Juan Carlos Rodríguez-del-Pino
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

require_once(dirname(__FILE__).'/../../../config.php');
require_once(dirname(__FILE__).'/../locallib.php');
require_once(dirname(__FILE__).'/../vpl.class.php');
require_once(dirname(__FILE__).'/../vpl_submission.class.php');
global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once(dirname(__FILE__).'/similarity_form.class.php');

require_login();

$id = required_param( 'id', PARAM_INT );
$vpl = new mod_vpl( $id );
$vpl->prepare_page( 'similarity/similarity_form.php', [
        'id' => $id,
] );

// Find out current groups mode.
$cm = $vpl->get_course_module();
$groupmode = groups_get_activity_groupmode( $cm );
if (! $groupmode) {
    $groupmode = groups_get_course_groupmode( $vpl->get_course() );
}
$currentgroup = groups_get_activity_group( $cm, true );
if (! $currentgroup) {
    $currentgroup = '';
}

$vpl->require_capability( VPL_SIMILARITY_CAPABILITY );
\mod_vpl\event\vpl_similarity_form_viewed::log( $vpl );
// Print header.
$vpl->print_header( get_string( 'similarity', VPL ) );
$vpl->print_view_tabs( basename( __FILE__ ) );
// Menu for groups.
if ($groupmode) {
    $groupsurl = vpl_mod_href( 'similarity/similarity_form.php', 'id', $id );
    groups_print_activity_menu( $cm, $groupsurl );
}
$form = new vpl_similarity_form( 'listsimilarity.php', $vpl );
$form->display();
$vpl->print_footer();
