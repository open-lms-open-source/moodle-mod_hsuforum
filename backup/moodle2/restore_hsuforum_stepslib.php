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

/**
 * Define all the restore steps that will be used by the restore_hsuforum_activity_task
 */

/**
 * Structure step to restore one forum activity
 */
class restore_hsuforum_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('hsuforum', '/activity/hsuforum');
        if ($userinfo) {
            $paths[] = new restore_path_element('hsuforum_discussion', '/activity/hsuforum/discussions/discussion');
            $paths[] = new restore_path_element('hsuforum_discussion_subscription', '/activity/hsuforum/discussions/discussion/subscriptions_discs/subscriptions_disc');
            $paths[] = new restore_path_element('hsuforum_post', '/activity/hsuforum/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('hsuforum_tag', '/activity/hsuforum/poststags/tag');
            $paths[] = new restore_path_element('hsuforum_rating', '/activity/hsuforum/discussions/discussion/posts/post/ratings/rating');
            $paths[] = new restore_path_element('hsuforum_subscription', '/activity/hsuforum/subscriptions/subscription');
            $paths[] = new restore_path_element('hsuforum_digest', '/activity/hsuforum/digests/digest');
            $paths[] = new restore_path_element('hsuforum_read', '/activity/hsuforum/readposts/read');
            $paths[] = new restore_path_element('hsuforum_track', '/activity/hsuforum/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_hsuforum($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        if (!property_exists($data, 'allowprivatereplies')) {
            $data->allowprivatereplies = 1;
        }
        if (!property_exists($data, 'showsubstantive')) {
            $data->showsubstantive = 1;
        }
        if (!property_exists($data, 'showbookmark')) {
            $data->showbookmark = 1;
        }

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('hsuforum', $data);
        $this->apply_activity_instance($newitemid);

        // Add current enrolled user subscriptions if necessary.
        $data->id = $newitemid;
        $ctx = context_module::instance($this->task->get_moduleid());
        hsuforum_instance_created($ctx, $data);
    }

    protected function process_hsuforum_discussion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->forum = $this->get_new_parentid('hsuforum');
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        if (empty($data->groupid)) {
            $data->groupid = -1;
        }
        $newitemid = $DB->insert_record('hsuforum_discussions', $data);
        $this->set_mapping('hsuforum_discussion', $oldid, $newitemid);
    }

    protected function process_hsuforum_discussion_subscription($data) {
        global $DB;

        $data = (object)$data;
        unset($data->id);

        $data->discussion = $this->get_new_parentid('hsuforum_discussion');
        $data->userid     = $this->get_mappingid('user', $data->userid);

        $DB->insert_record('hsuforum_subscriptions_disc', $data);
    }

    protected function process_hsuforum_post($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $olduserid = $data->userid;
        $data->discussion = $this->get_new_parentid('hsuforum_discussion');
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('hsuforum_post', $data->parent);
        }

        $newitemid = $DB->insert_record('hsuforum_posts', $data);
        $this->set_mapping('hsuforum_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('hsuforum_discussions', 'firstpost', $newitemid, array('id' => $data->discussion));
        }
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('posts'), $olduserid, $data->userid);
    }

    protected function process_hsuforum_tag($data) {
        $data = (object)$data;

        if (!core_tag_tag::is_enabled('mod_hsuforum', 'forum_posts')) { // Tags disabled in server, nothing to process.
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('hsuforum_post', $data->itemid)) {
            // Some orphaned tag, we could not find the restored post for it - ignore.
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_hsuforum', 'forum_posts', $itemid, $context, $tag);
    }

    protected function process_hsuforum_rating($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('hsuforum_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);

        // We need to check that component and ratingarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_hsuforum';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'post';
        }

        $newitemid = $DB->insert_record('rating', $data);
    }

    protected function process_hsuforum_subscription($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forum = $this->get_new_parentid('hsuforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Create only a new subscription if it does not already exist (see MDL-59854).
        if ($subscription = $DB->get_record('hsuforum_subscriptions',
                array('forum' => $data->forum, 'userid' => $data->userid))) {
            $this->set_mapping('hsuforum_subscription', $oldid, $subscription->id, true);
        } else {
            $newitemid = $DB->insert_record('hsuforum_subscriptions', $data);
            $this->set_mapping('hsuforum_subscription', $oldid, $newitemid, true);
        }

    }

    protected function process_hsuforum_digest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forum = $this->get_new_parentid('hsuforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('hsuforum_digests', $data);
    }

    protected function process_hsuforum_read($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumid = $this->get_new_parentid('hsuforum');
        $data->discussionid = $this->get_mappingid('hsuforum_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('hsuforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('hsuforum_read', $data);
    }

    protected function process_hsuforum_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->forumid = $this->get_new_parentid('hsuforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('hsuforum_track_prefs', $data);
    }

    protected function after_execute() {
        // Add forum related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_hsuforum', 'intro', null);

        // Add post related files, matching by itemname = 'hsuforum_post'
        $this->add_related_files('mod_hsuforum', 'post', 'hsuforum_post');
        $this->add_related_files('mod_hsuforum', 'attachment', 'hsuforum_post');
    }

    protected function after_restore() {
        global $DB;

        // If the forum is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using forum
        // information as base for the initial post.
        $forumid = $this->task->get_activityid();
        $forumrec = $DB->get_record('hsuforum', array('id' => $forumid));
        
        if ($forumrec->type == 'single' && !$DB->record_exists('hsuforum_discussions', array('forum' => $forumid))) {
            // Create single discussion/lead post from forum data
            $sd = new stdClass();
            $sd->course   = $forumrec->course;
            $sd->forum    = $forumrec->id;
            $sd->name     = $forumrec->name;
            $sd->assessed = $forumrec->assessed;
            $sd->message  = $forumrec->intro;
            $sd->messageformat = $forumrec->introformat;
            $sd->messagetrust  = true;
            $sd->mailnow  = false;
            $sd->reveal = 0;
            $sdid = hsuforum_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field ('hsuforum_posts','mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_hsuforum/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(), 'mod_hsuforum', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdClass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid   = $DB->get_field('hsuforum_discussions', 'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
    }
}
