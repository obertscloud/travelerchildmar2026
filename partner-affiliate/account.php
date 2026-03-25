<?php
// File: wp-content/themes/traveler-childtheme/partner-affiliate/account.php
if (!defined('ABSPATH')) exit;

$uid   = get_current_user_id();
if (!$uid) {
    echo '<p>' . esc_html__('You must be logged in to view this page.', 'partner-portal') . '</p>';
    return;
}

$user  = get_userdata($uid);
$roles = (array) $user->roles;

// Access gate: role=partner + ACF user meta partner_type = affiliate (also accept legacy misspelling)
$ptype_raw = function_exists('get_field') ? get_field('partner_type', 'user_' . $uid) : get_user_meta($uid, 'partner_type', true);
$ptype     = is_string($ptype_raw) ? strtolower(trim($ptype_raw)) : '';
$is_aff    = in_array('partner', $roles, true) && in_array($ptype, ['affiliate','affilliate'], true);

if (!$is_aff && !current_user_can('manage_options')) {
    echo '<p class="st-alert" style="padding:10px;background:#f8d7da;color:#842029;border:1px solid #f5c2c7;border-radius:4px;">' .
         esc_html__('You must be a verified partner affiliate to view this page.', 'partner-portal') . '</p>';
    return;
}

// Load commission + allowed posts (defensive)
$commission = [];
$allowed_tours = $allowed_activities = [];
if (class_exists('PBP_Utils')) {
    try { $commission = PBP_Utils::get_partner_commission($uid) ?: []; } catch (Throwable $e) {}
    try { $allowed_tours = (array) PBP_Utils::get_partner_allowed_posts($uid, 'st_tours'); } catch (Throwable $e) {}
    try { $allowed_activities = (array) PBP_Utils::get_partner_allowed_posts($uid, 'st_activity'); } catch (Throwable $e) {}
}

// Handle save (no email change)
$saved = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pp_account_update'])) {
    if (!isset($_POST['pp_account_nonce']) || !wp_verify_nonce($_POST['pp_account_nonce'], 'pp_account_update')) {
        $err = __('Security check failed.', 'partner-portal');
    } else {
        $first = isset($_POST['first_name'])   ? sanitize_text_field(wp_unslash($_POST['first_name']))   : '';
        $last  = isset($_POST['last_name'])    ? sanitize_text_field(wp_unslash($_POST['last_name']))    : '';
        $disp  = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';

        // If display name blank, default to "First Last" or username
        if ($disp === '') {
            $disp = trim($first . ' ' . $last);
            if ($disp === '') $disp = $user->user_login;
        }

        $phone    = isset($_POST['st_phone'])     ? sanitize_text_field(wp_unslash($_POST['st_phone']))     : '';
        $addr1    = isset($_POST['st_address'])   ? sanitize_text_field(wp_unslash($_POST['st_address']))   : '';
        $addr2    = isset($_POST['st_address2'])  ? sanitize_text_field(wp_unslash($_POST['st_address2']))  : '';
        $city     = isset($_POST['st_city'])      ? sanitize_text_field(wp_unslash($_POST['st_city']))      : '';
        $province = isset($_POST['st_province'])  ? sanitize_text_field(wp_unslash($_POST['st_province']))  : '';
        $zip      = isset($_POST['st_zip_code'])  ? sanitize_text_field(wp_unslash($_POST['st_zip_code']))  : '';
        $country  = isset($_POST['st_country'])   ? sanitize_text_field(wp_unslash($_POST['st_country']))   : '';

        // Update WP user display and names (email not changed)
        $upd = [
            'ID'           => $uid,
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => $disp,
        ];
        $res = wp_update_user($upd);
        if (is_wp_error($res)) {
            $err = $res->get_error_message();
        } else {
            // Contact metas (Traveler conventions)
            update_user_meta($uid, 'st_phone',    $phone);
            update_user_meta($uid, 'st_address',  $addr1);
            update_user_meta($uid, 'st_address2', $addr2);
            update_user_meta($uid, 'st_city',     $city);
            update_user_meta($uid, 'st_province', $province);
            update_user_meta($uid, 'st_zip_code', $zip);
            update_user_meta($uid, 'st_country',  $country);
            $saved = true;

            // Refresh $user for display
            $user = get_userdata($uid);
        }
    }
}

