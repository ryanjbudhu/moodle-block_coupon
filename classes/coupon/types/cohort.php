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
 * Cohort type coupon processor
 *
 * File         cohort.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * */

namespace block_coupon\coupon\types;

defined('MOODLE_INTERNAL') || die();

use block_coupon\coupon\icoupontype;
use block_coupon\coupon\typebase;
use block_coupon\exception;

/**
 * block_coupon\coupon\types\cohort
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort extends typebase implements icoupontype {

    /**
     * Claim coupon.
     * @param int $foruserid user that claims coupon. Current userid if not given.
     */
    public function claim($foruserid = null) {
        global $CFG, $DB, $USER;
        // Because we're outside course context we've got to include libraries manually.
        require_once($CFG->dirroot . '/cohort/lib.php');

        // Validate.
        if ($this->coupon->typ !== \block_coupon\coupon\generatoroptions::COHORT) {
            throw new exception('invalid-coupon-type');
        }
        // Claim.
        if (empty($foruserid)) {
            $foruserid = $USER->id;
        }

        // Validate correct user, if applicable.
        if (!empty($this->coupon->userid) && $this->coupon->userid != $foruserid) {
            throw new exception('coupon:claim:wronguser', 'block_coupon');
        }

        // Load associated cohorts.
        $couponcohorts = $DB->get_records('block_coupon_cohorts', array('couponid' => $this->coupon->id));
        if (count($couponcohorts) == 0) {
            throw new exception('error:missing_cohort');
        }

        // Add user to cohort.
        foreach ($couponcohorts as $couponcohort) {
            if (!$DB->get_record('cohort', array('id' => $couponcohort->cohortid))) {
                throw new exception('error:missing_cohort');
            }
            cohort_add_member($couponcohort->cohortid, $foruserid);
        }
        // Now execute the cohort sync.
        $result = $this->enrol_cohort_sync();
        // If result = 0 it went ok. (lol!).
        if ($result === 1) {
            throw new exception('error:cohort_sync');
        } else if ($result === 2) {
            throw new exception('error:plugin_disabled');
        }

        // And finally update the coupon record.
        $this->coupon->claimed = 1;
        $this->coupon->userid = $foruserid;
        $this->coupon->timemodified = time();
        $DB->update_record('block_coupon', $this->coupon);
    }

    /**
     * Sync all cohort course links.
     *
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function enrol_cohort_sync() {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        if ($CFG->version < 2013051400) {
            return enrol_cohort_sync();
        } else {
            $trace = new \null_progress_trace();
            return enrol_cohort_sync($trace);
        }
    }

}