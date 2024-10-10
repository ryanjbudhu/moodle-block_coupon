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
 * Batch selector field.
 *
 * File         findbatches.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_coupon\forms\element;

defined('MOODLE_INTERNAL') || die();

use MoodleQuickForm_autocomplete;
global $CFG;

require_once($CFG->libdir . '/form/autocomplete.php');
/**
 * Form field type for choosing a batch.
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class findbatches extends MoodleQuickForm_autocomplete {

    /**
     * Has setValue() already been called already?
     *
     * @var bool
     */
    private $selectedset = false;

    /**
     * Constructor.
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param array $options Options to control the element's display
     *                       Valid options are:
     *                       - multiple bool Whether or not the field accepts more than one values.
     */
    public function __construct($elementname = null, $elementlabel = null, $options = []) {
        $validattributes = ['ajax' => 'block_coupon/findbatches'];
        if (!empty($options['multiple'])) {
            $validattributes['multiple'] = 'multiple';
        }
        $validattributes['tags'] = false;
        $validattributes['casesensitive'] = false;
        $validattributes['placeholder'] = $options['placeholder'] ??
                get_string('findbatches:placeholder', 'block_coupon');
        $validattributes['noselectionstring'] = $options['noselectionstring'] ??
                get_string('findbatches:noselectionstring', 'block_coupon');
        $validattributes['showsuggestions'] = $options['showsuggestions'] ?? true;
        parent::__construct($elementname, $elementlabel, [], $validattributes);
    }

    /**
     * Set the value of this element.
     *
     * @param  string|array $value The value to set.
     * @return boolean
     */
    public function setValue($value) { // @codingStandardsIgnoreLine Can't change parent behaviour.
        global $DB;
        // The following lines SEEM to fix the issues around the autocomplete...
        // When e.g. postback of form introduces a server side validation error.
        // The result is that when this method has been called before, selection is reset to NOTHING.
        // See https://tracker.moodle.org/browse/MDL-53889 among others.
        // The autocomplete, is must say, is VERY poorly developed and not properly tested.
        if ($this->selectedset) {
            return;
        }
        $this->selectedset = true;

        $values = (array) $value;
        $ids = [];
        foreach ($values as $onevalue) {
            if (!empty($onevalue) && (!$this->optionExists($onevalue)) &&
                    ($onevalue !== '_qf__force_multiselect_submission')) {
                array_push($ids, $onevalue);
            }
        }
        if (empty($ids)) {
            return;
        }
        // Logic here is simulating API.
        $toselect = [];
        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'param');
        $coupons = $DB->get_records_select('block_coupon', 'batchid '.$insql, $inparams, '', 'id,batchid');
        foreach ($coupons as $coupon) {
            $this->addOption($coupon->batchid, $coupon->batchid, ['selected' => 'selected']);
            array_push($toselect, $coupon->batchid);
        }
        $rs = $this->setSelected($toselect);
        return $rs;
    }
}