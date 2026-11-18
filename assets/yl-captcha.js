/* YL Captcha – solves the proof-of-work challenge in the browser. No cookies, no third parties. */
(function () {
  'use strict';
  var I18N = window.ylCaptchaI18n || {};
  var enc = new TextEncoder();

  async function sha256hex(s) {
    var buf = await crypto.subtle.digest('SHA-256', enc.encode(s));
    return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }

  async function solve(c) {
    for (var n = 0; n <= c.maxnumber; n++) {
      if ((await sha256hex(c.salt + n)) === c.challenge) return n;
      if (n % 500 === 0) await new Promise(function (r) { setTimeout(r, 0); }); // keep the page responsive
    }
    return null;
  }

  async function arm(box) {
    if (box.dataset.state === 'ok' || box.dataset.state === 'busy') return box.promise;
    box.dataset.state = 'busy';
    var status = box.querySelector('.yl-captcha__status');
    status.textContent = I18N.solving || 'Checking …';
    box.promise = (async function () {
      try {
        var res = await fetch(box.dataset.endpoint, { cache: 'no-store', credentials: 'same-origin' });
        var c = await res.json();
        var n = await solve(c);
        if (n === null) throw new Error('unsolved');
        box.querySelector('[name=ylc_salt]').value = c.salt;
        box.querySelector('[name=ylc_number]').value = n;
        box.querySelector('[name=ylc_challenge]').value = c.challenge;
        box.querySelector('[name=ylc_signature]').value = c.signature;
        box.dataset.state = 'ok';
        box.expires = parseInt(c.salt.split('.')[1], 10) * 1000;
        status.textContent = I18N.ok || 'Verified.';
        return true;
      } catch (e) {
        box.dataset.state = 'error';
        status.textContent = I18N.error || 'Verification failed.';
        return false;
      }
    })();
    return box.promise;
  }

  function init() {
    if (!window.crypto || !crypto.subtle) return; // very old browsers: server rejects, nothing we can do
    document.querySelectorAll('.yl-captcha').forEach(function (box) {
      var form = box.closest('form');
      arm(box);
      if (!form) return;
      form.addEventListener('submit', function (ev) {
        // solved and still valid? let it through. Otherwise solve first, then submit.
        if (box.dataset.state === 'ok' && Date.now() < box.expires - 5000) return;
        ev.preventDefault();
        box.dataset.state = '';
        arm(box).then(function (ok) { if (ok) form.requestSubmit ? form.requestSubmit() : form.submit(); });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
