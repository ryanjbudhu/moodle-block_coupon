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
 * This file contains the element date's core interaction API.
 *
 * @package    block_coupon
 * @copyright  2023 RvD <helpdesk@sebsoft.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\template\element\date;

use block_coupon\template\element_helper;

/**
 * Date - Current date
 */
define('COUPON_DATE_CURRENT_DATE', '0');

/**
 * Date - Expiry date
 */
define('COUPON_DATE_EXPIRES', '-1');

/**
 * The element date's core interaction API.
 *
 * @package    block_coupon
 * @copyright  2023 RvD <helpdesk@sebsoft.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \block_coupon\template\element {

    /**
     * This function renders the form elements when adding a element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        // Get the possible date options.
        $dateoptions = [];
        $dateoptions[COUPON_DATE_CURRENT_DATE] = get_string('currentdate', 'block_coupon');
        $dateoptions[COUPON_DATE_EXPIRY_ONE] = get_string('expirydate', 'block_coupon');

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'block_coupon'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'block_coupon');

        $mform->addElement('select', 'dateformat', get_string('dateformat', 'block_coupon'), self::get_date_formats());
        $mform->addHelpButton('dateformat', 'dateformat', 'block_coupon');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * block_coupon_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = [
            'dateitem' => $data->dateitem,
            'dateformat' => $data->dateformat,
        ];

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param boolean $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     * @param \stdClass|null $extradata -- expects "code" to be present
     */
    public function render($pdf, $preview, $user, ?\stdClass $extradata = null) {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());
        $dateitem = $dateinfo->dateitem;
        $dateformat = $dateinfo->dateformat;

        // If we are previewing this certificate then just show a demonstration date.
        if ($preview) {
            $date = time();
        } else {
            switch ($dateitem) {
                case COUPON_DATE_CURRENT_DATE:
                    $date = time();
                    break;

                case COUPON_DATE_EXPIRES:
                    // The $extradata SHOULD contain the expiry date or it evaluates to "never".
                    $date = $extradata->expirydate ?? 0;
                    break;
            }
        }

        // Ensure that a date has been set.
        if (empty($date)) {
            $formatteddate = '&#8734'; // Infinity symbol.
        } else {
            $formatteddate = $this->get_date_format_string($date, $dateformat);
        }
        element_helper::render_content($pdf, $this, $formatteddate);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $dateinfo = json_decode($this->get_data());
        $dateformat = $dateinfo->dateformat;

        return element_helper::render_html_content($this, $this->get_date_format_string(time(), $dateformat));
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $dateinfo = json_decode($this->get_data());

            $element = $mform->getElement('dateitem');
            $element->setValue($dateinfo->dateitem);

            $element = $mform->getElement('dateformat');
            $element->setValue($dateinfo->dateformat);
        }

        parent::definition_after_data($mform);
    }

    /**
     * Helper function to return all the date formats.
     *
     * @return array the list of date formats
     */
    public static function get_date_formats() {
        // Hard-code date so users can see the difference between short dates with and without the leading zero.
        // Eg. 06/07/18 vs 6/07/18.
        $date = 1530849658;

        $suffix = self::get_ordinal_number_suffix(userdate($date, '%d'));

        $dateformats = [
            1 => userdate($date, '%B %d, %Y'),
            2 => userdate($date, '%B %d' . $suffix . ', %Y'),
        ];

        $strdateformats = [
            'strftimedate',
            'strftimedatefullshort',
            'strftimedatefullshortwleadingzero',
            'strftimedateshort',
            'strftimedatetime',
            'strftimedatetimeshort',
            'strftimedatetimeshortwleadingzero',
            'strftimedaydate',
            'strftimedaydatetime',
            'strftimedayshort',
            'strftimedaytime',
            'strftimemonthyear',
            'strftimerecent',
            'strftimerecentfull',
            'strftimetime',
        ];

        foreach ($strdateformats as $strdateformat) {
            if ($strdateformat == 'strftimedatefullshortwleadingzero') {
                $dateformats[$strdateformat] = userdate($date, get_string('strftimedatefullshort', 'langconfig'), 99, false);
            } else if ($strdateformat == 'strftimedatetimeshortwleadingzero') {
                $dateformats[$strdateformat] = userdate($date, get_string('strftimedatetimeshort', 'langconfig'), 99, false);
            } else {
                $dateformats[$strdateformat] = userdate($date, get_string($strdateformat, 'langconfig'));
            }
        }

        return $dateformats;
    }

    /**
     * Returns the date in a readable format.
     *
     * @param int $date
     * @param string $dateformat
     * @return string
     */
    protected function get_date_format_string($date, $dateformat) {
        // Keeping for backwards compatibility.
        if (is_number($dateformat)) {
            switch ($dateformat) {
                case 1:
                    $certificatedate = userdate($date, '%B %d, %Y');
                    break;
                case 2:
                    $suffix = self::get_ordinal_number_suffix(userdate($date, '%d'));
                    $certificatedate = userdate($date, '%B %d' . $suffix . ', %Y');
                    break;
                case 3:
                    $certificatedate = userdate($date, '%d %B %Y');
                    break;
                case 4:
                    $certificatedate = userdate($date, '%B %Y');
                    break;
                default:
                    $certificatedate = userdate($date, get_string('strftimedate', 'langconfig'));
            }
        }

        // Ok, so we must have been passed the actual format in the lang file.
        if (!isset($certificatedate)) {
            if ($dateformat == 'strftimedatefullshortwleadingzero') {
                $certificatedate = userdate($date, get_string('strftimedatefullshort', 'langconfig'), 99, false);
            } else if ($dateformat == 'strftimedatetimeshortwleadingzero') {
                $certificatedate = userdate($date, get_string('strftimedatetimeshort', 'langconfig'), 99, false);
            } else {
                $certificatedate = userdate($date, get_string($dateformat, 'langconfig'));
            }
        }

        return $certificatedate;
    }

    /**
     * Helper function to return the suffix of the day of
     * the month, eg 'st' if it is the 1st of the month.
     *
     * @param int $day the day of the month
     * @return string the suffix.
     */
    protected static function get_ordinal_number_suffix($day) {
        if (!in_array(($day % 100), [11, 12, 13])) {
            switch ($day % 10) {
                // Handle 1st, 2nd, 3rd.
                case 1:
                    return get_string('numbersuffix_st_as_in_first', 'block_coupon');
                case 2:
                    return get_string('numbersuffix_nd_as_in_second', 'block_coupon');
                case 3:
                    return get_string('numbersuffix_rd_as_in_third', 'block_coupon');
            }
        }
        return 'th';
    }
}
