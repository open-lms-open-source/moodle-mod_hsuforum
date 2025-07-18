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
 * External forum API
 *
 * @package    mod_hsuforum
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

use core_external\external_api;
use core_external\external_value;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\util as external_util;
use core_external\external_files;
use core_external\external_format_value;
use core_external\external_warnings;


class mod_hsuforum_external extends external_api {

    /**
     * Describes the parameters for get_forum.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forums in a provided list of courses,
     * if no list is provided all forums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forum details
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses($courseids = array()) {
        global $CFG;

        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::get_forums_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Array to store the forums to return.
        $arrforums = array();
        $warnings = array();

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the forums in this course. This function checks users visibility permissions.
            $forums = get_all_instances_in_courses("hsuforum", $courses);
            foreach ($forums as $forum) {

                $course = $courses[$forum->course];
                $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id);
                $context = context_module::instance($cm->id);

                // Skip forums we are not allowed to see discussions.
                if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
                    continue;
                }

                $forum->name = \core_external\util::format_string($forum->name, $context->id);
                // Format the intro before being returning using the format setting.
                list($forum->intro, $forum->introformat) = \core_external\util::format_text($forum->intro, $forum->introformat,
                                                                                $context, 'mod_hsuforum', 'intro', 0);
                $forum->introfiles = external_util::get_area_files($context->id, 'mod_hsuforum', 'intro', false, false);
                // Discussions count. This function does static request cache.
                $forum->numdiscussions = hsuforum_count_discussions($forum, $cm, $course);
                $forum->cmid = $forum->coursemodule;
                $forum->cancreatediscussions = hsuforum_user_can_post_discussion($forum, null, -1, $cm, $context);
                $forum->istracked = true;
                if ($forum->istracked) {
                    $forum->unreadpostscount = hsuforum_count_forum_unread_posts($cm, $course);
                }

                // Add the forum to the array to return.
                $arrforums[$forum->id] = $forum;
            }
        }
        return $arrforums;
    }

    /**
     * Describes the get_forum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forum type'),
                    'name' => new external_value(PARAM_RAW, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The forum intro'),
                    'introformat' => new external_format_value('intro'),
                    'duedate' => new external_value(PARAM_INT, 'A due date to show in the calendar. Not used for grading.'),
                    'cutoffdate' => new external_value(PARAM_INT, 'The final date after which forum posts will no longer be accepted for this forum.'),
                    'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'grade_forum' => new external_value(PARAM_INT, 'Grade forum'),
                    'grade_forum_notify' => new external_value(PARAM_INT, 'Grade forum notify'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Tracking type'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'showrecent' => new external_value(PARAM_INT, 'Show recent posts on course page'),
                    'showsubstantive' => new external_value(PARAM_INT, 'Show toggle to mark posts as substantive'),
                    'showbookmark' => new external_value(PARAM_INT, 'Show toggle to bookmark posts'),
                    'allowprivatereplies' => new external_value(PARAM_INT, 'Allow private replies'),
                    'anonymous' => new external_value(PARAM_INT, 'Allow anonymous posts'),
                    'gradetype' => new external_value(PARAM_INT, 'Gradetype'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions'),
                    'cancreatediscussions' => new external_value(PARAM_BOOL, 'If the user can create discussions', VALUE_OPTIONAL),
                    'lockdiscussionafter' => new external_value(PARAM_INT, 'After what period a discussion is locked', VALUE_OPTIONAL),
                    'istracked' => new external_value(PARAM_BOOL, 'If the user is tracking the forum', VALUE_OPTIONAL),
                    'unreadpostscount' => new external_value(PARAM_INT, 'The number of unread posts for tracked forums', VALUE_OPTIONAL),
                ), 'hsuforum'
            )
        );
    }



    /**
     * Describes the parameters for get_forum_discussion_posts.
     *
     * @return external_function_parameters
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
            ),
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     *
     * @return array the forum post details
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts($discussionid) {
        global $CFG, $DB, $USER, $PAGE;

        $posts = array();
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(
            self::get_forum_discussion_posts_parameters(),
            array('discussionid' => $discussionid)
        );

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];

        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/hsuforum/discuss.php.
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        // Check they have the view forum capability.
        require_capability('mod/hsuforum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'hsuforum');

        if (! $post = hsuforum_get_post_full($discussion->firstpost)) {
            throw new \core\exception\moodle_exception('notexists', 'hsuforum');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
            throw new \core\exception\moodle_exception('noviewdiscussionspermission', 'hsuforum');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

        $allposts = hsuforum_get_all_discussion_posts($discussion->id);

        foreach ($allposts as $pid => $post) {

            if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm, false)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function hsuforum_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = array();
            }

            if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                // The post is available, but has been marked as deleted.
                // It will still be available but filled with a placeholder.
                $post->userid = null;
                $post->userfullname = null;
                $post->userpictureurl = null;
                $post->subject = get_string('privacy:request:delete:post:subject', 'mod_hsuforum');
                $post->message = get_string('privacy:request:delete:post:message', 'mod_hsuforum');
                $post->deleted = true;
                $posts[] = $post;
                continue;
            }
            $post->deleted = false;

            if (hsuforum_is_author_hidden($post, $forum)) {
                $post->userid = null;
                $post->userfullname = null;
                $post->userpictureurl = null;
            } else {
                $user = new stdclass();
                $user->id = $post->userid;
                $user = username_load_fields_from_object($user, $post, null, array('picture', 'imagealt', 'email'));
                $post->userfullname = fullname($user, $canviewfullname);

                $userpicture = new \core\output\user_picture($user);
                $userpicture->size = 1; // Size f1.
                $post->userpictureurl = $userpicture->get_url($PAGE)->out(false);
            }

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                \core_external\util::format_text($post->message, $post->messageformat, $modcontext, 'mod_hsuforum', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = external_util::get_area_files($modcontext->id, 'mod_hsuforum', 'attachment', $post->id);
            }
            $messageinlinefiles = external_util::get_area_files($modcontext->id, 'mod_hsuforum', 'post', $post->id);
            if (!empty($messageinlinefiles)) {
                $post->messageinlinefiles = $messageinlinefiles;
            }

            $posts[] = $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['ratinginfo'] = \core_rating\external\util::get_rating_info($forum, $modcontext, 'mod_hsuforum', 'post', $posts);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_forum_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts_returns() {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_value(PARAM_INT, 'The post message format'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'messageinlinefiles' => new external_files('post message inline files', VALUE_OPTIONAL),
                                'attachment' => new external_value(PARAM_RAW, 'Attachments'),
                                'attachments' => new external_files('attachments', VALUE_OPTIONAL),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                                'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                                'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL),
                                'deleted' => new external_value(PARAM_BOOL, 'This post has been removed.'),
                            ), 'post'
                        )
                    ),
                'ratinginfo' => \core_rating\external\util::external_ratings_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussions_paginated.
     *
     * @return external_function_parameters
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated_parameters() {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'hsuforum instance id', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of forum discussions optionally sorted and paginated.
     *
     * @param int $forumid the forum instance id
     * @param string $sortby sort by this element (id, timemodified, timestart or timeend)
     * @param string $sortdirection sort direction: ASC or DESC
     * @param int $page page number
     * @param int $perpage items per page
     *
     * @return array the forum discussion details including warnings
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated($forumid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                    $page = -1, $perpage = 0) {
        global $CFG, $DB, $USER, $PAGE;

        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_forum_discussions_paginated_parameters(),
            array(
                'forumid' => $forumid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage,
            )
        );

        // Compact/extract functions are not recommended.
        $forumid        = $params['forumid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new \core\exception\invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new \core\exception\invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $forum = $DB->get_record('hsuforum', array('id' => $forumid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        if ($forum->anonymous) {
            $result = array();
            $result['discussions'] = array();
            $warning = array();
            $warning['item'] = 'forum';
            $warning['itemid'] = $forum->id;
            $warning['warningcode'] = '1';
            $warning['message'] = 'Anonymous forums not supported yet';
            $warnings[] = $warning;
            $result['warnings'] = $warnings;
            return $result;
        }
        // Check they have the view forum capability.
        require_capability('mod/hsuforum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'hsuforum');

        $sort = 'd.pinned DESC, d.' . $sortby . ' ' . $sortdirection;
        $alldiscussions = hsuforum_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage, HSUFORUM_POSTS_ALL_USER_GROUPS);

        if ($alldiscussions) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = hsuforum_get_discussions_unread($cm);

            // The forum function returns the replies for all the discussions in a given forum.
            $replies = hsuforum_count_discussion_replies($forumid, $sort, -1, $page, $perpage);

            foreach ($alldiscussions as $discussion) {

                // This function checks for qanda forums.
                // Note that the hsuforum_get_discussions returns as id the post id, not the discussion id so we need to do this.
                $discussionrec = clone $discussion;
                $discussionrec->id = $discussion->discussion;
                if (!hsuforum_user_can_see_discussion($forum, $discussionrec, $modcontext)) {
                    $warning = array();
                    // Function hsuforum_get_discussions returns forum_posts ids not forum_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if (isset($unreads[$discussion->discussion])) {
                    $discussion->numunread = (int) $unreads[$discussion->discussion];
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int) $replies[$discussion->discussion]->replies;
                }

                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    \core_external\util::format_text($discussion->message, $discussion->messageformat,
                                            $modcontext, 'mod_hsuforum', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = external_util::get_area_files($modcontext->id, 'mod_hsuforum', 'attachment',
                                                                                $discussion->id);
                }
                $messageinlinefiles = external_util::get_area_files($modcontext->id, 'mod_hsuforum', 'post', $discussion->id);
                if (!empty($messageinlinefiles)) {
                    $discussion->messageinlinefiles = $messageinlinefiles;
                }

                $discussion->locked = hsuforum_discussion_is_locked($forum, $discussion);
                $discussion->canreply = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

                if (hsuforum_is_author_hidden($discussion, $forum)) {
                    $discussion->userid = null;
                    $discussion->userfullname = null;
                    $discussion->userpictureurl = null;

                    $discussion->usermodified = null;
                    $discussion->usermodifiedfullname = null;
                    $discussion->usermodifiedpictureurl = null;
                } else {
                    $picturefields = \core_user\fields::get_picture_fields();

                    // Load user objects from the results of the query.
                    $user = new stdclass();
                    $user->id = $discussion->userid;
                    $user = username_load_fields_from_object($user, $discussion, null, $picturefields);
                    // Preserve the id, it can be modified by username_load_fields_from_object.
                    $user->id = $discussion->userid;
                    $discussion->userfullname = fullname($user, $canviewfullname);

                    $userpicture = new \core\output\user_picture($user);
                    $userpicture->size = 1; // Size f1.
                    $discussion->userpictureurl = $userpicture->get_url($PAGE)->out(false);

                    $usermodified = new stdclass();
                    $usermodified->id = $discussion->usermodified;
                    $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um', $picturefields);
                    // Preserve the id (it can be overwritten due to the prefixed $picturefields).
                    $usermodified->id = $discussion->usermodified;
                    $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);

                    $userpicture = new \core\output\user_picture($usermodified);
                    $userpicture->size = 1; // Size f1.
                    $discussion->usermodifiedpictureurl = $userpicture->get_url($PAGE)->out(false);
                }

                $discussions[] = $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_forum_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                                'groupid' => new external_value(PARAM_INT, 'Group id'),
                                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                                'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                                'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                                'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'messageinlinefiles' => new external_files('post message inline files', VALUE_OPTIONAL),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_files('attachments', VALUE_OPTIONAL),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                                'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                                'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                                'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.'),
                                'pinned' => new external_value(PARAM_BOOL, 'Is the discussion pinned'),
                                'locked' => new external_value(PARAM_BOOL, 'Is the discussion locked'),
                                'canreply' => new external_value(PARAM_BOOL, 'Can the user reply to the discussion'),
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussions.
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function get_forum_discussions_parameters() {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'forum instance id', VALUE_REQUIRED),
                'sortorder' => new external_value(PARAM_INT,
                    'sort by this element: numreplies, , created or timemodified', VALUE_DEFAULT, -1),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of hsuforum discussions optionally sorted and paginated.
     *
     * @param int $forumid the forum instance id
     * @param int $sortorder The sort order
     * @param int $page page number
     * @param int $perpage items per page
     * @param int $groupid the user course group
     *
     *
     * @return array the hsuforum discussion details including warnings
     * @since Moodle 3.7
     */
    public static function get_forum_discussions(int $forumid, ?int $sortorder = -1, ?int $page = -1,
                                                 ?int $perpage = 0, ?int $groupid = 0) {

        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_forum_discussions_parameters(),
            array(
                'forumid' => $forumid,
                'sortorder' => $sortorder,
                'page' => $page,
                'perpage' => $perpage,
                'groupid' => $groupid
            )
        );

