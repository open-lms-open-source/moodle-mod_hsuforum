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

/**
 * View Posters Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\controller;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/controller_abstract.php');
require_once(dirname(dirname(__DIR__)).'/lib/table/posters.php');

class posters_controller extends controller_abstract {
    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        // Anyone can view
    }

    /**
     * View Posters
     */
    public function viewposters_action() {
        global $PAGE, $OUTPUT;

        $table = new \hsuforum_lib_table_posters('mod_hsuforum_viewposters');
        $table->define_baseurl($PAGE->url->out());
        $table->set_attribute('class', 'generaltable generalbox hsuforum_viewposters');
        $table->column_class('userpic', 'col_userpic');

        echo $OUTPUT->heading(get_string('viewposters', 'hsuforum'));
        $table->out('25', false);
    }
}
