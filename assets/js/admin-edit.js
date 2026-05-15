(function () {
    'use strict';

    var EDITABLE_FIELDS = [
        'first_name', 'last_name', 'player_strength', 'gor',
        'country', 'email', 'egd_number', 'phone_number', 'rounds'
    ];

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.querySelector('.gtr-registrations-table');
        if (!table) return;
        if (!window.gtrAdminEdit) return;

        var activeEdit = null;

        table.addEventListener('click', function (e) {
            var target = e.target;
            if (!(target instanceof Element)) return;

            var row = target.closest('.gtr-registration-row');
            if (!row) return;

            if (target.classList.contains('gtr-edit-row')) {
                e.preventDefault();
                enterEditMode(row);
            } else if (target.classList.contains('gtr-cancel-row')) {
                e.preventDefault();
                cancelEditMode(row);
            } else if (target.classList.contains('gtr-save-row')) {
                e.preventDefault();
                saveRow(row);
            }
        });

        function enterEditMode(row) {
            if (activeEdit && activeEdit.row === row) return;
            if (activeEdit) {
                cancelEditMode(activeEdit.row);
            }

            var totalRounds = parseInt(table.getAttribute('data-tournament-rounds'), 10) || 0;
            var fieldEls = {};
            var inputs = {};

            EDITABLE_FIELDS.forEach(function (field) {
                var cell = row.querySelector('td[data-field="' + field + '"]');
                if (!cell) return;
                fieldEls[field] = cell;
                cell.innerHTML = '';
                cell.classList.add('gtr-edit-cell');
                var value = cell.getAttribute('data-value') || '';
                var input = buildInput(field, value, totalRounds);
                cell.appendChild(input);
                inputs[field] = input;

                var errorEl = document.createElement('div');
                errorEl.className = 'gtr-field-error';
                errorEl.style.display = 'none';
                cell.appendChild(errorEl);
            });

            toggleActionButtons(row, true);

            var egdController = null;
            if (window.GtrEgdLookup && window.gtrEgdLookup) {
                egdController = window.GtrEgdLookup.init(row, {
                    ajaxUrl: window.gtrEgdLookup.ajaxUrl,
                    nonce: window.gtrEgdLookup.nonce,
                    button: '.gtr-row-egd-lookup-btn',
                    fieldIds: {
                        first_name: '[data-field="first_name"] input',
                        last_name: '[data-field="last_name"] input',
                        country: '[data-field="country"] select',
                        egd_number: '[data-field="egd_number"] input',
                        player_strength: '[data-field="player_strength"] input',
                        gor: '[data-field="gor"] input'
                    }
                });
            }

            activeEdit = {
                row: row,
                fieldEls: fieldEls,
                inputs: inputs,
                egd: egdController
            };
        }

        function buildInput(field, value, totalRounds) {
            if (field === 'country') {
                var select = document.createElement('select');
                select.name = field;
                select.className = 'gtr-edit-input gtr-edit-country';
                var blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '-- Select --';
                select.appendChild(blank);
                var template = document.getElementById('gtr-country-options');
                if (template && template.content) {
                    select.appendChild(template.content.cloneNode(true));
                }
                select.value = value;
                return select;
            }
            var input = document.createElement('input');
            input.type = (field === 'gor') ? 'number' : 'text';
            input.name = field;
            input.value = value;
            input.className = 'gtr-edit-input gtr-edit-' + field;
            if (field === 'rounds' && totalRounds > 0) {
                input.placeholder = '1-' + totalRounds + ' e.g. 1,2,4';
            }
            return input;
        }

        function cancelEditMode(row) {
            if (activeEdit && activeEdit.egd) {
                activeEdit.egd.destroy();
            }
            EDITABLE_FIELDS.forEach(function (field) {
                var cell = row.querySelector('td[data-field="' + field + '"]');
                if (!cell) return;
                cell.classList.remove('gtr-edit-cell');
                var value = cell.getAttribute('data-value') || '';
                cell.innerHTML = displayValue(field, value, cell);
            });
            toggleActionButtons(row, false);
            if (activeEdit && activeEdit.row === row) {
                activeEdit = null;
            }
        }

        function saveRow(row) {
            if (!activeEdit || activeEdit.row !== row) return;

            clearFieldErrors(row);

            var formData = new FormData();
            formData.append('action', 'gtr_update_registration');
            formData.append('nonce', window.gtrAdminEdit.updateNonce);
            formData.append('id', row.getAttribute('data-id'));

            EDITABLE_FIELDS.forEach(function (field) {
                var input = activeEdit.inputs[field];
                if (!input) return;
                formData.append(field, input.value);
            });

            var saveBtn = row.querySelector('.gtr-save-row');
            var cancelBtn = row.querySelector('.gtr-cancel-row');
            if (saveBtn) saveBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;

            fetch(window.gtrAdminEdit.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { status: response.status, body: data };
                    });
                })
                .then(function (result) {
                    if (saveBtn) saveBtn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;

                    if (result.body.success && result.body.data && result.body.data.registration) {
                        applyServerRow(row, result.body.data.registration);
                        toggleActionButtons(row, false);
                        if (activeEdit && activeEdit.egd) activeEdit.egd.destroy();
                        activeEdit = null;
                    } else if (result.body.data && result.body.data.errors) {
                        showFieldErrors(row, result.body.data.errors);
                    } else {
                        showRowError(row, (result.body.data && result.body.data.message) || 'Update failed.');
                    }
                })
                .catch(function () {
                    if (saveBtn) saveBtn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    showRowError(row, 'Failed to connect to the server.');
                });
        }

        function applyServerRow(row, registration) {
            EDITABLE_FIELDS.forEach(function (field) {
                var cell = row.querySelector('td[data-field="' + field + '"]');
                if (!cell) return;
                var rawValue = registration[field];
                if (rawValue === null || rawValue === undefined) rawValue = '';
                cell.setAttribute('data-value', String(rawValue));
                cell.classList.remove('gtr-edit-cell');
                cell.innerHTML = displayValue(field, String(rawValue), cell);
            });
        }

        function displayValue(field, value, cell) {
            if (field === 'country') {
                // Look up the display name from the options template; fall back to the code.
                var template = document.getElementById('gtr-country-options');
                if (template && template.content && value) {
                    var opt = template.content.querySelector('option[value="' + cssEscape(value) + '"]');
                    if (opt) {
                        cell.setAttribute('data-display', opt.textContent);
                        return escapeHtml(opt.textContent);
                    }
                }
                return escapeHtml(value);
            }
            if (value === '' && (field === 'gor' || field === 'egd_number' || field === 'rounds')) {
                return '-';
            }
            return escapeHtml(value);
        }

        function toggleActionButtons(row, editing) {
            row.classList.toggle('gtr-row-editing', !!editing);
        }

        function clearFieldErrors(row) {
            row.querySelectorAll('.gtr-field-error').forEach(function (el) {
                el.style.display = 'none';
                el.textContent = '';
            });
            var rowErr = row.querySelector('.gtr-row-error');
            if (rowErr) rowErr.remove();
        }

        function showFieldErrors(row, errors) {
            Object.keys(errors).forEach(function (field) {
                var cell = row.querySelector('td[data-field="' + field + '"]');
                if (!cell) return;
                var errEl = cell.querySelector('.gtr-field-error');
                if (!errEl) return;
                errEl.textContent = errors[field];
                errEl.style.display = '';
            });
        }

        function showRowError(row, message) {
            var actions = row.querySelector('.gtr-row-actions');
            if (!actions) return;
            var err = document.createElement('div');
            err.className = 'gtr-row-error';
            err.textContent = message;
            actions.appendChild(err);
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            var div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }

        function cssEscape(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }
            return String(value).replace(/([^\w-])/g, '\\$1');
        }
    });
})();
