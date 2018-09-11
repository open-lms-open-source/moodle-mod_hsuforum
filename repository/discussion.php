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
 * Discussion Repository Mapper
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/abstract.php');

class hsuforum_repository_discussion extends hsuforum_repository_abstract {

    /**
     * @param int $forumid
     * @param int $userid
     * @return array
     */
    public function get_user_subscriptions($forumid, $userid) {
        return $this->get_db()->get_records_sql_menu("
            SELECT s.id, s.discussion
              FROM {hsuforum_subscriptions_disc} s
        INNER JOIN {hsuforum_discussions} d ON d.id = s.discussion
             WHERE s.userid = ?
               AND d.forum = ?
        ", array($userid, $forumid));
    }

    /**
     * @param $forum
     * @param $discussion
     * @param context_module $context
     * @param int $groupid
     * @param null $fields
     * @param array $search
     * @param string $sort
     * @return array
     */
    public function get_unsubscribed_users($forum, $discussion, context_module $context, $groupid=0, $fields = null, array $search = array(), $sort = 'u.lastname ASC, u.firstname ASC') {
        global $DB, $CFG;

        if ($forum->forcesubscribe == HSUFORUM_DISALLOWSUBSCRIBE or hsuforum_is_forcesubscribed($forum)) {
            return array();
        }
        if (is_null($fields)) {
            $fields = "u.id,
                      u.username,
                      u.firstname,
                      u.lastname,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforums,
                      u.mnethostid";
        }

        // only active enrolled users or everybody on the frontpage
        list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
        $params['discussionid'] = $discussion->id;
        $params['forumid'] = $forum->id;

        $where = '';
        if (!empty($search)) {
            $where .= ' AND '.$search[0];
            $params = array_merge($params, $search[1]);
        }
        $results = $DB->get_records_sql("
            SELECT $fields
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
   LEFT OUTER JOIN {hsuforum_subscriptions_disc} s ON s.userid = u.id AND s.discussion = :discussionid
   LEFT OUTER JOIN {hsuforum_subscriptions} fs ON fs.userid = u.id AND fs.forum = :forumid
             WHERE s.id IS NULL AND fs.id IS NULL$where
             ORDER BY $sort
        ", $params);

        // Guest user should never be subscribed to a forum.
        unset($results[$CFG->siteguest]);

        return $results;
    }

    /**
     * Get the users subscribed to the discussion
     *
     * @param stdClass $forum
     * @param stdClass $discussion
     * @param context_module $context
     * @param int $groupid
     * @param null|string $fields
     * @param array $search
     * @param string $sort
     * @return array
     */
    public function get_subscribed_users($forum, $discussion, context_module $context, $groupid=0, $fields = null, array $search = array(), $sort = 'u.lastname ASC, u.firstname ASC') {
        global $CFG;

        if ($forum->forcesubscribe == HSUFORUM_DISALLOWSUBSCRIBE or hsuforum_is_forcesubscribed($forum)) {
            return array();
        }
        if (is_null($fields)) {
            $fields = "u.id,
                      u.alternatename,
                      u.username,
                      u.firstname,
                      u.firstnamephonetic,
                      u.lastname,
                      u.lastnamephonetic,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.middlename,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforums,
                      u.mnethostid";
        }

        list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
        $params['discussionid'] = $discussion->id;

        $where = '';
        if (!empty($search)) {
            $where .= ' AND '.$search[0];
            $params = array_merge($params, $search[1]);
        }
        $results = $this->get_db()->get_records_sql("
            SELECT $fields
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
              JOIN {hsuforum_subscriptions_disc} s ON s.userid = u.id
             WHERE s.discussion = :discussionid$where
          ORDER BY $sort
        ", $params);

        // Guest user should never be subscribed to a forum.
        unset($results[$CFG->siteguest]);

        return $results;
    }

    /**
     * @param int $discussionid
     * @param int $userid
     * @return hsuforum_repository_discussion
     */
    public function subscribe($discussionid, $userid) {
        $params = array('userid' => $userid, 'discussion' => $discussionid);
        if (!$this->get_db()->record_exists('hsuforum_subscriptions_disc', $params)) {
            $this->get_db()->insert_record('hsuforum_subscriptions_disc', $params);
        }
        return $this;
    }

    /**
     * @param int $discussionid
     * @param int $userid
     * @return hsuforum_repository_discussion
     */
    public function unsubscribe($discussionid, $userid) {
        $this->get_db()->delete_records(
            'hsuforum_subscriptions_disc',
            array('userid' => $userid, 'discussion' => $discussionid)
        );
        return $this;
    }

    /**
     * @param int $forumid
     * @param int $userid
     * @return hsuforum_repository_discussion
     */
    public function unsubscribe_all($forumid, $userid) {
        $sql = "DELETE FROM {hsuforum_subscriptions_disc}
                WHERE userid = :uid AND discussion IN (SELECT id FROM {hsuforum_discussions} WHERE forum = :fid)";
        $this->get_db()->execute($sql, array('fid' => $forumid, 'uid' => $userid));
        return $this;
    }
}
