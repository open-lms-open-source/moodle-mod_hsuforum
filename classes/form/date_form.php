<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_hsuforum\form;

global $CFG;

require_once($CFG->libdir.'/formslib.php');

/**
 * Date form.
 * @author    gthomas2
 * @copyright Copyright (c) 2015 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class date_form extends \moodleform {
    function definition() {
        $mform = $this->_form;
        $data = $this->get_data();
        // We have to add this first or we can't add the untoggleable legend into the form.
        $mform->addElement('static', 'nothing', '');
        // Untoggleable legend.
        $mform->addElement('html', '<legend>'.get_string('displayperiod', 'hsuforum').'</legend>');
        $mform->addElement('date_time_selector', 'timestart', get_string('displaystart', 'hsuforum'), array('optional' => true));
        $mform->addHelpButton('timestart', 'displaystart', 'hsuforum');
        $mform->addElement('date_time_selector', 'timeend', get_string('displayend', 'hsuforum'), array('optional' => true));
        $mform->addHelpButton('timeend', 'displayend', 'hsuforum');
    }
}
