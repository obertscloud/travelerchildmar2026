<?php
/**
 * Child theme functions.php
 * User: MSI
 * Date: 21/08/2015
 */

/* =========================
   Load parent & child styles
   ========================= */
add_action('wp_enqueue_scripts', 'enqueue_parent_styles', 20);
function enqueue_parent_styles() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style', get_stylesheet_uri());
}

/* Load scripts on the User Dashboard page template */
add_action('wp_enqueue_scripts', function () {
    if (is_page_template('template-user.php')) {
        wp_enqueue_script('jquery'); // optional (template uses vanilla JS too)
    }
});

/* Load partnerlisting CSS on partners template */
function enqueue_partner_preview_css() {
    if (is_page_template('page-partners.php')) {
        wp_enqueue_style(
            'partner-preview',
            get_stylesheet_directory_uri() . '/partner-preview.css',
            array(),
            '1.0',
            'all'
        );
    }
}
add_action('wp_enqueue_scripts', 'enqueue_partner_preview_css');

/* =========================
   Affiliate dashboard page includes (helpers)
   ========================= */
function pbp_affiliate_dashboard_page($user_data) {
    $path = get_stylesheet_directory() . '/partner-affiliate/affiliate-dashboard.php';
    if (file_exists($path)) { include $path; }
    else { echo '<p style="color:red;">Error: affiliate-dashboard.php file not found.</p>'; }
}
function pbp_affiliate_book_page($user_data) {
    $path = get_stylesheet_directory() . '/partner-affiliate/book.php';
    if (file_exists($path)) { include $path; }
    else { echo '<p style="color:red;">Error: Book file not found.</p>'; }
}
function pbp_affiliate_bookings_page($user_data) {
    $path = get_stylesheet_directory() . '/partner-affiliate/bookings.php';
    if (file_exists($path)) { include $path; }
    else { echo '<p style="color:red;">Error: Bookings file not found.</p>'; }
}
function pbp_affiliate_commissions_page($user_data) {
    $path = get_stylesheet_directory() . '/partner-affiliate/commissions.php';
    if (file_exists($path)) { include $path; }
    else { echo '<p style="color:red;">Error: Commissions file not found.</p>'; }
}
function pbp_affiliate_account_page($user_data) {
    $path = get_stylesheet_directory() . '/partner-affiliate/account.php';
    if (file_exists($path)) { include $path; }
    else { echo '<p style="color:red;">Error: Account file not found.</p>'; }
}

/* =========================
   Expose ajaxurl for logged-in users (optional)
   ========================= */
add_action('wp_head', function () {
    if (is_user_logged_in()) {
        echo '<script>var ajaxurl = "' . esc_url(admin_url('admin-ajax.php')) . '";</script>';
    }
});

/* =========================
   reCAPTCHA keys (server-side only)
   ========================= */
if (!defined('MY_RECAPTCHA_SITE_KEY')) {
    define('MY_RECAPTCHA_SITE_KEY', '6LcB-6MrAAAAAAs2aQQwdpRxdZAXfKrt-UcF3n8M');
}
if (!defined('MY_RECAPTCHA_SECRET_KEY')) {
    define('MY_RECAPTCHA_SECRET_KEY', '6LcB-6MrAAAAABInszdzBKGJTxf7ObXX3Uag7lfs');
}

/* =========================
   reCAPTCHA helpers
   ========================= */
if (!function_exists('pbp_recaptcha_get_token')) {
    function pbp_recaptcha_get_token($field = 'g-recaptcha-response') {
        if (!isset($_POST[$field])) return '';
        $raw = $_POST[$field];
        if (is_array($raw)) {
            foreach ($raw as $v) {
                if (is_string($v)) {
                    $v = trim($v);
                    if ($v !== '') return $v;
                }
            }
            return '';
        }
        return is_string($raw) ? trim($raw) : '';
    }
}

if (!function_exists('pbp_recaptcha_siteverify')) {
    function pbp_recaptcha_siteverify($token) {
        $resp = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => MY_RECAPTCHA_SECRET_KEY,
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
            ],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'codes' => ['unavailable'], 'raw' => []];
        }
        $data  = json_decode(wp_remote_retrieve_body($resp), true);
        $ok    = !empty($data['success']);
        $codes = !empty($data['error-codes']) ? (array) $data['error-codes'] : [];
        return ['ok' => $ok, 'codes' => $codes, 'raw' => $data];
    }
}

/* =========================
   LOGIN: override Traveler's st_login_popup
   ========================= */
