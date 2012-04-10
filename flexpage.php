<?php
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

        $cm      = $this->get_cm();
        $forum   = $DB->get_record('hsuforum', array('id' => $cm->instance));
        $context = context_module::instance($cm->id);

        if ($forum and has_capability('mod/hsuforum:viewdiscussion', $context)) {
            ob_start();
            $PAGE->get_renderer('mod_hsuforum')->view($COURSE, $cm, $forum, $context);
            $this->append_content(ob_get_contents());
            ob_end_clean();

            add_to_log($COURSE->id, "forum", "view forum", "view.php?id=$cm->id", "$forum->id", $cm->id);

            require_once($CFG->libdir . '/completionlib.php');
            $completion = new completion_info($COURSE);
            $completion->set_module_viewed($cm);
        }
    }
}