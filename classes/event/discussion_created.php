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
 * The mod_hsuforum discussion created event.
 *
 * @package    mod_hsuforum
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_hsuforum discussion created event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int forumid: The id of the forum the discussion is in.
 * }
 *
 * @package    mod_hsuforum
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_created extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'hsuforum_discussions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has created the discussion with id '$this->objectid' in the forum " .
            "with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussioncreated', 'mod_hsuforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/mod/hsuforum/discuss.php', array('d' => $this->objectid));
    }

    /**
     * Custom validation.
     *
     * @throws \core\exception\coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['forumid'])) {
            throw new \core\exception\coding_exception('The \'forumid\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \core\exception\coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'hsuforum_discussions', 'restore' => 'hsuforum_discussion');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['forumid'] = array('db' => 'hsuforum', 'restore' => 'hsuforum');

        return $othermapped;
    }
}
