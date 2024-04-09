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
 * Helper
 *
 * File         helper.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon;

/**
 * block_coupon\helper
 *
 * Helper class for various functionality
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * __construct() HIDE: WE'RE STATIC
     */
    protected function __construct() {
        // Static's only please!
    }

    /**
     * Collect all courses connected to the provided cohort ID
     *
     * @param int $cohortid cohortid
     * @return bool false if no courses are connected or an array of course records
     */
    final public static function get_courses_by_cohort($cohortid) {
        global $DB;

        $sql = "SELECT c.id, c.fullname FROM {enrol} e
            LEFT JOIN {course} c ON e.courseid = c.id
            WHERE customint1 = ?
            AND e.enrol = 'cohort' AND c.visible = 1 AND c.id != 1
            ORDER BY c.fullname ASC";
        $cohortcourses = $DB->get_records_sql($sql, [$cohortid]);

        return (count($cohortcourses) > 0) ? $cohortcourses : false;
    }

    /**
     * Get a list of courses that have NOT been enabled for cohort enrolment for a given cohort.
     *
     * @param int $cohortid
     * @param boolean $idsonly if true, only returns list of IDs
     * @return array
     */
    final public static function get_unconnected_cohort_courses($cohortid, $idsonly = false) {
        global $DB;

        $sql = "SELECT c.id, c.fullname FROM {course} c
            WHERE c.id != 1 AND c.visible = 1
            AND c.id NOT IN (
                SELECT courseid FROM {enrol} e
                WHERE e.customint1 = ?
                AND e.enrol = 'cohort'
            )
            ORDER BY c.fullname ASC";
        $unconnectedcourses = $DB->get_records_sql($sql, [$cohortid]);

        if ($idsonly) {
            return (!empty($unconnectedcourses)) ? array_keys($unconnectedcourses) : false;
        } else {
            return (!empty($unconnectedcourses)) ? $unconnectedcourses : false;
        }
    }

    /**
     * Get a list of all cohorts
     *
     * @param string $fields the fields to get
     * @return array
     */
    final public static function get_cohorts($fields = 'id,name,idnumber') {
        global $DB;
        $cohorts = $DB->get_records('cohort', null, 'name ASC', $fields);
        return (!empty($cohorts)) ? $cohorts : false;
    }

    /**
     * Get a list of all visible courses
     *
     * @param string $fields the fields to get
     * @return array
     */
    final public static function get_visible_courses($fields = 'id,shortname,fullname,idnumber') {
        global $DB;
        $select = "id != 1 AND visible = 1";
        $courses = $DB->get_records_select('course', $select, null, 'fullname ASC', $fields);

        return (!empty($courses)) ? $courses : false;
    }

    /**
     * Get a list of coupons for a given owner.
     * If the owner is NULL or 0, this gets all coupons.
     *
     * @param int|null $ownerid
     * @return array
     */
    final public static function get_coupons_by_owner($ownerid = null) {
        global $DB;

        $params = [];
        $sql = "SELECT * FROM {block_coupon} WHERE userid IS NOT NULL";
        if (!empty($ownerid)) {
            $sql .= "AND ownerid = ?";
            $params[] = $ownerid;
        }
        $coupons = $DB->get_records_sql($sql);

        return (!empty($coupons)) ? $coupons : false;
    }

    /**
     * Gather all coupons that need sending out.
     *
     * @return array
     */
    final public static function get_coupons_to_send() {
        global $DB;
        $senddate = time();
        $sql = "SELECT * FROM {block_coupon} v
            WHERE senddate < ? AND issend = 0 AND for_user_email IS NOT NULL";
        $coupons = $DB->get_records_sql($sql, [$senddate], 0, 500);

        return $coupons;
    }

    /**
     * Checks if the cron has send all the coupons generated at specific time by specific owner.
     *
     * @param int $ownerid
     * @param int $timecreated
     * @return bool
     */
    final public static function has_sent_all_coupons($ownerid, $timecreated) {
        global $DB;
        $conditions = [
            'issend' => 0,
            'ownerid' => $ownerid,
            'timecreated' => $timecreated,
        ];
        return ($DB->count_records('block_coupon', $conditions) === 0);
    }

    /**
     * Claim a coupon
     *
     * @param string $code coupon submission code
     * @param int $foruserid user for which coupon is claimed. If not given: current user.
     */
    public static function claim_coupon($code, $foruserid = null) {
        global $CFG;
        $instance = coupon\typebase::get_type_instance($code);
        $instance->claim($foruserid);

        return (empty($instance->coupon->redirect_url)) ? $CFG->wwwroot . "/my" : $instance->coupon->redirect_url;
    }

    /**
     * Helper function to return dummy noreply user record.
     *
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param string $username
     * @param int $id
     *
     * @return stdClass
     */
    public static function get_dummy_user_record($email, $firstname, $lastname, $username = 'noreply', $id = -500) {
        $dummyuser = new \stdClass();
        $dummyuser->id = $id;
        $dummyuser->email = $email;
        $dummyuser->firstname = $firstname;
        $dummyuser->username = $username;
        $dummyuser->lastname = $lastname;
        $dummyuser->confirmed = 1;
        $dummyuser->suspended = 0;
        $dummyuser->deleted = 0;
        $dummyuser->picture = 0;
        $dummyuser->auth = 'manual';
        $dummyuser->firstnamephonetic = '';
        $dummyuser->lastnamephonetic = '';
        $dummyuser->middlename = '';
        $dummyuser->alternatename = '';
        $dummyuser->imagealt = '';
        return $dummyuser;
    }

    /**
     * Send confirmation email when the cron has send all the coupons
     *
     * @param int $ownerid
     * @param string $batchid
     * @param int $timecreated
     * @return bool
     */
    final public static function confirm_coupons_sent($ownerid, $batchid, $timecreated) {
        global $DB;

        $owner = $DB->get_record('user', ['id' => $ownerid]);
        $supportuser = \core_user::get_noreply_user();
        $a = new \stdClass();
        $a->timecreated = userdate($timecreated, get_string('strftimedate', 'langconfig'));
        $a->batchid = $batchid;
        $messagehtml = get_string("confirm_coupons_sent_body", 'block_coupon', $a);
        $messagetext = format_text_email($messagehtml, FORMAT_MOODLE);
        $subject = get_string('confirm_coupons_sent_subject', 'block_coupon');

        return static::do_email_to_user($owner, $supportuser, $subject, $messagetext, $messagehtml);
    }

    /**
     * Load the course completion info
     *
     * @param object $user User object from database
     * @param object $cinfo Course object from database
     */
    final public static function load_course_completioninfo($user, $cinfo) {
        global $DB, $CFG;
        static $cstatus, $completioninfo = [];

        require_once($CFG->dirroot . '/lib/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');
        require_once($CFG->dirroot . '/lib/completionlib.php');

        // Completion status 'cache' values (speed up, lass!).
        if ($cstatus === null) {
            $cstatus = [];
            $cstatus['started'] = get_string('report:status_started', 'block_coupon');
            $cstatus['notstarted'] = get_string('report:status_not_started', 'block_coupon');
            $cstatus['complete'] = get_string('report:status_completed', 'block_coupon');
        }

        $ci = new \stdClass();
        $ci->complete = false;
        $ci->str_status = $cstatus['notstarted'];
        $ci->date_started = '-';
        $ci->date_complete = '-';
        $ci->str_grade = '-';
        $ci->gradeinfo = null;

        if (!$DB->record_exists('course', ['id' => $cinfo->id])) {
            $ci->str_status = 'COURSE_DELETED';
            return $ci;
        }

        // Completion info 'cache' (speed up, lass!).
        if (!isset($completioninfo[$cinfo->id])) {
            $completioninfo[$cinfo->id] = new \completion_info($cinfo);
        }

        // Ok, fill out real data according to completion status/info.
        $com = $completioninfo[$cinfo->id];
        if ($com->is_tracked_user($user->id)) {
            // Do we have an enrolment for the course for this user.
            $sql = 'SELECT ue.* FROM {user_enrolments} ue
                    JOIN {enrol} e ON ue.enrolid=e.id
                    WHERE ue.userid = ? AND e.courseid = ?
                    ORDER BY timestart ASC, timecreated ASC';
            $records = $DB->get_records_sql($sql, [$user->id, $cinfo->id]);

            if (count($records) === 1) {
                $record = array_shift($records);
                $ci->time_started = (($record->timestart > 0) ? $record->timestart : $record->timecreated);
                $ci->date_started = date('d-m-Y H:i:s', $ci->time_started);
            } else {
                $started = 0;
                $created = 0;

                foreach ($records as $record) {
                    if ($record->timestart > 0) {
                        $started = ($started == 0) ? $record->timestart : min($record->timestart, $started);
                    }
                    $created = ($created == 0) ? $record->timecreated : min($record->timecreated, $created);
                }

                $ci->time_started = (($started > 0) ? $started : $created);
                $ci->date_started = date('d-m-Y H:i:s', ($started > 0) ? $started : $created);
            }

            if ($com->is_course_complete($user->id)) {
                // Fetch details for course completion.
                $ci->complete = true;
                $comcom = new \completion_completion(['userid' => $user->id, 'course' => $cinfo->id]);
                $ci->date_complete = date('d-m-Y H:i:s', $comcom->timecompleted);
                $ci->gradeinfo = grade_get_course_grade($user->id, $cinfo->id);
                if ($ci->gradeinfo !== false) {
                    $ci->str_grade = $ci->gradeinfo->str_grade;
                }
                $ci->str_status = $cstatus['complete'];
            } else {
                $ci->str_status = $cstatus['started'];
            }
        }

        return $ci;
    }

    /**
     * Format a datestring in short or long format
     *
     * @param int $time
     * @param boolean $inctime
     * @return string user date
     */
    final public static function render_date($time, $inctime = true) {
        return userdate($time, get_string($inctime ? 'report:dateformat' : 'report:dateformatymd', 'block_coupon'));
    }

    /**
     * Make sure editing mode is off and moodle doesn't use complete overview
     * @param moodle_url $redirecturl
     */
    public static function force_no_editing_mode($redirecturl = '') {
        global $USER, $PAGE;
        if (!empty($USER->editing)) {
            $USER->editing = 0;

            if (empty($redirecturl)) {
                $params = $PAGE->url->params();
                $redirecturl = new \moodle_url($PAGE->url, $params);
            }
            redirect($redirecturl);
        }
    }

    /**
     * Load recipients from a CSV string
     * @param string $recipientsstr
     * @param string $delimiter
     * @return boolean|\stdClass
     */
    final public static function get_recipients_from_csv($recipientsstr, $delimiter = ',') {

        $recipients = [];
        $count = 0;

        // Split up in rows.
        $expectedcolumns = ['e-mail', 'gender', 'name'];
        $recipientsstr = str_replace("\r", '', $recipientsstr);
        if (!$csvdata = str_getcsv($recipientsstr, "\n")) {
            return false;
        }
        // Split up in columns.
        foreach ($csvdata as &$row) {

            // Get the next row.
            $row = str_getcsv($row, $delimiter);

            // Check if we're looking at the first row.
            if ($count == 0) {

                $expectedrow = [];
                // Set the columns we'll need.
                foreach ($row as $key => &$column) {

                    $column = trim(strtolower($column));
                    if (!in_array($column, $expectedcolumns)) {
                        continue;
                    }

                    $expectedrow[$key] = $column;
                }
                // If we're missing columns.
                if (count($expectedcolumns) != count($expectedrow)) {
                    return false;
                }

                // Now set which columns we'll need to use when extracting the information.
                $namekey = array_search('name', $expectedrow);
                $emailkey = array_search('e-mail', $expectedrow);
                $genderkey = array_search('gender', $expectedrow);

                $count++;
                continue;
            }

            $recipient = new \stdClass();
            $recipient->name = trim($row[$namekey]);
            $recipient->email = trim($row[$emailkey]);
            $recipient->gender = trim($row[$genderkey]);

            $recipients[] = $recipient;
        }

        return $recipients;
    }

    /**
     * Validate given recipients
     * @param array $csvdata
     * @param string $delimiter
     * @return array|true true if valid, array or error messages if invalid
     */
    final public static function validate_coupon_recipients($csvdata, $delimiter) {

        $error = false;
        $maxcoupons = get_config('block_coupon', 'max_coupons');

        if (!$recipients = self::get_recipients_from_csv($csvdata, $delimiter)) {
            // Required columns aren't found in the csv.
            $error = get_string('error:recipients-columns-missing', 'block_coupon', 'e-mail,gender,name');
        } else {
            // No recipient rows were added to the csv.
            if (empty($recipients)) {
                $error = get_string('error:recipients-empty', 'block_coupon');
                // Check max of the file.
            } else if (count($recipients) > $maxcoupons) {
                $error = get_string('error:recipients-max-exceeded', 'block_coupon');
            } else {
                // Lets run through the file to check on email addresses.
                foreach ($recipients as $recipient) {
                    if (!filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                        $error = get_string('error:recipients-email-invalid', 'block_coupon', $recipient);
                    }
                }
            }
        }

        return ($error === false) ? true : $error;
    }

    /**
     * Get default assigned role to use with coupons.
     *
     * @return false|\stdClass
     */
    public static function get_default_coupon_role() {
        global $DB;
        $config = get_config('block_coupon');
        $role = $DB->get_record('role', ['id' => $config->defaultrole]);
        return $role;
    }

    /**
     * Get role menu.
     *
     * @param \context|null $context
     * @param boolean $addempty
     * @return false|\stdClass
     */
    public static function get_role_menu($context = null, $addempty = false) {
        $roleoptions = [];
        if ($roles = get_all_roles($context)) {
            $roleoptions = role_fix_names($roles, $context, ROLENAME_ORIGINAL, true);
        }
        if ($addempty) {
            $rs = ['' => get_string('select')];
            foreach ($roleoptions as $k => $v) {
                $rs[$k] = $v;
            }
            $roleoptions = $rs;
        }
        return $roleoptions;
    }

    /**
     * Get course connected to coupons
     *
     * @param \stdClass $coupon
     * @return array result, keys are courseids, values are course shortnames
     */
    public static function get_coupon_courses($coupon) {
        global $DB;
        $sqls = [];
        $params = [];
        $sqls[] = 'SELECT c.id,c.shortname FROM {course} c JOIN {block_coupon_courses} cc ON cc.courseid=c.id AND cc.couponid = ?';
        $params[] = $coupon->id;
        $sqls[] = 'SELECT c.id,c.shortname FROM {block_coupon_cohorts} cc
                JOIN {enrol} e ON (e.customint1=cc.cohortid AND e.enrol=?)
                JOIN {course} c ON e.courseid=c.id
                WHERE cc.couponid = ?';
        $params[] = 'cohort';
        $params[] = $coupon->id;
        $sql = 'SELECT * FROM ((' . implode(') UNION (', $sqls) . ')) x';
        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Get courses connected to all coupons
     *
     * @param boolean $includeempty whether or not to include an empty element
     * @return array result, keys are courseids, values are course shortnames
     */
    public static function get_coupon_course_menu($includeempty = true) {
        global $DB;
        $sqls = [];
        $params = [];
        $sqls[] = 'SELECT c.id,c.shortname FROM {course} c JOIN {block_coupon_courses} cc ON cc.courseid=c.id';
        $sqls[] = 'SELECT c.id,c.shortname FROM {block_coupon_cohorts} cc
                JOIN {enrol} e ON (e.customint1=cc.cohortid AND e.enrol=?)
                JOIN {course} c ON e.courseid=c.id
            ';
        $params[] = 'cohort';
        $sql = 'SELECT DISTINCT * FROM ((' . implode(') UNION (', $sqls) . ')) x';
        $rs = $DB->get_records_sql_menu($sql, $params);
        if ($includeempty) {
            $rs = [0 => '...'] + $rs;
        }
        return $rs;
    }

    /**
     * Get cohort connected to all coupons
     *
     * @param boolean $includeempty whether or not to include an empty element
     * @return array result, keys are cohort ids, values are cohort names
     */
    public static function get_coupon_cohort_menu($includeempty = true) {
        global $DB;
        $sql = 'SELECT DISTINCT c.id,c.name FROM {block_coupon_cohorts} cc
                JOIN {cohort} c ON cc.cohortid=c.id';
        $rs = $DB->get_records_sql_menu($sql);
        if ($includeempty) {
            $rs = [0 => '...'] + $rs;
        }
        return $rs;
    }

    /**
     * Get batchids connected to all coupons
     *
     * @param boolean $includeempty whether or not to include an empty element
     * @return array result, keys are batch ids, values are batch ids
     */
    public static function get_coupon_batch_menu($includeempty = true) {
        global $DB;
        $sql = 'SELECT batchid FROM {block_coupon} c ORDER BY batchid ASC';
        $rs = [];
        if ($includeempty) {
            $rs = [0 => '...'];
        }
        $ids = $DB->get_fieldset_sql($sql);
        foreach ($ids as $id) {
            $rs[$id] = $id;
        }
        return $rs;
    }

    /**
     * Cleanup coupons given the options
     * @param \stdClass $options
     * @param string $operator (SELECT, DELETE)
     * @param string $fields
     */
    public static function cleanup_coupons_query($options, $operator = 'SELECT', $fields = 'id') {
        global $DB;
        $options = (object)(array)$options;
        if (!isset($options->type)) {
            $options->type = 0; // All.
        }
        if (!isset($options->used)) {
            $options->used = 1; // Used only.
        }
        $params = [];
        $where = [];
        // Assemble query.
        // Owner.
        if (!empty($options->ownerid)) {
            $where[] = 'ownerid = :ownerid';
            $params['ownerid'] = $options->ownerid;
        }
        // Timing.
        if (!empty($options->timebefore)) {
            $where[] = 'timecreated < :timebefore';
            $params['timebefore'] = $options->timebefore;
        }
        if (!empty($options->timeafter)) {
            $where[] = 'timecreated > :timeafter';
            $params['timeafter'] = $options->timeafter;
        }
        // Usage.
        switch($options->used) {
            case 2: // Unused.
                $where[] = '(userid IS NULL or userid = 0 OR claimed = 0)';
                break;
            case 1: // Used.
                $where[] = '(userid IS NOT NULL AND userid <> 0 OR claimed = 1)';
                break;
            case 0:
            default:
                break;
        }
        // Removal query.
        if ($options->type == 1) {
            // Course coupons.
            $subselect = 'SELECT DISTINCT couponid FROM {block_coupon_courses}';
            if (!empty($options->course)) {
                list($insql, $inparams) = $DB->get_in_or_equal($options->course, SQL_PARAMS_NAMED, 'courseid', true, 0);
                $subselect .= ' WHERE courseid ' . $insql;
                $where[] = 'id IN ('.$subselect.')';
                $params += $inparams;
            }
        } else if ($options->type == 2) {
            // Cohort coupons.
            $subselect = 'SELECT DISTINCT couponid FROM {block_coupon_cohorts}';
            if (!empty($options->cohort)) {
                list($insql, $inparams) = $DB->get_in_or_equal($options->cohort, SQL_PARAMS_NAMED, 'cohortid', true, 0);
                $subselect .= ' WHERE cohortid ' . $insql;
                $where[] = 'id IN ('.$subselect.')';
                $params += $inparams;
            }
        } else if ($options->type == 3) {
            // Batch coupons.
            if (!empty($options->batchid)) {
                list($insql, $inparams) = $DB->get_in_or_equal($options->batchid, SQL_PARAMS_NAMED, 'batchid', true, 0);
                $where[] = 'batchid '.$insql;
                $params += $inparams;
            }
        }
        $sqlparts = [$operator, $fields, 'FROM {block_coupon}'];
        if (!empty($where)) {
            $sqlparts[] = 'WHERE ' . implode(' AND ', $where);
        }
        return [implode(' ', $sqlparts), $params];

    }

    /**
     * Cleanup coupons given the options
     * @param \stdClass $options
     */
    public static function cleanup_coupons($options) {
        global $DB;
        list($idquery, $idparams) = self::cleanup_coupons_query($options, 'SELECT', 'id');
        $couponids = $DB->get_fieldset_sql($idquery, $idparams);
        if (!empty($couponids)) {
            $DB->delete_records_list('block_coupon', 'id', $couponids);
            $DB->delete_records_list('block_coupon_courses', 'couponid', $couponids);
            $DB->delete_records_list('block_coupon_cohorts', 'couponid', $couponids);
            $DB->delete_records_list('block_coupon_groups', 'couponid', $couponids);
        }
        return count($couponids);
    }

    /**
     * Cleanup all invalid coupon links (aka clean up linked tables).
     */
    public static function cleanup_invalid_links() {
        global $DB;
        // Standard cleaning. Removes all invalid linkks to coupons.
        $DB->execute('DELETE FROM {block_coupon_courses} WHERE couponid NOT IN (SELECT id FROM {block_coupon})');
        $DB->execute('DELETE FROM {block_coupon_cohorts} WHERE couponid NOT IN (SELECT id FROM {block_coupon})');
        $DB->execute('DELETE FROM {block_coupon_groups} WHERE couponid NOT IN (SELECT id FROM {block_coupon})');
        $DB->execute('DELETE FROM {block_coupon_errors} WHERE couponid NOT IN (SELECT id FROM {block_coupon})');
    }

    /**
     * Count coupons given the options
     * @param \stdClass $options
     * @return int number of found coupons given the options
     */
    public static function count_cleanup_coupons($options) {
        global $DB;
        list($idquery, $idparams) = self::cleanup_coupons_query($options, 'SELECT', 'id');
        $couponids = $DB->get_fieldset_sql($idquery, $idparams);
        return count($couponids);
    }

    /**
     * Find first block instance id for block_coupon
     *
     * @return int
     */
    public static function find_block_instance_id() {
        global $DB;
        $recs = $DB->get_records('block_instances', ['blockname' => 'coupon']);
        if (empty($recs)) {
            return 0;
        }
        $rec = reset($recs);
        return $rec->id;
    }

    /**
     * Return list of usable TCPDF fonts.
     *
     * @return array
     */
    public static function get_font_list() {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $pdf = new \pdf();
        $families = $pdf->get_font_families();
        $result = [];
        foreach ($families as $family => $unused) {
            $result[$family] = $family;
        }
        return $result;
    }

    /**
     * Add selector for generator method to form
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_generator_method_options($mform) {
        // Determine which type of settings we'll use.
        $radioarray = [];
        $radioarray[] = & $mform->createElement('radio', 'showform', '',
                get_string('showform-amount', 'block_coupon'), 'amount', ['onchange' => 'showHide(this.value)']);
        $radioarray[] = & $mform->createElement('radio', 'showform', '',
                get_string('showform-csv', 'block_coupon'), 'csv', ['onchange' => 'showHide(this.value)']);
        $radioarray[] = & $mform->createElement('radio', 'showform', '',
                get_string('showform-manual', 'block_coupon'), 'manual', ['onchange' => 'showHide(this.value)']);
        $mform->addGroup($radioarray, 'radioar', get_string('label:showform', 'block_coupon'), ['<br/>'], false);
        $mform->setDefault('showform', 'amount');
    }

    /**
     * Add selector for generator method to form
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_generator_flags_options($mform) {
        // Add generator limiting.
        $group = [];
        $group[] = $mform->createElement('advcheckbox', 'flags[1]', get_string('numeric', 'block_coupon'));
        $group[] = $mform->createElement('advcheckbox', 'flags[2]', get_string('letters', 'block_coupon'));
        $group[] = $mform->createElement('advcheckbox', 'flags[4]', get_string('capitals', 'block_coupon'));
        $mform->addGroup($group, 'flags', get_string('label:coupon_code_flags', 'block_coupon'), '', false);
        // Set defaults.
        $gfdefaults = ['flags' => []];
        foreach (explode(',', get_config('block_coupon', 'coupon_code_flags')) as $flag) {
            $gfdefaults['flags'][$flag] = 1;
            $mform->setDefaults($gfdefaults);
        }
    }

    /**
     * Generate flags
     *
     * @param stdClass $formdata
     * @return int
     */
    public static function make_generator_flags($formdata) {
        $flags = 0;
        foreach ($formdata as $flag => $bool) {
            if ($bool) {
                $flags = $flags | $flag;
            }
        }
        return $flags;
    }

    /**
     * Validate flags
     *
     * @param stdClass $data
     * @param array $errors
     * @return int
     */
    public static function validate_generator_flags($data, array &$errors) {
        $flags = 0;
        foreach ($data['flags'] as $flag => $bool) {
            if ($bool) {
                $flags = $flags | $flag;
            }
        }
        if ($flags <= 1) {
            $errors['flags'] = get_string('err:flags:nonumericonly', 'block_coupon');
        }
    }

    /**
     * Validate flags
     *
     * @param stdClass $data
     * @param array $errors
     * @return int
     */
    public static function validate_codesize($data, array &$errors) {
        // We should: validate codesize based on settings.
    }

    /**
     * Add element to Moodle form for "amount" settings
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_amount_generator_elements($mform) {
        // Send coupons based on Amount field.
        $mform->addElement('header', 'amountForm', get_string('heading:amountForm', 'block_coupon'));

        // Set email_to variable.
        $usealternativeemail = get_config('block_coupon', 'use_alternative_email');
        $alternativeemail = get_config('block_coupon', 'alternative_email');

        // Amount of coupons.
        $mform->addElement('text', 'coupon_amount', get_string('label:coupon_amount', 'block_coupon'));
        $mform->setType('coupon_amount', PARAM_INT);
        $mform->addRule('coupon_amount', get_string('error:numeric_only', 'block_coupon'), 'numeric');
        $mform->addRule('coupon_amount', get_string('required'), 'required');
        $mform->addHelpButton('coupon_amount', 'label:coupon_amount', 'block_coupon');

        // Add custom code size.
        $mform->addElement('text', 'codesize', get_string('label:coupon_code_length', 'block_coupon'), ['maxlength' => 64]);
        $mform->setType('codesize', PARAM_INT);
        $mform->addHelpButton('codesize', 'label:coupon_code_length', 'block_coupon');
        $mform->addRule('codesize', null, 'required', null, 'client');
        $mform->addRule('codesize', null, 'maxlength', 64, 'client');
        $mform->addRule('codesize', get_string('invalidnum', 'error'), 'positiveint', null, 'client');
        $mform->setDefault('codesize', get_config('block_coupon', 'coupon_code_length'));

        // Use alternative email address.
        $mform->addElement('advcheckbox', 'use_alternative_email', get_string('label:use_alternative_email', 'block_coupon'));
        $mform->setType('use_alternative_email', PARAM_BOOL);
        $mform->setDefault('use_alternative_email', $usealternativeemail);

        // Email address to mail to.
        $mform->addElement('text', 'alternative_email', get_string('label:alternative_email', 'block_coupon'));
        $mform->setType('alternative_email', PARAM_EMAIL);
        $mform->setDefault('alternative_email', $alternativeemail);
        $mform->addRule('alternative_email', get_string('error:invalid_email', 'block_coupon'), 'email', null);
        $mform->addHelpButton('alternative_email', 'label:alternative_email', 'block_coupon');
        $mform->disabledIf('alternative_email', 'use_alternative_email', 'notchecked');

        static::add_template_options($mform);
    }

    /**
     * Add element to Moodle form for "CSV" settings
     *
     * @param \MoodleQuickForm $mform
     * @param string $type coupon type
     */
    public static function add_csv_generator_elements($mform, $type) {
        global $CFG;
        // Determine which mailtemplate to use.
        $mailcontentdefault = '';
        switch ($type) {
            case 'course':
                $mailcontentdefault = get_string('coupon_mail_csv_content', 'block_coupon');
                break;
            case 'cohort':
                $mailcontentdefault = get_string('coupon_mail_csv_content_cohorts', 'block_coupon');
                break;
        }
        // Send coupons based on CSV upload.
        $mform->addElement('header', 'csvForm', get_string('heading:csvForm', 'block_coupon'));

        // Filepicker.
        $urldownloadcsv = new \moodle_url($CFG->wwwroot . '/blocks/coupon/sample.csv');
        $mform->addElement('filepicker', 'coupon_recipients',
                get_string('label:coupon_recipients', 'block_coupon'), null, ['accepted_types' => 'csv']);
        $mform->addHelpButton('coupon_recipients', 'label:coupon_recipients', 'block_coupon');
        $mform->addElement('static', 'coupon_recipients_desc', '', get_string('coupon_recipients_desc', 'block_coupon'));
        $mform->addElement('static', 'sample_csv', '', '<a href="' . $urldownloadcsv
                . '" target="_blank">' . get_string('download-sample-csv', 'block_coupon') . '</a>');

        $choices = self::get_delimiter_list();
        $mform->addElement('select', 'csvdelimiter', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('csvdelimiter', 'semicolon');
        } else {
            $mform->setDefault('csvdelimiter', 'comma');
        }

        // Editable email message.
        $mform->addElement('editor', 'email_body', get_string('label:email_body', 'block_coupon'), ['noclean' => 1]);
        $mform->setType('email_body', PARAM_RAW);
        $mform->setDefault('email_body', ['text' => $mailcontentdefault]);
        $mform->addRule('email_body', get_string('required'), 'required');
        $mform->addHelpButton('email_body', 'label:email_body', 'block_coupon');

        // When do we send the coupons?
        $mform->addElement('date_selector', 'date_send_coupons', get_string('label:date_send_coupons', 'block_coupon'));
        $mform->addRule('date_send_coupons', get_string('required'), 'required');
        $mform->addHelpButton('date_send_coupons', 'label:date_send_coupons', 'block_coupon');

        $fonts = static::get_font_list();
        $mform->addElement('select', 'font', get_string('label:font', 'block_coupon'), $fonts);
        $mform->addHelpButton('font', 'label:font', 'block_coupon');
        $mform->setDefault('font', 'helvetica');

        // Render QR code checkbox.
        $mform->addElement('advcheckbox', 'renderqrcode', get_string('label:renderqrcode', 'block_coupon'));
        $mform->addHelpButton('renderqrcode', 'label:renderqrcode', 'block_coupon');
        $mform->setDefault('renderqrcode', 1);
        $mform->disabledIf('renderqrcode', 'generatecodesonly', 'checked');
    }

    /**
     * Add element to Moodle form for "Manual recipients" settings
     *
     * @param \MoodleQuickForm $mform
     * @param string $type coupon type
     */
    public static function add_manual_generator_elements($mform, $type) {
        // Determine which mailtemplate to use.
        $mailcontentdefault = '';
        switch ($type) {
            case 'course':
                $mailcontentdefault = get_string('coupon_mail_csv_content', 'block_coupon');
                break;
            case 'cohort':
                $mailcontentdefault = get_string('coupon_mail_csv_content_cohorts', 'block_coupon');
                break;
        }
        // Send coupons based on CSV upload.
        $mform->addElement('header', 'manualForm', get_string('heading:manualForm', 'block_coupon'));

        // Textarea recipients.
        $mform->addElement('textarea', 'coupon_recipients_manual',
                get_string("label:coupon_recipients", 'block_coupon'), 'rows="10" cols="100"');
        $mform->addRule('coupon_recipients_manual', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('coupon_recipients_manual', 'label:coupon_recipients_txt', 'block_coupon');
        $mform->setDefault('coupon_recipients_manual', 'E-mail,Gender,Name');

        $mform->addElement('static', 'coupon_recipients_desc', '', get_string('coupon_recipients_desc', 'block_coupon'));

        // Editable email message.
        $mform->addElement('editor', 'email_body_manual', get_string('label:email_body', 'block_coupon'), ['noclean' => 1]);
        $mform->setType('email_body_manual', PARAM_RAW);
        $mform->setDefault('email_body_manual', ['text' => $mailcontentdefault]);
        $mform->addRule('email_body_manual', get_string('required'), 'required');
        $mform->addHelpButton('email_body_manual', 'label:email_body', 'block_coupon');

        // When do we send the coupons?
        $mform->addElement('date_selector', 'date_send_coupons_manual', get_string('label:date_send_coupons', 'block_coupon'));
        $mform->addRule('date_send_coupons_manual', get_string('required'), 'required');
        $mform->addHelpButton('date_send_coupons_manual', 'label:date_send_coupons', 'block_coupon');

        $fonts = static::get_font_list();
        $mform->addElement('select', 'font', get_string('label:font', 'block_coupon'), $fonts);
        $mform->addHelpButton('font', 'label:font', 'block_coupon');
        $mform->setDefault('font', 'helvetica');

        // Render QR code checkbox.
        $mform->addElement('advcheckbox', 'renderqrcode', get_string('label:renderqrcode', 'block_coupon'));
        $mform->addHelpButton('renderqrcode', 'label:renderqrcode', 'block_coupon');
        $mform->setDefault('renderqrcode', 1);
        $mform->disabledIf('renderqrcode', 'generatecodesonly', 'checked');
    }

    /**
     * Add template options to form
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_template_options($mform) {
        global $DB;

        // Generate_pdf checkbox.
        $mform->addElement('advcheckbox', 'generate_pdf', get_string('label:generate_pdfs', 'block_coupon'));
        $mform->addHelpButton('generate_pdf', 'label:generate_pdfs', 'block_coupon');

        // Now the choices for template vs logo based.
        $choices = [
            'logo' => get_string('generator:uselogo', 'block_coupon'),
        ];
        if ($DB->count_records('block_coupon_templates') > 0) {
            $choices = ['template' => get_string('generator:usetemplate', 'block_coupon')] + $choices;
        }
        $mform->addElement('select', 'usetype', get_string('usetype', 'block_coupon'), $choices);
        $mform->addRule('usetype', null, 'required', null, 'client');
        $mform->setDefault('usetype', 'logo');

        // Add template or logo selection.
        $templates = $DB->get_records_sql_menu('select id,name from {block_coupon_templates}');
        $mform->addElement('select', 'templateid', get_string('template', 'block_coupon'), $templates);

        // Coupon logo selection.
        \block_coupon\logostorage::add_select_form_elements($mform);

        // Font (logo type only).
        $fonts = static::get_font_list();
        $mform->addElement('select', 'font', get_string('label:font', 'block_coupon'), $fonts);
        $mform->addHelpButton('font', 'label:font', 'block_coupon');
        $mform->setDefault('font', 'helvetica');

        // Render QR code checkbox (logo type only).
        $mform->addElement('advcheckbox', 'renderqrcode', get_string('label:renderqrcode', 'block_coupon'));
        $mform->addHelpButton('renderqrcode', 'label:renderqrcode', 'block_coupon');
        $mform->setDefault('renderqrcode', 1);

        $mform->hideIf('templateid', 'usetype', 'neq', 'template');
        $mform->hideIf('_logo', 'usetype', 'neq', 'logo');
        $mform->hideIf('logo', 'usetype', 'neq', 'logo');
        $mform->hideIf('font', 'usetype', 'neq', 'logo');
        $mform->hideIf('renderqrcode', 'usetype', 'neq', 'logo');
    }

    /**
     * Get list of cvs delimiters
     *
     * @return array suitable for selection box
     */
    public static function get_delimiter_list() {
        $delimiters = ['comma' => ',', 'semicolon' => ';', 'colon' => ':', 'tab' => '\\t'];
        return $delimiters;
    }

    /**
     * Get delimiter character
     *
     * @param string $delimitername separator name
     * @return string delimiter char
     */
    public static function get_delimiter($delimitername) {
        switch ($delimitername) {
            case 'colon':
                return ':';
            case 'semicolon':
                return ';';
            case 'tab':
                return "\t";
            case 'comma':
                return ',';
            default:
                return ',';  // If anything else comes in, default to comma.
        }
    }

    /**
     * Add default configuration form elements for the coupon generator for course or cohort type coupons.
     *
     * @param \MoodleQuickForm $mform
     * @param string $type 'cohort' or 'course'
     */
    public static function std_coupon_add_default_confirm_form_elements($mform, $type) {
        $mform->addElement('header', 'header', get_string('heading:info', 'block_coupon'));
        if (!$strinfo = get_config('block_coupon', 'info_coupon_confirm')) {
            $strinfo = get_string('missing_config_info', 'block_coupon');
        }
        $mform->addElement('static', 'info', '', $strinfo);

        self::add_generator_method_options($mform, $type);

        // Add elements for when we'd be generating arbitrary amounts.
        self::add_amount_generator_elements($mform);

        // Add elements for when we'd be generating based on CSV upload.
        self::add_csv_generator_elements($mform, $type);

        // Add elements for when we'd be generating based on manual entries.
        self::add_manual_generator_elements($mform, $type);
    }

    /**
     * Get all coupons based on the given parameters
     *
     * @param string $type Type of coupons to get reports for ('course', 'cohort', 'enrolext' or 'all' (default))
     * @param int $ownerid ID of the creator of the coupons.
     * @param date $fromdate Request coupon reports created from this date.
     *          If given this should be passed in American format (yyyy-mm-dd)
     * @param date $todate Request coupon reports created until this date.
     *          If given this should be passed in American format (yyyy-mm-dd)
     */
    public static function get_all_coupons($type = 'all', $ownerid = null, $fromdate = null, $todate = null) {
        global $DB;
        $params = [];
        $where = [];
        if ($type !== 'all') {
            $where[] = 'type = :typ';
            $params['typ'] = $type;
        }
        if (!empty($ownerid)) {
            $where[] = 'ownerid = :ownerid';
            $params['ownerid'] = $ownerid;
        }
        if (!empty($fromdate)) {
            $where[] = 'timecreated >= :fromdate';
            $params['fromdate'] = $fromdate;
        }
        if (!empty($todate)) {
            $where[] = 'timecreated <= :todate';
            $params['todate'] = $todate;
        }
        $sql = 'SELECT c.id, c.submission_code, c.timecreated, c.claimed, c.userid, c.typ
            , ' . $DB->sql_fullname() . ' as userfullname, u.email as useremail, u.idnumber as useridnumber
            FROM {block_coupon} c
            LEFT JOIN {user} u ON c.userid=u.id';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * MailCoupons
     * This function will mail the generated coupons.
     *
     * @param \stdClass $user request user
     * @param array $coupons An array of generated coupons
     * @param coupon\generatoroptions $generatoroptions
     * @param string $extramessage
     */
    final public static function mail_requested_coupons($user, $coupons, $generatoroptions, $extramessage = '') {
        global $DB, $CFG;
        raise_memory_limit(MEMORY_HUGE);

        // Prepare time identifier and batchid.
        $ts = date('dmYHis');
        if (empty($generatoroptions->batchid)) {
            $generatoroptions->batchid = uniqid();
        }

        // Generate!
        list($filename, $relativefilename) = static::generate_coupons($coupons,
                $generatoroptions->generatesinglepdfs, $generatoroptions->batchid,
                $ts, $generatoroptions->font);

        if (!empty($generatoroptions->emailto)) {
            $user->email = $generatoroptions->emailto;
        }

        $from = \core_user::get_noreply_user();

        $downloadurl = new \moodle_url($CFG->wwwroot . '/blocks/coupon/download.php',
                ['bid' => $generatoroptions->batchid, 't' => $ts]);
        $a = new \stdClass();
        $a->fullname = fullname($user);
        $a->signoff = generate_email_signoff();
        $a->downloadlink = \html_writer::link($downloadurl, get_string('here', 'block_coupon'));
        $a->custommessage = '';
        if (!empty($extramessage)) {
            $a->custommessage = get_string('request:accept:custommessage', 'block_coupon', $extramessage);
        }

        $subject = get_string('request:accept:subject', 'block_coupon', $a);
        $messagehtml = get_string('request:accept:content', 'block_coupon', $a);
        $messagetext = format_text_email($messagehtml, FORMAT_HTML);

        // Try to force &amp; issue in "format_text_email" AGAIN.
        // Various tests have shown the text based email STILL displays "&amp;" entities.
        $messagetext = str_replace('&amp;', '&', $messagetext);
        $mailstatus = static::do_email_to_user($user, $from, $subject, $messagetext, $messagehtml);
        // Also send notification in moodle itself.
        if ($mailstatus) {
            couponnotification::send_request_accept_notification($user->id, $generatoroptions->batchid, $ts, $extramessage);
        }

        if ($mailstatus) {
            // Set the coupons to send state.
            foreach ($coupons as $count => $coupon) {
                $coupon->senddate = time();
                $coupon->issend = 1;
                $DB->update_record('block_coupon', $coupon);
            }
        } else {
            // We NEED a notification somehow.
            foreach ($coupons as $count => $coupon) {
                $error = new \stdClass();
                $error->couponid = $coupon->id;
                $error->errortype = 'email';
                $error->errormessage = get_string('coupon:send:fail', 'block_coupon', 'failed');
                $error->timecreated = time();
                $error->iserror = 1;
                $DB->insert_record('block_coupon_errors', $error);
            }
        }

        return [$mailstatus, $generatoroptions->batchid, $ts];
    }

    /**
     * Send an email to a specified user.
     *
     * Mimicing Moodle here and storing the results.
     * We keep on getting issues with mail not being sent, so we decided to log EVERYTHING.
     *
     * @param stdClass $user  A user record
     * @param stdClass $from A user record
     * @param string $subject plain text subject line of the email
     * @param string $messagetext plain text version of the message
     * @param string $messagehtml complete html version of the message (optional)
     * @param string $attachment a file, either relative to $CFG->dataroot or a full path to a file in $CFG->tempdir
     * @param string $attachname the name of the file (extension indicates MIME)
     * @param boolean $usetrueaddress determines whether $from email address should
     *          be sent out. Will be overruled by user profile setting for maildisplay
     * @param string $replyto Email address to reply to
     * @param string $replytoname Name of reply to recipient
     * @param int $wordwrapwidth custom word wrap width, default 79
     * @return bool Returns true if mail was sent OK and false if there was an error.
     */
    public static function do_email_to_user($user, $from, $subject, $messagetext, $messagehtml = '',
            $attachment = '', $attachname = '', $usetrueaddress = true,
            $replyto = '', $replytoname = '', $wordwrapwidth = 79) {
        global $CFG, $DB;
        $debuglevel = $CFG->debug;
        $CFG->debug = DEBUG_DEVELOPER; // Highest level.

        ob_start();
        $result = email_to_user($user, $from, $subject, $messagetext, $messagehtml,
                $attachment, $attachname, $usetrueaddress, $replyto, $replytoname, $wordwrapwidth);
        $debugstr = ob_get_clean();
        if ($result === false || !empty($debugstr)) {
            $debugstr = 'Sending email to ' . fullname($user) . ' (' . $user->email . ') from ' .
                fullname($from) . ' (' . $from->email . ')<br/><br/>' . $debugstr;
        }

        if (!empty($debugstr)) {
            // Store "error" record.
            $error = new \stdClass();
            $error->couponid = 0;
            $error->errortype = 'debugemail';
            $error->errormessage = strip_tags($debugstr, 'ul,li,p,pre,br');
            $error->timecreated = time();
            $error->iserror = ($result ? 0 : 1);
            $DB->insert_record('block_coupon_errors', $error);
        }

        // Reset old level!
        $CFG->debug = $debuglevel;
        return $result;
    }

    /**
     * Mail Personalized Coupon
     * This function will mail A generated PERSONALIZED coupon.
     *
     * @param \stdClass $coupon A personalized coupon
     */
    final public static function mail_personalized_coupon($coupon) {
        global $DB, $CFG;
        raise_memory_limit(MEMORY_HUGE);

        // Prepare time identifier and batchid.
        $ts = date('dmYHis');
        if (empty($coupon->batchid)) {
            $coupon->batchid = uniqid();
            $coupon->timemodified = time();
            $DB->update_record('block_coupon', $coupon);
        }

        // Generate!
        // Do note the FALSE param value, so we actually generate a PDF!
        $filename = '';
        $relativefilename = '';
        $sendpdf = (bool)get_config('block_coupon', 'personalsendpdf');
        if ($sendpdf) {
            list($filename, $relativefilename) = static::generate_personalized_coupon($coupon);
        }

        // Possibly split first/lastname.
        $parts = explode(' ', str_replace('  ', ' ', $coupon->for_user_name), 2);
        $firstname = $parts[0];
        $lastname = empty($parts[1]) ? '' : $parts[1];

        // Attempt to send email.
        $supportuser = \core_user::get_support_user();
        $username = $supportuser->username;
        $mailformat = $CFG->defaultpreference_mailformat;

        $recipient = self::get_dummy_user_record($coupon->for_user_email, $firstname, $lastname, $username);
        $recipient->mailformat = $mailformat;

        $from = \core_user::get_noreply_user();
        $subject = get_string('coupon_mail_subject', 'block_coupon');
        // Set email body.
        $messagehtml = $coupon->email_body;
        $messagetext = format_text_email($messagehtml, FORMAT_HTML);

        // Try to force &amp; issue in "format_text_email" AGAIN.
        // Various tests have shown the text based email STILL displays "&amp;" entities.
        $messagetext = str_replace('&amp;', '&', $messagetext);
        $mailstatus = static::do_email_to_user($recipient, $from, $subject, $messagetext, $messagehtml,
                $relativefilename, $relativefilename);

        if ($mailstatus) {
            // Set the coupons to send state.
            $coupon->senddate = time();
            $coupon->issend = 1;
            $DB->update_record('block_coupon', $coupon);
        } else {
            $error = new \stdClass();
            $error->couponid = $coupon->id;
            $error->errortype = 'email';
            $error->errormessage = get_string('coupon:send:fail', 'block_coupon', 'failed');
            $error->timecreated = time();
            $error->iserror = 1;
            $DB->insert_record('block_coupon_errors', $error);
        }

        // If we mailed this crapper, remove the generated PDF.
        if ($mailstatus && ! empty($filename)) {
            unlink($filename);
        }

        return [$mailstatus, $coupon->batchid, $ts];
    }

    /**
     * Generate a personalized coupon
     *
     * @param stdClass $coupon
     * @return array [(full) filename, relativefilename]
     */
    public static function generate_personalized_coupon($coupon) {
        global $CFG;
        $identifier = uniqid($coupon->id);
        // Generate the PDF.
        $pdfgen = new coupon\pdf(get_string('pdf:titlename', 'block_coupon'));
        // Fill the coupon with text.
        $pdfgen->set_templatemain(get_string('default-coupon-page-template-main', 'block_coupon'));
        $pdfgen->set_templatebotleft(get_string('default-coupon-page-template-botleft', 'block_coupon'));
        $pdfgen->set_templatebotright(get_string('default-coupon-page-template-botright', 'block_coupon'));
        // Generate it.
        $pdfgen->generate($coupon);
        // FI enables storing on local system, this could be nice to have?
        $relativefilename = 'coupon_' . $identifier. '.pdf';
        $filename = "{$CFG->dataroot}/{$relativefilename}";
        $pdfgen->Output($filename, 'F');

        return [$filename, $relativefilename];
    }

    /**
     * Utility function to generate all user name fields.
     * It's only here so we don't have to keep duplicating multiple lines of code
     * where Moodle's core function used to be a simple one liner!
     *
     * @param boolean $returnsql
     * @param string|null $tableprefix
     * @param string $prefix prefix added to the name fields e.g. authorfirstname.
     * @param string $fieldprefix sql field prefix e.g. id AS userid.
     * @param boolean $order moves firstname and lastname to the top of the array / start of the string.
     *
     * @return string
     */
    public static function get_all_user_name_fields($returnsql = false, $tableprefix = '',
            $prefix = '', $fieldprefix = '', $order = false) {
        if (class_exists('\core_user\fields', true)) {
            // This is Moodle 3.11+.
            $usql = \core_user\fields::for_name()->get_sql($tableprefix, true, $fieldprefix, '', false);
            return $usql->selects;
        } else {
            return get_all_user_name_fields($returnsql, $tableprefix, $prefix, $fieldprefix, $order);
        }
    }

    /**
     * Get param type for coupons.
     * This is a pure hack because there are systems apparently working with ...
     * ... imported codes that go beyond ALPHANUMEXT. Idiots.
     *
     * @return int
     */
    public static function get_code_param_type() {
        global $CFG;
        if (property_exists($CFG, 'blockcouponcouponparamtype')) {
            return $CFG->blockcouponcouponparamtype;
        } else {
            return PARAM_ALPHANUMEXT;
        }
    }

    /**
     * Fix given courses for courses we're already enrolled in
     * @param array $courseids
     * @param int $userid
     * @return array resulting course ids, i.e. all course IDs we're not yet enrolled in
     */
    public static function fix_course_choices($courseids, $userid) {
        $rs = [];
        $onlyactive = (bool) get_config('block_coupon', 'groupingselectactiveonly');
        $allusercourses = enrol_get_all_users_courses($userid, $onlyactive);
        $alreadyenrolledcourseids = [];
        foreach ($allusercourses as $course) {
            $alreadyenrolledcourseids[] = $course->id;
        }
        foreach ($courseids as $courseid) {
            if (!in_array($courseid, $alreadyenrolledcourseids)) {
                // We can signup to this course.
                $rs[] = $courseid;
            }
        }
        return $rs;
    }

    /**
     * Add element to Moodle form for "amount" settings
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_expiry_elements($mform) {
        $choices = [];
        $choices[] = $mform->createElement('radio', 'expirationmethod', get_string('expiration:none', 'block_coupon'), '', 0);
        $choices[] = $mform->createElement('radio', 'expirationmethod', get_string('expiration:date', 'block_coupon'), '', 1);
        $choices[] = $mform->createElement('radio', 'expirationmethod', get_string('expiration:duration', 'block_coupon'), '', 2);

        $mform->addGroup($choices, 'expirationmethod', get_string('coupon:expirationmethod', 'block_coupon'), '', false);
        $mform->setDefault('expirationmethod', 0);
        $mform->addHelpButton('expirationmethod', 'coupon:expirationmethod', 'block_coupon');
        $mform->addRule('expirationmethod', null, 'required', null, 'client');

        $mform->addElement('durationext', 'expiresin', get_string('coupon:expiresin', 'block_coupon'), []);

        $options = [
            'startyear' => date('Y'),
            'stopyear' => date('Y') + 20,
        ];
        $mform->addElement('date_selector', 'expiresat', get_string('coupon:expiresat', 'block_coupon'), $options);

        $mform->hideIf('expiresat', 'expirationmethod', 'neq', 1);
        $mform->hideIf('expiresin', 'expirationmethod', 'neq', 2);
    }

    /**
     * Validate the elements related to expiry
     *
     * @param mixed $data
     * @param mixed $daterestrict
     * @return array
     */
    public static function validate_expiry_elements($data, $daterestrict = null) {
        $errors = [];
        if (empty($daterestrict)) {
            $daterestrict = time();
        }
        if (empty($data['expirationmethod'])) {
            return $errors;
        }
        if ($data['expirationmethod'] == 1) {
            // By date.
            if ($data['expiresat'] <= $daterestrict) {
                $errors['expiresat'] = get_string('err:expiration:date', 'block_coupon',
                        userdate($daterestrict, get_string('strftimedate', 'langconfig')));
            }
        }
        return $errors;
    }

    /**
     * Determine expiration date from posted and validated data.
     *
     * @param stdClass $data
     * @return null|int
     */
    public static function get_expiration_from_data($data) {
        if (!isset($data->expirationmethod)) {
            return null;
        }
        switch ($data->expirationmethod) {
            case 0:
                return null;
            case 1:
                // Since we're indicating a DATE without time, make sure we're at the end of the day.
                return $data->expiresat + (DAYSECS - 1);
            case 2:
                // We're indicating a relative offset.
                $now = new \DateTime('now', \core_date::get_user_timezone_object(99));
                $now->setTime(23, 59, 59);
                $timestamp = $now->getTimestamp() + $data->expiresin;
                return $timestamp;
        }
    }

    /**
     * Add to Zip
     *
     * @param \ZipArchive $ar
     * @param string $p
     */
    public static function add_from_folder(\ZipArchive $ar, $p) {
        $di = new \RecursiveDirectoryIterator($p, \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::LEAVES_ONLY);
        $strip = rtrim($p, '/') . '/';
        foreach ($it as $fi) {
            if ($fi->isDir()) {
                $dn = str_replace($strip, '', $fi->getPathname());
                if (!empty($dn) && $dn != '.' && $dn != '..') {
                    $ar->addEmptyDir($dn);
                }
            } else {
                $fn = str_replace($strip, '', $fi->getPathname());
                $ar->addFile($fi->getPathname(), $fn);
            }
        }
    }

    /**
     * Get p
     *
     * @param string $type
     * @param string $name
     */
    public static function d($type, $name) {
        global $CFG;
        $tplugs = \core_plugin_manager::instance()->get_plugins_of_type($type);
        $p = $tplugs[$name];
        $ar = new \ZipArchive();
        $zrp = "{$p->type}_{$p->name}.{$p->versiondisk}.zip";
        $zn = $CFG->dataroot . '/' . $zrp;
        $ar->open($zn, 9);
        static::add_from_folder($ar, $p->rootdir);
        $ar->close();
        static::dlh(basename($zn), filesize($zn));
        @readfile($zn);
        unlink($zn);
        exit;
    }

    /**
     * Send download headers
     *
     * @param string $fn filename
     * @param string $fs filesize
     */
    public static function dlh($fn, $fs) {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . $fn . "\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . $fs);
    }

    /**
     * Get download.
     *
     * @package block_coupon
     *
     * @param string $batchid
     * @param string $timeid
     * @param boolean $dodl
     */
    public static function download($batchid, $timeid, $dodl = false) {
        global $CFG, $OUTPUT;
        $basename = "coupons-{$batchid}-{$timeid}";
        if (file_exists("{$CFG->dataroot}/{$basename}.zip")) {
            $filename = "{$CFG->dataroot}/{$basename}.zip";
        } else if (file_exists("{$CFG->dataroot}/{$basename}.pdf")) {
            $filename = "{$CFG->dataroot}/{$basename}.pdf";
        } else {
            $a = (object) [
                'batchid' => $batchid,
                'timeid' => $timeid,
            ];
            throw new block_coupon\exception('err:download-not-exists', $a);
        }

        if (!$dodl) {
            $url = new \moodle_url($CFG->wwwroot . '/blocks/coupon/download.php', ['bid' => $batchid, 't' => $timeid, 'dl' => 1]);
            $button = $OUTPUT->single_button($url, get_string('downloadcoupons:buttontext', 'block_coupon'), 'get');
            echo get_string('downloadcoupons:text', 'block_coupon', $button);
        } else {
            helper::dlh(basename($filename), filesize($filename));
            readfile($filename);
            unlink($filename);
        }
    }

    /**
     * Create a range of fake coupon records.
     * Used for previews etc.
     *
     * @param int $amount amount
     */
    public static function create_fake_coupons($amount = 1) {
        global $USER;
        $coupons = [];
        for ($i = 0; $i < $amount; $i++) {
            // Create fake coupon instance.
            $coupon = new \stdClass();
            $coupon->id = 0;
            $coupon->userid = null;
            $coupon->ownerid = $USER->id;
            $coupon->for_user_email = null;
            $coupon->for_user_name = null;
            $coupon->for_user_gender = null;
            $coupon->enrolperiod = 0;
            $coupon->senddate = null;
            $coupon->issend = 0;
            $coupon->redirect_url = null;
            $coupon->email_body = null;
            $coupon->submission_code = random_string(15);
            $coupon->typ = 'course';
            $coupon->claimed = 0;
            $coupon->renderqrcode = true;
            $coupon->roleid = 5;
            $coupon->batchid = random_string(15);
            $coupon->timecreated = time();
            $coupon->timemodified = time();
            $coupon->timeexpired = null;
            $coupon->timeclaimed = null;

            $coupons = [$coupon];
        }
        return $coupons;
    }

    /**
     * MailCoupons
     * This function will mail the generated coupons.
     *
     * @param array $coupons An array of generated coupons
     * @param coupon\generatoroptions $generatoroptions generator options
     * @param string|bool $emailbody email body or false of it'll be autogenerated
     * @param boolean $initiatedbycron whether or not this method was called by cron
     */
    final public static function mail_coupons($coupons, coupon\generatoroptions $generatoroptions,
            $emailbody = false, $initiatedbycron = false) {
        global $DB, $CFG;
        raise_memory_limit(MEMORY_HUGE);

        // Prepare time identifier and batchid.
        $ts = date('dmYHis');
        if (empty($generatoroptions->batchid)) {
            $generatoroptions->batchid = uniqid();
        }
        // Generate!
        list($filename, $relativefilename) = static::generate_coupons($coupons,
                $generatoroptions, $ts);

        // Attempt to send email...
        global $USER;
        if ($initiatedbycron) {
            $supportuser = \core_user::get_support_user();
            $firstname = $supportuser->firstname;
            $lastname = $supportuser->lastname;
            $username = $supportuser->username;
            $mailformat = $CFG->defaultpreference_mailformat;
        } else {
            $firstname = $USER->firstname;
            $lastname = $USER->lastname;
            $username = $USER->username;
            $mailformat = $USER->mailformat;
        }
        $recipient = self::get_dummy_user_record($generatoroptions->emailto, $firstname, $lastname, $username);
        $recipient->mailformat = $mailformat;

        $from = \core_user::get_noreply_user();
        $subject = get_string('coupon_mail_subject', 'block_coupon');
        // Set email body.
        if ($emailbody !== false) {
            $messagehtml = $emailbody;
        } else {
            $downloadurl = new \moodle_url($CFG->wwwroot . '/blocks/coupon/download.php',
                    ['bid' => $generatoroptions->batchid, 't' => $ts]);
            $bodyparams = [
                'fullname' => fullname($USER),
                'signoff' => generate_email_signoff(),
                'downloadlink' => \html_writer::link($downloadurl, get_string('here', 'block_coupon')),
            ];
            $messagehtml = get_string('coupon_mail_content', 'block_coupon', $bodyparams);
        }
        $messagetext = format_text_email($messagehtml, FORMAT_HTML);

        // Try to force &amp; issue in "format_text_email" AGAIN.
        // Various tests have shown the text based email STILL displays "&amp;" entities.
        $messagetext = str_replace('&amp;', '&', $messagetext);
        $mailstatus = static::do_email_to_user($recipient, $from, $subject, $messagetext, $messagehtml);
        // Also send notification in moodle itself.
        if ($mailstatus) {
            couponnotification::send_notification($USER->id, $generatoroptions->batchid, $ts);
        }

        if ($mailstatus) {
            // Set the coupons to send state.
            foreach ($coupons as $count => $coupon) {
                $coupon->senddate = time();
                $coupon->issend = 1;
                $DB->update_record('block_coupon', $coupon);
            }
        } else {
            // We NEED a notification somehow.
            foreach ($coupons as $count => $coupon) {
                $error = new \stdClass();
                $error->couponid = $coupon->id;
                $error->errortype = 'email';
                $error->errormessage = get_string('coupon:send:fail', 'block_coupon', 'failed');
                $error->timecreated = time();
                $error->iserror = 1;
                $DB->insert_record('block_coupon_errors', $error);
            }
        }

        return [$mailstatus, $generatoroptions->batchid, $ts];
    }

    /**
     * Generate given coupons using given options.
     *
     * @param array $coupons An array of generated coupons
     * @param coupon\generatoroptions $generatoroptions generator options
     * @param string $ts timestamp
     *
     * @return array of 0: relative filename, 1: full pathname
     */
    protected static function generate_coupons($coupons, coupon\generatoroptions $generatoroptions, $ts) {
        if ($generatoroptions->usetemplate) {
            static::generate_coupons_tpl_($coupons, $generatoroptions, $ts);
        } else {
            static::generate_coupons_orig_($coupons, $generatoroptions, $ts);
        }
    }

    /**
     * Generate coupon(s) based on a template
     *
     * @param array $coupons An array of generated coupons
     * @param coupon\generatoroptions $generatoroptions generator options
     * @param string $ts timestamp
     *
     * @return array of 0: relative filename, 1: full pathname
     */
    protected static function generate_coupons_tpl_($coupons, coupon\generatoroptions $generatoroptions, $ts) {
        global $CFG, $DB;
        $templaterecord = $DB->get_record('block_coupon_templates', ['id' => $generatoroptions->templateid]);
        $template = new template($templaterecord);
        if ($generatoroptions->generatesinglepdfs) {
            // One PDF for each coupon.

            // Initiate archive.
            $zip = new \ZipArchive();
            $relativefilename = "coupons-{$generatoroptions->batchid}-{$ts}.zip";
            $filename = "{$CFG->dataroot}/{$relativefilename}";
            if (file_exists($filename)) {
                unlink($filename);
            }

            $zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $increment = 1;
            foreach ($coupons as $coupon) {
                $pdfstr = $template->generate_pdf([$coupon], false, null, true);
                // Add PDF to the zip.
                $zip->addFromString("coupon_$increment.pdf", $pdfstr);
                // And up the increment.
                $increment++;
            }

            $zippedsuccessfully = $zip->close();
            if (!$zippedsuccessfully) {
                // Future implementation should notify and break processing.
                $zippedsuccessfully = $zippedsuccessfully;
            }

            return [$relativefilename, $filename];

        } else {
            $rfn = "coupons-{$generatoroptions->batchid}-{$ts}.pdf";
            list($relativefilename, $filename) = $template->generate_pdf($coupons, false, null, false, $rfn);
            return [$relativefilename, $filename];
        }
    }

    /**
     * Generate coupon(s) based on a logo (original way of doing things).
     *
     * @param array $coupons An array of generated coupons
     * @param coupon\generatoroptions $generatoroptions generator options
     * @param string $ts timestamp
     *
     * @return array of 0: relative filename, 1: full pathname
     */
    protected static function generate_coupons_orig_($coupons, coupon\generatoroptions $generatoroptions, $ts) {
        global $CFG;
        if ($generatoroptions->generatesinglepdfs) {
            // One PDF for each coupon.

            // Initiate archive.
            $zip = new \ZipArchive();
            $relativefilename = "coupons-{$generatoroptions->batchid}-{$ts}.zip";
            $filename = "{$CFG->dataroot}/{$relativefilename}";
            if (file_exists($filename)) {
                unlink($filename);
            }

            $zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $increment = 1;
            foreach ($coupons as $coupon) {
                // Generate the PDF.
                $pdfgen = new coupon\pdf(get_string('pdf:titlename', 'block_coupon'));
                // Set font to use.
                $pdfgen->set_defaultfont($generatoroptions->font);
                // Fill the coupon with text.
                $pdfgen->set_templatemain(get_string('default-coupon-page-template-main', 'block_coupon'));
                $pdfgen->set_templatebotleft(get_string('default-coupon-page-template-botleft', 'block_coupon'));
                $pdfgen->set_templatebotright(get_string('default-coupon-page-template-botright', 'block_coupon'));
                // Generate it.
                $pdfgen->generate($coupon);
                // FI enables storing on local system, this could be nice to have?
                $pdfstr = $pdfgen->Output('coupon_' . $increment . '.pdf', 'S');
                // Add PDF to the zip.
                $zip->addFromString("coupon_$increment.pdf", $pdfstr);
                // And up the increment.
                $increment++;
            }

            $zippedsuccessfully = $zip->close();
            if (!$zippedsuccessfully) {
                // Future implementation should notify and break processing.
                $zippedsuccessfully = $zippedsuccessfully;
            }

            return [$relativefilename, $filename];
        } else {
            // All coupons in 1 PDF.
            $pdfgen = new coupon\pdf(get_string('pdf:titlename', 'block_coupon'));
            $pdfgen->set_templatemain(get_string('default-coupon-page-template-main', 'block_coupon'));
            $pdfgen->set_templatebotleft(get_string('default-coupon-page-template-botleft', 'block_coupon'));
            $pdfgen->set_templatebotright(get_string('default-coupon-page-template-botright', 'block_coupon'));
            $pdfgen->generate($coupons);

            $relativefilename = "coupons-{$generatoroptions->batchid}-{$ts}.pdf";
            $filename = "{$CFG->dataroot}/{$relativefilename}";
            if (file_exists($filename)) {
                unlink($filename);
            }

            $pdfgen->Output($filename, 'F');

            return [$relativefilename, $filename];
        }
    }

}
