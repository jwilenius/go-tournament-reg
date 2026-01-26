(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initEgdLookup();
    });

    function initEgdLookup() {
        var lookupBtn = document.getElementById('gtr-egd-lookup-btn');
        if (!lookupBtn) return;

        var dropdown = null;
        var isLoading = false;

        lookupBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!isLoading) performLookup();
        });

        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target) && e.target !== lookupBtn) {
                closeDropdown();
            }
        });

        function performLookup() {
            var firstName = document.getElementById('first_name');
            var lastName = document.getElementById('last_name');
            var country = document.getElementById('country');

            var firstNameVal = firstName ? firstName.value.trim() : '';
            var lastNameVal = lastName ? lastName.value.trim() : '';
            var countryVal = country ? country.value : '';

            if (!firstNameVal && !lastNameVal && !countryVal) {
                showError('Please enter a name or select a country before searching.');
                return;
            }

            showLoading();

            var formData = new FormData();
            formData.append('action', 'gtr_egd_lookup');
            formData.append('nonce', gtrEgdLookup.nonce);
            formData.append('first_name', firstNameVal);
            formData.append('last_name', lastNameVal);
            formData.append('country', countryVal);

            fetch(gtrEgdLookup.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                hideLoading();
                if (data.success) {
                    showResults(data.data);
                } else {
                    showError(data.data && data.data.message ? data.data.message : 'An error occurred.');
                }
            })
            .catch(function() {
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
                data.players.forEach(function(player) {
                    var item = document.createElement('div');
                    item.className = 'gtr-egd-player';
                    item.innerHTML = createPlayerHtml(player);
                    item.addEventListener('click', function() { selectPlayer(player); });
                    dropdown.appendChild(item);
                });

                if (data.has_more && data.search_url) {
                    var overflow = document.createElement('div');
                    overflow.className = 'gtr-egd-overflow';
                    overflow.innerHTML = '<span>More than 10 results found.</span> ' +
                        '<a href="' + escapeAttr(data.search_url) + '" target="_blank" rel="noopener">Search on EGD website</a>';
                    dropdown.appendChild(overflow);
                }
            }

            var notRegistered = document.createElement('div');
            notRegistered.className = 'gtr-egd-not-registered';
            notRegistered.textContent = 'Not registered in EGD';
            notRegistered.addEventListener('click', function() { closeDropdown(); });
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

            setTimeout(function() { closeDropdown(); }, 3000);
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
            var fields = {
                'first_name': player.first_name,
                'last_name': player.last_name,
                'egd_number': player.pin,
                'player_strength': player.strength,
                'country': player.country
            };

            for (var fieldId in fields) {
                var field = document.getElementById(fieldId);
                if (field && fields[fieldId]) {
                    field.value = fields[fieldId];
                    field.classList.add('gtr-egd-filled');
                    (function(f) {
                        setTimeout(function() { f.classList.remove('gtr-egd-filled'); }, 1500);
                    })(field);
                }
            }
            closeDropdown();
        }

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function escapeAttr(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                      .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    }
})();
