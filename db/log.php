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
 * Definition of log events
 *
 * @package    mod_hsuforum
 * @category   log
 * @copyright  2010 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables

$logs = array(
    array('module' => 'hsuforum', 'action' => 'add', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'update', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'add discussion', 'mtable'=>'hsuforum_discussions', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'add post', 'mtable'=>'hsuforum_posts', 'field'=>'subject'),
    array('module' => 'hsuforum', 'action' => 'update post', 'mtable'=>'hsuforum_posts', 'field'=>'subject'),
    array('module' => 'hsuforum', 'action' => 'user report', 'mtable'=>'user', 'field'=>$DB->sql_concat('firstname', "' '" , 'lastname')),
    array('module' => 'hsuforum', 'action' => 'move discussion', 'mtable'=>'hsuforum_discussions', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'view subscribers', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'view discussion', 'mtable'=>'hsuforum_discussions', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'view forum', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'subscribe', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'unsubscribe', 'mtable'=>'hsuforum', 'field'=>'name'),
    array('module' => 'hsuforum', 'action' => 'pin discussion', 'mtable' => 'hsuforum_discussions', 'field' => 'name'),
    array('module' => 'hsuforum', 'action' => 'unpin discussion', 'mtable' => 'hsuforum_discussions', 'field' => 'name'),
);
