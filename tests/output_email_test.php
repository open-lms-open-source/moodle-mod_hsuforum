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
 * The module forums tests
 *
 * @package    mod_hsuforum
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the forum output/email class.
 *
 * @copyright  2016 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hsuforum_output_email_testcase extends advanced_testcase {
    /**
     * Data provider for the postdate function tests.
     */
    public function postdate_provider() {
        return array(
            'Timed discussions disabled, timestart unset' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 0,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions disabled, timestart set and newer' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 0,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 2000,
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions disabled, timestart set but older' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 0,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 500,
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions enabled, timestart unset' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 1,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                ),
                'expectation'       => 1000,
            ),
            'Timed discussions enabled, timestart set and newer' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 1,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 2000,
                ),
                'expectation'       => 2000,
            ),
            'Timed discussions enabled, timestart set but older' => array(
                'globalconfig'      => array(
                    'hsuforum_enabletimedposts' => 1,
                ),
                'forumconfig'       => array(
                ),
                'postconfig'        => array(
                    'modified'  => 1000,
                ),
                'discussionconfig'  => array(
                    'timestart' => 500,
                ),
                'expectation'       => 1000,
            ),
        );
    }

    /**
     * Test for the forum email renderable postdate.
     *
     * @dataProvider postdate_provider
     *
     * @param array  $globalconfig      The configuration to set on $CFG
     * @param array  $forumconfig       The configuration for this forum
     * @param array  $postconfig        The configuration for this post
     * @param array  $discussionconfig  The configuration for this discussion
     * @param string $expectation       The expected date
     */
    public function test_postdate($globalconfig, $forumconfig, $postconfig, $discussionconfig, $expectation) {
        global $CFG, $DB;
        $this->resetAfterTest(true);

        // Apply the global configuration.
        foreach ($globalconfig as $key => $value) {
            if ($key == 'hsuforum_enabletimedposts') {
                set_config('enabletimedposts', $value, 'hsuforum');
            } else {
                $CFG->$key = $value;
            }
        }

        // Create the fixture.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', (object) array('course' => $course->id));
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a new discussion.
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion(
            (object) array_merge($discussionconfig, array(
                'course'    => $course->id,
                'forum'     => $forum->id,
                'userid'    => $user->id,
            )));

        // Apply the discussion configuration.
        // Some settings are ignored by the generator and must be set manually.
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussion->id));
        foreach ($discussionconfig as $key => $value) {
            $discussion->$key = $value;
        }
        $DB->update_record('hsuforum_discussions', $discussion);

        // Apply the post configuration.
        // Some settings are ignored by the generator and must be set manually.
        $post = $DB->get_record('hsuforum_posts', array('discussion' => $discussion->id));
        foreach ($postconfig as $key => $value) {
            $post->$key = $value;
        }
        $DB->update_record('hsuforum_posts', $post);

        // Create the renderable.
        $renderable = new mod_hsuforum\output\hsuforum_post_email(
                $course,
                $cm,
                $forum,
                $discussion,
                $post,
                $user,
                $user,
                true
            );

        // Check the postdate matches our expectations.
        $this->assertEquals(userdate($expectation, "", \core_date::get_user_timezone($user)), $renderable->get_postdate());
    }

    public function test_anonymous_author() {
        global $OUTPUT, $DB, $PAGE;
        $this->resetAfterTest(true);

        // Create the fixture (including an anonymous forum).
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('hsuforum', ['course' => $course->id, 'anonymous' => 1]);
        $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);

        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create a new discussion.
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_hsuforum')->create_discussion(
                [
                    'course'    => $course->id,
                    'forum'     => $forum->id,
                    'userid'    => $user->id,
                ]);

        $post = $DB->get_record('hsuforum_posts', ['discussion' => $discussion->id, 'userid' => $user->id]);

        $anonuser = hsuforum_anonymize_user($user, $forum, $post);
        $postemail = new \mod_hsuforum\output\hsuforum_post_email($course, $cm, $forum, $discussion, $post, $anonuser,
                get_admin(), true);

        $anonprofileurl = new moodle_url('/user/view.php', ['id' => $anonuser->id, 'course' => $course->id]);
        $anonprofilepic = $OUTPUT->user_picture($anonuser, ['courseid' => $course->id, 'link' => false]);

        $renderer = $PAGE->get_renderer('mod_hsuforum');
        $this->assertEquals($anonprofileurl->out(false), $postemail->get_authorlink($renderer));
        $this->assertEquals($anonprofilepic, $postemail->get_author_picture($renderer));
    }
}
