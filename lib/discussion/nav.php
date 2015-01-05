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
 * Discussion Next/Previous Buttons
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/sort.php');

class hsuforum_lib_discussion_nav implements Serializable {
    /**
     * The max size of $discussionids
     */
    const MAX_IDS = 50;

    /**
     * @var int
     */
    protected $cmid;

    /**
     * @var null|object
     */
    protected $cm = null;

    /**
     * @var array
     */
    protected $discussionids = array();

    /**
     * Keeps track of the position of each discussion
     * that is loaded in the discussionids array.
     *
     * @var array
     */
    protected $posistions = array();

    /**
     * @var hsuforum_lib_discussion_sort
     */
    protected $sort;

    /**
     * @param int $cmid The forum's course module ID
     */
    public function __construct($cmid) {
        $this->set_cmid($cmid);
    }

    /**
     * @static
     * @param stdClass $cm
     * @param hsuforum_lib_discussion_sort $sort
     * @return hsuforum_lib_discussion_nav
     */
    public static function get_from_session($cm, hsuforum_lib_discussion_sort $sort) {
        global $SESSION;

        if (!empty($SESSION->hsuforum_lib_discussion_nav)) {
            /** @var $instance hsuforum_lib_discussion_nav */
            $instance = unserialize($SESSION->hsuforum_lib_discussion_nav);

            if ($instance->get_cmid() != $cm->id) {
                $instance = new self($cm->id);
            }
        } else {
            $instance = new self($cm->id);
        }
        $instance->set_cm($cm)->set_sort($sort);

        return $instance;
    }

    /**
     * @static
     * @param hsuforum_lib_discussion_nav|null $nav
     */
    public static function set_to_session(hsuforum_lib_discussion_nav $nav = null) {
        global $SESSION;

        if (is_null($nav)) {
            unset($SESSION->hsuforum_lib_discussion_nav);
        } else {
            $SESSION->hsuforum_lib_discussion_nav = serialize($nav);
        }
    }

    /**
     * @param null|object $cm
     * @return hsuforum_lib_discussion_nav
     */
    public function set_cm($cm) {
        if ($cm->id != $this->get_cmid()) {
            throw new coding_exception('The passed course module object ID does not match current ID');
        }
        $this->cm = $cm;
        return $this;
    }

    /**
     * @return object
     */
    public function get_cm() {
        if (empty($this->cm)) {
            $this->set_cm(get_coursemodule_from_id('hsuforum', $this->get_cmid(), 0, false, MUST_EXIST));
        }
        return $this->cm;
    }

    /**
     * @param int $cmid
     * @return hsuforum_lib_discussion_nav
     */
    protected function set_cmid($cmid) {
        $this->cmid = $cmid;
        return $this;
    }

    /**
     * @return int
     */
    public function get_cmid() {
        return $this->cmid;
    }

    /**
     * @param array $discussionids
     * @return hsuforum_lib_discussion_nav
     */
    protected function set_discussionids($discussionids) {
        $this->discussionids = $discussionids;
        return $this;
    }

    /**
     * @return array
     */
    public function get_discussionids() {
        return $this->discussionids;
    }

