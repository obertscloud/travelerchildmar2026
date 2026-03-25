<?php
// File: wp-content/themes/traveler-childtheme/partner-affiliate/commissions.php
if (!defined('ABSPATH')) exit;

$uid   = get_current_user_id();
$user  = wp_get_current_user();
$roles = (array) $user->roles;

// Access gate: only partners with partner_type=affiliate (allow admins for testing)
$ptype_raw = function_exists('get_field') ? get_field('partner_type', 'user_' . $uid) : '';
$ptype     = is_string($ptype_raw) ? strtolower(trim($ptype_raw)) : '';
$is_aff    = in_array('partner', $roles, true) && in_array($ptype, ['affiliate','affilliate'], true);
if (!$is_aff && !current_user_can('manage_options')) {
    echo '<div class="affiliate-commissions"><p>' . esc_html__('You do not have access to this page.', 'partner-portal') . '</p></div>';
    return;
}

// Helpers
function ppc_money($v) {
    $n = is_numeric($v) ? (float) $v : 0.0;
    return number_format_i18n($n, 2);
}
function ppc_date_disp($check_in) {
    if (!$check_in) return '';
    $dt = DateTime::createFromFormat('d/m/Y', (string) $check_in);
    if ($dt) return $dt->format('Y-m-d');
    $ts = strtotime((string) $check_in);
    return $ts ? date('Y-m-d', $ts) : (string) $check_in;
}
function ppc_commission_for_order($order_id, $uid) {
    // Prefer snapshot saved at booking time
    $rate  = get_post_meta($order_id, '_pp_commission_rate', true);
    $type  = get_post_meta($order_id, '_pp_commission_type', true);
    $amt   = get_post_meta($order_id, '_pp_commission_amount', true);

    $total = get_post_meta($order_id, 'total_price', true);
    if ($total === '' || $total === null) $total = get_post_meta($order_id, 'total_order', true);
    $total = (float) $total;

    if ($amt !== '' && $amt !== null) {
        return [
            'rate'  => $rate === '' ? null : (float) $rate,
            'type'  => $type ?: 'percent',
            'total' => $total,
            'amount'=> (float) $amt,
        ];
    }

    // Fallback: compute from current partner commission settings
    $fallback_rate = 0; $fallback_type = 'percent';
    if (class_exists('PBP_Utils')) {
        try {
            $c = PBP_Utils::get_partner_commission($uid);
            if (is_array($c)) {
                $fallback_rate = isset($c['rate']) ? (float) $c['rate'] : 0;
                $fallback_type = isset($c['type']) ? $c['type'] : 'percent';
            }
        } catch (Throwable $e) {}
    }
    $amount = $fallback_type === 'percent' ? round($total * ($fallback_rate / 100), 2) : (float) $fallback_rate;
    return [
        'rate'  => $fallback_rate,
        'type'  => $fallback_type,
        'total' => $total,
        'amount'=> $amount,
    ];
}

// Pagination
$paged     = max(1, absint(isset($_GET['ppg']) ? $_GET['ppg'] : 1));
$per_page  = 20;

// Orders for this affiliate
$q = new WP_Query([
    'post_type'      => 'st_order',
    'post_status'    => 'any',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'     => '_pp_affiliate_user',
            'value'   => $uid,
            'compare' => '='
        ]
    ],
    'fields'         => 'ids',
]);

