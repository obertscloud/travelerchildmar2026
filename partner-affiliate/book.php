<?php
// File: wp-content/themes/traveler-childtheme/partner-affiliate/book.php
// Updated: remove Create button, blue Book button, 6-col spacing, centered white confirmation modal

if (!defined('ABSPATH')) exit;

// Access gate
$user         = wp_get_current_user();
$roles        = (array) $user->roles;
$ptype_raw    = function_exists('get_field') ? get_field('partner_type', 'user_' . $user->ID) : '';
$partner_type = is_string($ptype_raw) ? strtolower(trim($ptype_raw)) : '';
$is_affiliate = in_array('partner', $roles, true) && in_array($partner_type, ['affiliate', 'affilliate'], true);
if (!$is_affiliate && !current_user_can('manage_options')) {
    echo '<div class="wrap"><h1>' . esc_html__('Access Denied', 'partner-portal') . '</h1><p>' . esc_html__('You must be an affiliate partner to access the booking page.', 'partner-portal') . '</p></div>';
    return;
}

// Ensure PBP_Utils if available
if (!class_exists('PBP_Utils')) {
    if (defined('PBP_PLUGIN_PATH') && file_exists(PBP_PLUGIN_PATH . 'includes/utils.php')) {
        require_once PBP_PLUGIN_PATH . 'includes/utils.php';
    } elseif (file_exists(get_stylesheet_directory() . '/partner-affiliate/class-pbp-utils.php')) {
        require_once get_stylesheet_directory() . '/partner-affiliate/class-pbp-utils.php';
    }
}

// Normalize IDs
if (!function_exists('ppa_norm_ids')) {
    function ppa_norm_ids($val) {
        if (is_array($val)) $arr = $val;
        elseif (is_string($val) && $val !== '') {
            $dec = json_decode($val, true);
            $arr = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : preg_split('/[,\s]+/', $val);
        } else $arr = [];
        $arr = array_filter($arr, function($v){ return $v !== '' && $v !== null; });
        $arr = array_map('intval', $arr);
        return array_values(array_unique($arr));
    }
}

// Allowed posts
$tours = $activities = [];
if (class_exists('PBP_Utils')) {
    try { $tours = ppa_norm_ids(PBP_Utils::get_partner_allowed_posts($user->ID, 'st_tours')); } catch (Throwable $e) {}
    try { $activities = ppa_norm_ids(PBP_Utils::get_partner_allowed_posts($user->ID, 'st_activity')); } catch (Throwable $e) {}
}

