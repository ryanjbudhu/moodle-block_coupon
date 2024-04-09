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
 * Request admin manager implementation for use with block_coupon
 *
 * File         myrequests.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coupon\controller\my;

use html_writer;

/**
 * block_coupon\manager\myrequests
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @author      Sebastian Berm <sebastian@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requests {

    /**
     * @var \moodle_page
     */
    protected $page;

    /**
     * @var \core_renderer
     */
    protected $output;

    /**
     * @var \block_coupon_renderer
     */
    protected $renderer;

    /**
     * Create new manager instance
     * @param \moodle_page $page
     * @param \core\output_renderer $output
     * @param \core_renderer|null $renderer
     */
    public function __construct($page, $output, $renderer = null) {
        $this->page = $page;
        $this->output = $output;
        $this->renderer = $renderer;
    }

    /**
     * Execute page request
     */
    public function execute_request() {
        $action = optional_param('action', null, PARAM_ALPHA);
        switch ($action) {
            case 'newrequest':
                $this->process_new_request();
                break;
            case 'delete':
                $this->process_delete_request();
                break;
            case 'details':
                $this->process_request_details();
                break;
            case 'batchlist':
                $this->process_batchlist_overview();
                break;
            case 'list':
            default:
                $this->process_request_overview();
                break;
        }
    }

    /**
     * Display user overview table
     */
    protected function process_request_overview() {
        $table = new \block_coupon\tables\myrequests();
        $table->baseurl = new \moodle_url($this->page->url->out());

        echo $this->output->header();
        echo $this->renderer->get_my_tabs($this->page->context, 'myrequests', $this->page->url->params());
        echo '<div class="block_coupon-requestactions">';
        echo '<span class="mr-1">';
        echo implode('</span><span class="mr-1">', $this->determine_requestable_coupon_links());
        echo '</span>';
        echo '</div>';
        echo '<br/>';
        echo $table->render(25);
        echo $this->output->footer();
    }

    /**
     * Display user overview table
     */
    protected function process_batchlist_overview() {
        global $USER;
        // Table instance.
        $table = new \block_coupon\tables\downloadbatchlist($this->page->context, $USER->id);
        $table->baseurl = $this->page->url;

        echo $this->output->header();
        echo html_writer::start_div('block-coupon-container');
        echo html_writer::start_div();
        echo $this->renderer->get_my_tabs($this->page->context, 'cpmybatches', $this->page->url->params());
        echo html_writer::end_div();
        echo $table->render(999999);
        echo html_writer::end_div();
        echo $this->output->footer();
    }

    /**
     * Process delete requestuser instance
     */
    protected function process_delete_request() {
        global $DB;
        $itemid = required_param('itemid', PARAM_INT);
        $redirect = optional_param('redirect', null, PARAM_LOCALURL);
        if (empty($redirect)) {
            $redirect = $this->get_url(['action' => 'list']);
        }

        $params = ['action' => 'delete', 'itemid' => $itemid];
        $url = $this->get_url($params);

        $instance = $DB->get_record('block_coupon_requests', ['id' => $itemid]);
        $user = \core_user::get_user($instance->userid);
        // Assert correct user.
        $this->assert_user($user->id);
        $this->assert_not_final($instance);

        $options = [
            get_string('delete:request:header', 'block_coupon', $user),
            $this->renderer->requestdetails($instance),
            get_string('delete:request:confirmmessage', 'block_coupon', $user),
        ];
        $mform = new \block_coupon\forms\confirmation($url, $options);
        if ($mform->is_cancelled()) {
            redirect($redirect);
        } else if ($data = $mform->get_data()) {
            if ((bool) $data->confirm) {
                $DB->delete_records('block_coupon_requests', ['id' => $itemid]);
            }
            redirect($redirect);
        }
        echo $this->output->header();
        echo $this->renderer->get_my_tabs($this->page->context, 'delete', $this->page->url->params());
        $mform->display();
        echo $this->output->footer();
    }

    /**
     * Process edit requestuser instance
     */
    protected function process_new_request() {
        $coupontype = required_param('t', PARAM_ALPHA);

        switch ($coupontype) {
            case 'course':
                $this->process_new_request_course();
                break;
            case 'cohort':
                $this->process_new_request_cohort();
                break;
        }
    }

    /**
     * Process request for new course coupons
     */
    protected function process_new_request_course() {
        global $DB, $USER;
        $redirect = optional_param('redirect', null, PARAM_LOCALURL);
        if (empty($redirect)) {
            $redirect = $this->get_url(['action' => 'list']);
        }

        $params = ['action' => 'newrequest', 't' => 'course'];
        $url = $this->get_url($params);

        $instance = $DB->get_record('block_coupon_rusers', ['userid' => $USER->id]);

        $mform = new \block_coupon\forms\coupon\request\course($url, [$instance, $USER]);

        if ($mform->is_cancelled()) {
            redirect($redirect);
        } else if ($data = $mform->get_data()) {
            $generatoroptions = new \block_coupon\coupon\generatoroptions();
            $generatoroptions->type = \block_coupon\coupon\generatoroptions::COURSE;
            $generatoroptions->amount = $data->coupon_amount;
            $generatoroptions->ownerid = $instance->userid;
            $generatoroptions->enrolperiod = $data->enrolment_period;
            $generatoroptions->courses = $data->coupon_courses;
            if ($data->use_alternative_email) {
                $generatoroptions->emailto = $data->alternative_email;
            } else {
                $generatoroptions->emailto = $USER->email;
            }
            $generatoroptions->generatesinglepdfs = (bool) $data->generate_pdf;
            $generatoroptions->logoid = $data->logo;
            $generatoroptions->renderqrcode = (bool) $data->renderqrcode;
            $generatoroptions->roleid = $data->coupon_role;

            $record = new \stdClass();
            $record->id = 0;
            $record->userid = $instance->userid;
            $record->configuration = serialize($generatoroptions);
            $record->clientref = $data->clientref;
            $record->denied = 0;
            $record->finalized = 0;
            $record->timecreated = time();
            $record->timemodified = $record->timecreated;
            $record->id = $DB->insert_record('block_coupon_requests', $record);

            // SUCCESS!
            redirect($redirect);
        }

        echo $this->output->header();
        echo $this->renderer->get_my_tabs($this->page->context, 'newrequest', $this->page->url->params());
        echo $mform->render();
        echo $this->output->footer();
    }

    /**
     * Process request for new course coupons
     */
    protected function process_new_request_cohort() {
        global $DB, $USER;
        $redirect = optional_param('redirect', null, PARAM_LOCALURL);
        if (empty($redirect)) {
            $redirect = $this->get_url(['action' => 'list']);
        }

        $params = ['action' => 'newrequest', 't' => 'cohort'];
        $url = $this->get_url($params);

        $instance = $DB->get_record('block_coupon_rusers', ['userid' => $USER->id]);

        $mform = new \block_coupon\forms\coupon\request\cohort($url, [$instance, $USER]);

        if ($mform->is_cancelled()) {
            redirect($redirect);
        } else if ($data = $mform->get_data()) {
            $generatoroptions = new \block_coupon\coupon\generatoroptions();
            $generatoroptions->type = \block_coupon\coupon\generatoroptions::COHORT;
            $generatoroptions->amount = $data->coupon_amount;
            $generatoroptions->ownerid = $instance->userid;
            $generatoroptions->enrolperiod = $data->enrolment_period;
            $generatoroptions->cohorts = $data->coupon_cohorts;
            if ($data->use_alternative_email) {
                $generatoroptions->emailto = $data->alternative_email;
            } else {
                $generatoroptions->emailto = $USER->email;
            }
            $generatoroptions->generatesinglepdfs = (bool) $data->generate_pdf;
            $generatoroptions->logoid = $data->logo;
            $generatoroptions->renderqrcode = (bool) $data->renderqrcode;

            $record = new \stdClass();
            $record->id = 0;
            $record->userid = $instance->userid;
            $record->configuration = serialize($generatoroptions);
            $record->clientref = $data->clientref;
            $record->denied = 0;
            $record->finalized = 0;
            $record->timecreated = time();
            $record->timemodified = $record->timecreated;
            $record->id = $DB->insert_record('block_coupon_requests', $record);

            // SUCCESS!
            redirect($redirect);
        }

        echo $this->output->header();
        echo $this->renderer->get_my_tabs($this->page->context, 'newrequest', $this->page->url->params());
        echo $mform->render();
        echo $this->output->footer();
    }

    /**
     * Process details view
     */
    protected function process_request_details() {
        global $DB;
        $itemid = required_param('itemid', PARAM_INT);
        $instance = $DB->get_record('block_coupon_requests', ['id' => $itemid]);
        $user = \core_user::get_user($instance->userid);
        // Assert correct user.
        $this->assert_user($user->id);

        echo $this->output->header();
        echo $this->renderer->get_my_tabs($this->page->context, 'details', $this->page->url->params());
        echo $this->renderer->requestdetails($instance);
        echo $this->output->footer();
    }

    /**
     * Return new url based on the current page-url
     *
     * @param array $mergeparams
     * @return \moodle_url
     */
    protected function get_url($mergeparams = []) {
        $url = $this->page->url;
        $url->params($mergeparams);
        return $url;
    }

    /**
     * Asser the intended user is the current user.
     *
     * We DO allow site administrators and anyone with the coupon administration capability.
     *
     * @param int $userid
     * @throws \block_coupon\exception
     */
    protected function assert_user($userid) {
        global $USER;
        if (is_siteadmin()) {
            // We'll allow site admins to do everything.
            return;
        }
        if (has_capability('block/coupon:administration', $this->page->context)) {
            // We will also allow anyone with administration rights.
            return;
        }
        if ($USER->id != $userid) {
            throw new \block_coupon\exception('error:myrequests:user');
        }
    }

    /**
     * Asser the intended user is the current user.
     *
     * We DO allow site administrators and anyone with the coupon administration capability.
     *
     * @param stdClass $instance
     * @throws \block_coupon\exception
     */
    protected function assert_not_final($instance) {
        if (is_siteadmin()) {
            // We'll allow site admins to do everything.
            return;
        }
        if (has_capability('block/coupon:administration', $this->page->context)) {
            // We will also allow anyone with administration rights.
            return;
        }
        if ((bool)$instance->finalized) {
            throw new \block_coupon\exception('error:myrequests:finalized');
        }
    }

    /**
     * Determine and return the various links for coupons requests.
     *
     * @return array (of string)
     */
    protected function determine_requestable_coupon_links() {
        global $DB, $USER;
        $instance = $DB->get_record('block_coupon_rusers', ['userid' => $USER->id]);
        $options = json_decode($instance->configuration);
        $links = [];
        if (!empty($options->courses)) {
            $links[] = html_writer::link($this->get_url(['action' => 'newrequest', 't' => 'course']),
                    get_string('str:request:coursecoupons', 'block_coupon'));
        }
        if (!empty($options->cohorts)) {
            $links[] = html_writer::link($this->get_url(['action' => 'newrequest', 't' => 'cohort']),
                    get_string('str:request:cohortcoupons', 'block_coupon'));
        }
        return $links;
    }

}
