<?php
/**
 * grid  live site reiamginetours.com  v7.0 – PURE POPUP: NO BACKDROP, NO OVERLAY, ESC + CLICK OUTSIDE
 * Optimized: Defer TicketingHub script to on-click dynamic append (fixes looping/slow load)
 * Fixed: Single-load enforcement (check for existing script, disable button during load, flag container)
 * Ensures scrolling: !important on overflow-y:auto for widget container
 */

$post_id         = get_the_ID();
$post_translated = TravelHelper::post_translated($post_id);
$thumbnail_id    = get_post_thumbnail_id($post_translated);
$duration        = get_post_meta($post_id, 'duration_day', true);
$info_price      = STTour::get_info_price();
$address         = get_post_meta($post_translated, 'address', true);
$review_rate     = floatval(STReview::get_avg_rate());
$count_review    = get_comment_count($post_translated)['approved'];
$class_image     = 'image-feature st-hover-grow';

$url = st_get_link_with_search(
    get_permalink($post_translated),
    array('start', 'date', 'adult_number', 'child_number'),
    $_GET
);

$widget_id = get_field('ticketinghub_widget_id', $post_id);
$has_widget = !empty($widget_id);
$modal_id = 'th-modal-' . $post_id;
$tour_title = get_the_title($post_translated);
?>

<!-- FULL CARD -->
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

            <?php if (is_user_logged_in()): ?>
                <?php $data = STUser_f::get_icon_wishlist(); ?>
                <div class="service-add-wishlist login <?php echo $data['status'] ? 'added' : ''; ?>"
                     data-id="<?php echo get_the_ID(); ?>"
                     data-type="<?php echo get_post_type(get_the_ID()); ?>">
                    <?php echo TravelHelper::getNewIconV2('wishlist'); ?>
                    <div class="lds-dual-ring"></div>
                </div>
            <?php else: ?>
                <a href="#" class="login" data-bs-toggle="modal" data-bs-target="#st-login-form">
                    <div class="service-add-wishlist" title="<?php echo __('Add to wishlist', 'traveler'); ?>">
                        <?php echo TravelHelper::getNewIconV2('wishlist'); ?>
                        <div class="lds-dual-ring"></div>
                    </div>
                </a>
            <?php endif; ?>

            <a href="<?php echo esc_url($url); ?>">
                <img src="<?php echo wp_get_attachment_image_url($thumbnail_id, [900,600]); ?>"
                     alt="<?php echo TravelHelper::get_alt_image(); ?>" class="<?php echo $class_image; ?>"/>
            </a>
            <?php do_action('st_list_compare_button', get_the_ID(), get_post_type(get_the_ID())); ?>
            <?php echo st_get_avatar_in_list_service(get_the_ID(),70); ?>
        </div>

        <div class="content-item">
            <?php if ($address): ?>
                <div class="sub-title st-address d-flex align-items-center">
                    <i class="stt-icon-location1"></i> <?php echo esc_html($address); ?>
                </div>
            <?php endif; ?>

            <h3 class="title"><a href="<?php echo esc_url($url); ?>"><?php echo get_the_title($post_translated); ?></a></h3>

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

            <!-- Reviews -->
            <?php
            $full = floor($review_rate); $half = ($review_rate - $full) >= 0.5 ? 1 : 0; $empty = 5 - $full - $half;
            ?>
            <div class="st-review">
                <?php for($i=0;$i<$full;$i++) echo '<i class="stt-icon-star1" style="color:gold;"></i>'; ?>
                <?php if($half) echo '<i class="stt-icon-star1" style="color:gold;opacity:0.5;"></i>'; ?>
                <?php for($i=0;$i<$empty;$i++) echo '<i class="stt-icon-star1" style="color:#E0E0E0;"></i>'; ?>
                <span style="margin-left:4px;"><?php echo $review_rate; ?></span>
                <span>(<?php echo $count_review; ?> <?php echo $count_review == 1 ? 'Review' : 'Reviews'; ?>)</span>
            </div>

            <!-- Features -->
            <div class="fixed-bottoms">
                <div class="st-tour--feature st-tour--tablet">
                    <div class="st-tour__item">
                        <div class="item__icon"><?php echo TravelHelper::getNewIcon('icon-calendar-tour-solo', $main_color, '24px', '24px'); ?></div>
                        <div class="item__info"><h4>Duration</h4><p><?php echo esc_html($duration); ?></p></div>
                    </div>
                    <div class="st-tour__item">
                        <div class="item__icon"><?php echo TravelHelper::getNewIcon('icon-service-tour-solo', $main_color, '24px', '24px'); ?></div>
                        <div class="item__info"><h4>Group Size</h4><p>
                            <?php
                            $max = get_post_meta($post_id, 'max_people', true);
                            echo $max <= 0 ? 'Unlimited' : ($max == 1 ? "$max person" : "$max people");
                            ?>
                        </p></div>
                    </div>
                </div>
            </div>

            <!-- PRICE + BUTTONS -->
            <div class="section-footer">
                <div class="st-flex space-between st-price__wrapper">
                    <div class="right">
                        <span class="price--tour"><?php echo STTour::get_price_html($post_id); ?></span>
                    </div>
                    <div class="st-btn--book d-flex gap-2">
                        <a href="<?php echo esc_url($url); ?>" class="btn btn-burgundy btn-sm">Tour Details</a>
                        <?php if ($has_widget): ?>
                            <button type="button" class="btn btn-success btn-sm th-book-btn d-flex align-items-center gap-1"
                                    data-modal-id="<?php echo esc_attr($modal_id); ?>"
                                    data-widget-id="<?php echo esc_attr($widget_id); ?>">
                                                            <svg width="18" height="18" viewBox="0 0 16 16" fill="white">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                                Book Now
                            </button>
                        <?php else: ?>
                            <a href="<?php echo esc_url($url); ?>" class="btn btn-success btn-sm">Book Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/* PURE POPUP — NO BACKDROP */
