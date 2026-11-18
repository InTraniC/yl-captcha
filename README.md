# YL Captcha – self-hosted proof-of-work captcha for WordPress

Stops form spam without puzzles, without Google, without Cloudflare and without cookies. The visitor's browser solves a small SHA-256 challenge in the background (proof of work, like [ALTCHA](https://altcha.org) or Friendly Captcha); humans never notice, bots have to burn CPU time for every submission.

Built by [YL Web Dev](https://yl-web.dev) because every client asked the same question: *"Can we have a captcha that doesn't need a line in the privacy policy?"*

## Why

| | reCAPTCHA / hCaptcha / Turnstile | YL Captcha |
|---|---|---|
| External requests | yes (Google / Intuition Machines / Cloudflare) | **none** |
| Cookies, fingerprinting | yes | **none** |
| Consent banner needed (EU) | usually | **no** |
| Visitor has to solve something | sometimes | **never** |
| Accessible without vision/hearing | partly | **yes** |
| Dependencies | remote script | **0** |

## What it protects

- Comment form
- Registration form
- Lost-password form
- Login form (optional, off by default)
- Contact Form 7 (automatic when CF7 is active)
- Any other form: `[yl_captcha]` shortcode + `YL_Captcha::instance()->verify($_POST)`

## How it works

1. The widget fetches a challenge from `/wp-json/yl-captcha/v1/challenge`: a random salt, a target hash and an HMAC signature (server secret, generated on activation).
2. The browser searches for the number `n` with `sha256(salt + n) == target`, 0 ≤ n ≤ difficulty. At the default difficulty of 50 000 that takes 0.2–1 s on a phone, in the background, in 500-hash slices so the page stays responsive.
3. The form submits salt, number, target and signature as hidden fields.
4. The server checks the signature (so the challenge really came from this server), the hash (so the work was done), the expiry (15 min) and a one-time transient (so a solution can't be replayed).

No state before the submission, no IP logging, no session.

## Install

Download the ZIP from [Releases](../../releases), upload it under *Plugins → Add New → Upload*, activate. Settings under *Settings → YL Captcha*.

Or clone into `wp-content/plugins/yl-captcha`.

## Custom forms

```php
<form method="post">
  …
  <?php echo YL_Captcha::instance()->widget(); ?>
  <button>Send</button>
</form>
```

```php
if (!YL_Captcha::instance()->verify($_POST)) {
    wp_die('Captcha failed');
}
```

## Requirements

WordPress 6.0+, PHP 8.0+, a browser with WebCrypto (everything since 2017).

## License

GPL-2.0-or-later. © Yannic Lange, [YL Web Dev](https://yl-web.dev), Worms, Germany.