wp_enqueue_script('jquery');
$pp_create_nonce = wp_create_nonce('pp_create_booking_nonce');
?>
<div class="wrap">
    <h1><?php echo esc_html__('Book Tour or Activity', 'partner-portal'); ?></h1>
    <p><?php echo esc_html__('Select a tour or activity below to create a booking.', 'partner-portal'); ?></p>

    <div id="pp-debug" style="display:none;"></div>

    <ul class="pp-tabs">
        <li class="active" data-tab="tours-tab"><?php esc_html_e('Tours', 'partner-portal'); ?></li>
        <li data-tab="activities-tab"><?php esc_html_e('Activities', 'partner-portal'); ?></li>
    </ul>

    <div id="tours-tab" class="pp-tab-content active">
        <?php if (!empty($tours)) : ?>
        <table class="wp-list-table widefat striped ppb-table">
            <colgroup>
                <col class="ppb-col-id">
                <col class="ppb-col-title">
                <col class="ppb-col-desc">
                <col class="ppb-col-dur">
                <col class="ppb-col-price">
                <col class="ppb-col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th><?php _e('Tour ID', 'partner-portal'); ?></th>
                    <th><?php _e('Title', 'partner-portal'); ?></th>
                    <th><?php _e('Description', 'partner-portal'); ?></th>
                    <th><?php _e('Duration', 'partner-portal'); ?></th>
                    <th><?php _e('Price', 'partner-portal'); ?></th>
                    <th><?php _e('Actions', 'partner-portal'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tours as $tour_id) :
                $p = get_post($tour_id);
                if (!$p || $p->post_status !== 'publish') continue;
                $desc       = wp_strip_all_tags(wp_trim_words($p->post_content, 50, '...'));
                $duration   = get_post_meta($tour_id, 'duration', true);
                if ($duration === '') $duration = get_post_meta($tour_id, 'duration_day', true);
                $price      = get_post_meta($tour_id, 'min_price', true);
                $widget     = get_post_meta($tour_id, 'ticketinghub_widget_id', true);
                if ($widget === '') $widget = get_post_meta($tour_id, 'ticketinghub_widget', true);
                $title_attr = get_the_title($tour_id);
            ?>
                <tr>
                    <td><?php echo esc_html($tour_id); ?></td>
                    <td><?php echo esc_html($title_attr); ?></td>
                    <td><?php echo esc_html($desc); ?></td>
                    <td><?php echo esc_html($duration); ?></td>
                    <td class="ppb-tx-right"><?php echo esc_html($price); ?> €</td>
                    <td class="ppb-tx-right">
                        <button type="button" class="button pp-launch-modal"
                                data-id="<?php echo esc_attr($tour_id); ?>"
                                data-title="<?php echo esc_attr($title_attr); ?>"
                                data-widget="<?php echo esc_attr($widget); ?>"
                                data-base-price="<?php echo esc_attr($price); ?>"
                                data-price-type="per_person">
                            <?php _e('Book', 'partner-portal'); ?>
                        </button>
                        <!-- Create button removed -->
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php esc_html_e('No allowed tours found for your account.', 'partner-portal'); ?></p>
        <?php endif; ?>
    </div>

    <div id="activities-tab" class="pp-tab-content">
        <?php if (!empty($activities)) : ?>
        <table class="wp-list-table widefat striped ppb-table">
            <colgroup>
                <col class="ppb-col-id">
                <col class="ppb-col-title">
                <col class="ppb-col-desc">
                <col class="ppb-col-dur">
                <col class="ppb-col-price">
                <col class="ppb-col-actions">
            </colgroup>
            <thead>
                <tr>
                    <th><?php _e('Activity ID', 'partner-portal'); ?></th>
                    <th><?php _e('Title', 'partner-portal'); ?></th>
                    <th><?php _e('Description', 'partner-portal'); ?></th>
                    <th><?php _e('Duration', 'partner-portal'); ?></th>
                    <th><?php _e('Price', 'partner-portal'); ?></th>
                    <th><?php _e('Actions', 'partner-portal'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $act_id) :
                $p = get_post($act_id);
                if (!$p || $p->post_status !== 'publish') continue;
                $desc       = wp_strip_all_tags(wp_trim_words($p->post_content, 50, '...'));
                $duration   = get_post_meta($act_id, 'duration', true);
                $price_type = get_post_meta($act_id, 'price_type', true);
                $price      = ($price_type === 'price_by_fixed') ? get_post_meta($act_id, 'fixed_price', true) : get_post_meta($act_id, 'adult_price', true);
                $widget     = get_post_meta($act_id, 'ticketinghub_widget_id', true);
                if ($widget === '') $widget = get_post_meta($act_id, 'ticketinghub_widget', true);
                $title_attr = get_the_title($act_id);
                $pt_label   = ($price_type === 'price_by_fixed') ? 'total' : 'per_person';
            ?>
                <tr>
                    <td><?php echo esc_html($act_id); ?></td>
                    <td><?php echo esc_html($title_attr); ?></td>
                    <td><?php echo esc_html($desc); ?></td>
                    <td><?php echo esc_html($duration); ?></td>
                    <td class="ppb-tx-right">
                        <?php
                        if ($price_type === 'price_by_fixed') {
                            echo esc_html($price) . ' € ' . esc_html__('total', 'partner-portal');
                        } else {
                            echo esc_html($price) . ' € / ' . esc_html__('person', 'partner-portal');
                        }
                        ?>
                    </td>
                    <td class="ppb-tx-right">
                        <button type="button" class="button pp-launch-modal"
                                data-id="<?php echo esc_attr($act_id); ?>"
                                data-title="<?php echo esc_attr($title_attr); ?>"
                                data-widget="<?php echo esc_attr($widget); ?>"
                                data-base-price="<?php echo esc_attr($price); ?>"
                                data-price-type="<?php echo esc_attr($pt_label); ?>">
                            <?php _e('Book', 'partner-portal'); ?>
                        </button>
                        <!-- Create button removed -->
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p><?php esc_html_e('No allowed activities found for your account.', 'partner-portal'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Booking Modal -->
<div id="pp-booking-modal" style="display:none;">
    <button id="pp-booking-close-icon" type="button">×</button>
    <div class="pp-booking-modal-content"></div>
    <div id="pp-booking-controls">
        <button id="pp-booking-finish" type="button"><?php esc_html_e('Affiliate -> Process booking', 'partner-portal'); ?></button>
    </div>
</div>

<!-- Finalize overlay (collect customer + payment) -->
<div id="pp-finalize" style="display:none;">
  <div class="ppf-card">
    <div class="ppf-header">
      <strong><?php esc_html_e('Finalize Booking', 'partner-portal'); ?></strong>
      <button type="button" class="ppf-close" aria-label="<?php esc_attr_e('Close', 'partner-portal'); ?>">×</button>
    </div>
    <div class="ppf-body">
      <div class="ppf-row">
        <label><?php esc_html_e('Item', 'partner-portal'); ?></label>
        <div id="ppf-item" class="ppf-val">—</div>
      </div>
      <div class="ppf-grid">
        <div>
          <label><?php esc_html_e('Customer first name', 'partner-portal'); ?> *</label>
          <input type="text" id="ppf-first-name" placeholder="Jane">
        </div>
        <div>
          <label><?php esc_html_e('Customer last name', 'partner-portal'); ?> *</label>
          <input type="text" id="ppf-last-name" placeholder="Doe">
        </div>
        <div>
          <label><?php esc_html_e('Customer email', 'partner-portal'); ?></label>
          <input type="email" id="ppf-customer-email" placeholder="jane@example.com">
        </div>
        <div>
          <label><?php esc_html_e('Customer phone', 'partner-portal'); ?></label>
          <input type="text" id="ppf-customer-phone" placeholder="+49 170 1234567">
        </div>
        <div>
          <label><?php esc_html_e('Departure date (dd/mm/YYYY)', 'partner-portal'); ?></label>
          <input type="text" id="ppf-check-in" placeholder="<?php echo esc_attr(date('d/m/Y')); ?>">
        </div>
        <div>
          <label><?php esc_html_e('Start time (HH:MM)', 'partner-portal'); ?></label>
          <input type="text" id="ppf-start-time" placeholder="13:00">
        </div>
        <div>
          <label><?php esc_html_e('TicketingHub ref (optional)', 'partner-portal'); ?></label>
          <input type="text" id="ppf-th-ref" placeholder="TH-12345">
        </div>
        <div>
          <label><?php esc_html_e('Adults', 'partner-portal'); ?></label>
          <input type="number" min="1" step="1" id="ppf-adults" value="1">
        </div>
        <div>
          <label><?php esc_html_e('Children', 'partner-portal'); ?></label>
          <input type="number" min="0" step="1" id="ppf-children" value="0">
        </div>
        <div>
          <label><?php esc_html_e('Price per adult', 'partner-portal'); ?></label>
          <input type="number" min="0" step="0.01" id="ppf-price-adult" value="0">
        </div>
        <div>
          <label><?php esc_html_e('Pay amount (total)', 'partner-portal'); ?> *</label>
          <input type="number" min="0" step="0.01" id="ppf-pay-amount" value="0">
        </div>
      </div>
      <div class="ppf-note">
        <small><?php esc_html_e('Pay amount will be saved as the total order for this booking.', 'partner-portal'); ?></small>
      </div>
    </div>
    <div class="ppf-footer">
      <button type="button" class="ppf-confirm"><?php esc_html_e('Confirm & Create', 'partner-portal'); ?></button>
    </div>
  </div>
</div>

<!-- Confirmation Modal (centered white card) -->
<div id="pp-confirmation-modal" style="display:none;">
  <div class="ppc-card">
    <h2><?php esc_html_e('Booking Confirmation', 'partner-portal'); ?></h2>
    <p id="pp-confirmation-details"></p>
    <div class="ppc-actions">
      <button id="pp-confirmation-ok" type="button"><?php esc_html_e('OK', 'partner-portal'); ?></button>
    </div>
  </div>
</div>

<script>
var ajaxurl = "<?php echo esc_js(admin_url('admin-ajax.php')); ?>";
var ppCreateNonce = "<?php echo esc_js($pp_create_nonce); ?>";

jQuery(function($){
    var currentPostId = '', currentPostTitle = '', currentWidgetId = '';
    var currentBasePrice = 0, currentPriceType = 'per_person';
    var $debug = $('#pp-debug');

    function logDbg(msg) {
        if (!$debug.length) { console.log('[PBP]', msg); return; }
        if ($debug.is(':hidden')) {
            $debug.show().html('<span class="pp-close">×</span><div><strong>Debug</strong></div>');
        }
        $debug.append($('<div/>').text(String(msg)));
        console.log('[PBP]', msg);
    }

    $(document).on('click', '#pp-debug .pp-close', function(e){
        e.preventDefault(); e.stopPropagation();
        $debug.hide().empty();
    });

    // Tabs
    $('.pp-tabs li').on('click', function(e){
        e.preventDefault(); e.stopPropagation();
        var tab = $(this).data('tab');
        $('.pp-tabs li').removeClass('active');
        $(this).addClass('active');
        $('.pp-tab-content').removeClass('active');
        $('#' + tab).addClass('active');
    });

    // Launch modal
    $(document).on('click', '.pp-launch-modal', function(e){
        e.preventDefault(); e.stopPropagation();
        currentPostId    = String($(this).data('id'));
        currentPostTitle = String($(this).data('title'));
        currentWidgetId  = String($(this).data('widget') || '');
        currentBasePrice = parseFloat($(this).data('base-price') || '0') || 0;
        currentPriceType = String($(this).data('price-type') || 'per_person');

        var iframeURL = '<?php echo esc_js(home_url('/')); ?>?p=' + encodeURIComponent(currentPostId) + '&pp_preview=1';
        $('.pp-booking-modal-content').html('<iframe src="' + iframeURL + '" style="width:100%; height:720px; border:none;"></iframe>');
        $('#pp-booking-modal').fadeIn();

        $('#pp-booking-finish').text(currentWidgetId ? '<?php echo esc_js(__('Process booking', 'partner-portal')); ?>' : '<?php echo esc_js(__('Close', 'partner-portal')); ?>');
    });

    // Close booking modal + confirmation
    $(document).on('click', '#pp-booking-close-icon, #pp-confirmation-ok', function(e){
        e.preventDefault(); e.stopPropagation();
        $('#pp-booking-modal, #pp-confirmation-modal').fadeOut();
        $('.pp-booking-modal-content').html('');
        $('#pp-confirmation-details').html('');
    });

    // Click background closes modals
    $(document).on('click', '#pp-booking-modal, #pp-confirmation-modal', function(e){
        if (e.target === this) { $(this).fadeOut(); }
    });

    // ESC closes modals/debug/finalize
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') {
            $('#pp-booking-modal, #pp-confirmation-modal, #pp-finalize').fadeOut();
            $debug.hide().empty();
        }
    });

    function openFinalize(id, title, basePrice, priceType) {
        $('#ppf-item').text(title + ' (#' + id + ')');
        $('#ppf-first-name').val('');
        $('#ppf-last-name').val('');
        $('#ppf-customer-email').val('');
        $('#ppf-customer-phone').val('');
        $('#ppf-check-in').val('<?php echo esc_js(date('d/m/Y')); ?>');
        $('#ppf-start-time').val('13:00');
        $('#ppf-th-ref').val('');
        $('#ppf-adults').val(1);
        $('#ppf-children').val(0);
        $('#ppf-price-adult').val((basePrice || 0).toFixed(2));
        var pay = (priceType === 'per_person') ? (parseFloat(basePrice || 0) * 1) : parseFloat(basePrice || 0);
        $('#ppf-pay-amount').val(pay.toFixed(2));

        function recalc() {
            var pa = parseFloat($('#ppf-price-adult').val() || '0') || 0;
            var ad = Math.max(1, parseInt($('#ppf-adults').val() || '1', 10));
            if (priceType === 'per_person') {
                $('#ppf-pay-amount').val((pa * ad).toFixed(2));
            }
        }
        $('#ppf-adults, #ppf-price-adult').off('input').on('input', recalc);

        $('#pp-finalize').fadeIn()
            .data('post-id', id)
            .data('post-title', title);
    }

    // Finish from modal → TH path opens finalize (else just close)
    $(document).on('click', '#pp-booking-finish', function(e){
        e.preventDefault(); e.stopPropagation();
        if (currentWidgetId.trim() !== '') {
            $('#pp-booking-modal').fadeOut();
            $('.pp-booking-modal-content').html('');
            openFinalize(currentPostId, currentPostTitle, currentBasePrice, currentPriceType);
        } else {
            $('#pp-booking-modal').fadeOut();
        }
    });

    // Finalize actions
    $(document).on('click', '.ppf-close', function(e){
        e.preventDefault(); e.stopPropagation();
        $('#pp-finalize').fadeOut();
    });

    $(document).on('click', '.ppf-confirm', function(e){
        e.preventDefault(); e.stopPropagation();
        var $btn = $(this).prop('disabled', true);

        var id     = $('#pp-finalize').data('post-id');
        var fname  = $('#ppf-first-name').val().trim();
        var lname  = $('#ppf-last-name').val().trim();

        var payload = {
            action: 'pp_ajax_create_manual_booking_autofill',
            nonce:  ppCreateNonce,
            tour_id: id,
            customer_first_name: fname,
            customer_last_name:  lname,
            customer_name: (fname + ' ' + lname).trim(),
            customer_email: $('#ppf-customer-email').val().trim(),
            customer_phone: $('#ppf-customer-phone').val().trim(),
            check_in:       $('#ppf-check-in').val().trim(),
            start_time:     $('#ppf-start-time').val().trim(),
            th_ref:         $('#ppf-th-ref').val().trim(),
            adult_number:   $('#ppf-adults').val().trim(),
            child_number:   $('#ppf-children').val().trim(),
            total_override: $('#ppf-pay-amount').val().trim()
        };

        if (!fname || !lname) {
            alert('<?php echo esc_js(__('Customer first and last name are required.', 'partner-portal')); ?>');
            $btn.prop('disabled', false);
            return;
        }
        if (!payload.total_override || parseFloat(payload.total_override) <= 0) {
            alert('<?php echo esc_js(__('Pay amount must be greater than 0.', 'partner-portal')); ?>');
            $btn.prop('disabled', false);
            return;
        }

        $.post(ajaxurl, payload).done(function(resp){
            var html;
            if (resp && resp.success) {
                var d = resp.data || {};
                html = '<strong>' + (d.message || 'Success') + '</strong>';
                if (d.order_id)   html += '<br>Order ID: <code>' + d.order_id + '</code>';
                if (d.post_title) html += '<br>Post: <strong>' + d.post_title + '</strong>';
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Unknown error';
                logDbg('Finalize error: ' + msg);
                html = '<strong>⚠️ ' + msg + '</strong>';
            }
            $('#pp-finalize').fadeOut();
            $('#pp-confirmation-details').html(html);
            $('#pp-confirmation-modal').fadeIn();
        }).fail(function(xhr){
            var txt = (xhr.responseText || '').replace(/<[^>]+>/g,'').slice(0,800);
            var msg = 'HTTP ' + xhr.status + (txt ? (' — ' + txt) : '');
            logDbg('Finalize AJAX failed: ' + msg);
            $('#pp-finalize').fadeOut();
            $('#pp-confirmation-details').html('<strong>⚠️ ' + msg + '</strong>');
            $('#pp-confirmation-modal').fadeIn();
        }).always(function(){
            $btn.prop('disabled', false);
        });
    });
});
</script>

