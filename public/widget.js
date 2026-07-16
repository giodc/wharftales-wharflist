(function () {
    'use strict';

    var LOG = '[WharfList]';

    function log() {
        var args = Array.prototype.slice.call(arguments);
        args.unshift(LOG);
        console.log.apply(console, args);
    }

    function warn() {
        var args = Array.prototype.slice.call(arguments);
        args.unshift(LOG);
        console.warn.apply(console, args);
    }

    function error() {
        var args = Array.prototype.slice.call(arguments);
        args.unshift(LOG);
        console.error.apply(console, args);
    }

    try {
        var currentScript = document.currentScript;

        if (!currentScript) {
            var keyed = document.querySelectorAll('script[data-api-key]');
            currentScript = keyed[keyed.length - 1];
            log('currentScript fallback via data-api-key', currentScript);
        }

        if (!currentScript) {
            var allScripts = document.getElementsByTagName('script');
            currentScript = allScripts[allScripts.length - 1];
            warn('currentScript fallback via last script tag');
        }

        var apiKey = currentScript ? currentScript.getAttribute('data-api-key') : '';
        var siteId = currentScript ? currentScript.getAttribute('data-site-id') : '';
        var scriptSrc = currentScript && currentScript.src ? currentScript.src : '';

        log('boot', {
            apiKeyPrefix: apiKey ? apiKey.slice(0, 8) + '…' : '(missing)',
            siteId: siteId || '(missing)',
            scriptSrc: scriptSrc || '(missing)',
            pageUrl: window.location.href
        });

        var baseUrl = scriptSrc.replace(/\/widget\.js(?:\?.*)?$/i, '');
        baseUrl = baseUrl.replace(/([^:]\/)\/+/g, '$1');
        baseUrl = baseUrl.replace(/\/$/, '');

        if (!baseUrl || !/^https?:\/\//i.test(baseUrl)) {
            if (scriptSrc && scriptSrc.indexOf('widget.js') > -1) {
                var parts = scriptSrc.split('/');
                parts.pop();
                baseUrl = parts.join('/');
            } else {
                baseUrl = window.location.protocol + '//' + window.location.host;
                warn('baseUrl reconstructed from page host', baseUrl);
            }
        }

        log('baseUrl', baseUrl);

        var arrowIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>';
        var spinnerIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2 A10 10 0 0 1 22 12" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>';

        var formHTML = ''
            + '<style>'
            + '#wharflist-widget{max-width:400px;font-family:inherit;transition:opacity .3s ease}'
            + '#wharflist-widget.fade-out{opacity:0}'
            + '#wharflist-email-wrapper{position:relative;display:flex;align-items:center}'
            + '#wharflist-email{width:100%;padding:14px 50px 14px 14px;border:2px solid #000;border-radius:5px;font-size:14px;outline:none;background:#fff;color:#000}'
            + '#wharflist-email:focus{border-color:#000;box-shadow:0 0 0 2px rgba(0,0,0,.1)}'
            + '.wharflist-hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none}'
            + '#wharflist-submit-btn{position:absolute;right:4px;background:#000;color:#fff;border:none;border-radius:5px;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .3s ease,transform .2s ease}'
            + '#wharflist-submit-btn:hover{background:#333;transform:scale(1.05)}'
            + '#wharflist-submit-btn:active{transform:scale(.95)}'
            + '#wharflist-submit-btn:disabled{background:#666;cursor:not-allowed;transform:scale(1)}'
            + '#wharflist-message{margin:0;padding:14px;border-radius:5px;text-align:center;font-size:14px;line-height:1.375;box-sizing:border-box;opacity:0;transform:translateY(-10px);transition:opacity .3s ease,transform .3s ease}'
            + '#wharflist-message.show{opacity:1;margin-bottom:24px;transform:translateY(0)}'
            + '#wharflist-message.success{background:#fff;color:#000;border:2px solid #000}'
            + '#wharflist-message.error{background:#000;color:#fff;border:2px solid #000}'
            + '</style>'
            + '<div id="wharflist-widget">'
            + '  <div id="wharflist-subscribe-form" role="form">'
            + '    <input type="text" name="website" class="wharflist-hp" tabindex="-1" autocomplete="off">'
            + '    <input type="hidden" id="wharflist-timestamp" value="">'
            + '    <div id="wharflist-email-wrapper">'
            + '      <input type="email" id="wharflist-email" placeholder="Enter your email" autocomplete="email">'
            + '      <button type="button" id="wharflist-submit-btn" aria-label="Subscribe">' + arrowIcon + '</button>'
            + '    </div>'
            + '    <div id="wharflist-message"></div>'
            + '  </div>'
            + '</div>';

        var container = document.getElementById('wharflist-form');
        if (!container) {
            error('Mount target #wharflist-form not found — widget cannot render');
            return;
        }

        container.innerHTML = formHTML;
        log('mounted into #wharflist-form');

        var form = document.getElementById('wharflist-subscribe-form');
        var emailInput = document.getElementById('wharflist-email');
        var submitButton = document.getElementById('wharflist-submit-btn');
        var widget = document.getElementById('wharflist-widget');
        var emailWrapper = document.getElementById('wharflist-email-wrapper');
        var messageDiv = document.getElementById('wharflist-message');
        var timestampField = document.getElementById('wharflist-timestamp');
        var honeypot = form ? form.querySelector('.wharflist-hp') : null;
        var busy = false;

        function refreshTimestamp() {
            if (timestampField) {
                timestampField.value = String(Date.now());
                log('timestamp set', timestampField.value);
            }
        }

        function setButtonIdle() {
            if (!submitButton) return;
            submitButton.disabled = false;
            submitButton.innerHTML = arrowIcon;
        }

        function restoreForm() {
            emailWrapper.style.display = 'flex';
            emailWrapper.style.opacity = '1';
            messageDiv.className = '';
            messageDiv.textContent = '';
            setButtonIdle();
            refreshTimestamp();
            busy = false;
            log('form restored');
        }

        function showMessage(success, message, restoreAfterMs) {
            log('showMessage', { success: success, message: message, restoreAfterMs: restoreAfterMs });
            widget.classList.add('fade-out');

            setTimeout(function () {
                emailWrapper.style.display = 'none';
                messageDiv.className = success ? 'show success' : 'show error';
                messageDiv.textContent = message;
                widget.classList.remove('fade-out');

                setTimeout(function () {
                    widget.classList.add('fade-out');
                    setTimeout(function () {
                        restoreForm();
                        widget.classList.remove('fade-out');
                    }, 300);
                }, restoreAfterMs);
            }, 300);
        }

        function subscribe() {
            log('subscribe() called', { busy: busy });

            if (busy) {
                warn('ignored — already submitting');
                return;
            }

            var email = (emailInput.value || '').trim();
            if (!email) {
                warn('empty email');
                emailInput.focus();
                return;
            }

            if (!apiKey) {
                error('missing data-api-key on script tag');
                showMessage(false, 'Widget misconfigured (missing API key).', 3000);
                return;
            }

            var timestamp = parseInt(timestampField.value, 10);
            if (!timestamp || isNaN(timestamp)) {
                warn('timestamp missing/invalid — refreshing');
                refreshTimestamp();
                timestamp = parseInt(timestampField.value, 10);
            }

            var timeDiff = Date.now() - timestamp;
            log('anti-spam', { timestamp: timestamp, timeDiff: timeDiff, honeypot: honeypot ? honeypot.value : null });

            if (honeypot && honeypot.value !== '') {
                warn('blocked by honeypot (silent)');
                return;
            }

            if (timeDiff < 1000) {
                warn('blocked by timing < 1000ms (silent) — wait a second and retry');
                return;
            }

            busy = true;
            submitButton.disabled = true;
            submitButton.innerHTML = spinnerIcon;
            emailWrapper.style.opacity = '0.5';

            var apiUrl = baseUrl + '/api.php';
            var payload = {
                api_key: apiKey,
                email: email,
                _t: timestamp,
                _hp: ''
            };

            log('POST', apiUrl, payload);

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    log('response status', response.status, response.statusText);
                    return response.text().then(function (text) {
                        log('response body', text);
                        var data = {};
                        try {
                            data = text ? JSON.parse(text) : {};
                        } catch (err) {
                            error('JSON parse failed', err);
                            throw new Error('Invalid JSON from server');
                        }
                        return { ok: response.ok, status: response.status, data: data };
                    });
                })
                .then(function (result) {
                    var data = result.data || {};
                    if (typeof data.success === 'undefined') {
                        error('missing success flag', result);
                        showMessage(false, 'An error occurred. Please try again.', 3000);
                        return;
                    }

                    if (data.success) {
                        emailInput.value = '';
                        if (honeypot) honeypot.value = '';
                        showMessage(true, data.message || 'Subscribed.', 5000);
                    } else {
                        showMessage(false, data.message || 'Unable to subscribe.', 3000);
                    }
                })
                .catch(function (err) {
                    error('fetch failed', err);
                    showMessage(false, 'An error occurred. Please try again.', 3000);
                });
        }

        refreshTimestamp();

        submitButton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            log('submit button click');
            subscribe();
        });

        emailInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                log('enter key on email');
                subscribe();
            }
        });

        log('ready — click arrow or press Enter to subscribe');
    } catch (bootError) {
        console.error('[WharfList] boot failed', bootError);
    }
})();
