<?php
/**
 * Final Version: Burgundy Check button → Opens popup with TicketingHub widget
 * Based 100% on your working date/time popup — just replaced content with widget
 */
$post_id = get_the_ID();
$post_translated = TravelHelper::post_translated($post_id);
$thumbnail_id = get_post_thumbnail_id($post_translated);
$duration = get_post_meta(get_the_ID(), 'duration_day', true);
$info_price = STTour::get_info_price();
$address = get_post_meta($post_translated, 'address', true);
$review_rate = floatval(STReview::get_avg_rate());
$price = STTour::get_info_price();
$count_review = get_comment_count($post_translated)['approved'];
$class_image = 'image-feature st-hover-grow';
$search_params = $_GET;
unset($search_params['paged']);
$url = st_get_link_with_search(get_permalink($post_translated), array('start', 'date', 'adult_number', 'child_number'), $search_params);
$main_color = st()->get_option('main_color', '#ec927e');

// TicketingHub Widget ID
$widget_id = get_field('ticketinghub_widget_id', $post_id);
$has_widget = !empty($widget_id);
$tour_title = get_the_title($post_translated);
$modal_id = 'th-check-popup-' . $post_id;
?>

<div class="services-item item-elementor grid-2" itemscope itemtype="https://schema.org/TouristTrip">
    <div class="item service-border st-border-radius">
        <div class="featured-image">
            <div class="st-tag-feature-sale">
                <?php if (get_post_meta($post_translated, 'is_featured', true) == 'on'): ?>
                    <div class="featured"><?php echo st()->get_option('st_text_featured', 'Featured'); ?></div>
                <?php endif; ?>
                <?php if (!empty($info_price['discount']) && $info_price['discount'] > 0 && $info_price['price_new'] > 0): ?>
                    <?php echo STFeatured::get_sale($info_price['discount']); ?>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url($url); ?>">
                <img itemprop="image" src="<?php echo wp_get_attachment_image_url($thumbnail_id, array(450, 300)); ?>" alt="<?php echo TravelHelper::get_alt_image(); ?>" class="<?php echo esc_attr($class_image); ?>" />
            </a>
            <?php
            $list_country = get_post_meta(get_the_ID(), 'multi_location', true);
            $list_country = preg_replace("/(\_)/", "", $list_country);
            $list_country = explode(",", $list_country);
            if (!empty($list_country)) {
                global $location_name, $location_id;
                if (!empty($location_name) && !empty($location_id)) {
                    if (in_array($location_id, $list_country)) {
                        $color_location = get_post_meta($location_id, 'color', true);
                        echo '<span class="ml5 f14 address st-location--style4" style="background:' . esc_attr($color_location) . '">' . esc_html(get_the_title($location_id)) . '</span>';
                    } else {
                        $color_location = get_post_meta($list_country[0], 'color', true);
                        echo '<span class="ml5 f14 address st-location--style4" style="background:' . esc_attr($color_location) . '">' . esc_html(get_the_title($list_country[0])) . '</span>';
                    }
                } else {
                    $color_location = get_post_meta($list_country[0], 'color', true);
                    echo '<span class="ml5 f14 address st-location--style4" style="background:' . esc_attr($color_location) . '">' . esc_html(get_the_title($list_country[0])) . '</span>';
                }
            }
            ?>
            <?php echo st_get_avatar_in_list_service(get_the_ID(), 70) ?>
        </div>

        <div class="content-item">
            <?php if ($address): ?>
                <div class="sub-title st-address d-flex align-items-center">
                    <i class="stt-icon-location1"></i> <?php echo esc_html($address); ?>
                </div>
            <?php endif; ?>
            <h3 class="title" itemprop="name">
                <a href="<?php echo esc_url($url); ?>" class="c-main"><?php echo get_the_title($post_translated); ?></a>
            </h3>
<!-- ADD MISSING Stuf -->
<?php
            $fullStars = floor($review_rate);
            $halfStar = ($review_rate - $fullStars) >= 0.5 ? 1 : 0;
            $emptyStars = 5 - $fullStars - $halfStar;
            ?>
            <div class="st-review">
                <?php for ($i = 0; $i < $fullStars; $i++): ?>
                    <i class="stt-icon-star1" style="color:gold;"></i>
                <?php endfor; ?>
                <?php if ($halfStar): ?>
                    <i class="stt-icon-star1" style="color:gold; opacity:0.5;"></i>
                <?php endif; ?>
                <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                    <i class="stt-icon-star1" style="color:#E0E0E0;"></i>
                <?php endfor; ?>
                <span class="rating" style="margin-left:4px;"><?php echo esc_html($review_rate); ?></span>
                <span class="count">
                    (<?php echo esc_html($count_review); ?>
                    <?php echo ($count_review == 1) ? esc_html__('Review', 'traveler') : esc_html__('Reviews', 'traveler'); ?>)
                </span>
            </div>
