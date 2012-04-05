<?php
/**
 * Post Flag Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/abstract.php');
require_once(dirname(__DIR__).'/lib/flag.php');

class hsuforum_controller_flag extends hsuforum_controller_abstract {
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
        $flaglib  = new hsuforum_lib_flag();
        $newflags = $flaglib->toggle_flag($flags, $flag);

        if ($newflags != $flags) {
            $DB->set_field('hsuforum_posts', 'flags', $newflags, array('id' => $postid));
        }
        if (!AJAX_SCRIPT) {
            redirect(new moodle_url($returnurl));
        }
    }
}