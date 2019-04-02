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
 * Open Forum subscription manager.
 *
 * @package    mod_hsuforum
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Open Forum subscription manager.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const FORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for forums.
     *
     * The first level key is the user ID
     * The second level is the forum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $forumcache = array();

    /**
     * The list of forums which have been wholly retrieved for the forum subscription cache.
     *
     * This allows for prior caching of an entire forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedforums = array();

    /**
     * The subscription cache for forum discussions.
     *
     * The first level key is the user ID
     * The second level is the forum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $forumdiscussioncache = array();

    /**
     * The list of forums which have been wholly retrieved for the forum discussion subscription cache.
     *
     * This allows for prior caching of an entire forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedforums = array();

    /**
     * Whether a user is subscribed to this forum, or a discussion within
     * the forum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the forum preference.
     *
     * If it is not specified then only the forum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $forum The record of the forum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $forum, $discussionid = null, $cm = null) {
        // If forum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($forum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($forum->course)->instances['forum'][$forum->id];
            }
            if (has_capability('mod/hsuforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_forum($userid, $forum);
        }

        $subscriptions = self::fetch_discussion_subscription($forum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if ($subscriptions) {
            return ($subscriptions != self::FORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_forum($userid, $forum);
    }

    /**
     * Whether a user is subscribed to this forum.
     *
     * @param int $userid The user ID
     * @param \stdClass $forum The record of the forum to test
     * @return boolean
     */
    protected static function is_subscribed_to_forum($userid, $forum) {
        return self::fetch_subscription_cache($forum->id, $userid);
    }

    /**
     * Helper to determine whether a forum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function is_forcesubscribed($forum) {
        return ($forum->forcesubscribe == HSUFORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a forum has it's subscription mode set to disabled.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function subscription_disabled($forum) {
        return ($forum->forcesubscribe == FORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified forum can be subscribed to.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function is_subscribable($forum) {
        return (isloggedin() && !isguestuser() &&
            !\mod_hsuforum\subscriptions::is_forcesubscribed($forum) &&
            !\mod_hsuforum\subscriptions::subscription_disabled($forum));
    }

    /**
     * Fetch the forum subscription data for the specified userid and forum.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($forumid, $userid) {
        if (isset(self::$forumcache[$userid]) && isset(self::$forumcache[$userid][$forumid])) {
            return self::$forumcache[$userid][$forumid];
        }
        self::fill_subscription_cache($forumid, $userid);

        if (!isset(self::$forumcache[$userid]) || !isset(self::$forumcache[$userid][$forumid])) {
            return false;
        }

        return self::$forumcache[$userid][$forumid];
    }

    /**
     * Fill the forum subscription data for the specified userid and forum.
     *
     * If the userid is not specified, then all subscription data for that forum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($forumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedforums[$forumid])) {
            // This forum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$forumcache[$userid])) {
                    self::$forumcache[$userid] = array();
                }

                if (!isset(self::$forumcache[$userid][$forumid])) {
                    if ($DB->record_exists('hsuforum_subscriptions', array(
                        'userid' => $userid,
                        'forum' => $forumid,
                    ))) {
                        self::$forumcache[$userid][$forumid] = true;
                    } else {
                        self::$forumcache[$userid][$forumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('hsuforum_subscriptions', array(
                    'forum' => $forumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$forumcache[$data->userid])) {
                        self::$forumcache[$data->userid] = array();
                    }
                    self::$forumcache[$data->userid][$forumid] = true;
                }
                self::$fetchedforums[$forumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and forum.
     *
     * This is returned as an array of discussions for that forum which contain the preference in a stdClass.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the forum.
     */
    public static function fetch_discussion_subscription($forumid, $userid = null) {
        self::fill_discussion_subscription_cache($forumid, $userid);

        if (!isset(self::$forumdiscussioncache[$userid]) || !isset(self::$forumdiscussioncache[$userid][$forumid])) {
            return array();
        }

        return self::$forumdiscussioncache[$userid][$forumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and forum.
     *
     * If the userid is not specified, then all discussion subscription data for that forum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($forumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedforums[$forumid])) {
            // This forum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$forumdiscussioncache[$userid])) {
                    self::$forumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$forumdiscussioncache[$userid][$forumid])) {
                    $subscriptions = $DB->get_recordset('hsuforum_subscriptions_disc', array(
                        'userid' => $userid,
                    ), null, 'id, discussion');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($forumid, $userid, $data->discussion);
                    }
                    $subscriptions->close();
                }
            }
        }
    }

    /**
     * Add the specified discussion to the discussion subscription cache.
     *
     * @param int $forumid The ID of the forum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     */
    protected static function add_to_discussion_cache($forumid, $userid, $discussion) {
        if (!isset(self::$forumdiscussioncache[$userid])) {
            self::$forumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$forumdiscussioncache[$userid][$forumid])) {
            self::$forumdiscussioncache[$userid][$forumid] = array();
        }

        self::$forumdiscussioncache[$userid][$forumid] = $discussion;
    }
}