<?php
// People Viewing Notice
$people_viewing = rand(18, 42);
$slots_remaining = rand(3, 7);
?>
<div class="people-viewing-notice" style="display: block !important; opacity: 1 !important; background: #f5f5f5; padding: 12px; border: 1px solid #800020; margin-top: 8px; font-size: 14px; color: #e60000; z-index: 9999; font-weight: bold; border-radius: 8px;">
    <strong>📍 <?php echo esc_html($people_viewing); ?> travelers are viewing this tour right now</strong><br>
    <strong>⏰ Only <?php echo esc_html($slots_remaining); ?> slots remain — book now to secure your spot</strong>
</div><br>
<!-- End People Viewing Notice -->
<?php
// Begin cancellation code

$allow_cancel = get_post_meta(get_the_ID(), 'st_allow_cancel', true);

if (!empty($allow_cancel) && ($allow_cancel === 'on' || $allow_cancel === 'yes' || $allow_cancel == 1)) {
    echo '
    <style>
        .st-tooltip-container {
            position: relative;
            display: inline-block;
        }

        .st-tooltip-icon {
            border: 1.5px solid #888; /* grey border */
            background-color: transparent; /* transparent background */
            color: #888; /* grey "i" */
            border-radius: 50%;
            padding: 0 7px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            line-height: 18px;
            text-align: center;
            cursor: help;
            user-select: none;
            margin-left: 6px;
            transition: none; /* no hover bg change */
            position: relative;
        }

        /* Remove any native tooltip blue ? or default browser tooltip */
        .st-tooltip-icon[title],
        .st-tooltip-icon[title]:hover::after,
        .st-tooltip-icon:hover::after {
            content: none !important;
            display: none !important;
            pointer-events: none !important;
        }

        .st-tooltip-text {
            visibility: hidden;
            width: 260px;
            background-color: #fff; /* white background */
            color: #000; /* black text */
            text-align: left;
            border-radius: 6px;
            padding: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: absolute;
            z-index: 9999;
            bottom: 125%;
            left: 50%;
            margin-left: -130px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 13px;
            pointer-events: none;
        }

        .st-tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #fff transparent transparent transparent; /* white arrow */
        }

        .st-tooltip-container:hover .st-tooltip-text {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }
    </style>

    <div class="st-cancel-note" style="margin-bottom: 15px; font-weight: bold; color: #2e8b57; display: flex; align-items: center; gap: 6px;">
        <span style="color: #2e8b57;">🕒</span> Free cancellation available
        <div class="st-tooltip-container">
            <span class="st-tooltip-icon">i</span>
            <div class="st-tooltip-text">
Refund if cancelled within 24 hours of making the reservation 
– minus booking and processing fee.
            </div>
        </div>
    </div>';
}