<style>
/* Book button: blue + white text */
.button.pp-launch-modal {
  background:#2563eb !important;
  border-color:#2563eb !important;
  color:#fff !important;
}
.button.pp-launch-modal:hover {
  background:#1d4ed8 !important;
  border-color:#1d4ed8 !important;
  color:#fff !important;
}
/* Hide any legacy Create button (just in case of cache) */
.pp-create-booking { display:none !important; }

/* Tabs */
.pp-tabs { list-style:none; padding:0; margin:15px 0; display:flex; gap:12px; }
.pp-tabs li { cursor:pointer; padding:8px 12px; border:1px solid #ddd; border-radius:4px; }
.pp-tabs li.active { background:#0073aa; color:#fff; border-color:#0073aa; }
.pp-tab-content { display:none; margin-top:10px; }
.pp-tab-content.active { display:block; }

/* Spaced 6-column table */
table.wp-list-table.ppb-table { table-layout: fixed; border-collapse: separate; border-spacing: 0; }
table.wp-list-table.ppb-table thead th { padding:12px 18px; border-bottom:2px solid #e5e7eb; background:#fafafa; }
table.wp-list-table.ppb-table tbody td { padding:12px 18px; vertical-align:top; border-bottom:1px solid #e5e7eb; }
/* Column widths via colgroup */
table.wp-list-table.ppb-table col.ppb-col-id      { width: 100px; }
table.wp-list-table.ppb-table col.ppb-col-title   { width: 22%;  }
table.wp-list-table.ppb-table col.ppb-col-desc    { width: 40%;  }
table.wp-list-table.ppb-table col.ppb-col-dur     { width: 12%;  }
table.wp-list-table.ppb-table col.ppb-col-price   { width: 10%;  }
table.wp-list-table.ppb-table col.ppb-col-actions { width: 12%;  }
/* Extra gutter between columns */
table.wp-list-table.ppb-table td:not(:first-child),
table.wp-list-table.ppb-table th:not(:first-child) { padding-left: 26px; }
/* Alignments */
.ppb-tx-right { text-align:right; white-space:nowrap; }
table.wp-list-table.ppb-table td:nth-child(3) { overflow:hidden; text-overflow:ellipsis; }

/* Booking modal (existing) */
#pp-booking-modal {
  position: fixed; inset:0; background: rgba(0,0,0,0.5);
  display:none; z-index: 100000; padding:40px 5%;
}
#pp-booking-modal .pp-booking-modal-content {
  background:#fff; padding:0; border-radius:6px; overflow:hidden;
}
#pp-booking-controls { margin-top:10px; text-align:right; }
#pp-booking-close-icon {
  position:absolute; top:16px; right:20px; background:#fff; border:1px solid #ccc;
  border-radius:50%; width:36px; height:36px; font-size:18px; line-height:28px; cursor:pointer;
}
#pp-booking-finish {
  background:#27ae60; border-color:#27ae60; color:#fff; padding:10px 16px; font-weight:600; border-radius:4px;
}
#pp-booking-finish:hover { background:#1e8e50; border-color:#1e8e50; }

