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
 * Post services
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_service {
    /**
     * @var \mod_hsuforum_renderer
     */
    protected $renderer;

    /**
     * Lazy load renderer
     *
     * @return \mod_hsuforum_renderer|\renderer_base
     */
    protected function get_renderer() {
        global $PAGE;

        if (!$this->renderer instanceof \mod_hsuforum_renderer) {
            $this->renderer = $PAGE->get_renderer('mod_hsuforum');
        }
        return $this->renderer;
    }

    /**
     * Same function as core, however we need to add files into the existing draft area!
     * Initialise a draft file area from a real one by copying the files. A draft
     * area will be created if one does not already exist. Normally you should
     * get $draftitemid by calling file_get_submitted_draft_itemid('elementname');
     *
     * @category files
     * @global stdClass $CFG
     * @global stdClass $USER
     * @param int $draftitemid the id of the draft area to use, or 0 to create a new one, in which case this parameter is updated.
     * @param int $contextid This parameter and the next two identify the file area to copy files from.
     * @param string $component
     * @param string $filearea helps indentify the file area.
     * @param int $itemid helps identify the file area. Can be null if there are no files yet.
     * @param array $options text and file options ('subdirs'=>false, 'forcehttps'=>false)
     * @param string $text some html content that needs to have embedded links rewritten to point to the draft area.
     * @return string|null returns string if $text was passed in, the rewritten $text is returned. Otherwise NULL.
     */
    protected function file_prepare_draft_area(&$draftitemid, $contextid, $component, $filearea, $itemid, array $options=null, $text=null) {
        global $CFG, $USER, $CFG, $DB;

        $options = (array)$options;
        if (!isset($options['subdirs'])) {
            $options['subdirs'] = false;
        }
        if (!isset($options['forcehttps'])) {
            $options['forcehttps'] = false;
        }

        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();

        if (empty($draftitemid)) {
            // create a new area and copy existing files into
            $draftitemid = file_get_unused_draft_itemid();
        }
        $file_record = array('contextid'=>$usercontext->id, 'component'=>'user', 'filearea'=>'draft', 'itemid'=>$draftitemid);
        if (!is_null($itemid) and $files = $fs->get_area_files($contextid, $component, $filearea, $itemid)) {
            foreach ($files as $file) {
                if ($file->is_directory() and $file->get_filepath() === '/') {
                    // we need a way to mark the age of each draft area,
                    // by not copying the root dir we force it to be created automatically with current timestamp
                    continue;
                }
                if (!$options['subdirs'] and ($file->is_directory() or $file->get_filepath() !== '/')) {
                    continue;
                }

                // We are adding to an already existing draft area so we need to make sure we don't double add draft files!
                $checkfile = array_merge($file_record, ['filename' => $file->get_filename()]);
                $draftexists = $DB->get_record('files', $checkfile);
                if ($draftexists) {
                    continue;
                }
                $draftfile = $fs->create_file_from_storedfile($file_record, $file);
                // XXX: This is a hack for file manager (MDL-28666)
                // File manager needs to know the original file information before copying
                // to draft area, so we append these information in mdl_files.source field
                // {@link file_storage::search_references()}
                // {@link file_storage::search_references_count()}
                $sourcefield = $file->get_source();
                $newsourcefield = new \stdClass;
                $newsourcefield->source = $sourcefield;
                $original = new \stdClass;
                $original->contextid = $contextid;
                $original->component = $component;
                $original->filearea  = $filearea;
                $original->itemid    = $itemid;
                $original->filename  = $file->get_filename();
                $original->filepath  = $file->get_filepath();
                $newsourcefield->original = \file_storage::pack_reference($original);
                $draftfile->set_source(serialize($newsourcefield));
                // End of file manager hack
            }
        }
        if (!is_null($text)) {
            // at this point there should not be any draftfile links yet,
            // because this is a new text from database that should still contain the @@pluginfile@@ links
            // this happens when developers forget to post process the text
            $text = str_replace("\"$CFG->httpswwwroot/draftfile.php", "\"$CFG->httpswwwroot/brokenfile.php#", $text);
        }


        if (is_null($text)) {
            return null;
        }

        // relink embedded files - editor can not handle @@PLUGINFILE@@ !
        return file_rewrite_pluginfile_urls($text, 'draftfile.php', $usercontext->id, 'user', 'draft', $draftitemid, $options);
    }

    public function prepare_message_for_edit($cm, $post, $draftid) {

        $this->append_edited_by($post);

        $context = \context_module::instance($cm->id);
        $post    = trusttext_pre_edit($post, 'message', $context);
        $message = $this->file_prepare_draft_area($draftid, $context->id, 'mod_hsuforum', 'post',
            $post->id, \mod_hsuforum_post_form::editor_options($context, $post->id), $post->message);

        return array($message, $draftid);
    }

    /**
     * When editing a post, append editing information to the message
     *
     * @param object $post
     */
    protected function append_edited_by($post) {
        global $CFG, $USER, $COURSE;

        if ($USER->id != $post->userid) { // Not the original author, so add a message to the end
            $data       = new \stdClass();
            $data->date = userdate($post->modified);
            if ($post->messageformat == FORMAT_HTML) {
                $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$COURSE->id.'">'.
                    fullname($USER).'</a>';
                $post->message .= '<p><span class="edited">('.get_string('editedby', 'hsuforum', $data).')</span></p>';
            } else {
                $data->name = fullname($USER);
                $post->message .= "\n\n(".get_string('editedby', 'hsuforum', $data).')';
            }
            unset($data);
        }
    }

    /**
     * Create the edit form for a post
     *
     * @param object $cm
     * @param object $post
     * @return string
     */
    public function edit_post_form($cm, $post, $draftid) {
        list($message, $itemid) = $this->prepare_message_for_edit($cm, $post, $draftid);

        return $this->get_renderer()->simple_edit_post($cm, true, $post->id, array(
            'subject' => $post->subject,
            'message' => $message,
            'privatereply' => $post->privatereply,
            'reveal' => $post->reveal,
            'itemid'  => $itemid,
        ));
    }

    /**
     * Create an edit form for a discussion
     *
     * @param object $cm
     * @param object $discussion
     * @param object $post
     * @param integer $draftid
     * @return string
     */
    public function edit_discussion_form($cm, $discussion, $post, $draftid) {
        list($message, $itemid) = $this->prepare_message_for_edit($cm, $post, $draftid);

        return $this->get_renderer()->simple_edit_discussion($cm, $post->id, array(
            'subject' => $post->subject,
            'message' => $message,
            'reveal' => $post->reveal,
            'groupid' => ($discussion->groupid == -1) ? 0 : $discussion->groupid,
            'itemid'  => $itemid,
            'timestart' => $discussion->timestart,
            'timeend' => $discussion->timeend
        ));
    }
}
