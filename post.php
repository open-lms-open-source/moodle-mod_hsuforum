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
 * Edit and save a new post to a discussion
 *
 * @package   mod_hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$forum   = optional_param('forum', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);
$messagecontent = optional_param('msgcontent', '', PARAM_TEXT);
$subjectcontent = optional_param('subcontent', '', PARAM_TEXT);

$PAGE->set_url('/mod/hsuforum/post.php', array(
    'reply' => $reply,
    'forum' => $forum,
    'edit'  => $edit,
    'delete' => $delete,
    'prune' => $prune,
    'name'  => $name,
    'confirm' => $confirm,
    'groupid' => $groupid,
));
// These page_params will be passed as hidden variables later in the form.
$pageparams = array('reply' => $reply, 'forum' => $forum, 'edit' => $edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052.
        require_login();
    }

    if (!empty($forum)) {      // User is starting a new discussion in a forum.
        if (! $forum = $DB->get_record('hsuforum', array('id' => $forum))) {
            print_error('invalidforumid', 'hsuforum');
        }
    } else if (!empty($reply)) {      // User is writing a new reply.
        if (! $parent = hsuforum_get_post_full($reply)) {
            print_error('invalidparentpostid', 'hsuforum');
        }
        if (! $discussion = $DB->get_record('hsuforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'hsuforum');
        }
        if (! $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum))) {
            print_error('invalidforumid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id)) { // For the logs.
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forum);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'hsuforum').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in.

