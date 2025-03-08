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
        $result = [];
        if (self::is_windows()) {
            if ($type == 'binary') {
                $cmd = 'where *.exe';
            } else {
                $cmd = 'dir /b';
            }
        } else {
            if ($type == 'binary') {
                $cmd = '(IFS=:;set -f;find -L $PATH -maxdepth 1 -type f -perm -100 -print;)';
            } else {
                $cmd = 'find . -maxdepth 1';
            }
        }
        $res = $this->run($cmd, true);
        $res = $this->extract_workingdir($res);
        if ($res === null) {
            return [];
        }
        $res = $res[0];
        foreach (explode("\n", $res) as $line) {
            if ($line !== '') {
                $base = basename($line);
                if ($type == 'file' && $line == '.') {
                    continue;
                }
                if (substr($base, 0, strlen($value)) === $value) {
                    $result[] = $base;
                }
            }
        }
        return $result;
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
        $result = $this->run($cmd, true);
        $res = $this->extract_workingdir($result);

        if ($res != null) {
            [$result, $workingdir] = $res;
        } else {
            $workingdir = $this->get_working_dir();
        }
        $user = $this->combined_user_hostname();
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
    public function get_hostname(): string {
        if ($this->all_function_exist(['gethostname'])) {
            $hostname = gethostname();
            return $hostname;
        }
        if (self::is_windows()) {
            return $this->run('echo %USERDOMAIN%');
        } else {
            return $this->run('hostname');
        }
    }

    /**
     * Get username from system
     *
     * @return string
     * @throws \moodle_exception
     */
    public function get_user_name(): string {
        if (self::is_windows()) {
            if ($this->all_function_exist(['getenv'])) {
                $username = getenv('USERNAME');
                if ($username !== false) {
                    return $username;
                }
            }
        } else {
            if ($this->all_function_exist(['posix_getpwuid', 'posix_geteuid'])) {
                $pwuid = posix_getpwuid(posix_geteuid());
                if ($pwuid !== false) {
                    return $pwuid['name'];
                }
            }
        }
        $username = $this->run('whoami');
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
        if ($this->all_function_exist(['getcwd'])) {
            return getcwd();
        }
        if (self::is_windows()) {
            return $this->run('cd');
        } else {
            return $this->run('pwd');
        }
    }

    /**
     * Try to run the user code.
     *
     * This function should NEVER be called by other plugins since we do NOT check capabilities here again,
     * all checks are made in the WEBSERVICE's "execute" and "hinting"!
     *
     * @param string $cmd
     * @param bool   $pathcheck
     * @return string
     * @throws \moodle_exception
     */
    public function run(string $cmd, bool $pathcheck = false): string {
        if (self::is_windows()) {
            // Windows!
            $pathcheckstr = "&& (FOR /F \"tokens=*\" %g IN ('CD') do (SET VAR=%g)) &&" .
                ' echo ^<-moodle-local_webshell-^>%VAR%^<-moodle-local_webshell-^>)';
        } else {
            // Linux!
            $pathcheckstr = ';echo "<-moodle-local_webshell->${PWD}<-moodle-local_webshell->")';
        }
        if (!$pathcheck) {
            $pathcheckstr = '';
        }
        $cmd = "($cmd $pathcheckstr 2>&1";

        if (function_exists('raise_memory_limit')) {
            raise_memory_limit(MEMORY_HUGE);
        }
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        if (function_exists('shell_exec')) {
            return shell_exec($cmd) ?? '';
        } else if (function_exists('exec')) {
            $output = [];
            exec($cmd, $output);
            return implode("\n", $output);
        } else if ($this->all_function_exist(['system', 'ob_start', 'ob_get_contents', 'ob_end_clean'])) {
            ob_start();
            system($cmd);
            $output = ob_get_contents();
            ob_end_clean();
            return !$output ? '' : $output;
        } else if ($this->all_function_exist(['passthru', 'ob_start', 'ob_get_contents', 'ob_end_clean'])) {
            ob_start();
            passthru($cmd);
            $output = ob_get_contents();
            ob_end_clean();
            return !$output ? '' : $output;
        } else if ($this->all_function_exist(['popen', 'feof', 'fread', 'pclose'])) {
            $output = '';
            $handle = popen($cmd, 'r');
            while (!feof($handle)) {
                $output .= fread($handle, 4096);
            }
            pclose($handle);
            return $output;
        } else if ($this->all_function_exist(['proc_open', 'stream_get_contents', 'proc_close'])) {
            $handle = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            proc_close($handle);
            return !$output ? '' : $output;
        }
        throw new \moodle_exception('noexecturofunctionfound', 'local_webshell');
    }


    /**
     * Determine the functions available to select the best approach for the executor.
     *
     * @param array $list
     * @return bool
     */
    private function all_function_exist(array $list = []): bool {
        foreach ($list as $entry) {
            if (!function_exists($entry)) {
                return false;
            }
        }
        return true;
    }
}
