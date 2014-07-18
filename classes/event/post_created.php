<?php

namespace mod_hsuforum\event;

use core\event\base;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum post created.
 *
 * @package mod_hsuforum
 * @author Mark Nielsen
 */
class post_created extends base {

    protected function init() {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'hsuforum_posts';
    }

    public static function get_name() {
        return get_string('event_post_created', 'hsuforum');
    }

    public function get_description() {
        return "User with id {$this->userid} created post with id {$this->objectid}.";
    }

    protected function validate_data() {
        if (!isset($this->other['discussionid'])) {
            throw new \coding_exception("Field other['discussionid'] cannot be empty");
        }
    }
}
