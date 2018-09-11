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
 * Discussion Sorting Management
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class hsuforum_lib_discussion_sort implements Serializable {
    /**
     * @var string
     */
    protected $key = 'lastreply';

    /* TODO - why would you sort by the number of unread replies??

    */

    /**
     * @var array
     */
    protected $keyopts = array(
        'lastreply' => 'd.pinned DESC, d.timemodified %dir%',
        'replies'   => 'd.pinned DESC, extra.replies %dir%, d.timemodified %dir%',
        // 'unread'    => 'unread.unread %dir%, d.timemodified %dir%',
        'created'   => 'd.pinned DESC, p.created %dir%',
        // 'firstname' => 'u.firstname %dir%, d.timemodified %dir%',
        // 'lastname'  => 'u.lastname %dir%, d.timemodified %dir%',
        'subscribe' => 'd.pinned DESC, sd.id %dir%, d.timemodified %dir%',
    );

    /**
     * @var string
     */
    protected $direction = 'DESC';

    /**
     * @var array
     */
    protected $directionopts = array(
        'DESC' => 'DESC',
        'ASC' => 'ASC',
    );

    /**
     * @var array
     */
    protected $disabled = array();

    /**
     * @static
     * @param stdClass $forum
     * @param context_module $context
     * @return hsuforum_lib_discussion_sort
     */
    public static function get_from_session($forum, context_module $context) {
        global $SESSION;

        require_once(__DIR__.'/subscribe.php');

        if (!empty($SESSION->hsuforum_lib_discussion_sort)) {
            /** @var $instance hsuforum_lib_discussion_sort */
            $instance = unserialize($SESSION->hsuforum_lib_discussion_sort);
        } else {
            $instance = new self();
        }
        $dsub = new hsuforum_lib_discussion_subscribe($forum, $context);
        if (!$dsub->can_subscribe()) {
            $instance->disable('subscribe');
        }
        return $instance;
    }

    /**
     * @static
     * @param hsuforum_lib_discussion_sort $sort
     */
    public static function set_to_session(hsuforum_lib_discussion_sort $sort) {
        global $SESSION;
        $SESSION->hsuforum_lib_discussion_sort = serialize($sort);
    }

    /**
     * @param array $disabled
     * @return hsuforum_lib_discussion_sort
     */
    public function set_disabled(array $disabled) {
        if (in_array('lastreply', $disabled)) {
            throw new coding_exception('The "lastreply" key is the only key that cannot be disabled');
        }
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * @return array
     */
    public function get_disabled() {
        return $this->disabled;
    }

    /**
     * @return array
     */
    public function get_keyopts() {
        return $this->keyopts;
    }

    /**
     * @return array
     */
    public function get_directionopts() {
        return $this->directionopts;
    }

    /**
     * @param string $direction
     * @return hsuforum_lib_discussion_sort
     */
    public function set_direction($direction) {
        if (!in_array($direction, $this->get_directionopts())) {
            throw new coding_exception('Invalid sort direction: '.$direction);
        }
        $this->direction = $direction;
        return $this;
    }

    /**
     * @return string
     */
    public function get_direction() {
        return $this->direction;
    }

    /**
     * @param string $key
     * @return hsuforum_lib_discussion_sort
     */
    public function set_key($key) {
        if (!array_key_exists($key, $this->get_keyopts())) {
            throw new coding_exception('Invalid sort key: '.$key);
        }
        if (in_array($key, $this->get_disabled())) {
            throw new coding_exception('Invalid sort key (it has been disabled): '.$key);
        }
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function get_key() {
        return $this->key;
    }

    /**
     * @return array
     */
    public function get_key_options_menu() {
        $menu = array();
        foreach ($this->get_keyopts() as $key => $sort) {
            if (!in_array($key, $this->get_disabled())) {
                $menu[$key] = get_string('discussionsortkey:'.$key, 'hsuforum');
            }
        }
        return $menu;
    }

    /**
     * @return array
     */
    public function get_direction_options_menu() {
        $menu = array();
        foreach ($this->get_directionopts() as $direction) {
            $menu[$direction] = get_string('discussionsortdirection:'.$direction, 'hsuforum');
        }
        return $menu;
    }

    /**
     * @return string
     */
    public function get_sort_sql() {
        $sortopts = $this->get_keyopts();
        return str_replace('%dir%', $this->get_direction(), $sortopts[$this->get_key()]);
    }

    /**
     * @param $key
     * @return hsuforum_lib_discussion_sort
     */
    public function disable($key) {
        $disabled = $this->get_disabled();
        $disabled[$key] = $key;
        $this->set_disabled($disabled);

        if ($this->get_key() == $key) {
            $this->set_key('lastreply')
                 ->set_direction('DESC');
        }
        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or &null;
     */
    public function serialize() {
        return serialize(array('key' => $this->get_key(), 'direction' => $this->get_direction()));
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
        $sortinfo = unserialize($serialized);

        try {
            $this->set_key($sortinfo['key'])
                 ->set_direction($sortinfo['direction']);
        } catch (Exception $e) {
            // Ignore...
        }
    }
}
