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
 * The mod_hsuforum course module viewed event.
 *
 * @package    mod_hsuforum
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_hsuforum course module viewed event class.
 *
 * @package    mod_hsuforum
 * @since      Moodle 2.7
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'hsuforum';
    }

    /**
     * Get URL related to the action
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/mod/hsuforum/view.php', array('f' => $this->objectid));
    }

    public static function get_objectid_mapping() {
        return array('db' => 'hsuforum', 'restore' => 'hsuforum');
    }
}

