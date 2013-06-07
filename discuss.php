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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package mod-hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

    require_once('../../config.php');
    require_once(__DIR__.'/lib/discussion/sort.php');
    require_once(__DIR__.'/lib/discussion/nav.php');

    $d      = required_param('d', PARAM_INT);                // Discussion ID
    $parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
    $mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
    $move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forum
    $mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
    $postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
    $warned = optional_param('warned', 0, PARAM_INT);

    $url = new moodle_url('/mod/hsuforum/discuss.php', array('d'=>$d));
    if ($parent !== 0) {
        $url->param('parent', $parent);
    }
    $PAGE->set_url($url);

    $discussion = $DB->get_record('hsuforum_discussions', array('id' => $d), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
    $forum = $DB->get_record('hsuforum', array('id' => $discussion->forum), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('hsuforum', $forum->id, $course->id, false, MUST_EXIST);

    require_course_login($course, true, $cm);

    // move this down fix for MDL-6926
    require_once($CFG->dirroot.'/mod/hsuforum/lib.php');

    $modcontext = context_module::instance($cm->id);
    require_capability('mod/hsuforum:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'hsuforum');

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->hsuforum_enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forum->name);
        rss_add_http_header($modcontext, 'mod_hsuforum', $forum, $rsstitle);
    }

/// move discussion if requested
    if ($move > 0 and confirm_sesskey()) {
        $return = $CFG->wwwroot.'/mod/hsuforum/discuss.php?d='.$discussion->id;

        require_capability('mod/hsuforum:movediscussions', $modcontext);

        if ($forum->type == 'single') {
            print_error('cannotmovefromsingleforum', 'hsuforum', $return);
        }

        if (!$forumto = $DB->get_record('hsuforum', array('id' => $move))) {
            print_error('cannotmovetonotexist', 'hsuforum', $return);
        }

        if ($forumto->type == 'single') {
            print_error('cannotmovetosingleforum', 'hsuforum', $return);
        }

        if (!$cmto = get_coursemodule_from_instance('hsuforum', $forumto->id, $course->id)) {
            print_error('cannotmovetonotfound', 'hsuforum', $return);
        }

        if (!coursemodule_visible_for_user($cmto)) {
            print_error('cannotmovenotvisible', 'hsuforum', $return);
        }

        require_capability('mod/hsuforum:startdiscussion', context_module::instance($cmto->id));

        if (!$forum->anonymous or $warned) {
            if (!hsuforum_move_attachments($discussion, $forum->id, $forumto->id)) {
                echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
            }
            $DB->set_field('hsuforum_discussions', 'forum', $forumto->id, array('id' => $discussion->id));
            $DB->set_field('hsuforum_read', 'forumid', $forumto->id, array('discussionid' => $discussion->id));
            add_to_log($course->id, 'hsuforum', 'move discussion', "discuss.php?d=$discussion->id", $discussion->id, $cmto->id);

            require_once($CFG->libdir.'/rsslib.php');
            require_once($CFG->dirroot.'/mod/hsuforum/rsslib.php');

            // Delete the RSS files for the 2 forums to force regeneration of the feeds
            hsuforum_rss_delete_file($forum);
            hsuforum_rss_delete_file($forumto);

            redirect($return.'&moved=-1&sesskey='.sesskey());
        }
    }

    add_to_log($course->id, 'hsuforum', 'view discussion', "discuss.php?d=$discussion->id", $discussion->id, $cm->id);

    unset($SESSION->fromdiscussion);

    if ($mode) {
        set_user_preference('hsuforum_displaymode', $mode);
    }

    $displaymode = hsuforum_get_layout_mode($forum);

    if ($parent) {
        // If flat AND parent, then force nested display this time
        if ($displaymode == HSUFORUM_MODE_FLATOLDEST or $displaymode == HSUFORUM_MODE_FLATNEWEST or $displaymode == HSUFORUM_MODE_FLATFIRSTNAME or $displaymode == HSUFORUM_MODE_FLATLASTNAME) {
            $displaymode = HSUFORUM_MODE_NESTED;
        }
    } else {
        $parent = $discussion->firstpost;
    }

    if (! $post = hsuforum_get_post_full($parent)) {
        print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
    }

    if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
        print_error('noviewdiscussionspermission', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?id=$forum->id");
    }

    if ($mark == 'read' or $mark == 'unread') {
        if ($CFG->hsuforum_usermarksread && hsuforum_tp_can_track_forums($forum) && hsuforum_tp_is_tracked($forum)) {
            if ($mark == 'read') {
                hsuforum_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                hsuforum_tp_delete_read_records($USER->id, $postid);
            }
        }
    }

    $searchform = hsuforum_search_form($course);

    $forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($forumnode)) {
        $forumnode = $PAGE->navbar;
    } else {
        $forumnode->make_active();
    }
    $node = $forumnode->add(format_string($discussion->name), new moodle_url('/mod/hsuforum/discuss.php', array('d'=>$discussion->id)));
    $node->display = false;
    if ($node && $post->id != $discussion->firstpost) {
        $node->add(format_string($post->subject), $PAGE->url);
    }

    $dsort = hsuforum_lib_discussion_sort::get_from_session($forum, $modcontext);
    $dnav  = hsuforum_lib_discussion_nav::get_from_session($cm, $dsort);

    $prevdiscussion = $dnav->get_prev_discussionid($discussion->id);
    $nextdiscussion = $dnav->get_next_discussionid($discussion->id);

    if ($prevdiscussion) {
        $prevdiscussion = $DB->get_record('hsuforum_discussions', array('id' => $prevdiscussion));
    }
    if ($nextdiscussion) {
        $nextdiscussion = $DB->get_record('hsuforum_discussions', array('id' => $nextdiscussion));
    }
    hsuforum_lib_discussion_nav::set_to_session($dnav);

    /** @var $renderer mod_hsuforum_renderer */
    $renderer = $PAGE->get_renderer('mod_hsuforum');

    $PAGE->set_title("$course->shortname: ".format_string($discussion->name));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_button($searchform);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this forum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

    $canreply = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);
    if (!$canreply and $forum->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canreply = true;
        }
        if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
            // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this link too, they are asked to enrol instead
            $canreply = enrol_selfenrol_available($course->id);
        }
    }

