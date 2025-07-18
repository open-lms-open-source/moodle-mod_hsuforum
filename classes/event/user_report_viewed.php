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
 * The mod_hsuforum user report viewed event.
 *
 * @package    mod_hsuforum
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_hsuforum user report viewed event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string reportmode: The mode the report has been viewed in (posts or discussions).
 * }
 *
 * @package    mod_hsuforum
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_report_viewed extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has viewed the user report for the user with id '$this->relateduserid' in " .
            "the course with id '$this->courseid' with viewing mode '{$this->other['reportmode']}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventuserreportviewed', 'mod_hsuforum');
    }

    /**
     * Get URL related to the action
     *
     * @return \core\url
     */
    public function get_url() {

        $url = new \core\url('/mod/hsuforum/user.php', array('id' => $this->relateduserid,
            'mode' => $this->other['reportmode']));

        if ($this->courseid != SITEID) {
            $url->param('course', $this->courseid);
        }

        return $url;
    }

    /**
     * Custom validation.
     *
     * @throws \core\exception\coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \core\exception\coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['reportmode'])) {
            throw new \core\exception\coding_exception('The \'reportmode\' value must be set in other.');
        }

        switch ($this->contextlevel)
        {
            case CONTEXT_COURSE:
            case CONTEXT_SYSTEM:
            case CONTEXT_USER:
                // OK, expected context level.
                break;
            default:
                // Unexpected contextlevel.
                throw new \core\exception\coding_exception('Context level must be either CONTEXT_SYSTEM, CONTEXT_COURSE or CONTEXT_USER.');
        }
    }

    public static function get_other_mapping() {
        return false;
    }
}