/* Finalize overlay (existing) */
#pp-finalize {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 100002; display:none;
  padding: 30px 5%;
}
#pp-finalize .ppf-card { max-width: 820px; margin: 0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
.ppf-header { display:flex; align-items:center; justify-content:space-between; background:#f7f7f7; padding:12px 16px; border-bottom:1px solid #eee; }
.ppf-header .ppf-close { background:#fff; border:1px solid #ccc; border-radius:50%; width:32px; height:32px; font-size:18px; line-height:28px; cursor:pointer; }
.ppf-body { padding:16px; }
.ppf-row { margin-bottom:12px; }
.ppf-row label { display:block; font-weight:600; color:#333; margin-bottom:4px; }
.ppf-val { background:#fafafa; border:1px solid #eee; padding:8px 10px; border-radius:4px; }
.ppf-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; align-items:flex-start; }
.ppf-grid input { width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:4px; outline:none; }
.ppf-note { margin-top:6px; color:#666; }
.ppf-footer { display:flex; justify-content:flex-end; gap:12px; padding:12px 16px; border-top:1px solid #eee; background:#fafafa; }
.ppf-confirm { background:#27ae60; border-color:#27ae60; color:#fff; padding:10px 16px; font-weight:600; border-radius:4px; }
.ppf-confirm:hover { background:#1e8e50; border-color:#1e8e50; }

/* Centered white card confirmation modal */
#pp-confirmation-modal {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 100001;
  display: none;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
#pp-confirmation-modal .ppc-card {
  background: #fff; color:#111;
  max-width: 560px; width: 100%;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0,0,0,.25);
  padding: 18px 20px 16px;
}
#pp-confirmation-modal h2 { margin: 0 0 8px; font-weight: 700; }
#pp-confirmation-details {
  margin: 0 0 16px;
  white-space: pre-wrap; word-break: break-word;
}
#pp-confirmation-modal .ppc-actions { text-align: right; }
#pp-confirmation-ok {
  background:#2563eb; border:1px solid #2563eb; color:#fff;
  padding:8px 14px; border-radius:4px; font-weight:600; cursor:pointer;
}
#pp-confirmation-ok:hover { background:#1d4ed8; border-color:#1d4ed8; }

/* Debug panel */
#pp-debug {
  position: fixed; top: 70px; right: 20px; max-width: 520px; max-height: 50vh; overflow: auto;
  background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; border-radius: 4px; padding: 10px; z-index: 100003;
}
#pp-debug .pp-close { float: right; cursor: pointer; font-weight: bold; margin-left: 10px; }

/* ===== Responsive patch (add below your existing <style>) ===== */

/* Prevent background scroll when a modal is open (JS adds/removes this class) */
body.pp-no-scroll { overflow: hidden; }

:root { --pp-pad: clamp(12px, 2.5vw, 24px); }

/* Booking modal: make overlay scrollable and iframe responsive */
#pp-booking-modal {
  padding: var(--pp-pad);
  height: 100vh;            /* fallback */
  height: 100dvh;           /* modern mobile browsers */
  overflow: auto;
  -webkit-overflow-scrolling: touch;
}

/* Respect safe-area insets (iOS) */
@supports (padding: max(0px)) {
  #pp-booking-modal {
    padding-top: max(var(--pp-pad), env(safe-area-inset-top));
    padding-right: max(var(--pp-pad), env(safe-area-inset-right));
    padding-bottom: max(var(--pp-pad), env(safe-area-inset-bottom));
    padding-left: max(var(--pp-pad), env(safe-area-inset-left));
  }
}

