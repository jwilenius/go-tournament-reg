(function () {
    'use strict';

    var DEFAULT_FIELD_IDS = {
        first_name: 'first_name',
        last_name: 'last_name',
        country: 'country',
        egd_number: 'egd_number',
        player_strength: 'player_strength',
        gor: 'gor'
    };

    /**
     * Initialise EGD lookup against a root element.
     *
     * @param {Element|Document} root The container holding both the lookup button and the target fields.
     *                                 For per-row admin edit pass the row element; for the public form pass `document`.
     * @param {Object} options { ajaxUrl, nonce, fieldIds?, button? }
     *   fieldIds defaults to public-form ids. Each value may be a CSS selector (relative to root) or an id.
     *   button may be a CSS selector relative to root; defaults to '#gtr-egd-lookup-btn'.
     * @returns {{destroy: function}} controller; call destroy() to detach handlers.
     */
    function initEgdLookup(root, options) {
        if (!root || !options || !options.ajaxUrl || !options.nonce) {
            return null;
        }

        var lookupBtn = options.button
            ? (typeof options.button === 'string' ? root.querySelector(options.button) : options.button)
            : root.querySelector('#gtr-egd-lookup-btn');
        if (!lookupBtn) return null;

        var fieldIds = mergeFieldIds(options.fieldIds);
        var dropdown = null;
        var isLoading = false;

        function onButtonClick(e) {
            e.preventDefault();
            if (!isLoading) performLookup();
        }

        function onDocumentClick(e) {
            if (dropdown && !dropdown.contains(e.target) && e.target !== lookupBtn) {
                closeDropdown();
            }
        }

        lookupBtn.addEventListener('click', onButtonClick);
        document.addEventListener('click', onDocumentClick);

        function performLookup() {
            var firstNameVal = readField('first_name');
            var lastNameVal = readField('last_name');
            var countryVal = readField('country');

            if (!firstNameVal && !lastNameVal && !countryVal) {
                showError('Please enter a name or select a country before searching.');
                return;
            }

            showLoading();

            var formData = new FormData();
            formData.append('action', 'gtr_egd_lookup');
            formData.append('nonce', options.nonce);
            formData.append('first_name', firstNameVal);
            formData.append('last_name', lastNameVal);
            formData.append('country', countryVal);

            fetch(options.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                hideLoading();
                if (data.success) {
                    showResults(data.data);
                } else {
                    showError(data.data && data.data.message ? data.data.message : 'An error occurred.');
                }
            })
            .catch(function () {
                hideLoading();
                showError('Failed to connect to the server.');
            });
        }

        function showLoading() {
            isLoading = true;
            lookupBtn.disabled = true;
            lookupBtn.classList.add('gtr-loading');
            closeDropdown();
        }

        function hideLoading() {
            isLoading = false;
            lookupBtn.disabled = false;
            lookupBtn.classList.remove('gtr-loading');
        }

        function showResults(data) {
            closeDropdown();
            dropdown = document.createElement('div');
            dropdown.className = 'gtr-egd-dropdown';

            if (!data.players || data.players.length === 0) {
                var noResults = document.createElement('div');
                noResults.className = 'gtr-egd-no-results';
                noResults.textContent = 'No players found in EGD.';
                dropdown.appendChild(noResults);
            } else {
                data.players.forEach(function (player) {
                    var item = document.createElement('div');
                    item.className = 'gtr-egd-player';
                    item.innerHTML = createPlayerHtml(player);
                    item.addEventListener('click', function () { selectPlayer(player); });
                    dropdown.appendChild(item);
                });

                if (data.has_more && data.search_url) {
                    var overflow = document.createElement('div');
                    overflow.className = 'gtr-egd-overflow';

                    var label = document.createElement('span');
                    label.textContent = 'More than 10 results found.';
                    overflow.appendChild(label);
                    overflow.appendChild(document.createTextNode(' '));

                    var link = document.createElement('a');
                    link.href = data.search_url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = 'Search on EGD website';
                    overflow.appendChild(link);

                    dropdown.appendChild(overflow);
                }
            }

            var notRegistered = document.createElement('div');
            notRegistered.className = 'gtr-egd-not-registered';
            notRegistered.textContent = 'Not registered in EGD';
            notRegistered.addEventListener('click', function () { closeDropdown(); });
            dropdown.appendChild(notRegistered);

            lookupBtn.parentNode.style.position = 'relative';
            lookupBtn.parentNode.appendChild(dropdown);
        }

        function showError(message) {
            closeDropdown();
            dropdown = document.createElement('div');
            dropdown.className = 'gtr-egd-dropdown gtr-egd-dropdown-error';

            var errorDiv = document.createElement('div');
            errorDiv.className = 'gtr-egd-error';
            errorDiv.textContent = message;
            dropdown.appendChild(errorDiv);

            lookupBtn.parentNode.style.position = 'relative';
            lookupBtn.parentNode.appendChild(dropdown);

            setTimeout(function () { closeDropdown(); }, 3000);
        }

        function closeDropdown() {
            if (dropdown && dropdown.parentNode) {
                dropdown.parentNode.removeChild(dropdown);
            }
            dropdown = null;
        }

        function createPlayerHtml(player) {
            var html = '<div class="gtr-egd-player-name">' +
                escapeHtml(player.first_name) + ' ' + escapeHtml(player.last_name) + '</div>';
            html += '<div class="gtr-egd-player-details">';
            html += '<span class="gtr-egd-player-strength">' + escapeHtml(player.strength) + '</span>';
            html += '<span class="gtr-egd-player-country">' + escapeHtml(player.country) + '</span>';
            if (player.club) {
                html += '<span class="gtr-egd-player-club">' + escapeHtml(player.club) + '</span>';
            }
            html += '</div>';
            return html;
        }

        function selectPlayer(player) {
            var values = {
                first_name: player.first_name,
                last_name: player.last_name,
                egd_number: player.pin,
                player_strength: player.strength,
                country: player.country,
                gor: player.gor
            };

            // Preserve the user's diacritic spelling for first/last name when
            // it's the same person, just folded differently. "Ström" entered +
            // "Stroem" selected ⇒ keep "Ström". "Ström" + "Stroemberger" ⇒
            // overwrite, because there's no fold-equivalence match.
            for (var key in values) {
                if (!Object.prototype.hasOwnProperty.call(values, key)) continue;
                if (!values[key] && values[key] !== 0) continue;
                if ((key === 'first_name' || key === 'last_name')
                        && namesAreFoldEquivalent(readField(key), values[key])) {
                    continue;
                }
                writeField(key, values[key]);
            }
            closeDropdown();
        }

        function readField(key) {
            var el = resolveField(key);
            if (!el) return '';
            return (el.value || '').trim();
        }

        function writeField(key, value) {
            var el = resolveField(key);
            if (!el) return;
            el.value = value;
            el.classList.add('gtr-egd-filled');
            setTimeout(function () { el.classList.remove('gtr-egd-filled'); }, 1500);
        }

        function resolveField(key) {
            var ref = fieldIds[key];
            if (!ref) return null;
            // If it looks like a selector, query within root; otherwise treat as id.
            if (ref.charAt(0) === '#' || ref.charAt(0) === '.' || ref.indexOf('[') !== -1) {
                return root.querySelector ? root.querySelector(ref) : null;
            }
            // Plain id: prefer scoped lookup if root supports it.
            if (root.querySelector) {
                var scoped = root.querySelector('#' + cssEscape(ref));
                if (scoped) return scoped;
            }
            return document.getElementById(ref);
        }

        function destroy() {
            lookupBtn.removeEventListener('click', onButtonClick);
            document.removeEventListener('click', onDocumentClick);
            closeDropdown();
        }

        return { destroy: destroy };
    }

    function mergeFieldIds(overrides) {
        var merged = {};
        for (var key in DEFAULT_FIELD_IDS) {
            if (Object.prototype.hasOwnProperty.call(DEFAULT_FIELD_IDS, key)) {
                merged[key] = DEFAULT_FIELD_IDS[key];
            }
        }
        if (overrides) {
            for (var k in overrides) {
                if (Object.prototype.hasOwnProperty.call(overrides, k)) {
                    merged[k] = overrides[k];
                }
            }
        }
        return merged;
    }

    // Character fold tables, kept in sync with the PHP-side
    // transliterate_simple / transliterate_double helpers in
    // includes/class-form-handler.php. Update both sides together.
    var SIMPLE_FOLD = {
        'Å': 'A', 'å': 'a', 'Ä': 'A', 'ä': 'a',
        'Ö': 'O', 'ö': 'o', 'Ø': 'O', 'ø': 'o',
        'Ü': 'U', 'ü': 'u',
        'É': 'E', 'é': 'e', 'È': 'E', 'è': 'e', 'Ê': 'E', 'ê': 'e',
        'Á': 'A', 'á': 'a', 'À': 'A', 'à': 'a', 'Â': 'A', 'â': 'a',
        'Í': 'I', 'í': 'i', 'Î': 'I', 'î': 'i',
        'Ó': 'O', 'ó': 'o', 'Ô': 'O', 'ô': 'o',
        'Ú': 'U', 'ú': 'u', 'Û': 'U', 'û': 'u',
        'Ñ': 'N', 'ñ': 'n',
        'Č': 'C', 'č': 'c', 'Š': 'S', 'š': 's', 'Ž': 'Z', 'ž': 'z',
        'ß': 'ss'
    };
    var DOUBLE_FOLD = {
        'Å': 'Aa', 'å': 'aa', 'Ä': 'Ae', 'ä': 'ae',
        'Ö': 'Oe', 'ö': 'oe', 'Ø': 'Oe', 'ø': 'oe',
        'Ü': 'Ue', 'ü': 'ue', 'ß': 'ss'
    };

    function applyFold(str, table) {
        var out = '';
        for (var i = 0; i < str.length; i++) {
            var ch = str.charAt(i);
            out += Object.prototype.hasOwnProperty.call(table, ch) ? table[ch] : ch;
        }
        return out;
    }

    function namesAreFoldEquivalent(typed, candidate) {
        if (typed === null || typed === undefined || candidate === null || candidate === undefined) {
            return false;
        }
        var t = String(typed).trim();
        var c = String(candidate).trim();
        if (t === '' || c === '') return false;

        // Both names get reduced to their two folds; if any pair matches
        // case-insensitively, the names refer to the same string modulo
        // character translation. Acute accents fold the same way under both
        // tables, so two folds cover the full mapping space.
        var typedSimple = applyFold(t, SIMPLE_FOLD).toLowerCase();
        var typedDouble = applyFold(applyFold(t, DOUBLE_FOLD), SIMPLE_FOLD).toLowerCase();
        var candSimple = applyFold(c, SIMPLE_FOLD).toLowerCase();
        var candDouble = applyFold(applyFold(c, DOUBLE_FOLD), SIMPLE_FOLD).toLowerCase();

        return typedSimple === candSimple
            || typedSimple === candDouble
            || typedDouble === candSimple
            || typedDouble === candDouble;
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

    window.GtrEgdLookup = { init: initEgdLookup };

    // Back-compat auto-init for the public form.
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.gtrEgdLookup) return;
        if (!document.getElementById('gtr-egd-lookup-btn')) return;
        initEgdLookup(document, window.gtrEgdLookup);
    });
})();