if (!function_exists('pbp_handle_login_override')) {
    function pbp_handle_login_override() {
        @header('X-PBP-Handler: st_login_popup');

        if (is_user_logged_in()) {
            wp_send_json(['status' => 1, 'message' => __('Already logged in.', 'traveler'), 'reload' => 1]);
        }

        $token = pbp_recaptcha_get_token();
        @header('X-PBP-Token-Len: ' . strlen($token));
        if ($token === '') {
            @header('X-Recaptcha-Codes: missing-input-response');
            wp_send_json([
                'status'  => 0,
                'message' => __('Please complete the captcha.', 'traveler'),
                'codes'   => ['missing-input-response'],
                'raw'     => null,
            ]);
        }

        $vr = pbp_recaptcha_siteverify($token);
        if (!$vr['ok']) {
            $codes = !empty($vr['codes']) ? implode(', ', $vr['codes']) : 'unknown';
            @header('X-Recaptcha-Codes: ' . $codes);
            wp_send_json([
                'status'  => 0,
                'message' => sprintf(__('Captcha failed (%s). Please try again.', 'traveler'), esc_html($codes)),
                'codes'   => $vr['codes'],
                'raw'     => isset($vr['raw']) ? $vr['raw'] : null,
            ]);
        }

        $login_input = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password    = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $remember    = !empty($_POST['remember']);

        if ($login_input === '' || $password === '') {
            wp_send_json(['status' => 0, 'message' => __('Username and password are required.', 'traveler')]);
        }

        $user = get_user_by('login', $login_input);
        if (!$user && is_email($login_input)) $user = get_user_by('email', $login_input);
        if (!$user) {
            @header('X-PBP-Auth: user-not-found');
            wp_send_json(['status' => 0, 'message' => __('Invalid username or email.', 'traveler')]);
        }

        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            @header('X-PBP-Auth: bad-password');
            wp_send_json(['status' => 0, 'message' => __('Incorrect password.', 'traveler')]);
        }

        @header('X-PBP-Auth: manual-ok');
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        $redirect = home_url('/');
        if (function_exists('st')) {
            $candidates = ['page_user_dashboard','page_user_account','page_my_account'];
            foreach ($candidates as $opt) {
                $pid = (int) st()->get_option($opt);
                if ($pid) { $redirect = get_permalink($pid); break; }
            }
        }
        if (!empty($_POST['redirect_to'])) {
            $rt = esc_url_raw(wp_unslash($_POST['redirect_to']));
            if ($rt) $redirect = $rt;
        }
        @header('X-Redirect: ' . $redirect);

        wp_send_json([
            'status'   => 1,
            'message'  => __('Login successful. Redirecting...', 'traveler'),
            'redirect' => $redirect,
            'reload'   => 1,
        ]);
    }
}

/* Early intercept for st_login_popup */
add_action('admin_init', function () {
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] === 'st_login_popup') {
        pbp_handle_login_override();
        wp_die();
    }
}, 0);

/* Remove theme handlers for st_login_popup and add ours */
add_action('init', function () {
    if (function_exists('remove_all_actions')) {
        remove_all_actions('wp_ajax_nopriv_st_login_popup');
        remove_all_actions('wp_ajax_st_login_popup');
    } else {
        remove_all_filters('wp_ajax_nopriv_st_login_popup');
        remove_all_filters('wp_ajax_st_login_popup');
    }
    add_action('wp_ajax_nopriv_st_login_popup', 'pbp_handle_login_override', 1);
    add_action('wp_ajax_st_login_popup',        'pbp_handle_login_override', 1);
}, 9999);

/* =========================
   REGISTER: enforce captcha then let theme continue
   ========================= */
if (!function_exists('pbp_guard_registration_captcha')) {
    function pbp_guard_registration_captcha() {
        if (is_user_logged_in()) return;
        $token = pbp_recaptcha_get_token();
        if ($token === '') {
            wp_send_json(['status' => 0, 'message' => __('Please complete the captcha.', 'traveler')]);
        }
        $vr = pbp_recaptcha_siteverify($token);
        if (!$vr['ok']) {
            $codes = !empty($vr['codes']) ? implode(', ', $vr['codes']) : 'unknown';
            wp_send_json(['status' => 0, 'message' => sprintf(__('Captcha failed (%s). Please try again.', 'traveler'), esc_html($codes))]);
        }
    }
}
add_action('wp_ajax_nopriv_st_registration_popup', 'pbp_guard_registration_captcha', 0);
add_action('wp_ajax_st_registration_popup',        'pbp_guard_registration_captcha', 0);

/* =========================
   RESET PASSWORD: enforce captcha then let theme continue
   ========================= */
if (!function_exists('pbp_guard_reset_password_captcha')) {
    function pbp_guard_reset_password_captcha() {
        if (is_user_logged_in()) return;
        $token = pbp_recaptcha_get_token();
        if ($token === '') {
            wp_send_json(['status' => 0, 'message' => __('Please complete the captcha.', 'traveler')]);
        }
        $vr = pbp_recaptcha_siteverify($token);
        if (!$vr['ok']) {
            $codes = !empty($vr['codes']) ? implode(', ', $vr['codes']) : 'unknown';
            wp_send_json(['status' => 0, 'message' => sprintf(__('Captcha failed (%s). Please try again.', 'traveler'), esc_html($codes))]);
        }
    }
}
add_action('wp_ajax_nopriv_st_reset_password', 'pbp_guard_reset_password_captcha', 0);
add_action('wp_ajax_st_reset_password',        'pbp_guard_reset_password_captcha', 0);

/* =========================
   Front-end redirect helper for login/register
   ========================= */
