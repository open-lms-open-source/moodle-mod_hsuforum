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
 * A Handler to process replies to forum posts.
 *
 * @package    mod_hsuforum
 * @subpackage core_message
 * @copyright  2014 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\message\inbound;

use mod_hsuforum\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hsuforum/lib.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * A Handler to process replies to forum posts.
 *
 * @copyright  2014 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reply_handler extends \core\message\inbound\handler {

    /**
     * Return a description for the current handler.
     *
     * @return string
     */
    public function get_description() {
        return get_string('reply_handler', 'mod_hsuforum');
    }

    /**
     * Return a short name for the current handler.
     * This appears in the admin pages as a human-readable name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('reply_handler_name', 'mod_hsuforum');
    }

    /**
     * Process a message received and validated by the Inbound Message processor.
     *
     * @throws \core\message\inbound\processing_failed_exception
     * @param \stdClass $messagedata The Inbound Message record
     * @param \stdClass $messagedata The message data packet
     * @return bool Whether the message was successfully processed.
     */
    public function process_message(\stdClass $record, \stdClass $messagedata) {
        global $DB, $USER;

        // Load the post being replied to.
        $post = $DB->get_record('hsuforum_posts', array('id' => $record->datavalue));
        if (!$post) {
            mtrace("--> Unable to find an hsuforum post matching with id {$record->datavalue}");
            return false;
        }

        // Load the discussion that this post is in.
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion));
        if (!$post) {
            mtrace("--> Unable to find the hsuforum discussion for post {$record->datavalue}");
            return false;
        }

        // Load the other required data.
        $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum));
        $course = $DB->get_record('course', array('id' => $forum->course));
        $cm = get_fast_modinfo($course->id)->instances['hsuforum'][$forum->id];
        $modcontext = \context_module::instance($cm->id);
        $usercontext = \context_user::instance($USER->id);

        // Make sure user can post in this discussion.
        $canpost = true;
        if (!hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext)) {
            $canpost = false;
        }

        if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
            $groupmode = $cm->groupmode;
        } else {
            $groupmode = $course->groupmode;
        }
        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($discussion->groupid == -1) {
                $canpost = false;
            } else {
                if (!groups_is_member($discussion->groupid)) {
                    $canpost = false;
                }
            }
        }

        if (!$canpost) {
            $data = new \stdClass();
            $data->forum = $forum;
            throw new \core\message\inbound\processing_failed_exception('messageinboundnopostforum', 'mod_hsuforum', $data);
        }

        // And check the availability.
        if (!\core_availability\info_module::is_user_visible($cm)) {
            $data = new \stdClass();
            $data->forum = $forum;
            throw new \core\message\inbound\processing_failed_exception('messageinboundforumhidden', 'mod_hsuforum', $data);
        }

        // Before we add this we must check that the user will not exceed the blocking threshold.
        // This should result in an appropriate reply.
        $thresholdwarning = hsuforum_check_throttling($forum, $cm);
        if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
            $data = new \stdClass();
            $data->forum = $forum;
            $data->message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
            throw new \core\message\inbound\processing_failed_exception('messageinboundthresholdhit', 'mod_hsuforum', $data);
        }

        $subject = clean_param($messagedata->envelope->subject, PARAM_TEXT);
        $restring = get_string('re', 'hsuforum');
        if (strpos($subject, $discussion->name)) {
            // The discussion name is mentioned in the e-mail subject. This is probably just the standard reply. Use the
            // standard reply subject instead.
            $newsubject = $restring . ' ' . $discussion->name;
            mtrace("--> Note: Post subject matched discussion name. Optimising from {$subject} to {$newsubject}");
            $subject = $newsubject;
        } else if (strpos($subject, $post->subject)) {
            // The replied-to post's subject is mentioned in the e-mail subject.
            // Use the previous post's subject instead of the e-mail subject.
            $newsubject = $post->subject;
            if (!strpos($restring, $post->subject)) {
                // The previous post did not contain a re string, add it.
                $newsubject = $restring . ' ' . $newsubject;
            }
            mtrace("--> Note: Post subject matched original post subject. Optimising from {$subject} to {$newsubject}");
            $subject = $newsubject;
        }

        $addpost = new \stdClass();
        $addpost->course       = $course->id;
        $addpost->forum        = $forum->id;
        $addpost->discussion   = $discussion->id;
        $addpost->modified     = $messagedata->timestamp;
        $addpost->subject      = local::shorten_post_name($subject);
        $addpost->parent       = $post->id;
        $addpost->itemid       = file_get_unused_draft_itemid();
        $addpost->reveal       = 0;
        $addpost->privatereply = 0;
        $addpost->flags        = null;

        list ($message, $format) = self::remove_quoted_text($messagedata);
        $addpost->message = $message;
        $addpost->messageformat = $format;

        // We don't trust text coming from e-mail.
        $addpost->messagetrust = false;

        // Find all attachments. If format is plain text, treat inline attachments as regular ones.
        $attachments = !empty($messagedata->attachments['attachment']) ? $messagedata->attachments['attachment'] : [];
        $inlineattachments = [];
        if (!empty($messagedata->attachments['inline'])) {
            if ($addpost->messageformat == FORMAT_HTML) {
                $inlineattachments = $messagedata->attachments['inline'];
            } else {
                $attachments = array_merge($attachments, $messagedata->attachments['inline']);
            }
        }

        // Add attachments to the post.
        if ($attachments) {
            $attachmentcount = count($attachments);
            if (!hsuforum_can_create_attachment($forum, $modcontext)) {
                // Attachments are not allowed.
                mtrace("--> User does not have permission to attach files in this hsuforum. Rejecting e-mail.");

                $data = new \stdClass();
                $data->forum = $forum;
                $data->attachmentcount = $attachmentcount;
                throw new \core\message\inbound\processing_failed_exception('messageinboundattachmentdisallowed', 'mod_hsuforum', $data);
            }

            if ($forum->maxattachments < $attachmentcount) {
                // Too many attachments.
                mtrace("--> User attached {$attachmentcount} files when only {$forum->maxattachments} where allowed. "
                     . " Rejecting e-mail.");

                $data = new \stdClass();
                $data->forum = $forum;
                $data->attachmentcount = $attachmentcount;
                throw new \core\message\inbound\processing_failed_exception('messageinboundfilecountexceeded', 'mod_hsuforum', $data);
            }

            $filesize = 0;
            $addpost->attachments  = file_get_unused_draft_itemid();
            foreach ($attachments as $attachment) {
                mtrace("--> Processing {$attachment->filename} as an attachment.");
                $this->process_attachment('*', $usercontext, $addpost->attachments, $attachment);
                $filesize += $attachment->filesize;
            }

            if ($forum->maxbytes < $filesize) {
                // Too many attachments.
                mtrace("--> User attached {$filesize} bytes of files when only {$forum->maxbytes} where allowed. "
                     . "Rejecting e-mail.");
                $data = new \stdClass();
                $data->forum = $forum;
                $data->maxbytes = display_size($forum->maxbytes);
                $data->filesize = display_size($filesize);
                throw new \core\message\inbound\processing_failed_exception('messageinboundfilesizeexceeded', 'mod_hsuforum', $data);
            }
        }

        // Process any files in the message itself.
        if ($inlineattachments) {
            foreach ($inlineattachments as $attachment) {
                mtrace("--> Processing {$attachment->filename} as an inline attachment.");
                $this->process_attachment('*', $usercontext, $addpost->itemid, $attachment);

                // Convert the contentid link in the message.
                $draftfile = \moodle_url::make_draftfile_url($addpost->itemid, '/', $attachment->filename);
                $addpost->message = preg_replace('/cid:' . $attachment->contentid . '/', $draftfile, $addpost->message);
            }
        }

        // Insert the message content now.
        $addpost->id = hsuforum_add_new_post($addpost, true);

        // Log the new post creation.
        $params = array(
            'context' => $modcontext,
            'objectid' => $addpost->id,
            'other' => array(
                'discussionid'  => $discussion->id,
                'forumid'       => $forum->id,
                'forumtype'     => $forum->type,
            )
        );
        $event = \mod_hsuforum\event\post_created::create($params);
        $event->add_record_snapshot('hsuforum_posts', $addpost);
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();

        // Update completion state.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && ($forum->completionreplies || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);

            mtrace("--> Updating completion status for user {$USER->id} in hsuforum {$forum->id} for post {$addpost->id}.");
        }

        mtrace("--> Created an hsuforum post {$addpost->id} in {$discussion->id}.");
        return $addpost;
    }

    /**
     * Process attachments included in a message.
     *
     * @param string[] $acceptedtypes String The mimetypes of the acceptable attachment types.
     * @param \context_user $context context_user The context of the user creating this attachment.
     * @param int $itemid int The itemid to store this attachment under.
     * @param \stdClass $attachment stdClass The Attachment data to store.
     * @return \stored_file
     */
    protected function process_attachment($acceptedtypes, \context_user $context, $itemid, \stdClass $attachment) {
        global $USER, $CFG;

        // Create the file record.
        $record = new \stdClass();
        $record->filearea   = 'draft';
        $record->component  = 'user';

        $record->itemid     = $itemid;
        $record->license    = $CFG->sitedefaultlicense;
        $record->author     = fullname($USER);
        $record->contextid  = $context->id;
        $record->userid     = $USER->id;

        // All files sent by e-mail should have a flat structure.
        $record->filepath   = '/';

        $record->filename = $attachment->filename;

        mtrace("--> Attaching {$record->filename} to " .
               "/{$record->contextid}/{$record->component}/{$record->filearea}/" .
               "{$record->itemid}{$record->filepath}{$record->filename}");

        $fs = get_file_storage();
        return $fs->create_file_from_string($record, $attachment->content);
    }


    /**
     * Return the content of any success notification to be sent.
     * Both an HTML and Plain Text variant must be provided.
     *
     * @param \stdClass $messagedata The message data.
     * @param \stdClass $handlerresult The record for the newly created post.
     * @return \stdClass with keys `html` and `plain`.
     */
    public function get_success_message(\stdClass $messagedata, $handlerresult) {
        $a = new \stdClass();
        $a->subject = $handlerresult->subject;
        $discussionurl = new \moodle_url('/mod/hsuforum/discuss.php', array('d' => $handlerresult->discussion));
        $discussionurl->set_anchor('p' . $handlerresult->id);
        $a->discussionurl = $discussionurl->out();

        $message = new \stdClass();
        $message->plain = get_string('postbymailsuccess', 'mod_hsuforum', $a);
        $message->html = get_string('postbymailsuccess_html', 'mod_hsuforum', $a);
        return $message;
    }

}
