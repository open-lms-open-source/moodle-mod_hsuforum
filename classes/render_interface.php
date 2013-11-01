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
 * Render Discussion Interface
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface render_interface {
    /**
     * Render a list of discussions
     *
     * @param \stdClass $cm The forum course module
     * @param array $discussions A list of discussion and discussion post pairs, EG: array(array($discussion, $post), ...)
     * @param array $options Display options and information, EG: total discussions, page number and discussions per page
     * @return string
     */
    public function discussions($cm, array $discussions, array $options);

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
    public function discussion_thread($cm, $discussion, $post, array $posts, $canreply = null);

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
    public function discussion($cm, $discussion, $post, array $posts = array(), $canreply = null);

    /**
     * Render a list of posts
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The discussion for the posts
     * @param \stdClass[] $posts The posts to render
     * @param bool $canreply
     * @return string
     */
    public function posts($cm, $discussion, $posts, $canreply = false);

    /**
     * Render a single post
     *
     * @param \stdClass $cm The forum course module
     * @param \stdClass $discussion The post's discussion
     * @param \stdClass $post The post to render
     * @param bool $canreply
     * @param null|object $parent Optional, parent post
     * @param array $commands Override default post commands
     * @return string
     */
    public function post($cm, $discussion, $post, $canreply = false, $parent = null, array $commands = array());
}