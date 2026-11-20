<?php
/**
 * Plugin Name:       YL Captcha
 * Plugin URI:        https://github.com/InTraniC/yl-captcha
 * Description:       Self-hosted proof-of-work captcha for comments, registration, login and Contact Form 7. No external service, no cookies, no tracking, GDPR-friendly.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Yannic Lange, YL Web Dev
 * Author URI:        https://yl-web.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yl-captcha
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

final class YL_Captcha {
    const VERSION  = '1.0.0';
    const OPTION   = 'yl_captcha';
    const TTL      = 900;   // challenge valid for 15 minutes
    const NS       = 'yl-captcha/v1';

    private static ?YL_Captcha $instance = null;
    private array $opt;

    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct() {
        $this->opt = wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
        add_action('rest_api_init', [$this, 'rest']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('login_enqueue_scripts', [$this, 'assets']);
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
        add_shortcode('yl_captcha', fn() => $this->widget());

        if ($this->opt['comments']) {
            add_action('comment_form_after_fields', [$this, 'print_widget']);
            add_action('comment_form_logged_in_after', [$this, 'print_widget']);
            add_filter('preprocess_comment', [$this, 'check_comment']);
        }
        if ($this->opt['register']) {
            add_action('register_form', [$this, 'print_widget']);
            add_filter('registration_errors', [$this, 'check_register']);
        }
        if ($this->opt['login']) {
            add_action('login_form', [$this, 'print_widget']);
            add_filter('authenticate', [$this, 'check_login'], 30, 3);
        }
        if ($this->opt['cf7'] && defined('WPCF7_VERSION')) {
            add_filter('wpcf7_form_elements', fn(string $html) => preg_replace('/(<input[^>]*type="submit")/i', $this->widget() . '$1', $html, 1));
            add_filter('wpcf7_spam', fn(bool $spam) => $spam || !$this->verify(), 5);
        }
        if ($this->opt['lostpassword']) {
            add_action('lostpassword_form', [$this, 'print_widget']);
            add_action('lostpassword_post', [$this, 'check_lostpassword']);
        }
    }

    public static function defaults(): array {
        return ['comments' => 1, 'register' => 1, 'login' => 0, 'lostpassword' => 1, 'cf7' => 1, 'difficulty' => 50000, 'secret' => ''];
    }

    public static function activate(): void {
        $o = wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
        if ($o['secret'] === '') { $o['secret'] = bin2hex(random_bytes(32)); update_option(self::OPTION, $o); }
    }

    /* ---------- Challenge ---------- */

    public function rest(): void {
        register_rest_route(self::NS, '/challenge', ['methods' => 'GET', 'callback' => [$this, 'challenge'], 'permission_callback' => '__return_true']);
    }

    public function challenge(): WP_REST_Response {
        $expires   = time() + self::TTL;
        $salt      = bin2hex(random_bytes(12)) . '.' . $expires;
        $max       = (int) $this->opt['difficulty'];
        $number    = random_int(0, $max);
        $challenge = hash('sha256', $salt . $number);
        $signature = hash_hmac('sha256', $challenge, $this->opt['secret']);
        $r = new WP_REST_Response(['algorithm' => 'SHA-256', 'salt' => $salt, 'challenge' => $challenge, 'signature' => $signature, 'maxnumber' => $max]);
        $r->header('Cache-Control', 'no-store');
        return $r;
    }

    /** Verifies the solved challenge from a submitted form. Replay-safe. */
    public function verify(?array $post = null): bool {
        $post ??= $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signed single-use token, see below
        // No nonce here on purpose: the HMAC signature issued by this server is the integrity check
        // (a nonce would break caching of the form page and is not needed for a single-use token).
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $f = static fn(string $k): string => isset($post[$k]) ? sanitize_text_field(wp_unslash((string) $post[$k])) : '';
        $salt = $f('ylc_salt'); $num = $f('ylc_number'); $chal = $f('ylc_challenge'); $sig = $f('ylc_signature');
        // phpcs:enable
        $exp  = (int) (explode('.', $salt)[1] ?? 0);
        if ($salt === '' || !ctype_digit($num) || $exp < time()) return false;
        if (!hash_equals(hash_hmac('sha256', $chal, $this->opt['secret']), $sig)) return false;
        if (!hash_equals($chal, hash('sha256', $salt . $num))) return false;
        $key = 'ylc_' . substr($chal, 0, 40);
        if (get_transient($key)) return false;                 // already used
        set_transient($key, 1, self::TTL);
        return true;
    }

    /* ---------- Widget ---------- */

    public function widget(): string {
        return '<div class="yl-captcha" data-endpoint="' . esc_url(rest_url(self::NS . '/challenge')) . '" aria-live="polite">'
             . '<input type="hidden" name="ylc_salt"><input type="hidden" name="ylc_number"><input type="hidden" name="ylc_challenge"><input type="hidden" name="ylc_signature">'
             . '<span class="yl-captcha__status">' . esc_html__('Checking that you are not a bot …', 'yl-captcha') . '</span></div>';
    }
    public function print_widget(): void { echo wp_kses($this->widget(), ['div' => ['class' => [], 'data-endpoint' => [], 'aria-live' => []], 'input' => ['type' => [], 'name' => []], 'span' => ['class' => []]]); }

    public function assets(): void {
        wp_enqueue_script('yl-captcha', plugins_url('assets/yl-captcha.js', __FILE__), [], self::VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        wp_enqueue_style('yl-captcha', plugins_url('assets/yl-captcha.css', __FILE__), [], self::VERSION);
        wp_localize_script('yl-captcha', 'ylCaptchaI18n', [
            'solving' => __('Checking that you are not a bot …', 'yl-captcha'),
            'ok'      => __('Verified. You can submit the form.', 'yl-captcha'),
            'error'   => __('Verification failed. Please reload the page.', 'yl-captcha'),
        ]);
    }

    /* ---------- Form hooks ---------- */

    public function check_comment(array $data): array {
        if (!current_user_can('moderate_comments') && !$this->verify()) {
            wp_die(esc_html__('Captcha verification failed. Please go back and try again.', 'yl-captcha'), esc_html__('Comment blocked', 'yl-captcha'), ['response' => 403, 'back_link' => true]);
        }
        return $data;
    }
    public function check_register(WP_Error $errors): WP_Error {
        if (!$this->verify()) $errors->add('yl_captcha', __('<strong>Error:</strong> Captcha verification failed.', 'yl-captcha'));
        return $errors;
    }
    public function check_login($user, string $username, string $password) {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])) : '';
        if ($method !== 'POST' || $username === '') return $user; // only real submissions
        if (defined('XMLRPC_REQUEST') || defined('REST_REQUEST') || (defined('DOING_AJAX') && DOING_AJAX)) return $user;
        if (!$this->verify()) return new WP_Error('yl_captcha', __('<strong>Error:</strong> Captcha verification failed.', 'yl-captcha'));
        return $user;
    }
    public function check_lostpassword(WP_Error $errors): void {
        if (!$this->verify()) $errors->add('yl_captcha', __('<strong>Error:</strong> Captcha verification failed.', 'yl-captcha'));
    }

    /* ---------- Settings ---------- */

    public function menu(): void {
        add_options_page('YL Captcha', 'YL Captcha', 'manage_options', 'yl-captcha', [$this, 'page']);
    }
    public function settings(): void {
        register_setting('yl_captcha', self::OPTION, ['sanitize_callback' => function ($in) {
            $o = $this->opt;
            foreach (['comments', 'register', 'login', 'lostpassword', 'cf7'] as $k) $o[$k] = empty($in[$k]) ? 0 : 1;
            $o['difficulty'] = max(5000, min(500000, (int) ($in['difficulty'] ?? 50000)));
            return $o;
        }]);
    }
    public function page(): void {
        $o = $this->opt; ?>
        <div class="wrap"><h1>YL Captcha</h1>
        <p><?php esc_html_e('A proof-of-work captcha: the visitor\'s browser solves a small puzzle in the background, bots have to spend CPU time for every submission. No external service, no cookies, no personal data leaves your server.', 'yl-captcha'); ?></p>
        <form method="post" action="options.php"><?php settings_fields('yl_captcha'); ?>
        <table class="form-table" role="presentation">
          <tr><th scope="row"><?php esc_html_e('Protect', 'yl-captcha'); ?></th><td>
            <?php foreach (['comments' => __('Comment form', 'yl-captcha'), 'register' => __('Registration form', 'yl-captcha'), 'lostpassword' => __('Lost password form', 'yl-captcha'), 'login' => __('Login form (adds ~1 s to every login)', 'yl-captcha'), 'cf7' => __('Contact Form 7', 'yl-captcha')] as $k => $label): ?>
              <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION . "[$k]"); ?>" value="1" <?php checked($o[$k]); ?>> <?php echo esc_html($label); ?></label><br>
            <?php endforeach; ?></td></tr>
          <tr><th scope="row"><label for="ylc_diff"><?php esc_html_e('Difficulty', 'yl-captcha'); ?></label></th><td>
            <input type="number" id="ylc_diff" name="<?php echo esc_attr(self::OPTION . '[difficulty]'); ?>" value="<?php echo (int) $o['difficulty']; ?>" min="5000" max="500000" step="5000">
            <p class="description"><?php esc_html_e('Maximum number the browser has to search. 50,000 takes about 0.2–1 s on a phone. Raise it if spam still gets through.', 'yl-captcha'); ?></p></td></tr>
          <tr><th scope="row"><?php esc_html_e('Other forms', 'yl-captcha'); ?></th><td><code>[yl_captcha]</code> <?php esc_html_e('inside any form, then verify with', 'yl-captcha'); ?> <code>YL_Captcha::instance()->verify($_POST)</code></td></tr>
        </table><?php submit_button(); ?></form></div><?php
    }
}

register_activation_hook(__FILE__, ['YL_Captcha', 'activate']);
add_action('plugins_loaded', ['YL_Captcha', 'instance']);
