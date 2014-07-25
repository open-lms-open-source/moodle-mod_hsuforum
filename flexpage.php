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
 * Flexpage Integration
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class mod_hsuforum_flexpage extends block_flexpagemod_lib_mod {
    public function module_block_setup() {
        global $CFG, $COURSE, $DB, $PAGE;

        // Cannot use cm_info because it is read only.
        $cm      = get_coursemodule_from_id('hsuforum', $this->get_cm()->id, $COURSE->id, false, MUST_EXIST);
        $forum   = $DB->get_record('hsuforum', array('id' => $cm->instance));
        $context = context_module::instance($cm->id);

        if ($forum and has_capability('mod/hsuforum:viewdiscussion', $context)) {
            ob_start();
            $PAGE->get_renderer('mod_hsuforum')->view($COURSE, $cm, $forum, $context);
            $this->append_content(ob_get_contents());
            ob_end_clean();

            $params = array(
                'context'  => $context,
                'objectid' => $forum->id
            );
            $event  = \mod_hsuforum\event\course_module_viewed::create($params);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $COURSE);
            $event->add_record_snapshot('hsuforum', $forum);
            $event->trigger();

            require_once($CFG->libdir . '/completionlib.php');
            $completion = new completion_info($COURSE);
            $completion->set_module_viewed($cm);
        }
    }
}