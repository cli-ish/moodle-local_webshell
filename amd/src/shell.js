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
    constructor() {
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

    _updateUi(result) {
        this.inputbox.querySelector('.username').innerText = result.user;
        this.inputbox.querySelector('.workingdir').innerText = result.workingdir;
    }

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
                    // Todo: insert into history!
                    // Todo: reset cursor of history.
                    e.target.value = '';
                    if (command === 'clear') {
                        that.resultbox.innerHTML = '';
                        return;
                    }
                    that._execCommand(command, data => {
                        that._printResult(command, data);
                        that._updateUi(data);
                    });
                    break;
                case 'ArrowUp':
                    // Todo: move cursor one up in history.
                    break;
                case 'ArrowDown':
                    // Todo: move cursor one down in history.
                    break;
                case 'Tab':
                    e.preventDefault();
                    that._autocomplete(e.target);

                    break;
            }
        });
    }

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