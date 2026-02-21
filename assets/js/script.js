/**
 * RDAS Pricing Updater - Modern UI JavaScript
 * Version: 3.0.0
 *
 * Enhanced interactions and animations for premium UI
 */

(function($) {
  'use strict';

  // Main RDAS object
  var RDAS = {
    version: '3.0.0',
    ajaxUrl: '', // Will be set from page
    nonce: '',
    currentPage: '',
    isLoading: false,
    selectedRows: [],
    settings: {
      autoRefresh: false,
      refreshInterval: 30000,
      darkMode: false,
      compactView: false
    }
  };

  // Initialize when document is ready
  $(document).ready(function() {
    RDAS.init();
  });

  /**
   * Initialize RDAS functionality
   */
  RDAS.init = function() {
    this.initAjaxUrl();
    this.getCurrentPage();
    this.loadSettings();
    this.bindEvents();
    this.initializeComponents();
    this.initTheme();
  };

  /**
   * Initialize AJAX URL based on current page
   */
  RDAS.initAjaxUrl = function() {
    // If ajaxUrl was set from PHP, use it
    if (this.ajaxUrl) {
      return;
    }

    // Otherwise, build from current URL
    var url = window.location.href;
    var baseUrl = url.split('?')[0];

    // Check if we're on addonmodules.php
    if (url.indexOf('addonmodules.php') !== -1) {
      // Extract the base URL and module parameter
      var params = new URLSearchParams(window.location.search);
      var module = params.get('module') || 'rdas_pricing_updater';
      this.ajaxUrl = baseUrl + '?module=' + module + '&action=ajax';
    } else {
      // Fallback - use current URL
      this.ajaxUrl = url;
    }
  };

  /**
   * Get current page from URL
   */
  RDAS.getCurrentPage = function() {
    var urlParams = new URLSearchParams(window.location.search);
    this.currentPage = urlParams.get('page') || 'dashboard';
  };

  /**
   * Load user settings from localStorage
   */
  RDAS.loadSettings = function() {
    var savedSettings = localStorage.getItem('rdas_settings');
    if (savedSettings) {
      this.settings = $.extend(this.settings, JSON.parse(savedSettings));
    }
  };

  /**
   * Save user settings to localStorage
   */
  RDAS.saveSettings = function() {
    localStorage.setItem('rdas_settings', JSON.stringify(this.settings));
  };

  /**
   * Initialize theme based on settings
   */
  RDAS.initTheme = function() {
    if (this.settings.darkMode) {
      $('.rdas-pricing-updater').addClass('rdas-dark');
    }

    // Create toast container if not exists
    if ($('.rdas-toast-container').length === 0) {
      $('body').append('<div class="rdas-toast-container"></div>');
    }
  };

  /**
   * Toggle dark mode
   */
  RDAS.toggleDarkMode = function() {
    this.settings.darkMode = !this.settings.darkMode;
    $('.rdas-pricing-updater').toggleClass('rdas-dark');
    this.saveSettings();
  };

  /**
   * Bind all event handlers
   */
  RDAS.bindEvents = function() {
    // Global events
    $(document).on('click', '.rdas-modal-close, .rdas-modal-backdrop', function(e) {
      if ($(e.target).hasClass('rdas-modal-backdrop') || $(e.target).hasClass('rdas-modal-close')) {
        RDAS.closeModal();
      }
    });

    // Close toast on click
    $(document).on('click', '.rdas-toast-close', function() {
      $(this).closest('.rdas-toast').fadeOut(200, function() {
        $(this).remove();
      });
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape') {
        RDAS.closeModal();
      }
    });

    // Theme toggle
    $(document).on('click', '.rdas-theme-toggle', function() {
      RDAS.toggleDarkMode();
    });

    // Navigation tabs
    $(document).on('click', '.rdas-nav-tab', function(e) {
      e.preventDefault();
      var href = $(this).attr('href');
      if (href && href !== '#') {
        window.location.href = href;
      }
    });

    // Page-specific events
    this.bindPageEvents();
  };

  /**
   * Bind page-specific events
   */
  RDAS.bindPageEvents = function() {
    // Pricing page events
    $(document).on('click', '.rdas-sync-btn', function(e) {
      e.preventDefault();
      var tld = $(this).data('tld');
      RDAS.syncDomain(tld, $(this));
    });

    $(document).on('click', '.rdas-edit-btn', function(e) {
      e.preventDefault();
      var tld = $(this).data('tld');
      RDAS.openEditModal(tld);
    });

    $(document).on('click', '.rdas-bulk-sync-btn', function(e) {
      e.preventDefault();
      RDAS.bulkSync();
    });

    // Row selection
    $(document).on('change', '.rdas-row-checkbox', function() {
      RDAS.updateSelection();
    });

    $(document).on('change', '.rdas-select-all', function() {
      var checked = $(this).is(':checked');
      $('.rdas-row-checkbox').prop('checked', checked);
      RDAS.updateSelection();
    });

    // Settings page events
    $(document).on('submit', '.rdas-settings-form', function(e) {
      e.preventDefault();
      RDAS.saveSettingsForm($(this));
    });

    $(document).on('input change', '.rdas-calc-input', function() {
      RDAS.updateCalcPreview();
    });

    // Table search with debounce
    $(document).on('input', '.rdas-table-search input', RDAS.debounce(function() {
      RDAS.filterTable($(this).val());
    }, 300));

    // Status filter
    $(document).on('change', '.rdas-status-filter', function() {
      RDAS.filterByStatus($(this).val());
    });
  };

  /**
   * Initialize components based on current page
   */
  RDAS.initializeComponents = function() {
    // Initialize tooltips
    this.initTooltips();

    // Initialize animations
    this.initAnimations();

    // Page-specific initialization
    switch(this.currentPage) {
      case 'dashboard':
        this.initDashboard();
        break;
      case 'pricing':
        this.initPricing();
        break;
      case 'settings':
        this.initSettings();
        break;
      case 'logs':
        this.initLogs();
        break;
    }
  };

  /**
   * Initialize tooltips
   */
  RDAS.initTooltips = function() {
    $('[data-tooltip]').each(function() {
      var $el = $(this);
      var text = $el.data('tooltip');
      $el.attr('title', text);
    });
  };

  /**
   * Initialize scroll reveal animations
   */
  RDAS.initAnimations = function() {
    // Staggered reveal for stat cards
    $('.rdas-stat-card').each(function(index) {
      var $card = $(this);
      setTimeout(function() {
        $card.addClass('rdas-visible');
      }, index * 100);
    });

    // Fade in cards
    $('.rdas-card').each(function(index) {
      var $card = $(this);
      setTimeout(function() {
        $card.addClass('rdas-visible');
      }, index * 150);
    });
  };

  // ==========================================================================
  // Dashboard Functions
  // ==========================================================================

  RDAS.initDashboard = function() {
    this.loadDashboardStats();

    // Auto refresh if enabled
    if (this.settings.autoRefresh) {
      this.startAutoRefresh();
    }
  };

  RDAS.loadDashboardStats = function() {
    this.ajaxRequest({
      action: 'get_dashboard_stats',
      success: function(response) {
        if (response.success) {
          RDAS.updateDashboardStats(response.data);
        }
      }
    });
  };

  RDAS.updateDashboardStats = function(stats) {
    $('#rdas-total-domains').text(stats.total_domains || 0);
    $('#rdas-active-promos').text(stats.active_promos || 0);
    $('#rdas-last-sync').text(stats.last_sync || 'Never');
    $('#rdas-api-status').html(
      stats.api_status === 'connected'
        ? '<span class="rdas-status rdas-status-active">Connected</span>'
        : '<span class="rdas-status rdas-status-error">Disconnected</span>'
    );

    // Animate number changes
    $('.rdas-stat-value').each(function() {
      var $this = $(this);
      var finalValue = $this.text();
      if ($.isNumeric(finalValue)) {
        $this.prop('counter', 0).animate({
          counter: finalValue
        }, {
          duration: 1000,
          easing: 'swing',
          step: function(now) {
            $this.text(Math.ceil(now));
          }
        });
      }
    });
  };

  RDAS.startAutoRefresh = function() {
    this.stopAutoRefresh();
    this.refreshInterval = setInterval(function() {
      RDAS.loadDashboardStats();
    }, this.settings.refreshInterval);
  };

  RDAS.stopAutoRefresh = function() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  };

  // ==========================================================================
  // Pricing Functions
  // ==========================================================================

  RDAS.initPricing = function() {
    // Data is already rendered server-side by PHP
    // No need to load via AJAX
  };

  RDAS.loadPricingData = function() {
    // Data is rendered server-side by PHP
    // Reload page to get fresh data
    window.location.reload();
  };

  RDAS.renderPricingTable = function(data) {
    var tbody = $('.rdas-table tbody');
    tbody.empty();

    $.each(data, function(index, item) {
      var row = RDAS.createPricingRow(item);
      tbody.append(row);
    });
  };

  RDAS.createPricingRow = function(item) {
    var promoClass = item.promo_active ? 'rdas-row-promo' : '';
    var promoIndicator = item.promo_active
      ? '<span class="rdas-promo-indicator"><i class="fa fa-tag"></i> Promo</span>'
      : '';

    var registerPrice = item.promo_active && item.promo_register
      ? '<span class="rdas-price-original">Rp' + item.register + '</span><span class="rdas-price-promo">Rp' + item.promo_register + '</span>'
      : '<span class="rdas-price">Rp' + item.register + '</span>';

    return $('<tr class="' + promoClass + '" data-tld="' + item.extension + '">' +
      '<td>' +
        '<div class="rdas-flex rdas-gap-2">' +
          '<input type="checkbox" class="rdas-row-checkbox" value="' + item.extension + '">' +
          '<div>' +
            '<span class="rdas-tld">' + item.extension + '</span>' +
            promoIndicator +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td>' + registerPrice + '</td>' +
      '<td><span class="rdas-price">Rp' + item.renew + '</span></td>' +
      '<td><span class="rdas-price">Rp' + item.transfer + '</span></td>' +
      '<td><span class="rdas-margin-badge">' + item.margin + '%</span></td>' +
      '<td>' +
        '<div class="rdas-actions">' +
          '<button class="rdas-btn rdas-btn-sm rdas-btn-ghost rdas-sync-btn" data-tld="' + item.extension + '" title="Sync">' +
            '<i class="fa fa-sync"></i>' +
          '</button>' +
          '<button class="rdas-btn rdas-btn-sm rdas-btn-ghost rdas-edit-btn" data-tld="' + item.extension + '" title="Edit">' +
            '<i class="fa fa-edit"></i>' +
          '</button>' +
        '</div>' +
      '</td>' +
    '</tr>');
  };

  RDAS.syncDomain = function(tld, button) {
    var originalHtml = button.html();
    button.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

    var row = button.closest('tr');
    row.addClass('rdas-row-syncing');

    this.ajaxRequest({
      action: 'sync_domain',
      data: { tld: tld },
      success: function(response) {
        if (response.success) {
          row.replaceWith(RDAS.createPricingRow(response.data));
          RDAS.showToast('success', 'Sync Complete', tld + ' has been synced successfully');
        } else {
          row.removeClass('rdas-row-syncing').addClass('rdas-row-error');
          RDAS.showToast('error', 'Sync Failed', response.message || 'Unable to sync ' + tld);
        }
      },
      complete: function() {
        button.html(originalHtml).prop('disabled', false);
        setTimeout(function() {
          row.removeClass('rdas-row-syncing rdas-row-error');
        }, 2000);
      }
    });
  };

  RDAS.updateSelection = function() {
    this.selectedRows = [];
    $('.rdas-row-checkbox:checked').each(function() {
      RDAS.selectedRows.push($(this).val());
    });

    var count = this.selectedRows.length;
    $('.rdas-selected-count').text(count);
    $('.rdas-bulk-actions').toggle(count > 0);
  };

  RDAS.bulkSync = function() {
    if (this.selectedRows.length === 0) {
      this.showToast('warning', 'No Selection', 'Please select domains to sync');
      return;
    }

    var button = $('.rdas-bulk-sync-btn');
    var originalHtml = button.html();
    button.html('<i class="fa fa-spinner fa-spin"></i> Syncing...').prop('disabled', true);

    this.ajaxRequest({
      action: 'bulk_sync',
      data: { tlds: this.selectedRows },
      success: function(response) {
        if (response.success) {
          RDAS.showToast('success', 'Bulk Sync Complete', response.data.updated + ' domains updated');
          // Reload page to show updated data
          setTimeout(function() { window.location.reload(); }, 1500);
        } else {
          RDAS.showToast('error', 'Bulk Sync Failed', response.message);
        }
      },
      complete: function() {
        button.html(originalHtml).prop('disabled', false);
      }
    });
  };

  RDAS.filterTable = function(query) {
    var filter = query.toLowerCase();
    $('.rdas-table tbody tr').each(function() {
      var tld = $(this).data('tld') || '';
      $(this).toggle(tld.toLowerCase().indexOf(filter) > -1);
    });
  };

  RDAS.filterByStatus = function(status) {
    if (!status) {
      $('.rdas-table tbody tr').show();
      return;
    }

    $('.rdas-table tbody tr').each(function() {
      var hasPromo = $(this).hasClass('rdas-row-promo');
      if (status === 'promo') {
        $(this).toggle(hasPromo);
      } else {
        $(this).toggle(!hasPromo);
      }
    });
  };

  // ==========================================================================
  // Settings Functions
  // ==========================================================================

  RDAS.initSettings = function() {
    this.updateCalcPreview();
  };

  RDAS.updateCalcPreview = function() {
    var basePrice = parseFloat($('.rdas-calc-base-price').val()) || 100000;
    var margin = parseFloat($('.rdas-calc-margin').val()) || 20;
    var rounding = $('.rdas-calc-rounding').val() || 'up_1000';

    var marginAmount = basePrice * (margin / 100);
    var priceWithMargin = basePrice + marginAmount;
    var finalPrice = RDAS.applyRounding(priceWithMargin, rounding);

    $('.rdas-calc-margin-amount').text('Rp ' + marginAmount.toLocaleString('id-ID'));
    $('.rdas-calc-price-with-margin').text('Rp ' + Math.round(priceWithMargin).toLocaleString('id-ID'));
    $('.rdas-calc-final-price').text('Rp ' + finalPrice.toLocaleString('id-ID'));
  };

  RDAS.applyRounding = function(price, rule) {
    switch(rule) {
      case 'up_1000':
        return Math.ceil(price / 1000) * 1000;
      case 'up_5000':
        return Math.ceil(price / 5000) * 5000;
      case 'nearest_1000':
        return Math.round(price / 1000) * 1000;
      default:
        return Math.round(price);
    }
  };

  RDAS.saveSettingsForm = function(form) {
    var button = form.find('button[type="submit"]');
    var originalHtml = button.html();
    button.html('<i class="fa fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

    this.ajaxRequest({
      action: 'save_settings',
      data: form.serialize(),
      success: function(response) {
        if (response.success) {
          RDAS.showToast('success', 'Settings Saved', 'Your settings have been saved successfully');
        } else {
          RDAS.showToast('error', 'Save Failed', response.message || 'Unable to save settings');
        }
      },
      complete: function() {
        button.html(originalHtml).prop('disabled', false);
      }
    });
  };

  // ==========================================================================
  // Logs Functions
  // ==========================================================================

  RDAS.initLogs = function() {
    this.loadLogs();
  };

  RDAS.loadLogs = function(page) {
    page = page || 1;

    this.ajaxRequest({
      action: 'get_logs',
      data: { page: page },
      success: function(response) {
        if (response.success) {
          RDAS.renderLogs(response.data.logs);
          RDAS.renderPagination(response.data.pagination);
        }
      }
    });
  };

  RDAS.renderLogs = function(logs) {
    var tbody = $('.rdas-table tbody');
    tbody.empty();

    if (logs.length === 0) {
      tbody.append('<tr><td colspan="5" class="rdas-text-center rdas-text-muted">No logs found</td></tr>');
      return;
    }

    $.each(logs, function(index, log) {
      var levelClass = log.level === 'error' ? 'rdas-text-error' : log.level === 'warning' ? 'rdas-text-warning' : '';
      tbody.append('<tr>' +
        '<td><code>' + log.id + '</code></td>' +
        '<td><span class="rdas-status rdas-status-' + log.level + '">' + log.level + '</span></td>' +
        '<td class="' + levelClass + '">' + log.message + '</td>' +
        '<td><small>' + log.date + '</small></td>' +
        '<td>' +
          '<button class="rdas-btn rdas-btn-sm rdas-btn-ghost rdas-view-log-btn" data-log-id="' + log.id + '">' +
            '<i class="fa fa-eye"></i>' +
          '</button>' +
        '</td>' +
      '</tr>');
    });
  };

  RDAS.renderPagination = function(pagination) {
    var container = $('.rdas-pagination');
    container.empty();

    for (var i = 1; i <= pagination.total_pages; i++) {
      var active = i === pagination.current_page ? 'rdas-btn-primary' : 'rdas-btn-secondary';
      container.append('<button class="rdas-btn rdas-btn-sm ' + active + ' rdas-page-btn" data-page="' + i + '">' + i + '</button>');
    }
  };

  // ==========================================================================
  // Modal Functions
  // ==========================================================================

  RDAS.openModal = function(modalId) {
    var backdrop = $('#' + modalId);
    backdrop.addClass('show');
    $('body').css('overflow', 'hidden');
  };

  RDAS.closeModal = function() {
    $('.rdas-modal-backdrop').removeClass('show');
    $('body').css('overflow', '');
  };

  RDAS.openEditModal = function(tld) {
    // Load data first
    this.ajaxRequest({
      action: 'get_domain_pricing',
      data: { tld: tld },
      success: function(response) {
        if (response.success) {
          // Populate modal fields
          $('#rdas-edit-tld').text(tld);
          $('#rdas-edit-register').val(response.data.register);
          $('#rdas-edit-renew').val(response.data.renew);
          $('#rdas-edit-transfer').val(response.data.transfer);
          $('#rdas-edit-margin').val(response.data.margin);

          RDAS.openModal('rdas-edit-modal');
        }
      }
    });
  };

  // ==========================================================================
  // Toast Notifications
  // ==========================================================================

  RDAS.showToast = function(type, title, message) {
    var iconMap = {
      success: 'fa-check',
      error: 'fa-times',
      warning: 'fa-exclamation',
      info: 'fa-info'
    };

    var toast = $('<div class="rdas-toast rdas-toast-' + type + '">' +
      '<div class="rdas-toast-icon">' +
        '<i class="fa ' + iconMap[type] + '"></i>' +
      '</div>' +
      '<div class="rdas-toast-content">' +
        '<div class="rdas-toast-title">' + title + '</div>' +
        '<div class="rdas-toast-message">' + message + '</div>' +
      '</div>' +
      '<button class="rdas-toast-close">' +
        '<i class="fa fa-times"></i>' +
      '</button>' +
    '</div>');

    $('.rdas-toast-container').append(toast);

    // Auto remove after 5 seconds
    setTimeout(function() {
      toast.fadeOut(200, function() {
        $(this).remove();
      });
    }, 5000);
  };

  // ==========================================================================
  // AJAX Helper
  // ==========================================================================

  RDAS.ajaxRequest = function(options) {
    var defaults = {
      url: this.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: {
        action: 'ajax'
      },
      beforeSend: function() {
        RDAS.isLoading = true;
      },
      complete: function() {
        RDAS.isLoading = false;
      },
      error: function(xhr, status, error) {
        console.error('RDAS AJAX Error:', error);
        RDAS.showToast('error', 'Request Failed', 'An error occurred. Please try again.');
      }
    };

    var settings = $.extend({}, defaults, options);

    // Add operation to data (action is already set to 'ajax')
    if (settings.action) {
      settings.data.operation = settings.action;
      delete settings.action;
    }

    // Add nonce for security
    if (this.nonce) {
      settings.data.nonce = this.nonce;
    }

    return $.ajax(settings);
  };

  // ==========================================================================
  // Utility Functions
  // ==========================================================================

  RDAS.debounce = function(func, wait) {
    var timeout;
    return function() {
      var context = this, args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function() {
        func.apply(context, args);
      }, wait);
    };
  };

  RDAS.formatCurrency = function(amount) {
    return 'Rp ' + parseFloat(amount).toLocaleString('id-ID');
  };

  RDAS.formatDate = function(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Promo countdown timer
  RDAS.initPromoCountdown = function(endDate, containerId) {
    var container = $('#' + containerId);
    var endTime = new Date(endDate).getTime();

    function updateTimer() {
      var now = new Date().getTime();
      var distance = endTime - now;

      if (distance < 0) {
        container.html('Promo Ended');
        return;
      }

      var days = Math.floor(distance / (1000 * 60 * 60 * 24));
      var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      var seconds = Math.floor((distance % (1000 * 60)) / 1000);

      container.html(
        '<span class="rdas-promo-timer-item"><span class="rdas-promo-timer-value">' + days + '</span><span class="rdas-promo-timer-label">Days</span></span>' +
        '<span class="rdas-promo-timer-item"><span class="rdas-promo-timer-value">' + hours + '</span><span class="rdas-promo-timer-label">Hours</span></span>' +
        '<span class="rdas-promo-timer-item"><span class="rdas-promo-timer-value">' + minutes + '</span><span class="rdas-promo-timer-label">Min</span></span>' +
        '<span class="rdas-promo-timer-item"><span class="rdas-promo-timer-value">' + seconds + '</span><span class="rdas-promo-timer-label">Sec</span></span>'
      );
    }

    updateTimer();
    setInterval(updateTimer, 1000);
  };

  // Expose RDAS to global scope
  window.RDAS = RDAS;

})(jQuery);

// CSS animation classes
document.addEventListener('DOMContentLoaded', function() {
  // Add visible class to animated elements
  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('rdas-visible');
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.rdas-stat-card, .rdas-card').forEach(function(el) {
    observer.observe(el);
  });
});