        // Compact/extract functions are not recommended.
        $forumid        = $params['forumid'];
        $sortorder      = $params['sortorder'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];
        $groupid        = $params['groupid'];

        $vaultfactory = \mod_hsuforum\local\container::get_vault_factory();
        $discussionlistvault = $vaultfactory->get_discussions_in_forum_vault();

        $sortallowedvalues = array(
            $discussionlistvault::SORTORDER_LASTPOST_DESC,
            $discussionlistvault::SORTORDER_LASTPOST_ASC,
            $discussionlistvault::SORTORDER_CREATED_DESC,
            $discussionlistvault::SORTORDER_CREATED_ASC,
            $discussionlistvault::SORTORDER_REPLIES_DESC,
            $discussionlistvault::SORTORDER_REPLIES_ASC
        );

        // If sortorder not defined set a default one.
        if ($sortorder == -1) {
            $sortorder = $discussionlistvault::SORTORDER_LASTPOST_DESC;
        }

        if (!in_array($sortorder, $sortallowedvalues)) {
            throw new \core\exception\invalid_parameter_exception('Invalid value for sortorder parameter (value: ' . $sortorder . '),' .
                ' allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $managerfactory = \mod_hsuforum\local\container::get_manager_factory();
        $urlfactory = \mod_hsuforum\local\container::get_url_factory();
        $legacydatamapperfactory = mod_hsuforum\local\container::get_legacy_data_mapper_factory();

        $forumvault = $vaultfactory->get_forum_vault();
        $forum = $forumvault->get_from_id($forumid);
        if (!$forum) {
            throw new \core\exception\moodle_exception("Unable to find hsuforum with id {$forumid}");
        }
        $forumdatamapper = $legacydatamapperfactory->get_forum_data_mapper();
        $forumrecord = $forumdatamapper->to_legacy_object($forum);

        $capabilitymanager = $managerfactory->get_capability_manager($forum);

        $course = $DB->get_record('course', array('id' => $forum->get_course_id()), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->get_id(), $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        $canseeanyprivatereply = $capabilitymanager->can_view_any_private_reply($USER);

        // Check they have the view hsuforum capability.
        if (!$capabilitymanager->can_view_discussions($USER)) {
            throw new \core\exception\moodle_exception('noviewdiscussionspermission', 'hsuforum');
        }

        $alldiscussions = mod_hsuforum_get_discussion_summaries($forum, $USER, $groupid, $sortorder, $page, $perpage);

        if ($alldiscussions) {
            $discussionids = array_keys($alldiscussions);

            $postvault = $vaultfactory->get_post_vault();
            $postdatamapper = $legacydatamapperfactory->get_post_data_mapper();
            // Return the reply count for each discussion in a given hsuforum.
            $replies = $postvault->get_reply_count_for_discussion_ids($USER, $discussionids, $canseeanyprivatereply);
            // Return the first post for each discussion in a given hsuforum.
            $firstposts = $postvault->get_first_post_for_discussion_ids($discussionids);

            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forum_tp_can_track_forums($forumrecord)) {
                if ($forumtracked = forum_tp_is_tracked($forumrecord)) {
                    $unreads = $postvault->get_unread_count_for_discussion_ids($USER, $discussionids, $canseeanyprivatereply);
                }
            }

            $canlock = $capabilitymanager->can_manage_forum($USER);

            $usercontext = context_user::instance($USER->id);
            $ufservice = core_favourites\service_factory::get_service_for_user_context($usercontext);

            $canfavourite = has_capability('mod/hsuforum:cantogglefavourite', $modcontext, $USER);

            foreach ($alldiscussions as $discussionsummary) {
                $discussion = $discussionsummary->get_discussion();
                $firstpostauthor = $discussionsummary->get_first_post_author();
                $latestpostauthor = $discussionsummary->get_latest_post_author();

                // This function checks for qanda hsuforums.
                $canviewdiscussion = $capabilitymanager->can_view_discussion($USER, $discussion);
                if (!$canviewdiscussion) {
                    $warning = array();
                    // Function forum_get_discussions returns forum_posts ids not forum_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->get_id();
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $firstpost = $firstposts[$discussion->get_first_post_id()];
                $discussionobject = $postdatamapper->to_legacy_object($firstpost);
                // Fix up the types for these properties.
                $discussionobject->mailed = $discussionobject->mailed ? 1 : 0;
                $discussionobject->messagetrust = $discussionobject->messagetrust ? 1 : 0;
                $discussionobject->mailnow = $discussionobject->mailnow ? 1 : 0;
                $discussionobject->groupid = $discussion->get_group_id();
                $discussionobject->timemodified = $discussion->get_time_modified();
                $discussionobject->usermodified = $discussion->get_user_modified();
                $discussionobject->timestart = $discussion->get_time_start();
                $discussionobject->timeend = $discussion->get_time_end();
                $discussionobject->pinned = $discussion->is_pinned();

                $discussionobject->numunread = 0;
                if ($cantrack && $forumtracked) {
                    if (isset($unreads[$discussion->get_id()])) {
                        $discussionobject->numunread = (int) $unreads[$discussion->get_id()];
                    }
                }

                $discussionobject->numreplies = 0;
                if (!empty($replies[$discussion->get_id()])) {
                    $discussionobject->numreplies = (int) $replies[$discussion->get_id()];
                }

                $discussionobject->name = \core_external\util::format_string($discussion->get_name(), $modcontext);
                $discussionobject->subject = \core_external\util::format_string($discussionobject->subject, $modcontext);
                // Rewrite embedded images URLs.
                $options = array('trusted' => $discussionobject->messagetrust);
                list($discussionobject->message, $discussionobject->messageformat) =
                    \core_external\util::format_text($discussionobject->message, $discussionobject->messageformat,
                        $modcontext, 'mod_hsuforum', 'post', $discussionobject->id, $options);

                // List attachments.
                if (!empty($discussionobject->attachment)) {
                    $discussionobject->attachments = external_util::get_area_files($modcontext->id, 'mod_hsuforum',
                        'attachment', $discussionobject->id);
                }
                $messageinlinefiles = external_util::get_area_files($modcontext->id, 'mod_hsuforum', 'post',
                    $discussionobject->id);
                if (!empty($messageinlinefiles)) {
                    $discussionobject->messageinlinefiles = $messageinlinefiles;
                }

                $discussionobject->locked = $forum->is_discussion_locked($discussion);
                $discussionobject->canlock = $canlock;
                $discussionobject->starred = !empty($ufservice) ? $ufservice->favourite_exists('mod_hsuforum', 'discussions',
                    $discussion->get_id(), $modcontext) : false;
                $discussionobject->canreply = $capabilitymanager->can_post_in_discussion($USER, $discussion);
                $discussionobject->canfavourite = $canfavourite;

                if (forum_is_author_hidden($discussionobject, $forumrecord)) {
                    $discussionobject->userid = null;
                    $discussionobject->userfullname = null;
                    $discussionobject->userpictureurl = null;

                    $discussionobject->usermodified = null;
                    $discussionobject->usermodifiedfullname = null;
                    $discussionobject->usermodifiedpictureurl = null;

                } else {
                    $discussionobject->userfullname = $firstpostauthor->get_full_name();
                    $discussionobject->userpictureurl = $urlfactory->get_author_profile_image_url($firstpostauthor, null, 2)
                        ->out(false);

                    $discussionobject->usermodifiedfullname = $latestpostauthor->get_full_name();
                    $discussionobject->usermodifiedpictureurl = $urlfactory->get_author_profile_image_url(
                        $latestpostauthor, null, 2)->out(false);
                }

                $discussions[] = (array) $discussionobject;
            }
        }
        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;

        return $result;
    }

    /**
     * Describes the get_forum_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 3.7
     */
    public static function get_forum_discussions_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'name' => new external_value(PARAM_RAW, 'Discussion name'),
                            'groupid' => new external_value(PARAM_INT, 'Group id'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                            'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                            'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                            'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'modified' => new external_value(PARAM_INT, 'Time modified'),
                            'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                            'subject' => new external_value(PARAM_RAW, 'The post subject'),
                            'message' => new external_value(PARAM_RAW, 'The post message'),
                            'messageformat' => new external_format_value('message'),
                            'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                            'messageinlinefiles' => new external_files('post message inline files', VALUE_OPTIONAL),
                            'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                            'attachments' => new external_files('attachments', VALUE_OPTIONAL),
                            'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                            'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                            'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                            'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                            'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                            'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                            'numreplies' => new external_value(PARAM_INT, 'The number of replies in the discussion'),
                            'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.'),
                            'pinned' => new external_value(PARAM_BOOL, 'Is the discussion pinned'),
                            'locked' => new external_value(PARAM_BOOL, 'Is the discussion locked'),
                            'starred' => new external_value(PARAM_BOOL, 'Is the discussion starred'),
                            'canreply' => new external_value(PARAM_BOOL, 'Can the user reply to the discussion'),
                            'canlock' => new external_value(PARAM_BOOL, 'Can the user lock the discussion'),
                            'canfavourite' => new external_value(PARAM_BOOL, 'Can the user star the discussion'),
                        ), 'post'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Toggle the favouriting value for the discussion provided
     *
     * @param int $discussionid The discussion we need to favourite
     * @param bool $targetstate The state of the favourite value
     * @return array The exported discussion
     */
    public static function toggle_favourite_state($discussionid, $targetstate) {
        global $DB, $PAGE, $USER;

        $params = self::validate_parameters(self::toggle_favourite_state_parameters(), [
            'discussionid' => $discussionid,
            'targetstate' => $targetstate
        ]);

        $vaultfactory = mod_hsuforum\local\container::get_vault_factory();
        // Get the discussion vault and the corresponding discussion entity.
        $discussionvault = $vaultfactory->get_discussion_vault();
        $discussion = $discussionvault->get_from_id($params['discussionid']);

        $forumvault = $vaultfactory->get_forum_vault();
        $forum = $forumvault->get_from_id($discussion->get_forum_id());
        $forumcontext = $forum->get_context();
        self::validate_context($forumcontext);

        $managerfactory = mod_hsuforum\local\container::get_manager_factory();
        $capabilitymanager = $managerfactory->get_capability_manager($forum);

        // Does the user have the ability to favourite the discussion?
        if (!$capabilitymanager->can_favourite_discussion($USER)) {
            throw new \core\exception\moodle_exception('cannotfavourite', 'hsuforum');
        }
        $usercontext = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $isfavourited = $ufservice->favourite_exists('mod_hsuforum', 'discussions', $discussion->get_id(), $forumcontext);

        $favouritefunction = $targetstate ? 'create_favourite' : 'delete_favourite';
        if ($isfavourited != (bool) $params['targetstate']) {
            $ufservice->{$favouritefunction}('mod_hsuforum', 'discussions', $discussion->get_id(), $forumcontext);
        }

        $exporterfactory = mod_hsuforum\local\container::get_exporter_factory();
        $builder = mod_hsuforum\local\container::get_builder_factory()->get_exported_discussion_builder();
        $favourited = ($builder->is_favourited($discussion, $forumcontext, $USER) ? [$discussion->get_id()] : []);
        $exporter = $exporterfactory->get_discussion_exporter($USER, $forum, $discussion, [], $favourited);
        return $exporter->export($PAGE->get_renderer('mod_hsuforum'));
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function toggle_favourite_state_returns() {
        return discussion_exporter::get_read_structure();
    }

    /**
     * Defines the parameters for the toggle_favourite_state method
     *
     * @return external_function_parameters
     */
    public static function toggle_favourite_state_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'The discussion to subscribe or unsubscribe'),
                'targetstate' => new external_value(PARAM_BOOL, 'The target state')
            ]
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forum_parameters() {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'forum instance id'),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $forumid the forum instance id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws \core\exception\moodle_exception
     */
    public static function view_forum($forumid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::view_forum_parameters(),
                                            array(
                                                'forumid' => $forumid,
                                            ));
        $warnings = array();
        $discussions = array();

