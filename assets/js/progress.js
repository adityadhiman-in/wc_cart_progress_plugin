/**
 * Cart Progress Discount - By Aditya Dhiman
 * https://adityadhiman.live
 * @version 2.0.2
 */

(function ($) {
  "use strict";

  if (window.wcCpdLoaded) return;
  window.wcCpdLoaded = true;

  var config = window.wcCpdConfig || {};
  var ajaxURL = config.ajaxUrl || "/wp-admin/admin-ajax.php";
  var action = config.action || "wc_cpd_get_cart";
  var i18n = config.i18n || {};

  var $container, $text, $fill;
  var isHidden = true;

  function formatCurrency(amount, symbol) {
    if (!amount || isNaN(amount) || amount <= 0) return symbol + "0";
    return symbol + Math.round(amount).toLocaleString("en-IN");
  }

  function updateBar() {
    $.ajax({
      url: ajaxURL,
      type: "GET",
      data: { action: action },
      dataType: "json",
      timeout: 10000,
      success: function (data) {
        // Initialize elements
        $container = $container || $("#wc-cpd-global");
        $text = $text || $container.find(".wc-cpd-text");
        $fill = $fill || $container.find(".wc-cpd-fill");

        if (!$container.length || !$text.length || !$fill.length) return;

        // Check if cart has items
        if (!data || !data.success || !data.subtotal || data.subtotal <= 0) {
          // Hide bar when cart is empty
          if (!isHidden) {
            $container.hide().attr("aria-hidden", "true");
            isHidden = true;
          }
          return;
        }

        // Show bar when cart has items
        if (isHidden) {
          $container.show().attr("aria-hidden", "false");
          isHidden = false;
        }

        // Update progress
        var progress = Math.min(
          100,
          Math.max(0, parseFloat(data.progress) || 0),
        );
        $fill.css("width", progress + "%");

        // Update text
        var symbol = data.currency_symbol || "₹";

        if (data.remaining > 0 && data.next_goal) {
          var remaining = formatCurrency(data.remaining, symbol);
          var discount = data.next_discount + "%";

          if (i18n.unlock_discount) {
            $text.text(
              i18n.unlock_discount
                .replace("%1$s", remaining)
                .replace("%2$s", discount),
            );
          } else {
            $text.html(
              "Add <strong>" +
                remaining +
                "</strong> more to unlock <strong>" +
                discount +
                "</strong> OFF",
            );
          }
        } else if (data.current_discount > 0) {
          var discount = data.current_discount + "%";

          if (i18n.unlocked) {
            $text.text(i18n.unlocked.replace("%s", discount));
          } else {
            $text.html("🎉 You unlocked <strong>" + discount + "</strong> OFF");
          }
        } else {
          // First tier
          var goal = data.next_goal || 2000;
          var discount = data.next_discount || 5;
          var remaining = formatCurrency(goal, symbol);

          if (i18n.unlock_discount) {
            $text.text(
              i18n.unlock_discount
                .replace("%1$s", remaining)
                .replace("%2$s", discount + "%"),
            );
          } else {
            $text.html(
              "Add <strong>" +
                remaining +
                "</strong> more to unlock <strong>" +
                discount +
                "%</strong> OFF",
            );
          }
        }
      },
      error: function () {
        // Hide bar on error
        $container = $container || $("#wc-cpd-global");
        if ($container.length && !isHidden) {
          $container.hide().attr("aria-hidden", "true");
          isHidden = true;
        }
      },
    });
  }

  function init() {
    $container = $("#wc-cpd-global");
    $text = $container.find(".wc-cpd-text");
    $fill = $container.find(".wc-cpd-fill");

    if (!$container.length) {
      setTimeout(init, 500);
      return;
    }

    // Listen to WooCommerce events
    $(document.body).on(
      "added_to_cart removed_from_cart updated_cart_totals wc_fragments_refreshed wc_cart_emptied ajaxComplete",
      updateBar,
    );

    // Visibility change
    $(document).on("visibilitychange", function () {
      if (!document.hidden) updateBar();
    });

    // Window focus
    $(window).on("focus", updateBar);

    // Initial update
    updateBar();

    // Periodic update every 5 seconds
    setInterval(updateBar, 5000);
  }

  $(document).ready(function () {
    init();
  });
})(jQuery);
