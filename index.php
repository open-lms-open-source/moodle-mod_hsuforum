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
 * @package   mod_hsuforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/hsuforum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all forums

$config = get_config('hsuforum');

$url = new moodle_url('/mod/hsuforum/index.php', array('id' => $id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);

unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_hsuforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strforums       = get_string('forums', 'hsuforum');
$strforum        = get_string('forum', 'hsuforum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'hsuforum');
$strsubscribed   = get_string('subscribed', 'hsuforum');
$strunreadposts  = get_string('unreadposts', 'hsuforum');
$strmarkallread  = get_string('markallread', 'hsuforum');
$strsubscribe    = get_string('subscribe', 'hsuforum');
$strunsubscribe  = get_string('unsubscribe', 'hsuforum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = hsuforum_search_form($course);

// Retrieve the list of forum digest options for later.
$digestoptions = hsuforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/hsuforum/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forums.
$generaltable = new html_table();
$generaltable->head  = array ($strforum, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

$generaltable->head[] = $strunreadposts;
$generaltable->align[] = 'center';

$subscribed_forums = hsuforum_get_subscribed_forums($course);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_hsuforum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($config->enablerssfeeds) &&
                 $CFG->enablerssfeeds && $config->enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the forums.  Most forums are course modules but
// some special ones are not.  These get placed in the general forums
// category with the forums in section 0.

$forums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {hsuforum} f
 LEFT JOIN {hsuforum_digests} d ON d.forum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalforums  = array();
$learningforums = array();
$modinfo = get_fast_modinfo($course);
$showsubscriptioncolumns = false;

foreach ($modinfo->get_instances_of('hsuforum') as $forumid => $cm) {
    if (!$cm->uservisible or !isset($forums[$forumid])) {
        continue;
    }

    $forum = $forums[$forumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        // Shouldn't happen.
        continue;
    }

    if (!has_capability('mod/hsuforum:viewdiscussion', $context)) {
        // User can't view this one - skip it.
        continue;
    }

    // Determine whether subscription options should be displayed.
    $forum->cansubscribe = mod_hsuforum\subscriptions::is_subscribable($forum);
    $forum->cansubscribe = $forum->cansubscribe || has_capability('mod/hsuforum:managesubscriptions', $context);
    $forum->issubscribed = mod_hsuforum\subscriptions::is_subscribed($USER->id, $forum, null, $cm);

    $showsubscriptioncolumns = $showsubscriptioncolumns || $forum->issubscribed || $forum->cansubscribe;

    // Fill two type array - order in modinfo is the same as in course.
    if ($forum->type == 'news' or $forum->type == 'social') {
        $generalforums[$forum->id] = $forum;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalforums[$forum->id] = $forum;

    } else {
        $learningforums[$forum->id] = $forum;
    }
}

/// Do course wide subscribe/unsubscribe
if (!is_null($subscribe) and !isguestuser()) {
    foreach ($modinfo->get_instances_of('hsuforum') as $forumid=>$cm) {
        $forum = $forums[$forumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/hsuforum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/hsuforum:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!hsuforum_is_forcesubscribed($forum)) {
            $subscribed = hsuforum_is_subscribed($USER->id, $forum);
            if ((has_capability('moodle/course:manageactivities', $coursecontext, $USER->id) || $forum->forcesubscribe != HSUFORUM_DISALLOWSUBSCRIBE) && $subscribe && !$subscribed && $cansub) {
                hsuforum_subscribe($USER->id, $forumid, $modcontext);
            } else if (!$subscribe && $subscribed) {
                hsuforum_unsubscribe($USER->id, $forumid, $modcontext);
            }
        }
    }
    $returnto = hsuforum_go_back_to(new moodle_url('/mod/hsuforum/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'hsuforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'hsuforum', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

if ($generalforums) {
    // Process general forums.
    foreach ($generalforums as $forum) {
        $cm      = $modinfo->instances['hsuforum'][$forum->id];
        $context = context_module::instance($cm->id);

        $count = hsuforum_count_discussions($forum, $cm, $course);
        if ($unread = hsuforum_count_forum_unread_posts($cm, $course)) {
            $unreadlink = '<span class="unread"><a href="view.php?f='.$forum->id.'#unread">'.$unread.'</a>';
         } else {
             $unreadlink = '<span class="read">0</span>';
         }
        $forum->intro = shorten_text(format_module_intro('hsuforum', $forum, $cm->id), $config->shortpost);
        $forumname = format_string($forum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $forumlink = "<a href=\"view.php?f=$forum->id\" $style>".format_string($forum->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$forum->id\" $style>".$count."</a>";

        $row = array ($forumlink, $forum->intro, $discussionlink, $unreadlink);

        if ($showsubscriptioncolumns) {
            $row[] = hsuforum_get_subscribe_link($forum, $context, array('subscribed' => $stryes,
                'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                'cantsubscribe' => '-'), false, false, true);
            $row[] = hsuforum_index_get_forum_subscription_selector($forum);
        }

        // If this forum has RSS activated, calculate it.
        if ($show_rss) {
            if ($forum->rsstype and $forum->rssarticles) {
                //Calculate the tooltip text
                if ($forum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'hsuforum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'hsuforum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_hsuforum', $forum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Forums
$learningtable = new html_table();
$learningtable->head  = array ($strforum, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

$learningtable->head[] = $strunreadposts;
$learningtable->align[] = 'center';

if ($showsubscriptioncolumns) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_hsuforum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($config->enablerssfeeds) &&
                 $CFG->enablerssfeeds && $config->enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning forums

if ($course->id != SITEID) {    // Only real courses have learning forums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningforums) {
        $currentsection = '';
            foreach ($learningforums as $forum) {
            $cm      = $modinfo->instances['hsuforum'][$forum->id];
            $context = context_module::instance($cm->id);

            $count = hsuforum_count_discussions($forum, $cm, $course);
            if ($unread = hsuforum_count_forum_unread_posts($cm, $course)) {
                $unreadlink = '<span class="unread"><a href="view.php?f='.$forum->id.'#unread">'.$unread.'</a>';
             } else {
                 $unreadlink = '<span class="read">0</span>';
             }

            $forum->intro = shorten_text(format_module_intro('hsuforum', $forum, $cm->id), $config->shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $forumname = format_string($forum->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $forumlink = "<a href=\"view.php?f=$forum->id\" $style>".format_string($forum->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$forum->id\" $style>".$count."</a>";

            $row = array ($printsection, $forumlink, $forum->intro, $discussionlink, $unreadlink);

            if ($can_subscribe) {
                $row[] = hsuforum_get_subscribe_link($forum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true, $subscribed_forums);

                $digestoptions_selector->url->param('id', $forum->id);
                if ($forum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $forum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this forum has RSS activated, calculate it
            if ($show_rss) {
                if ($forum->rsstype and $forum->rssarticles) {
                    //Calculate the tolltip text
                    if ($forum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'hsuforum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'hsuforum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_hsuforum', $forum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strforums);
$PAGE->set_title("$course->shortname: $strforums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

if (!isguestuser() && isloggedin()) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/hsuforum/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'hsuforum')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/hsuforum/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'hsuforum')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalforums) {
    echo $OUTPUT->heading(get_string('generalforums', 'hsuforum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningforums) {
    echo $OUTPUT->heading(get_string('learningforums', 'hsuforum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

/**
 * Get the content of the forum subscription options for this forum.
 *
 * @param   stdClass    $forum      The forum to return options for
 * @return  string
 */
function hsuforum_index_get_forum_subscription_selector($forum) {
    global $OUTPUT, $PAGE;

    if ($forum->cansubscribe || $forum->issubscribed) {
        if ($forum->maildigest === null) {
            $forum->maildigest = -1;
        }

        $renderer = $PAGE->get_renderer('mod_hsuforum');
        return $OUTPUT->render($renderer->render_digest_options($forum, $forum->maildigest));
    } else {
        // This user can subscribe to some forums. Add the empty fields.
        return '';
    }
};
