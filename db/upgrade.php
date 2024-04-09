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
 * Upgrade script for block_coupon
 *
 * File         upgrade.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade
 *
 * @param int $oldversion old (current) plugin version
 * @return boolean
 */
function xmldb_block_coupon_upgrade($oldversion) {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2016011000) {
        // Add activity completion table.
        $table = new xmldb_table('block_coupon_errors');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('couponid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('errortype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'couponid');
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, 'errortype');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'errormessage');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add INDEXES.
        $table->add_index('couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // We shall add indexes to link tables!
        $table = new xmldb_table('block_coupon_cohorts');
        $index = new xmldb_index('couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('cohortid', XMLDB_INDEX_NOTUNIQUE, ['cohortid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('block_coupon_groups');
        $index = new xmldb_index('couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('groupid', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('block_coupon_courses');
        $index = new xmldb_index('couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2016011000, 'coupon');

    }

    if ($oldversion < 2017050100) {
        // Add activity completion table.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('logoid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'submission_code');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('typ', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'logoid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Detect all coupon types and set them.
        // Set cohort types.
        $cids = $DB->get_fieldset_sql('SELECT DISTINCT couponid FROM {block_coupon_cohorts}');
        list($insql, $params) = $DB->get_in_or_equal($cids, SQL_PARAMS_QM, 'unused', true, 0);
        array_unshift($params, 'cohort');
        $DB->execute('UPDATE {block_coupon} SET typ = ? WHERE id '.$insql, $params);
        // Set course types.
        list($notinsql, $params) = $DB->get_in_or_equal($cids, SQL_PARAMS_QM, 'unused', false, 0);
        array_unshift($params, 'course');
        $DB->execute('UPDATE {block_coupon} SET typ = ? WHERE id '.$notinsql, $params);

        // Now IF we have a custom logo, please place into Moodle's Filesystem.
        $logofile = $CFG->dataroot.'/coupon_logos/couponlogo.png';
        if (file_exists($logofile)) {
            // Store.
            $content = file_get_contents($logofile);
            \block_coupon\logostorage::store_from_content('couponlogo.png', $content);
            // Delete original.
            unlink($logofile);
            // ANd remove dir.
            remove_dir(dirname($logofile));
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2017050100, 'coupon');

    }

    if ($oldversion < 2017050102) {
        // Add claimed bit.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('claimed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'typ');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set all that have userids claimed.
        $sql = 'UPDATE {block_coupon} SET claimed = 1 WHERE (userid IS NOT NULL OR userid = 0)';
        $DB->execute($sql);

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2017050102, 'coupon');

    }

    if ($oldversion < 2017050103) {
        // Transform enrolperiod column to contain seconds instead of days.
        $sql = 'UPDATE {block_coupon} SET enrolperiod = enrolperiod * 86400 WHERE enrolperiod <> 0';
        $DB->execute($sql);

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2017050103, 'coupon');

    }

    if ($oldversion < 2017052402) {
        // Add renderqrcode option bit.
        // This WILL set all existing coupons to the default value of 1 but alas.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('renderqrcode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'claimed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2017052402, 'coupon');

    }

    if ($oldversion < 2017092503) {
        // Add renderqrcode option bit.
        // This WILL set all existing coupons to the default value of 1 but alas.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('roleid', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'renderqrcode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2017092503, 'coupon');

    }

    if ($oldversion < 2018050301) {
        // Add request users/requests table.
        $table = new xmldb_table('block_coupon_rusers');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('configuration', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, 'userid');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'configuration');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add INDEXES.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('block_coupon_requests');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('configuration', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, 'userid');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'configuration');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add INDEXES.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2018050301, 'coupon');

    }

    if ($oldversion < 2018050302) {
        // Add batchid field to coupon table.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('batchid', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'roleid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2018050302, 'coupon');

    }

    if ($oldversion < 2018050303) {
        // Add batchid field to coupon table.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('timeclaimed', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'timeexpired');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Assume all coupons claimed on "timemodified".
        $DB->execute("UPDATE {block_coupon} SET timeclaimed = timemodified WHERE claimed = 1");

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2018050303, 'coupon');

    }
    if ($oldversion < 2019031804) {
        // Add batchid field to coupon table.
        $table = new xmldb_table('block_coupon_errors');
        $field = new xmldb_field('iserror', XMLDB_TYPE_INTEGER, '1', null, null, null, 1, 'errormessage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2019031804, 'coupon');

    }

    if ($oldversion < 2020010802) {
        // Add batchid field to coupon table.
        $table = new xmldb_table('block_coupon');
        $field = new xmldb_field('batchid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'roleid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2020010802, 'coupon');

    }

    if ($oldversion < 2020010805) {
        // Add INDICES field to coupon table. Can't believe I never saw this!
        $table = new xmldb_table('block_coupon');
        $index = new xmldb_index('idx-userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-ownerid', XMLDB_INDEX_NOTUNIQUE, ['ownerid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-logoid', XMLDB_INDEX_NOTUNIQUE, ['logoid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-claimed', XMLDB_INDEX_NOTUNIQUE, ['claimed']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-batchid', XMLDB_INDEX_NOTUNIQUE, ['batchid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-submission_code', XMLDB_INDEX_UNIQUE, ['submission_code']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2020010805, 'coupon');

    }

    if ($oldversion < 2020010815) {
        // Add some fields to coupon requests.
        $table = new xmldb_table('block_coupon_requests');
        $field = new xmldb_field('clientref', XMLDB_TYPE_CHAR, 100, null, null, null, null, 'configuration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('denied', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, '0', 'clientref');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('finalized', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, '0', 'denied');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('idx-denied', XMLDB_INDEX_NOTUNIQUE, ['denied']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('idx-finalized', XMLDB_INDEX_NOTUNIQUE, ['finalized']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2020010815, 'coupon');

    }

    if ($oldversion < 2020010816) {
        // Add course groupings table.
        $table = new xmldb_table('block_coupon_coursegroupings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'name');
        $table->add_field('maxamount', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'idnumber');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'maxamount');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add coupon/grouping link table.
        $table = new xmldb_table('block_coupon_groupings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('couponid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('coursegroupingid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'couponid');
        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        $table->add_index('idx-coursegroupingid', XMLDB_INDEX_NOTUNIQUE, ['coursegroupingid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add grouping/course link table.
        $table = new xmldb_table('block_coupon_cgcourses');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('coursegroupingid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'coursegroupingid');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'courseid');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-coursegroupingid', XMLDB_INDEX_NOTUNIQUE, ['coursegroupingid']);
        $table->add_index('idx-courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Add coupon/course link table (tracks users choice).
        $table = new xmldb_table('block_coupon_cgucourses');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('couponid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'couponid');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'courseid');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        $table->add_index('idx-courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Install default of new setting.
        set_config('groupingselectactiveonly', 0, 'block_coupon');

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2020010816, 'coupon');
    }

    if ($oldversion < 2023110300) {
        // Add activity link table.
        $table = new xmldb_table('block_coupon_activities');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('couponid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'couponid');
        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-couponid', XMLDB_INDEX_NOTUNIQUE, ['couponid']);
        $table->add_index('idx-cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add templates table.
        $table = new xmldb_table('block_coupon_templates');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'name');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'contextid');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-contextid', XMLDB_INDEX_NOTUNIQUE, ['contextid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add template pages table.
        $table = new xmldb_table('block_coupon_pages');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('width', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'templateid');
        $table->add_field('height', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'width');
        $table->add_field('leftmargin', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'height');
        $table->add_field('rightmargin', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '0', 'leftmargin');
        $table->add_field('sequence', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'rightmargin');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'sequence');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-templateid', XMLDB_INDEX_NOTUNIQUE, ['templateid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add page elements table.
        $table = new xmldb_table('block_coupon_elements');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('pageid', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'pageid');
        $table->add_field('element', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL, null, null, 'name');
        $table->add_field('data', XMLDB_TYPE_TEXT, 'long', null, null, null, null, 'element');
        $table->add_field('font', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'data');
        $table->add_field('fontsize', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'font');
        $table->add_field('colour', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'fontsize');
        $table->add_field('posx', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'colour');
        $table->add_field('posy', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'posx');
        $table->add_field('width', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'posy');
        $table->add_field('refpoint', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'width');
        $table->add_field('sequence', XMLDB_TYPE_INTEGER, '18', null, null, null, null, 'refpoint');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'sequence');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add KEYS.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-pageid', XMLDB_INDEX_NOTUNIQUE, ['pageid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add mail templates table.
        $table = new xmldb_table('block_coupon_mailtemplates');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null, 'name');
        $table->add_field('body', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, 'subject');
        $table->add_field('bodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'body');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'bodyformat');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'usercreated');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'timecreated');
        // Add keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // Add indices.
        $table->add_index('idx-usercreated', XMLDB_INDEX_NOTUNIQUE, ['usercreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2023110300, 'coupon');
    }

    if ($oldversion < 2024010200) {
        // Set defaults for new settings.
        set_config('defaultgeneratecodesonly', 0, 'block_coupon');
        set_config('defaultenrolmentperiod', 0, 'block_coupon');

        // Block_coupon savepoint reached.
        upgrade_block_savepoint(true, 2024010200, 'coupon');
    }

    return true;
}