if (!empty($forum)) {      // User is starting a new discussion in a forum.
    if (! $forum = $DB->get_record("hsuforum", array("id" => $forum))) {
        print_error('invalidforumid', 'hsuforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $forum->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! hsuforum_user_can_post_discussion($forum, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/hsuforum/view.php?f=' . $forum->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostforum', 'hsuforum');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->forum         = $forum->id;
    $post->discussion    = 0;           // Ie discussion # not defined yet.
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->reveal        = 0;
    $post->privatereply  = 0;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    hsuforum_set_return();

} else if (!empty($reply)) {      // User is writing a new reply.

    if (! $parent = hsuforum_get_post_full($reply)) {
        print_error('invalidparentpostid', 'hsuforum');
    }
    if (! $discussion = $DB->get_record("hsuforum_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'hsuforum');
    }
    if (! $forum = $DB->get_record("hsuforum", array("id" => $discussion->forum))) {
        print_error('invalidforumid', 'hsuforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926.
    $PAGE->set_cm($cm, $course, $forum);
    $renderer = $PAGE->get_renderer('mod_hsuforum');
    $PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/hsuforum/view.php?f=' . $forum->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostforum', 'hsuforum');
    }

    // Make sure user can post here.
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode = $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostforum', 'hsuforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostforum', 'hsuforum');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->forum       = $forum->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->reveal      = 0;
    $post->privatereply= 0;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'hsuforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre) && empty($subjectcontent)) {
        $post->subject = $strre.' '.$post->subject;
    }

    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post.

    if (! $post = hsuforum_get_post_full($edit)) {
        print_error('invalidpostid', 'hsuforum');
    }
    if ($post->parent) {
        if (! $parent = hsuforum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'hsuforum');
        }
    }

    if (! $discussion = $DB->get_record("hsuforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'hsuforum');
    }
    if (! $forum = $DB->get_record("hsuforum", array("id" => $discussion->forum))) {
        print_error('invalidforumid', 'hsuforum');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forum);
    $renderer = $PAGE->get_renderer('mod_hsuforum');
    $PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());

    if (!($forum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
            !has_capability('mod/hsuforum:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'hsuforum', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
        !has_capability('mod/hsuforum:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'hsuforum');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->forum  = $forum->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    unset($SESSION->fromdiscussion);


} else if (!empty($delete)) {  // User is deleting a post.

    if (! $post = hsuforum_get_post_full($delete)) {
        print_error('invalidpostid', 'hsuforum');
    }
    if (! $discussion = $DB->get_record("hsuforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'hsuforum');
    }
    if (! $forum = $DB->get_record("hsuforum", array("id" => $discussion->forum))) {
        print_error('invalidforumid', 'hsuforum');
    }
    if (!$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $forum->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/hsuforum:deleteownpost', $modcontext))
        || has_capability('mod/hsuforum:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'hsuforum');
    }


    $replycount = hsuforum_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete.
        redirect(
            hsuforum_verify_and_delete_post($course, $cm, $forum, $modcontext, $discussion, $post)
        );
    } else { // User just asked to delete something.

        hsuforum_set_return();
        $PAGE->navbar->add(get_string('delete', 'hsuforum'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        $renderer = $PAGE->get_renderer('mod_hsuforum');
        $PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());

        if ($replycount) {
            if (!has_capability('mod/hsuforum:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "hsuforum",
                    hsuforum_go_back_to(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $post->discussion), 'p'.$post->id)));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($forum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "hsuforum", $replycount + 1),
                "post.php?delete=$delete&confirm=$delete",
                $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'#p'.$post->id);

            echo $renderer->post($cm, $discussion, $post, false, null, false);
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($forum->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "hsuforum", $replycount),
                "post.php?delete=$delete&confirm=$delete",
                $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$post->discussion.'#p'.$post->id);

            echo $renderer->post($cm, $discussion, $post, false, null, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning.

    if (!$post = hsuforum_get_post_full($prune)) {
        print_error('invalidpostid', 'hsuforum');
    }
    if (!$discussion = $DB->get_record("hsuforum_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'hsuforum');
    }
    if (!$forum = $DB->get_record("hsuforum", array("id" => $discussion->forum))) {
        print_error('invalidforumid', 'hsuforum');
    }
    if ($forum->type == 'single') {
        print_error('cannotsplit', 'hsuforum');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'hsuforum');
    }
    if (!$cm = get_coursemodule_from_instance("hsuforum", $forum->id, $forum->course)) { // For the logs.
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/hsuforum:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'hsuforum');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_hsuforum_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(hsuforum_go_back_to(new moodle_url("/mod/hsuforum/discuss.php", array('d' => $post->discussion))));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.

        // Make sure post name does not go beyond 255 chars.
        $name = \mod_hsuforum\local::shorten_post_name($name);

        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->forum        = $discussion->forum;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('hsuforum_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;
        $newpost->privatereply = 0;

        $DB->update_record("hsuforum_posts", $newpost);

        hsuforum_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        hsuforum_discussion_update_last_post($discussion->id);
        hsuforum_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_hsuforum\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_hsuforum\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );
        $event = \mod_hsuforum\event\post_updated::create($params);
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();

        $message = get_string('discussionsplit', 'hsuforum');
        redirect(
            hsuforum_go_back_to(new moodle_url("/mod/hsuforum/discuss.php", array('d' => $newid))),
            $message,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $forum->course));

        $renderer = $PAGE->get_renderer('mod_hsuforum');
        $PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());
        $subjectstr = format_string($post->subject, true);
        $PAGE->navbar->add($subjectstr, new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id)));
        $PAGE->navbar->add(get_string("prune", "hsuforum"));
        $PAGE->set_title("$discussion->name: $post->subject");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($forum->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'hsuforum'), 3);
        echo $renderer->svg_sprite();
        if (!empty($post->privatereply)) {
            echo $OUTPUT->notification(get_string('splitprivatewarning', 'hsuforum'));
        }

        $prunemform->display();

        // We don't have the valid unread status. Set to read so we don't see
        // the unread tag.
        $post->postread = true;
        echo $renderer->post($cm, $discussion, $post);
    }
    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($forum->course);
}


// from now on user must be logged on properly.

if (!$cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id)) { // For the logs.
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // Just in case.
    print_error('noguest');
}

if (!isset($forum->maxattachments)) {  // TODO - delete this once we add a field to the forum table.
    $forum->maxattachments = 3;
}

