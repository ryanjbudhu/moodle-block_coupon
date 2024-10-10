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
 * This file contains the form for handling editing a template element.
 *
 * @package    block_coupon
 * @copyright  2023 RvD <helpdesk@sebsoft.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\forms\dynamic;

use core_form\dynamic_form;
use context_system;

/**
 * The form for handling editing a template element.
 *
 * @package    block_coupon
 * @copyright  2023 RvD <helpdesk@sebsoft.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editcourses extends dynamic_form {

    /**
     * @var array list of coupons ids.
     */
    protected $idlist;

    /**
     * @var array of coupon instances
     */
    protected $coupons;

    /**
     * @var array of error messages
     */
    protected $errorlist;

    /**
     * Form definition.
     */
    public function definition() {
        $mform =& $this->_form;

        // Force extra form class attribute.
        $oldclass = $this->_form->getAttribute('class');
        if (!empty($oldclass)) {
            $this->_form->updateAttributes(['class' => trim(str_replace('mform', '', $oldclass))
                . ' mform couponmodal']);
        } else {
            $this->_form->updateAttributes(['class' => 'mform couponmodal']);
        }

        if (count($this->errorlist) > 0) {
            // We'll only display the errors.
            foreach ($this->errorlist as $msg) {
                $mform->addElement('html', \html_writer::div($msg, 'alert alert-danger'));
            }
        } else {
            // We can continue.
            foreach ($this->idlist as $idx => $id) {
                $mform->addElement('hidden', "id[{$idx}]");
                $mform->setType("id[{$idx}]", PARAM_INT);
                $mform->setConstant("id[{$idx}]", $id);
            }

            $mform->addElement('static', '_courses', '', get_string('searchcourses:desc', 'block_coupon'));
            $mform->addElement('findcourses', 'courses', get_string('course'), ['multiple' => true]);
        }
    }

    /**
     * Initializer
     */
    protected function initialize() {
        global $DB, $CFG;

        \MoodleQuickForm::registerElementType('findcourses',
            $CFG->dirroot . '/blocks/coupon/classes/forms/element/findcourses.php',
                '\\block_coupon\\forms\\element\\findcourses');

        $this->errorlist = [];
        $this->idlist = $this->required_param_array('id', PARAM_INT);
        $this->coupons = $DB->get_records_list('block_coupon', 'id', $this->idlist);
        $a = (object)['claimed' => 0, 'typs' => []];
        foreach ($this->coupons as $coupon) {
            if ($coupon->typ != 'course') {
                $a->typs[] = $coupon->typ;
            }
            if ($coupon->claimed) {
                $a->claimed++;
            }
        }

        if ($a->claimed > 0) {
            $this->errorlist[] = get_string('err:coupon:editcourses:nclaimed', 'block_coupon', $a);
        }
        $a->typs = array_unique($a->typs);
        if (count($a->typs) > 0) {
            $b = [];
            foreach ($a->typs as $typ) {
                $b[] = get_string('coupon:type:' . $typ, 'block_coupon');
            }
            $this->errorlist[] = get_string('err:coupon:editcourses:ntypes', 'block_coupon', implode(', ', $b));
        }
    }

    /**
     * Returns context where this form is used
     *
     * This context is validated in {@see \external_api::validate_context()}
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * Example:
     *     $cmid = $this->optional_param('cmid', 0, PARAM_INT);
     *     return context_module::instance($cmid);
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $this->initialize();
        return context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     * Sometimes permission check may depend on the action and/or id of the entity.
     * If necessary, form data is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * Example:
     *     require_capability('dosomething', $this->get_context_for_dynamic_submission());
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('block/coupon:administration', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * Example:
     *     $data = $this->get_data();
     *     file_postupdate_standard_filemanager($data, ....);
     *     api::save_entity($data); // Save into the DB, trigger event, etc.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $DB;
        $this->initialize();
        $data = $this->get_data();

        foreach ($data->id as $id) {
            $coupon = $this->coupons[$id];
            // Unlink old.
            $DB->delete_records('block_coupon_courses', ['couponid' => $coupon->id]);
            // Inject new.
            foreach ($data->courses as $cid) {
                $link = ['couponid' => $coupon->id, 'courseid' => $cid];
                $DB->insert_record('block_coupon_courses', $link);
            }
        }
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $id = $this->optional_param('id', 0, PARAM_INT);
     *     $data = api::get_entity($id); // For example, retrieve a row from the DB.
     *     file_prepare_standard_filemanager($data, ...);
     *     $this->set_data($data);
     */
    public function set_data_for_dynamic_submission(): void {
        $this->initialize();
        // No-op, it's done in the definition....
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * If the form has arguments (such as 'id' of the element being edited), the URL should
     * also have respective argument.
     *
     * Example:
     *     $id = $this->optional_param('id', 0, PARAM_INT);
     *     return new moodle_url('/my/page/where/form/is/used.php', ['id' => $id]);
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $this->initialize();
        return new \moodle_url('/blocks/coupon/view/coupons.php');
    }

    /**
     * Checks if a parameter was passed in the previous form submission.
     * We're REALLY missing this in Moodle's dynamic forms, just like "required_param".
     *
     * @param string $name the name of the page parameter we want, for example 'id' or 'element[sub][13]'
     * @param string $type expected type of parameter
     * @return mixed
     */
    public function required_param_array($name, $type) {
        $nameparsed = [];
        // Convert element name into a sequence of keys, for example 'element[sub][13]' -> ['element', 'sub', '13'].
        parse_str($name . '=1', $nameparsed);
        $keys = [];
        while (is_array($nameparsed)) {
            $key = key($nameparsed);
            $keys[] = $key;
            $nameparsed = $nameparsed[$key];
        }

        // Search for the element first in $this->_ajaxformdata, then in $_POST and then in $_GET.
        if (($values = $this->get_array_value_by_keys($this->_ajaxformdata ?? [], $keys)) !== null ||
            ($values = $this->get_array_value_by_keys($_POST, $keys)) !== null ||
            ($values = $this->get_array_value_by_keys($_GET, $keys)) !== null) {

            if (!is_array($values)) {
                throw new \moodle_exception('missingparam', '', '', $name);
            }

            $result = [];
            foreach ($values as $key => $value) {
                if (!preg_match('/^[a-z0-9_-]+$/i', $key)) {
                    debugging(
                        "Invalid key name in required_param_array() detected: {$key}, parameter: {$name}",
                    );
                    continue;
                }
                $result[$key] = clean_param($value, $type);
            }

            return $result;
        }

        throw new \moodle_exception('missingparam', '', '', $name);
    }

}
