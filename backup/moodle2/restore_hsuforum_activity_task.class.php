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
 * @package    mod_hsuforum
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hsuforum/backup/moodle2/restore_hsuforum_stepslib.php'); // Because it exists (must)

/**
 * forum restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_hsuforum_activity_task extends restore_activity_task {

    /**
     * Given a comment area, return the itemname that contains the itemid mappings
     */
    public function get_comment_mapping_itemname($commentarea) {
        if ($commentarea == 'userposts_comments') {
            return 'user';
        }

        return $commentarea;
    }


    /**
     * @return stdClass
     */
    public function get_comment_file_annotation_info() {
        return (object) array(
            'component' => 'mod_hsuforum',
            'filearea' => 'comments',
        );
    }

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_hsuforum_activity_structure_step('hsuforum_structure', 'hsuforum.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('hsuforum', array('intro'), 'hsuforum');
        $contents[] = new restore_decode_content('hsuforum_posts', array('message'), 'hsuforum_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of forums in course
        $rules[] = new restore_decode_rule('HSUFORUMINDEX', '/mod/hsuforum/index.php?id=$1', 'course');
        // Forum by cm->id and forum->id
        $rules[] = new restore_decode_rule('HSUFORUMVIEWBYID', '/mod/hsuforum/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('HSUFORUMVIEWBYF', '/mod/hsuforum/view.php?f=$1', 'hsuforum');
        // Link to forum discussion
        $rules[] = new restore_decode_rule('HSUFORUMDISCUSSIONVIEW', '/mod/hsuforum/discuss.php?d=$1', 'hsuforum_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('HSUFORUMDISCUSSIONVIEWPARENT', '/mod/hsuforum/discuss.php?d=$1&parent=$2',
                                           array('hsuforum_discussion', 'hsuforum_post'));
        $rules[] = new restore_decode_rule('HSUFORUMDISCUSSIONVIEWINSIDE', '/mod/hsuforum/discuss.php?d=$1#$2',
                                           array('hsuforum_discussion', 'hsuforum_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * forum logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('hsuforum', 'add', 'view.php?id={course_module}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'update', 'view.php?id={course_module}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'view', 'view.php?id={course_module}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'view forum', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'mark read', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'start tracking', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'stop tracking', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'subscribe', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'unsubscribe', 'view.php?f={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'subscriber', 'subscribers.php?id={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'subscribers', 'subscribers.php?id={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'view subscribers', 'subscribers.php?id={hsuforum}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'add discussion', 'discuss.php?d={hsuforum_discussion}', '{hsuforum_discussion}');
        $rules[] = new restore_log_rule('hsuforum', 'view discussion', 'discuss.php?d={hsuforum_discussion}', '{hsuforum_discussion}');
        $rules[] = new restore_log_rule('hsuforum', 'move discussion', 'discuss.php?d={hsuforum_discussion}', '{hsuforum_discussion}');
        $rules[] = new restore_log_rule('hsuforum', 'delete discussi', 'view.php?id={course_module}', '{hsuforum}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('hsuforum', 'delete discussion', 'view.php?id={course_module}', '{hsuforum}');
        $rules[] = new restore_log_rule('hsuforum', 'add post', 'discuss.php?d={hsuforum_discussion}&parent={hsuforum_post}', '{hsuforum_post}');
        $rules[] = new restore_log_rule('hsuforum', 'update post', 'discuss.php?d={hsuforum_discussion}#p{hsuforum_post}&parent={hsuforum_post}', '{hsuforum_post}');
        $rules[] = new restore_log_rule('hsuforum', 'update post', 'discuss.php?d={hsuforum_discussion}&parent={hsuforum_post}', '{hsuforum_post}');
        $rules[] = new restore_log_rule('hsuforum', 'prune post', 'discuss.php?d={hsuforum_discussion}', '{hsuforum_post}');
        $rules[] = new restore_log_rule('hsuforum', 'delete post', 'discuss.php?d={hsuforum_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('hsuforum', 'view forums', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('hsuforum', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('hsuforum', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('hsuforum', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('hsuforum', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');
        $rules[] = new restore_log_rule('hsuforum', 'view all', 'index.php?id={course}', '{course}');

        return $rules;
    }
}
