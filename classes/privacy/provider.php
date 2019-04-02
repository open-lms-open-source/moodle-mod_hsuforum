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
 * Privacy Subsystem implementation for mod_hsuforum.
 *
 * @package    mod_hsuforum
 */

namespace mod_hsuforum\privacy;

use \core_privacy\local\request\userlist;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\local\request\deletion_criteria;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\helper as request_helper;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;
use \core_comment\privacy\provider as comments_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Implementation of the privacy subsystem plugin provider for the forum activity module.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin\provider interface.
    \core_privacy\local\request\plugin\provider,

    // This plugin is capable of determining which users have data within it.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin has some sitewide user preferences to export.
    \core_privacy\local\request\user_preference_provider {

    use \core_privacy\local\legacy_polyfill;
    use subcontext_info;

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function _get_metadata(collection $items) : collection {
        // The 'forum' table does not store any specific user data.
        $items->add_database_table('hsuforum_digests', [
            'forum' => 'privacy:metadata:hsuforum_digests:hsuforum',
            'userid' => 'privacy:metadata:hsuforum_digests:userid',
            'maildigest' => 'privacy:metadata:hsuforum_digests:maildigest',
        ], 'privacy:metadata:hsuforum_digests');

        // The 'hsuforum_discussions' table stores the metadata about each forum discussion.
        $items->add_database_table('hsuforum_discussions', [
            'name' => 'privacy:metadata:hsuforum_discussions:name',
            'userid' => 'privacy:metadata:hsuforum_discussions:userid',
            'assessed' => 'privacy:metadata:hsuforum_discussions:assessed',
            'timemodified' => 'privacy:metadata:hsuforum_discussions:timemodified',
            'usermodified' => 'privacy:metadata:hsuforum_discussions:usermodified',
        ], 'privacy:metadata:hsuforum_discussions');

        // The 'hsuforum_posts' table stores the metadata about each forum discussion.
        $items->add_database_table('hsuforum_posts', [
            'discussion' => 'privacy:metadata:hsuforum_posts:discussion',
            'parent' => 'privacy:metadata:hsuforum_posts:parent',
            'created' => 'privacy:metadata:hsuforum_posts:created',
            'modified' => 'privacy:metadata:hsuforum_posts:modified',
            'subject' => 'privacy:metadata:hsuforum_posts:subject',
            'message' => 'privacy:metadata:hsuforum_posts:message',
            'userid' => 'privacy:metadata:hsuforum_posts:userid',
        ], 'privacy:metadata:hsuforum_posts');

        // The 'hsuforum_discussion_subs' table stores information about which discussions a user is subscribed to.
        $items->add_database_table('hsuforum_subscriptions_disc', [
            'discussion' => 'privacy:metadata:hsuforum_subscriptions_disc:discussion',
            'userid' => 'privacy:metadata:hsuforum_subscriptions_disc:userid',
        ], 'privacy:metadata:hsuforum_subscriptions_disc');

        //The 'hsuforum_queue' table contains user data, but it is only a temporary cache of other data.
        $items->add_database_table('hsuforum_queue', [
            'userid' => 'privacy:metadata:hsuforum_queue:userid',
            'discussionid' => 'privacy:metadata:hsuforum_queue:discussionid',
            'postid' => 'privacy:metadata:hsuforum_queue:postid',
            'timemodified' => 'privacy:metadata:hsuforum_queue:timemodified'
        ], 'privacy:metadata:hsuforum_queue');

        // The 'hsuforum_read' table stores data about which forum posts have been read by each user.
        $items->add_database_table('hsuforum_read', [
            'userid' => 'privacy:metadata:hsuforum_read:userid',
            'discussionid' => 'privacy:metadata:hsuforum_read:discussionid',
            'postid' => 'privacy:metadata:hsuforum_read:postid',
            'firstread' => 'privacy:metadata:hsuforum_read:firstread',
            'lastread' => 'privacy:metadata:hsuforum_read:lastread',
        ], 'privacy:metadata:hsuforum_read');

        // The 'hsuforum_subscriptions' table stores information about which forums a user is subscribed to.
        $items->add_database_table('hsuforum_subscriptions', [
            'userid' => 'privacy:metadata:hsuforum_subscriptions:userid',
            'forum' => 'privacy:metadata:hsuforum_subscriptions:forum',
        ], 'privacy:metadata:hsuforum_subscriptions');

        // The 'hsuforum_subscriptions' table stores information about which forums a user is subscribed to.
        $items->add_database_table('hsuforum_track_prefs', [
            'userid' => 'privacy:metadata:hsuforum_track_prefs:userid',
            'forumid' => 'privacy:metadata:hsuforum_track_prefs:forumid',
        ], 'privacy:metadata:hsuforum_track_prefs');

        // Forum posts can be tagged and rated.
        $items->link_subsystem('core_tag', 'privacy:metadata:core_tag');
        $items->link_subsystem('core_rating', 'privacy:metadata:core_rating');

        // There are several user preferences.
        $items->add_user_preference('maildigest', 'privacy:metadata:preference:maildigest');
        $items->add_user_preference('autosubscribe', 'privacy:metadata:preference:autosubscribe');
        $items->add_user_preference('trackforums', 'privacy:metadata:preference:trackforums');
        $items->add_user_preference('markasreadonnotification', 'privacy:metadata:preference:markasreadonnotification');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int $userid   The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function _get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $params = [
            'modname'       => 'hsuforum',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];

        // Discussion creators.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                 WHERE d.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Post authors.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                  JOIN {hsuforum_posts} p ON p.discussion = d.id
                 WHERE p.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Forum digest records.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_digests} dig ON dig.forum = f.id
                 WHERE dig.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Forum subscriptions.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_subscriptions} sub ON sub.forum = f.id
                 WHERE sub.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Discussion subscriptions.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_subscriptions_disc} dsub ON dsub.discussion = f.id
                 WHERE dsub.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Discussion tracking preferences.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_track_prefs} pref ON pref.forumid = f.id
                 WHERE pref.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Discussion read records.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_read} hasread ON hasread.forumid = f.id
                 WHERE hasread.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Rating authors.
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_hsuforum', 'post', 'p.id', $userid, true);
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                  JOIN {hsuforum_posts} p ON p.discussion = d.id
                  {$ratingsql->join}
                 WHERE {$ratingsql->userwhere}
        ";
        $params += $ratingsql->params;
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param   userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist)  {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid' => $context->instanceid,
            'modulename' => 'hsuforum',
        ];

        // Discussion authors.
        $sql = "SELECT d.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Forum authors.
        $sql = "SELECT p.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                  JOIN {hsuforum_posts} p ON d.id = p.discussion
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Forum post ratings.
        $sql = "SELECT p.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                  JOIN {hsuforum_posts} p ON d.id = p.discussion
                 WHERE cm.id = :instanceid";
        \core_rating\privacy\provider::get_users_in_context_from_sql($userlist, 'rat', 'mod_hsuforum', 'post', $sql, $params);

        // Forum Digest settings.
        $sql = "SELECT dig.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_digests} dig ON dig.forum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Forum Subscriptions.
        $sql = "SELECT sub.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_subscriptions} sub ON sub.forum = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Read Posts.
        $sql = "SELECT hasread.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_read} hasread ON hasread.forumid = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Tracking Preferences.
        $sql = "SELECT pref.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_track_prefs} pref ON pref.forumid = f.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param  int $userid The userid of the user whose data is to be exported.
     */
    public static function _export_user_preferences(int $userid) {
        $user = \core_user::get_user($userid);

        switch ($user->maildigest) {
            case 1:
                $digestdescription = get_string('emaildigestcomplete');
                break;
            case 2:
                $digestdescription = get_string('emaildigestsubjects');
                break;
            case 0:
            default:
                $digestdescription = get_string('emaildigestoff');
                break;
        }
        writer::export_user_preference('mod_hsuforum', 'maildigest', $user->maildigest, $digestdescription);

        switch ($user->autosubscribe) {
            case 0:
                $subscribedescription = get_string('autosubscribeno');
                break;
            case 1:
            default:
                $subscribedescription = get_string('autosubscribeyes');
                break;
        }
        writer::export_user_preference('mod_hsuforum', 'autosubscribe', $user->autosubscribe, $subscribedescription);

        switch ($user->trackforums) {
            case 0:
                $trackforumdescription = get_string('trackforumsno');
                break;
            case 1:
            default:
                $trackforumdescription = get_string('trackforumsyes');
                break;
        }
        writer::export_user_preference('mod_hsuforum', 'trackforums', $user->trackforums, $trackforumdescription);

        $markasreadonnotification = get_user_preferences('markasreadonnotification', null, $user->id);
        if (null !== $markasreadonnotification) {
            switch ($markasreadonnotification) {
                case 0:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationno', 'mod_hsuforum');
                    break;
                case 1:
                default:
                    $markasreadonnotificationdescription = get_string('markasreadonnotificationyes', 'mod_hsuforum');
                    break;
            }
            writer::export_user_preference('mod_hsuforum', 'markasreadonnotification', $markasreadonnotification, $markasreadonnotificationdescription);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist)) {
            return;
        }
        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = $contextparams;

        // Digested forums.
        $sql = "SELECT
                    c.id AS contextid,
                    dig.maildigest AS maildigest
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_digests} dig ON dig.forum = f.id
                 WHERE (
                    dig.userid = :userid AND
                    c.id {$contextsql}
                )
        ";
        $params['userid'] = $userid;
        $digests = $DB->get_records_sql_menu($sql, $params);

        // Forum subscriptions.
        $sql = "SELECT
                    c.id AS contextid,
                    sub.userid AS subscribed
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_subscriptions} sub ON sub.forum = f.id
                 WHERE (
                    sub.userid = :userid AND
                    c.id {$contextsql}
                )
        ";
        $params['userid'] = $userid;
        $subscriptions = $DB->get_records_sql_menu($sql, $params);

        // Tracked forums.
        $sql = "SELECT 
                    c.id AS contextid,
                    pref.userid AS tracked
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {hsuforum} f ON f.id = cm.instance
                  JOIN {hsuforum_track_prefs} pref ON pref.forumid = f.id
                 WHERE (
                    pref.userid = :userid AND
                    c.id {$contextsql}
                )
        ";
        $params['userid'] = $userid;
        $tracked = $DB->get_records_sql_menu($sql, $params);

        $sql = "SELECT
                    c.id AS contextid,
                    f.*,
                    cm.id AS cmid
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid
                  JOIN {hsuforum} f ON f.id = cm.instance
                 WHERE (
                    c.id {$contextsql}
                )
        ";

        $params += $contextparams;

        // Keep a mapping of forumid to contextid.
        $mappings = [];
        $forums = $DB->get_recordset_sql($sql, $params);

        foreach ($forums as $forum) {
            $mappings[$forum->id] = $forum->contextid;

            $context = \context::instance_by_id($mappings[$forum->id]);

            // Store the main forum data.
            $data = request_helper::get_context_data($context, $user);
            writer::with_context($context)
                ->export_data([], $data);
            request_helper::export_context_files($context, $user);

            // Store relevant metadata about this forum instance.
            if (isset($digests[$forum->contextid])) {
                static::export_digest_data($userid, $forum, $digests[$forum->contextid]);
            }
            if (isset($subscriptions[$forum->contextid])) {
                static::export_subscription_data($userid, $forum, $subscriptions[$forum->contextid]);
            }
            if (isset($tracked[$forum->contextid])) {
                static::export_tracking_data($userid, $forum, $tracked[$forum->contextid]);
            }
            comments_provider::export_comments($context, 'mod_hsuforum', 'userposts_comments', $userid, [], false);

        }
        $forums->close();
        if (!empty($mappings)) {
            // Store all discussion data for this forum.
            static::export_discussion_data($userid, $mappings);

            // Store all post data for this forum.
            static::export_all_posts($userid, $mappings);
        }
    }

    /**
     * Store all information about all discussions that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   array       $mappings A list of mappings from forumid => contextid.
     * @return  array       Which forums had data written for them.
     */
    protected static function export_discussion_data(int $userid, array $mappings) {
        global $DB;

        // Find all of the discussions, and discussion subscriptions for this forum.
        list($hsuforuminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $sql = "SELECT d.*,
                       gg.finalgrade AS finalgrade,
                       gg.rawgrademax AS grademax,
                       gg.timemodified AS gradetimemodified,
                       gg.rawgrade AS grade,
                       gg.feedback AS gradefeedback, 
                       g.name AS groupname,
                       subs.discussion AS subscribedto
                  FROM {hsuforum} f
            INNER JOIN {hsuforum_discussions} d ON d.forum = f.id
             LEFT JOIN {hsuforum_subscriptions_disc} subs ON subs.discussion = d.id
             LEFT JOIN {groups} g ON g.id = d.groupid
             LEFT JOIN {grade_items} gi ON gi.iteminstance = f.id AND gi.itemmodule = 'hsuforum'
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = d.userid
             LEFT JOIN {hsuforum_posts} p ON p.discussion = d.id
                 WHERE f.id ${hsuforuminsql}
                   AND (
                        d.userid    = :discussionuserid OR
                        p.userid    = :postuserid OR
                        subs.id IS NOT NULL
                   )
        ";

        $params = [
            'postuserid' => $userid,
            'discussionuserid' => $userid,
        ];
        $params += $forumparams;

        // Keep track of the forums which have data.
        $forumswithdata = [];

        $discussions = $DB->get_recordset_sql($sql, $params);
        foreach ($discussions as $discussion) {

            $forumswithdata[$discussion->forum] = true;
            $context = \context::instance_by_id($mappings[$discussion->forum]);

            // Store related metadata for this discussion.
            static::export_discussion_subscription_data($userid, $context, $discussion);


            $discussiondata = (object)[
                'name' => format_string($discussion->name, true),
                'pinned' => transform::yesno((bool)$discussion->pinned),
                'timemodified' => transform::datetime($discussion->timemodified),
                'usermodified' => transform::datetime($discussion->usermodified),
                'creator_was_you' => transform::yesno($discussion->userid == $userid)
            ];

            $discussionarea = static::get_discussion_area($discussion);
            // Store the discussion content.
            writer::with_context($context)
                ->export_data($discussionarea, $discussiondata);
            if ($discussion->finalgrade != null) {
                self::export_grade_data($discussion, $context, $discussionarea);
            }
        }

        $discussions->close();

        return $forumswithdata;
    }

    /**
     * Formats and then exports the user's grade data.
     *
     * @param  \stdClass $grade   The assign grade object
     * @param  \context  $context The context object
     * @param  array     $area
     */
    protected static function export_grade_data(\stdClass $data, \context $context, array $area) {
        $gradedata = (object)[
            'timemodified' => transform::datetime($data->gradetimemodified),
            'grademax' => $data->grademax,
            'grade' => $data->grade,
            'finalgrade' => $data->finalgrade,
        ];

        if (!empty($data->gradefeedback)) {
            $gradedata->feedback = $data->gradefeedback;
        }
        writer::with_context($context)
            ->export_related_data($area, 'grade', $gradedata);
    }

    /**
     * Store all information about the discusison grades.
     * @params
     */

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   array       $mappings A list of mappings from forumid => contextid.
     * @return  array       Which forums had data written for them.
     */
    protected static function export_all_posts(int $userid, array $mappings) {
        global $DB;

        // Find all of the posts, and post subscriptions for this forum.
        list($hsuforuminsql, $forumparams) = $DB->get_in_or_equal(array_keys($mappings), SQL_PARAMS_NAMED);
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_hsuforum', 'post', 'p.id', $userid);
        $sql = "SELECT p.discussion AS id,
                       f.id AS forumid,
                       d.name,
                       d.groupid
                  FROM {hsuforum} f
                  JOIN {hsuforum_discussions} d ON d.forum = f.id
                  JOIN {hsuforum_posts} p ON p.discussion = d.id
             LEFT JOIN {hsuforum_read} fr ON fr.postid = p.id AND fr.userid = :readuserid
            {$ratingsql->join}
                 WHERE f.id ${hsuforuminsql} AND
                (
                    p.userid = :postuserid OR
                    fr.userid IS NOT NULL OR
                    {$ratingsql->userwhere}
                )
              GROUP BY f.id, p.discussion, d.name, d.groupid
        ";

        $params = [
            'postuserid' => $userid,
            'readuserid' => $userid,
        ];
        $params += $forumparams;
        $params += $ratingsql->params;

        $discussions = $DB->get_records_sql($sql, $params);
        foreach ($discussions as $discussion) {
            $context = \context::instance_by_id($mappings[$discussion->forumid]);
            static::export_all_posts_in_discussion($userid, $context, $discussion);
        }
    }

    /**
     * Store all information about all posts that we have detected this user to have access to.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   \stdClass       $discussion The discussion whose data is being exported.
     */
    protected static function export_all_posts_in_discussion(int $userid, \context $context, \stdClass $discussion) {
        global $DB, $USER;

        $discussionid = $discussion->id;

        // Find all of the posts, and post subscriptions for this forum.
        $ratingsql = \core_rating\privacy\provider::get_sql_join('rat', 'mod_hsuforum', 'post', 'p.id', $userid);
        $sql = "SELECT
                    p.*,
                    d.forum AS forumid,
                    fr.firstread,
                    fr.lastread,
                    fr.id AS readflag,
                    rat.id AS hasratings
                    FROM {hsuforum_discussions} d
              INNER JOIN {hsuforum_posts} p ON p.discussion = d.id
               LEFT JOIN {hsuforum_read} fr ON fr.postid = p.id AND fr.userid = :readuserid
            {$ratingsql->join} AND {$ratingsql->userwhere}
                   WHERE d.id = :discussionid
        ";

        $params = [
            'discussionid' => $discussionid,
            'readuserid' => $userid,
        ];
        $params += $ratingsql->params;

        // Keep track of the forums which have data.
        $structure = (object)[
            'children' => [],
        ];

        $posts = $DB->get_records_sql($sql, $params);
        foreach ($posts as $post) {
            $post->hasdata = (isset($post->hasdata)) ? $post->hasdata : false;
            $post->hasdata = $post->hasdata || !empty($post->hasratings);
            $post->hasdata = $post->hasdata || $post->readflag;
            $post->hasdata = $post->hasdata || ($post->userid == $USER->id);

            if (0 == $post->parent) {
                $structure->children[$post->id] = $post;
            } else {
                if (empty($posts[$post->parent]->children)) {
                    $posts[$post->parent]->children = [];
                }
                $posts[$post->parent]->children[$post->id] = $post;
            }

            // Set all parents.
            if ($post->hasdata) {
                $curpost = $post;
                while ($curpost->parent != 0) {
                    $curpost = $posts[$curpost->parent];
                    $curpost->hasdata = true;
                }
            }
        }
        $discussionarea = static::get_discussion_area($discussion);
        $discussionarea[] = get_string('posts', 'mod_hsuforum');
        static::export_posts_in_structure($userid, $context, $discussionarea, $structure);
    }

    /**
     * Export all posts in the provided structure.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   array           $parentarea The subcontext fo the parent post.
     * @param   \stdClass       $structure  The post structure and all of its children
     */
    protected static function export_posts_in_structure(int $userid, \context $context, $parentarea, \stdClass $structure) {
        foreach ($structure->children as $post) {
            if (!$post->hasdata) {
                // This tree has no content belonging to the user. Skip it and all children.
                continue;
            }

            $postarea = array_merge($parentarea, static::get_post_area($post));

            // Store the post content.
            static::export_post_data($userid, $context, $postarea, $post);

            if (isset($post->children)) {
                // Now export children of this post.
                static::export_posts_in_structure($userid, $context, $postarea, $post);
            }
        }
    }

    /**
     * Export all data in the post.
     *
     * @param   int             $userid     The userid of the user whose data is to be exported.
     * @param   \context_module The         instance of the forum context.
     * @param   array           $parentarea The subcontext fo the parent post.
     * @param   \stdClass       $structure  The post structure and all of its children
     */
    protected static function export_post_data(int $userid, \context $context, $postarea, $post) {
        // Store related metadata.
        static::export_read_data($userid, $context, $postarea, $post);

        $postdata = (object)[
            'subject' => format_string($post->subject, true),
            'created' => transform::datetime($post->created),
            'modified' => transform::datetime($post->modified),
            'author_was_you' => transform::yesno($post->userid == $userid),
        ];

        $postdata->message = writer::with_context($context)
            ->rewrite_pluginfile_urls($postarea, 'mod_hsuforum', 'post', $post->id, $post->message);

        $postdata->message = format_text($postdata->message, $post->messageformat, (object)[
            'para' => false,
            'trusted' => $post->messagetrust,
            'context' => $context,
        ]);
        writer::with_context($context)
            // Store the post.
            ->export_data($postarea, $postdata)

            // Store the associated files.
            ->export_area_files($postarea, 'mod_hsuforum', 'post', $post->id)
            // Attachements.
            ->export_area_files($postarea, 'mod_hsuforum', 'attachment', $post->id);
            ;
        if ($post->userid == $userid) {
            // Store all ratings against this post as the post belongs to the user. All ratings on it are ratings of their content.
            \core_rating\privacy\provider::export_area_ratings($userid, $context, $postarea, 'mod_hsuforum', 'post', $post->id, false);

            // Store all tags against this post as the tag belongs to the user.
            \core_tag\privacy\provider::export_item_tags($userid, $context, $postarea, 'mod_hsuforum', 'hsuforum_posts', $post->id);
        }

        // Check for any ratings that the user has made on this post.
        \core_rating\privacy\provider::export_area_ratings($userid, $context, $postarea, 'mod_hsuforum', 'post', $post->id, $userid, true);
    }

    /**
     * Store data about daily digest preferences
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $forum The forum whose data is being exported.
     * @param   int         $maildigest The mail digest setting for this forum.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_digest_data(int $userid, \stdClass $forum, int $maildigest) {
        if (null !== $maildigest) {
            // The user has a specific maildigest preference for this forum.
            $a = (object)[
                'forum' => format_string($forum->name, true),
            ];

            switch ($maildigest) {
                case 0:
                    $a->type = get_string('emaildigestoffshort', 'mod_hsuforum');
                    break;
                case 1:
                    $a->type = get_string('emaildigestcompleteshort', 'mod_hsuforum');
                    break;
                case 2:
                    $a->type = get_string('emaildigestsubjectsshort', 'mod_hsuforum');
                    break;
            }

            writer::with_context(\context_module::instance($forum->cmid))
                ->export_metadata([], 'digestpreference', $maildigest,
                    get_string('privacy:digesttypepreference', 'mod_hsuforum', $a));

            return true;
        }

        return false;
    }

    /**
     * Store data about whether the user subscribes to forum.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $forum The forum whose data is being exported.
     * @param   int         $subscribed if the user is subscribed
     * @return  bool        Whether any data was stored.
     */
    protected static function export_subscription_data(int $userid, \stdClass $forum, int $subscribed) {
        if (null !== $subscribed) {
            // The user is subscribed to this forum.
            writer::with_context(\context_module::instance($forum->cmid))
                ->export_metadata([], 'subscriptionpreference', 1, get_string('privacy:subscribedtoforum', 'mod_hsuforum'));

            return true;
        }

        return false;
    }

    /**
     * Store data about whether the user subscribes to this particular discussion.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context_module The instance of the forum context.
     * @param   \stdClass   $discussion The discussion whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_discussion_subscription_data(int $userid, \context_module $context, \stdClass $discussion) {
        global $DB;
        $area = static::get_discussion_area($discussion);
        if (null !== $discussion->subscribedto) {
            // The user has a specific subscription preference for this discussion.
            $a = (object)[];
            $subscribed = mod_hsuforum\subscriptions::is_subscribed($userid, $DB->get_record('hsuforum', array('id' => $discussion->forum)));
            if ($subscribed) {
                $a->preference = get_string('subscribed', 'mod_hsuforum');
            } else {
                $a->preference = get_string('unsubscribed', 'mod_hsuforum');
            }
            writer::with_context($context)
                ->export_metadata(
                    $area,
                    'subscriptionpreference',
                    $discussion->subscribedto,
                    get_string('privacy:discussionsubscriptionpreference', 'mod_hsuforum', $a)
                );

            return true;
        }

        return true;
    }

    /**
     * Store forum read-tracking data about a particular forum.
     *
     * This is whether a forum has read-tracking enabled or not.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \stdClass   $forum The forum whose data is being exported.
     * @param   int         $tracke if the user is subscribed
     * @return  bool        Whether any data was stored.
     */
    protected static function export_tracking_data(int $userid, \stdClass $forum, int $tracked) {
        if (null !== $tracked) {
            // The user has a main preference to track all forums, but has opted out of this one.
            writer::with_context(\context_module::instance($forum->cmid))
                ->export_metadata([], 'trackreadpreference', 0, get_string('privacy:readtrackingdisabled', 'mod_hsuforum'));

            return true;
        }

        return false;
    }

    /**
     * Store read-tracking information about a particular forum post.
     *
     * @param   int         $userid The userid of the user whose data is to be exported.
     * @param   \context_module The instance of the forum context.
     * @param   \stdClass   $post The post whose data is being exported.
     * @return  bool        Whether any data was stored.
     */
    protected static function export_read_data(int $userid, \context_module $context, array $postarea, \stdClass $post) {
        if (null !== $post->firstread) {
            $a = (object)[
                'firstread' => $post->firstread,
                'lastread' => $post->lastread,
            ];

            writer::with_context($context)
                ->export_metadata(
                    $postarea,
                    'postread',
                    (object)[
                        'firstread' => $post->firstread,
                        'lastread' => $post->lastread,
                    ],
                    get_string('privacy:postwasread', 'mod_hsuforum', $a)
                );

            return true;
        }

        return false;
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context|context $context $context  The specific context to delete data for.
     * @throws \coding_exception
     */
    public static function _delete_data_for_all_users_in_context($context) {
        global $DB;
        // Check that this is a context_module.
        if (!$context instanceof \context_module) {
            return;
        }

        // Get the course module.
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $forum = $DB->get_record('hsuforum', ['id' => $cm->instance]);
        if ($forum) {
            $DB->delete_records('hsuforum_track_prefs', ['forumid' => $forum->id]);
            $DB->delete_records('hsuforum_subscriptions', ['forum' => $forum->id]);
            $DB->delete_records('hsuforum_read', ['forumid' => $forum->id]);
            $DB->delete_records('hsuforum_digests', ['forum' => $forum->id]);

            // Delete all discussion items.
            $DB->delete_records_select(
                'hsuforum_queue',
                "discussionid IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forum)",
                [
                    'forum' => $forum->id,
                ]
            );

            $DB->delete_records_select(
                'hsuforum_posts',
                "discussion IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forum)",
                [
                    'forum' => $forum->id,
                ]
            );

            $DB->delete_records('hsuforum_subscriptions_disc', ['discussion' => $forum->id]);
            $DB->delete_records('hsuforum_discussions', ['forum' => $forum->id]);
        }


        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_hsuforum', 'post');
        $fs->delete_area_files($context->id, 'mod_hsuforum', 'attachment');

        // Delete all ratings in the context.
        \core_rating\privacy\provider::delete_ratings($context, 'mod_hsuforum', 'post');

        // Delete all Tags.
        \core_tag\privacy\provider::delete_item_tags($context, 'mod_hsuforum', 'hsuforum_posts');

    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        foreach ($contextlist as $context) {
            // Get the course module.
            $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
            $forum = $DB->get_record('hsuforum', ['id' => $cm->instance]);

            $DB->delete_records('hsuforum_track_prefs', [
                'forumid' => $forum->id,
                'userid' => $userid,
            ]);
            $DB->delete_records('hsuforum_subscriptions', [
                'forum' => $forum->id,
                'userid' => $userid,
            ]);
            $DB->delete_records('hsuforum_read', [
                'forumid' => $forum->id,
                'userid' => $userid,
            ]);

            $DB->delete_records('hsuforum_digests', [
                'forum' => $forum->id,
                'userid' => $userid,
            ]);

            // Delete all discussion items.
            $DB->delete_records_select(
                'hsuforum_queue',
                "userid = :userid AND discussionid IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forum)",
                [
                    'userid' => $userid,
                    'forum' => $forum->id,
                ]
            );

            $DB->delete_records('hsuforum_subscriptions_disc', [
                'discussion' => $forum->id,
                'userid' => $userid,
            ]);

            // Do not delete discussion or forum posts.
            // Instead update them to reflect that the content has been deleted.
            $postsql = "userid = :userid AND discussion IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forum)";
            $postidsql = "SELECT fp.id FROM {hsuforum_posts} fp WHERE {$postsql}";
            $postparams = [
                'forum' => $forum->id,
                'userid' => $userid,
            ];

            // Update the subject.
            $DB->set_field_select('hsuforum_posts', 'subject', '', $postsql, $postparams);

            // Update the subject and its format.
            $DB->set_field_select('hsuforum_posts', 'message', '', $postsql, $postparams);
            $DB->set_field_select('hsuforum_posts', 'messageformat', FORMAT_PLAIN, $postsql, $postparams);

            // Mark the post as deleted.
            $DB->set_field_select('hsuforum_posts', 'deleted', 1, $postsql, $postparams);

            // Note: Do _not_ delete ratings of other users. Only delete ratings on the users own posts.
            // Ratings are aggregate fields and deleting the rating of this post will have an effect on the rating
            // of any post.
            \core_rating\privacy\provider::delete_ratings_select($context, 'mod_hsuforum', 'post',
                "IN ($postidsql)", $postparams);

            \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_hsuforum', 'hsuforum_posts',
                "IN ($postidsql)", $postparams);

            // Delete all files from the posts.
            $fs = get_file_storage();
            $fs->delete_area_files_select($context->id, 'mod_hsuforum', 'post', "IN ($postidsql)", $postparams);
            $fs->delete_area_files_select($context->id, 'mod_hsuforum', 'attachment', "IN ($postidsql)", $postparams);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $forum = $DB->get_record('hsuforum', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['forumid' => $forum->id], $userinparams);

        $DB->delete_records_select('hsuforum_track_prefs', "forumid = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('hsuforum_subscriptions', "forum = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select('hsuforum_read', "forumid = :forumid AND userid {$userinsql}", $params);
        $DB->delete_records_select(
            'hsuforum_queue',
            "userid {$userinsql} AND discussionid IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forumid)",
            $params
        );

        // Do not delete discussion or forum posts.
        // Instead update them to reflect that the content has been deleted.
        $postsql = "userid {$userinsql} AND discussion IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :forumid)";
        $postidsql = "SELECT fp.id FROM {hsuforum_posts} fp WHERE {$postsql}";

        // Update the subject.
        $DB->set_field_select('hsuforum_posts', 'subject', '', $postsql, $params);

        // Update the subject and its format.
        $DB->set_field_select('hsuforum_posts', 'message', '', $postsql, $params);
        $DB->set_field_select('hsuforum_posts', 'messageformat', FORMAT_PLAIN, $postsql, $params);

        // Mark the post as deleted.
        $DB->set_field_select('hsuforum_posts', 'deleted', 1, $postsql, $params);

        // Note: Do _not_ delete ratings of other users. Only delete ratings on the users own posts.
        // Ratings are aggregate fields and deleting the rating of this post will have an effect on the rating
        // of any post.
        \core_rating\privacy\provider::delete_ratings_select($context, 'mod_hsuforum', 'post', "IN ($postidsql)", $params);

        // Delete all Tags.
        \core_tag\privacy\provider::delete_item_tags_select($context, 'mod_hsuforum', 'hsuforum_posts', "IN ($postidsql)", $params);

        // Delete all files from the posts.
        $fs = get_file_storage();
        $fs->delete_area_files_select($context->id, 'mod_hsuforum', 'post', "IN ($postidsql)", $params);
        $fs->delete_area_files_select($context->id, 'mod_hsuforum', 'attachment', "IN ($postidsql)", $params);
    }
}
