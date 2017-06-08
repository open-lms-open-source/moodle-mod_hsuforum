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
 * Testing form service prepare draft area
 *
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class testable_form_service extends \mod_hsuforum\service\form_service {
    public function protected_file_prepare_draft_area(&$draftitemid, $contextid, $component, $filearea, $itemid, array $options=null, $text=null) {
        return $this->file_prepare_draft_area($draftitemid, $contextid, $component, $filearea, $itemid, $options, $text);
    }
}

class mod_hsuforum_form_service_testcase extends advanced_testcase {
    public function test_prepare_draft_area() {
        global $DB, $CFG, $USER;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $user = $generator->create_user();
        $USER = $user;

        // Enrol teacher on course.
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id,
            $course->id,
            $teacherrole->id
        );

        /** @var mod_hsuforum_generator $afgen */
        $afgen = $generator->get_plugin_generator('mod_hsuforum');
        $forum = $afgen->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $modcontext = context_module::instance($cm->id);
        $usercontext = context_user::instance($user->id);

        // Add a few discussions.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $record['subject'] = 'Test image files';
        $record['message'] = 'This is a test!';
        $discussion = $afgen->create_discussion($record);
        $post = $DB->get_record('hsuforum_posts', ['discussion' => $discussion->id]);

        $fs = get_file_storage();
        $draftid = file_get_submitted_draft_itemid('message');
        $formservice = new testable_form_service();

        $message = $formservice->protected_file_prepare_draft_area($draftid, $modcontext->id, 'mod_hsuforum', 'post',
            $post->id, \mod_hsuforum_post_form::editor_options($modcontext, $post->id), $post->message);

        // Should be no change as no images in message yet.
        $this->assertEquals($post->message, $message);

        $dummy = array(
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => ''
        );

        // Create some files.
        $imagefiles = array(
            'testgif_small.gif',
            'testgif2_small.gif'
        );

        // Add files to draft area and make sure they exist!
        foreach ($imagefiles as $filename) {
            $dummy['filename'] = $filename;
            $fs->create_file_from_pathname($dummy, $CFG->dirroot.'/mod/hsuforum/tests/fixtures/'.$filename);
            $this->assertTrue(repository::draftfile_exists($draftid, '/', $filename));
            $draftfileurl = new moodle_url('/draftfile.php/'.$usercontext->id.'/user/draft/'.$draftid.'/'.$filename);
            $message .= '<img src="'.$draftfileurl.'" />';
        }

        // Call the draft file area function again - normally this would wipe out the previous draft files in the core
        // moodle function.
        // (This function can be called more than once to accomodate the shared advanced editor - accessed via show
        // advanced editor on inline discussions and posts).
        $formservice->protected_file_prepare_draft_area($draftid, $modcontext->id, 'mod_hsuforum', 'post',
            $post->id, \mod_hsuforum_post_form::editor_options($modcontext, $post->id), $post->message);

        // Add a new image to the draft area.
        $filename = 'testgif3_small.gif';
        $imagefiles[] = $filename;
        $dummy['filename'] = $filename;
        $fs->create_file_from_pathname($dummy, $CFG->dirroot.'/mod/hsuforum/tests/fixtures/'.$filename);
        $this->assertTrue(repository::draftfile_exists($draftid, '/', $filename));
        $draftfileurl = new moodle_url('/draftfile.php/'.$usercontext->id.'/user/draft/'.$draftid.'/'.$filename);
        $message .= '<img src="'.$draftfileurl.'" />';

        // Now save the items in the draft area
        $options = array('subdirs'=>true);
        $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_hsuforum', 'post', $post->id, $options, $message);

        // Check post message has correct saved values.
        foreach ($imagefiles as $filename) {
            $this->assertContains('@@PLUGINFILE@@/'.$filename, $post->message);
        }

        // Check post message has correct rendered for viewing values.
        $message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php',
            $modcontext->id, 'mod_hsuforum', 'post', $post->id);
        foreach ($imagefiles as $filename) {
            $urlbase = $CFG->wwwroot.'/pluginfile.php';
            $url = $urlbase.'/'.$modcontext->id.'/mod_hsuforum/post/'.$post->id.'/'.$filename;
            $this->assertContains($url, $message);
        }
    }
}