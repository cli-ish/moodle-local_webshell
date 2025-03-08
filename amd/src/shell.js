/**
 * Shell Component.
 *
 * @module     local_webshell/shell
 * @class      local_webshell/shell
 * @copyright  2024 Vincent Schneider (cli-ish)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';

export default class Component {
    /**
     * Init the component.
     */
    constructor() {
        this.history = this._fetchHistory();
        this.selectionArrow = this.history.length;
        this.resultbox = document.querySelector('body .local_webshell .shell-result');
        this.inputbox = document.querySelector('body .local_webshell .shell-input');
        this.stateReady();
    }

    /**
     * Static method to create a component instance from the mustache template.
     *
     * @return {Component}
     */
    static init() {
        return new Component();
    }

    /**
     * Update workingdir/username if changed.
     * @param {object} result
     * @private
     */
    _updateUi(result) {
        this.inputbox.querySelector('.username').innerText = result.user;
        this.inputbox.querySelector('.workingdir').innerText = result.workingdir;
    }

    /**
     * Print the result of the command execution.
     * @param {string} command
     * @param {object} result
     * @private
     */
    _printResult(command, result) {
        const cmdNode = document.createElement('div');
        cmdNode.innerHTML = '<div class="cmdline"><span class="username">' + result.user + ':</span><span class="workingdir">' +
            result.workingdir + '</span><span class="arg">#</span> ' + command + '</div>';
        this.resultbox.appendChild(cmdNode);
        const resultNode = document.createElement('div');
        resultNode.textContent += atob(result.result); // This is not deprecated in this context.
        this.resultbox.appendChild(resultNode);
        this.resultbox.scrollTop = this.resultbox.scrollHeight;
    }

    /**
     * Execute command and fetch result.
     * @param {string} command
     * @param {function} callback
     * @private
     */
    _execCommand(command, callback) {
        let promises = ajax.call([
            {
                methodname: 'local_webshell_run',
                args: {
                    command: command,
                }
            }
        ]);
        promises[0].done(function(response) {
            // Todo: handle errors?
            callback(response);
        });
    }

    /**
     * Query hinting webservice.
     * @param {string} value
     * @param {string} type
     * @param {function} callback
     * @private
     */
    _hinting(value, type, callback) {
        let promises = ajax.call([
            {
                methodname: 'local_webshell_hinting',
                args: {
                    value: value,
                    type: type
                }
            }
        ]);
        promises[0].done(function(response) {
            // Todo: handle errors?
            callback(response);
        });
    }

    /**
     * Initial state ready method.
     */
    stateReady() {
        let that = this;
        this.inputbox.querySelector('#shell-cmd').addEventListener('keydown', e => {
            let command = '';
            switch (event.key) {
                case 'Enter':
                    command = e.target.value;
                    e.target.value = '';
                    if (command === 'clear') {
                        that.resultbox.innerHTML = '';
                        return;
                    }
                    this._pushToHistory(command);
                    this.selectionArrow = this.history.length;
                    that._execCommand(command, data => {
                        that._printResult(command, data);
                        that._updateUi(data);
                    });
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (that.selectionArrow - 1 < 0) {
                        return;
                    }
                    that.selectionArrow -= 1;
                    that.inputbox.querySelector('#shell-cmd').value = that.history[that.selectionArrow];
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (that.selectionArrow + 1 >= that.history.length) {
                        that.selectionArrow = that.history.length;
                        that.inputbox.querySelector('#shell-cmd').value = "";
                        return;
                    }
                    that.selectionArrow += 1;
                    that.inputbox.querySelector('#shell-cmd').value = that.history[that.selectionArrow];
                    break;
                case 'Tab':
                    e.preventDefault();
                    that._autocomplete(e.target);
                    break;
            }
        });
    }

    /**
     * Return a list of history commands.
     * @return {string[]}
     * @private
     */
    _fetchHistory() {
        let result = window.localStorage.getItem('moodle-local_webshell/history');
        if (result === null) {
            return [];
        }
        let data = JSON.parse(result);
        if (!Array.isArray(data)) {
            return [];
        }
        return data;
    }

    /**
     * Add command to history.
     * @param {string} command
     * @private
     */
    _pushToHistory(command) {
        this.history.push(command);
        window.localStorage.setItem('moodle-local_webshell/history', JSON.stringify(this.history));
    }

    /**
     * Autocomplete file/directories.
     * @param {HTMLElement} target
     * @private
     */
    _autocomplete(target) {
        let command = target.value;
        if (command.trim().length === 0) {
            return;
        }
        let parts = command.split(' ');
        let type = (parts.length === 1) ? 'binary' : 'file';
        let value = (type === 'binary') ? parts[0] : parts[parts.length - 1];
        let resultStr = '';
        let that = this;
        this._hinting(value, type, result => {
            if (result.matches.length <= 0) {
                return;
            }
            if (result.matches.length === 1) {
                if (type === 'binary') {
                    // We can replace the whole string since we know that we don't have more yet!
                    target.value = result.matches[0];
                } else {
                    target.value = command.replace(/(\S*)$/, result.matches[0]);
                }
            } else {
                resultStr = '';
                let count = 0;
                result.matches.forEach(entry => {
                    resultStr += entry + '\t';
                    if (count > 5) {
                        count = 0;
                        resultStr += '\n';
                    }
                    count++;
                });
                result.result = btoa(resultStr);
                that._printResult(command, result);
            }
        });
    }
}