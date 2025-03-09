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
 * Executor
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webshell;

use context_system;
use local_webshell\event\command_executed;
use local_webshell\pod\exec_result;

/**
 * Executor which hols all dangerous commands.
 *
 * @package     local_webshell
 * @copyright   2024 Vincent Schneider (cli-ish)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class executor {

    /**
     * Try to get hints for a value and a type.
     * Valid types are binary and file.
     * "binary" searches all executeable files in the $PATH.
     * "file" searches for all files in the current directory.
     *
     * @param string $value
     * @param string $type
     * @return array
     * @throws \moodle_exception
     */
    public function hinting(string $value, string $type = 'binary'): array {
        $hintmap = [
            'WINDOWS' => [
                'binary' => 'where *.exe',
                'file' => 'dir /b',
            ],
            'UNIX' => [
                'binary' => '(IFS=:;set -f;find -L $PATH -maxdepth 1 -type f -perm -100 -print;)',
                'file' => 'find . -maxdepth 1',
            ],
        ];
        $cmd = $hintmap[self::is_windows() ? 'WINDOWS' : 'UNIX'][$type == 'binary' ? 'binary' : 'file'];
        $res = $this->run_with_path_check($cmd);
        $res = $this->extract_workingdir($res);
        if ($res === null) {
            return [];
        }
        $result = [];
        $res = explode("\n", $res[0]);
        foreach ($res as $line) {
            if ($line === '') {
                continue;
            }
            $base = basename($line);
            if ($type == 'file' && $line == '.') {
                continue;
            }
            if (substr($base, 0, strlen($value)) === $value) {
                $result[] = $base;
            }
        }
        // Todo: check if this should be logged too?
        return array_unique($result);
    }

    /**
     * Change the working dir to the specified path.
     *
     * @param string $path
     * @return bool
     */
    public function cwd(string $path): bool {
        $real = realpath($path);
        if (!is_dir($real)) {
            return false;
        }
        return chdir($real);
    }

    /**
     * Is current system windows?
     *
     * @return bool
     */
    private static function is_windows(): bool {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /**
     * Execute shell command with all needed value.
     *
     * @param string $cmd
     * @return exec_result
     * @throws \moodle_exception
     */
    public function execute(string $cmd): exec_result {
        $result = $this->run_with_path_check($cmd);
        $res = $this->extract_workingdir($result);

        if ($res != null) {
            [$result, $workingdir] = $res;
        } else {
            $workingdir = $this->get_working_dir();
        }
        $user = $this->combined_user_hostname();

        $event = command_executed::create([
            'context' => context_system::instance(),
            'other' => ['command' => $cmd],
        ]);
        $event->trigger();

        return new exec_result($workingdir, $user, $result);
    }

    /**
     * Combine the user and the hostname.
     *
     * @return string
     * @throws \moodle_exception
     */
    public function combined_user_hostname(): string {
        return $this->get_user_name() . '@' . $this->get_hostname();
    }

    /**
     * Extract and remove the current working dir after a query is done.
     *
     * @param string $result
     * @return array|null
     */
    private function extract_workingdir(string &$result): ?array {
        $sep = self::is_windows() ? "\r\n" : "\n";
        $lines = array_filter(explode($sep, $result), fn($line) => $line !== '');
        if (count($lines) == 0) {
            return null;
        }
        $last = array_pop($lines);
        $result = implode($sep, $lines);
        if (preg_match('/<-moodle-local_webshell->(.*?)<-moodle-local_webshell->/s', $last, $matches)) {
            return [$result, $matches[1]];
        }
        return null;
    }

    /**
     * Get hostname
     *
     * @return string
     * @throws \moodle_exception
     */
    private function get_hostname(): string {
        if (runner::all_function_exist(['gethostname'])) {
            $hostname = gethostname();
            return $hostname;
        }
        $cmd = self::is_windows() ? 'echo %USERDOMAIN%' : 'hostname';
        return $this->runner($cmd);
    }

    /**
     * Get username from system
     *
     * @return string
     * @throws \moodle_exception
     */
    private function get_user_name(): string {
        if (self::is_windows()) {
            if (runner::all_function_exist(['getenv'])) {
                $username = getenv('USERNAME');
                if ($username !== false) {
                    return $username;
                }
            }
        } else {
            if (runner::all_function_exist(['posix_getpwuid', 'posix_geteuid'])) {
                $pwuid = posix_getpwuid(posix_geteuid());
                if ($pwuid !== false) {
                    return $pwuid['name'];
                }
            }
        }
        $username = $this->runner('whoami');
        $username = explode('\\', $username);
        if (count($username) == 2) {
            return $username[1];
        }
        return 'NONE';
    }

    /**
     * Get the current directory
     *
     * @return string
     * @throws \moodle_exception
     */
    public function get_working_dir(): string {
        if (runner::all_function_exist(['getcwd'])) {
            return getcwd();
        }
        $cmd = self::is_windows() ? 'cd' : 'pwd';
        return $this->runner($cmd);
    }

    /**
     * Append path logging args to the cmd command in the hope to fetch the real path after execution.
     *
     * @param string $cmd
     * @return string
     * @throws \moodle_exception
     */
    private function run_with_path_check(string $cmd): string {
        if (self::is_windows()) {
            // Windows!
            $pathcheckstr = "&& (FOR /F \"tokens=*\" %g IN ('CD') do (SET VAR=%g)) &&" .
                ' echo ^<-moodle-local_webshell-^>%VAR%^<-moodle-local_webshell-^>)';
        } else {
            // Linux!
            $pathcheckstr = ';echo "<-moodle-local_webshell->${PWD}<-moodle-local_webshell->")';
        }
        $cmd = "( $cmd $pathcheckstr 2>&1";
        return $this->runner($cmd);
    }

    /**
     * Wrap calls to runner class.
     *
     * @param string $cmd
     * @return string
     * @throws \moodle_exception
     */
    private function runner(string $cmd): string {
        if (!defined('ALLOWED_SHELL_RUN')) {
            define('ALLOWED_SHELL_RUN', true); // Allow call.
        }
        return runner::run($cmd);
    }
}