$thresholdwarning = hsuforum_check_throttling($forum, $cm);
$mformpost = new mod_hsuforum_post_form('post.php', array('course' => $course,
    'cm' => $cm,
    'coursecontext' => $coursecontext,
    'modcontext' => $modcontext,
    'forum' => $forum,
    'post' => $post,
    'subscribe' => mod_hsuforum\subscriptions::is_subscribed($USER->id, $forum,
        null, $cm),
    'thresholdwarning' => $thresholdwarning,
    'edit' => $edit), 'post', '', array('id' => 'mformhsuforum'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
$postid = empty($post->id) ? null : $post->id;
$attachoptions = mod_hsuforum_post_form::attachment_options($forum);
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_hsuforum', 'attachment', $postid, $attachoptions);

// Load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end.
    $data = new stdClass();
    $data->date = userdate($post->created);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
            fullname($USER).'</a>';
        $post->message .= '<p class="edited">('.get_string('editedby', 'hsuforum', $data).')</p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'hsuforum', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $formheading = get_string("yourreply", "hsuforum");
} else {
    if ($forum->type == 'qanda') {
        $formheading = get_string('yournewquestion', 'hsuforum');
    } else {
        $formheading = get_string('yournewtopic', 'hsuforum');
    }
}

if (hsuforum_is_subscribed($USER->id, $forum->id) || $USER->autosubscribe) {
    $subscribe = true;
} else if (hsuforum_user_has_posted($forum->id, 0, $USER->id)) {
    $subscribe = false;
} else {
    $subscribe = false;
}

$postid = empty($post->id) ? null : $post->id;
$draftideditor = file_get_submitted_draft_itemid('message');
$editoropts = mod_hsuforum_post_form::editor_options($modcontext, $postid);
$currenttext = file_prepare_draft_area($draftideditor, $modcontext->id, 'mod_hsuforum', 'post', $postid, $editoropts, $post->message);
if (!empty($messagecontent) && $edit === 0) {
    $currenttext = $messagecontent;
}
if (!empty($subjectcontent) && $edit === 0) {
    $post->subject = $subjectcontent;
}
$mformpost->set_data(
    array(
        'attachments'=>$draftitemid,
        'subject'=>$post->subject,
        'message'=>array(
            'text'=>$currenttext,
            'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
            'itemid'=>$draftideditor
        ),
        'subscribe'=>$subscribe?1:0,
        'mailnow'=>!empty($post->mailnow),
        'userid'=>$post->userid,
        'parent'=>$post->parent,
        'reveal'=>$post->reveal,
        'privatereply'=>$post->privatereply,
        'discussion'=>$post->discussion,
        'course'=>$course->id
    ) +

    $pageparams +

    (isset($post->format) ? array('format'=>$post->format) : array()) +

    (isset($discussion->timestart) ? array('timestart'=>$discussion->timestart) : array()) +

    (isset($discussion->timeend) ? array('timeend'=>$discussion->timeend) : array()) +

    (isset($discussion->pinned) ? array('pinned' => $discussion->pinned) : array()) +

    (isset($post->groupid) ? array('groupid'=>$post->groupid) : array()) +

    (isset($discussion->id) ? array('discussion'=>$discussion->id) : array())
);

if ($fromform = $mformpost->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    // Clean message text.
    $fromform = trusttext_pre_edit($fromform, 'message', $modcontext);

    if ($fromform->edit) {           // Updating a post.
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        // Fix for bug #4314.
        if (!$realpost = $DB->get_record('hsuforum_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // If user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066.
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/hsuforum:replypost', $modcontext)
                    || has_capability('mod/hsuforum:startdiscussion', $modcontext))) ||
            has_capability('mod/hsuforum:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'hsuforum');
        }

        if ($realpost->userid != $USER->id || !has_capability('mod/hsuforum:revealpost', $modcontext)) {
            unset($fromform->reveal);
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/hsuforum:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!hsuforum_user_can_post_discussion($forum, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'hsuforum');
            }

            $DB->set_field('hsuforum_discussions', 'groupid', $fromform->groupinfo, array('firstpost' => $fromform->id));
        }
        // When editing first post/discussion.
        if (!$fromform->parent) {
            if (has_capability('mod/hsuforum:pindiscussions', $modcontext)) {
                // Can change pinned if we have capability.
                $fromform->pinned = !empty($fromform->pinned) ? HSUFORUM_DISCUSSION_PINNED : HSUFORUM_DISCUSSION_UNPINNED;
            } else {
                // We don't have the capability to change so keep to previous value.
                unset($fromform->pinned);
            }
        }
        $updatepost = $fromform; // Realpost.
        $updatepost->forum = $forum->id;
        if (!hsuforum_update_post($updatepost, $mformpost)) {
            print_error("couldnotupdate", "hsuforum", $errordestination);
        }

        // MDL-11818.
        if (($forum->type == 'single') && ($updatepost->parent == '0')) {
            // Updating first post of single discussion type -> updating forum intro.
            $forum->timemodified = time();
            $DB->update_record("hsuforum", $forum);
        }

        if ($realpost->userid == $USER->id) {
            $message .= get_string("postupdated", "hsuforum");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $freshpost = $DB->get_record('hsuforum_posts', array('id' => $fromform->id));

            if ($realuser && $freshpost) {
                $postuser = hsuforum_get_postuser($realuser, $freshpost, $forum, $modcontext);
                $message .= '<br />'.get_string('editedpostupdated', 'hsuforum', $postuser->fullname);
            } else {
                $message .= get_string('postupdated', 'hsuforum');
            }
        }

        $subscribemessage = hsuforum_post_subscription($fromform, $forum);
        if ($forum->type == 'single') {
            // Single discussion forums are an exception. We show
            // the forum itself since it only has one discussion
            // thread.
            $discussionurl = new moodle_url("/mod/hsuforum/view.php", array('f' => $forum->id));
        } else {
            $discussionurl = new moodle_url("/mod/hsuforum/discuss.php", array('d' => $discussion->id), 'p' . $fromform->id);
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_hsuforum\event\post_updated::create($params);
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();

        redirect(
            hsuforum_go_back_to($discussionurl),
            $message . $subscribemessage,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        hsuforum_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->forum = $forum->id;
        if ($fromform->id = hsuforum_add_new_post($addpost, $mformpost, $message)) {
            $fromform->deleted = 0;
            $subscribemessage = hsuforum_post_subscription($fromform, $forum);

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "hsuforum");
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "hsuforum") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "hsuforum", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($forum->type == 'single') {
                // Single discussion forums are an exception. We show
                // the forum itself since it only has one discussion
                // thread.
                $discussionurl = new moodle_url("/mod/hsuforum/view.php", array('f' => $forum->id), 'p'.$fromform->id);
            } else {
                $discussionurl = new moodle_url("/mod/hsuforum/discuss.php", array('d' => $discussion->id), 'p'.$fromform->id);
            }
            $post   = $DB->get_record('hsuforum_posts', array('id' => $fromform->id), '*', MUST_EXIST);
            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                )
            );
            $event = \mod_hsuforum\event\post_created::create($params);
            $event->add_record_snapshot('hsuforum_posts', $post);
            $event->add_record_snapshot('hsuforum_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            redirect(
                hsuforum_go_back_to($discussionurl),
                $message . $subscribemessage,
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );

        } else {
            print_error("couldnotadd", "hsuforum", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('/mod/hsuforum/view.php', array('f' => $fromform->forum));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        if (!empty($fromform->reveal) && has_capability('mod/hsuforum:revealpost', $modcontext)) {
            $discussion->reveal = 1;
        } else {
            $discussion->reveal = 0;
        }

        $newstopic = false;
        if ($forum->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        if (has_capability('mod/hsuforum:pindiscussions', $modcontext) && !empty($fromform->pinned)) {
            $discussion->pinned = HSUFORUM_DISCUSSION_PINNED;
        } else {
            $discussion->pinned = HSUFORUM_DISCUSSION_UNPINNED;
        }

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/hsuforum:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (hsuforum_user_can_post_discussion($forum, $groupid, -1, $cm, $modcontext)) {
                    $groupstopostto[] = $groupid;
                }
            }
        } else if (isset($fromform->groupinfo)) {
            // Use the value provided in the dropdown group selection.
            $groupstopostto[] = $fromform->groupinfo;
            $redirectto->param('group', $fromform->groupinfo);
        } else if (isset($fromform->groupid) && !empty($fromform->groupid)) {
            // Use the value provided in the hidden form element instead.
            $groupstopostto[] = $fromform->groupid;
            $redirectto->param('group', $fromform->groupid);
        } else {
            // Use the value for all participants instead.
            $groupstopostto[] = -1;
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        hsuforum_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!hsuforum_user_can_post_discussion($forum, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'hsuforum');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = hsuforum_add_discussion($discussion, $mformpost, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'forumid' => $forum->id,
                    )
                );
                $event = \mod_hsuforum\event\discussion_created::create($params);
                $event->add_record_snapshot('hsuforum_discussions', $discussion);
                $event->trigger();

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "hsuforum");
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "hsuforum") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "hsuforum", format_time($CFG->maxeditingtime)) . '</p>';
                }

                $subscribemessage = hsuforum_post_subscription($fromform, $forum, $discussion);
            } else {
                print_error("couldnotadd", "hsuforum", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completiondiscussions || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        redirect(
            hsuforum_go_back_to($redirectto->out()),
            $message . $subscribemessage,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// Vars $course, $forum are defined. $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("hsuforum_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'hsuforum', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = get_string("addanewtopic", "hsuforum");
    $toppost->subject = ($forum->type == "news") ? get_string("addanewtopic", "hsuforum") :
        get_string("addanewdiscussion", "hsuforum");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $forum->name;
}
if ($forum->type == 'single') {
    // There is only one discussion thread for this forum type. We should
    // not show the discussion name (same as forum name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = $discussion->name.':';
}

$forcefocus = empty($reply) ? null : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'hsuforum'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'hsuforum'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname $toppost->subject");
$PAGE->set_heading($course->fullname);
$renderer = $PAGE->get_renderer('mod_hsuforum');
$PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($forum->name), 2);

// Checkup.
if (!empty($parent) && !hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'hsuforum');
}
if (!empty($parent) && !empty($parent->privatereply)) {
    print_error('cannotreply', 'hsuforum');
}
if (empty($parent) && empty($edit) && !hsuforum_user_can_post_discussion($forum, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'hsuforum');
}

