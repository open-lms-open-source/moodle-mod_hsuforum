<?php

namespace mod_hsuforum\event;

use core\event\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum discussion created.
 *
 * @package mod_hsuforum
 * @author Mark Nielsen
 */
class discussion_created extends base {

    protected function init() {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'hsuforum_discussions';
    }

    public static function get_name() {
        return get_string('event_discussion_created', 'hsuforum');
    }

    public function get_description() {
        return "User with id {$this->userid} created discussion with id {$this->objectid}.";
    }
}
