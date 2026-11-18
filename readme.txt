=== YL Captcha ===
Contributors: ylwebdev
Tags: captcha, spam, comments, gdpr, privacy, contact form 7
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted proof-of-work captcha. No puzzles for humans, no external service, no cookies, no personal data leaves your server.

== Description ==

YL Captcha stops form spam without asking your visitors to click traffic lights. The browser solves a small cryptographic puzzle in the background (proof of work, the same idea as ALTCHA or Friendly Captcha), which takes a human visitor a fraction of a second and makes mass submissions expensive for bots.

* **No external requests.** Everything runs on your own server. Nothing is loaded from Google, Cloudflare or anyone else, so there is nothing to add to your privacy policy and no consent banner needed.
* **No cookies, no fingerprinting, no IP storage.**
* **Protects** the comment form, registration, lost-password and (optionally) login form, plus Contact Form 7. Any other form via the `[yl_captcha]` shortcode and one PHP call.
* **Replay-safe.** Every solved challenge can be used exactly once and expires after 15 minutes.
* **Accessible.** No visual or audio task. Works with screen readers; status is announced via `aria-live`.
* **Tiny.** One PHP file, 2 KB of JavaScript, no dependencies.

== Installation ==

1. Upload the `yl-captcha` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New.
2. Activate the plugin. A secret key is generated automatically.
3. Settings → YL Captcha: choose which forms to protect. Comments, registration, lost password and Contact Form 7 are on by default.

== Frequently Asked Questions ==

= Does the visitor have to do anything? =
No. A short status line shows "Checking …" and then "Verified". Submitting before the check finishes simply waits for it.

= How do I protect my own form? =
Put `[yl_captcha]` (or `echo YL_Captcha::instance()->widget();`) inside the form and check `YL_Captcha::instance()->verify($_POST)` on the server before you process the submission.

= Is it GDPR compliant? =
The plugin makes no external requests and stores no personal data. The only thing kept is a short-lived transient of the solved challenge hash, which contains no visitor information.

= What about browsers without WebCrypto? =
Browsers released before 2017 cannot solve the challenge and their submissions are rejected. That is well under 0.1 % of traffic today.

== Changelog ==

= 1.0.0 =
* Initial release.