if ($has_widget) {
    add_action('wp_footer', function() use ($modal_id, $post_id, $tour_title, $widget_id, $url) {
        ?>
        <div class="th-pure-popup" id="<?php echo esc_attr($modal_id); ?>" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; align-items:center; justify-content:center; pointer-events:none;">
            <div class="popup-content" style="width:380px; max-width:95%; height:90vh; background:#fff; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative; pointer-events:auto; overflow:hidden;">
                
                <!-- TOP-RIGHT: Tour Details + BLACK X -->
                <div style="position:absolute; top:12px; right:12px; z-index:10; display:flex; gap:6px; align-items:center;">
                    <a href="<?php echo esc_url($url); ?>" class="btn btn-burgundy btn-sm th-tour-details-btn">
                        Tour Details
                    </a>
                    <button type="button" class="th-close-popup" style="background:transparent; border:none; font-size:24px; font-weight:bold; color:#000; cursor:pointer; width:28px; height:28px; display:flex; align-items:center; justify-content:center;">
                        ×
                    </button>
                </div>

                <!-- TITLE -->
                <div style="padding:50px 20px 0; text-align:center;">
                    <h5 style="margin:0; font-size:18px; font-weight:bold;"><?php echo esc_html($tour_title); ?></h5>
                </div>

                <!-- WIDGET CONTAINER (Script appended dynamically) -->
                <div class="th-widget-container" 
                     style="height:calc(100% - 80px); overflow-y:auto !important; padding:16px;">
                    <div class="th-loading" style="text-align:center; padding:40px; color:#666;">Loading booking widget...</div>
                </div>
            </div>
        </div>
        <?php
    }, 10);
}
?>