add_action('wp_footer', function () {
    if (is_user_logged_in()) return;

    $login_page_id = function_exists('st') ? (int) st()->get_option('page_user_login') : 0;
    $login_url     = $login_page_id ? get_permalink($login_page_id) : home_url('/login/');
    ?>
    <script>
    (function($){
      function parseAction(settings){
        try{
          if (!settings) return '';
          if (typeof settings.data === 'string') {
            var m = settings.data.match(/(?:^|&)action=([^&]+)/);
            return m ? decodeURIComponent(m[1]) : '';
          }
          if (settings.data && settings.data.get) {
            return settings.data.get('action') || '';
          }
        }catch(e){}
        return '';
      }
      function onAjaxSuccess(e, xhr, settings){
        try{
          if (!settings || !settings.url || settings.url.indexOf('admin-ajax.php') === -1) return;
          var action = parseAction(settings);
          if (action !== 'st_login_popup' && action !== 'st_registration_popup') return;
          var hdr = xhr.getResponseHeader ? xhr.getResponseHeader('X-Redirect') : '';
          if (action === 'st_login_popup' && hdr) { window.location.assign(hdr); return; }
          var txt = xhr && xhr.responseText ? xhr.responseText : ''; if (!txt) return;
          var res = {}; try { res = JSON.parse(txt); } catch(e){ return; }
          if (!(res && (res.status === 1 || res.status === true))) return;
          if (action === 'st_login_popup') {
            var url = (res.redirect && typeof res.redirect === 'string') ? res.redirect : window.location.href;
            window.location.assign(url); return;
          }
          if (action === 'st_registration_popup') {
            var url = <?php echo json_encode($login_url); ?>;
            try {
              if (typeof settings.data === 'string') {
                var m = settings.data.match(/(?:^|&)redirect_to=([^&]+)/);
                if (m) url = decodeURIComponent(m[1]);
              } else if (settings.data && settings.data.get) {
                var rt = settings.data.get('redirect_to'); if (rt) url = rt;
              }
            } catch(e){}
            window.location.assign(url);
          }
        }catch(err){}
      }
      var $jq = window.jQuery || window.$;
      if ($jq && $jq(document) && $jq(document).on) { $jq(document).on('ajaxSuccess', onAjaxSuccess); }
    })(window.jQuery || window.$);
    </script>
    <?php
}, 999);

/* =========================
   Affiliate booking AJAX (front-end)
   ========================= */

/* Helper: get allowed IDs for user+post_type (no plugin dependency) */
if (!function_exists('pbp_get_allowed_post_ids')) {
    function pbp_get_allowed_post_ids($uid, $pt) {
        $ids = [];
        $child_utils = get_stylesheet_directory() . '/partner-affiliate/class-pbp-utils.php';
        if (!class_exists('PBP_Utils') && file_exists($child_utils)) {
            require_once $child_utils;
        }
        if (class_exists('PBP_Utils') && method_exists('PBP_Utils', 'get_partner_allowed_posts')) {
            try { $ids = PBP_Utils::get_partner_allowed_posts($uid, $pt); } catch (Exception $e) { $ids = []; }
        }
        if (!is_array($ids)) $ids = [];
        $ids = array_filter($ids, function($v){ return $v !== '' && $v !== null; });
        $ids = array_map('intval', $ids);
        return array_values(array_unique($ids));
    }
}

