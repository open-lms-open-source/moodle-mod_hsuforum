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
 * Tests for the forum implementation of the Privacy Provider API.
 *
 * @package    mod_hsuforum
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__ . '/helper.php');
require_once($CFG->dirroot . '/rating/lib.php');
use \mod_hsuforum\privacy\provider;

/**
 * Tests for the hsuforum implementation of the Privacy Provider API.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_provider_test extends \core_privacy\tests\provider_testcase {

    // Include the privacy helper trait.
    use \mod_hsuforum\privacy\subcontext_info;

    // Include the mod_hsuforum test helpers.
    // This includes functions to create forums, users, discussions, and posts.
    use helper_hsuforums;

    // Include the privacy helper trait for the ratings API.
    use \core_rating\phpunit\privacy_helper;

    // Include the privacy helper trait for the tag API.
    use \core_tag\tests\privacy_helper;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        $this->resetAfterTest(true);
    }

    /**
     * Helper to assert that the forum data is correct.
     */
    protected function assert_forum_data($expected, $actual) {
        // Exact matches.
        $this->assertEquals(format_string($expected->name, true), $actual->name);
    }

    /**
     * Helper to assert that the discussion data is correct.
     */
    protected function assert_discussion_data($expected, $actual) {
        // Exact matches.
        $this->assertEquals(format_string($expected->name, true), $actual->name);
        $this->assertEquals(
            \core_privacy\local\request\transform::yesno($expected->pinned),
            $actual->pinned
        );

        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->timemodified),
            $actual->timemodified
        );

        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->usermodified),
            $actual->usermodified
        );
    }

    /**
     * Helper to assert that the post data is correct.
     */
    protected function assert_post_data($expected, $actual, $writer) {
        // Exact matches.
        $this->assertEquals(format_string($expected->subject, true), $actual->subject);

        // The message should have been passed through the rewriter.
        // Note: The testable rewrite_pluginfile_urls function in the ignores all items except the text.
        $this->assertEquals(
            $writer->rewrite_pluginfile_urls([], '', '', '', $expected->message),
            $actual->message
        );

        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->created),
            $actual->created
        );

        $this->assertEquals(
            \core_privacy\local\request\transform::datetime($expected->modified),
            $actual->modified
        );
    }

    /**
     * Test that a user who is enrolled in a course, but who has never
     * posted and has no other metadata stored will not have any link to
     * that context.
     */
    public function test_user_has_never_posted() {
        global $DB;

        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $forum = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        list($user, $otheruser) = $this->helper_create_users($course, 2);
        list($discussion, $post) = $this->helper_post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Test that no contexts were retrieved.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $contexts = $contextlist->get_contextids();
        $this->assertCount(0, $contexts);

        // Attempting to export data for this context should return nothing either.
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');

        $writer = \core_privacy\local\request\writer::with_context($context);

        // The provider should always export data for any context explicitly asked of it, but there should be no
        // metadata, files, or discussions.
        $this->assertEmpty($writer->get_data([get_string('discussions', 'mod_hsuforum')]));
        $this->assertEmpty($writer->get_all_metadata([]));
        $this->assertEmpty($writer->get_files([]));
    }

    /**
     * Test that a user who is enrolled in a course, and who has never
     * posted and has subscribed to the forum will have relevant
     * information returned.
     */
    public function test_user_has_never_posted_subscribed_to_forum() {
        global $DB;
        // Create a course, with a forum, our user under test, another user, and a discussion + post from the other user.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        list($user, $otheruser) = $this->helper_create_users($course, 2);
        list($discussion, $post) = $this->helper_post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);
        $record = new stdClass();
        $record->userid = $user->id;
        $record->forum = $forum->id;
        $DB->insert_record('hsuforum_subscriptions', $record);

        $comment = new stdClass();
        $comment->contextid = $context->id;
        $comment->component = 'mod_hsuforum';
        $comment->commentarea = 'userposts_comments';
        $comment->content = 'This is a test';
        $comment->itemid = $user->id;
        $comment->format = 0;
        $comment->userid = $otheruser->id;
        $comment->timecreated = time();
        $DB->insert_record('comments', $comment);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $subcontext = $this->get_subcontext($forum);
        // There should be one item of metadata.
        $this->assertCount(1, $writer->get_all_metadata($subcontext));

        // It should be the subscriptionpreference whose value is 1.
        $this->assertEquals(1, $writer->get_metadata($subcontext, 'subscriptionpreference'));

        // There should be data about the forum itself.
        $expecteddata = $writer->get_data($subcontext);
        $exportedcomments = $writer->get_data(['Comments']);
        $this->assertNotEmpty($expecteddata);
        // The comment should be in the exported data.
        $this->assertStringContainsString('This is a test', $exportedcomments->comments[0]->content);

        // Delete the data now.
        // Only the post by the user under test will be removed.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context->id]
        );
        $this->assertCount(1, $DB->get_records('hsuforum_subscriptions', ['userid' => $user->id]));
        provider::delete_data_for_user($approvedcontextlist);
        $this->assertCount(0, $DB->get_records('hsuforum_subscriptions', ['userid' => $user->id]));
    }

    /**
     * Test that a user who has posted their own discussion will have all
     * content returned.
     */
    public function test_user_has_posted_own_discussion() {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        list($user, $otheruser) = $this->helper_create_users($course, 2);

        // Post twice - only the second discussion should be included.
        list($discussion, $post) = $this->helper_post_to_forum($forum, $user);
        list($otherdiscussion, $otherpost) = $this->helper_post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export all of the data for the context.
        $this->setUser($user);
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data($this->get_subcontext($forum, $otherdiscussion)));

        $this->assert_discussion_data($discussion, $writer->get_data($this->get_subcontext($forum, $discussion)));
        $this->assert_post_data($post, $writer->get_data($this->get_subcontext($forum, $discussion, $post)), $writer);
    }

    /**
     * Test that a user who has posted a reply to another users discussion will have all content returned, and
     * appropriate content removed.
     */
    public function test_user_has_posted_reply() {
        global $DB;

        // Create several courses and forums. We only insert data into the final one.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        list($user, $otheruser) = $this->helper_create_users($course, 2);
        // Post twice - only the second discussion should be included.
        list($discussion, $post) = $this->helper_post_to_forum($forum, $otheruser);
        list($otherdiscussion, $otherpost) = $this->helper_post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Post a reply to the other person's post.
        $reply = $this->helper_reply_to_post($post, $user);

        // Testing as user $user.
        $this->setUser($user);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // Refresh the discussions.
        $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);
        $otherdiscussion = $DB->get_record('hsuforum_discussions', ['id' => $otherdiscussion->id]);

        // The other discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data($this->get_subcontext($forum, $otherdiscussion)));

        // Our discussion should have been returned as we did post in it.
        $data = $writer->get_data($this->get_subcontext($forum, $discussion));
        $this->assertNotEmpty($data);
        $this->assert_discussion_data($discussion, $data);

        // The reply will be included.
        $this->assert_post_data($reply, $writer->get_data($this->get_subcontext($forum, $discussion, $reply)), $writer);

        // Delete the data now.
        // Only the post by the user under test will be removed.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);

        $reply = $DB->get_record('hsuforum_posts', ['id' => $reply->id]);
        $this->assertEmpty($reply->subject);
        $this->assertEmpty($reply->message);
        $this->assertEquals(1, $reply->deleted);

        $post = $DB->get_record('hsuforum_posts', ['id' => $post->id]);
        $this->assertNotEmpty($post->subject);
        $this->assertNotEmpty($post->message);
        $this->assertEquals(0, $post->deleted);
    }

    /**
     * Test that the rating of another users content will have only the
     * rater's information returned.
     */
    public function test_user_has_rated_others() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', [
            'course' => $course->id,
            'scale' => 100,
        ]);
        list($user, $otheruser) = $this->helper_create_users($course, 2);
        list($discussion, $post) = $this->helper_post_to_forum($forum, $otheruser);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Rate the other users content.
        $rm = new rating_manager();
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->component = 'mod_hsuforum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->itemid  = $post->id;
        $ratingoptions->scaleid = $forum->scale;
        $ratingoptions->userid  = $user->id;

        $rating = new \rating($ratingoptions);
        $rating->update_rating(75);

        // Run as the user under test.
        $this->setUser($user);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // The discussion should not have been returned as we did not post in it.
        $this->assertEmpty($writer->get_data($this->get_subcontext($forum, $discussion)));

        $this->assert_all_own_ratings_on_context(
            $user->id,
            $context,
            $this->get_subcontext($forum, $discussion, $post),
            'mod_hsuforum',
            'post',
            $post->id
        );

        // The original post will not be included.
        $this->assert_post_data($post, $writer->get_data($this->get_subcontext($forum, $discussion, $post)), $writer);

        // Delete the data of the user who rated the other user.
        // The rating should not be deleted as it the rating is considered grading data.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Ratings should remain as they are of another user's content.
        $this->assertCount(1, $DB->get_records('rating', ['itemid' => $post->id]));
    }

    /**
     * Test that ratings of a users own content will all be returned.
     */
    public function test_user_has_been_rated() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', [
            'course' => $course->id,
            'scale' => 100,
        ]);
        list($user, $otheruser, $anotheruser) = $this->helper_create_users($course, 3);
        list($discussion, $post) = $this->helper_post_to_forum($forum, $user);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Other users rate my content
        $rm = new rating_manager();
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->component = 'mod_hsuforum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->itemid  = $post->id;
        $ratingoptions->scaleid = $forum->scale;

        $ratingoptions->userid  = $otheruser->id;
        $rating = new \rating($ratingoptions);
        $rating->update_rating(75);

        $ratingoptions->userid  = $anotheruser->id;
        $rating = new \rating($ratingoptions);
        $rating->update_rating(75);

        // Run as the user under test.
        $this->setUser($user);

        // Retrieve all contexts - only this context should be returned.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export all of the data for the context.
        $this->export_context_data_for_user($user->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $this->assert_all_ratings_on_context(
            $context,
            $this->get_subcontext($forum, $discussion, $post),
            'mod_hsuforum',
            'post',
            $post->id
        );

        // Delete the data of the user who was rated.
        // The rating should now be deleted.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);

        // Ratings should remain as they are of another user's content.
        $this->assertCount(0, $DB->get_records('rating', ['itemid' => $post->id]));
    }

    /**
     * Test that per-user daily digest settings are included correctly.
     */
    public function test_user_forum_digest() {
        $course = $this->getDataGenerator()->create_course();
        global $DB;

        $forum0 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm0 = get_coursemodule_from_instance('hsuforum', $forum0->id);
        $context0 = \context_module::instance($cm0->id);

        $forum1 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm1 = get_coursemodule_from_instance('hsuforum', $forum1->id);
        $context1 = \context_module::instance($cm1->id);

        $forum2 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('hsuforum', $forum2->id);
        $context2 = \context_module::instance($cm2->id);

        $forum3 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm3 = get_coursemodule_from_instance('hsuforum', $forum3->id);
        $context3 = \context_module::instance($cm3->id);

        list($user) = $this->helper_create_users($course, 1);

        // Set a digest value for each forum.
        hsuforum_set_user_maildigest($forum0, 0, $user);
        hsuforum_set_user_maildigest($forum1, 1, $user);
        hsuforum_set_user_maildigest($forum2, 2, $user);

        // Run as the user under test.
        $this->setUser($user);

        // Retrieve all contexts - three contexts should be returned - the fourth should not be included.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(3, $contextlist);

        $contextids = [
                $context0->id,
                $context1->id,
                $context2->id,
            ];
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);

        // Check export data for each context.
        $this->export_context_data_for_user($user->id, $context0, 'mod_hsuforum');
        $this->assertEquals(0, \core_privacy\local\request\writer::with_context($context0)->get_metadata([], 'digestpreference'));

        $this->export_context_data_for_user($user->id, $context1, 'mod_hsuforum');
        $this->assertEquals(1, \core_privacy\local\request\writer::with_context($context1)->get_metadata([], 'digestpreference'));

        $this->export_context_data_for_user($user->id, $context2, 'mod_hsuforum');
        $this->assertEquals(2, \core_privacy\local\request\writer::with_context($context2)->get_metadata([], 'digestpreference'));

        // Delete the data for one of the users in one of the forums.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context1->id]
        );

        $this->assertEquals(0, $DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum0->id]));
        $this->assertEquals(1, $DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum1->id]));
        $this->assertEquals(2, $DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum2->id]));
        provider::delete_data_for_user($approvedcontextlist);
        $this->assertEquals(0, $DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum0->id]));
        $this->assertFalse($DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum1->id]));
        $this->assertEquals(2, $DB->get_field('hsuforum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forum2->id]));
    }

    /**
     * Test that the posts which a user has read are returned correctly.
     */
    public function test_user_read_posts() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $forum1 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm1 = get_coursemodule_from_instance('hsuforum', $forum1->id);
        $context1 = \context_module::instance($cm1->id);

        $forum2 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('hsuforum', $forum2->id);
        $context2 = \context_module::instance($cm2->id);

        $forum3 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm3 = get_coursemodule_from_instance('hsuforum', $forum3->id);
        $context3 = \context_module::instance($cm3->id);

        $forum4 = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id]);
        $cm4 = get_coursemodule_from_instance('hsuforum', $forum4->id);
        $context4 = \context_module::instance($cm4->id);

        list($author, $user) = $this->helper_create_users($course, 2);

        list($f1d1, $f1p1) = $this->helper_post_to_forum($forum1, $author);
        $f1p1reply = $this->helper_post_to_discussion($forum1, $f1d1, $author);
        $f1d1 = $DB->get_record('hsuforum_discussions', ['id' => $f1d1->id]);
        list($f1d2, $f1p2) = $this->helper_post_to_forum($forum1, $author);

        list($f2d1, $f2p1) = $this->helper_post_to_forum($forum2, $author);
        $f2p1reply = $this->helper_post_to_discussion($forum2, $f2d1, $author);
        $f2d1 = $DB->get_record('hsuforum_discussions', ['id' => $f2d1->id]);
        list($f2d2, $f2p2) = $this->helper_post_to_forum($forum2, $author);

        list($f3d1, $f3p1) = $this->helper_post_to_forum($forum3, $author);
        $f3p1reply = $this->helper_post_to_discussion($forum3, $f3d1, $author);
        $f3d1 = $DB->get_record('hsuforum_discussions', ['id' => $f3d1->id]);
        list($f3d2, $f3p2) = $this->helper_post_to_forum($forum3, $author);

        list($f4d1, $f4p1) = $this->helper_post_to_forum($forum4, $author);
        $f4p1reply = $this->helper_post_to_discussion($forum4, $f4d1, $author);
        $f4d1 = $DB->get_record('hsuforum_discussions', ['id' => $f4d1->id]);
        list($f4d2, $f4p2) = $this->helper_post_to_forum($forum4, $author);

        // Insert read info.
        // User has read post1, but not the reply or second post in forum1.
        hsuforum_tp_add_read_record($user->id, $f1p1->id);

        // User has read post1 and its reply, but not the second post in forum2.
        hsuforum_tp_add_read_record($user->id, $f2p1->id);
        hsuforum_tp_add_read_record($user->id, $f2p1reply->id);

        // User has read post2 in forum3.
        hsuforum_tp_add_read_record($user->id, $f3p2->id);

        // Nothing has been read in forum4.

        // Run as the user under test.
        $this->setUser($user);

        // Retrieve all contexts - should be three - forum4 has no data.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(3, $contextlist);

        $contextids = [
                $context1->id,
                $context2->id,
                $context3->id,
            ];
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);

        // Forum 1.
        $this->export_context_data_for_user($user->id, $context1, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context1);

        // User has read f1p1.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum1, $f1d1, $f1p1),
                'postread'
            );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));

        // User has not f1p1reply.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum1, $f1d1, $f1p1reply),
                'postread'
            );
        $this->assertEmpty($readdata);

        // User has not f1p2.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum1, $f1d2, $f1p2),
                'postread'
            );
        $this->assertEmpty($readdata);

        // Forum 2.
        $this->export_context_data_for_user($user->id, $context2, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context2);

        // User has read f2p1.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum2, $f2d1, $f2p1),
                'postread'
            );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));

        // User has read f2p1reply.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum2, $f2d1, $f2p1reply),
                'postread'
            );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));

        // User has not read f2p2.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum2, $f2d2, $f2p2),
                'postread'
            );
        $this->assertEmpty($readdata);

        // Forum 3.
        $this->export_context_data_for_user($user->id, $context3, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context3);

        // User has not read f3p1.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum3, $f3d1, $f3p1),
                'postread'
            );
        $this->assertEmpty($readdata);

        // User has not read f3p1reply.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum3, $f3d1, $f3p1reply),
                'postread'
            );
        $this->assertEmpty($readdata);

        // User has read f3p2.
        $readdata = $writer->get_metadata(
                $this->get_subcontext($forum3, $f3d2, $f3p2),
                'postread'
            );
        $this->assertNotEmpty($readdata);
        $this->assertTrue(isset($readdata->firstread));
        $this->assertTrue(isset($readdata->lastread));

        // Delete all data for one of the users in one of the forums.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hsuforum',
            [$context3->id]
        );

        $this->assertTrue($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum1->id]));
        $this->assertTrue($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum2->id]));
        $this->assertTrue($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum3->id]));

        provider::delete_data_for_user($approvedcontextlist);

        $this->assertTrue($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum1->id]));
        $this->assertTrue($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum2->id]));
        $this->assertFalse($DB->record_exists('hsuforum_read', ['userid' => $user->id, 'forumid' => $forum3->id]));
    }

    /**
     * Test that posts with attachments have their attachments correctly exported.
     */
    public function test_post_attachment_inclusion() {
        global $DB;

        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        list($author, $otheruser) = $this->helper_create_users($course, 2);

        $forum = $this->getDataGenerator()->create_module('hsuforum', [
            'course' => $course->id,
            'scale' => 100,
        ]);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Create a new discussion + post in the forum.
        list($discussion, $post) = $this->helper_post_to_forum($forum, $author);
        $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);

        // Add a number of replies.
        $reply = $this->helper_reply_to_post($post, $author);
        $reply = $this->helper_reply_to_post($post, $author);
        $reply = $this->helper_reply_to_post($reply, $author);
        $posts[$reply->id] = $reply;

        // Add a fake inline image to the original post.
        $createdfile = $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_hsuforum',
                'filearea'  => 'post',
                'itemid'    => $post->id,
                'filepath'  => '/',
                'filename'  => 'example.jpg',
            ],
        'image contents (not really)');

        // Tag the post and the final reply.
        \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $post->id, $context, ['example', 'tag']);
        \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $reply->id, $context, ['example', 'differenttag']);

        // Create a second discussion + post in the forum without tags.
        list($otherdiscussion, $otherpost) = $this->helper_post_to_forum($forum, $author);
        $otherdiscussion = $DB->get_record('hsuforum_discussions', ['id' => $otherdiscussion->id]);

        // Add a number of replies.
        $reply = $this->helper_reply_to_post($otherpost, $author);
        $reply = $this->helper_reply_to_post($otherpost, $author);

        // Run as the user under test.
        $this->setUser($author);

        // Retrieve all contexts - should be one.
        $contextlist = $this->get_contexts_for_userid($author->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);

        $this->export_context_data_for_user($author->id, $context, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);

        // The inline file should be on the first forum post.
        $subcontext = $this->get_subcontext($forum, $discussion, $post);
        $foundfiles = $writer->get_files($subcontext);
        $this->assertCount(1, $foundfiles);
        $this->assertEquals($createdfile, reset($foundfiles));
    }

    /**
     * Test that posts which include tags have those tags exported.
     */
    public function test_post_tags() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        list($author, $otheruser) = $this->helper_create_users($course, 2);

        $forum = $this->getDataGenerator()->create_module('hsuforum', [
            'course' => $course->id,
            'scale' => 100,
        ]);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
        $context = \context_module::instance($cm->id);

        // Create a new discussion + post in the forum.
        list($discussion, $post) = $this->helper_post_to_forum($forum, $author);
        $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);

        // Add a number of replies.
        $reply = $this->helper_reply_to_post($post, $author);
        $reply = $this->helper_reply_to_post($post, $author);
        $reply = $this->helper_reply_to_post($reply, $author);
        $posts[$reply->id] = $reply;

        // Tag the post and the final reply.
        \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $post->id, $context, ['example', 'tag']);
        \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $reply->id, $context, ['example', 'differenttag']);

        // Create a second discussion + post in the forum without tags.
        list($otherdiscussion, $otherpost) = $this->helper_post_to_forum($forum, $author);
        $otherdiscussion = $DB->get_record('hsuforum_discussions', ['id' => $otherdiscussion->id]);

        // Add a number of replies.
        $reply = $this->helper_reply_to_post($otherpost, $author);
        $reply = $this->helper_reply_to_post($otherpost, $author);

        // Run as the user under test.
        $this->setUser($author);

        // Retrieve all contexts - should be two.
        $contextlist = $this->get_contexts_for_userid($author->id, 'mod_hsuforum');
        $this->assertCount(1, $contextlist);

        $this->export_all_data_for_user($author->id, 'mod_hsuforum');
        $writer = \core_privacy\local\request\writer::with_context($context);


        $this->assert_all_tags_match_on_context(
            $author->id,
            $context,
            $this->get_subcontext($forum, $discussion, $post),
            'mod_hsuforum',
            'hsuforum_posts',
            $post->id
        );
    }

    /**
     * Ensure that all user data is deleted from a context.
     */
    public function test_all_users_deleted_from_context() {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->helper_create_users($course, 5);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('hsuforum', [
                'course' => $course->id,
                'scale' => 100,
            ]);
            $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
            $context = \context_module::instance($cm->id);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }
        $discussions = [];
        $posts = [];
        foreach ($users as $user) {
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];
                // Create a new discussion + post in the forum.
                list($discussion, $post) = $this->helper_post_to_forum($forum, $user);
                $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                // Add a number of replies.
                $posts[$post->id] = $post;
                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $reply = $this->helper_reply_to_post($reply, $user);
                $posts[$reply->id] = $reply;
                // Add a fake inline image to the original post.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'post',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');

                // Add a fake file as attachment.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'attachment',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.txt',
                ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user.
        $user = reset($users);
        $ratedposts = [];
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->forum];
            $context = $contexts[$forum->id];
            // Mark the post as being read by user.
            forum_tp_add_read_record($user->id, $post->id);
            // Tag the post.
            \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $post->id, $context, ['example', 'tag']);
            // Rate the other users content.
            if ($post->userid != $user->id) {
                $ratedposts[$post->id] = $post;
                $rm = new rating_manager();
                $ratingoptions = (object) [
                    'context' => $context,
                    'component' => 'mod_hsuforum',
                    'ratingarea' => 'post',
                    'itemid' => $post->id,
                    'scaleid' => $forum->scale,
                    'userid' => $user->id,
                ];
                $rating = new \rating($ratingoptions);
                $rating->update_rating(75);
            }
        }
        // Run as the user under test.
        $this->setUser($user);
        // Retrieve all contexts - should be two.
        $contextlist = $this->get_contexts_for_userid($user->id, 'mod_hsuforum');
        $this->assertCount(2, $contextlist);
        // These are the contexts we expect.
        $contextids = array_map(function($context) {
            return $context->id;
        }, $contexts);
        sort($contextids);
        $contextlistids = $contextlist->get_contextids();
        sort($contextlistids);
        $this->assertEquals($contextids, $contextlistids);
        // Delete for the first forum.
        $forum = reset($forums);
        $context = $contexts[$forum->id];
        provider::delete_data_for_all_users_in_context($context);
        // Determine what should have been deleted.
        $discussionsinforum = array_filter($discussions, function($discussion) use ($forum) {
            return $discussion->forum == $forum->id;
        });
        $postsinforum = array_filter($posts, function($post) use ($discussionsinforum) {
            return isset($discussionsinforum[$post->discussion]);
        });
        // All forum discussions and posts should have been deleted in this forum.
        $this->assertCount(0, $DB->get_records('hsuforum_discussions', ['forum' => $forum->id]));
        list ($insql, $inparams) = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertCount(0, $DB->get_records_select('hsuforum_posts', "discussion {$insql}", $inparams));
        // All uploaded files relating to this context should have been deleted (post content).
        foreach ($postsinforum as $post) {
            $this->assertEmpty($fs->get_area_files($context->id, 'mod_hsuforum', 'post', $post->id));
            $this->assertEmpty($fs->get_area_files($context->id, 'mod_hsuforum', 'attachment', $post->id));
        }
        // All ratings should have been deleted.
        $rm = new rating_manager();
        foreach ($postsinforum as $post) {
            $ratings = $rm->get_all_ratings_for_item((object) [
                'context' => $context,
                'component' => 'mod_hsuforum',
                'ratingarea' => 'post',
                'itemid' => $post->id,
            ]);
            $this->assertEmpty($ratings);
        }
        // All tags should have been deleted.
        $posttags = \core_tag_tag::get_items_tags('mod_hsuforum', 'hsuforum_posts', array_keys($postsinforum));
        foreach ($posttags as $tags) {
            $this->assertEmpty($tags);
        }
        // Check the other forum too. It should remain intact.
        $forum = next($forums);
        $context = $contexts[$forum->id];
        // Grab the list of discussions and posts in the forum.
        $discussionsinforum = array_filter($discussions, function($discussion) use ($forum) {
            return $discussion->forum == $forum->id;
        });
        $postsinforum = array_filter($posts, function($post) use ($discussionsinforum) {
            return isset($discussionsinforum[$post->discussion]);
        });
        // Forum discussions and posts should not have been deleted in this forum.
        $this->assertGreaterThan(0, $DB->count_records('hsuforum_discussions', ['forum' => $forum->id]));
        list ($insql, $inparams) = $DB->get_in_or_equal(array_keys($discussionsinforum));
        $this->assertGreaterThan(0, $DB->count_records_select('hsuforum_posts', "discussion {$insql}", $inparams));
        // Uploaded files relating to this context should remain.
        foreach ($postsinforum as $post) {
            if ($post->parent == 0) {
                $this->assertNotEmpty($fs->get_area_files($context->id, 'mod_hsuforum', 'post', $post->id));
            }
        }
        // Ratings should not have been deleted.
        $rm = new rating_manager();
        foreach ($postsinforum as $post) {
            if (!isset($ratedposts[$post->id])) {
                continue;
            }
            $ratings = $rm->get_all_ratings_for_item((object) [
                'context' => $context,
                'component' => 'mod_hsuforum',
                'ratingarea' => 'post',
                'itemid' => $post->id,
            ]);
            $this->assertNotEmpty($ratings);
        }
        // All tags should remain.
        $posttags = \core_tag_tag::get_items_tags('mod_hsuforum', 'hsuforum_posts', array_keys($postsinforum));
        foreach ($posttags as $tags) {
            $this->assertNotEmpty($tags);
        }
    }


    /**
     * Ensure that all user data is deleted for a specific context.
     */
    public function test_delete_data_for_user() {
        global $DB;
        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->helper_create_users($course, 5);
        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('hsuforum', [
                'course' => $course->id,
                'scale' => 100,
            ]);
            $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
            $context = \context_module::instance($cm->id);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }
        $discussions = [];
        $posts = [];
        $postsbyforum = [];
        foreach ($users as $user) {
            $postsbyforum[$user->id] = [];
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];
                // Create a new discussion + post in the forum.
                list($discussion, $post) = $this->helper_post_to_forum($forum, $user);
                $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                $postsbyforum[$user->id][$context->id] = [];
                // Add a number of replies.
                $posts[$post->id] = $post;
                $thisforumposts[$post->id] = $post;
                $postsbyforum[$user->id][$context->id][$post->id] = $post;
                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                $reply = $this->helper_reply_to_post($reply, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;
                // Add a fake inline image to the original post.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'post',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');

                // Add a fake file as attachment.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'attachment',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.txt',
                ], 'image contents (not really)');
            }
        }
        // Mark all posts as read by user1.
        $user1 = reset($users);
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->forum];
            $context = $contexts[$forum->id];
            // Mark the post as being read by user1.
            forum_tp_add_read_record($user1->id, $post->id);
        }
        // Rate and tag all posts.
        $ratedposts = [];
        foreach ($users as $user) {
            foreach ($posts as $post) {
                $discussion = $discussions[$post->discussion];
                $forum = $forums[$discussion->forum];
                $context = $contexts[$forum->id];
                // Tag the post.
                \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $post->id, $context, ['example', 'tag']);
                // Rate the other users content.
                if ($post->userid != $user->id) {
                    $ratedposts[$post->id] = $post;
                    $rm = new rating_manager();
                    $ratingoptions = (object) [
                        'context' => $context,
                        'component' => 'mod_hsuforum',
                        'ratingarea' => 'post',
                        'itemid' => $post->id,
                        'scaleid' => $forum->scale,
                        'userid' => $user->id,
                    ];
                    $rating = new \rating($ratingoptions);
                    $rating->update_rating(75);
                }
            }
        }
        // Delete for one of the forums for the first user.
        $firstcontext = reset($contexts);
        $deletedpostids = [];
        $otherpostids = [];
        foreach ($postsbyforum as $user => $contexts) {
            foreach ($contexts as $thiscontextid => $theseposts) {
                $thesepostids = array_map(function($post) {
                    return $post->id;
                }, $theseposts);
                if ($user == $user1->id && $thiscontextid == $firstcontext->id) {
                    // This post is in the deleted context and by the target user.
                    $deletedpostids = array_merge($deletedpostids, $thesepostids);
                } else {
                    // This post is by another user, or in a non-target context.
                    $otherpostids = array_merge($otherpostids, $thesepostids);
                }
            }
        }
        list($postinsql, $postinparams) = $DB->get_in_or_equal($deletedpostids, SQL_PARAMS_NAMED);
        list($otherpostinsql, $otherpostinparams) = $DB->get_in_or_equal($otherpostids, SQL_PARAMS_NAMED);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user1->id),
            'mod_hsuforum',
            [$firstcontext->id]
        );
        provider::delete_data_for_user($approvedcontextlist);
        // All posts should remain.
        $this->assertCount(40, $DB->get_records('hsuforum_posts'));
        // There should be 8 posts belonging to user1.
        $this->assertCount(8, $DB->get_records('hsuforum_posts', [
            'userid' => $user1->id,
        ]));
        // Four of those posts should have been marked as deleted.
        // That means that the deleted flag is set, and both the subject and message are empty.
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts', "userid = :userid AND deleted = :deleted"
            . " AND " . $DB->sql_compare_text('subject') . " = " . $DB->sql_compare_text(':subject')
            . " AND " . $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message')
            , [
                'userid' => $user1->id,
                'deleted' => 1,
                'subject' => '',
                'message' => '',
            ]));
        // Only user1's posts should have been marked this way.
        $this->assertCount(4, $DB->get_records('hsuforum_posts', [
            'deleted' => 1,
        ]));
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            $DB->sql_compare_text('subject') . " = " . $DB->sql_compare_text(':subject'), [
                'subject' => '',
            ]));
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message'), [
                'message' => '',
            ]));
        // Only the posts in the first discussion should have been marked this way.
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            "deleted = :deleted AND id {$postinsql}",
            array_merge($postinparams, [
                'deleted' => 1,
            ])
        ));
        // Ratings should have been removed from the affected posts.
        $this->assertCount(0, $DB->get_records_select('rating', "itemid {$postinsql}", $postinparams));
        // Ratings should remain on posts in the other context, and posts not belonging to the affected user.
        $this->assertCount(144, $DB->get_records_select('rating', "itemid {$otherpostinsql}", $otherpostinparams));
        // Ratings should remain where the user has rated another person's post.
        $this->assertCount(32, $DB->get_records('rating', ['userid' => $user1->id]));
        // Tags for the affected posts should be removed.
        $this->assertCount(0, $DB->get_records_select('tag_instance', "itemid {$postinsql}", $postinparams));
        // Tags should remain for the other posts by this user, and all posts by other users.
        $this->assertCount(72, $DB->get_records_select('tag_instance', "itemid {$otherpostinsql}", $otherpostinparams));
        // Files for the affected posts should be removed.
        // 5 users * 2 forums * 1 file in each forum
        // Original total: 10
        // One post with file removed.
        $this->assertCount(0, $DB->get_records_select('files', "itemid {$postinsql}", $postinparams));
        // Files for the other posts should remain.
        $this->assertCount(18, $DB->get_records_select('files', "filename <> '.' AND itemid {$otherpostinsql}", $otherpostinparams));
    }


    /**
     * Ensure that user data for specific users is deleted from a specified context.
     */
    public function test_delete_data_for_users() {
        global $DB;

        $fs = get_file_storage();
        $course = $this->getDataGenerator()->create_course();
        $users = $this->helper_create_users($course, 5);

        $forums = [];
        $contexts = [];
        for ($i = 0; $i < 2; $i++) {
            $forum = $this->getDataGenerator()->create_module('hsuforum', [
                'course' => $course->id,
                'scale' => 100,
            ]);
            $cm = get_coursemodule_from_instance('hsuforum', $forum->id);
            $context = \context_module::instance($cm->id);
            $forums[$forum->id] = $forum;
            $contexts[$forum->id] = $context;
        }

        $discussions = [];
        $posts = [];
        $postsbyforum = [];
        foreach ($users as $user) {
            $postsbyforum[$user->id] = [];
            foreach ($forums as $forum) {
                $context = $contexts[$forum->id];

                // Create a new discussion + post in the forum.
                list($discussion, $post) = $this->helper_post_to_forum($forum, $user);
                $discussion = $DB->get_record('hsuforum_discussions', ['id' => $discussion->id]);
                $discussions[$discussion->id] = $discussion;
                $postsbyforum[$user->id][$context->id] = [];

                // Add a number of replies.
                $posts[$post->id] = $post;
                $thisforumposts[$post->id] = $post;
                $postsbyforum[$user->id][$context->id][$post->id] = $post;

                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;

                $reply = $this->helper_reply_to_post($post, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;

                $reply = $this->helper_reply_to_post($reply, $user);
                $posts[$reply->id] = $reply;
                $postsbyforum[$user->id][$context->id][$reply->id] = $reply;

                // Add a fake inline image to the original post.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'post',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');
                // And a fake attachment.
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_hsuforum',
                    'filearea'  => 'attachment',
                    'itemid'    => $post->id,
                    'filepath'  => '/',
                    'filename'  => 'example.jpg',
                ], 'image contents (not really)');
            }
        }

        // Mark all posts as read by user1.
        $user1 = reset($users);
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion];
            $forum = $forums[$discussion->forum];
            $context = $contexts[$forum->id];

            // Mark the post as being read by user1.
            hsuforum_tp_add_read_record($user1->id, $post->id);
        }

        // Rate and tag all posts.
        $ratedposts = [];
        foreach ($users as $user) {
            foreach ($posts as $post) {
                $discussion = $discussions[$post->discussion];
                $forum = $forums[$discussion->forum];
                $context = $contexts[$forum->id];

                // Tag the post.
                \core_tag_tag::set_item_tags('mod_hsuforum', 'hsuforum_posts', $post->id, $context, ['example', 'tag']);

                // Rate the other users content.
                if ($post->userid != $user->id) {
                    $ratedposts[$post->id] = $post;
                    $rm = new rating_manager();
                    $ratingoptions = (object) [
                        'context' => $context,
                        'component' => 'mod_hsuforum',
                        'ratingarea' => 'post',
                        'itemid' => $post->id,
                        'scaleid' => $forum->scale,
                        'userid' => $user->id,
                    ];

                    $rating = new \rating($ratingoptions);
                    $rating->update_rating(75);
                }
            }
        }

        // Delete for one of the forums for the first user.
        $firstcontext = reset($contexts);

        $deletedpostids = [];
        $otherpostids = [];
        foreach ($postsbyforum as $user => $contexts) {
            foreach ($contexts as $thiscontextid => $theseposts) {
                $thesepostids = array_map(function($post) {
                    return $post->id;
                }, $theseposts);

                if ($user == $user1->id && $thiscontextid == $firstcontext->id) {
                    // This post is in the deleted context and by the target user.
                    $deletedpostids = array_merge($deletedpostids, $thesepostids);
                } else {
                    // This post is by another user, or in a non-target context.
                    $otherpostids = array_merge($otherpostids, $thesepostids);
                }
            }
        }
        list($postinsql, $postinparams) = $DB->get_in_or_equal($deletedpostids, SQL_PARAMS_NAMED);
        list($otherpostinsql, $otherpostinparams) = $DB->get_in_or_equal($otherpostids, SQL_PARAMS_NAMED);

        $approveduserlist = new \core_privacy\local\request\approved_userlist($firstcontext, 'mod_hsuforum', [$user1->id]);
        provider::delete_data_for_users($approveduserlist);

        // All posts should remain.
        $this->assertCount(40, $DB->get_records('hsuforum_posts'));

        // There should be 8 posts belonging to user1.
        $this->assertCount(8, $DB->get_records('hsuforum_posts', [
            'userid' => $user1->id,
        ]));

        // Four of those posts should have been marked as deleted.
        // That means that the deleted flag is set, and both the subject and message are empty.
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts', "userid = :userid AND deleted = :deleted"
            . " AND " . $DB->sql_compare_text('subject') . " = " . $DB->sql_compare_text(':subject')
            . " AND " . $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message')
            , [
                'userid' => $user1->id,
                'deleted' => 1,
                'subject' => '',
                'message' => '',
            ]));

        // Only user1's posts should have been marked this way.
        $this->assertCount(4, $DB->get_records('hsuforum_posts', [
            'deleted' => 1,
        ]));
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            $DB->sql_compare_text('subject') . " = " . $DB->sql_compare_text(':subject'), [
                'subject' => '',
            ]));
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            $DB->sql_compare_text('message') . " = " . $DB->sql_compare_text(':message'), [
                'message' => '',
            ]));

        // Only the posts in the first discussion should have been marked this way.
        $this->assertCount(4, $DB->get_records_select('hsuforum_posts',
            "deleted = :deleted AND id {$postinsql}",
            array_merge($postinparams, [
                'deleted' => 1,
            ])
        ));

        // Ratings should have been removed from the affected posts.
        $this->assertCount(0, $DB->get_records_select('rating', "itemid {$postinsql}", $postinparams));

        // Ratings should remain on posts in the other context, and posts not belonging to the affected user.
        $this->assertCount(144, $DB->get_records_select('rating', "itemid {$otherpostinsql}", $otherpostinparams));

        // Ratings should remain where the user has rated another person's post.
        $this->assertCount(32, $DB->get_records('rating', ['userid' => $user1->id]));

        // Tags for the affected posts should be removed.
        $this->assertCount(0, $DB->get_records_select('tag_instance', "itemid {$postinsql}", $postinparams));

        // Tags should remain for the other posts by this user, and all posts by other users.
        $this->assertCount(72, $DB->get_records_select('tag_instance', "itemid {$otherpostinsql}", $otherpostinparams));

        // Files for the affected posts should be removed.
        // 5 users * 2 forums * 1 file in each forum
        // Original total: 10
        // One post with file removed.
        $this->assertCount(0, $DB->get_records_select('files', "itemid {$postinsql}", $postinparams));

        // Files for the other posts should remain.
        $this->assertCount(18,
            $DB->get_records_select('files', "filename <> '.' AND itemid {$otherpostinsql}", $otherpostinparams));
    }
}
