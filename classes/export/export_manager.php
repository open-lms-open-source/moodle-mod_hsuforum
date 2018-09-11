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
 * Export Manager
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\export;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__DIR__)).'/lib.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_manager {
    /**
     * @var \stdClass
     */
    protected $cm;

    /**
     * @var adapter_interface
     */
    protected $adapter;

    /**
     * @param \stdClass $cm
     * @param adapter_interface $adapter
     */
    public function __construct($cm, adapter_interface $adapter) {
        $this->cm = $cm;
        $this->adapter = $adapter;
    }

    /**
     * Export all forum discussions
     *
     * @param int $userid Only export posts with this user as the author
     */
    public function export_discussions($userid = 0) {
        $this->adapter->initialization();

        $rs = hsuforum_get_discussions($this->cm, null, 'd.*');
        foreach ($rs as $discussion) {
            $this->process_discussion($discussion, $userid);
        }
        $rs->close();

        $this->adapter->finish();
    }

    /**
     * Export all posts from this discussion
     *
     * @param int $discussionid
     * @param int $userid Only export posts with this user as the author
     */
    public function export_discussion($discussionid, $userid = 0) {
        global $DB;

        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);

        $this->adapter->initialization($discussion);
        $this->process_discussion($discussion, $userid);
        $this->adapter->finish();
    }

    /**
     * Run the actual export on a discussion
     *
     * @param \stdClass $discussion
     * @param int $userid Only export posts with this user as the author
     */
    public function process_discussion($discussion, $userid = 0) {
        global $USER;

        if (hsuforum_get_cm_forum($this->cm)->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0 || $discussion->timestart <= time()) && ($discussion->timeend == 0 || $discussion->timeend > time())))) {
                return;
            }
        }
        if (!empty($userid)) {
            $conditions = array('p.userid' => $userid);
        } else {
            $conditions = array();
        }
        $posts = hsuforum_get_all_discussion_posts($discussion->id, $conditions);

        if (array_key_exists($discussion->firstpost, $posts)) {
            $post = $posts[$discussion->firstpost];
        } else {
            $post = hsuforum_get_post_full($discussion->firstpost);
        }
        if (!hsuforum_user_can_see_post(hsuforum_get_cm_forum($this->cm), $discussion, $post, null, $this->cm)) {
            return;
        }
        $this->clean_posts($discussion, $posts);
        $this->adapter->send_discussion($discussion, $posts);
    }

    /**
     * Process posts by removing unwanted data, etc.
     *
     * @param \stdClass $discussion
     * @param \stdClass[] $posts
     */
    public function clean_posts($discussion, &$posts) {
        foreach ($posts as $key => $post) {
            if (!hsuforum_user_can_see_post(hsuforum_get_cm_forum($this->cm), $discussion, $post, null, $this->cm)) {
                unset($posts[$key]);
                continue;
            }
            // Remove children, not processing them.
            unset($post->children);
        }
    }
}