        // Request and permission validation.
        $forum = $DB->get_record('hsuforum', array('id' => $params['forumid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'hsuforum');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/hsuforum:viewdiscussion', $context, null, true, 'noviewdiscussionspermission', 'hsuforum');

        // Call the hsuforum/lib API.
        hsuforum_view($forum, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forum_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forum_discussion_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion id'),
            ),
        );
    }

    /**
     * Trigger the discussion viewed event.
     *
     * @param int $discussionid the discussion id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws \core\exception\moodle_exception
     */
    public static function view_forum_discussion($discussionid) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::view_forum_discussion_parameters(),
                                            array(
                                                'discussionid' => $discussionid,
                                            ));
        $warnings = array();

        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $params['discussionid']), '*', MUST_EXIST);
        $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'hsuforum');

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        require_capability('mod/hsuforum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'hsuforum');

        // Call the hsuforum/lib API.
        hsuforum_discussion_view($modcontext, $forum, $discussion);

        hsuforum_mark_discussion_read($USER, $discussion->id);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forum_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_post_parameters() {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post id we are going to reply to
                                                (can be the initial discussion post'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'message' => new external_value(PARAM_RAW, 'new post message (only html format allowed)'),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'the value of the option,
                                                            this param is validated in the external function.'
                                        ),
                    )
                ), 'Options', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @since Moodle 3.0
     * @throws \core\exception\moodle_exception
     */
    public static function add_discussion_post($postid, $subject, $message, $options = array()) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::add_discussion_post_parameters(),
            array(
                'postid' => $postid,
                'subject' => $subject,
                'message' => $message,
                'options' => $options,
            )
        );
        $warnings = array();

        if (!$parent = hsuforum_get_post_full($params['postid'])) {
            throw new \core\exception\moodle_exception('invalidparentpostid', 'hsuforum');
        }

        if (!$discussion = $DB->get_record("hsuforum_discussions", array("id" => $parent->discussion))) {
            throw new \core\exception\moodle_exception('notpartofdiscussion', 'hsuforum');
        }

        // Request and permission validation.
        $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'hsuforum');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null,
            'privatereplyto' => 0,
            'wordcount' => null,
            'charcount' => null,
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                case 'privatereplyto':
                case 'wordcount':
                case 'charcount':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    // Ensure that the user has permissions to create attachments.
                    if (!has_capability('mod/hsuforum:createattachment', $context)) {
                        $value = 0;
                    }
                    break;
                default:
                    throw new \core\exception\moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        if (!hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context)) {
            throw new \core\exception\moodle_exception('nopostforum', 'hsuforum');
        }

        $thresholdwarning = hsuforum_check_throttling($forum, $cm);
        hsuforum_check_blocking_threshold($thresholdwarning);

        // Create the post.
        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = $parent->id;
        $post->subject = $params['subject'];
        $post->message = $params['message'];
        $post->messageformat = FORMAT_HTML;   // Force formatting for now.
        $post->messagetrust = trusttext_trusted($context);
        $post->reveal = 0;
        $post->flags = 0;
        $post->privatereply = 0;
        $post->privatereplyto = $options['privatereplyto'];
        $post->wordcount = $options['wordcount'];
        $post->charcount = $options['charcount'];
        $post->itemid = $options['inlineattachmentsid'];
        $post->attachments   = $options['attachmentsid'];
        $post->deleted = 0;
        $fakemform = $post->attachments;
        if ($postid = hsuforum_add_new_post($post, $fakemform)) {

            $post->id = $postid;

            // Trigger events and completion.
            $params = array(
                'context' => $context,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                ),
            );
            $event = \mod_hsuforum\event\post_created::create($params);
            $event->add_record_snapshot('hsuforum_posts', $post);
            $event->add_record_snapshot('hsuforum_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            hsuforum_post_subscription($settings, $forum, $discussion);
        } else {
            throw new \core\exception\moodle_exception('couldnotadd', 'hsuforum');
        }

        $result = array();
        $result['postid'] = $postid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_post_returns() {
        return new external_single_structure(
            array(
                'postid' => new external_value(PARAM_INT, 'new post id'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum instance ID'),
                'subject' => new external_value(PARAM_TEXT, 'New Discussion subject'),
                'message' => new external_value(PARAM_RAW, 'New Discussion message (only html format allowed)'),
                'groupid' => new external_value(PARAM_INT, 'The group, default to 0', VALUE_DEFAULT, 0),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        discussionpinned    (bool); is the discussion pinned, default to false
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'The value of the option,
                                                            This param is validated in the external function.'
                                        ),
                    )
                ), 'Options', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Add a new discussion into an existing forum.
     *
     * @param int $forumid the forum instance id
     * @param string $subject new discussion subject
     * @param string $message new discussion message (only html format allowed)
     * @param int $groupid the user course group
     * @param array $options optional settings
     * @return array of warnings and the new discussion id
     * @since Moodle 3.0
     * @throws \core\exception\moodle_exception
     */
    public static function add_discussion($forumid, $subject, $message, $groupid = 0, $options = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::add_discussion_parameters(),
                                            array(
                                                'forumid' => $forumid,
                                                'subject' => $subject,
                                                'message' => $message,
                                                'groupid' => $groupid,
                                                'options' => $options,
                                            ));

        $warnings = array();

        // Request and permission validation.
        $forum = $DB->get_record('hsuforum', array('id' => $params['forumid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'hsuforum');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'discussionpinned' => false,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null,
            'timelocked' => 0,
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'discussionpinned':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                case 'timelocked':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    // Ensure that the user has permissions to create attachments.
                    if (!has_capability('mod/hsuforum:createattachment', $context)) {
                        $value = 0;
                    }
                    break;
                default:
                    throw new \core\exception\moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        // Normalize group.
        if (!groups_get_activity_groupmode($cm)) {
            // Groups not supported, force to -1.
            $groupid = -1;
        } else {
            // Check if we receive the default or and empty value for groupid,
            // in this case, get the group for the user in the activity.
            if (empty($params['groupid'])) {
                $groupid = groups_get_activity_group($cm);
            } else {
                // Here we rely in the group passed, hsuforum_user_can_post_discussion will validate the group.
                $groupid = $params['groupid'];
            }
        }

        if (!hsuforum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
            throw new \core\exception\moodle_exception('cannotcreatediscussion', 'hsuforum');
        }

        $thresholdwarning = hsuforum_check_throttling($forum, $cm);
        hsuforum_check_blocking_threshold($thresholdwarning);

        // Create the discussion.
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->message = $params['message'];
        $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = $options['inlineattachmentsid'];
        $discussion->groupid = $groupid;
        $discussion->mailnow = 0;
        $discussion->subject = $params['subject'];
        $discussion->name = $discussion->subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->reveal = 0;
        $discussion->attachments = $options['attachmentsid'];
        $discussion->timelocked = $options['timelocked'];

        if (has_capability('mod/hsuforum:pindiscussions', $context) && $options['discussionpinned']) {
            $discussion->pinned = HSUFORUM_DISCUSSION_PINNED;
        } else {
            $discussion->pinned = HSUFORUM_DISCUSSION_UNPINNED;
        }
        $fakemform = $options['attachmentsid'];
        if ($discussionid = hsuforum_add_discussion($discussion, $fakemform)) {

            $discussion->id = $discussionid;

            // Trigger events and completion.

            $params = array(
                'context' => $context,
                'objectid' => $discussion->id,
                'other' => array(
                    'forumid' => $forum->id,
                ),
            );
            $event = \mod_hsuforum\event\discussion_created::create($params);
            $event->add_record_snapshot('hsuforum_discussions', $discussion);
            $event->trigger();

            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($forum->completiondiscussions || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            hsuforum_post_subscription($settings, $forum, $discussion);
        } else {
            throw new \core\exception\moodle_exception('couldnotadd', 'hsuforum');
        }

        $result = array();
        $result['discussionid'] = $discussionid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_returns() {
        return new external_single_structure(
            array(
                'discussionid' => new external_value(PARAM_INT, 'New Discussion ID'),
                'warnings' => new external_warnings(),
            ),
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function can_add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum instance ID'),
                'groupid' => new external_value(PARAM_INT, 'The group to check, default to active group.
                                                Use -1 to check if the user can post in all the groups.', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Check if the current user can add discussions in the given forum (and optionally for the given group).
     *
     * @param int $forumid the forum instance id
     * @param int $groupid the group to check, default to active group. Use -1 to check if the user can post in all the groups.
     * @return array of warnings and the status (true if the user can add discussions)
     * @since Moodle 3.1
     * @throws \core\exception\moodle_exception
     */
    public static function can_add_discussion($forumid, $groupid = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/hsuforum/lib.php");

        $params = self::validate_parameters(self::can_add_discussion_parameters(),
                                            array(
                                                'forumid' => $forumid,
                                                'groupid' => $groupid,
                                            ));
        $warnings = array();

        // Request and permission validation.
        $forum = $DB->get_record('hsuforum', array('id' => $params['forumid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'hsuforum');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $status = hsuforum_user_can_post_discussion($forum, $params['groupid'], -1, $cm, $context);

        $result = array();
        $result['status'] = $status;
        $result['canpindiscussions'] = has_capability('mod/hsuforum:pindiscussions', $context);
        $result['cancreateattachment'] = hsuforum_can_create_attachment($forum, $context);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function can_add_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if the user can add discussions, false otherwise.'),
                'canpindiscussions' => new external_value(PARAM_BOOL, 'True if the user can pin discussions, false otherwise.',
                    VALUE_OPTIONAL),
                'cancreateattachment' => new external_value(PARAM_BOOL, 'True if the user can add attachments, false otherwise.',
                    VALUE_OPTIONAL),
                'warnings' => new external_warnings(),
            )
        );
    }

}