#pp-booking-modal .pp-booking-modal-content {
  max-width: 1100px;
  margin: 0 auto;
  border-radius: 10px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.25);
  overflow: hidden; /* keeps iframe corners rounded */
}

/* Override the inline iframe height for small screens */
#pp-booking-modal .pp-booking-modal-content iframe {
  display: block;
  width: 100%;
  height: 75vh !important;    /* fallback */
  height: 75dvh !important;   /* modern mobile browsers */
  max-height: 720px !important; /* don’t exceed desktop-intended size */
}

/* Keep the bottom controls reachable; sticky within the overlay scroll */
#pp-booking-controls {
  position: sticky;
  bottom: 0;
  z-index: 1;
  max-width: 1100px;
  margin: 10px auto 0;
  text-align: right;
  padding: 10px 12px 12px;
  background: linear-gradient(to top, rgba(255,255,255,1), rgba(255,255,255,0.92));
  backdrop-filter: saturate(120%) blur(2px);
  border-top: 1px solid rgba(0,0,0,0.06);
  border-radius: 0 0 8px 8px;
}

/* Make the close icon play nice with notches */
#pp-booking-close-icon {
  top: max(10px, env(safe-area-inset-top));
  right: max(10px, env(safe-area-inset-right));
}

/* Finalize modal: constrain height, make body scrollable */
#pp-finalize { padding: var(--pp-pad); }
#pp-finalize .ppf-card {
  width: min(100%, 900px);
  margin: 0 auto;
  max-height: calc(100dvh - 2 * var(--pp-pad));
  display: grid;
  grid-template-rows: auto 1fr auto; /* header | body | footer */
}
#pp-finalize .ppf-body { overflow: auto; -webkit-overflow-scrolling: touch; }
#pp-finalize .ppf-footer { background: #fafafa; }

