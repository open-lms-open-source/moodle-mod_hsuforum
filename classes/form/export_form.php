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

namespace mod_hsuforum\form;

global $CFG;

require_once($CFG->libdir.'/formslib.php');

class export_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;
        $options1 = $this->get_discussion_options();
        if ($this->_customdata->forum->anonymous) {
            $options2 = $this->get_anonymous_discussion_user_options($options1);
        } else {
            $options2 = $this->get_discussion_user_options();
        }
        $hierselect = $mform->addElement('hierselect', 'discussionopts', get_string('discussions', 'hsuforum'), null, ' '.get_string('participants', 'hsuforum').' ');
        $hierselect->setOptions(array($options1, $options2));
        $mform->setType('discussionopts', PARAM_INT);

        $options = array(
            'print' => get_string('print', 'hsuforum'),
            'csv'   => get_string('csv', 'hsuforum'),
            'text'  => get_string('plaintext', 'hsuforum'),

        );

        $mform->addElement('select', 'format', get_string('exportformat', 'hsuforum'), $options);
        $mform->setType('format', PARAM_ALPHA);

        $mform->addElement('advcheckbox', 'attachments', get_string('exportattachments', 'hsuforum'));
        $mform->setType('attachments', PARAM_BOOL);
        $mform->disabledIf('attachments', 'format', 'eq', 'print');
        $this->add_action_buttons(true, get_string('export', 'hsuforum'));
    }

    /**
     * Generate options array for hierselect form element
     *
     * Creates a list of discussions.
     *
     * @return array
     */
    public function get_discussion_options() {
        $options = array(0 => get_string('all', 'hsuforum'));
        $rs       = hsuforum_get_discussions($this->_customdata->cm, 'd.name');
        foreach ($rs as $discussion) {
            $options[$discussion->discussion] = shorten_text(format_string($discussion->name));
        }
        $rs->close();

        return $options;
    }

    /**
     * Generate the second options array for hierselect form element
     *
     * For all discussions, allow the All option.  If the current
     * user has posted in the discussion, then allow exporting
     * of their own posts.
     *
     * @param array $discussions The result from get_discussion_options
     * @return array
     */
    public function get_anonymous_discussion_user_options($discussions) {
        global $USER;

        $posted  = hsuforum_discussions_user_has_posted_in($this->_customdata->forum->id, $USER->id);
        $options = array(0 => array(0 => get_string('all', 'hsuforum')));

        if (!empty($posted)) {
            $options[0][$USER->id] = $this->get_fullname($USER);
        }
        foreach (array_keys($discussions) as $discussionid) {
            if (empty($discussionid)) {
                continue;  // This is the zero (AKA All) option, skip!
            }
            $useropts = array(0 => get_string('all', 'hsuforum'));
            if (array_key_exists($discussionid, $posted)) {
                $useropts[$USER->id] = $this->get_fullname($USER);
            }
            $options[$discussionid] = $useropts;
        }
        return $options;
    }

    /**
     * Generate the second options array for hierselect form element
     *
     * For each discussion, allow exporting of all posts or posts
     * authored by a specific user.
     *
     * @return array
     */
    public function get_discussion_user_options() {
        $options = array(0 => array(0 => get_string('all', 'hsuforum')));
        $all     =& $options[0];
        $rs = $this->get_discussion_users();
        foreach ($rs as $user) {
            if (!array_key_exists($user->discussionid, $options)) {
                $options[$user->discussionid] = array(0 => get_string('all', 'hsuforum'));
            }
            $options[$user->discussionid][$user->id] = $this->get_fullname($user);

            if (!array_key_exists($user->id, $all)) {
                $all[$user->id] = $this->get_fullname($user);
            }
        }
        $rs->close();

        return $options;
    }

    /**
     * Get a list of users who have posted in each discussion.
     *
     * @return \moodle_recordset
     */
    public function get_discussion_users() {
        global $USER, $DB;

        $fields = get_all_user_name_fields(true, 'u');

        return $DB->get_recordset_sql("
            SELECT DISTINCT d.id discussionid, u.id, $fields
              FROM {hsuforum_discussions} d
              JOIN {hsuforum_posts} p ON d.id = p.discussion
              JOIN {user} u ON p.userid = u.id
             WHERE d.forum = ?
               AND (p.privatereply = 0 OR p.privatereply = ? OR p.userid = ?)
          ORDER BY u.firstname, u.lastname
        ", array($this->_customdata->forum->id, $USER->id, $USER->id));
    }

    /**
     * Get the full name for a user
     *
     * @param $user
     * @return mixed
     */
    public function get_fullname($user) {
        // Cache because we may see the same user A LOT!
        static $cache = array();

        if (!array_key_exists($user->id, $cache)) {
            $cache[$user->id] = fullname($user);
        }
        return $cache[$user->id];
    }
}