/* Single, hardened handler */
if (!function_exists('pp_ajax_create_manual_booking_autofill_cb')) {
    function pp_ajax_create_manual_booking_autofill_cb() {
        nocache_headers();
        ob_start();
        try {
            check_ajax_referer('pp_create_booking_nonce', 'nonce');
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'forbidden'], 403);

            $uid   = get_current_user_id();
            $user  = wp_get_current_user();
            $booker_email = $user ? $user->user_email : '';

            $ptype = function_exists('get_field') ? get_field('partner_type', 'user_' . $uid) : '';
            $ptype = is_string($ptype) ? strtolower(trim($ptype)) : '';
            if (!in_array($ptype, ['affiliate','affilliate'], true) && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'forbidden'], 403);
            }

            $post_id = isset($_POST['tour_id']) ? absint($_POST['tour_id']) : 0;
            $post    = $post_id ? get_post($post_id) : null;
            $pt      = $post ? get_post_type($post_id) : '';
            if (!$post || !in_array($pt, ['st_tours','st_activity'], true)) {
                wp_send_json_error(['message' => 'Invalid post'], 400);
            }

            // Enforce allowed list if defined (safe even if helper absent)
            $allowed = pbp_get_allowed_post_ids($uid, $pt);
            if (!empty($allowed) && !in_array($post_id, $allowed, true)) {
                wp_send_json_error(['message' => 'Not allowed for this item'], 403);
            }

            // Widget (support both keys)
            $widget_id  = get_post_meta($post_id, 'ticketinghub_widget_id', true);
            if ($widget_id === '') $widget_id = get_post_meta($post_id, 'ticketinghub_widget', true);

            // Customer fields
            $first_name = isset($_POST['customer_first_name']) ? sanitize_text_field(wp_unslash($_POST['customer_first_name'])) : '';
            $last_name  = isset($_POST['customer_last_name'])  ? sanitize_text_field(wp_unslash($_POST['customer_last_name']))  : '';
            $cust_name  = trim(($first_name . ' ' . $last_name));
            if ($cust_name === '') $cust_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
            $cust_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
            $cust_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';

            // Counts, schedule, refs
            $adult_num  = max(1, absint($_POST['adult_number'] ?? 1));
            $child_num  = max(0, absint($_POST['child_number'] ?? 0));
            $infant_num = 0;
            $check_in   = isset($_POST['check_in']) ? sanitize_text_field(wp_unslash($_POST['check_in'])) : date('d/m/Y');
            $start_time = isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '';
            $th_ref     = isset($_POST['th_ref']) ? sanitize_text_field(wp_unslash($_POST['th_ref'])) : '';

            // Tax/fee/txid
            $tax_percent    = isset($_POST['tax_percent']) ? floatval($_POST['tax_percent']) : 0;
            $fee_title      = isset($_POST['fee_title']) ? sanitize_text_field(wp_unslash($_POST['fee_title'])) : '';
            $fee_amount     = isset($_POST['fee_amount']) ? floatval($_POST['fee_amount']) : 0;
            $transaction_id = isset($_POST['transaction_id']) ? sanitize_text_field(wp_unslash($_POST['transaction_id'])) : '';

            // Duration + base price
            $duration = get_post_meta($post_id, 'duration', true);
            if ($duration === '') $duration = get_post_meta($post_id, 'duration_day', true);

            $price_type = 'per_person';
            if ($pt === 'st_activity') {
                $p_type     = get_post_meta($post_id, 'price_type', true);
                $price_type = ($p_type === 'price_by_fixed') ? 'total' : 'per_person';
                $adult_price = ($p_type === 'price_by_fixed')
                    ? floatval(get_post_meta($post_id, 'fixed_price', true))
                    : floatval(get_post_meta($post_id, 'adult_price', true));
            } else {
                $adult_price = floatval(get_post_meta($post_id, 'min_price', true));
            }
            if ($adult_price < 0) $adult_price = 0;

            $item_title = get_the_title($post_id);

            // Totals
            $total_override = isset($_POST['total_override']) ? floatval($_POST['total_override']) : 0;
            $base_total     = ($price_type === 'per_person') ? ($adult_price * $adult_num) : $adult_price;
            $pre_fee_total  = $total_override > 0 ? $total_override : $base_total;
            $total_order    = $pre_fee_total + max(0, $fee_amount);
            $tax_amount     = $tax_percent > 0 ? round($total_order * ($tax_percent / 100), 2) : 0;

            // Commission config for this partner
            $rate  = 0.0;
            $ctype = 'percent'; // 'percent' or 'fixed'
            if (class_exists('PBP_Utils') && method_exists('PBP_Utils', 'get_partner_commission')) {
                try {
                    $cfg = PBP_Utils::get_partner_commission($uid);
                    if (is_array($cfg)) {
                        $rate  = isset($cfg['rate']) ? floatval($cfg['rate']) : 0.0;
                        $ctype = strtolower(trim($cfg['type'] ?? 'percent'));
                        if ($ctype !== 'fixed') $ctype = 'percent';
                    }
                } catch (Exception $e) {}
            }
            $commission_amount = ($ctype === 'fixed') ? $rate : round($total_order * ($rate / 100), 2);

            // Create order
            $order_title = 'Booking – ' . $item_title . ' – ' . current_time('Y-m-d H:i');
            $order_id = wp_insert_post([
                'post_title'  => $order_title,
                'post_type'   => 'st_order',
                'post_status' => 'publish',
            ]);
            if (!$order_id || is_wp_error($order_id)) {
                wp_send_json_error(['message' => 'Failed to create booking record']);
            }

            // raw_data ARRAY (Traveler-friendly shape)
            $extras = [];
            if ($fee_amount > 0) {
                $extras[] = [
                    'title'       => ($fee_title !== '' ? $fee_title : 'Fee'),
                    'price'       => $fee_amount,
                    'number'      => 1,
                    'price_total' => $fee_amount,
                    'extra_name'  => sanitize_title($fee_title !== '' ? $fee_title : 'Fee'),
                ];
            }
            $raw_data = [
                'price_type'           => $price_type,
                'adult_price'          => $adult_price,
                'child_price'          => 0,
                'infant_price'         => 0,
                'adult_number'         => $adult_num,
                'child_number'         => $child_num,
                'infant_number'        => $infant_num,
                'discount'             => 0,
                'ori_price'            => $total_order,
                'sale_price'           => $total_order,
                'total_price'          => $total_order,
                'tax_percent'          => $tax_percent,
                'tax_amount'           => $tax_amount,
                'extra_price'          => ['value' => $extras, 'total' => array_sum(array_column($extras, 'price_total'))],
                'extras'               => $extras,
                'duration'             => $duration,
                'st_booking_post_type' => $pt,
                'st_booking_id'        => $post_id,
                'title_cart'           => $item_title,
                'starttime'            => $start_time,
                'check_in'             => str_replace('/', '\/', $check_in),
                'check_out'            => str_replace('/', '\/', $check_in),
                'customer_first_name'  => $first_name,
                'customer_last_name'   => $last_name,
                'customer_name'        => $cust_name,
                'customer_email'       => $cust_email,
                'customer_phone'       => $cust_phone,
                'th_ref'               => $th_ref,
                'transaction_id'       => $transaction_id,
                'gateway'              => $transaction_id ? 'stripe' : '',
                'pay_now'              => $total_order,
            ];

            // Action payload (Traveler hook)
            $action_data = [
                'order_item_id'        => $order_id,
                'type'                 => 'normal_booking',
                'check_in'             => $check_in,
                'check_out'            => $check_in,
                'starttime'            => $start_time,
                'duration'             => $duration,
                'adult_number'         => $adult_num,
                'child_number'         => $child_num,
                'st_booking_id'        => $post_id,
                'st_booking_post_type' => $pt,
                'partner_id'           => $uid,

                // Commission: rate when percent, fixed value when fixed
                'commission'           => ($ctype === 'percent') ? $rate : 0,
                'commission_fixed'     => ($ctype === 'fixed')   ? $rate : 0,

                'total_order'          => $total_order,
                'status'               => 'pending',
                'raw_data'             => wp_json_encode($raw_data),
                'wc_order_id'          => $post_id,
                'origin_id'            => $post_id,
                'cancel_refund_status' => 'pending',
                'title_cart'           => $item_title,

                // Our snapshots (helpful downstream)
                'pp_commission_type'   => $ctype,
                'pp_commission_rate'   => $rate,
                'pp_commission_amount' => $commission_amount,
            ];
            do_action('st_save_order_item_meta', $action_data, $order_id, 'normal_booking');

            // Save raw_data/st_cart_info as ARRAYS for admin view
            update_post_meta($order_id, 'raw_data',     $raw_data);
            update_post_meta($order_id, 'st_cart_info', $raw_data);

            // REQUIRED metas for admin view
            update_post_meta($order_id, 'id_user', $uid);
            $type_tour = get_post_meta($post_id, 'type_tour', true);
            if ($type_tour) update_post_meta($order_id, 'type_tour', $type_tour);
            update_post_meta($order_id, 'adult_price',  $adult_price);
            update_post_meta($order_id, 'child_price',  0);
            update_post_meta($order_id, 'infant_price', 0);
            update_post_meta($order_id, 'adult_number', $adult_num);
            update_post_meta($order_id, 'child_number', $child_num);
            update_post_meta($order_id, 'infant_number', $infant_num);
            update_post_meta($order_id, 'check_in',     $check_in);
            update_post_meta($order_id, 'starttime',    $start_time);
            update_post_meta($order_id, 'duration',     $duration);
            update_post_meta($order_id, 'st_booking_post_type', $pt);
            update_post_meta($order_id, 'st_booking_id',        $post_id);
            update_post_meta($order_id, 'item_id',              $post_id);
            update_post_meta($order_id, 'item_title',           $item_title);

            if ($cust_email) { update_post_meta($order_id, 'st_email', $cust_email); update_post_meta($order_id, 'email', $cust_email); }
            if ($cust_phone) { update_post_meta($order_id, 'st_phone', $cust_phone); update_post_meta($order_id, 'phone', $cust_phone); }
            if ($first_name) { update_post_meta($order_id, 'st_first_name', $first_name); update_post_meta($order_id, 'first_name', $first_name); }
            if ($last_name)  { update_post_meta($order_id, 'st_last_name',  $last_name);  update_post_meta($order_id, 'last_name',  $last_name); }
            if ($cust_name)  { update_post_meta($order_id, 'st_name', $cust_name); update_post_meta($order_id, 'customer_name', $cust_name); update_post_meta($order_id, 'st_full_name', $cust_name); }

            if ($fee_amount > 0) update_post_meta($order_id, 'booking_fee_price', $fee_amount);

            update_post_meta($order_id, 'total_order', $total_order);
            update_post_meta($order_id, 'total_price', $total_order);
            update_post_meta($order_id, 'pay_amount',  $total_order);
            update_post_meta($order_id, 'tax_percent', $tax_percent);
            update_post_meta($order_id, 'tax_amount',  $tax_amount);

            if ($booker_email) {
                update_post_meta($order_id, 'booker_email',  $booker_email);
                update_post_meta($order_id, 'st_user_email', $booker_email);
                update_post_meta($order_id, 'user_mail',     $booker_email);
            }
            if ($transaction_id) {
                update_post_meta($order_id, 'transaction_id',  $transaction_id);
                update_post_meta($order_id, 'payment_gateway', 'stripe');
            }

            update_post_meta($order_id, 'status',   'pending');
            update_post_meta($order_id, 'st_status','pending');

            // Ensure extras meta is array to avoid admin editor fatal
            $extras_meta = get_post_meta($order_id, 'extras', true);
            if (!is_array($extras_meta) || !isset($extras_meta['value'])) {
                update_post_meta($order_id, 'extras', ['value' => []]);
            }

            // Commission meta snapshots (for listings and reports)
            update_post_meta($order_id, 'commission',        ($ctype === 'percent') ? $rate : 0);
            update_post_meta($order_id, 'commission_fixed',  ($ctype === 'fixed')   ? $rate : 0);
            update_post_meta($order_id, 'commission_type',   $ctype);
            update_post_meta($order_id, 'commission_rate',   $rate);
            update_post_meta($order_id, 'commission_amount', $commission_amount);

            // Our namespaced keys too
            update_post_meta($order_id, '_pp_commission_type',   $ctype);
            update_post_meta($order_id, '_pp_commission_rate',   $rate);
            update_post_meta($order_id, '_pp_commission_amount', $commission_amount);

            $noise = ob_get_clean();
            header('X-PBP: create-booking');
            wp_send_json_success([
                'message'             => 'Internal booking record created.',
                'order_id'            => $order_id,
                'post_id'             => $post_id,
                'post_title'          => $item_title,
                'widget_id'           => $widget_id,
                'status'              => 'pending',
                'commission_type'     => $ctype,
                'commission_rate'     => $rate,
                'commission_amount'   => $commission_amount,
                'noise'               => $noise,
            ]);
        } catch (Exception $e) {
            $noise = ob_get_clean();
            header('X-PBP: create-booking-error');

            $resp = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'noise'   => $noise,
            ];

            // Back-compat for older WP: status code when supported
            if (function_exists('wp_send_json_error')) {
                if (version_compare(get_bloginfo('version'), '5.5', '>=')) {
                    wp_send_json_error($resp, 500);
                } else {
                    status_header(500);
                    wp_send_json_error($resp);
                }
            } else {
                status_header(500);
                die(json_encode(['success' => false, 'data' => $resp]));
            }
        }
    }
}

