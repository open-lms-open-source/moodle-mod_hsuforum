<?php
/**
 * View Posters Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/abstract.php');
require_once(dirname(__DIR__).'/lib.php');

class hsuforum_controller_posts extends hsuforum_controller_abstract {
    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        global $PAGE;

        require_capability('mod/hsuforum:viewdiscussion', $PAGE->context, NULL, true, 'noviewdiscussionspermission', 'hsuforum');
    }

    /**
     * View Posters
     */
    public function postnodes_action() {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        $discussionid = required_param('discussionid', PARAM_INT);
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum = $PAGE->activityrecord;
        $course = $COURSE;
        $cm = $PAGE->cm;

        if ($forum->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
                || $discussion->timestart <= time())
                && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
                print_error('invaliddiscussionid', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
            }
        }
        if (!$post = hsuforum_get_post_full($discussion->firstpost)) {
            print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }
        if (!hsuforum_user_can_view_post($post, $course, $cm, $forum, $discussion)) {
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?id=$forum->id");
        }

        $mode = get_user_preferences('hsuforum_displaymode', $CFG->hsuforum_displaymode);
        if ($mode == HSUFORUM_MODE_FLATNEWEST) {
            $sort = "p.created DESC";
        } else {
            $sort = "p.created ASC";
        }

        $forumtracked = hsuforum_tp_is_tracked($forum);
        $posts        = hsuforum_get_all_discussion_posts($discussion->id, $sort, $forumtracked);
        $nodes        = array();

        if (!empty($posts[$post->id]) and !empty($posts[$post->id]->children)) {
            foreach ($posts[$post->id]->children as $post) {
                if ($node = $this->get_renderer()->post_to_node($cm, $forum, $discussion, $post, $forumtracked)) {
                    $nodes[] = $node;
                }
            }
        }
        echo json_encode($nodes);
    }
}