/* Confirmation modal: allow overflow on very small screens */
#pp-confirmation-modal .ppc-card {
  max-height: 90dvh;
  overflow: auto;
}

/* Tables: allow horizontal scroll and hide less-critical columns on small screens */
.pp-tab-content { overflow-x: auto; }

/* Medium screens: hide Description to reduce width */
@media (max-width: 1024px) {
  table.wp-list-table.ppb-table col.ppb-col-desc { display: none; }
  table.wp-list-table.ppb-table th:nth-child(3),
  table.wp-list-table.ppb-table td:nth-child(3) { display: none; }
}

/* Phones: also hide Duration; tighten padding */
@media (max-width: 640px) {
  table.wp-list-table.ppb-table col.ppb-col-dur { display: none; }
  table.wp-list-table.ppb-table th:nth-child(4),
  table.wp-list-table.ppb-table td:nth-child(4) { display: none; }

  table.wp-list-table.ppb-table thead th,
  table.wp-list-table.ppb-table tbody td { padding: 10px 12px; }

  .pp-tabs { flex-wrap: wrap; gap: 8px; }
  .button.pp-launch-modal { padding: 8px 10px; }
  #pp-booking-modal .pp-booking-modal-content iframe { height: 72dvh !important; }
  #pp-booking-finish { width: 100%; }
  #pp-debug { left: 10px; right: 10px; max-width: none; }
}

/* Finalize grid: 2 cols on tablet, 1 on phone */
@media (max-width: 1024px) {
  .ppf-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 640px) {
  .ppf-grid { grid-template-columns: 1fr; }
  .ppf-footer { position: sticky; bottom: 0; }
}
</style>