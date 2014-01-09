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
 * This file contains a custom renderer class used by the forum module.
 *
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 */

use mod_hsuforum\render_interface;

require_once(__DIR__.'/classes/render_interface.php');

/**
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the forum module.
 *
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @author Mark Nielsen
 **/
class mod_hsuforum_renderer extends plugin_renderer_base {
    /**
     * This method is used to generate HTML for a subscriber selection form that
     * uses two user_selector controls
     *
     * @param user_selector_base $existinguc
     * @param user_selector_base $potentialuc
     * @return string
     */
    public function subscriber_selection_form(user_selector_base $existinguc, user_selector_base $potentialuc) {
        $output = '';
        $formattributes = array();
        $formattributes['id'] = 'subscriberform';
        $formattributes['action'] = '';
        $formattributes['method'] = 'post';
        $output .= html_writer::start_tag('form', $formattributes);
        $output .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));

        $existingcell = new html_table_cell();
        $existingcell->text = $existinguc->display(true);
        $existingcell->attributes['class'] = 'existing';
        $actioncell = new html_table_cell();
        $actioncell->text  = html_writer::start_tag('div', array());
        $actioncell->text .= html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'subscribe', 'value'=>$this->page->theme->larrow.' '.get_string('add'), 'class'=>'actionbutton'));
        $actioncell->text .= html_writer::empty_tag('br', array());
        $actioncell->text .= html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'unsubscribe', 'value'=>$this->page->theme->rarrow.' '.get_string('remove'), 'class'=>'actionbutton'));
        $actioncell->text .= html_writer::end_tag('div', array());
        $actioncell->attributes['class'] = 'actions';
        $potentialcell = new html_table_cell();
        $potentialcell->text = $potentialuc->display(true);
        $potentialcell->attributes['class'] = 'potential';

        $table = new html_table();
        $table->attributes['class'] = 'subscribertable boxaligncenter';
        $table->data = array(new html_table_row(array($existingcell, $actioncell, $potentialcell)));
        $output .= html_writer::table($table);

        $output .= html_writer::end_tag('form');
        return $output;
    }

    /**
     * This function generates HTML to display a subscriber overview, primarily used on
     * the subscribers page if editing was turned off
     *
     * @param array $users
     * @param string $entityname
     * @param object $course
     * @return string
     */
    public function subscriber_overview($users, $entityname, $course) {
        $output = '';
        if (!$users || !is_array($users) || count($users)===0) {
            $output .= $this->output->heading(get_string("nosubscribers", "hsuforum"));
        } else {
            $output .= $this->output->heading(get_string("subscribersto","hsuforum", "'".format_string($entityname)."'"));
            $table = new html_table();
            $table->cellpadding = 5;
            $table->cellspacing = 5;
            $table->tablealign = 'center';
            $table->data = array();
            foreach ($users as $user) {
                $table->data[] = array($this->output->user_picture($user, array('courseid'=>$course->id)), fullname($user), $user->email);
            }
            $output .= html_writer::table($table);
        }
        return $output;
    }

    /**
     * This is used to display a control containing all of the subscribed users so that
     * it can be searched
     *
     * @param user_selector_base $existingusers
     * @return string
     */
    public function subscribed_users(user_selector_base $existingusers) {
        $output  = $this->output->box_start('subscriberdiv boxaligncenter');
        $output .= html_writer::tag('p', get_string('forcessubscribe', 'hsuforum'));
        $output .= $existingusers->display(true);
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * The javascript module used by the presentation layer
     *
     * @return array
     * @author Mark Nielsen
     */
    public function get_js_module() {
        return array(
            'name'      => 'mod_hsuforum',
            'fullpath'  => '/mod/hsuforum/module.js',
            'requires'  => array(
                'base',
                'node',
                'event',
                'anim',
                'panel',
                'dd-plugin',
                'io-base',
                'json',
                'yui2-treeview',
                'core_rating',
            ),
            'strings' => array(
                array('jsondecodeerror', 'hsuforum'),
                array('ajaxrequesterror', 'hsuforum'),
                array('clicktoexpand', 'hsuforum'),
                array('clicktocollapse', 'hsuforum'),
                array('manualwarning', 'hsuforum'),
                array('subscribedtodiscussionx', 'hsuforum'),
                array('notsubscribedtodiscussionx', 'hsuforum'),
            )
        );
    }

    /**
     * @param stdClass $post The post to add flags to
     * @param context_module $context
     * @throws coding_exception
     * @return string
     * @author Mark Nielsen
     */
    public function post_flags($post, context_module $context) {
        $flaghtml = $this->post_get_flags($post, $context);
        return html_writer::tag('div', implode('', $flaghtml), array('class' => 'hsuforum_flags'));
    }

    /**
     * @param stdClass $post The post to add flags to
     * @param context_module $context
     * @throws coding_exception
     * @return array
     * @author Mark Nielsen
     */
    public function post_get_flags($post, context_module $context) {
        global $OUTPUT, $PAGE;

        static $jsinit = false;

        if (!has_capability('mod/hsuforum:viewflags', $context)) {
            return array();
        }
        if (!property_exists($post, 'flags')) {
            throw new coding_exception('The post\'s flags property must be set');
        }
        require_once(__DIR__.'/lib/flag.php');

        $flaglib   = new hsuforum_lib_flag();
        $canedit   = has_capability('mod/hsuforum:editanypost', $context);
        $returnurl = $PAGE->url;

        if ($canedit and !$jsinit) {
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_flags', null, false, $this->get_js_module());
            $jsinit = true;
        }

        $flaghtml = array();
        if (!empty($post->name)) {
            $postname = format_string($post->name);
        } else {
            $postname = $post->subject;
            if (empty($post->subjectnoformat)) {
                $postname = format_string($postname);
            } else {
                $postname = strip_links($postname);
            }
        }
        foreach ($flaglib->get_flags() as $flag) {
            $isflagged = $flaglib->is_flagged($post->flags, $flag);
            $class     = 'hsuforum_flag';
            if ($isflagged) {
                $class .= ' hsuforum_flag_active';
            }
            if ($canedit) {
                $label = $flaglib->get_flag_action_label($flag, $postname, $isflagged);
            } else {
                $label = $flaglib->get_flag_state_label($flag, $postname, $isflagged);
            }
            $attributes = array('class' => $class);
            $icon       = $OUTPUT->pix_icon("flag/$flag", '', 'hsuforum', array('class' => 'iconsmall', 'role' => 'presentation'));

            if ($canedit) {
                $attributes['role']       = 'button';
                $attributes['title']      = $label;
                $attributes['data-title'] = $flaglib->get_flag_action_label($flag, $postname, !$isflagged);

                $url = new moodle_url('/mod/hsuforum/route.php', array(
                    'contextid' => $context->id,
                    'action'    => 'flag',
                    'returnurl' => $returnurl,
                    'postid'    => $post->id,
                    'flag'      => $flag,
                    'sesskey'   => sesskey()
                ));
                $text = html_writer::tag('span', $label, array('class' => 'accesshide')).$icon;
                $flaghtml[$flag] = html_writer::link($url, $text, $attributes);
            } else {
                $flaghtml[$flag] = html_writer::tag('span', $icon, $attributes);
            }
        }
        return $flaghtml;
    }

    /**
     * @param stdClass $discussion
     * @param hsuforum_lib_discussion_subscribe $subscribe
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_subscribe_link($discussion, hsuforum_lib_discussion_subscribe $subscribe) {
        global $PAGE;

        static $jsinit = false;

        if ($subscribe->can_subscribe()) {
            if (!$jsinit) {
                $PAGE->requires->js_init_call('M.mod_hsuforum.init_subscribe', null, false, $this->get_js_module());
                $jsinit = true;
            }
            $subscribeurl = new moodle_url('/mod/hsuforum/route.php', array(
                'contextid' => $subscribe->get_context()->id,
                'action' => 'subscribedisc',
                'discussionid' => property_exists($discussion, 'discussion') ? $discussion->discussion : $discussion->id,
                'sesskey' => sesskey(),
                'returnurl' => $PAGE->url,
            ));

            $class = '';
            $name  = format_string($discussion->name);
            if (!empty($discussion->subscriptionid)) {
                $label = get_string('subscribedtodiscussionx', 'hsuforum', $name);
                $class = ' subscribed';
                $pix   = 'check-yes';
            } else {
                $label = get_string('notsubscribedtodiscussionx', 'hsuforum', $name);
                $pix   = 'check-no';
            }
            $text  = html_writer::tag('span', $label, array('class' => 'accesshide'));
            $text .= $this->output->pix_icon($pix, '', 'hsuforum', array('class' => 'iconsmall', 'role' => 'presentation'));

            return html_writer::link($subscribeurl, $text, array(
                'title' => $label,
                'class' => 'hsuforum_discussion_subscribe'.$class,
                'data-name' => $name,
                'role' => 'button',
            ));
        }
        return '';
    }

    /**
     * @param $cm
     * @param $discussion
     * @param $post
     * @param $skipcansee
     * @return bool|object
     * @author Mark Nielsen
     */
    public function post_to_node($cm, $discussion, $post, $skipcansee = false) {
        global $PAGE;

        hsuforum_cm_add_cache($cm);

        // Sometimes we must skip this check because $discussion isn't the actual discussion record, it's some sort of monster!
        if (!$skipcansee and !hsuforum_user_can_see_post($cm->cache->forum, $discussion, $post, NULL, $cm)) {
            return false;
        }
        $displaymode = hsuforum_get_layout_mode($cm->cache->forum);
        $postuser    = hsuforum_extract_postuser($post, $cm->cache->forum, $cm->cache->context);

        $class = '';
        if ($cm->cache->istracked) {
            if (!empty($post->postread)) {
                $class = 'read';
            } else {
                $class = 'unread';
            }
        }
        if ($displaymode == HSUFORUM_MODE_THREADED) {
            $url = new moodle_url('/mod/hsuforum/discuss.php', array('d' => $post->discussion, 'parent' => $post->id));
        } else {
            $url = new moodle_url('/mod/hsuforum/discuss.php', array('d' => $post->discussion));
            $url->set_anchor("p$post->id");
        }
        if (empty($post->parent)) {
            $author = $this->discussion_startedby($cm, $discussion, $postuser).'. '.
                      $this->discussion_lastpostby($cm, $discussion);
        } else {
            $author = get_string('createdbynameondate', 'hsuforum', array('name' => $postuser->fullname, 'date' => userdate($post->modified)));
        }
        $html = "<span><span class=\"$class\">".
                html_writer::link($url, format_string($post->subject,true)).'&nbsp;'.
                $author.'</span>'.
                $PAGE->get_renderer('mod_hsuforum')->post_flags($post, $cm->cache->context).
                "</span>";

        $leaf = true;
        if (!empty($post->replies)) { // Actually a discussion...
            $leaf = false;
        } else if (!empty($post->children)) {
            $leaf = false;
        }
        $node = (object) array(
            'type' => 'html',
            'html' => $html,
            'isLeaf' => $leaf,
            'nowrap' => true,
            'id' => null,
            'children' => array(),
        );

        if (empty($post->parent)) {
            $node->id = $post->discussion;
        }
        if (!empty($post->children)) {
            foreach ($post->children as $childpost) {
                if ($childnode = $this->post_to_node($cm, $discussion, $childpost)) {
                    $node->children[] = $childnode;
                }
            }
        }
        return $node;
    }

    /**
     * @param \context_module $context
     * @param array $nodes
     * @author Mark Nielsen
     * @return string
     */
    public function discussion_nodes(context_module $context, array $nodes) {
        global $OUTPUT, $PAGE;

        $output = '';
        if (!empty($nodes)) {
            $id  = html_writer::random_id('hsuforum_treeview');
            $url = new moodle_url('/mod/hsuforum/route.php', array('contextid' => $context->id, 'action' => 'postnodes'));

            $expandall   = html_writer::link($PAGE->url, get_string('expandall', 'hsuforum'), array('class' => 'hsuforum_expandall'));
            $collapseall = html_writer::link($PAGE->url, get_string('collapseall', 'hsuforum'), array('class' => 'hsuforum_collapseall'));

            $PAGE->requires->js_init_call('M.mod_hsuforum.init_treeview', array($id, $url->out(false), $nodes), false, $this->get_js_module());

            $commands = html_writer::tag('div', $expandall.' / '.$collapseall, array('class' => 'hsuforum_treeview_commands'));
            $treeview = html_writer::tag('div', '', array('id' => $id, 'class' => 'hsuforum_treeview'));

            $output .= html_writer::tag('noscript', $OUTPUT->notification(get_string('javascriptdisableddisplayformat', 'hsuforum')));
            $output .= html_writer::tag('div', $commands.$treeview, array('class' => 'hsuforum_treeview_wrapper'));
        }
        return $output;
    }

    /**
     * @param hsuforum_lib_discussion_sort $sort
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_sorting(hsuforum_lib_discussion_sort $sort) {
        global $PAGE;

        $keyselect = new single_select($PAGE->url, 'dsortkey', $sort->get_key_options_menu(), $sort->get_key(), array());
        $keyselect->set_label(get_string('sortdiscussionsby', 'hsuforum'), array('class' => 'accesshide'));

        $dirselect = new single_select($PAGE->url, 'dsortdirection', $sort->get_direction_options_menu(), $sort->get_direction(), array());
        $dirselect->set_label(get_string('orderdiscussionsby', 'hsuforum'), array('class' => 'accesshide'));

        $output  = html_writer::tag('legend', get_string('sortdiscussions', 'hsuforum'), array('class' => 'accesshide'));
        $output .= html_writer::tag('div', $this->output->render($keyselect), array('class' => 'hsuforum_discussion_sort_key'));
        $output .= html_writer::tag('div', $this->output->render($dirselect), array('class' => 'hsuforum_discussion_sort_direction'));
        $output  = html_writer::tag('fieldset', $output, array('class' => 'invisiblefieldset'));

        return html_writer::tag('div', $output, array('class' => 'hsuforum_discussion_sort'));
    }

    /**
     * @param stdClass|boolean $prevdiscussion
     * @param stdClass|boolean $nextdiscussion
     * @param array $attributes
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_navigation($prevdiscussion, $nextdiscussion, $attributes = array()) {
        if (empty($prevdiscussion) and empty($nextdiscussion)) {
            return '';
        }

        $classes = array();
        $shorten = function ($name) {
            return shorten_text(format_string($name), 25, true);
        };

        if (!empty($prevdiscussion)) {
            $title = get_string('prevdiscussionx', 'hsuforum', $shorten($prevdiscussion->name));
            $html  = html_writer::link(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $prevdiscussion->id)), $title, array('title' => $title));
        } else {
            $html = '';
            $classes[] = 'hsuforum_no_prevtopic';
        }
        $output = html_writer::tag('div', $html, array('class' => 'hsuforumprevtopic'));

        if (!empty($nextdiscussion)) {
            $title = get_string('nextdiscussionx', 'hsuforum', $shorten($nextdiscussion->name));
            $html  = html_writer::link(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $nextdiscussion->id)), $title, array('title' => $title));
        } else {
            $html = '';
            $classes[] = 'hsuforum_no_nexttopic';
        }
        $output .= html_writer::tag('div', $html, array('class' => 'hsuforumnexttopic'));

        if (empty($classes)) {
            $classes[] = 'hsuforum_both_nextprevtopics';
        }
        $classes = 'hsuforumtopicnav '.implode(' ', $classes);

        if (array_key_exists('class', $attributes)) {
            $attributes['class'] = $classes.' '.$attributes['class'];
        } else {
            $attributes['class'] = $classes;
        }
        return html_writer::tag('div', $output, $attributes);
    }

    /**
     * @param stdClass $cm
     * @param stdClass $discussion
     * @return string
     * @author Mark Nielsen
     */
    public function nested_discussion($cm, $discussion) {
        global $PAGE, $USER;

        static $jsinit = false;

        hsuforum_cm_add_cache($cm);

        if (!$jsinit) {
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_nested', null, false, $this->get_js_module());
            $jsinit = true;
        }
        $canreply = hsuforum_user_can_post($cm->cache->forum, $discussion, $USER, $cm, $cm->cache->course, $cm->cache->context);

        $postuser     = hsuforum_extract_postuser($discussion, $cm->cache->forum, $cm->cache->context);
        $postsubject  = $this->post_subject($discussion, $cm->cache->context);
        $postmessage  = $this->post_message($discussion, $cm);
        $postrating   = $this->post_rating($discussion);
        $postcommands = $this->post_commands($discussion, $discussion, $cm, $canreply);

        $postsubject .= html_writer::tag('div', $this->discussion_startedby($cm, $discussion, $postuser), array('class' => 'author'));
        $postsubject .= html_writer::tag('div', $this->discussion_lastpostby($cm, $discussion), array('class' => 'lastpostby'));

        $header  = html_writer::tag('div', $this->render($postuser->user_picture), array('class' => 'picture'));
        $header .= $this->discussion_info($cm, $discussion);
        $header .= html_writer::tag('div', $postsubject, array('class' => 'hsuforum_nested_header_content'));
        $header  = $this->post_header($cm, $discussion, $header);

        $footer  = html_writer::tag('div', $postrating.$postcommands, array('class' => 'hsuforum_nested_footer'));

        if ($discussion->replies > 0) {
            $postsurl = new moodle_url('/mod/hsuforum/route.php', array('action' => 'postsnested', 'contextid' => $cm->cache->context->id, 'discussionid' => $discussion->discussion));
            $posts    = html_writer::tag('div', '', array('class' => 'hsuforum_nested_posts indent', 'postsurl' => $postsurl));
        } else {
            $posts = '';
        }
        $body = html_writer::tag('div', $postmessage.$footer.$posts, array('class' => 'hsuforum_nested_body'));

        return html_writer::tag('div', $header.$body, array('class' => 'hsuforum_nested_wrapper hsuforum_nested_discussion clearfix'));
    }

    /**
     * @param stdClass $cm
     * @param stdClass $discussion
     * @param stdClass $post
     * @param boolean $canreply
     * @return bool|string
     * @author Mark Nielsen
     */
    public function nested_post($cm, $discussion, $post, $canreply) {
        hsuforum_cm_add_cache($cm);

        if (!hsuforum_user_can_see_post($cm->cache->forum, $discussion, $post, NULL, $cm)) {
            return false;
        }
        $postuser     = hsuforum_extract_postuser($post, $cm->cache->forum, $cm->cache->context);
        $postsubject  = $this->post_subject($post, $cm->cache->context);
        $postmessage  = $this->post_message($post, $cm);
        $postrating   = $this->post_rating($post);
        $postcommands = $this->post_commands($post, $discussion, $cm, $canreply);

        $postsubject .= html_writer::tag(
            'div',
            get_string('createdbynameondate', 'hsuforum', array('name' => $postuser->fullname, 'date' => userdate($post->modified, $cm->cache->str->strftimerecentfull))),
            array('class' => 'author')
        );
        $header  = html_writer::tag('div', $this->render($postuser->user_picture), array('class' => 'picture'));
        $header .= html_writer::tag('div', $postsubject, array('class' => 'hsuforum_nested_header_content'));
        $header  = $this->post_header($cm, $post, $header);

        $footer  = html_writer::tag('div', $postrating.$postcommands, array('class' => 'hsuforum_nested_footer'));

        $childrenoutput = '';
        if (!empty($post->children)) {
            foreach ($post->children as $child) {
                if ($childoutput = $this->nested_post($cm, $discussion, $child, $canreply)) {
                    $childrenoutput .= $childoutput;
                }
            }
        }
        $posts = html_writer::tag('div', $childrenoutput, array('class' => 'hsuforum_nested_posts indent postsloaded'));
        $body  = html_writer::tag('div', $postmessage.$footer.$posts, array('class' => 'hsuforum_nested_body'));

        return html_writer::tag('div', $header.$body, array('class' => 'hsuforum_nested_wrapper hsuforum_nested_post clearfix'));
    }

    /**
     * @param $cm
     * @param $discussion
     * @param null $postuser
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_startedby($cm, $discussion, $postuser = null) {
        hsuforum_cm_add_cache($cm);

        if (is_null($postuser)) {
            $postuser = hsuforum_extract_postuser($discussion, $cm->cache->forum, $cm->cache->context);
        }
        if ($cm->cache->groupmode > 0) {
            if (isset($cm->cache->groups[$discussion->groupid])) {
                $group = $cm->cache->groups[$discussion->groupid];
                return get_string('startedbyxgroupx', 'hsuforum', array('name' => $postuser->fullname, 'group' => format_string($group->name)));
            }
        }
        return get_string('startedbyx', 'hsuforum', $postuser->fullname);
    }

    /**
     * @param $cm
     * @param $discussion
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_lastpostby($cm, $discussion) {
        hsuforum_cm_add_cache($cm);

        $usermodified            = new stdClass();
        $usermodified->id        = $discussion->usermodified;
        $usermodified->firstname = $discussion->umfirstname;
        $usermodified->lastname  = $discussion->umlastname;

        $lastpost         = new stdClass;
        $lastpost->id     = $discussion->lastpostid;
        $lastpost->userid = $discussion->usermodified;
        $lastpost->reveal = is_null($discussion->umreveal) ? $discussion->reveal : $discussion->umreveal;

        $usermodified = hsuforum_get_postuser($usermodified, $lastpost, $cm->cache->forum, $cm->cache->context);
        $usedate      = (empty($discussion->timemodified)) ? $discussion->modified : $discussion->timemodified;

        return get_string('lastpostbyx', 'hsuforum', array('name' => $usermodified->fullname, 'time' => userdate($usedate, $cm->cache->str->strftimerecentfull)));
    }

    /**
     * @param stdClass $cm
     * @param stdClass $discussion
     * @return string
     * @author Mark Nielsen
     */
    public function discussion_info($cm, $discussion) {
        hsuforum_cm_add_cache($cm);

        $infos = array();
        if ($cm->cache->caps['mod/hsuforum:viewdiscussion']) {
            $infos[] = html_writer::tag('div', get_string('repliesx', 'hsuforum', $discussion->replies), array('class' => 'replies'));

            if ($cm->cache->cantrack) {
                $unread = html_writer::tag('span', '-', array('class' => 'read'));
                if ($cm->cache->istracked) {
                    if ($discussion->unread > 0) {
                        $unread = html_writer::tag('span', $discussion->unread, array('class' => 'unread'));
                    } else {
                        $unread = html_writer::tag('span', $discussion->unread, array('class' => 'read'));
                    }
                }
                $infos[] = html_writer::tag('div', get_string('unreadx', 'hsuforum', $unread), array('class' => 'unreadposts'));
            }
        }
        require_once(__DIR__.'/lib/discussion/subscribe.php');
        $subscribe = new hsuforum_lib_discussion_subscribe($cm->cache->forum, $cm->cache->context);
        if ($link = $this->discussion_subscribe_link($discussion, $subscribe)) {
            $content  = html_writer::tag('div', get_string('subscribed', 'hsuforum').':&nbsp;', array('class' => 'subscribe_label'));
            $content .= html_writer::tag('div', $link, array('class' => 'subscribe_toggle'));
            $infos[] = html_writer::tag('div', $content, array('class' => 'subscribe'));
        }
        return html_writer::tag('div', implode('', $infos), array('class' => 'discussioninfo'));
    }

    /**
     * @param stdClass $cm
     * @param stdClass $post
     * @param string $headercontent
     * @return string
     * @author Mark Nielsen
     */
    public function post_header($cm, $post, $headercontent) {
        hsuforum_cm_add_cache($cm);

        $unreadclass = $unreadurl = '';
        if ($cm->cache->istracked) {
            if (!empty($post->postread)) {
                $unreadclass = ' read';
            } else {
                $unreadclass = ' unread';
                $unreadurl   = new moodle_url('/mod/hsuforum/route.php', array('action' => 'markread', 'contextid' => $cm->cache->context->id, 'postid' => $post->id));
            }
        }
        return html_writer::tag('div', $headercontent, array('class' => 'hsuforum_nested_header'.$unreadclass, 'title' => get_string('clicktoexpand', 'hsuforum'), 'unreadurl' => $unreadurl));
    }

    /**
     * @param stdClass $post
     * @param context_module $context
     * @return string
     * @author Mark Nielsen
     */
    public function post_subject($post, context_module $context) {
        $postsubject  = $this->raw_post_subject($post);
        $postsubject .= $this->post_flags($post, $context);
        return html_writer::tag('div', $postsubject, array('class' => 'subject'));
    }

    /**
     * @param stdClass $post
     * @return string
     */
    public function raw_post_subject($post) {
        if (empty($post->subjectnoformat)) {
            return format_string($post->subject);
        }
        return $post->subject;
    }

    /**
     * @param stdClass $post
     * @param stdClass $cm
     * @return string
     * @author Mark Nielsen
     */
    public function post_message($post, $cm) {

        hsuforum_cm_add_cache($cm);

        $options = new stdClass;
        $options->para    = false;
        $options->trusted = $post->messagetrust;
        $options->context = $cm->cache->context;

        list($attachments, $attachedimages) = hsuforum_print_attachments($post, $cm, 'separateimages');

        $message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $cm->cache->context->id, 'mod_hsuforum', 'post', $post->id);

        $postcontent = format_text($message, $post->messageformat, $options, $cm->course);
        if (!empty($cm->cache->forum->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class' => 'post-word-count'));
        }
        if (!empty($attachments)) {
            $postcontent .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
        }
        if (!empty($attachedimages)) {
            $postcontent .= html_writer::tag('div', $attachedimages, array('class' => 'attachedimages'));
        }
        $postcontent  = html_writer::tag('div', $postcontent, array('class' => 'posting'));

        return html_writer::tag('div', $postcontent, array('class' => 'content'));
    }

    /**
     * @param stdClass $post
     * @return string
     * @author Mark Nielsen
     */
    public function post_rating($post) {
        $output = '';
        if (!empty($post->rating)) {
            $rendered = $this->render($post->rating);
            if (!empty($rendered)) {
                $output = html_writer::tag('div', $rendered, array('class' => 'forum-post-rating'));
            }
        }
        return $output;
    }

    /**
     * @param stdClass $post
     * @param stdClass $discussion
     * @param stdClass $cm
     * @param bool $canreply
     * @return string
     * @throws coding_exception
     * @author Mark Nielsen
     */
    public function post_commands($post, $discussion, $cm, $canreply) {
        $commands = $this->post_get_commands($post, $discussion, $cm, $canreply);
        return html_writer::tag('div', implode(' | ', $commands), array('class'=>'commands'));
    }

    /**
     * @param stdClass $post
     * @param stdClass $discussion
     * @param stdClass $cm
     * @param bool $canreply
     * @return array
     * @throws coding_exception
     * @author Mark Nielsen
     */
    public function post_get_commands($post, $discussion, $cm, $canreply) {
        global $CFG, $USER;

        hsuforum_cm_add_cache($cm);

        $discussionlink = new moodle_url('/mod/hsuforum/discuss.php', array('d' => $post->discussion));
        $ownpost        = (isloggedin() and $post->userid == $USER->id);
        $commands       = array();

        // Zoom in to the parent specifically
        if ($post->parent) {
            $url = new moodle_url($discussionlink);
            if ($cm->cache->displaymode == HSUFORUM_MODE_THREADED) {
                $url->param('parent', $post->parent);
            } else {
                $url->set_anchor('p'.$post->parent);
            }
            $commands['parent'] = array('url' => $url, 'text' => $cm->cache->str->parent);
        }

        // Hack for allow to edit news posts those are not displayed yet until they are displayed
        $age = time() - $post->created;
        if (!$post->parent && $cm->cache->forum->type == 'news' && $discussion->timestart > time()) {
            $age = 0;
        }
        if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/hsuforum:editanypost']) {
            $commands['edit'] = array('url' => new moodle_url('/mod/hsuforum/post.php', array('edit' => $post->id)), 'text' => $cm->cache->str->edit);
        }

        if ($cm->cache->caps['mod/hsuforum:splitdiscussions'] && $post->parent && $cm->cache->forum->type != 'single') {
            $commands['split'] = array('url' => new moodle_url('/mod/hsuforum/post.php', array('prune' => $post->id)), 'text' => $cm->cache->str->prune, 'title' => $cm->cache->str->pruneheading);
        }

        if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/hsuforum:deleteownpost']) || $cm->cache->caps['mod/hsuforum:deleteanypost']) {
            $commands['delete'] = array('url' => new moodle_url('/mod/hsuforum/post.php', array('delete' => $post->id)), 'text' => $cm->cache->str->delete);
        }

        if (!property_exists($post, 'privatereply')) {
            throw new coding_exception('Must set post\'s privatereply property!');
        }
        if ($canreply and empty($post->privatereply)) {
            $postuser   = hsuforum_extract_postuser($post, $cm->cache->forum, $cm->cache->context);
            $replytitle = get_string('replybuttontitle', 'hsuforum', strip_tags($postuser->fullname));
            $commands['reply'] = array('url' => new moodle_url('/mod/hsuforum/post.php', array('reply' => $post->id)), 'text' => $cm->cache->str->reply, 'title' => $replytitle);
        }

        if ($CFG->enableportfolios && ($cm->cache->caps['mod/hsuforum:exportpost'] || ($ownpost && $cm->cache->caps['mod/hsuforum:exportownpost']))) {
            require_once($CFG->libdir.'/portfoliolib.php');
            $button = new portfolio_add_button();
            $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id), 'mod_hsuforum');
            list($attachments, $attachedimages) = hsuforum_print_attachments($post, $cm, 'separateimages');
            if (empty($attachments)) {
                $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
            } else {
                $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
            }
            $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            if (!empty($porfoliohtml)) {
                $commands['portfolio'] = $porfoliohtml;
            }
        }
        foreach ($commands as $key => $command) {
            if (is_array($command)) {
                $attributes = array();
                if (array_key_exists('title', $command)) {
                    $attributes = array('title' => $command['title']);
                }
                $commands[$key] = html_writer::link($command['url'], $command['text'], $attributes);
            }
        }
        return $commands;
    }

    /**
     * @param $userid
     * @param $cm
     * @param null|stdClass $showonlypreference
     *
     * @return string
     * @author Mark Nielsen
     */
    public function user_posts_overview($userid, $cm, $showonlypreference = null) {
        global $PAGE;

        hsuforum_cm_add_cache($cm);

        require_once(__DIR__.'/lib/flag.php');

        $showonlypreferencebutton = '';
        if (!empty($showonlypreference) and !empty($showonlypreference->button) and !$cm->cache->forum->anonymous) {
            $showonlypreferencebutton = $showonlypreference->button;
        }

        $output    = '';
        $postcount = $discussioncount = $flagcount = 0;
        $flaglib   = new hsuforum_lib_flag();
        if ($posts = hsuforum_get_user_posts($cm->cache->forum->id, $userid, $cm->cache->context)) {
            $discussions = hsuforum_get_user_involved_discussions($cm->cache->forum->id, $userid);
            if (!empty($showonlypreference) and !empty($showonlypreference->preference)) {
                foreach ($discussions as $discussion) {
                    if ($discussion->userid == $userid and array_key_exists($discussion->firstpost, $posts)) {
                        $discussionpost = $posts[$discussion->firstpost];

                        $discussioncount++;
                        if ($flaglib->is_flagged($discussionpost->flags, 'substantive')) {
                            $flagcount++;
                        }
                    } else {
                        if (!$discussionpost = hsuforum_get_post_full($discussion->firstpost)) {
                            continue;
                        }
                    }
                    if (!$cm->cache->forum->anonymous) {
                        $output .= hsuforum_print_post($discussionpost, $discussion, $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, '');
                        $output .= html_writer::start_tag('div', array('class' => 'indent'));
                    }
                    foreach ($posts as $post) {
                        if ($post->discussion == $discussion->id and !empty($post->parent)) {
                            $postcount++;
                            if ($flaglib->is_flagged($post->flags, 'substantive')) {
                                $flagcount++;
                            }
                            $command = html_writer::link(
                                new moodle_url('/mod/hsuforum/route.php', array('action' => 'postincontext', 'contextid' => $cm->cache->context->id, 'postid' => $post->id)),
                                get_string('viewincontext', 'hsuforum'),
                                array('class' => 'hsuforum_viewincontext', 'postid' => $post->id)
                            );
                            if (!$cm->cache->forum->anonymous) {
                                $output .= hsuforum_print_post($post, $discussion, $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, $command);
                            }
                        }
                    }
                    if (!$cm->cache->forum->anonymous) {
                        $output .= html_writer::end_tag('div');
                    }
                }
            } else {
                foreach ($posts as $post) {
                    if (!empty($post->parent)) {
                        $postcount++;
                    } else {
                        $discussioncount++;
                    }
                    if ($flaglib->is_flagged($post->flags, 'substantive')) {
                        $flagcount++;
                    }
                    if (!$cm->cache->forum->anonymous) {
                        $command = html_writer::link(
                            new moodle_url('/mod/hsuforum/route.php', array('action' => 'postincontext', 'contextid' => $cm->cache->context->id, 'postid' => $post->id)),
                            get_string('viewincontext', 'hsuforum'),
                            array('class' => 'hsuforum_viewincontext', 'postid' => $post->id)
                        );
                        $output .= hsuforum_print_post($post, $discussions[$post->discussion], $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, $command);
                    }
                }
            }
        }
        if (!empty($postcount) or !empty($discussioncount)) {
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_post_in_context', null, false, $this->get_js_module());

            if ($cm->cache->forum->anonymous) {
                $output = html_writer::tag('h3', get_string('thisisanonymous', 'hsuforum'));
            }
            $counts = array(
                get_string('totalpostsanddiscussions', 'hsuforum', ($discussioncount+$postcount)),
                get_string('totaldiscussions', 'hsuforum', $discussioncount),
                get_string('totalreplies', 'hsuforum', $postcount),
                get_string('totalsubstantive', 'hsuforum', $flagcount),
            );
            if ($grade = hsuforum_get_user_formatted_rating_grade($cm->cache->forum, $userid)) {
                $counts[] = get_string('totalrating', 'hsuforum', $grade);
            }
            $countshtml = '';
            foreach ($counts as $count) {
                $countshtml .= html_writer::tag('div', $count, array('class' => 'hsuforum_count'));
            }
            $output = html_writer::tag('div', $countshtml, array('class' => 'hsuforum_counts')).$showonlypreferencebutton.$output;
            $output = html_writer::tag('div', $output, array('class' => 'mod_hsuforum_posts_container'));
        }
        return $output;
    }

    /**
     * @param $cm
     * @param $discussion
     * @param $post
     * @return string
     * @author Mark Nielsen
     */
    public function post_in_context($cm, $discussion, $post) {
        $output = '';

        hsuforum_cm_add_cache($cm);

        $output .= hsuforum_print_post($post, $discussion, $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, '');

        if (!empty($post->children)) {
            foreach ($post->children as $child) {
                $output .= html_writer::start_tag('div', array('class' => 'indent'));
                $output .= $this->post_in_context($cm, $discussion, $child);
                $output .= html_writer::end_tag('div');
            }
        }
        return $output;
    }

    /**
     * @param $course
     * @param $cm
     * @param $forum
     * @param context_module $context
     * @author Mark Nielsen
     */
    public function view($course, $cm, $forum, context_module $context) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;

        require_once(__DIR__.'/lib/discussion/sort.php');
        require_once(__DIR__.'/lib/discussion/nav.php');

        $showall = optional_param('showall', '', PARAM_INT); // show all discussions on one page
        $mode    = optional_param('mode', 0, PARAM_INT); // Display mode (for single forum)
        $page    = optional_param('page', 0, PARAM_INT); // which page to show

        echo $OUTPUT->heading(format_string($forum->name), 2);

        /// find out current groups mode
        groups_get_activity_group($cm);
        groups_get_activity_groupmode($cm);

        // Unset session
        hsuforum_lib_discussion_nav::set_to_session();

        $dsort = hsuforum_lib_discussion_sort::get_from_session($forum, $context);

        // If it's a simple single discussion forum, we need to print the display
        // mode control.
        $discussion = NULL;
        if ($forum->type == 'single') {
            $discussions = $DB->get_records('hsuforum_discussions', array('forum'=>$forum->id), 'timemodified ASC');
            if (!empty($discussions)) {
                $discussion = array_pop($discussions);
            }
            if ($discussion) {
                if ($mode) {
                    set_user_preference("hsuforum_displaymode", $mode);
                }
                $displaymode   = get_user_preferences("hsuforum_displaymode", $CFG->hsuforum_displaymode);
                $select        = new single_select(new moodle_url("/mod/hsuforum/view.php", array('id'=> $cm->id)), 'mode', hsuforum_get_layout_modes($forum), $displaymode, null, "mode");
                $select->set_label(get_string('displaydiscussionreplies', 'hsuforum'), array('class' => 'accesshide'));
                $select->class = "forummode";
                echo $OUTPUT->render($select);
            }
        }

        if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
            $a = new stdClass();
            $a->blockafter = $forum->blockafter;
            $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
            echo $OUTPUT->notification(get_string('thisforumisthrottled', 'hsuforum', $a));
        }

        if ($forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
            echo $OUTPUT->notification(get_string('qandanotify','hsuforum'));
        }

        switch ($forum->type) {
            case 'single':
                if (!empty($discussions) && count($discussions) > 1) {
                    echo $OUTPUT->notification(get_string('warnformorepost', 'hsuforum'));
                }
                if (! $post = hsuforum_get_post_full($discussion->firstpost)) {
                    print_error('cannotfindfirstpost', 'hsuforum');
                }
                if ($mode) {
                    set_user_preference("hsuforum_displaymode", $mode);
                }

                $canreply    = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);
                $canrate     = has_capability('mod/hsuforum:rate', $context);
                $displaymode = get_user_preferences("hsuforum_displaymode", $CFG->hsuforum_displaymode);

                echo '&nbsp;'; // this should fix the floating in FF
                hsuforum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
                break;

            case 'eachuser':
                if (!empty($forum->intro)) {
                    echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
                }
                echo '<p class="mdl-align">';
                if (hsuforum_user_can_post_discussion($forum, null, -1, $cm)) {
                    print_string("allowsdiscussions", "hsuforum");
                } else {
                    echo '&nbsp;';
                }
                echo '</p>';
                if (!empty($showall)) {
                    hsuforum_print_latest_discussions($course, $forum, 0, 'header', $dsort->get_sort_sql(), -1, -1, -1, 0, $cm);
                } else {
                    hsuforum_print_latest_discussions($course, $forum, -1, 'header', $dsort->get_sort_sql(), -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
                }
                break;

            case 'teacher':
                if (!empty($showall)) {
                    hsuforum_print_latest_discussions($course, $forum, 0, 'header', $dsort->get_sort_sql(), -1, -1, -1, 0, $cm);
                } else {
                    hsuforum_print_latest_discussions($course, $forum, -1, 'header', $dsort->get_sort_sql(), -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
                }
                break;

            case 'blog':
                if (!empty($forum->intro)) {
                    echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
                }
                echo '<br />';
                if (!empty($showall)) {
                    hsuforum_print_latest_discussions($course, $forum, 0, 'plain', $dsort->get_sort_sql(), -1, -1, -1, 0, $cm);
                } else {
                    hsuforum_print_latest_discussions($course, $forum, -1, 'plain', $dsort->get_sort_sql(), -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
                }
                break;

            default:
                if (!empty($forum->intro)) {
                    echo $OUTPUT->box(format_module_intro('hsuforum', $forum, $cm->id), 'generalbox', 'intro');
                }
                if (!empty($showall)) {
                    hsuforum_print_latest_discussions($course, $forum, 0, 'header', $dsort->get_sort_sql(), -1, -1, -1, 0, $cm);
                } else {
                    hsuforum_print_latest_discussions($course, $forum, -1, 'header', $dsort->get_sort_sql(), -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
                }


                break;
        }
    }

    /**
     * @param Exception[] $errors
     * @return string;
     */
    public function validation_errors($errors) {
        $message = '';
        if (count($errors) == 1) {
            $error = current($errors);
            $message = get_string('validationerrorx', 'hsuforum', $error->getMessage());
        } else if (count($errors) > 1) {
            $items = array();
            foreach ($errors as $error) {
                $items[] = $error->getMessage();
            }
            $message = get_string('validationerrorsx', 'hsuforum', array(
                'count'  => count($errors),
                'errors' => html_writer::alist($items, null, 'ol'),
            ));
        }
        return $message;
    }

    /**
     * Get the simple edit discussion form
     *
     * @param object $cm
     * @param int $postid
     * @param array $data Template data
     * @return string
     */
    public function simple_edit_discussion($cm, $postid = 0, array $data = array()) {
        if (!empty($postid)) {
            $params = array('edit' => $postid);
            $legend = get_string('editingpost', 'hsuforum');
        } else {
            $params  = array('forum' => $cm->instance);
            $legend = get_string('addyourdiscussion', 'hsuforum');
        }
        $context = context_module::instance($cm->id);

        $data += array(
            'itemid'        => 0,
            'groupid'       => 0,
            'messageformat' => FORMAT_HTML,
        );
        $actionurl = new moodle_url('/mod/hsuforum/route.php', array(
            'action'        => (empty($postid)) ? 'add_discussion' : 'update_post',
            'sesskey'       => sesskey(),
            'edit'          => $postid,
            'contextid'     => $context->id,
            'itemid'        => $data['itemid'],
            'messageformat' => $data['messageformat'],
        ));

        $extrahtml = '';
        if (groups_get_activity_groupmode($cm)) {
            $groupdata = groups_get_activity_allowed_groups($cm);
            if (count($groupdata) > 1 && has_capability('mod/hsuforum:movediscussions', $context)) {
                $groupinfo = array('0' => get_string('allparticipants'));
                foreach ($groupdata as $grouptemp) {
                    $groupinfo[$grouptemp->id] = $grouptemp->name;
                }
                $extrahtml = html_writer::tag('span', get_string('group'));
                $extrahtml .= html_writer::select($groupinfo, 'groupinfo', $data['groupid'], false);
                $extrahtml = html_writer::tag('label', $extrahtml);
            } else {
                $actionurl->param('groupinfo', groups_get_activity_group($cm));
            }
        }
        $data += array(
            'postid'      => $postid,
            'context'     => $context,
            'actionurl'   => $actionurl,
            'class'       => 'hsuforum-discussion',
            'legend'      => $legend,
            'extrahtml'   => $extrahtml,
            'advancedurl' => new moodle_url('/mod/hsuforum/post.php', $params),
        );
        return $this->simple_edit_template($data);
    }

    /**
     * Get the simple edit post form
     *
     * @param object $cm
     * @param bool $isedit If we are editing or not
     * @param int $postid If editing, then the ID of the post we are editing. If
     *                    not editing, then the ID of the post we are replying to.
     * @param array $data Template data
     * @return string
     */
    public function simple_edit_post($cm, $isedit = false, $postid = 0, array $data = array()) {
        if ($isedit) {
            $param  = 'edit';
            $legend = get_string('editingpost', 'hsuforum');
        } else {
            // It is a reply, AKA new post
            $param  = 'reply';
            $legend = get_string('addareply', 'hsuforum');
        }
        $context = context_module::instance($cm->id);

        $data += array(
            'itemid'        => 0,
            'private'       => 0,
            'messageformat' => FORMAT_HTML,
        );
        $actionurl = new moodle_url('/mod/hsuforum/route.php', array(
            'action'        => ($isedit) ? 'update_post' : 'reply',
            $param          => $postid,
            'sesskey'       => sesskey(),
            'contextid'     => $context->id,
            'itemid'        => $data['itemid'],
            'messageformat' => $data['messageformat'],
        ));

        $extrahtml = '';
        if (has_capability('mod/hsuforum:allowprivate', $context)) {
            $extrahtml = html_writer::tag('label', html_writer::checkbox('privatereply', 1, !empty($data['private'])).
                get_string('privatereply', 'hsuforum'));
        }
        $data += array(
            'postid'          => ($isedit) ? $postid : 0,
            'context'         => $context,
            'actionurl'       => $actionurl,
            'class'           => 'hsuforum-reply',
            'legend'          => $legend,
            'extrahtml'       => $extrahtml,
            'subjectrequired' => $isedit,
            'advancedurl'     => new moodle_url('/mod/hsuforum/post.php', array($param => $postid)),
        );
        return $this->simple_edit_template($data);
    }

    /**
     * The simple edit template
     *
     * @param array $t The letter "t" is for template! Put template variables into here
     * @return string
     */
    protected function simple_edit_template($t) {
        global $USER;

        $required = get_string('required');
        $subjectlabeldefault = get_string('subject', 'hsuforum');
        if (!array_key_exists('subjectrequired', $t) || $t['subjectrequired'] === true) {
            $subjectlabeldefault .= " ($required)";
        }

        // Apply some sensible defaults.
        $t += array(
            'postid'             => 0,
            'hidden'             => '',
            'subject'            => '',
            'subjectlabel'       => $subjectlabeldefault,
            'subjectrequired'    => true,
            'subjectplaceholder' => get_string('subjectplaceholder', 'hsuforum'),
            'message'            => '',
            'messagelabel'       => get_string('message', 'hsuforum')." ($required)",
            'messageplaceholder' => get_string('messageplaceholder', 'hsuforum'),
            'attachmentlabel'    => get_string('attachment', 'hsuforum'),
            'submitlabel'        => get_string('submit', 'hsuforum'),
            'cancellabel'        => get_string('cancel'),
            'userpicture'        => $this->output->user_picture($USER, array('link' => false, 'size' => 100)),
            'extrahtml'          => '',
            'advancedlabel'      => get_string('useadvancededitor', 'hsuforum')
        );

        $t            = (object) $t;
        $legend       = s($t->legend);
        $subject      = s($t->subject);
        $hidden       = html_writer::input_hidden_params($t->actionurl);
        $actionurl    = $t->actionurl->out_omit_querystring();
        $advancedurl  = s($t->advancedurl);
        $messagelabel = s($t->messagelabel);
        $files        = '';

        $subjectrequired = '';
        if ($t->subjectrequired) {
            $subjectrequired = 'required="required"';
        }
        if (!empty($t->postid)) {
            require_once(__DIR__.'/classes/attachments.php');
            $attachments = new \mod_hsuforum\attachments($t->context);
            foreach ($attachments->get_attachments($t->postid) as $file) {
                $checkbox = html_writer::checkbox('deleteattachment[]', $file->get_filename(), false).
                    get_string('deleteattachmentx', 'hsuforum', $file->get_filename());

                $files .= html_writer::tag('label', $checkbox);
            }
            $files = html_writer::tag('legend', get_string('deleteattachments', 'hsuforum'), array('class' => 'accesshide')).$files;
            $files = html_writer::tag('fieldset', $files);
        }

        return <<<HTML
<div class="hsuforum-reply-wrapper">
    <form method="post" role="region" aria-label="$legend" class="hsuforum-form $t->class" action="$actionurl" autocomplete="off">
        $hidden
        <fieldset>
            <legend>$t->legend</legend>
            <div class="hsuforum-validation-errors" role="alert"></div>
            <div class="hsuforum-post-figure">
                $t->userpicture
            </div>
            <div class="hsuforum-post-body">
                <label>
                    <span class="accesshide">$t->subjectlabel</span>
                    <input type="text" placeholder="$t->subjectplaceholder" name="subject" class="form-control" $subjectrequired spellcheck="true" value="$subject" maxlength="255" />
                </label>

                <textarea name="message" class="hidden"></textarea>
                <div data-placeholder="$t->messageplaceholder" aria-label="$messagelabel" contenteditable="true" required="required" spellcheck="true" role="textbox" aria-multiline="true" class="textarea">$t->message</div>

                $files
                <label>
                    <span class="accesshide">$t->attachmentlabel</span>
                    <input type="file" name="attachment[]" multiple="multiple" />
                </label>

                $t->extrahtml

                <button type="submit">$t->submitlabel</button>
                <a href="#" class="hsuforum-cancel disable-router">$t->cancellabel</a>
                <a href="$advancedurl" class="hsuforum-use-advanced disable-router">$t->advancedlabel</a>
            </div>
        </fieldset>
    </form>
</div>
HTML;

    }
}

class mod_hsuforum_article_renderer extends mod_hsuforum_renderer implements render_interface {
    /**
     * @var hsuforum_lib_discussion_nav
     */
    protected $discussionnav;
    /**
     * Override to prevent output
     */
    public function discussion_navigation($prevdiscussion, $nextdiscussion, $attributes = array()) {
        return '';
    }

    /**
     * @param object $cm
     * @return hsuforum_lib_discussion_nav
     */
    protected function get_discussion_nav($cm) {
        if (!$this->discussionnav instanceof hsuforum_lib_discussion_nav) {
            require_once(__DIR__.'/lib/discussion/sort.php');
            require_once(__DIR__.'/lib/discussion/nav.php');

            hsuforum_cm_add_cache($cm);
            $dsort = hsuforum_lib_discussion_sort::get_from_session($cm->cache->forum, $cm->cache->context);
            $this->discussionnav  = hsuforum_lib_discussion_nav::get_from_session($cm, $dsort);
        }
        return $this->discussionnav;
    }

    public function article_js($context = null) {
        if (!$context instanceof \context) {
            $contextid = $this->page->context->id;
        } else {
            $contextid = $context->id;
        }
        // For some reason, I need to require core_rating manually...
        $this->page->requires->js_module('core_rating');
        $this->page->requires->yui_module(
            'moodle-mod_hsuforum-article',
            'M.mod_hsuforum.init_article',
            array(array(
                'contextId' => $contextid,
            ))
        );
        $this->page->requires->strings_for_js(array(
            'replytox',
            'xdiscussions',
            'deletesure',
            'discussionloaded',
            'discussionclosed',
        ), 'mod_hsuforum');
        $this->page->requires->string_for_js('changesmadereallygoaway', 'moodle');
    }

    public function article_assets($cm) {
        $context = context_module::instance($cm->id);
        $this->article_js($context);
        $output = html_writer::tag(
            'script',
            $this->simple_edit_post($cm),
            array('type' => 'text/template', 'id' => 'hsuforum-reply-template')
        );
        $output .= html_writer::tag(
            'script',
            $this->simple_edit_discussion($cm),
            array('type' => 'text/template', 'id' => 'hsuforum-discussion-template')
        );
        return $output;
    }

    /**
     * Render a list of discussions
     *
     * @param \stdClass $cm The forum course module
     * @param array $discussions A list of discussion and discussion post pairs, EG: array(array($discussion, $post), ...)
     * @param array $options Display options and information, EG: total discussions, page number and discussions per page
     * @return string
     */
    public function discussions($cm, array $discussions, array $options) {
        hsuforum_cm_add_cache($cm);

        $output = html_writer::tag('div', '', array('class' => 'hsuforum-new-discussion-target'));
        foreach ($discussions as $discussionpost) {
            list($discussion, $post) = $discussionpost;
            $output .= $this->discussion($cm, $discussion, $post);
        }

        $currentcount = ($options['page'] * $options['perpage']) + $options['perpage'];
        if (!empty($options['total']) && $currentcount < $options['total']) {
            $url = $this->page->url;
            $url->param('page', $options['page'] + 1);
            $output .= $this->output->container('', 'hsuforum-threads-load-target');
            $output .= html_writer::link($url, get_string('loadmorediscussions', 'hsuforum'), array(
                'class'        => 'hsuforum-threads-load-more',
                'data-perpage' => $options['perpage'],
                'data-total'   => $options['total'],
                'role' => 'button'
            ));
        }
        return $this->notification_area().
            $this->output->container('', 'hsuforum-add-discussion-target').
            html_writer::tag('section', $output, array('role' => 'region', 'aria-label' => get_string('discussions', 'hsuforum'), 'class' => 'hsuforum-threads-wrapper', 'tabindex' => '-1')).
            $this->article_assets($cm);
    }

    /**
     * Render a single, stand alone discussion
     *
     * This is very similar to discussion(), but allows for
     * wrapping a single discussion in extra renderings
     * when the discussion is the only thing being viewed
     * on the page.
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The discussion to render
     * @param \stdClass $post The discussion's post to render
     * @param \stdClass[] $posts The discussion posts
     * @param null|boolean $canreply If the user can reply or not (optional)
     * @return string
     */
    public function discussion_thread($cm, $discussion, $post, array $posts, $canreply = null) {
        $output  = $this->discussion($cm, $discussion, $post, $posts, $canreply);
        $output .= $this->article_assets($cm);

        return $output;
    }

    /**
     * Render a single discussion
     *
     * Optionally also render the discussion's posts
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The discussion to render
     * @param \stdClass $post The discussion's post to render
     * @param \stdClass[] $posts The discussion posts (optional)
     * @param null|boolean $canreply If the user can reply or not (optional)
     * @return string
     */
    public function discussion($cm, $discussion, $post, array $posts = array(), $canreply = null) {
        hsuforum_cm_add_cache($cm);

        $postuser = hsuforum_extract_postuser($post, $cm->cache->forum, $cm->cache->context);
        $postuser->user_picture->size = 100;

        if (is_null($canreply)) {
            $canreply = hsuforum_user_can_post($cm->cache->forum, $discussion, null, $cm, $cm->cache->course, $cm->cache->context);
        }
        // Meta properties, sometimes don't exist.
        if (!property_exists($discussion, 'replies')) {
            if (!empty($posts)) {
                $discussion->replies = count($posts) - 1;
            } else {
                $discussion->replies = 0;
            }
        } else if (empty($discussion->replies)) {
            $discussion->replies = 0;
        }
        if (!property_exists($discussion, 'unread') or empty($discussion->unread)) {
            $discussion->unread = '-';
        }
        $format = get_string('articledateformat', 'hsuforum');

        $group = '';
        if ($cm->cache->groupmode > 0 && isset($cm->cache->groups[$discussion->groupid])) {
            $group = $cm->cache->groups[$discussion->groupid];
            $group = format_string($group->name);
        }

        $data           = new stdClass;
        $data->id       = $discussion->id;
        $data->postid   = $post->id;
        $data->unread   = $discussion->unread;
        $data->fullname = $postuser->fullname;
        $data->subject  = $this->raw_post_subject($post);
        $data->message  = $this->post_message($post, $cm);
        $data->created  = userdate($post->created, $format);
        $data->datetime = date(DATE_W3C, usertime($post->created));
        $data->modified = userdate($discussion->timemodified, $format);
        $data->unread   = $discussion->unread;
        $data->replies  = $discussion->replies;
        $data->group    = $group;
        $data->imagesrc = $postuser->user_picture->get_url($this->page)->out();
        $data->userurl  = $this->get_post_user_url($cm, $postuser);
        $data->viewurl  = new moodle_url('/mod/hsuforum/discuss.php', array('d' => $discussion->id));
        $data->nav      = $this->discussion_nav($cm, $discussion);
        $data->tools    = $this->toolbox($this->toolbox_commands($cm, $discussion, $post, $canreply), 'hsuforum-thread-tools');

        if ($canreply) {
            $data->replyform = html_writer::tag(
                'div', $this->simple_edit_post($cm, false, $post->id), array('class' => 'hsuforum-footer-reply')
            );
        } else {
            $data->replyform = '';
        }
        if (empty($posts) and !empty($discussion->replies)) {
            $data->posts = html_writer::tag('div', '', array('class' => 'thread-replies-placeholder'));
        } else {
            $data->posts = $this->posts($cm, $discussion, $posts, $canreply);
        }
        return $this->discussion_template($data);
    }

    /**
     * Render a list of posts
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The discussion for the posts
     * @param \stdClass[] $posts The posts to render
     * @param bool $canreply
     * @throws coding_exception
     * @return string
     */
    public function posts($cm, $discussion, $posts, $canreply = false) {
        global $USER;

        $items = '';
        $count = 0;
        if (!empty($posts)) {
            if (!array_key_exists($discussion->firstpost, $posts)) {
                throw new coding_exception('Missing discussion post');
            }
            $parent = $posts[$discussion->firstpost];
            $items .= $this->post_walker($cm, $discussion, $posts, $parent, $canreply, $count);

            // Mark post as read. $CFG->hsuforum_usermarksread not yet implemented.
            if ($cm->cache->istracked && empty($parent->postread)) {
                hsuforum_tp_mark_post_read($USER->id, $parent, $cm->cache->forum->id);
            }
        }
        $output  = html_writer::tag('h5', get_string('xreplies', 'hsuforum', $count), array('role' => 'heading', 'aria-level' => '5'));
        if (!empty($count)) {
            $output .= html_writer::tag('ol', $items, array('class' => 'hsuforum-thread-replies-list'));
        }
        return html_writer::tag('div', $output, array('class' => 'hsuforum-thread-replies'), array('tabindex' => 0));
    }

    /**
     * Internal method to walk over a list of posts, rendering
     * each post and their children.
     *
     * @param object $cm
     * @param object $discussion
     * @param array $posts
     * @param object $parent
     * @param bool $canreply
     * @param int $count Keep track of the number of posts actually rendered
     * @param int $depth
     * @return string
     */
    protected function post_walker($cm, $discussion, $posts, $parent, $canreply, &$count, $depth = 0) {
        $output = '';
        foreach ($posts as $post) {
            if ($post->parent != $parent->id) {
                continue;
            }
            $html = $this->post($cm, $discussion, $post, $canreply, $parent, array(), $depth);
            if (!empty($html)) {
                $count++;
                $output .= html_writer::tag('li', $html, array('class' => "hsuforum-post clearfix depth$depth", 'data-depth' => $depth, 'tabindex' => '-1', 'data-count' => $count));

                if (!empty($post->children)) {
                    $output .= $this->post_walker($cm, $discussion, $posts, $post, $canreply, $count, ($depth + 1));
                }
            }
        }
        return $output;
    }

    /**
     * Render a single post
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The post's discussion
     * @param \stdClass $post The post to render
     * @param bool $canreply
     * @param null|object $parent Optional, parent post
     * @param array $commands Override default post commands
     * @param int $depth Depth of the post
     * @return string
     */
    public function post($cm, $discussion, $post, $canreply = false, $parent = null, array $commands = array(), $depth = 0) {
        global $USER;

        hsuforum_cm_add_cache($cm);

        if (!hsuforum_user_can_see_post($cm->cache->forum, $discussion, $post, null, $cm)) {
            return '';
        }
        if (empty($commands)) {
            $commands = $this->toolbox_commands($cm, $discussion, $post, $canreply);
        }
        $postuser = hsuforum_extract_postuser($post, $cm->cache->forum, $cm->cache->context);
        $postuser->user_picture->size = 100;

        // $post->breadcrumb comes from search btw.
        $data                 = new stdClass;
        $data->id             = $post->id;
        $data->discussionid   = $discussion->id;
        $data->fullname       = $postuser->fullname;
        $data->subject        = property_exists($post, 'breadcrumb') ? $post->breadcrumb : $this->raw_post_subject($post);
        $data->message        = $this->post_message($post, $cm);
        $data->created        = userdate($post->created, get_string('articledateformat', 'hsuforum'));
        $data->datetime       = date(DATE_W3C, usertime($post->created));
        $data->privatereply   = $post->privatereply;
        $data->imagesrc       = $postuser->user_picture->get_url($this->page)->out();
        $data->userurl        = $this->get_post_user_url($cm, $postuser);
        $data->unread         = ($cm->cache->istracked && empty($post->postread)) ? true : false;
        $data->permalink      = new moodle_url('/mod/hsuforum/discuss.php#p'.$post->id, array('d' => $discussion->id));
        $data->parentfullname = '';
        $data->parentuserurl  = '';
        $data->tools          = $this->toolbox($commands, 'hsuforum-post-tools');
        $data->depth          = $depth;

        // Mark post as read. $CFG->hsuforum_usermarksread not yet implemented.
        if ($data->unread) {
            hsuforum_tp_mark_post_read($USER->id, $post, $cm->cache->forum->id);
        }
        if (!empty($parent)) {
            $parentuser = hsuforum_extract_postuser($parent, $cm->cache->forum, $cm->cache->context);
            $parentuser->user_picture->size = 100;
            $data->parentfullname = $parentuser->fullname;
            $data->parentuserurl = $this->get_post_user_url($cm, $parentuser);
        }
        return $this->post_template($data);
    }

    public function discussion_template($d) {
        $meta = '';
        if(!empty($d->replies)) {
            $meta = get_string('discussionmeta', 'hsuforum', array(
                'replies' => $d->replies,
                'updated' => $d->modified,
            ));
        }
        if (!empty($d->userurl)) {
            $byuser = html_writer::link($d->userurl, $d->fullname, array('class' => 'hsuforum-thread-author'));
        } else {
            $byuser = html_writer::tag('span', $d->fullname, array('class' => 'hsuforum-thread-author'));
        }
        $unread = $attrs = $group = '';
        if ($d->unread != '-') {
            $unread  = get_string('xunread', 'hsuforum', $d->unread);
            $unread  = html_writer::tag('span', $unread, array('class' => 'hsuforum-unreadcount'));
            $attrs   = 'data-isunread="true"';
        }
        $byuser = get_string('byx', 'hsuforum', $byuser);
        $author = s(strip_tags($d->fullname));
        if (!empty($d->group)) {
            $group = " | $d->group";
        }
        
        if (!empty($meta)) {
            $meta = '<p class="hsuforum-thread-replies-meta">'.$meta.'</p>';
        }
        return <<<HTML
<article id="p{$d->postid}" role="article" class="hsuforum-thread-article hsuforum-post-target clearfix" tabindex="0"
    data-discussionid="$d->id" data-postid="$d->postid" data-author="$author" data-isdiscussion="true" $attrs
     aria-hidden="false" aria-expanded="false" aria-labelledby="thread_title_{$d->id}">

    <header class="hsuforum-thread-header clearfix">
        <div class="hsuforum-thread-figure">
            <img class="userpicture img-circle" src="{$d->imagesrc}" alt="" />
        </div>

        <div class="hsuforum-thread-body">
            <p class="hsuforum-thread-byline">
                $byuser$group
                <br />
                <time datetime="$d->datetime" class="hsuforum-thread-pubdate">$d->created</time>
                $unread
            </p>

            <h4 id="thread_title_{$d->id}" role="heading" aria-level="4" class="hsuforum-thread-title">
                <a class="hsuforum-thread-view" href="$d->viewurl">$d->subject</a>
            </h4>

            $meta
            
        </div>
    </header>
    <div class="hsuforum-thread-content" tabindex="0">
        $d->message
    </div>
    $d->tools
    $d->posts
    $d->replyform
    $d->nav
</article>
HTML;
    }


    
    /**
     * Return html for individual post
     *
     * 3 use cases:
     *  1. Standard post
     *  2. Reply to user
     *  3. Private reply to user
     *
     * @param array $p
     */    
    public function post_template($p) {
        $icon = "&#64;";
        $byuser = $p->fullname;
        if (!empty($p->userurl)) {
            $byuser = html_writer::link($p->userurl, $p->fullname);
        }
        $byline = get_string('postbyx', 'hsuforum', $byuser);

        if (!empty($p->parentfullname)) {
            $parent = $icon.$p->parentfullname;
            if (!empty($p->parentuserurl)) {
                $parent = html_writer::link($p->parentuserurl, $icon.$p->parentfullname);
            }
        }
        // Post is a reply.
        if($p->depth) {
            $byline = get_string('postbyxinreplytox', 'hsuforum', array(
                    'author' => $byuser,
                    'parent' => $parent
                ));
         }
        // Post is private reply.
        if (!empty($p->privatereply)) {
            
            $byline = get_string('postbyxinprivatereplytox', 'hsuforum', array(
                    'author' => $byuser,
                    'parent' => $parent
                ));
        }

        $author = s(strip_tags($p->fullname));
        $unread = '';
        if ($p->unread) {
            $unread = html_writer::tag('span', get_string('unread', 'hsuforum'), array('class' => 'hsuforum-unreadcount'));
        }
        
        return <<<HTML
<div class="hsuforum-post-wrapper hsuforum-post-target" id="p$p->id" data-postid="$p->id" data-discussionid="$p->discussionid" data-author="$author" data-ispost="true">
    <div class="hsuforum-post-figure">
        <img class="userpicture img-circle" src="{$p->imagesrc}" alt="">
    </div>

    <div class="hsuforum-post-body">
        $unread
        <h6 role="heading" aria-level="6" class="hsuforum-post-byline">
            $byline
        </h6>

        <div class="hsuforum-post-content">
            <strong class="hsuforum-post-title">$p->subject</strong>
            $p->message
        </div>
        <time datetime="$p->datetime" class="hsuforum-post-pubdate"><a href="$p->permalink" class="disable-router">$p->created</a></time>

        $p->tools
    </div>
</div>
HTML;
    }

    protected function get_post_user_url($cm, $postuser) {
        if (!$postuser->user_picture->link) {
            return null;
        } else if ($cm->course == SITEID) {
            return new moodle_url('/user/profile.php', array('id' => $postuser->id));
        }
        return new moodle_url('/user/view.php', array('id' => $postuser->id, 'course' => $cm->course));
    }

    protected function discussion_nav($cm, $discussion) {
        $dnav = $this->get_discussion_nav($cm);

        $output = html_writer::link(
            new moodle_url('/mod/hsuforum/view.php', array('id' => $cm->id, 'page' => $dnav->get_page($discussion->id))),
            get_string('closediscussion', 'hsuforum'),
            array('class' => 'hsuforum_thread_close')
        );

        $class = 'prev';
        if (!$dnav->get_prev_discussionid($discussion->id)) {
            $class .= ' hidden';
        }
        $output .= html_writer::link(
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => (int) $dnav->get_prev_discussionid($discussion->id))),
            get_string('prevdiscussion', 'hsuforum'),
            array('class' => $class)
        );

        $class = 'next';
        if (!$dnav->get_next_discussionid($discussion->id)) {
            $class .= ' hidden';
        }
        $output .= html_writer::link(
            new moodle_url('/mod/hsuforum/discuss.php', array('d' => (int) $dnav->get_next_discussionid($discussion->id))),
            get_string('nextdiscussion', 'hsuforum'),
            array('class' => $class)
        );

        return html_writer::tag('nav', $output, array('class' => 'hsuforum-thread-nav'));
    }

    protected function notification_area() {
        return html_writer::tag('div', '', array('class' => 'hsuforum-notification', 'aria-hidden' => 'true'));
    }

    /**
     * Create a region with all of the actions one can take on a post
     *
     * @param array $commands
     * @param string $classes
     * @return string
     */
    protected function toolbox(array $commands, $classes = '') {
        if (empty($commands)) {
            return '';
        }
        $items = array();
        $glue  = ' | ';

        if (array_key_exists('seeincontext', $commands)) {
            $items[] = $commands['seeincontext'];
        }
        if (array_key_exists('reply', $commands)) {
            $items[] = $commands['reply'];
        }
        if (array_key_exists('rating', $commands)) {
            $items[] = $commands['rating'];
        }
        if (array_key_exists('tracking', $commands)) {
            $items[] = html_writer::tag('div',
                implode($glue, $commands['tracking']),
                array('aria-label' => get_string('trackingoptions', 'hsuforum')));
        }
        $output = implode($glue, $items);
        if (array_key_exists('options', $commands)) {
            $output .= $this->yui_options_menu($commands['options']);
        }
        return html_writer::tag('div', $output, array(
            'role'       => 'region',
            'class'      => trim('hsuforum-tools '.$classes),
            'aria-label' => get_string('tools', 'hsuforum'),
        ));
    }

    /**
     * Generic list of actions one can take on a post
     *
     * @param object $cm
     * @param object $discussion
     * @param object $post
     * @param bool $canreply
     * @return array
     */
    public function toolbox_commands($cm, $discussion, $post, $canreply) {
        $tools = array();

        $commands = $this->post_get_commands($post, $discussion, $cm, $canreply);

        if (array_key_exists('reply', $commands)) {
            $tools['reply'] = $commands['reply'];
            unset($commands['reply']);
        }
        $rating = $this->post_rating($post);
        if (!empty($rating)) {
            $tools['rating'] = $rating;
        }
        unset($commands['parent']);

        $tracking = array();
        if ($post->id == $discussion->firstpost) {
            require_once(__DIR__.'/lib/discussion/subscribe.php');

            $subscribe = new hsuforum_lib_discussion_subscribe($cm->cache->forum, $cm->cache->context);
            if (!property_exists($discussion, 'subscriptionid')) {
                // Don't need actual ID, bool will do.
                $discussion->subscriptionid = $subscribe->is_subscribed($discussion->id);
            }
            $subscribelink = $this->discussion_subscribe_link($discussion, $subscribe);

            if (!empty($subscribelink)) {
                $tracking['subscribe'] = $subscribelink;
            }
        }
        $tracking = array_merge($tracking, $this->post_get_flags($post, $cm->cache->context));

        if (!empty($tracking)) {
            $tools['tracking'] = $tracking;
        }
        if (!empty($commands)) {
            $tools['options'] = $commands;
        }
        return $tools;
    }

    /**
     * Create a YUI menu out of a list if items (should be links)
     *
     * @param array $items
     * @param array $attributes
     * @return string
     */
    protected function yui_menu(array $items, array $attributes = array()) {
        if (array_key_exists('class', $attributes)) {
            $attributes['class'] = 'yuimenu '.$attributes['class'];
        } else {
            $attributes['class'] = 'yuimenu';
        }
        $output = '';
        foreach ($items as $item) {
            $output .= html_writer::tag('li', $item, array('class' => 'yuimenuitem'));
        }
        $output = html_writer::tag('ul', $output, array('class' => 'first-of-type'));
        $output = html_writer::tag('div', $output, array('class' => 'bd'));
        $output = html_writer::tag('div', $output, $attributes);

        return $output;
    }

    /**
     * Create a YUI options menu
     *
     * @param array $options
     * @return string
     */
    protected function yui_options_menu(array $options) {
        $id      = html_writer::random_id('options');
        $output  = html_writer::link('#', get_string('options', 'hsuforum'), array('class' => 'hsuforum-options disable-router', 'id' => $id));
        $output .= $this->yui_menu($options, array('class' => 'hsuforum-options-menu unprocessed', 'data-controller' => $id));

        return $output;
    }
}
