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
 * Renderer for the coupon block.
 *
 * File         renderer.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

use block_coupon\helper;
use block_coupon\coupon\generatoroptions;
use block_coupon\coupon\generator;


/**
 * Renderer for the coupon block.
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      R.J. van Dongen <rogier@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_coupon_renderer extends plugin_renderer_base {

    /**
     * Return rendered request details
     * @param stdClass $request
     */
    public function requestdetails($request) {
        $widget = new \block_coupon\output\component\requestdetails($request);
        return $this->render_requestdetails($widget);
    }

    /**
     * Render request details
     * @param \block_coupon\output\component\requestdetails $widget
     */
    public function render_requestdetails(\block_coupon\output\component\requestdetails $widget) {
        $context = $widget->export_for_template($this);
        return $this->render_from_template('block_coupon/requestdetails', $context);
    }

    /**
     * Render image upload page (including header / footer).
     *
     * @param int $id block instance id
     * @return string
     */
    public function page_logomanager($id) {
        $form = new \block_coupon\forms\logo($this->page->url);
        if ($form->process_store()) {
            redirect($this->page->url);
        }
        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, 'wzcouponimage', array('id' => $id));
        $out .= html_writer::end_div();
        $out .= $form->render();
        $out .= html_writer::end_div();

        $out .= $this->footer();
        return $out;
    }

    /**
     * Render unused coupon page (including header / footer).
     *
     * @param int $id block instance id
     * @param int $ownerid the owner id of the coupons. Set 0 or NULL to see all.
     * @return string
     */
    public function page_unused_coupons($id, $ownerid = null) {
        $filter = \block_coupon\tables\coupons::UNUSED;
        return $this->page_coupons($id, $filter, $ownerid);
    }

    /**
     * Render used coupon page (including header / footer).
     *
     * @param int $id block instance id
     * @param int $ownerid the owner id of the coupons. Set 0 or NULL to see all.
     * @return string
     */
    public function page_used_coupons($id, $ownerid = null) {
        $filter = \block_coupon\tables\coupons::USED;
        return $this->page_coupons($id, $filter, $ownerid);
    }

    /**
     * Render coupon page (including header / footer).
     *
     * @param int $id block instance id
     * @param int $filter table filter
     * @param int $ownerid the owner id of the coupons. Set 0 or NULL to see all.
     * @return string
     */
    protected function page_coupons($id, $filter, $ownerid = null) {
        // Actions anyone?
        $action = optional_param('action', null, PARAM_ALPHA);
        if ($action === 'delete') {
            global $DB;
            require_sesskey();
            $id = required_param('itemid', PARAM_INT);
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records('block_coupon', array('id' => $id));
            $DB->delete_records('block_coupon_cohorts', array('couponid' => $id));
            $DB->delete_records('block_coupon_groups', array('couponid' => $id));
            $DB->delete_records('block_coupon_courses', array('couponid' => $id));
            $DB->delete_records('block_coupon_errors', array('couponid' => $id));
            $DB->commit_delegated_transaction($transaction);
            redirect($this->page->url, get_string('coupon:deleted', 'block_coupon'));
        }
        // Table instance.
        $table = new \block_coupon\tables\coupons($ownerid, $filter);
        $table->baseurl = $this->page->url;

        $filtering = new \block_coupon\tablefilters\coupons($this->page->url);
        $table->set_filtering($filtering);

        $table->is_downloadable(true);
        $table->show_download_buttons_at(array(TABLE_P_BOTTOM, TABLE_P_TOP));
        $download = optional_param('download', '', PARAM_ALPHA);
        if (!empty($download)) {
            $table->is_downloading($download, 'coupons', 'coupons');
            $table->render(25);
            exit;
        }

        $selectedtab = '';
        switch ($filter) {
            case \block_coupon\tables\coupons::UNUSED:
                $selectedtab = 'cpunused';
                break;
            case \block_coupon\tables\coupons::USED:
                $selectedtab = 'cpused';
                break;
        }

        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, $selectedtab, array('id' => $id));
        $out .= html_writer::end_div();
        ob_start();
        $filtering->display_add();
        $filtering->display_active();
        $table->render(25);
        $out .= ob_get_clean();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Render report page (including header / footer).
     *
     * @param int $id block instance id
     * @param int $ownerid the owner id of the coupons. Set 0 or NULL to see all.
     * @return string
     */
    public function page_report($id, $ownerid = null) {
        // Table instance.
        $table = new \block_coupon\tables\report($ownerid);
        $table->baseurl = $this->page->url;

        $filtering = new \block_coupon\tablefilters\report($this->page->url);
        $table->set_filtering($filtering);

        $table->is_downloadable(true);
        $table->show_download_buttons_at(array(TABLE_P_BOTTOM, TABLE_P_TOP));
        $download = optional_param('download', '', PARAM_ALPHA);
        if (!empty($download)) {
            $table->is_downloading($download, 'couponreport', 'couponreport');
            $table->render(25, true);
            exit;
        }

        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, 'cpreport', array('id' => $id));
        $out .= html_writer::end_div();
        ob_start();
        $filtering->display_add();
        $filtering->display_active();
        $table->render(25);
        $out .= ob_get_clean();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Render error report page (including header / footer).
     *
     * @param int $id block instance id
     * @param int $ownerid the owner id of the coupons. Set 0 or NULL to see all.
     * @return string
     */
    public function page_error_report($id, $ownerid = null) {
        // Table instance.
        $table = new \block_coupon\tables\errorreport($ownerid);
        $table->baseurl = $this->page->url;

        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, 'cperrorreport', array('id' => $id));
        $out .= html_writer::end_div();
        ob_start();
        $table->render(25);
        $out .= ob_get_clean();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Render coupon generator page 1 (including header / footer).
     *
     * @return string
     */
    public function page_coupon_generator() {
        global $USER, $CFG;
        // Create form.
        $mform = new block_coupon\forms\coupon\generator($this->page->url);

        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            // Load generator options.
            $generatoroptions = generatoroptions::from_session();
            $generatoroptions->ownerid = $USER->id;
            $generatoroptions->type = ($data->coupon_type['type'] == 0) ? generatoroptions::COURSE : generatoroptions::COHORT;
            $generatoroptions->logoid = $data->logo;
            if (!empty($data->batchid)) {
                $generatoroptions->batchid = $data->batchid;
            }
            // Serialize generatoroptions to session.
            $generatoroptions->to_session();
            // And redirect user to next page.
            $params = array('id' => $this->page->url->param('id'));
            $redirect = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/generate_coupon_step_two.php', $params);
            redirect($redirect);
        }

        generatoroptions::clean_session();
        $out = '';
        $out .= $this->get_coupon_form_page($mform);
        return $out;
    }

    /**
     * Render coupon generator page 2 (including header / footer).
     *
     * @return string
     */
    public function page_coupon_generator_step2() {
        global $DB, $CFG;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Depending on our data we'll get the right form.
        if ($generatoroptions->type == generatoroptions::COURSE) {
            $mform = new \block_coupon\forms\coupon\generator\selectcourse($this->page->url);
        } else {
            $mform = new \block_coupon\forms\coupon\generator\selectcohorts($this->page->url);
        }

        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            if ($generatoroptions->type == generatoroptions::COURSE) {
                $generatoroptions->courses = $data->coupon_courses;
                $generatoroptions->roleid = $data->coupon_role;

                $hasgroups = false;
                foreach ($data->coupon_courses as $courseid) {
                    $groups = $DB->get_records("groups", array('courseid' => $courseid));
                    if (count($groups) > 0) {
                        $hasgroups = true;
                    }
                }

                $nextpage = ($hasgroups) ? 'generate_coupon_step_three' : $nextpage = 'generate_coupon_step_four';
            } else {
                $generatoroptions->cohorts = $data->coupon_cohorts;
                $nextpage = 'generate_coupon_step_three';
            }

            // Serialize generatoroptions to session.
            $generatoroptions->to_session();

            $params = array('id' => $this->page->url->param('id'));
            $url = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/' . $nextpage . '.php', $params);
            redirect($url);
        }

        $out = '';
        $out .= $this->get_coupon_form_page($mform);
        return $out;
    }

    /**
     * Render coupon generator page 3 (including header / footer).
     *
     * @return string
     */
    public function page_coupon_generator_step3() {
        global $DB, $CFG;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Depending on our data we'll get the right form.
        if ($generatoroptions->type == generatoroptions::COURSE) {
            $mform = new \block_coupon\forms\coupon\generator\selectgroups($this->page->url);
        } else {
            $mform = new block_coupon\forms\coupon\generator\selectcohortcourses($this->page->url);
        }

        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            // Save param, its only about course or cohorts.
            if ($generatoroptions->type == generatoroptions::COURSE) {
                // Add selected groups to session.
                if (isset($data->coupon_groups)) {
                    $generatoroptions->groups = $data->coupon_groups;
                }
            } else {
                // Check if a course is selected.
                if (isset($data->connect_courses)) {
                    // Get required records.
                    $enrol = enrol_get_plugin('cohort');
                    $role = helper::get_default_coupon_role();
                    // Loop over all cohorts.
                    foreach ($data->connect_courses as $cohortid => $courses) {
                        // Loop over all courses selected for this cohort.
                        foreach ($courses as $courseid) {
                            // And enroll the shizzle.
                            $course = $DB->get_record('course', array('id' => $courseid));
                            $enrol->add_instance($course, array('customint1' => $cohortid, 'roleid' => $role->id));
                        }
                    }
                }
            }

            // Serialize generatoroptions to session.
            $generatoroptions->to_session();

            $params = array('id' => $this->page->url->param('id'));
            $url = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/generate_coupon_step_four.php', $params);
            redirect($url);
        }

        $out = '';
        $out .= $this->get_coupon_form_page($mform);
        return $out;
    }

    /**
     * Render coupon generator page 4 (including header / footer).
     *
     * @return string
     */
    public function page_coupon_generator_step4() {
        global $DB, $USER, $CFG;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Depending on our data we'll get the right form.
        if ($generatoroptions->type == generatoroptions::COURSE) {
            $mform = new \block_coupon\forms\coupon\generator\confirmcourse($this->page->url);
        } else {
            $mform = new \block_coupon\forms\coupon\generator\confirmcohorts($this->page->url);
        }

        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            // These settings are always the same.
            $generatoroptions->redirecturl = (empty($data->redirect_url)) ? null : $data->redirect_url;
            $generatoroptions->enrolperiod = (empty($data->enrolment_period)) ? null : $data->enrolment_period;
            $generatoroptions->renderqrcode = (isset($data->renderqrcode) && $data->renderqrcode) ? true : false;

            // If we're generating based on csv we'll redirect first to confirm the csv input.
            if ($data->showform == 'csv') {

                $generatoroptions->senddate = $data->date_send_coupons;
                $generatoroptions->csvrecipients = $mform->get_file_content('coupon_recipients');
                $generatoroptions->emailbody = $data->email_body['text'];

                // Serialize generatoroptions to session.
                $generatoroptions->to_session();

                // To the extra step.
                $params = array('id' => $this->page->url->param('id'));
                $url = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/generate_coupon_step_five.php', $params);
                redirect($url);
            }

            // If we're generating based on manual csv input.
            if ($data->showform == 'manual') {
                $generatoroptions->senddate = $data->date_send_coupons_manual;
                $generatoroptions->emailbody = $data->email_body_manual['text'];
                // We'll get users right away.
                $generatoroptions->recipients = helper::get_recipients_from_csv($data->coupon_recipients_manual);
            }

            // If we're generating based on 'amount' of coupons.
            if ($data->showform == 'amount') {
                // Save last settings in sessions.
                $generatoroptions->amount = $data->coupon_amount;
                $generatoroptions->emailto = (!empty($data->use_alternative_email)) ? $data->alternative_email : $USER->email;
                $generatoroptions->generatesinglepdfs = (isset($data->generate_pdf) && $data->generate_pdf) ? true : false;
                $generatoroptions->generatecodesonly = (isset($data->generatecodesonly) && $data->generatecodesonly) ? true : false;
            }

            // Now that we've got all the coupons.
            $generator = new generator();
            $result = $generator->generate_coupons($generatoroptions);
            if ($result !== true) {
                // Means we've got an error.
                // Don't know yet what we're gonne do in this situation. Maybe mail to supportuser?
                echo "<p>An error occured while trying to generate the coupons. Please contact support.</p>";
                echo "<pre>" . implode("\n", $result) . "</pre>";
                die();
            }

            if ($data->showform == 'amount') {
                // Only send if not opted to only generate the codes!
                if (!$generatoroptions->generatecodesonly) {
                    // Generate and send off.
                    $coupons = $DB->get_records_list('block_coupon', 'id', $generator->get_generated_couponids());
                    helper::mail_coupons($coupons, $generatoroptions->emailto, $generatoroptions->generatesinglepdfs);
                }
                generatoroptions::clean_session();
                redirect(new moodle_url($CFG->wwwroot . '/my'), get_string('coupons_sent', 'block_coupon'));
            } else {
                redirect(new moodle_url($CFG->wwwroot . '/my'), get_string('coupons_ready_to_send', 'block_coupon'));
            }
        }

        $out = '';
        $out .= $this->get_coupon_form_page($mform);
        return $out;
    }

    /**
     * Render coupon generator page 5 (including header / footer).
     *
     * @return string
     */
    public function page_coupon_generator_step5() {
        global $CFG;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Create form.
        $mform = new \block_coupon\forms\coupon\generator\extra($this->page->url);

        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            // Get recipients.
            $generatoroptions->recipients = helper::get_recipients_from_csv($data->coupon_recipients);

            // Now that we've got all information we'll create the coupon objects.
            $generator = new generator();
            $result = $generator->generate_coupons($generatoroptions);

            if ($result !== true) {
                // Means we've got an error.
                // Don't know yet what we're gonne do in this situation. Maybe mail to supportuser?
                echo "<p>An error occured while trying to generate the coupons. Please contact support.</p>";
                echo "<pre>" . implode("\n", $result) . "</pre>";
                die();
            }

            // Finish.
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/my'), get_string('coupons_ready_to_send', 'block_coupon'));
        }

        $out = '';
        $out .= $this->get_coupon_form_page($mform);
        return $out;
    }

    /**
     * Get form page output (includes header/footer).
     *
     * @param \moodleform $mform
     */
    protected function get_coupon_form_page($mform) {
        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, 'wzcoupons', array('id' => $this->page->url->param('id')));
        $out .= html_writer::end_div();
        $out .= $mform->render();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Create a tab object with a nice image view, instead of just a regular tabobject
     *
     * @param string $id unique id of the tab in this tree, it is used to find selected and/or inactive tabs
     * @param string $pix image name
     * @param string $component component where the image will be looked for
     * @param string|moodle_url $link
     * @param string $text text on the tab
     * @param string $title title under the link, by defaul equals to text
     * @param bool $linkedwhenselected whether to display a link under the tab name when it's selected
     * @return \tabobject
     */
    protected function create_pictab($id, $pix = null, $component = null, $link = null,
            $text = '', $title = '', $linkedwhenselected = false) {
        $img = '';
        if ($pix !== null) {
            $img = $this->image_icon($pix, $title, empty($component) ? 'moodle' : $component, ['class' => 'icon']);
        }
        return new \tabobject($id, $link, $img . $text, empty($title) ? $text : $title, $linkedwhenselected);
    }

    /**
     * Generate navigation tabs
     *
     * @param \context $context current context to work in (needed to determine capabilities).
     * @param string $selected selected tab
     * @param array $params any paramaters needed for the base url
     */
    public function get_tabs($context, $selected, $params = array()) {
        global $CFG;
        $tabs = array();
        // Add exclusions.
        $tabs[] = $this->create_pictab('wzcoupons', 'e/print', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/generate_coupon.php', $params),
                get_string('tab:wzcoupons', 'block_coupon'));
        $tabs[] = $this->create_pictab('wzcouponimage', 'e/insert_edit_image', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/managelogos.php', $params),
                get_string('tab:wzcouponimage', 'block_coupon'));
        $requesttab = $this->create_pictab('cprequestadmin', 'i/checkpermissions', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/requests/admin.php', $params),
                get_string('tab:requests', 'block_coupon'));
        $requesttab->subtree[] = $this->create_pictab('cprequestusers', 'i/users', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/requests/admin.php', $params + ['action' => 'users']),
                get_string('tab:requestusers', 'block_coupon'));
        $requesttab->subtree[] = $this->create_pictab('cprequests', 'e/help', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/requests/admin.php', $params + ['action' => 'requests']),
                get_string('tab:requests', 'block_coupon'));
        $tabs[] = $requesttab;
        $tabs[] = $this->create_pictab('cpreport', 'i/report', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/reports.php', $params),
                get_string('tab:report', 'block_coupon'));
        $tabs[] = $this->create_pictab('cpunused', 'i/completion-manual-n', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/coupon_view.php',
                array_merge($params, array('tab' => 'unused'))),
                get_string('tab:unused', 'block_coupon'));
        $tabs[] = $this->create_pictab('cpused', 'i/completion-manual-enabled', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/coupon_view.php',
                array_merge($params, array('tab' => 'used'))),
                get_string('tab:used', 'block_coupon'));
        $tabs[] = $this->create_pictab('cperrorreport', 'i/warning', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/errorreport.php',
                array_merge($params, array('tab' => 'cperrorreport'))),
                get_string('tab:errors', 'block_coupon'));
        $tabs[] = $this->create_pictab('cpcleaner', 'e/cleanup_messy_code', '',
                new \moodle_url($CFG->wwwroot . '/blocks/coupon/view/cleanup.php',
                array_merge($params, array('tab' => 'cpcleaner'))),
                get_string('tab:cleaner', 'block_coupon'));
        return $this->tabtree($tabs, $selected);
    }

    /**
     * render the clanup form page.
     *
     * @param int $owner userid of the coupon owner
     * @return string
     */
    public function page_cleanup($owner) {
        global $CFG;
        $redirect = new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id));
        // Create form.
        $mform = new block_coupon\forms\coupon\cleanup($this->page->url, array('ownerid' => $owner));

        if ($mform->is_cancelled()) {
            redirect($redirect);
        } else if ($data = $mform->get_data()) {
            // Delete coupons.
            helper::cleanup_coupons($data);
            redirect($redirect);
        }

        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= html_writer::start_div();
        $out .= $this->get_tabs($this->page->context, 'cpcleaner', array('id' => $this->page->url->param('id')));
        $out .= html_writer::end_div();
        $out .= $mform->render();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Get form page output (includes header/footer).
     *
     * @param \moodleform $mform
     */
    protected function get_extendenrolment_form_page($mform) {
        $out = '';
        $out .= $this->header();
        $out .= html_writer::start_div('block-coupon-container');
        $out .= $mform->render();
        $out .= html_writer::end_div();
        $out .= $this->footer();
        return $out;
    }

    /**
     * Start enrlment extension wizard page.
     *
     * @param int|null $courseid
     * @return string
     */
    public function page_extendenrolment_wizard($courseid = null) {
        global $USER, $CFG, $DB;
        // If we have a valid course ID, continue to page 2.
        if (!empty($courseid) && $courseid > 1) {
            // Validate course.
            if (!$DB->record_exists('course', array('id' => $courseid))) {
                redirect(new moodle_url($CFG->wwwroot . '/my'),
                        get_string('generator:extendenrolment:invalidcourse', 'block_coupon'));
            }
            // We have a valid course. Prepare generator options and continue to page 2.
            generatoroptions::clean_session();
            $generatoroptions = new generatoroptions();
            $generatoroptions->ownerid = $USER->id;
            $generatoroptions->type = generatoroptions::ENROLEXTENSION;
            $generatoroptions->courses = array($courseid);
            $generatoroptions->to_session();
            $params = array('id' => $this->page->url->param('id'), 'cid' => $this->page->course->id);
            $redirect = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/extendenrolment_step2.php', $params);
            redirect($redirect);
        }

        // Create form.
        $options = array('coursemultiselect' => false);
        $mform = new block_coupon\forms\coupon\extendenrolment($this->page->url, $options);
        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            // Load generator options.
            $generatoroptions = generatoroptions::from_session();
            $generatoroptions->ownerid = $USER->id;
            $generatoroptions->type = generatoroptions::ENROLEXTENSION;
            // Serialize generatoroptions to session.
            $generatoroptions->to_session();
            // And redirect user to next page.
            $params = array('id' => $this->page->url->param('id'), 'cid' => $this->page->course->id);
            $redirect = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/extendenrolment_step2.php', $params);
            redirect($redirect);
        }

        generatoroptions::clean_session();
        $out = '';
        $out .= $this->get_extendenrolment_form_page($mform);
        return $out;
    }

    /**
     * Render enrolment extension generator page 2 (including header / footer).
     *
     * @return string
     */
    public function page_extendenrolment_wizard_step2() {
        global $CFG, $DB;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Depending on our data we'll get the right form.
        $options = array('generatoroptions' => $generatoroptions);
        $mform = new \block_coupon\forms\coupon\extendenrolmentstep2($this->page->url, $options);
        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
        } else if ($data = $mform->get_data()) {
            if ((bool)$data->abort) {
                generatoroptions::clean_session();
                redirect(new moodle_url($CFG->wwwroot . '/course/view.php', array('id' => $this->page->course->id)));
                exit; // Never reached.
            }
            // Set logo.
            $generatoroptions->logoid = $data->logo;
            // Set user(s).
            $generatoroptions->amount = count($data->extendusers);
            $generatoroptions->extendusers = $data->extendusers;
            $generatoroptions->enrolperiod = $data->enrolperiod;
            $generatoroptions->generatesinglepdfs = $data->generate_pdf;
            $generatoroptions->renderqrcode = (isset($data->renderqrcode) && $data->renderqrcode) ? true : false;
            $generatoroptions->redirecturl = $data->redirect_url;
            $generatoroptions->emailto = (!empty($data->use_alternative_email)) ? $data->alternative_email : null;
            if (empty($data->use_alternative_email)) {
                $generatoroptions->emailto = null;
                // Load recipients!
                $generatoroptions->recipients = [];
                $fields = 'id, email, ' . get_all_user_name_fields(true);
                $users = $DB->get_records_list('user', 'id', $data->extendusers, '', $fields);
                foreach ($users as $user) {
                    $generatoroptions->recipients[] = (object) array(
                        'email' => $user->email,
                        'name' => fullname($user),
                        'gender' => '',
                    );
                }
                // Force seperate coupons!
                $generatoroptions->generatesinglepdfs = true;
            } else {
                $generatoroptions->emailto = $data->alternative_email;
            }
            $generatoroptions->emailbody = $data->email_body_manual['text'];

            // Serialize generatoroptions to session.
            $generatoroptions->to_session();
            $params = array('id' => $this->page->url->param('id'), 'cid' => $this->page->course->id);
            $url = new moodle_url($CFG->wwwroot . '/blocks/coupon/view/extendenrolment_step3.php', $params);
            redirect($url);
        }

        $out = '';
        $out .= $this->get_extendenrolment_form_page($mform);
        return $out;
    }

    /**
     * Render enrolment extension generator page 3 (including header / footer).
     *
     * @return string
     */
    public function page_extendenrolment_wizard_step3() {
        global $CFG, $DB;
        // Make sure sessions are still alive.
        generatoroptions::validate_session();
        // Load options.
        $generatoroptions = generatoroptions::from_session();

        // Depending on our data we'll get the right form.
        $options = array('generatoroptions' => $generatoroptions);
        $mform = new \block_coupon\forms\coupon\extendenrolmentconfirm($this->page->url, $options);
        if ($mform->is_cancelled()) {
            generatoroptions::clean_session();
            $conditions = array('id' => $this->page->course->id);
            redirect(new moodle_url($CFG->wwwroot . '/course/view.php', $conditions));
        } else if ($data = $mform->get_data()) {
            // Now that we've got all information we'll create the coupon objects.
            $generator = new generator();
            $result = $generator->generate_coupons($generatoroptions);

            if ($result !== true) {
                // Means we've got an error.
                // Don't know yet what we're gonne do in this situation. Maybe mail to supportuser?
                echo "<p>An error occured while trying to generate the coupons. Please contact support.</p>";
                echo "<pre>" . implode("\n", $result) . "</pre>";
                die();
            }

            // Finish.
            generatoroptions::clean_session();
            // We will only use direct sending if we're sending off to an alternative email address.
            if (empty($generatoroptions->emailto)) {
                redirect(new moodle_url($CFG->wwwroot . '/my'), get_string('coupons_ready_to_send', 'block_coupon'));
            } else {
                // Generate and send off.
                $coupons = $DB->get_records_list('block_coupon', 'id', $generator->get_generated_couponids());
                helper::mail_coupons($coupons, $generatoroptions->emailto, $generatoroptions->generatesinglepdfs);
                generatoroptions::clean_session();
                redirect(new moodle_url($CFG->wwwroot . '/my'), get_string('coupons_sent', 'block_coupon'));
            }
        }

        $out = '';
        $out .= $this->get_extendenrolment_form_page($mform);
        return $out;
    }

}