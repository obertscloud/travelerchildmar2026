(function($) {
    $(document).ready(function() {
        // Nuke the loader element immediately and forever
        function removeLoader() {
            $(".ajax-filter-loading").remove(); // Delete from DOM, not just hide
        }
        removeLoader(); // On load

        // Override fadeIn/fadeOut to prevent any show/hide attempts
        var originalFadeIn = $.fn.fadeIn;
        $.fn.fadeIn = function() {
            if ($(this).hasClass("ajax-filter-loading")) {
                removeLoader(); // Ensure it's gone
                return this; // Skip entirely
            }
            return originalFadeIn.apply(this, arguments);
        };
        var originalFadeOut = $.fn.fadeOut;
        $.fn.fadeOut = function() {
            if ($(this).hasClass("ajax-filter-loading")) {
                removeLoader();
                return this;
            }
            return originalFadeOut.apply(this, arguments);
        };

        // Aggressive watcher: Delete if theme tries to re-add via AJAX/mutations
        var observer = new MutationObserver(function(mutations) {
            var hasLoader = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === "childList") {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && (node.classList && node.classList.contains("ajax-filter-loading"))) {
                            hasLoader = true;
                            $(node).remove();
                        }
                    });
                }
            });
            if (hasLoader) removeLoader();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Failsafe: Poll every 100ms for 5s (covers AJAX loads)
        var interval = setInterval(function() {
            removeLoader();
        }, 100);
        setTimeout(function() {
            clearInterval(interval);
        }, 5000);

        // Ultra-aggressive: Hook into DOM inserts for older-style additions
        $(document).on('DOMNodeInserted', function(e) {
            if ($(e.target).hasClass('ajax-filter-loading')) {
                $(e.target).remove();
            }
        });
    });
})(jQuery);