/* Register handler and early-intercept to avoid any redirects/html during admin-ajax */
add_action('wp_ajax_pp_ajax_create_manual_booking_autofill',       'pp_ajax_create_manual_booking_autofill_cb');
add_action('wp_ajax_nopriv_pp_ajax_create_manual_booking_autofill','pp_ajax_create_manual_booking_autofill_cb');
add_action('admin_init', function () {
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] === 'pp_ajax_create_manual_booking_autofill') {
        pp_ajax_create_manual_booking_autofill_cb();
        wp_die();
    }
}, 0);

/* Tag orders created by affiliates (Traveler’s flow and our Ajax flow) */
add_action('st_save_order_item_meta', 'pp_affiliate_stamp_order', 50, 3);
function pp_affiliate_stamp_order($data, $order_id, $type) {
    // Determine the partner ID from payload or order meta
    $partner_id = isset($data['partner_id']) ? (int) $data['partner_id'] : 0;
    if (!$partner_id) {
        $partner_id = (int) get_post_meta($order_id, 'id_user', true);
    }
    if (!$partner_id && is_user_logged_in()) {
        $partner_id = get_current_user_id();
    }
    if (!$partner_id) return;

    $u = get_userdata($partner_id);
    if (!$u || !in_array('partner', (array) $u->roles, true)) return;

    $ptype_raw = function_exists('get_field') ? get_field('partner_type', 'user_' . $partner_id) : get_user_meta($partner_id, 'partner_type', true);
    $ptype = is_string($ptype_raw) ? strtolower(trim($ptype_raw)) : '';
    if (!in_array($ptype, ['affiliate','affilliate'], true) && !current_user_can('manage_options')) return;

    // Total from payload or meta
    $total = isset($data['total_order']) ? floatval($data['total_order']) : 0.0;
    if (!$total) $total = (float) get_post_meta($order_id, 'total_order', true);
    if (!$total) $total = (float) get_post_meta($order_id, 'total_price', true);

    // Commission config for that partner
    $rate = 0; $ctype = 'percent';
    if (class_exists('PBP_Utils') && method_exists('PBP_Utils', 'get_partner_commission')) {
        try {
            $c = PBP_Utils::get_partner_commission($partner_id);
            if (is_array($c)) {
                $rate  = isset($c['rate']) ? floatval($c['rate']) : 0;
                $ctype = strtolower(trim($c['type'] ?? 'percent'));
                if ($ctype !== 'fixed') $ctype = 'percent';
            }
        } catch (Exception $e) {}
    }
    $amount = ($ctype === 'percent') ? round($total * ($rate / 100), 2) : (float) $rate;

    // Stamp affiliate meta
    update_post_meta($order_id, '_pp_affiliate', 1);
    update_post_meta($order_id, '_pp_affiliate_user', $partner_id);

    // Common commission meta used by various views
    update_post_meta($order_id, '_pp_commission_type',   $ctype);
    update_post_meta($order_id, '_pp_commission_rate',   $rate);
    update_post_meta($order_id, '_pp_commission_amount', $amount);

    // Traveler-facing keys (keep these aligned)
    update_post_meta($order_id, 'commission',        ($ctype === 'percent') ? $rate : 0);
    update_post_meta($order_id, 'commission_fixed',  ($ctype === 'fixed')   ? $rate : 0);
    update_post_meta($order_id, 'commission_type',   $ctype);
    update_post_meta($order_id, 'commission_rate',   $rate);
    update_post_meta($order_id, 'commission_amount', $amount);

    // Source mark
    $listing_id = isset($data['st_booking_id']) ? absint($data['st_booking_id']) : 0;
    $widget_id  = $listing_id ? get_post_meta($listing_id, 'ticketinghub_widget_id', true) : '';
    if ($widget_id === '' && $listing_id) {
        $widget_id = get_post_meta($listing_id, 'ticketinghub_widget', true);
    }
    update_post_meta($order_id, '_pp_affiliate_source', $widget_id ? 'ticketinghub' : 'theme');
    if ($widget_id) {
        update_post_meta($order_id, '_pp_ticketinghub_widget_id', $widget_id);
    }
}

