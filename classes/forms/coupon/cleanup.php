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
 * Coupon cleanup form
 *
 * File         cleanup.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\forms\coupon;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * block_coupon\forms\cleanup
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \moodleform {

    /**
     * form definition
     */
    public function definition() {
        global $CFG;

        // Register elements.
        $path = $CFG->dirroot . '/blocks/coupon/classes/forms/element/findcourses.php';
        \MoodleQuickForm::registerElementType('findcourses', $path, '\block_coupon\forms\element\findcourses');
        $path = $CFG->dirroot . '/blocks/coupon/classes/forms/element/findcohorts.php';
        \MoodleQuickForm::registerElementType('findcohorts', $path, '\block_coupon\forms\element\findcohorts');
        $path = $CFG->dirroot . '/blocks/coupon/classes/forms/element/findbatches.php';
        \MoodleQuickForm::registerElementType('findbatches', $path, '\block_coupon\forms\element\findbatches');

        $mform = & $this->_form;

        $mform->addElement('header', 'header', get_string('coupon:cleanup:heading', 'block_coupon'));
        $mform->addElement('static', 'info', '', get_string('coupon:cleanup:info', 'block_coupon'));

        // Owner.
        $mform->addElement('hidden', 'ownerid', 0);
        $mform->setType('ownerid', PARAM_INT);
        if (!empty($this->_customdata['ownerid'])) {
            $mform->setConstant('ownerid', $this->_customdata['ownerid']);
        } else {
            $mform->setConstant('ownerid', 0);
        }

        // Which coupons.
        $options = [
            0 => get_string('coupon:type:all', 'block_coupon'),
            1 => get_string('course'),
            2 => get_string('cohort', 'core_cohort'),
            3 => get_string('th:batchid', 'block_coupon'),
        ];
        $select = $mform->addElement('select', 'type', get_string('coupon:type', 'block_coupon'), $options);
        $select->setMultiple(false);
        $mform->setDefault('type', 0);

        // Usage selection.
        $options = [
            0 => get_string('coupon:used:all', 'block_coupon'),
            1 => get_string('coupon:used:yes', 'block_coupon'),
            2 => get_string('coupon:used:no', 'block_coupon'),
        ];
        $select = $mform->addElement('select', 'used', get_string('coupon:used', 'block_coupon'), $options);
        $mform->setDefault('used', 1);

        // Date selection.
        $dateoptions = [
            'startyear' => 1970,
            'stopyear'  => date('Y') + 1,
            'timezone'  => 99,
            'optional'  => true,
        ];
        $mform->addElement('date_selector', 'timebefore', get_string('timebefore', 'block_coupon'), $dateoptions);
        $mform->addElement('date_selector', 'timeafter', get_string('timeafter', 'block_coupon'), $dateoptions);

        // Course selector.
        $mform->addElement('findcourses', 'course', get_string('th:course', 'block_coupon'), ['multiple' => true]);

        // Cohort selector.
        $mform->addElement('findcohorts', 'cohort', get_string('th:cohorts', 'block_coupon'), ['multiple' => true]);

        // Batch selector.
        $mform->addElement('findbatches', 'batchid', get_string('th:batchid', 'block_coupon'), ['multiple' => true]);

        $mform->hideIf('course[]', 'type', 'neq', 1);
        $mform->hideIf('cohort[]', 'type', 'neq', 2);
        $mform->hideIf('batchid[]', 'type', 'neq', 3);

        $this->add_action_buttons(true, get_string('button:next', 'block_coupon'));
    }

}
