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
 * Print webshell page.
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/webshell:runshell', context_system::instance());
admin_externalpage_setup('local_webshell', '', null);

$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'local_webshell'));

$reset = optional_param('reset', false, PARAM_BOOL);
if ($reset && confirm_sesskey()) {
    set_user_preference('local_webshell_current_dir', null);
    redirect(new moodle_url('/local/webshell/run.php'));
    exit();
}

$executor = new \local_webshell\executor();
$path = get_user_preferences('local_webshell_current_dir', null);
if ($path === null) {
    $path = $executor->get_working_dir();
    set_user_preference('local_webshell_current_dir', $executor->get_working_dir());
}
$ok = $executor->cwd($path);
if (!$ok) {
    // Perhaps the dir was deleted or moved?
    set_user_preference('local_webshell_current_dir', $executor->get_working_dir());
}

$userposition = get_user_preferences('local_webshell_current_dir', $executor->get_working_dir());

// Todo: implement a reset function (button to reset workingdir and environments)
// Todo: think about a way to use environment variables. export all and save them too?

$content = [
    'sesskey' => sesskey(),
    'username' => $executor->combined_user_hostname(),
    'workingdir' => $userposition,
    'image' => (new moodle_url('/local/webshell/pix/icon.svg'))->out(false),
    'reseturl' => (new moodle_url('/local/webshell/run.php', ['reset' => 1, 'sesskey' => sesskey()]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_webshell/shell', $content);
echo $OUTPUT->footer();
