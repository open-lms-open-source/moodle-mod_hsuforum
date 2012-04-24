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

        switch ($action) {
            case 'discsubscribers':
                if (!has_capability('mod/hsuforum:viewsubscribers', $PAGE->context)) {
                    print_error('nopermissiontosubscribe', 'hsuforum');
                }
                break;
            default:
                require_capability('mod/hsuforum:viewdiscussion', $PAGE->context, NULL, true, 'noviewdiscussionspermission', 'hsuforum');
        }
    }

    /**
     * Get post nodes
     */
    public function postnodes_action() {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        $discussionid = required_param('discussionid', PARAM_INT);
        $discussion   = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum        = $PAGE->activityrecord;
        $course       = $COURSE;
        $cm           = $PAGE->cm;

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
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }

        $forumtracked = hsuforum_tp_is_tracked($forum);
        $posts        = hsuforum_get_all_discussion_posts($discussion->id, hsuforum_get_layout_mode_sort(HSUFORUM_MODE_NESTED), $forumtracked);
        $nodes        = array();

        if (!empty($posts[$post->id]) and !empty($posts[$post->id]->children)) {
            foreach ($posts[$post->id]->children as $post) {
                if ($node = $this->get_renderer()->post_to_node($cm, $discussion, $post)) {
                    $nodes[] = $node;
                }
            }
        }
        echo json_encode($nodes);
    }

    /**
     * Gets nested post HTML
     *
     * @throws coding_exception
     */
    public function postsnested_action() {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        require_once($CFG->dirroot.'/rating/lib.php');

        $discussionid = required_param('discussionid', PARAM_INT);
        $discussion   = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum        = $PAGE->activityrecord;
        $course       = $COURSE;
        $cm           = $PAGE->cm;
        $context      = $PAGE->context;

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
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }

        $forumtracked = hsuforum_tp_is_tracked($forum);
        $posts        = hsuforum_get_all_discussion_posts($discussion->id, hsuforum_get_layout_mode_sort(HSUFORUM_MODE_NESTED), $forumtracked);
        $output       = '';
        $canreply     = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);

        if ($forum->assessed != RATING_AGGREGATE_NONE) {
            $ratingoptions = new stdClass;
            $ratingoptions->context = $context;
            $ratingoptions->component = 'mod_hsuforum';
            $ratingoptions->ratingarea = 'post';
            $ratingoptions->items = $posts;
            $ratingoptions->aggregate = $forum->assessed;
            $ratingoptions->scaleid = $forum->scale;
            $ratingoptions->userid = $USER->id;
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/hsuforum/view.php?id=$cm->id";
            $ratingoptions->assesstimestart = $forum->assesstimestart;
            $ratingoptions->assesstimefinish = $forum->assesstimefinish;

            $rm = new rating_manager();
            $posts = $rm->get_ratings($ratingoptions);
        }
        if (!empty($posts[$post->id]) and !empty($posts[$post->id]->children)) {
            foreach ($posts[$post->id]->children as $post) {
                if ($postoutput = $this->get_renderer()->nested_post($cm, $discussion, $post, $canreply)) {
                    $output .= $postoutput;
                }
            }
        }
        echo json_encode((object) array(
            'html' => $output,
        ));
    }

    /**
     * Prints a discussion
     */
    public function postincontext_action() {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        $postid = required_param('postid', PARAM_INT);
        $forum  = $PAGE->activityrecord;
        $course = $COURSE;
        $cm     = $PAGE->cm;

        if (!$focuspost = hsuforum_get_post_full($postid)) {
            print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $focuspost->discussion), '*', MUST_EXIST);

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
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }

        $posts  = hsuforum_get_all_discussion_posts($discussion->id, hsuforum_get_layout_mode_sort(HSUFORUM_MODE_NESTED), false);
        $output = $this->get_renderer()->post_in_context($cm, $discussion, $posts[$post->id]);

        echo json_encode((object) array(
            'html' => html_writer::tag('div', $output, array('class' => 'mod_hsuforum_posts_container')),
        ));
    }

    /**
     * Marks a post as read
     *
     * @throws coding_exception
     */
    public function markread_action() {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        require_once($CFG->dirroot.'/rating/lib.php');

        $postid  = required_param('postid', PARAM_INT);
        $forum   = $PAGE->activityrecord;
        $course  = $COURSE;
        $cm      = $PAGE->cm;

        if (!$post = hsuforum_get_post_full($postid)) {
            print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);

        if ($forum->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
                || $discussion->timestart <= time())
                && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
                print_error('invaliddiscussionid', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
            }
        }
        if (!hsuforum_user_can_view_post($post, $course, $cm, $forum, $discussion)) {
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }
        if (hsuforum_tp_is_tracked($forum)) {
            hsuforum_tp_add_read_record($USER->id, $post->id);
        }
    }

    /**
     * Discussion subscription toggle
     */
    public function subscribedisc_action() {
        global $PAGE;

        require_sesskey();

        require_once(dirname(__DIR__).'/lib/discussion/subscribe.php');

        $discussionid = required_param('discussionid', PARAM_INT);
        $returnurl    = required_param('returnurl', PARAM_LOCALURL);

        $subscribe = new hsuforum_lib_discussion_subscribe($PAGE->activityrecord, $PAGE->context);

        if ($subscribe->is_subscribed($discussionid)) {
            $subscribe->unsubscribe($discussionid);
        } else {
            $subscribe->subscribe($discussionid);
        }
        if (!AJAX_SCRIPT) {
            redirect(new moodle_url($returnurl));
        }
    }

    public function discsubscribers_action() {
        global $OUTPUT, $USER, $DB, $COURSE, $PAGE;

        require_once(dirname(__DIR__).'/repository/discussion.php');
        require_once(dirname(__DIR__).'/lib/userselector/discussion/existing.php');
        require_once(dirname(__DIR__).'/lib/userselector/discussion/potential.php');

        $discussionid = required_param('discussionid', PARAM_INT);
        $edit         = optional_param('edit', -1, PARAM_BOOL); // Turn editing on and off

        $url = $PAGE->url;
        $url->param('discussionid', $discussionid);
        if ($edit !== 0) {
            $url->param('edit', $edit);
        }
        $PAGE->set_url($url);

        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum      = $PAGE->activityrecord;
        $course     = $COURSE;
        $cm         = $PAGE->cm;
        $context    = $PAGE->context;
        $repo       = new hsuforum_repository_discussion();

        if (hsuforum_is_forcesubscribed($forum)) {
            throw new coding_exception('Cannot manage discussion subscriptions when subscription is forced');
        }

        $currentgroup = groups_get_activity_group($cm);
        $options = array('forum'=>$forum, 'discussion' => $discussion, 'currentgroup'=>$currentgroup, 'context'=>$context);
        $existingselector = new hsuforum_userselector_discussion_existing('existingsubscribers', $options);
        $subscriberselector = new hsuforum_userselector_discussion_potential('potentialsubscribers', $options);

        if (data_submitted()) {
            require_sesskey();
            $subscribe = (bool)optional_param('subscribe', false, PARAM_RAW);
            $unsubscribe = (bool)optional_param('unsubscribe', false, PARAM_RAW);
            /** It has to be one or the other, not both or neither */
            if (!($subscribe xor $unsubscribe)) {
                print_error('invalidaction');
            }
            if ($subscribe) {
                $users = $subscriberselector->get_selected_users();
                foreach ($users as $user) {
                    $repo->subscribe($discussion->id, $user->id);
                }
            } else if ($unsubscribe) {
                $users = $existingselector->get_selected_users();
                foreach ($users as $user) {
                    $repo->unsubscribe($discussion->id, $user->id);
                }
            }
            $subscriberselector->invalidate_selected_users();
            $existingselector->invalidate_selected_users();

            redirect($PAGE->url);
        }

        $strsubscribers = get_string('discussionsubscribers', 'hsuforum');

        // This works but it doesn't make a good navbar, would have to change the settings menu...
        // $PAGE->settingsnav->find('discsubscribers', navigation_node::TYPE_SETTING)->make_active();

        $PAGE->navbar->add(shorten_text(format_string($discussion->name)), new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id)));
        $PAGE->navbar->add($strsubscribers);
        $PAGE->set_title($strsubscribers);
        $PAGE->set_heading($COURSE->fullname);
        if (has_capability('mod/hsuforum:managesubscriptions', $context)) {
            if ($edit != -1) {
                $USER->subscriptionsediting = $edit;
            }
            if (!empty($USER->subscriptionsediting)) {
                $string = get_string('turneditingoff');
                $edit = "off";
            } else {
                $string = get_string('turneditingon');
                $edit = "on";
            }
            $url = $PAGE->url;
            $url->param('edit', $edit);

            $PAGE->set_button($OUTPUT->single_button($url, $string, 'get'));
        } else {
            unset($USER->subscriptionsediting);
        }
        $output = $OUTPUT->heading($strsubscribers);
        if (empty($USER->subscriptionsediting)) {
            $output .= $this->get_renderer()->subscriber_overview(current($existingselector->find_users('')), $discussion->name, $course);
        } else {
            $output .= $this->get_renderer()->subscriber_selection_form($existingselector, $subscriberselector);
        }
        return $output;
    }
}