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
 * AspirEDU Integration
 *
 * @package    local_aspiredu
 * @copyright  2024 AspirEDU
 * @author     AspirEDU
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/lti/OAuth.php');

use moodle\mod\lti as lti;

$id = required_param('id', PARAM_INT);
$product = required_param('product', PARAM_ALPHA);

if ($id == SITEID) {
    $course = get_site();
    $context = context_system::instance();
    $PAGE->set_context($context);
    require_login();
} else {
    $course = get_course($id);
    $context = context_course::instance($course->id);
    require_login($course);
}

$PAGE->set_url(new moodle_url('/local/aspiredu/lti.php', ['id' => $id, 'product' => $product]));

if ($product == 'dd') {
    require_capability('local/aspiredu:viewdropoutdetective', $context);
    $launchurl = get_config('local_aspiredu', 'dropoutdetectiveurl');
} else {
    require_capability('local/aspiredu:viewinstructorinsight', $context);
    $launchurl = get_config('local_aspiredu', 'instructorinsighturl');
}

$key = get_config('local_aspiredu', 'key');
$secret = get_config('local_aspiredu', 'secret');

// Ensure parameters set.
if ($launchurl && $key && $secret) {

    // Account level.
    if ($id == SITEID) {
        $resourcelinkid = 0;
    } else {
        // Course level.
        $resourcelinkid = $course->id;
    }

    $requestparams = [
        'resource_link_id' => $resourcelinkid,
        'custom_moodle_course_id' => $resourcelinkid,
        'resource_link_title' => $course->fullname,
        'resource_link_description' => $course->summary,
        'user_id' => $USER->id,
        'custom_moodle_user_id' => $USER->id,
        'roles' => 'urn:lti:instrole:ims/lis/Administrator',
        'context_id' => 1,
        'context_label' => $SITE->shortname,
        'context_title' => $SITE->fullname,
        'launch_presentation_locale' => current_language(),
        'ext_lms' => 'moodle-2',
        'tool_consumer_info_product_family_code' => 'moodle',
        'tool_consumer_info_version' => get_config('local_aspiredu', 'version'),
        'oauth_callback' => 'about:blank',
        'lti_version' => 'LTI-1p0',
        'lti_message_type' => 'basic-lti-launch-request',
        'lis_person_name_given' => $USER->firstname,
        'lis_person_name_family' => $USER->lastname,
    ];

    $hmacmethod = new lti\OAuthSignatureMethod_HMAC_SHA1();
    $testconsumer = new lti\OAuthConsumer($key, $secret, null);

    // Sing request.
    $accreq = lti\OAuthRequest::from_consumer_and_token($testconsumer, '', 'POST', $launchurl, $requestparams);
    $accreq->sign_request($hmacmethod, $testconsumer, '');
    $parms = $accreq->get_parameters();

    $endpointurl = new moodle_url($launchurl);
    $endpointparams = $endpointurl->params();
    // Strip querystring params in endpoint url from $parms to avoid duplication.
    if (!empty($endpointparams) && !empty($parms)) {
        foreach (array_keys($endpointparams) as $paramname) {
            if (isset($parms[$paramname])) {
                unset($parms[$paramname]);
            }
        }
    }

    // Print form.
    echo "<form action=\"" . $launchurl . "\" name=\"ltiLaunchForm\" id=\"ltiLaunchForm\" " .
        "method=\"post\" encType=\"application/x-www-form-urlencoded\">\n";
    // Contruct html for the launch parameters.
    foreach ($parms as $key => $value) {
        $key = htmlspecialchars($key);
        $value = htmlspecialchars($value);

        echo "<input type=\"hidden\" name=\"";
        echo $key;
        echo "\" value=\"";
        echo $value;
        echo "\"/>\n";
    }
    echo "</form>\n";

    echo " <script type=\"text/javascript\"> \n" .
        "  //<![CDATA[ \n" .
        "    document.ltiLaunchForm.submit(); \n" .
        "  //]]> \n" .
        " </script> \n";

}