<?php
/* JS + CSS: PURE POPUP — NO BACKDROP */
add_action('wp_footer', function() { ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Open popup + dynamic script append (single load only) + observer for render detection
    document.querySelectorAll('.th-book-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const modalId = this.dataset.modalId;
            const widgetId = this.dataset.widgetId;
            const popup = document.getElementById(modalId);
            const container = popup ? popup.querySelector('.th-widget-container') : null;
            const loading = container ? container.querySelector('.th-loading') : null;
            
            if (popup && container && !container.classList.contains('widget-loaded')) {
                // Prevent multiple loads: Flag container and disable button
                container.classList.add('widget-loaded');
                this.disabled = true;
                this.textContent = 'Loading...';
                
                popup.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                // Check for existing script before appending (single instance)
                if (!container.querySelector('script[src*="checkout.js"]')) {
                    const script = document.createElement('script');
                    script.src = 'https://assets.ticketinghub.com/checkout.js';
                    script.setAttribute('data-widget', widgetId);
                    script.async = true;
                    container.appendChild(script);

                    // MutationObserver to remove loading when widget adds content
                    if (loading) {
                        const observer = new MutationObserver(function(mutations) {
                            const hasContent = mutations.some(m => 
                                m.type === 'childList' && 
                                (m.addedNodes.length > 0 || m.removedNodes.length > 0) &&
                                container.children.length > 1
                            );
                            if (hasContent) {
                                loading.remove();
                                observer.disconnect();
                                // Re-enable button after load
                                btn.disabled = false;
                                btn.textContent = 'Book Now';
                            }
                        });
                        observer.observe(container, { childList: true, subtree: true });
                        
                        // Fallback: Remove after 5s and re-enable
                        setTimeout(() => {
                            if (loading && loading.parentNode) {
                                loading.remove();
                                observer.disconnect();
                                btn.disabled = false;
                                btn.textContent = 'Book Now';
                            }
                        }, 5000);
                    }

                    // Error handling
                    script.onerror = function() {
                        if (loading) loading.remove();
                        container.innerHTML = '<div style="text-align:center; padding:20px; color:#e74c3c;">Error loading widget. <a href="' + popup.querySelector('.th-tour-details-btn').href + '" style="color:#800020; text-decoration:underline;">Try Tour Details</a>.</div>';
                        btn.disabled = false;
                        btn.textContent = 'Book Now';
                    };
                } else {
                    // Already loaded: Just hide loading
                    if (loading) loading.remove();
                    this.disabled = false;
                    this.textContent = 'Book Now';
                }
            }
        });
    });

    // Close on X
    document.querySelectorAll('.th-close-popup').forEach(btn => {
        btn.addEventListener('click', function() {
            const popup = this.closest('.th-pure-popup');
            if (popup) {
                // Reset flags for potential re-open
                const container = popup.querySelector('.th-widget-container');
                if (container) container.classList.remove('widget-loaded');
                const bookBtn = document.querySelector(`[data-modal-id="${popup.id}"]`);
                if (bookBtn) {
                    bookBtn.disabled = false;
                    bookBtn.textContent = 'Book Now';
                }
                popup.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // Close on Tour Details (with redirect)
    document.querySelectorAll('.th-tour-details-btn').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const popup = this.closest('.th-pure-popup');
            if (popup) {
                // Reset flags
                const container = popup.querySelector('.th-widget-container');
                if (container) container.classList.remove('widget-loaded');
                const bookBtn = document.querySelector(`[data-modal-id="${popup.id}"]`);
                if (bookBtn) {
                    bookBtn.disabled = false;
                    bookBtn.textContent = 'Book Now';
                }
                popup.style.display = 'none';
                document.body.style.overflow = '';
                setTimeout(() => { window.location.href = this.href; }, 200);
            }
        });
    });

    // ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const open = document.querySelector('.th-pure-popup[style*="flex"]');
            if (open) {
                // Reset flags
                const container = open.querySelector('.th-widget-container');
                if (container) container.classList.remove('widget-loaded');
                const bookBtn = document.querySelector(`[data-modal-id="${open.id}"]`);
                if (bookBtn) {
                    bookBtn.disabled = false;
                    bookBtn.textContent = 'Book Now';
                }
                open.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    });

    // Click outside
    document.addEventListener('click', function(e) {
        const popup = document.querySelector('.th-pure-popup[style*="flex"]');
        if (popup && !popup.querySelector('.popup-content').contains(e.target)) {
            // Reset flags
            const container = popup.querySelector('.th-widget-container');
            if (container) container.classList.remove('widget-loaded');
            const bookBtn = document.querySelector(`[data-modal-id="${popup.id}"]`);
            if (bookBtn) {
                bookBtn.disabled = false;
                bookBtn.textContent = 'Book Now';
            }
            popup.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});
</script>

<style>
/* PURE POPUP — NO BACKDROP */
.th-pure-popup {
    background: transparent !important;
}

/* BUTTONS — SAME AS CARD */
.btn-burgundy,
.th-pure-popup .btn-burgundy {
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
.btn-burgundy:hover,
.th-pure-popup .btn-burgundy:hover {
    background: #660018 !important;
}

.btn-success {
    background: #28a745 !important;
    border-color: #28a745 !important;
    color: white !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    text-transform: uppercase !important;
    min-width: 120px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
}
.btn-success:hover {
    background: #218838 !important;
}

/* Smooth scrolling for popups/modals + enforce widget scroll */
.th-pure-popup .th-widget-container,
.popup, .modal-body {
    -webkit-overflow-scrolling: touch !important;
    overflow-y: auto !important;
}

/* Disabled button styling */
.th-book-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}


</style>
<?php }, 999);
?>