if ($forum->type == 'qanda'
    && !has_capability('mod/hsuforum:viewqandawithoutposting', $modcontext)
    && !empty($discussion->id)
    && !hsuforum_user_has_posted($forum->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'hsuforum'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    hsuforum_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('hsuforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'hsuforum');
    }

    echo $renderer->svg_sprite();
    // We don't have the valid unread status. Set to read so we don't see
    // the unread tag.
    $parent->postread = true;
    echo $renderer->post($cm, $discussion, $parent);
    if (empty($post->edit)) {
        if ($forum->type != 'qanda' || hsuforum_user_can_see_discussion($forum, $discussion, $modcontext)) {
            $posts = hsuforum_get_all_discussion_posts($discussion->id);
        }
    }
} else {
    if (!empty($forum->intro)) {
        echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
    }
}

// Call print disclosure for enabled plagiarism plugins.
if (!empty($CFG->enableplagiarism)) {
    require_once($CFG->libdir.'/plagiarismlib.php');
    echo plagiarism_print_disclosure($cm->id);
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 4);
}

$data = new StdClass();
if (isset($postid)) {
    $data->tags = core_tag_tag::get_item_tags_array('mod_hsuforum', 'hsuforum_posts', $postid);
    $mformpost->set_data($data);
}

$mformpost->display();
echo $OUTPUT->footer();
