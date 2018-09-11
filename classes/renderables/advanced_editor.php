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
 * Advanced editor renderable.
 * @package    mod
 * @subpackage hsuforum
 * @author    gthomas2
 * @copyright Copyright (c) 2015 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\renderables;

defined('MOODLE_INTERNAL') || die();

class advanced_editor implements \renderable {

    /**
     * @var \context_module
     */
    protected $forumcontext;

    /**
     * @var int
     */
    protected $maxbytes;

    /**
     * @param \context_module $forumcontext
     * @param int $maxbytes
     */
    public function __construct(\context_module $forumcontext, $maxbytes = 0) {
        $this->forumcontext = $forumcontext;
        $this->maxbytes = $maxbytes;
    }

    /**
     * Get data
     *
     * @return object
     */
    public function get_data($draftid = 0){
        global $CFG;

        require_once($CFG->dirroot.'/repository/lib.php');
        // Only output editor if preferred editor is Atto - tiny mce not supported yet.
        editors_head_setup();

        $options = ['context' => $this->forumcontext, 'maxbytes' => $this->maxbytes];

        $editor = editors_get_preferred_editor(FORMAT_HTML);
        if ($draftid === 0) {
            $draftitemid = file_get_unused_draft_itemid();

        } else {
            $draftitemid = $draftid;
        }

        $args = new \stdClass();
        // need these three to filter repositories list
        $args->accepted_types = array('image');
        $args->return_types = (FILE_INTERNAL | FILE_EXTERNAL);
        $args->context = $options['context'];
        $args->env = 'filepicker';
        // advimage plugin
        $image_options = initialise_filepicker($args);
        $image_options->context = $options['context'];
        $image_options->client_id = uniqid();
        $image_options->maxbytes = $options['maxbytes'];
        $image_options->env = 'editor';
        $image_options->itemid = $draftitemid;

        // moodlemedia plugin
        $args->accepted_types = array('video', 'audio');
        $media_options = initialise_filepicker($args);
        $media_options->context = $options['context'];
        $media_options->client_id = uniqid();
        $media_options->maxbytes  = $options['maxbytes'];
        $media_options->env = 'editor';
        $media_options->itemid = $draftitemid;

        // advlink plugin
        $args->accepted_types = '*';
        $link_options = initialise_filepicker($args);
        $link_options->context = $options['context'];
        $link_options->client_id = uniqid();
        $link_options->maxbytes  = $options['maxbytes'];
        $link_options->env = 'editor';
        $link_options->itemid = $draftitemid;

        $fpoptions['image'] = $image_options;
        $fpoptions['media'] = $media_options;
        $fpoptions['link'] = $link_options;

        return (object) [
            'editor' => $editor,
            'options' => $options,
            'fpoptions' => $fpoptions,
            'draftitemid' => $draftitemid
        ];
    }
}
