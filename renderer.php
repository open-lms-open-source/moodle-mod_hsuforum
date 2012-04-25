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
 */

/**
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the forum module.
 *
 * @package mod-hsuforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                array('yes'),
                array('no'),
            )
        );
    }

    /**
     * @param stdClass $post The post to add flags to
     * @param context_module $context
     * @return string
     * @author Mark Nielsen
     */
    public function post_flags($post, context_module $context) {
        global $OUTPUT, $PAGE;

        static $jsinit = false;

        if (!has_capability('mod/hsuforum:viewflags', $context)) {
            return '';
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
        foreach ($flaglib->get_flags() as $flag) {
            $class = 'hsuforum_flag';
            if ($flaglib->is_flagged($post->flags, $flag)) {
                $class .= ' hsuforum_flag_active';
            }
            $attributes = array('class' => $class);

            $icon = new pix_icon("flag/$flag", $flaglib->get_flag_name($flag), 'hsuforum', array('class' => 'iconsmall'));

            if ($canedit) {
                $url = new moodle_url('/mod/hsuforum/route.php', array(
                    'contextid'    => $context->id,
                    'action'       => 'flag',
                    'returnurl'    => $returnurl,
                    'postid'       => $post->id,
                    'flag'         => $flag,
                    'sesskey'      => sesskey()
                ));
                $flaghtml[] = $OUTPUT->action_icon($url, $icon, null, $attributes);
            } else {
                $flaghtml[] = html_writer::tag('span', $this->render($icon), $attributes);
            }
        }
        return html_writer::tag('div', implode('', $flaghtml), array('class' => 'hsuforum_flags'));
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
                'discussionid' => $discussion->discussion,
                'sesskey' => sesskey(),
                'returnurl' => $PAGE->url,
            ));

            $class = '';
            if (!empty($discussion->subscriptionid)) {
                $label = get_string('yes');
                $class = ' subscribed';
            } else {
                $label = get_string('no');
            }
            return html_writer::link($subscribeurl, $label, array('title' => get_string('changediscussionsubscription', 'hsuforum'), 'class' => 'hsuforum_discussion_subscribe'.$class));
        }
        return '';
    }

    /**
     * @param $cm
     * @param $discussion
     * @param $post
     * @return bool|object
     * @author Mark Nielsen
     */
    public function post_to_node($cm, $discussion, $post) {
        global $PAGE;

        hsuforum_cm_add_cache($cm);

        if (!hsuforum_user_can_see_post($cm->cache->forum, $discussion, $post, NULL, $cm)) {
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
        global $OUTPUT, $PAGE;

        $keyselect = $OUTPUT->single_select($PAGE->url, 'dsortkey', $sort->get_key_options_menu(), $sort->get_key(), array());
        $dirselect = $OUTPUT->single_select($PAGE->url, 'dsortdirection', $sort->get_direction_options_menu(), $sort->get_direction(), array());

        $output  = html_writer::tag('div', $keyselect, array('class' => 'hsuforum_discussion_sort_key'));
        $output .= html_writer::tag('div', $dirselect, array('class' => 'hsuforum_discussion_sort_direction'));

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
            $title = get_string('prevdiscussion', 'hsuforum', $shorten($prevdiscussion->name));
            $html  = html_writer::link(new moodle_url('/mod/hsuforum/discuss.php', array('d' => $prevdiscussion->id)), $title, array('title' => $title));
        } else {
            $html = '';
            $classes[] = 'hsuforum_no_prevtopic';
        }
        $output = html_writer::tag('div', $html, array('class' => 'hsuforumprevtopic'));

        if (!empty($nextdiscussion)) {
            $title = get_string('nextdiscussion', 'hsuforum', $shorten($nextdiscussion->name));
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
            $infos[] = html_writer::tag('div', get_string('subscribedx', 'hsuforum', $link), array('class' => 'subscribe'));
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
        $postsubject = $post->subject;
        if (empty($post->subjectnoformat)) {
            $postsubject = format_string($postsubject);
        }
        $postsubject .= $this->post_flags($post, $context);
        return html_writer::tag('div', $postsubject, array('class' => 'subject'));

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

        $postcontent  = format_text($post->message, $post->messageformat, $options, $cm->course);
        $postcontent .= html_writer::tag('div', $attachedimages, array('class' => 'attachedimages'));
        $postcontent  = html_writer::tag('div', $postcontent, array('class' => 'posting'));

        if (!empty($attachments)) {
            $postcontent = html_writer::tag('div', $attachments, array('class' => 'attachments')).$postcontent;
        }
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
            $output = html_writer::tag('div', $this->render($post->rating), array('class'=>'forum-post-rating'));
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
        global $CFG, $USER;

        hsuforum_cm_add_cache($cm);

        $discussionlink = new moodle_url('/mod/hsuforum/discuss.php', array('d'=> $post->discussion));
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
            $commands[] = array('url'=>$url, 'text'=>$cm->cache->str->parent);
        }

        // Hack for allow to edit news posts those are not displayed yet until they are displayed
        $age = time() - $post->created;
        if (!$post->parent && $cm->cache->forum->type == 'news' && $discussion->timestart > time()) {
            $age = 0;
        }
        if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/hsuforum:editanypost']) {
            $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('edit'=>$post->id)), 'text'=>$cm->cache->str->edit);
        }

        if ($cm->cache->caps['mod/hsuforum:splitdiscussions'] && $post->parent && $cm->cache->forum->type != 'single') {
            $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('prune'=>$post->id)), 'text'=>$cm->cache->str->prune, 'title'=>$cm->cache->str->pruneheading);
        }

        if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/hsuforum:deleteownpost']) || $cm->cache->caps['mod/hsuforum:deleteanypost']) {
            $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('delete'=>$post->id)), 'text'=>$cm->cache->str->delete);
        }

        if (!property_exists($post, 'privatereply')) {
            throw new coding_exception('Must set post\'s privatereply property!');
        }
        if ($canreply and empty($post->privatereply)) {
            $commands[] = array('url'=>new moodle_url('/mod/hsuforum/post.php', array('reply'=>$post->id)), 'text'=>$cm->cache->str->reply);
        }

        if ($CFG->enableportfolios && ($cm->cache->caps['mod/hsuforum:exportpost'] || ($ownpost && $cm->cache->caps['mod/hsuforum:exportownpost']))) {
            require_once($CFG->libdir.'/portfoliolib.php');
            $button = new portfolio_add_button();
            $button->set_callback_options('hsuforum_portfolio_caller', array('postid' => $post->id), '/mod/hsuforum/locallib.php');
            list($attachments, $attachedimages) = hsuforum_print_attachments($post, $cm, 'separateimages');
            if (empty($attachments)) {
                $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
            } else {
                $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
            }
            $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            if (!empty($porfoliohtml)) {
                $commands[] = $porfoliohtml;
            }
        }
        $commandhtml = array();
        foreach ($commands as $command) {
            if (is_array($command)) {
                $commandhtml[] = html_writer::link($command['url'], $command['text']);
            } else {
                $commandhtml[] = $command;
            }
        }
        return html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));
    }

    /**
     * @param $userid
     * @param $cm
     * @return string
     * @author Mark Nielsen
     */
    public function user_posts_overview($userid, $cm) {
        global $PAGE;

        hsuforum_cm_add_cache($cm);

        require_once(__DIR__.'/lib/flag.php');

        $output    = '';
        $postcount = $discussioncount = $flagcount = 0;
        $flaglib   = new hsuforum_lib_flag();
        if ($posts = hsuforum_get_user_posts($cm->cache->forum->id, $userid, $cm->cache->context)) {
            $discussions = hsuforum_get_user_involved_discussions($cm->cache->forum->id, $userid);

            foreach ($discussions as $discussion) {
                if ($discussion->userid == $userid) {
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
                $output .= hsuforum_print_post($discussionpost, $discussion, $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, '');
                $output .= html_writer::start_tag('div', array('class' => 'indent'));
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
                        $output .= hsuforum_print_post($post, $discussion, $cm->cache->forum, $cm, $cm->cache->course, false, false, false, '', '', true, false, false, true, $command);
                    }
                }
                $output .= html_writer::end_tag('div');
            }
        }
        if (!empty($output)) {
            $PAGE->requires->js_init_call('M.mod_hsuforum.init_post_in_context', null, false, $this->get_js_module());

            $counts = array(
                get_string('totalpostsanddiscussions', 'hsuforum', ($discussioncount+$postcount)),
                get_string('totaldiscussions', 'hsuforum', $discussioncount),
                get_string('totalposts', 'hsuforum', $postcount),
                get_string('totalsubstantive', 'hsuforum', $flagcount),
            );
            if ($grade = hsuforum_get_user_formatted_rating_grade($cm->cache->forum, $userid)) {
                $counts[] = get_string('totalrating', 'hsuforum', $grade);
            }
            $countshtml = '';
            foreach ($counts as $count) {
                $countshtml .= html_writer::tag('div', $count, array('class' => 'hsuforum_count'));
            }
            $output = html_writer::tag('div', $countshtml, array('class' => 'hsuforum_counts')).$output;
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

        /// find out current groups mode
        groups_print_activity_menu($cm, $PAGE->url);
        groups_get_activity_group($cm);
        groups_get_activity_groupmode($cm);

        // Unset session
        hsuforum_lib_discussion_nav::set_to_session();

        $dsort = hsuforum_lib_discussion_sort::get_from_session($forum, $context);
        if ($forum->type != 'single') {
            $dsort->set_key(optional_param('dsortkey', $dsort->get_key(), PARAM_ALPHA));
            $dsort->set_direction(optional_param('dsortdirection', $dsort->get_direction(), PARAM_ALPHA));
            hsuforum_lib_discussion_sort::set_to_session($dsort);
            echo $this->discussion_sorting($dsort);
        }

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
                $displaymode = get_user_preferences("hsuforum_displaymode", $CFG->hsuforum_displaymode);
                hsuforum_print_mode_form($forum->id, $displaymode, $forum);
            }
        }

        if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
            $a = new stdClass();
            $a->blockafter = $forum->blockafter;
            $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
            echo $OUTPUT->notification(get_string('thisforumisthrottled','hsuforum',$a));
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
                echo '<br />';
                if (!empty($showall)) {
                    hsuforum_print_latest_discussions($course, $forum, 0, 'header', $dsort->get_sort_sql(), -1, -1, -1, 0, $cm);
                } else {
                    hsuforum_print_latest_discussions($course, $forum, -1, 'header', $dsort->get_sort_sql(), -1, -1, $page, $CFG->hsuforum_manydiscussions, $cm);
                }


                break;
        }
    }
}