/// Print the controls across the top
    echo '<div class="discussioncontrols clearfix">';

    if (!empty($CFG->enableportfolios) && has_capability('mod/hsuforum:exportdiscussion', $modcontext)) {
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('hsuforum_portfolio_caller', array('discussionid' => $discussion->id), 'mod_hsuforum');
        $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_hsuforum'));
        $buttonextraclass = '';
        if (empty($button)) {
            // no portfolio plugin available.
            $button = '&nbsp;';
            $buttonextraclass = ' noavailable';
        }
        echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
    } else {
        echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
    }

    // groups selector not needed here
    echo '<div class="discussioncontrol displaymode">';
    $select = new single_select(new moodle_url("/mod/hsuforum/discuss.php", array('d'=> $discussion->id)), 'mode', hsuforum_get_layout_modes($forum), $displaymode, null, "mode");
    echo $OUTPUT->render($select);
    echo "</div>";

    if ($forum->type != 'single'
                && has_capability('mod/hsuforum:movediscussions', $modcontext)) {

        echo '<div class="discussioncontrol movediscussion">';
        // Popup menu to move discussions to other forums. The discussion in a
        // single discussion forum can't be moved.
        $modinfo = get_fast_modinfo($course);
        if (isset($modinfo->instances['hsuforum'])) {
            $forummenu = array();
            // Check forum types and eliminate simple discussions.
            $forumcheck = $DB->get_records('hsuforum', array('course' => $course->id),'', 'id, type');
            foreach ($modinfo->instances['hsuforum'] as $forumcm) {
                if (!$forumcm->uservisible || !has_capability('mod/hsuforum:startdiscussion',
                    context_module::instance($forumcm->id))) {
                    continue;
                }
                $section = $forumcm->sectionnum;
                $sectionname = get_section_name($course, $section);
                if (empty($forummenu[$section])) {
                    $forummenu[$section] = array($sectionname => array());
                }
                $forumidcompare = $forumcm->instance != $forum->id;
                $forumtypecheck = $forumcheck[$forumcm->instance]->type !== 'single';
                if ($forumidcompare and $forumtypecheck) {
                    $url = "/mod/hsuforum/discuss.php?d=$discussion->id&move=$forumcm->instance&sesskey=".sesskey();
                    $forummenu[$section][$sectionname][$url] = format_string($forumcm->name);
                }
            }
            if (!empty($forummenu)) {
                echo '<div class="movediscussionoption">';
                $select = new url_select($forummenu, '',
                        array(''=>get_string("movethisdiscussionto", "hsuforum")),
                        'forummenu');
                echo $OUTPUT->render($select);
                echo "</div>";
            }
        }
        echo "</div>";
    }
    echo '<div class="clearfloat">&nbsp;</div>';
    echo "</div>";

    // Print Notice of Warning if Moving this Discussion
    if ($move > 0 and confirm_sesskey()) {
        echo $OUTPUT->confirm(
            get_string('anonymouswarning', 'hsuforum'),
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id, 'move' => $move, 'warned' => 1)),
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id))
        );
    }

    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter  = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumisthrottled','hsuforum',$a));
    }

    if ($forum->type == 'qanda' && !has_capability('mod/hsuforum:viewqandawithoutposting', $modcontext) &&
                !hsuforum_user_has_posted($forum->id,$discussion->id,$USER->id)) {
        echo $OUTPUT->notification(get_string('qandanotify','hsuforum'));
    }

    if ($move == -1 and confirm_sesskey()) {
        echo $OUTPUT->notification(get_string('discussionmoved', 'hsuforum', format_string($forum->name,true)));
    }

    $canrate = has_capability('mod/hsuforum:rate', $modcontext);
    echo $renderer->discussion_navigation($prevdiscussion, $nextdiscussion, array('class' => 'hsuforumtopicnav_top'));
    hsuforum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
    echo $renderer->discussion_navigation($prevdiscussion, $nextdiscussion, array('class' => 'hsuforumtopicnav_bottom'));

    echo $OUTPUT->footer();



