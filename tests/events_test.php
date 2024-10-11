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
 * Tests for forum events.
 *
 * @package    mod_hsuforum
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for forum events.
 *
 * @package    mod_hsuforum
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends advanced_testcase {

    /**
     * Tests set up.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Ensure course_searched event validates that searchterm is set.
     */
    public function test_course_searched_searchterm_validation() {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $params = array(
            'context' => $coursectx,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('The \'searchterm\' value must be set in other');
        \mod_hsuforum\event\course_searched::create($params);
    }

    /**
     * Ensure course_searched event validates that context is the correct level.
     */
    public function test_course_searched_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);
        $params = array(
            'context' => $context,
            'other' => array('searchterm' => 'testing'),
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_COURSE');
        \mod_hsuforum\event\course_searched::create($params);
    }

    /**
     * Test course_searched event.
     */
    public function test_course_searched() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $searchterm = 'testing123';

        $params = array(
            'context' => $coursectx,
            'other' => array('searchterm' => $searchterm),
        );

        // Create event.
        $event = \mod_hsuforum\event\course_searched::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

         // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\course_searched', $event);
        $this->assertEquals($coursectx, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_created event validates that forumid is set.
     */
    public function test_discussion_created_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\discussion_created::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     */
    public function test_discussion_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\discussion_created::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_created() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('forumid' => $forum->id),
        );

        // Create the event.
        $event = \mod_hsuforum\event\discussion_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\discussion_created', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_updated event validates that forumid is set.
     */
    public function test_discussion_updated_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\discussion_updated::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     */
    public function test_discussion_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\discussion_updated::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_updated() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('forumid' => $forum->id),
        );

        // Create the event.
        $event = \mod_hsuforum\event\discussion_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\discussion_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_deleted event validates that forumid is set.
     */
    public function test_discussion_deleted_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\discussion_deleted::create($params);
    }

    /**
     * Ensure discussion_deleted event validates that context is of the correct level.
     */
    public function test_discussion_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\discussion_deleted::create($params);
    }

    /**
     * Test discussion_deleted event.
     */
    public function test_discussion_deleted() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('forumid' => $forum->id),
        );

        $event = \mod_hsuforum\event\discussion_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\discussion_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_moved event validates that fromforumid is set.
     */
    public function test_discussion_moved_fromforumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $toforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $context = context_module::instance($toforum->cmid);

        $params = array(
            'context' => $context,
            'other' => array('toforumid' => $toforum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'fromforumid' value must be set in other");
        \mod_hsuforum\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that toforumid is set.
     */
    public function test_discussion_moved_toforumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $toforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($toforum->cmid);

        $params = array(
            'context' => $context,
            'other' => array('fromforumid' => $fromforum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'toforumid' value must be set in other");
        \mod_hsuforum\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that the context level is correct.
     */
    public function test_discussion_moved_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $toforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $fromforum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
            'other' => array('fromforumid' => $fromforum->id, 'toforumid' => $toforum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\discussion_moved::create($params);
    }

    /**
     * Test discussion_moved event.
     */
    public function test_discussion_moved() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $fromforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $toforum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $fromforum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $context = context_module::instance($toforum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('fromforumid' => $fromforum->id, 'toforumid' => $toforum->id)
        );

        $event = \mod_hsuforum\event\discussion_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\discussion_moved', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }


    /**
     * Ensure discussion_viewed event validates that the contextlevel is correct.
     */
    public function test_discussion_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\discussion_viewed::create($params);
    }

    /**
     * Test discussion_viewed event.
     */
    public function test_discussion_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
        );

        $event = \mod_hsuforum\event\discussion_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure course_module_viewed event validates that the contextlevel is correct.
     */
    public function test_course_module_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $forum->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\course_module_viewed::create($params);
    }

    /**
     * Test the course_module_viewed event.
     */
    public function test_course_module_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $forum->id,
        );

        $event = \mod_hsuforum\event\course_module_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_created event validates that the forumid is set.
     */
    public function test_subscription_created_forumid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the relateduserid is set.
     */
    public function test_subscription_created_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $forum->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'relateduserid' must be set");
        \mod_hsuforum\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the contextlevel is correct.
     */
    public function test_subscription_created_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\subscription_created::create($params);
    }

    /**
     * Test the subscription_created event.
     */
    public function test_subscription_created() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($forum->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_hsuforum\event\subscription_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/subscribers.php', array('id' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_deleted event validates that the forumid is set.
     */
    public function test_subscription_deleted_forumid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the relateduserid is set.
     */
    public function test_subscription_deleted_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $forum->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'relateduserid' must be set");
        \mod_hsuforum\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the contextlevel is correct.
     */
    public function test_subscription_deleted_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\subscription_deleted::create($params);
    }

    /**
     * Test the subscription_deleted event.
     */
    public function test_subscription_deleted() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($forum->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_hsuforum\event\subscription_deleted::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/subscribers.php', array('id' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure readtracking_enabled event validates that the forumid is set.
     */
    public function test_readtracking_enabled_forumid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the relateduserid is set.
     */
    public function test_readtracking_enabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $forum->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'relateduserid' must be set");
        \mod_hsuforum\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the contextlevel is correct.
     */
    public function test_readtracking_enabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\readtracking_enabled::create($params);
    }

    /**
     * Test the readtracking_enabled event.
     */
    public function test_readtracking_enabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_hsuforum\event\readtracking_enabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\readtracking_enabled', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure readtracking_disabled event validates that the forumid is set.
     */
    public function test_readtracking_disabled_forumid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the relateduserid is set.
     */
    public function test_readtracking_disabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $forum->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'relateduserid' must be set");
        \mod_hsuforum\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the contextlevel is correct
     */
    public function test_readtracking_disabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\readtracking_disabled::create($params);
    }

    /**
     *  Test the readtracking_disabled event.
     */
    public function test_readtracking_disabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_hsuforum\event\readtracking_disabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\readtracking_disabled', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure subscribers_viewed event validates that the forumid is set.
     */
    public function test_subscribers_viewed_forumid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\subscribers_viewed::create($params);
    }

    /**
     *  Ensure subscribers_viewed event validates that the contextlevel is correct.
     */
    public function test_subscribers_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('forumid' => $forum->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\subscribers_viewed::create($params);
    }

    /**
     *  Test the subscribers_viewed event.
     */
    public function test_subscribers_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'other' => array('forumid' => $forum->id),
        );

        $event = \mod_hsuforum\event\subscribers_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\subscribers_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure user_report_viewed event validates that the reportmode is set.
     */
    public function test_user_report_viewed_reportmode_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $params = array(
            'context' => context_course::instance($course->id),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'reportmode' value must be set in other");
        \mod_hsuforum\event\user_report_viewed::create($params);
    }

    /**
     * Ensure user_report_viewed event validates that the contextlevel is correct.
     */
    public function test_user_report_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'other' => array('reportmode' => 'posts'),
            'relateduserid' => $user->id,
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be either CONTEXT_SYSTEM, CONTEXT_COURSE or CONTEXT_USER');
        \mod_hsuforum\event\user_report_viewed::create($params);
    }

    /**
     *  Ensure user_report_viewed event validates that the relateduserid is set.
     */
    public function test_user_report_viewed_relateduserid_validation() {

        $params = array(
            'context' => context_system::instance(),
            'other' => array('reportmode' => 'posts'),
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'relateduserid' must be set");
        \mod_hsuforum\event\user_report_viewed::create($params);
    }

    /**
     * Test the user_report_viewed event.
     */
    public function test_user_report_viewed() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $params = array(
            'context' => $context,
            'relateduserid' => $user->id,
            'other' => array('reportmode' => 'discussions'),
        );

        $event = \mod_hsuforum\event\user_report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\user_report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_created event validates that the postid is set.
     */
    public function test_post_created_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'other' => array('forumid' => $forum->id, 'forumtype' => $forum->type, 'discussionid' => $discussion->id)
        );

        \mod_hsuforum\event\post_created::create($params);
    }

    /**
     * Ensure post_created event validates that the discussionid is set.
     */
    public function test_post_created_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'discussionid' value must be set in other");
        \mod_hsuforum\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the forumid is set.
     */
    public function test_post_created_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\post_created::create($params);
    }

    /**
     * Ensure post_created event validates that the forumtype is set.
     */
    public function test_post_created_forumtype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumtype' value must be set in other");
        \mod_hsuforum\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the contextlevel is correct.
     */
    public function test_post_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\post_created::create($params);
    }

    /**
     * Test the post_created event.
     */
    public function test_post_created() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $event = \mod_hsuforum\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test the post_created event for a single discussion forum.
     */
    public function test_post_created_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $event = \mod_hsuforum\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_deleted event validates that the postid is set.
     */
    public function test_post_deleted_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'other' => array('forumid' => $forum->id, 'forumtype' => $forum->type, 'discussionid' => $discussion->id)
        );

        \mod_hsuforum\event\post_deleted::create($params);
    }

    /**
     * Ensure post_deleted event validates that the discussionid is set.
     */
    public function test_post_deleted_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'discussionid' value must be set in other");
        \mod_hsuforum\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the forumid is set.
     */
    public function test_post_deleted_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\post_deleted::create($params);
    }

    /**
     * Ensure post_deleted event validates that the forumtype is set.
     */
    public function test_post_deleted_forumtype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumtype' value must be set in other");
        \mod_hsuforum\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the contextlevel is correct.
     */
    public function test_post_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\post_deleted::create($params);
    }

    /**
     * Test post_deleted event.
     */
    public function test_post_deleted() {
        global $DB;

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $forum->course);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // When creating a discussion we also create a post, so get the post.
        $discussionpost = $DB->get_records('hsuforum_posts');
        // Will only be one here.
        $discussionpost = reset($discussionpost);

        // Add a few posts.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $posts = array();
        $posts[$discussionpost->id] = $discussionpost;
        for ($i = 0; $i < 3; $i++) {
            $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);
            $posts[$post->id] = $post;
        }

        // Delete the last post and capture the event.
        $lastpost = end($posts);
        $sink = $this->redirectEvents();
        hsuforum_delete_post($lastpost, true, $course, $cm, $forum);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the events contain the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_deleted', $event);
        $this->assertEquals(context_module::instance($forum->cmid), $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
        // Delete the whole discussion and capture the events.
        $sink = $this->redirectEvents();
        hsuforum_delete_discussion($discussion, true, $course, $cm, $forum);
        $events = $sink->get_events();
        // We will have 3 events. One for the discussion (creating a discussion creates a post), and two for the posts.
        $this->assertCount(3, $events);

        // Loop through the events and check they are valid.
        foreach ($events as $event) {
            $post = $posts[$event->objectid];

            // Check that the event contains the expected values.
            $this->assertInstanceOf('\mod_hsuforum\event\post_deleted', $event);
            $this->assertEquals(context_module::instance($forum->cmid), $event->get_context());
            $url = new \moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id));
            $this->assertEquals($url, $event->get_url());
            $this->assertEventContextNotUsed($event);
            $this->assertNotEmpty($event->get_name());
        }
    }

    /**
     * Test post_deleted event for a single discussion forum.
     */
    public function test_post_deleted_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $event = \mod_hsuforum\event\post_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure post_updated event validates that the discussionid is set.
     */
    public function test_post_updated_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'discussionid' value must be set in other");
        \mod_hsuforum\event\post_updated::create($params);
    }

    /**
     * Ensure post_updated event validates that the forumid is set.
     */
    public function test_post_updated_forumid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumid' value must be set in other");
        \mod_hsuforum\event\post_updated::create($params);
    }

    /**
     * Ensure post_updated event validates that the forumtype is set.
     */
    public function test_post_updated_forumtype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_module::instance($forum->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The 'forumtype' value must be set in other");
        \mod_hsuforum\event\post_updated::create($params);
    }

    /**
     *  Ensure post_updated event validates that the contextlevel is correct.
     */
    public function test_post_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE');
        \mod_hsuforum\event\post_updated::create($params);
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $event = \mod_hsuforum\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $forum->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_post($record);

        $context = context_module::instance($forum->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type)
        );

        $event = \mod_hsuforum\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hsuforum\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/hsuforum/view.php', array('f' => $forum->id));
        $url->set_anchor('p'.$post->id);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test mod_hsuforum_observer methods.
     */
    public function test_observers() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/hsuforum/lib.php');

        $forumgen = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum');

        $course = $this->getDataGenerator()->create_course();
        $trackedrecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => HSUFORUM_INITIALSUBSCRIBE);
        $untrackedrecord = array('course' => $course->id, 'type' => 'general');
        $trackedforum = $this->getDataGenerator()->create_module('hsuforum', $trackedrecord);
        $untrackedforum = $this->getDataGenerator()->create_module('hsuforum', $untrackedrecord);

        // Used functions don't require these settings; adding
        // them just in case there are APIs changes in future.
        $user = $this->getDataGenerator()->create_user(array(
            'maildigest' => 1,
            'trackforums' => 1
        ));

        $manplugin = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
        $student = $DB->get_record('role', array('shortname' => 'student'));

        // The role_assign observer does it's job adding the hsuforum_subscriptions record.
        $manplugin->enrol_user($manualenrol, $user->id, $student->id);

        $record = array();
        $record['course'] = $course->id;
        $record['forum'] = $trackedforum->id;
        $record['userid'] = $user->id;

        // Creating a discussion calls hsuforum_add_discussion which automatically adds a read record
        // So at this point the read count is 1.
        $discussion = $forumgen->create_discussion($record);

        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;

        // Creating a post doesn't automatically add a read record.
        $post = $forumgen->create_post($record);

        // Add a read record for this post - the read record count is now 2
        hsuforum_tp_add_read_record($user->id, $post->id);

        hsuforum_set_user_maildigest($trackedforum, 2, $user);

        $this->assertEquals(1, $DB->count_records('hsuforum_subscriptions', array('userid' => $user->id)));
        $this->assertEquals(1, $DB->count_records('hsuforum_digests', array('userid' => $user->id)));
        $this->assertEquals(2, $DB->count_records('hsuforum_read', array('userid' => $user->id)));

        // The course_module_created observer does it's job adding a subscription.
        $forumrecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => HSUFORUM_INITIALSUBSCRIBE);
        $extraforum = $this->getDataGenerator()->create_module('hsuforum', $forumrecord);
        $this->assertEquals(2, $DB->count_records('hsuforum_subscriptions'));

        $manplugin->unenrol_user($manualenrol, $user->id);

        $this->assertEquals(0, $DB->count_records('hsuforum_digests'));
        $this->assertEquals(0, $DB->count_records('hsuforum_subscriptions'));
        $this->assertEquals(0, $DB->count_records('hsuforum_track_prefs'));
        $this->assertEquals(0, $DB->count_records('hsuforum_read'));
    }

}
