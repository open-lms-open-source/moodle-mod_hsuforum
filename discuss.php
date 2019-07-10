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
 * @package   mod_hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

    use mod_hsuforum\local;
    use mod_hsuforum\renderables\advanced_editor;

    require_once('../../config.php');
    require_once(__DIR__.'/lib/discussion/sort.php');
    require_once($CFG->dirroot . '/grade/grading/lib.php');

    // Get the discussion id, and deal with broken requests by browsers...
    // that don't understand the AJAX links. I'm looking at you IE.
    $d = optional_param('d', null, PARAM_INT); // Forum discussion id

    if ($d === null) { // Fallback to id if present.
        $d = optional_param('id', null, PARAM_INT);

        if ($d === null) {
            print_error('missingparameter');
        }
    }

    $root   = optional_param('root', 0, PARAM_INT);          // If set, then display this post and all children.
    $move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forum
    $mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
    $postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
    $pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.
    $warned = optional_param('warned', 0, PARAM_INT);

    $config = get_config('hsuforum');

    $url = new moodle_url('/mod/hsuforum/discuss.php', array('d'=>$d));
    if ($root !== 0) {
        $url->param('root', $root);
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

    if ($forum->type == 'single') {
        // If we are viewing a simple single forum then we need to log forum as viewed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        $params = array(
            'context' => $modcontext,
            'objectid' => $forum->id
        );
        $event = \mod_hsuforum\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('hsuforum', $forum);
        $event->trigger();

        $PAGE->force_settings_menu(true);
    }

    if (!empty($CFG->enablerssfeeds) && !empty($config->enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
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

        // Get target forum cm and check it is visible to current user.
        $modinfo = get_fast_modinfo($course);
        $forums = $modinfo->get_instances_of('hsuforum');
        if (!array_key_exists($forumto->id, $forums)) {
            print_error('cannotmovetonotfound', 'hsuforum', $return);
        }
        $cmto = $forums[$forumto->id];
        if (!$cmto->uservisible) {
            print_error('cannotmovenotvisible', 'hsuforum', $return);
        }

        $destinationctx = context_module::instance($cmto->id);
        require_capability('mod/hsuforum:startdiscussion', $destinationctx);

        if (!$forum->anonymous or $warned) {
            if (!hsuforum_move_attachments($discussion, $forum->id, $forumto->id)) {
                echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
            }
            $DB->set_field('hsuforum_discussions', 'forum', $forumto->id, array('id' => $discussion->id));
            $DB->set_field('hsuforum_read', 'forumid', $forumto->id, array('discussionid' => $discussion->id));

            $params = array(
                'context'  => $destinationctx,
                'objectid' => $discussion->id,
                'other'    => array(
                    'fromforumid' => $forum->id,
                    'toforumid'   => $forumto->id,
                )
            );
            $event  = \mod_hsuforum\event\discussion_moved::create($params);
            $event->add_record_snapshot('hsuforum_discussions', $discussion);
            $event->add_record_snapshot('hsuforum', $forum);
            $event->add_record_snapshot('hsuforum', $forumto);
            $event->trigger();

            // Delete the RSS files for the 2 forums to force regeneration of the feeds
            require_once($CFG->dirroot.'/mod/hsuforum/rsslib.php');
            hsuforum_rss_delete_file($forum);
            hsuforum_rss_delete_file($forumto);

            redirect($return.'&move=-1&sesskey='.sesskey());
        }
    }

    // Pin or unpin discussion if requested.
    if ($pin !== -1 && confirm_sesskey()) {
        require_capability('mod/hsuforum:pindiscussions', $modcontext);

        $params = array('context' => $modcontext, 'objectid' => $discussion->id, 'other' => array('forumid' => $forum->id));

        switch ($pin) {
            case HSUFORUM_DISCUSSION_PINNED:
                // Pin the discussion and trigger discussion pinned event.
                hsuforum_discussion_pin($modcontext, $forum, $discussion);
                break;
            case HSUFORUM_DISCUSSION_UNPINNED:
                // Unpin the discussion and trigger discussion unpinned event.
                hsuforum_discussion_unpin($modcontext, $forum, $discussion);
                break;
            default:
                echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
                break;
        }

        redirect(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id)));
    }

    // Trigger discussion viewed event.
    hsuforum_discussion_view($modcontext, $forum, $discussion);

    unset($SESSION->fromdiscussion);

    if (!$root) {
        $root = $discussion->firstpost;
    }

    if (! $post = hsuforum_get_post_full($root)) {
        print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
    }

    if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm, false)) {
        print_error('noviewdiscussionspermission', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?id=$forum->id");
    }

    if ($mark == 'read') {
        hsuforum_tp_add_read_record($USER->id, $postid);
    }


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

    $renderer = $PAGE->get_renderer('mod_hsuforum');
    $PAGE->requires->js_init_call('M.mod_hsuforum.init', null, false, $renderer->get_js_module());

    $PAGE->set_title("$course->shortname: $discussion->name");
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    if ($forum->type != 'single') {
         echo "<h2><a href='$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id'>&#171; ".format_string($forum->name)."</a></h2>";
    }
     echo $renderer->svg_sprite();


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


    // Print Notice of Warning if Moving this Discussion
    if ($move > 0 and confirm_sesskey()) {
        echo $OUTPUT->confirm(
            get_string('anonymouswarning', 'hsuforum'),
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id, 'move' => $move, 'warned' => 1)),
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id))
        );
    }

    if (hsuforum_discussion_is_locked($forum, $discussion)) {
        echo $OUTPUT->notification(get_string('discussionlocked', 'hsuforum'),
            \core\output\notification::NOTIFY_INFO . ' discussionlocked');
    }

    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter  = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumisthrottled','hsuforum',$a));
    }

    if ($forum->type == 'qanda' && !local::cached_has_capability('mod/hsuforum:viewqandawithoutposting', $modcontext) &&
                !hsuforum_user_has_posted($forum->id,$discussion->id,$USER->id)) {
        echo $OUTPUT->notification(get_string('qandanotify', 'hsuforum'));
    }

    if ($move == -1 and confirm_sesskey()) {
        echo $OUTPUT->notification(get_string('discussionmoved', 'hsuforum', format_string($forum->name,true)), 'success');
    }

    $canrate = \mod_hsuforum\local::cached_has_capability('mod/hsuforum:rate', $modcontext);
    hsuforum_print_discussion($course, $cm, $forum, $discussion, $post, $canreply, $canrate);

    echo '<div class="discussioncontrols clearfix"><div class="controlscontainer m-b-1">';

    if (!empty($CFG->enableportfolios) && local::cached_has_capability('mod/hsuforum:exportdiscussion', $modcontext) && empty($forum->anonymous)) {
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
    }

    if ($course->format !='singleactivity' && $forum->type != 'single'
                && local::cached_has_capability('mod/hsuforum:movediscussions', $modcontext)) {
        echo '<div class="discussioncontrol movediscussion">';
        // Popup menu to move discussions to other forums. The discussion in a
        // single discussion forum can't be moved.
        $modinfo = get_fast_modinfo($course);
        if (isset($modinfo->instances['hsuforum'])) {
            $forummenu = array();
            // Check forum types and eliminate simple discussions.
            $forumcheck = $DB->get_records('hsuforum', array('course' => $course->id),'', 'id, type');
            foreach ($modinfo->instances['hsuforum'] as $forumcm) {
                if (!$forumcm->uservisible || !local::cached_has_capability('mod/hsuforum:startdiscussion',
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
        }
        echo "</div>";
    }
    if (!empty($forummenu)) {
        echo '<div class="movediscussionoption">';
        $select = new url_select($forummenu, '',
            array('/mod/hsuforum/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "hsuforum")),
            'forummenu');
        echo $OUTPUT->render($select);
        echo "</div>";
    }
    if ($forum->type == 'single') {
        $context = \context_module::instance($cm->id);
        $forumobject = $DB->get_record("hsuforum", ["id" => $PAGE->cm->instance]);
        echo hsuforum_search_form($course, $forum->id);
        // Don't allow non logged in users, or guest to try to manage subscriptions.
        if (isloggedin() && !isguestuser()) {
            if (get_config('core', 'theme') == 'snap') {

                echo \html_writer::div(html_writer::link(
                    new \moodle_url(
                        '/mod/hsuforum/index.php',
                        ['id' => $course->id]
                    ),
                    get_string('manageforumsubscriptions', 'mod_hsuforum'),
                    ['class' => 'managesubslink']
                ));

                echo \html_writer::div(html_writer::link(
                    new \moodle_url(
                        '/mod/hsuforum/route.php',
                        ['contextid' => $context->id, 'action' => 'export']
                    ),
                    get_string('export', 'mod_hsuforum'),
                    ['class' => 'exportdiscussionslink']
                ));

                echo \html_writer::div(html_writer::link(
                    new \moodle_url(
                        '/mod/hsuforum/route.php',
                        ['contextid' => $context->id, 'action' => 'viewposters']
                    ),
                    get_string('viewposters', 'mod_hsuforum'),
                    ['class' => 'viewposterslink']
                ));

                if (!hsuforum_is_subscribed($USER->id, $forumobject)) {
                    $subscribe = get_string('subscribe', 'hsuforum');
                } else {
                    $subscribe = get_string('unsubscribe', 'hsuforum');
                }

                echo \html_writer::div(html_writer::link(
                    new \moodle_url(
                        '/mod/hsuforum/subscribe.php',
                        ['id' => $forum->id, 'sesskey' => sesskey()]
                    ),
                    $subscribe,
                    ['class' => 'subscribeforumlink']
                ));
            }

            if (!empty($CFG->mod_hsuforum_grading_interface)) {
                $gradingmanager = get_grading_manager($context, 'mod_hsuforum', 'posts');
                $gradingcontrollerpreview = '';
                if ($gradingmethod = $gradingmanager->get_active_method()) {
                    $controller = $gradingmanager->get_controller($gradingmethod);
                    if ($controller->is_form_defined()) {
                        $gradingcontrollerpreview = $controller->render_preview($PAGE);
                        if ($gradingcontrollerpreview) {

                            echo \html_writer::div(html_writer::link(
                                '#hsuforum_gradingcriteria',
                                get_string('gradingmethodpreview', 'hsuforum'),
                                ['class' => 'hsuforum_gradingcriteria',
                                'data-toggle' => 'collapse',
                                'role' => 'button',
                                'aria-expanded' => 'false',
                                'aria-controls' => 'hsuforum_gradingcriteria']
                            ));

                            echo \html_writer::div(
                                html_writer::div(
                                    html_writer::div(
                                        html_writer::div(
                                            $gradingcontrollerpreview, 'card card-body'
                                        ), 'collapse multi-collapse', ['id' => 'hsuforum_gradingcriteria']
                                    ), 'col'
                                ), 'row'
                            );
                        }
                    }
                }
            }
        }
    }

    $neighbours = hsuforum_get_discussion_neighbours($cm, $discussion, $forum);
    echo $renderer->discussion_navigation($neighbours['prev'], $neighbours['next']);
    echo "</div></div>";
    $editor = new advanced_editor($modcontext);
    echo '<div id="preload-container" style="display: none;"><div id="preload-container-editor">';
    echo $renderer->render_advanced_editor($editor, 'preload-container-editor', 0).'</div></div>';
    echo $OUTPUT->footer();
