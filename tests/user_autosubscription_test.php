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

defined('MOODLE_INTERNAL') || die();
global $CFG;

use mod_hsuforum\service\post_service;
require_once($CFG->dirroot . '/mod/hsuforum/lib.php');


/**
 * PHPUnit testcase for evaluating hsuforum subscription interaction with
 * user's forum preferences.
 *
 * @package    mod_hsuforum
 * @category   phpunit
 * @copyright  Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hsuforum_user_autosubscription_testcase extends advanced_testcase {
    public function test_hsuforum_optional_subscription() {
        global $DB;

        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('hsuforum'));

        $course = $this->getDataGenerator()->create_course();

        $teacher = $this->getDataGenerator()->create_user([
            'firstname' => 'Teacher',
            'lastname' => 'WhoTeaches']);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $studentauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'Autosuscribed',
            'autosubscribe' => '1']);
        $studentnotauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'NotAutosuscribed',
            'autosubscribe' => '0']);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($studentauto->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($studentnotauto->id, $course->id, $studentrole->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum');

        // The forum.
        $conditions = new stdClass();
        $conditions->course = $course->id;
        $conditions->forcesubscribe = HSUFORUM_CHOOSESUBSCRIBE;
        $forum = $this->getDataGenerator()->create_module('hsuforum', $conditions);

        // Add a discussion to the forum.
        $conditions = array();
        $conditions['course'] = $course->id;
        $conditions['forum'] = $forum->id;
        $conditions['userid'] = $teacher->id;
        $discussion = $generator->create_discussion($conditions);

        // Add a reply from the student that is autosubscribed by preferences.
        $this->setUser($studentauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        // Add a reply from the student that is not autosubscribed by preferences.
        $this->setUser($studentnotauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentnotauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        $this->assertEquals(1, $DB->count_records('hsuforum_subscriptions',
            array('forum' => $forum->id)));
    }

    public function test_hsuforum_automatic_subscriptions() {
        global $DB;

        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('hsuforum'));

        $course = $this->getDataGenerator()->create_course();

        $teacher = $this->getDataGenerator()->create_user([
            'firstname' => 'Teacher',
            'lastname' => 'WhoTeaches']);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $studentauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'Autosuscribed',
            'autosubscribe' => '1']);
        $studentnotauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'NotAutosuscribed',
            'autosubscribe' => '0']);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($studentauto->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($studentnotauto->id, $course->id, $studentrole->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum');

        // The forum.
        $conditions = new stdClass();
        $conditions->course = $course->id;
        $conditions->forcesubscribe = HSUFORUM_INITIALSUBSCRIBE;
        $forum = $this->getDataGenerator()->create_module('hsuforum', $conditions);

        // Add a discussion to the forum.
        $conditions = array();
        $conditions['course'] = $course->id;
        $conditions['forum'] = $forum->id;
        $conditions['userid'] = $teacher->id;
        $discussion = $generator->create_discussion($conditions);

        // Add a reply from the student that is autosubscribed by preferences.
        $this->setUser($studentauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        // Add a reply from the student that is not autosubscribed by preferences.
        $this->setUser($studentnotauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentnotauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        // In this kind of forum every participant of the course is subscribed.
        $this->assertEquals(3, $DB->count_records('hsuforum_subscriptions',
            array('forum' => $forum->id)));
    }

    public function test_hsuforum_subscriptions_not_allowed() {
        global $DB;

        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('hsuforum'));

        $course = $this->getDataGenerator()->create_course();

        $teacher = $this->getDataGenerator()->create_user([
            'firstname' => 'Teacher',
            'lastname' => 'WhoTeaches']);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $studentauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'Autosuscribed',
            'autosubscribe' => '1']);
        $studentnotauto = $this->getDataGenerator()->create_user([
            'firstname' => 'Student',
            'lastname' => 'NotAutosuscribed',
            'autosubscribe' => '0']);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($studentauto->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($studentnotauto->id, $course->id, $studentrole->id);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum');

        // The forum.
        $conditions = new stdClass();
        $conditions->course = $course->id;
        $conditions->forcesubscribe = HSUFORUM_DISALLOWSUBSCRIBE;
        $forum = $this->getDataGenerator()->create_module('hsuforum', $conditions);

        // Add a discussion to the forum.
        $conditions = array();
        $conditions['course'] = $course->id;
        $conditions['forum'] = $forum->id;
        $conditions['userid'] = $teacher->id;
        $discussion = $generator->create_discussion($conditions);

        // Add a reply from the student that is autosubscribed by preferences.
        $this->setUser($studentauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        // Add a reply from the student that is not autosubscribed by preferences.
        $this->setUser($studentnotauto);
        $conditions = new stdClass();
        $conditions->discussion = $discussion->id;
        $conditions->userid = $studentnotauto->id;
        $post = $generator->create_post($conditions);
        $post->forum = $discussion->forum;

        $service = new post_service();
        $service->handle_user_autosubscription($forum, $post);
        $this->setUser(null); // Log out.

        // In this kind of forum nobody in the course is subscribed.
        $this->assertEquals(0, $DB->count_records('hsuforum_subscriptions',
            array('forum' => $forum->id)));
    }
}