/* =========================
   Repair helpers (safe for old orders)
   ========================= */
if (!function_exists('pp_normalize_order_meta')) {
    function pp_normalize_order_meta($order_id) {
        $changed = false;

        // extras must be an array with ['value' => []]
        $extras = get_post_meta($order_id, 'extras', true);
        if (is_string($extras)) {
            $dec = json_decode($extras, true);
            $extras = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
        }
        if (!is_array($extras) || !isset($extras['value']) || !is_array($extras['value'])) {
            update_post_meta($order_id, 'extras', ['value' => []]);
            $changed = true;
        }

        // raw_data must be an array and include extra_price shape
        $raw = get_post_meta($order_id, 'raw_data', true);
        if (is_string($raw)) {
            $dec = json_decode($raw, true);
            $raw = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : [];
        }
        if (!is_array($raw)) $raw = [];
        if (!isset($raw['extra_price']) || !is_array($raw['extra_price'])) {
            $raw['extra_price'] = ['value' => [], 'total' => 0];
            $changed = true;
        } else {
            if (!isset($raw['extra_price']['value']) || !is_array($raw['extra_price']['value'])) {
                $raw['extra_price']['value'] = [];
                $changed = true;
            }
            if (!isset($raw['extra_price']['total'])) {
                $raw['extra_price']['total'] = 0;
                $changed = true;
            }
        }
        foreach (['adult_number','child_number','infant_number'] as $k) {
            if (!isset($raw[$k])) $raw[$k] = (int) get_post_meta($order_id, $k, true);
        }
        if (!isset($raw['total_price'])) $raw['total_price'] = (float) get_post_meta($order_id, 'total_price', true);
        if ($changed) update_post_meta($order_id, 'raw_data', $raw);

        // st_cart_info mirror
        $cart = get_post_meta($order_id, 'st_cart_info', true);
        if (!is_array($cart) || !isset($cart['extra_price'])) {
            update_post_meta($order_id, 'st_cart_info', $raw);
            $changed = true;
        }

        // data_prices used by admin view
        $dp = get_post_meta($order_id, 'data_prices', true);
        if (!is_array($dp)) $dp = [];
        $tp = get_post_meta($order_id, 'total_price', true);
        if ($tp === '' || $tp === null) $tp = get_post_meta($order_id, 'total_order', true);
        $tp = (float) $tp;
        if (!isset($dp['total_price_origin']))  { $dp['total_price_origin']  = $tp; $changed = true; }
        if (!isset($dp['total_bulk_discount'])) { $dp['total_bulk_discount'] = 0;  $changed = true; }
        if (!isset($dp['coupon_price']))        { $dp['coupon_price']        = 0;  $changed = true; }
        if (!isset($dp['deposit_price']))       { $dp['deposit_price']       = 0;  $changed = true; }
        if ($changed) update_post_meta($order_id, 'data_prices', $dp);

        // price and total fallbacks
        foreach (['adult_price','child_price','infant_price'] as $k) {
            $v = get_post_meta($order_id, $k, true);
            if ($v === '' || $v === null) update_post_meta($order_id, $k, 0);
        }
        $v = get_post_meta($order_id, 'total_price', true);
        if ($v === '' || $v === null) update_post_meta($order_id, 'total_price', $tp);

        // id_user fallback
        $id_user = get_post_meta($order_id, 'id_user', true);
        if (!$id_user) update_post_meta($order_id, 'id_user', get_current_user_id());

        return $changed;
    }
}