// Helpers to render allowed posts
function pp_acc_list_titles(array $ids) {
    if (!$ids) return '<em>' . esc_html__('None', 'partner-portal') . '</em>';
    $links = [];
    foreach ($ids as $id) {
        $p = get_post((int) $id);
        if ($p && $p->post_status === 'publish') {
            $links[] = '<a href="'.esc_url(get_permalink($p)).'" target="_blank">'.esc_html(get_the_title($p)).'</a>';
        }
    }
    return $links ? implode(', ', $links) : '<em>' . esc_html__('None', 'partner-portal') . '</em>';
}
?>
<div class="account-settings">
  <h2 class="page-title"><?php echo esc_html__('Account Settings', 'partner-portal'); ?></h2>

  <?php if ($saved): ?>
    <div class="notice notice-success" style="margin:10px 0;padding:10px;border-left:4px solid #46b450;background:#f6fff6;">
      <?php echo esc_html__('Your profile has been updated.', 'partner-portal'); ?>
    </div>
  <?php elseif ($err): ?>
    <div class="notice notice-error" style="margin:10px 0;padding:10px;border-left:4px solid #dc3232;background:#fff5f5;">
      <?php echo esc_html($err); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="pp-account-form">
    <?php wp_nonce_field('pp_account_update', 'pp_account_nonce'); ?>
    <table class="form-table">
      <tr>
        <th><label><?php esc_html_e('Username', 'partner-portal'); ?></label></th>
        <td><?php echo esc_html($user->user_login); ?></td>
      </tr>
      <tr>
        <th><label><?php esc_html_e('Email', 'partner-portal'); ?></label></th>
        <td><input type="email" value="<?php echo esc_attr($user->user_email); ?>" disabled class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="first_name"><?php esc_html_e('First name', 'partner-portal'); ?></label></th>
        <td><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr(get_user_meta($uid, 'first_name', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="last_name"><?php esc_html_e('Last name', 'partner-portal'); ?></label></th>
        <td><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr(get_user_meta($uid, 'last_name', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="display_name"><?php esc_html_e('Display name', 'partner-portal'); ?></label></th>
        <td><input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" class="regular-text" /></td>
      </tr>

      <tr>
        <th><label for="st_phone"><?php esc_html_e('Phone', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_phone" name="st_phone" value="<?php echo esc_attr(get_user_meta($uid, 'st_phone', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_address"><?php esc_html_e('Address line 1', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_address" name="st_address" value="<?php echo esc_attr(get_user_meta($uid, 'st_address', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_address2"><?php esc_html_e('Address line 2', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_address2" name="st_address2" value="<?php echo esc_attr(get_user_meta($uid, 'st_address2', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_city"><?php esc_html_e('City', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_city" name="st_city" value="<?php echo esc_attr(get_user_meta($uid, 'st_city', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_province"><?php esc_html_e('State/Province/Region', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_province" name="st_province" value="<?php echo esc_attr(get_user_meta($uid, 'st_province', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_zip_code"><?php esc_html_e('ZIP/Postal code', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_zip_code" name="st_zip_code" value="<?php echo esc_attr(get_user_meta($uid, 'st_zip_code', true)); ?>" class="regular-text" /></td>
      </tr>
      <tr>
        <th><label for="st_country"><?php esc_html_e('Country', 'partner-portal'); ?></label></th>
        <td><input type="text" id="st_country" name="st_country" value="<?php echo esc_attr(get_user_meta($uid, 'st_country', true)); ?>" class="regular-text" /></td>
      </tr>

      <tr>
        <th></th>
        <td><button type="submit" name="pp_account_update" class="button button-primary"><?php esc_html_e('Save changes', 'partner-portal'); ?></button></td>
      </tr>
    </table>
  </form>

  <hr style="margin:24px 0;">

  <h3><?php esc_html_e('Commission', 'partner-portal'); ?></h3>
  <table class="form-table">
    <tr>
      <th><?php esc_html_e('Type', 'partner-portal'); ?></th>
      <td><?php echo esc_html(isset($commission['type']) ? $commission['type'] : '-'); ?></td>
    </tr>
    <tr>
      <th><?php esc_html_e('Rate (%)', 'partner-portal'); ?></th>
      <td><?php echo esc_html(isset($commission['rate']) ? $commission['rate'] : '-'); ?></td>
    </tr>
    <tr>
      <th><?php esc_html_e('Payout Schedule', 'partner-portal'); ?></th>
      <td><?php echo esc_html(isset($commission['schedule']) ? $commission['schedule'] : '-'); ?></td>
    </tr>
  </table>

  <h3><?php esc_html_e('Allowed Tours', 'partner-portal'); ?></h3>
  <p><?php echo wp_kses_post(pp_acc_list_titles($allowed_tours)); ?></p>

  <h3><?php esc_html_e('Allowed Activities', 'partner-portal'); ?></h3>
  <p><?php echo wp_kses_post(pp_acc_list_titles($allowed_activities)); ?></p>
</div>