// Total commission (lifetime) — small number of orders expected, so sum is fine
$total_commission = 0.0;
$all_q = new WP_Query([
    'post_type'      => 'st_order',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => '_pp_affiliate_user',
            'value'   => $uid,
            'compare' => '='
        ]
    ],
    'fields'         => 'ids',
]);
if ($all_q->have_posts()) {
    foreach ($all_q->posts as $oid) {
        $snap = get_post_meta($oid, '_pp_commission_amount', true);
        if ($snap !== '' && $snap !== null) {
            $total_commission += (float) $snap;
        } else {
            $c = ppc_commission_for_order($oid, $uid);
            $total_commission += (float) $c['amount'];
        }
    }
}
wp_reset_postdata();
?>
<div class="affiliate-commissions">
  <h2 class="page-title"><?php echo esc_html__('My Commissions', 'partner-portal'); ?></h2>

  <p>
    <strong><?php esc_html_e('Total Commission Earned:', 'partner-portal'); ?></strong>
    € <?php echo esc_html(ppc_money($total_commission)); ?>
  </p>

  <?php if (!$q->have_posts()): ?>
    <p><?php esc_html_e('No commission entries found.', 'partner-portal'); ?></p>
  <?php else: ?>
    <table class="pp-bookings-table" style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;">#</th>
          <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Item', 'partner-portal'); ?></th>
          <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Departure', 'partner-portal'); ?></th>
          <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Total', 'partner-portal'); ?></th>
          <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Rate', 'partner-portal'); ?></th>
          <th style="text-align:right;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Commission', 'partner-portal'); ?></th>
          <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Status', 'partner-portal'); ?></th>
          <th style="text-align:left;padding:8px;border-bottom:1px solid #ddd;"><?php esc_html_e('Source', 'partner-portal'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($q->posts as $order_id):
            $item_id    = (int) get_post_meta($order_id, 'st_booking_id', true);
            $item_title = $item_id ? get_the_title($item_id) : '';
            $item_link  = $item_id ? get_permalink($item_id) : '';
            if ($item_title === '') $item_title = get_the_title($order_id);

            $check_in   = (string) get_post_meta($order_id, 'check_in', true);
            $starttime  = (string) get_post_meta($order_id, 'starttime', true);
            $dep_disp   = trim(ppc_date_disp($check_in) . ($starttime ? (' ' . $starttime) : ''));

            $fee   = get_post_meta($order_id, 'booking_fee_price', true);
            if ($fee === '' || $fee === null) $fee = 0;

            $snap = ppc_commission_for_order($order_id, $uid);
            $total = (float) $snap['total'];
            $rate  = $snap['rate'];
            $type  = $snap['type'];
            $amount= (float) $snap['amount'];

            $status = (string) get_post_meta($order_id, 'status', true);
            if ($status === '') $status = 'pending';
            $source = (string) get_post_meta($order_id, '_pp_affiliate_source', true);

            $rate_disp = ($type === 'percent') ? (ppc_money($rate) . '%') : ('€ ' . ppc_money($rate));
        ?>
          <tr>
            <td style="padding:8px;border-bottom:1px solid #f0f0f0;">#<?php echo esc_html($order_id); ?></td>
            <td style="padding:8px;border-bottom:1px solid #f0f0f0;">
              <?php if ($item_link): ?>
                <a href="<?php echo esc_url($item_link); ?>" target="_blank"><?php echo esc_html($item_title); ?></a>
              <?php else: ?>
                <?php echo esc_html($item_title ?: '—'); ?>
              <?php endif; ?>
              <?php if ((float)$fee > 0): ?>
                <div style="color:#666;font-size:12px;"><?php echo esc_html(sprintf(__('Fee: € %s', 'partner-portal'), ppc_money($fee))); ?></div>
              <?php endif; ?>
            </td>
            <td style="padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html($dep_disp ?: '—'); ?></td>
            <td style="text-align:right;padding:8px;border-bottom:1px solid #f0f0f0;">€ <?php echo esc_html(ppc_money($total)); ?></td>
            <td style="text-align:right;padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html($rate_disp); ?></td>
            <td style="text-align:right;padding:8px;border-bottom:1px solid #f0f0f0;">
              <strong>€ <?php echo esc_html(ppc_money($amount)); ?></strong>
            </td>
            <td style="padding:8px;border-bottom:1px solid #f0f0f0;">
              <span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#eee;"><?php echo esc_html(ucfirst($status)); ?></span>
            </td>
            <td style="padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html($source ?: '—'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php
      $total_pages = max(1, (int) $q->max_num_pages);
      if ($total_pages > 1) {
        $base_url = remove_query_arg('ppg');
        echo '<div class="pp-pager" style="margin-top:12px;">';
        for ($i = 1; $i <= $total_pages; $i++) {
          $url = esc_url(add_query_arg('ppg', $i, $base_url));
          $style = $i === $paged ? 'background:#0073aa;color:#fff;border-color:#0073aa;' : '';
          echo '<a href="'.$url.'" style="display:inline-block;margin-right:6px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;'.$style.'">'.$i.'</a>';
        }
        echo '</div>';
      }
      wp_reset_postdata();
    ?>
  <?php endif; ?>
</div>
<style>
/* ===== Affiliate Bookings – responsive styles ===== */
.affiliate-bookings {
  /* allow horizontal scroll if content still overflows */
  overflow-x: auto;
}

.affiliate-bookings .page-title {
  margin: 8px 0 12px;
}

/* Table polish */
.affiliate-bookings .pp-bookings-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.affiliate-bookings .pp-bookings-table thead th {
  background: #fafafa;
  color: #111;
}

.affiliate-bookings .pp-bookings-table tbody tr:hover {
  background: #f9fafb;
}

/* Item link: clamp long titles cleanly */
.affiliate-bookings .pp-bookings-table td:nth-child(2) a {
  color: #1d4ed8;
  text-decoration: none;
  display: inline-block;
  max-width: 60ch;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.affiliate-bookings .pp-bookings-table td:nth-child(2) a:hover {
  text-decoration: underline;
}

/* Status pill (override inline bg) */
.affiliate-bookings .pp-bookings-table td:nth-child(6) span {
  display: inline-block;
  padding: 3px 10px !important;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 600;
  background: #eef2ff !important; /* neutral indigo tint */
  color: #1e40af !important;
}

/* Pagination: tidy up */
.affiliate-bookings .pp-pager {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px !important;
}
.affiliate-bookings .pp-pager a {
  display: inline-block;
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  color: #111827;
  text-decoration: none;
}
.affiliate-bookings .pp-pager a:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
}

/* Tablet: hide Source (7) and Tx ID (8) */
@media (max-width: 1024px) {
  .affiliate-bookings .pp-bookings-table th:nth-child(7),
  .affiliate-bookings .pp-bookings-table td:nth-child(7),
  .affiliate-bookings .pp-bookings-table th:nth-child(8),
  .affiliate-bookings .pp-bookings-table td:nth-child(8) {
    display: none;
  }
}

/* Small tablet / large phone: also hide Fee (4) */
@media (max-width: 768px) {
  .affiliate-bookings .pp-bookings-table th:nth-child(4),
  .affiliate-bookings .pp-bookings-table td:nth-child(4) {
    display: none;
  }
}

/* Phones: also hide # (1); allow item titles to wrap */
@media (max-width: 640px) {
  .affiliate-bookings .pp-bookings-table th:nth-child(1),
  .affiliate-bookings .pp-bookings-table td:nth-child(1) {
    display: none;
  }

  .affiliate-bookings .pp-bookings-table td:nth-child(2) a {
    white-space: normal;      /* allow wrapping on small screens */
    word-break: break-word;
    max-width: 100%;
  }

  /* Slightly tighter table padding */
  .affiliate-bookings .pp-bookings-table thead th,
  .affiliate-bookings .pp-bookings-table tbody td {
    padding: 10px 12px !important;
  }
}</style>