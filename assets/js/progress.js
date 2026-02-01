/**
 * Cart Progress Discount - JavaScript
 *
 * By Aditya Dhiman - https://adityadhiman.live
 *
 * @version 2.0.1
 */

(function ($) {
  "use strict";

  // Prevent duplicate initialization
  if (window.wcCpdInitialized) return;
  window.wcCpdInitialized = true;

  var config = window.wcCpdConfig || {};
  var AJAX_URL = config.ajaxUrl || "/wp-admin/admin-ajax.php";
  var ACTION = config.action || "wc_cpd_get_cart";
  var I18N = config.i18n || {};

  var container, textEl, fillEl;
  var isHidden = true;
  var fetchInFlight = false;
  var pollInterval = null;

  /**
   * Format currency
   */
  function formatCurrency(amount, symbol) {
    if (!amount || isNaN(amount)) return symbol + "0";
    return symbol + Math.round(amount).toLocaleString("en-IN");
  }

  /**
   * Fetch cart data
   */
  function fetchCartData() {
    if (fetchInFlight) return $.Deferred().resolve(null).promise();

    fetchInFlight = true;

    return $.ajax({
      url: AJAX_URL,
      type: "GET",
      data: { action: ACTION },
      dataType: "json",
    })
      .always(function () {
        fetchInFlight = false;
      })
      .fail(function () {
        return null;
      });
  }

  /**
   * Update UI
   */
  function updateUI(data) {
    // Cache elements
    container = container || $("#wc-cpd-global");
    textEl = textEl || container.find(".wc-cpd-text");
    fillEl = fillEl || container.find(".wc-cpd-fill");

    if (!container.length || !textEl.length || !fillEl.length) {
      return;
    }

    // Check for empty cart
    if (!data || !data.success || !data.subtotal || data.subtotal <= 0) {
      if (!isHidden) {
        container.hide().attr("aria-hidden", "true");
        isHidden = true;
      }
      return;
    }

    // Show if hidden
    if (isHidden) {
      container.show().attr("aria-hidden", "false");
      isHidden = false;
    }

    // Update progress bar
    var progress = Math.min(100, Math.max(0, parseFloat(data.progress) || 0));
    fillEl.css("width", progress + "%");

    // Update text
    var currencySymbol = data.currency_symbol || "₹";

    if (data.remaining > 0 && data.next_goal) {
      var remainingFormatted = formatCurrency(data.remaining, currencySymbol);
      var discountText = data.next_discount + "%";

      if (I18N.unlock_discount) {
        textEl.text(
          I18N.unlock_discount
            .replace("%1$s", remainingFormatted)
            .replace("%2$s", discountText),
        );
      } else {
        textEl.html(
          "Add <strong>" +
            remainingFormatted +
            "</strong> more to unlock <strong>" +
            discountText +
            "</strong> OFF",
        );
      }
    } else if (data.current_discount > 0) {
      var discountText = data.current_discount + "%";

      if (I18N.unlocked) {
        textEl.text(I18N.unlocked.replace("%s", discountText));
      } else {
        textEl.html(
          "🎉 You unlocked <strong>" + discountText + "</strong> OFF",
        );
      }
    } else {
      var firstGoal = data.next_goal || 2000;
      var firstDiscount = data.next_discount || 5;
      var remainingFormatted = formatCurrency(firstGoal, currencySymbol);

      if (I18N.unlock_discount) {
        textEl.text(
          I18N.unlock_discount
            .replace("%1$s", remainingFormatted)
            .replace("%2$s", firstDiscount + "%"),
        );
      } else {
        textEl.html(
          "Add <strong>" +
            remainingFormatted +
            "</strong> more to unlock <strong>" +
            firstDiscount +
            "%</strong> OFF",
        );
      }
    }
  }

  /**
   * Main update function
   */
  function updateIncentiveBar() {
    fetchCartData().then(function (data) {
      if (data) {
        updateUI(data);
      } else if (!isHidden) {
        $("#wc-cpd-global").hide().attr("aria-hidden", "true");
        isHidden = true;
      }
    });
  }

  /**
   * Start polling
   */
  function startPolling() {
    if (pollInterval) return;
    pollInterval = setInterval(updateIncentiveBar, 4000);
  }

  /**
   * Initialize event listeners
   */
  function initEventListeners() {
    var events = [
      "added_to_cart",
      "removed_from_cart",
      "updated_cart_totals",
      "wc_fragments_refreshed",
      "wc_cart_emptied",
      "ajaxComplete",
    ];

    $(document.body).on(events.join(" "), updateIncentiveBar);

    $(document).on("visibilitychange", function () {
      if (!document.hidden) {
        updateIncentiveBar();
      }
    });

    $(window).on("focus", updateIncentiveBar);
  }

  /**
   * Initialize
   */
  function init() {
    container = $("#wc-cpd-global");
    textEl = container.find(".wc-cpd-text");
    fillEl = container.find(".wc-cpd-fill");

    if (!container.length || !textEl.length || !fillEl.length) {
      setTimeout(init, 300);
      return;
    }

    initEventListeners();
    updateIncentiveBar();
    startPolling();
  }

  // Initialize on DOM ready
  $(document).ready(function () {
    init();
  });
})(jQuery);
