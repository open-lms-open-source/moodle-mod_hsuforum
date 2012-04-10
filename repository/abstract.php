<?php
/**
 * Repository Mapper Abstract
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class hsuforum_repository_abstract {
    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @param moodle_database|null $db
     */
    public function __construct(moodle_database $db = null) {
        global $DB;

        if (is_null($db)) {
            $this->db = $DB;
        } else {
            $this->db = $db;
        }
    }

    /**
     * @param \moodle_database $db
     * @return hsuforum_repository_discussion
     */
    public function set_db($db) {
        $this->db = $db;
        return $this;
    }

    /**
     * @return \moodle_database
     */
    public function get_db() {
        return $this->db;
    }
}