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
 * Discussion Subscription Management
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(__DIR__)).'/repository/discussion.php');

class hsuforum_lib_discussion_subscribe {
    /**
     * @var context_module
     */
    protected $context;

    /**
     * @var stdClass
     */
    protected $forum;

    /**
     * @var int
     */
    protected $userid;

    /**
     * @var hsuforum_repository_discussion
     */
    protected $repo;

    /**
     * Can subscribe cache
     *
     * @var array
     */
    static protected $cancache = array();

    /**
     * Is subscribed cache
     *
     * @var array
     */
    static protected $iscache = array();

    /**
     * @param $forum
     * @param context_module $context
     * @param null|int $userid
     * @param hsuforum_repository_discussion|null $repo
     */
    public function __construct($forum, context_module $context, $userid = null, hsuforum_repository_discussion $repo = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }
        if (is_null($repo)) {
            $repo = new hsuforum_repository_discussion();
        }
        $this->set_forum($forum)
             ->set_context($context)
             ->set_userid($userid)
             ->set_repo($repo);
    }

    /**
     * @param \stdClass $forum
     * @return hsuforum_lib_discussion_subscribe
     */
    public function set_forum($forum) {
        $this->forum = $forum;
        return $this;
    }

    /**
     * @return \stdClass
     */
    public function get_forum() {
        return $this->forum;
    }

    /**
     * @param \context_module $context
     * @return hsuforum_lib_discussion_subscribe
     */
    public function set_context(context_module $context) {
        $this->context = $context;
        return $this;
    }

    /**
     * @return \context_module
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * @param \hsuforum_repository_discussion $repo
     * @return hsuforum_lib_discussion_subscribe
     */
    public function set_repo(hsuforum_repository_discussion $repo) {
        $this->repo = $repo;
        return $this;
    }

    /**
     * @return \hsuforum_repository_discussion
     */
    public function get_repo() {
        return $this->repo;
    }

    /**
     * @param int $userid
     * @return hsuforum_lib_discussion_subscribe
     */
    public function set_userid($userid) {
        $this->userid = $userid;
        return $this;
    }

    /**
     * @return int
     */
    public function get_userid() {
        return $this->userid;
    }

    /**
     * @throws moodle_exception
     */
    public function require_can_subscribe() {
        if (!$this->can_subscribe()) {
            throw new moodle_exception('cansubscribediscerror', 'hsuforum');
        }
    }

    /**
     * Order from cheapest operation to most expensive to keep things zippy!
     *
     * @return bool
     */
    protected function _can_subscribe() {
        if ($this->get_forum()->forcesubscribe == HSUFORUM_DISALLOWSUBSCRIBE) {
            return false;
        }
        if ($this->get_forum()->type == 'single') {
            return false;
        }
        if (!has_capability('mod/hsuforum:viewdiscussion', $this->get_context(), $this->get_userid())) {
            return false;
        }
        if (hsuforum_is_subscribed($this->get_userid(), $this->get_forum())) {
            return false;
        }
        return true;
    }

    /**
     * Determine if the user can subscribe to discussions
     *
     * @return mixed
     */
    public function can_subscribe() {
        $forumid = $this->get_forum()->id;
        list($course, $cm) = get_course_and_cm_from_instance($forumid, 'hsuforum');
        if (is_guest(context_module::instance($cm->id))) {
            return false;
        }
        if (empty(self::$cancache[$forumid]) or !array_key_exists($this->get_userid(), self::$cancache[$forumid])) {
            self::$cancache[$forumid][$this->get_userid()] = $this->_can_subscribe();
        }
        return self::$cancache[$forumid][$this->get_userid()];
    }

    /**
     * Determine if the user is subscribed to a specific discussion
     *
     * @param int $discussionid
     * @return bool
     */
    public function is_subscribed($discussionid) {
        $forumid = $this->get_forum()->id;
        if (empty(self::$iscache[$forumid]) or !array_key_exists($this->get_userid(), self::$iscache[$forumid])) {
            self::$iscache[$forumid][$this->get_userid()] = $this->get_repo()->get_user_subscriptions($forumid, $this->get_userid());
        }
        return in_array($discussionid, self::$iscache[$forumid][$this->get_userid()]);
    }

    /**
     * Subscribe to a discussion
     *
     * @param int $discussionid
     * @return hsuforum_lib_discussion_subscribe
     */
    public function subscribe($discussionid) {
        $this->require_can_subscribe();
        $this->get_repo()->subscribe($discussionid, $this->get_userid());
        return $this;
    }

    /**
     * Unubscribe from a discussion
     *
     * @param int $discussionid
     * @return hsuforum_lib_discussion_subscribe
     */
    public function unsubscribe($discussionid) {
        $this->get_repo()->unsubscribe($discussionid, $this->get_userid());
        return $this;
    }

    /**
     * Unsubscribe from all discussions
     *
     * @return hsuforum_lib_discussion_subscribe
     */
    public function unsubscribe_all() {
        $this->require_can_subscribe();
        $this->get_repo()->unsubscribe_all($this->get_forum()->id, $this->get_userid());
        return $this;
    }
}
