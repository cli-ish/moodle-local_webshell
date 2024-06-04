<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External API
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webshell\external;

use core_external\external_multiple_structure;
use invalid_parameter_exception;
use local_webshell\executor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

if ($CFG->version < 2022112999 && !class_exists('\\core_external\\external_api')) { // Moodle < 4.2.
    class_alias('\\external_api', '\\core_external\\external_api');
    class_alias('\\external_function_parameters', '\\core_external\\external_function_parameters');
    class_alias('\\external_single_structure', '\\core_external\\external_single_structure');
    class_alias('\\external_multiple_structure', '\\core_external\\external_multiple_structure');
    class_alias('\\external_value', '\\core_external\\external_value');
}

/**
 * External API class to run comands
 */
class run extends \core_external\external_api {

    /**
     * API parameter descriptions
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'command' => new \core_external\external_value(
                PARAM_RAW, 'Shell command to run', VALUE_REQUIRED, '', NULL_NOT_ALLOWED
            ),
        ]);
    }

    /**
     * API return descriptions
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'result' => new \core_external\external_value(PARAM_RAW, 'Result', VALUE_REQUIRED),
            'user' => new \core_external\external_value(PARAM_RAW, 'Result', VALUE_REQUIRED),
            'workingdir' => new \core_external\external_value(PARAM_RAW, 'Result', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute API
     *
     * @param string $command
     * @return array
     * @throws invalid_parameter_exception
     * @throws \moodle_exception
     */
    public static function execute(string $command): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['command' => $command]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/webshell:runshell', $context);

        $executor = new executor();

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

        $result = $executor->execute($params['command']);

        $path = $result->get_workingdir();
        if ($path !== $executor->get_working_dir()) {
            set_user_preference('local_webshell_current_dir', $path);
        }

        // Todo: Environment variables?
        return [
            'result' => base64_encode($result->get_result()),
            'user' => $result->get_user(),
            'workingdir' => $path,
        ];
    }

    /**
     * hinting parameter descriptions
     *
     * @return \core_external\external_function_parameters
     */
    public static function hinting_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([
            'value' => new \core_external\external_value(
                PARAM_RAW, 'current string for autocomplete', VALUE_REQUIRED, '', NULL_NOT_ALLOWED
            ),
            'type' => new \core_external\external_value(
                PARAM_ALPHANUM, 'file or binary type', VALUE_REQUIRED, 'binary', NULL_NOT_ALLOWED
            ),
        ]);
    }

    /**
     * hinting return descriptions
     *
     * @return \core_external\external_single_structure
     */
    public static function hinting_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'matches' => new external_multiple_structure(new \core_external\external_value(PARAM_TEXT, 'matches')),
            'user' => new \core_external\external_value(PARAM_RAW, 'Result', VALUE_REQUIRED),
            'workingdir' => new \core_external\external_value(PARAM_RAW, 'Result', VALUE_REQUIRED),
        ]);
    }

    /**
     * hinting API
     *
     * @param string $value
     * @param string $type
     * @return array
     * @throws invalid_parameter_exception
     * @throws \coding_exception|\moodle_exception
     */
    public static function hinting(string $value, string $type = 'binary'): array {
        $params = self::validate_parameters(self::hinting_parameters(),
            ['value' => $value, 'type' => $type]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/webshell:runshell', $context);

        $executor = new executor();

        $path = get_user_preferences('local_webshell_current_dir', $executor->get_working_dir());
        $ok = $executor->cwd($path);
        if (!$ok) {
            // Perhaps the dir was deleted or moved?
            set_user_preference('local_webshell_current_dir', $executor->get_working_dir());
        }

        $result = $executor->hinting($params['value'], $params['type']);
        // Todo: Environment variables?

        return [
            'matches' => $result,
            'user' => $executor->combined_user_hostname(),
            'workingdir' => $path,
        ];
    }
    // Todo: Reset workingdir to path + reset environment?
}