// Auto-repair when editing an order in admin (structure only)
add_action('admin_init', function () {
    if (!is_admin()) return;
    $order_id = isset($_GET['order_item_id']) ? absint($_GET['order_item_id']) : 0;
    if (!$order_id) return;
    pp_normalize_order_meta($order_id);
}, 0);

// Tools → Repair Affiliate Orders (bulk tools)
add_action('admin_menu', function(){
    add_management_page('Repair Affiliate Orders', 'Repair Orders', 'manage_options', 'pp-repair-orders', function(){
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>Repair Affiliate Orders</h1>';

        // Structure repair
        if (isset($_POST['pp_repair_run'])) {
            $repaired = 0; $scanned = 0;
            $q = new WP_Query([
                'post_type'      => 'st_order',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);
            foreach ($q->posts as $oid) {
                $scanned++;
                if (pp_normalize_order_meta($oid)) $repaired++;
            }
            echo '<div class="updated notice"><p>Structure repaired. Scanned: '.esc_html($scanned).', Updated: '.esc_html($repaired).'</p></div>';
        }

        // Commission recompute
        if (isset($_POST['pp_repair_commissions'])) {
            $fixed = 0; $scanned = 0;
            $q = new WP_Query([
                'post_type'      => 'st_order',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_pp_affiliate',
                'meta_value'     => 1,
            ]);
            foreach ($q->posts as $oid) {
                $scanned++;
                $partner_id = (int) get_post_meta($oid, '_pp_affiliate_user', true);
                if (!$partner_id) $partner_id = (int) get_post_meta($oid, 'id_user', true);
                $total = (float) get_post_meta($oid, 'total_order', true);
                if (!$total) $total = (float) get_post_meta($oid, 'total_price', true);

                $rate = 0; $ctype = 'percent';
                if (class_exists('PBP_Utils') && method_exists('PBP_Utils', 'get_partner_commission')) {
                    try {
                        $c = PBP_Utils::get_partner_commission($partner_id);
                        if (is_array($c)) {
                            $rate  = isset($c['rate']) ? floatval($c['rate']) : 0;
                            $ctype = strtolower(trim($c['type'] ?? 'percent'));
                            if ($ctype !== 'fixed') $ctype = 'percent';
                        }
                    } catch (Exception $e) {}
                }
                $amount = ($ctype === 'percent') ? round($total * ($rate / 100), 2) : (float) $rate;

                update_post_meta($oid, '_pp_commission_type',   $ctype);
                update_post_meta($oid, '_pp_commission_rate',   $rate);
                update_post_meta($oid, '_pp_commission_amount', $amount);
                update_post_meta($oid, 'commission',        ($ctype === 'percent') ? $rate : 0);
                update_post_meta($oid, 'commission_fixed',  ($ctype === 'fixed')   ? $rate : 0);
                update_post_meta($oid, 'commission_type',   $ctype);
                update_post_meta($oid, 'commission_rate',   $rate);
                update_post_meta($oid, 'commission_amount', $amount);
                $fixed++;
            }
            echo '<div class="updated notice"><p>Commission recomputed. Scanned: '.esc_html($scanned).', Updated: '.esc_html($fixed).'</p></div>';
        }

        echo '<form method="post" style="display:flex; gap:10px; align-items:center;">';
        submit_button('Repair Structure', 'secondary', 'pp_repair_run', false);
        submit_button('Recompute Commissions', 'primary', 'pp_repair_commissions', false);
        echo '</form></div>';
    });
});

/* =========================
   Admin bar "Dashboard" + login redirect for affiliates
   ========================= */
if (!function_exists('pp_is_affiliate_user')) {
  function pp_is_affiliate_user($uid) {
    $u = get_userdata($uid);
    if (!$u) return false;
    if (!in_array('partner', (array) $u->roles, true)) return false;
    $raw = function_exists('get_field') ? get_field('partner_type', 'user_' . $uid) : get_user_meta($uid, 'partner_type', true);
    $ptype = is_string($raw) ? strtolower(trim($raw)) : '';
    return in_array($ptype, ['affiliate', 'affilliate'], true);
  }
}

if (!function_exists('pp_aff_get_dashboard_url')) {
  function pp_aff_get_dashboard_url() {
    $url = '';
    if (function_exists('st')) {
      $pid = (int) st()->get_option('page_user_dashboard');
      if ($pid) $url = get_permalink($pid);
    }
    if (!$url) {
      $guess = get_page_by_path('page-user-setting');
      $url = $guess ? get_permalink($guess->ID) : home_url('/');
    }
    return add_query_arg('sc', 'pbp_dashboard', $url);
  }
}

// Show affiliate Dashboard link in admin bar (frontend only), without removing Edit
add_action('admin_bar_menu', function ($wp_admin_bar) {
  if (!is_user_logged_in()) return;
  if (is_admin()) return; // don't change wp-admin screens
  $uid = get_current_user_id();

  // Only for affiliate users
  if (!pp_is_affiliate_user($uid)) return;

  $wp_admin_bar->add_node([
    'id'    => 'pp_aff_dashboard',
    'title' => __('Dashboard', 'traveler'),
    'href'  => pp_aff_get_dashboard_url(),
    'meta'  => ['class' => 'ab-item ab-icon']
  ]);
}, 999);

add_action('wp_head', 'pp_aff_adminbar_css');
function pp_aff_adminbar_css() {
  if (!is_user_logged_in() || is_admin()) return;
  $uid = get_current_user_id();
  if (!pp_is_affiliate_user($uid)) return;
  echo '<style>#wpadminbar #wp-admin-bar-pp_aff_dashboard > .ab-item:before { content:"\f226"; top:2px; }</style>';
}

add_filter('login_redirect', function ($redirect_to, $requested, $user) {
  if (is_wp_error($user) || !$user || empty($user->ID)) return $redirect_to;
  if (!pp_is_affiliate_user($user->ID)) return $redirect_to;
  if (strpos($redirect_to, '/wp-admin/') !== false) return $redirect_to;
  return pp_aff_get_dashboard_url();
}, 20, 3);

/* =========================
   Helper for templates to read commission cleanly
   ========================= */
if (!function_exists('pp_get_order_commission_info')) {
    function pp_get_order_commission_info($order_id) {
        $type   = get_post_meta($order_id, '_pp_commission_type', true);
        if ($type === '') $type = get_post_meta($order_id, 'commission_type', true);
        $type = $type ? strtolower(trim($type)) : 'percent';
        if ($type !== 'fixed') $type = 'percent';

        $rate   = get_post_meta($order_id, '_pp_commission_rate', true);
        if ($rate === '' || $rate === null) {
            $rate = get_post_meta($order_id, 'commission_rate', true);
            if ($rate === '' || $rate === null) $rate = get_post_meta($order_id, 'commission', true);
        }
        $rate = floatval($rate);

        $amount = get_post_meta($order_id, '_pp_commission_amount', true);
        if ($amount === '' || $amount === null) {
            $amount = get_post_meta($order_id, 'commission_amount', true);
        }
        $amount = ($amount === '' || $amount === null) ? 0 : floatval($amount);

        if ($amount <= 0) {
            $total = (float) get_post_meta($order_id, 'total_order', true);
            if (!$total) $total = (float) get_post_meta($order_id, 'total_price', true);
            $amount = ($type === 'fixed') ? $rate : round($total * ($rate / 100), 2);
        }

        return ['type' => $type, 'rate' => $rate, 'amount' => $amount];
    }
}

// End of file