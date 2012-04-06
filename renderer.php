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
     * @param object $forum
     * @param object $course
     * @return string
     */
    public function subscriber_overview($users, $forum , $course) {
        $output = '';
        if (!$users || !is_array($users) || count($users)===0) {
            $output .= $this->output->heading(get_string("nosubscribers", "hsuforum"));
        } else {
            $output .= $this->output->heading(get_string("subscribersto","hsuforum", "'".format_string($forum->name)."'"));
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
                'io-base',
                'json',
                'yui2-treeview',
            ),
            'strings' => array(
                array('jsondecodeerror', 'hsuforum'),
                array('ajaxrequesterror', 'hsuforum'),
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
     * @param $cm
     * @param $forum
     * @param $discussion
     * @param $post
     * @param $forumtracked
     * @return bool|object
     * @author Mark Nielsen
     */
    public function post_to_node($cm, $forum, $discussion, $post, $forumtracked) {
        global $CFG, $PAGE;

        if (!hsuforum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
            return false;
        }
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $PAGE->context);
        $displaymode = get_user_preferences('hsuforum_displaymode', $CFG->hsuforum_displaymode);

        $postuser = new stdClass;
        $postuser->id        = $post->userid;
        $postuser->firstname = $post->firstname;
        $postuser->lastname  = $post->lastname;
        $postuser = hsuforum_anonymize_user($postuser, $forum, $post);

        $by = new stdClass();
        $by->name = fullname($postuser, $canviewfullnames);
        $by->date = userdate($post->modified);

        $class = '';
        if ($forumtracked) {
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
        $html = "<span class=\"$class\">".
                html_writer::link($url, format_string($post->subject,true)).'&nbsp;'.
                get_string("bynameondate", "hsuforum", $by).
                $PAGE->get_renderer('mod_hsuforum')->post_flags($post, $PAGE->context, $discussion).
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
            $node->id = $discussion->id;
        }
        if (!empty($post->children)) {
            foreach ($post->children as $childpost) {
                if ($childnode = $this->post_to_node($cm, $forum, $discussion, $childpost, $forumtracked)) {
                    $node->children[] = $childnode;
                }
            }
        }
        return $node;
    }

    /**
     * @param array $nodes
     * @author Mark Nielsen
     * @return string
     */
    public function discussion_nodes(array $nodes) {
        global $OUTPUT, $PAGE;

        $output = '';
        if (!empty($nodes)) {
            $id  = html_writer::random_id('hsuforum_treeview');
            $url = new moodle_url('/mod/hsuforum/route.php', array('contextid' => $PAGE->context->id, 'action' => 'postnodes'));

            $PAGE->requires->js_init_call('M.mod_hsuforum.init_treeview', array($id, $url->out(false), $nodes), false, $this->get_js_module());
            $output .= html_writer::tag('noscript', $OUTPUT->notification(get_string('javascriptdisableddisplayformat', 'hsuforum')));
            $output .= html_writer::tag('div', '', array('id' => $id, 'class' => 'hsuforum_treeview'));
        }
        return $output;
    }
}