    /**
     * @param \hsuforum_lib_discussion_sort $sort
     * @return hsuforum_lib_discussion_nav
     */
    public function set_sort($sort) {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @return \hsuforum_lib_discussion_sort
     */
    public function get_sort() {
        global $DB;

        if (!$this->sort instanceof hsuforum_lib_discussion_sort) {
            $this->set_sort(hsuforum_lib_discussion_sort::get_from_session(
                $DB->get_record('hsuforum', array('id' => $this->get_cm()->instance), '*', MUST_EXIST),
                context_module::instance($this->get_cmid())
            ));
        }
        return $this->sort;
    }

    /**
     * Loads X discussion IDs before and after the passed ID into an array
     * If there are no more discussion IDs before or after the array
     * of IDs, then false is added to the array, EG:
     * array(false, 1, 2, 3, false)
     *
     * @param $basediscussionid
     * @return hsuforum_lib_discussion_nav
     */
    protected function load_discussionids($basediscussionid) {
        $discussionids = array();
        $shifted       = false;
        $countdown     = -1;
        $position      = 1;

        $rs = hsuforum_get_discussions($this->get_cm(), $this->get_sort()->get_sort_sql(), 'd.id');
        foreach ($rs as $discussion) {
            $id = (int) $discussion->id;
            $discussionids[] = $id;

            // Position tracking.
            $this->posistions[$id] = $position;
            $position++;

            if ($countdown != -1) {
                $countdown--;

                if ($countdown <= 0) {
                    break;
                }
            } else if ($discussion->id == $basediscussionid) {
                $countdown = round(self::MAX_IDS / 2);
            }
            if (count($discussionids) > self::MAX_IDS) {
                // Fancy way of removing the discussion from both arrays.
                unset($this->posistions[array_shift($discussionids)]);
                $shifted = true;
            }
        }
        // We never shifted, meaning we are at the start still, mark with false
        if (!$shifted) {
            array_unshift($discussionids, false);
        }

        // If we are at the end, then mark with false
        if ($rs->valid()) {
            // See if we have a next one...
            $rs->next();
            if (!$rs->valid()) {
                $discussionids[] = false;
            }
        } else {
            // Already at end
            $discussionids[] = false;
        }
        $rs->close();
        $this->set_discussionids($discussionids);
        return $this;
    }

    /**
     * Find the discussion ID in our array of discussion IDs
     *
     * Discussion IDs might be (re-)generated during this process.
     *
     * @param int $discussionid
     * @return mixed
     */
    protected function find_discussionid_key($discussionid) {
        $key = array_search($discussionid, $this->get_discussionids());

        if ($key === false) {
            // Try a re-load before failing
            $this->load_discussionids($discussionid);
            $key = array_search($discussionid, $this->get_discussionids());
        }
        $discussionids = $this->get_discussionids();
        if (!array_key_exists(($key + 1), $discussionids) or !array_key_exists(($key - 1), $discussionids)) {
            // Next or previous have not been loaded, recenter on discussionid
            $this->load_discussionids($discussionid);
            $key = array_search($discussionid, $this->get_discussionids());
        }
        return $key;
    }

    /**
     * Lookup a discussion ID by array key
     *
     * @param int $key
     * @return bool|int
     */
    protected function get_discussionid_by_key($key) {
        $discussionids = $this->get_discussionids();

        if (array_key_exists($key, $discussionids)) {
            return $discussionids[$key];
        }
        return false;
    }

    /**
     * Get the page that the discussion is on
     *
     * @param int $discussionid
     * @param null|int $perpage Override the per page setting
     * @return int
     */
    public function get_page($discussionid, $perpage = null) {

        $config = get_config('hsuforum');

        if (is_null($perpage)) {
            $perpage = $config->manydiscussions;
        }
        if ($this->find_discussionid_key($discussionid) === false) {
            return 0; // Shouldn't happen, we should find valid ones.
        }
        if (!array_key_exists($discussionid, $this->posistions)) {
            return 0; // Shouldn't happen, the above should have populated position.
        }
        // Subtract one because we start counting pages from zero.
        return ceil($this->posistions[$discussionid] / $perpage) - 1;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or &null;
     */
    public function serialize() {
        return serialize(array(
            'cmid' => $this->get_cmid(),
            'discussionids' => $this->get_discussionids(),
            'positions' => $this->posistions,
        ));
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     *
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized) {
        $data = unserialize($serialized);

        // Positions is new, so don't use session if missing.
        if (empty($data['positions'])) {
            return;
        }
        $this->set_cmid($data['cmid'])
             ->set_discussionids($data['discussionids']);
    }
}