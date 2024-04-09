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
 * My reports controller
 *
 * File         reports.php
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
 * block_coupon\manager\my\reports
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports {

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
        $config = get_config('block_coupon');
        if (empty($config->enablemyprogressforru)) {
            echo $this->output->header();
            echo "<div class=\"alert alert-danger\">" .
                    get_string('err:tab:enablemyprogressforru', 'block_coupon') .
                    "</div>";
            echo $this->output->footer();
        }

        $action = optional_param('action', null, PARAM_ALPHA);
        switch ($action) {
            case 'list':
            default:
                $this->process_overview();
                break;
        }
    }

    /**
     * Display user overview table
     */
    protected function process_overview() {
        global $USER;
        $table = new \block_coupon\tables\myreport($USER->id);
        $table->baseurl = $this->get_url();

        $filtering = new \block_coupon\tablefilters\report($table->baseurl);
        $table->set_filtering($filtering);

        $table->is_downloadable(true);
        $table->show_download_buttons_at([TABLE_P_BOTTOM, TABLE_P_TOP]);
        $download = optional_param('download', '', PARAM_ALPHA);
        if (!empty($download)) {
            $table->is_downloading($download, 'couponreport', 'couponreport');
            $table->render(25, true);
        } else {
            echo $this->output->header();
            echo html_writer::start_div('block-coupon-container');
            echo html_writer::start_div();
            echo $this->renderer->get_my_tabs($this->page->context, 'cpmyreports', $this->page->url->params());
            echo html_writer::end_div();
            echo html_writer::start_div();
            $filtering->display_add();
            $filtering->display_active();
            echo html_writer::end_div();
            echo $table->render(50);
            echo html_writer::end_div();
            echo $this->output->footer();
        }
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

}
