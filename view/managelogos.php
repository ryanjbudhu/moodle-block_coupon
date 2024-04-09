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
 * Logo manager.
 *
 * File         managelogos.php
 * Encoding     UTF-8
 *
 * @package     block_coupon
 *
 * @copyright   Sebsoft.nl
 * @author      RvD <helpdesk@sebsoft.nl>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Login_check is done in couponpage class.
// @codingStandardsIgnoreLine
require_once(dirname(__FILE__) . '/../../../config.php');

use block_coupon\couponpage;

$title = get_string('view:uploadimage:title', 'block_coupon');
$heading = get_string('view:uploadimage:heading', 'block_coupon');

$page = couponpage::setup(
    'block_coupon_view_managelogos',
    $title,
    couponpage::get_view_url('managelogos.php'),
    'block/coupon:generatecoupons',
    \context_system::instance(),
    [
        'pagelayout' => 'report',
        'title' => $title,
        'heading' => $heading,
    ]
);

$renderer = $PAGE->get_renderer('block_coupon');
// Using a manager.
$requestcontroller = new \block_coupon\controller\logomanager($PAGE, $OUTPUT, $renderer);
$requestcontroller->execute_request();
