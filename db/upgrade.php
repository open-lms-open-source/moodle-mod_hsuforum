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
 * This file keeps track of upgrades to
 * the forum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_hsuforum
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Copyright (c) 2012 Open LMS (https://www.openlms.net)
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_hsuforum_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2014051201) {

        // Incorrect values that need to be replaced.
        $replacements = array(
            11 => 20,
            12 => 50,
            13 => 100,
        );

        // Run the replacements.
        foreach ($replacements as $old => $new) {
            $DB->set_field('hsuforum', 'maxattachments', $new, array('maxattachments' => $old));
        }

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2014051201, 'hsuforum');
    }

    if ($oldversion < 2014051203) {
        // Find records with multiple userid/postid combinations and find the lowest ID.
        // Later we will remove all those which don't match this ID.
        $sql = "
            SELECT MIN(id) as lowid, userid, postid
            FROM {hsuforum_read}
            GROUP BY userid, postid
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('hsuforum_read', 'userid = ? AND postid = ? AND id <> ?', array(
                    $row->userid,
                    $row->postid,
                    $row->lowid,
                ));
            }
        }
        $duplicatedrows->close();

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2014051203, 'hsuforum');
    }

    if ($oldversion < 2014092400) {

        // Define fields to be added to hsuforum table.
        $table = new xmldb_table('hsuforum');
        $fields = array();
        $fields[] = new xmldb_field('showsubstantive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'displaywordcount');
        $fields[] = new xmldb_field('showbookmark', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'showsubstantive');

        // Go through each field and add if it doesn't already exist.
        foreach ($fields as $field){
            // Conditionally launch add field.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2014092400, 'hsuforum');
    }

    if ($oldversion < 2014093000) {
        // Define fields to be added to hsuforum table.
        $table = new xmldb_table('hsuforum');
        $field = new xmldb_field('allowprivatereplies', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'showbookmark');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2014093000, 'hsuforum');
    }

    if ($oldversion < 2014093001) {
        // Set default settings for existing forums.
        $DB->execute("
                UPDATE {hsuforum}
                   SET allowprivatereplies = 1,
                       showsubstantive = 1,
                       showbookmark = 1

        ");

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2014093001, 'hsuforum');
    }


    // Convert global configs to plugin configs
    if ($oldversion < 2014100600) {
        $configs = array(
            'allowforcedreadtracking',
            'cleanreadtime',
            'digestmailtime',
            'digestmailtimelast',
            'disablebookmark',
            'disablesubstantive',
            'displaymode',
            'enablerssfeeds',
            'enabletimedposts',
            'lastreadclean',
            'longpost',
            'manydiscussions',
            'maxattachments',
            'maxbytes',
            'oldpostdays',
            'replytouser',
            'shortpost',
            'showbookmark',
            'showsubstantive',
            'trackingtype',
            'trackreadposts',
            'usermarksread',
        );

        // Migrate legacy configs to plugin configs.
        foreach ($configs as $config) {
            $oldvar = 'hsuforum_'.$config;
            if (isset($CFG->$oldvar)){
                // Set new config variable up based on legacy config.
                set_config($config, $CFG->$oldvar, 'hsuforum');
                // Delete legacy config.
                unset_config($oldvar);
            }
        }

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2014100600, 'hsuforum');

    }

    if ($oldversion < 2014121700) {
        // Define fields to be added to hsuforum table.
        $table = new xmldb_table('hsuforum');
        $field = new xmldb_field('showrecent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'displaywordcount');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2014121700, 'hsuforum');
    }


    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016012600) {
        // Groupid = 0 is never valid.
        $DB->set_field('hsuforum_discussions', 'groupid', -1, array('groupid' => 0));

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2016012600, 'hsuforum');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016052301) {

        // Add support for pinned discussions.
        $table = new xmldb_table('hsuforum_discussions');
        $field = new xmldb_field('pinned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timeend');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2016052301, 'hsuforum');
    }
    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016121302) {

        // Define field lockdiscussionafter to be added to forum.
        $table = new xmldb_table('hsuforum');
        $field = new xmldb_field('lockdiscussionafter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'displaywordcount');

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2016121302, 'hsuforum');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017120700) {

        // Remove duplicate entries from hsuforum_subscriptions.
        // Find records with multiple userid/forum combinations and find the highest ID.
        // Later we will remove all those entries.
        $sql = "
            SELECT MIN(id) as minid, userid, forum
            FROM {hsuforum_subscriptions}
            GROUP BY userid, forum
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('hsuforum_subscriptions',
                    'userid = :userid AND forum = :forum AND id <> :minid', (array)$row);
            }
        }
        $duplicatedrows->close();

        // Define key useridforum (primary) to be added to hsuforum_subscriptions.
        $table = new xmldb_table('hsuforum_subscriptions');
        $key = new xmldb_key('useridforum', XMLDB_KEY_UNIQUE, array('userid', 'forum'));

        // Launch add key useridforum.
        $dbman->add_key($table, $key);

        // Forum savepoint reached.
        upgrade_mod_savepoint(true, 2017120700, 'hsuforum');
    }

    if ($oldversion < 2017120802) {

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability(
            'mod/hsuforum:revealpost',
            CAP_ALLOW,
            $studentroleid,
            context_system::instance(),
            false // Don't update if local setting already exists.
        );

        // Open Forum savepoint reached.
        upgrade_mod_savepoint(true, 2017120802, 'hsuforum');
    }

    if ($oldversion < 2017120803) {

        // Define field trackingtype to be dropped from hsuforum.
        $table = new xmldb_table('hsuforum');
        $field = new xmldb_field('trackingtype');

        // Conditionally launch drop field trackingtype.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Hsuforum savepoint reached.
        upgrade_mod_savepoint(true, 2017120803, 'hsuforum');
    }

    if ($oldversion < 2017120804) {

        // Define field deleted to be added to hsuforum_posts.
        $table = new xmldb_table('hsuforum_posts');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'privatereply');

        // Conditionally launch add field deleted.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Open Forum savepoint reached.
        upgrade_mod_savepoint(true, 2017120804, 'hsuforum');
    }

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2018120301) {
        // Adding index to improve discussions retrieval performance.
        $table = new xmldb_table('hsuforum_discussions');
        $index = new xmldb_index('hsudisc_forpontimid_ix', XMLDB_INDEX_NOTUNIQUE,
            ['forum', 'pinned', 'timemodified', 'id',]);
        // Conditionally launch add index modulename.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Open Forum savepoint reached.
        upgrade_mod_savepoint(true, 2018120301, 'hsuforum');
    }

    if ($oldversion < 2024091701) {
        $table = new xmldb_table('hsuforum');
        $field1 = new xmldb_field('duedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'introformat');
        $field2 = new xmldb_field('cutoffdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'duedate');
        $field3 = new xmldb_field('grade_forum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'scale');
        $field4 = new xmldb_field('grade_forum_notify', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'grade_forum');
        $field5 = new xmldb_field('trackingtype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'forcesubscribe');

        // Add whichever is missing.
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }
        if (!$dbman->field_exists($table, $field4)) {
            $dbman->add_field($table, $field4);
        }
        if (!$dbman->field_exists($table, $field5)) {
            $dbman->add_field($table, $field5);
        }

        $table = new xmldb_table('hsuforum_discussions');
        $field = new xmldb_field('timelocked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'pinned');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('hsuforum_posts');
        $field1 = new xmldb_field('privatereplyto', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'deleted');
        $field2 = new xmldb_field('wordcount', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'privatereplyto');
        $field3 = new xmldb_field('charcount', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'wordcount');

        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }
        if (!$dbman->field_exists($table, $field3)) {
            $dbman->add_field($table, $field3);
        }

        $table = new xmldb_table('hsuforum_discussion_subs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('forum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'forum');
            $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');
            $table->add_field('preference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'discussion');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('forum', XMLDB_KEY_FOREIGN, ['forum'], 'hsuforum', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('discussion', XMLDB_KEY_FOREIGN, ['discussion'], 'hsuforum_discussions', ['id']);
            $table->add_key('user_discussions', XMLDB_KEY_UNIQUE, ['userid', 'discussion']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('hsuforum_grades');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('forum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            $table->add_field('itemnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'forum');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'itemnumber');
            $table->add_field('grade', XMLDB_TYPE_NUMBER, '10,5', null, null, null, null, 'itemnumber');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'grade');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timecreated');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('forum', XMLDB_KEY_FOREIGN, ['forum'], 'hsuforum', ['id']);

            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('forumusergrade', XMLDB_INDEX_UNIQUE, ['forum', 'itemnumber', 'userid']);

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2024091701, 'hsuforum');
    }

    return true;
}


