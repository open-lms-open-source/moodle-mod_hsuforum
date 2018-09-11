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
 * Post Flag Controller
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
require_once(dirname(dirname(__DIR__)).'/lib/flag.php');

class flag_controller extends controller_abstract {
    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        global $PAGE;

        require_capability('mod/hsuforum:editanypost', $PAGE->context);
    }

    /**
     * Toggle Post Flags
     */
    public function flag_action() {
        global $DB;

        require_sesskey();

        $postid    = required_param('postid', PARAM_INT);
        $flag      = required_param('flag', PARAM_ALPHA);
        $returnurl = required_param('returnurl', PARAM_LOCALURL);

        $flags    = $DB->get_field('hsuforum_posts', 'flags', array('id' => $postid), MUST_EXIST);
        $flaglib  = new \hsuforum_lib_flag();
        $newflags = $flaglib->toggle_flag($flags, $flag);

        if ($newflags != $flags) {
            $updateok = $DB->set_field('hsuforum_posts', 'flags', $newflags, array('id' => $postid));
            if (AJAX_SCRIPT && !$updateok){
                http_response_code(500);
            }
        }
        if (!AJAX_SCRIPT) {
            redirect(new \moodle_url($returnurl));
        }
    }
}