// End cancellation code
?>
            <!-- Description -->
            <?php
            $excerpt = get_the_excerpt();
            $excerpt = preg_replace('/^This post is (also )?available in:.*?(\r?\n|<br\s*\/?>|&nbsp;|\s)+/ius', '', $excerpt);
            $excerpt = str_replace('简体中文 (Chinese (Simplified)) English Deutsch (German)', '', $excerpt);
            $excerpt = preg_replace('/^[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $excerpt);
            if ($excerpt) echo '<div class="st-tour--description">' . wp_kses_post($excerpt) . '</div>';
            ?>
             <div class="fixed-bottoms">
                <div class="st-tour--feature st-tour--tablet">
                    <div class="st-tour__item">
                        <div class="item__icon">
                            <?php echo TravelHelper::getNewIcon('icon-calendar-tour-solo', $main_color , '24px', '24px'); ?>
                        </div>
                        <div class="item__info">
                            <h4 class="info__name"><?php echo esc_html__('Duration', 'traveler'); ?></h4>
                            <p class="info__value"><?php echo esc_html($duration); ?></p>
                        </div>
                    </div>
                    <div class="st-tour__item">
                        <div class="item__icon">
                            <?php echo TravelHelper::getNewIcon('icon-service-tour-solo', $main_color , '24px', '24px'); ?>
                        </div>
                        <div class="item__info">
                            <h4 class="info__name"><?php echo esc_html__('Group Size', 'traveler'); ?></h4>
                            <p class="info__value">
                                <?php
                                $max_people = get_post_meta(get_the_ID(), 'max_people', true);
                                if (empty($max_people) or $max_people == 0 or $max_people < 0) {
                                    echo esc_html__('Unlimited', 'traveler');
                                } else {
                                    if ($max_people == 1)
                                        echo sprintf(esc_html__('%s person', 'traveler'), $max_people);
                                    else
                                        echo sprintf(esc_html__('%s people', 'traveler'), $max_people);
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-footer">
                <div class="st-flex space-between st-price__wrapper">
                    <div class="right">
                        <span class="price--tour">
                            <?php echo sprintf(esc_html__('%s', 'traveler'), STTour::get_price_html(get_the_ID())); ?>
                        </span>
                    </div>
                    <div class="st-btn--book d-flex gap-2 align-items-center">
                        <a href="<?php echo esc_url($url); ?>" class="btn btn-burgundy btn-sm">Tour Details</a>

                        <!-- Book Now Button — Opens TicketingHub Widget -->
                        <button type="button" 
                                class="btn-check-widget st-btn--book btn-success btn btn-sm d-flex align-items-center gap-1"
                                data-modal-id="<?php echo esc_attr($modal_id); ?>"
                                data-widget-id="<?php echo esc_attr($widget_id); ?>"
                                <?php echo $has_widget ? '' : 'disabled style="opacity:0.5;"'; ?>>
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="white">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                            Book Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- POPUP: TicketingHub Widget (uses your working popup structure) -->
<div id="<?php echo esc_attr($modal_id); ?>" class="check-date-overlay" style="display:none;">
    <div class="check-date-modal">
        <div class="modal-header">
            <h3><?php echo esc_html($tour_title); ?></h3>
            <button type="button" class="close-popup">&times;</button>
        </div>
        <div class="modal-body th-widget-container" style="padding:20px; height:calc(100vh - 120px); overflow-y:auto !important;">
            <div class="th-loading" style="text-align:center; padding:60px 20px; color:#666; font-size:16px;">
                Loading booking widget...
            </div>
        </div>
    </div>
</div>

<style>
/* Burgundy Check Button */
.btn-check-widget {
    background: #800020 !important;
    border-color: #800020 !important;
    color: white !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    text-transform: uppercase !important;
    min-width: 120px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    text-decoration: none !important;
}


.btn-check-widget:hover { background: #600018 !important; }
.btn-check-widget:disabled { opacity: 0.5; cursor: not-allowed; }

/* Popup Styling (your original — just taller for widget) */
.check-date-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}
.check-date-modal {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    height: 92vh;
    max-height: 800px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.4);
    overflow: hidden;
    animation: popupIn 0.35s ease;
    display: flex;
    flex-direction: column;
}
.modal-header {
    padding: 20px 24px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}
.modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #800020;
    font-weight: bold;
}
.close-popup {
    background: none;
    border: none;
    font-size: 36px;
    cursor: pointer;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    font-weight: 300;
}
.th-widget-container {
    flex: 1;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
@keyframes popupIn {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
// Initialize only once
if (!window.thCheckWidgetInit) {
    window.thCheckWidgetInit = true;

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-check-widget');
        if (!btn) return;

        e.preventDefault();
        const modalId = btn.dataset.modalId;
        const widgetId = btn.dataset.widgetId;
        const popup = document.getElementById(modalId);
        const container = popup?.querySelector('.th-widget-container');
        const loading = container?.querySelector('.th-loading');

        if (!popup || !container || !widgetId) return;

        // Open popup
        popup.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Load widget only once
        if (!container.classList.contains('widget-loaded')) {
            container.classList.add('widget-loaded');
            btn.disabled = true;
            btn.innerHTML = 'Loading...';

            const script = document.createElement('script');
            script.src = 'https://assets.ticketinghub.com/checkout.js';
            script.setAttribute('data-widget', widgetId);
            script.async = true;
            container.appendChild(script);

            // Remove loading when widget renders
            const observer = new MutationObserver(() => {
                if (container.children.length > 1) {
                    loading?.remove();
                    observer.disconnect();
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 16 16" fill="white"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg> Book Now';
                }
            });
            observer.observe(container, { childList: true, subtree: true });

            // Fallback
            setTimeout(() => {
                if (loading?.isConnected) {
                    loading.remove();
                    observer.disconnect();
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 16 16" fill="white"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg> Book Now';
                }
            }, 8000);
        } else {
            loading?.remove();
        }
    });

    // Close popup (X, outside click, ESC)
    document.addEventListener('click', e => {
        if (e.target.matches('.close-popup') || e.target.closest('.close-popup')) {
            document.querySelectorAll('.check-date-overlay').forEach(p => {
                p.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
        if (e.target.classList.contains('check-date-overlay')) {
            e.target.style.display = 'none';
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.check-date-overlay').forEach(p => {
                p.style.display = 'none';
                document.body.style.overflow = '';
            });
        }
    });
}
</script>