<?php
/**
 * RDAS Pricing Updater - Settings Page
 *
 * @package RDAS Pricing Updater
 * @version 2.0.0
 * @author Sadewa
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once dirname(__DIR__) . '/lib/functions.php';

/**
 * Show Settings Page
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showSettingsPage($vars) {
    try {
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
            $result = saveAddonSettings($_POST);
            if ($result['success']) {
                $success_message = 'Settings saved successfully!';
            } else {
                $error_message = $result['message'];
            }
        }

        // Get current settings
        $settings = getAddonSettings();

        // Prepare template variables
        $template_vars = array(
            'modulelink' => $vars['modulelink'],
            'settings' => $settings,
            'success_message' => isset($success_message) ? $success_message : '',
            'error_message' => isset($error_message) ? $error_message : '',
            'csrf_token' => rdasGenerateCSRFToken('settings')
        );

        // Load settings template
        return renderSettingsTemplate($template_vars);

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('RDAS Settings Page Error: ' . $e->getMessage());
        }
        return '<div class="alert alert-danger">Error loading settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Get addon settings
 *
 * @return array Current settings
 */
function getAddonSettings() {
    $defaults = array(
        'api_url' => 'https://api.rdash.id/api/domain-prices?currency=IDR',
        'api_key' => '',
        'api_timeout' => 30,
        'margin_type' => 'percentage',
        'default_margin' => 20,
        'minimum_margin' => 5,
        'rounding_rule' => 'up_1000',
        'custom_rounding_value' => 1000,
        'auto_update_enabled' => false,
        'auto_update_time' => '02:00',
        'batch_size' => 50,
        'log_retention_days' => 30,
        'notification_email' => '',
        'log_level' => 'info'
    );

    // Get from WHMCS addon settings
    $config = rdasGetAddonConfig('rdas_pricing_updater');

    return array_merge($defaults, $config);
}

/**
 * Save addon settings
 *
 * @param array $post_data POST data
 * @return array Result array
 */
function saveAddonSettings($post_data) {
    try {
        // Validate CSRF token
        if (!isset($post_data['csrf_token']) || !rdasValidateCSRFToken($post_data['csrf_token'], 'settings')) {
            return array('success' => false, 'message' => 'Invalid CSRF token');
        }

        // Define settings to save
        $settings_to_save = array(
            'api_url', 'api_key', 'api_timeout', 'api_retry_attempts',
            'margin_type', 'default_margin', 'minimum_margin',
            'rounding_rule', 'custom_rounding_value',
            'auto_update_enabled', 'auto_update_time', 'batch_size',
            'log_retention_days', 'notification_email', 'log_level'
        );

        // Validate and save each setting
        foreach ($settings_to_save as $setting) {
            if (isset($post_data[$setting])) {
                $value = $post_data[$setting];

                // Validate specific settings
                switch ($setting) {
                    case 'api_timeout':
                    case 'api_retry_attempts':
                    case 'default_margin':
                    case 'minimum_margin':
                    case 'custom_rounding_value':
                    case 'batch_size':
                    case 'log_retention_days':
                        $value = max(0, (int)$value);
                        break;

                    case 'api_url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            return array('success' => false, 'message' => 'Invalid API URL');
                        }
                        break;

                    case 'notification_email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            return array('success' => false, 'message' => 'Invalid notification email');
                        }
                        break;

                    case 'auto_update_time':
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                            return array('success' => false, 'message' => 'Invalid time format (use HH:MM)');
                        }
                        break;

                    case 'rounding_rule':
                        // Normalize rounding rule values
                        $validRules = ['none', 'up_1000', 'up_5000', 'nearest_1000', 'custom'];
                        if (!in_array($value, $validRules)) {
                            $value = 'up_1000'; // Default
                        }
                        break;
                }

                saveAddonConfigValue($setting, $value);
            }
        }

        // Log settings update
        if (function_exists('logActivity')) {
            logActivity('RDAS Pricing Updater settings updated');
        }

        return array('success' => true, 'message' => 'Settings saved successfully');

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error saving RDAS settings: ' . $e->getMessage());
        }
        return array('success' => false, 'message' => 'Error saving settings: ' . $e->getMessage());
    }
}

/**
 * Save addon config value (fallback method)
 *
 * @param string $key Setting key
 * @param mixed $value Setting value
 */
function saveAddonConfigValue($key, $value) {
    try {
        // Use native WHMCS database functions for compatibility
        $existing = select_query('tbladdonmodules', 'id', array(
            'module' => 'rdas_pricing_updater',
            'setting' => $key
        ));

        if (mysql_num_rows($existing) > 0) {
            update_query('tbladdonmodules', array('value' => $value), array(
                'module' => 'rdas_pricing_updater',
                'setting' => $key
            ));
        } else {
            insert_query('tbladdonmodules', array(
                'module' => 'rdas_pricing_updater',
                'setting' => $key,
                'value' => $value
            ));
        }
    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error saving config value: ' . $e->getMessage());
        }
    }
}

/**
 * Render settings template
 *
 * @param array $vars Template variables
 * @return string Rendered HTML
 */
function renderSettingsTemplate($vars) {
    ob_start();
    ?>
    <div class="rdas-pricing-updater">
        <!-- Page Header -->
        <div class="rdas-page-header">
            <h1 class="rdas-page-title">
                <div class="rdas-page-title-icon">
                    <i class="fa fa-cogs"></i>
                </div>
                Settings
            </h1>
            <div class="rdas-page-actions">
                <button type="button" class="rdas-btn rdas-btn-ghost rdas-theme-toggle" title="Toggle Dark Mode">
                    <i class="fa fa-moon"></i>
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <nav class="rdas-nav-tabs">
            <a href="<?php echo $vars['modulelink']; ?>&page=dashboard" class="rdas-nav-tab">
                <i class="fa fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=pricing" class="rdas-nav-tab">
                <i class="fa fa-list-alt"></i>
                <span>Pricing</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=settings" class="rdas-nav-tab active">
                <i class="fa fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=api_test" class="rdas-nav-tab">
                <i class="fa fa-plug"></i>
                <span>API Test</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=logs" class="rdas-nav-tab">
                <i class="fa fa-history"></i>
                <span>Logs</span>
            </a>
        </nav>

        <!-- Quick Actions -->
        <div class="rdas-quick-actions">
            <button type="button" class="rdas-btn rdas-btn-secondary" id="test-api-btn">
                <i class="fa fa-plug"></i> Test API Connection
            </button>
            <button type="button" class="rdas-btn rdas-btn-secondary" id="reset-settings-btn">
                <i class="fa fa-refresh"></i> Reset to Defaults
            </button>
        </div>

        <div class="rdas-settings-container">

        <!-- Messages -->
        <?php if (!empty($vars['success_message'])): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-check"></i> <?php echo htmlspecialchars($vars['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($vars['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($vars['error_message']); ?>
        </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="post" action="<?php echo $vars['modulelink']; ?>&page=settings" id="settings-form">
            <input type="hidden" name="csrf_token" value="<?php echo $vars['csrf_token']; ?>">
            <input type="hidden" name="save_settings" value="1">

            <!-- API Configuration -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-plug"></i> API Configuration
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="api_url">API URL:</label>
                                        <input type="url" class="form-control" id="api_url" name="api_url"
                                               value="<?php echo htmlspecialchars($vars['settings']['api_url']); ?>" required>
                                        <small class="help-block">RDASH.ID API endpoint URL</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="api_key">API Key:</label>
                                        <input type="password" class="form-control" id="api_key" name="api_key"
                                               value="<?php echo htmlspecialchars($vars['settings']['api_key']); ?>">
                                        <small class="help-block">Your RDASH.ID API key</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="api_timeout">API Timeout (seconds):</label>
                                        <input type="number" class="form-control" id="api_timeout" name="api_timeout"
                                               value="<?php echo $vars['settings']['api_timeout']; ?>" min="5" max="300">
                                        <small class="help-block">Request timeout in seconds</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="api_retry_attempts">Retry Attempts:</label>
                                        <input type="number" class="form-control" id="api_retry_attempts" name="api_retry_attempts"
                                               value="<?php echo $vars['settings']['api_retry_attempts']; ?>" min="1" max="10">
                                        <small class="help-block">Number of retry attempts on failure</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Margin Configuration -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-percent"></i> Margin Configuration
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="margin_type">Margin Type:</label>
                                        <select class="form-control" id="margin_type" name="margin_type">
                                            <option value="percentage" <?php echo $vars['settings']['margin_type'] == 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                            <option value="fixed" <?php echo $vars['settings']['margin_type'] == 'fixed' ? 'selected' : ''; ?>>Fixed Amount (Rp)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="default_margin">Default Margin:</label>
                                        <input type="number" class="form-control" id="default_margin" name="default_margin"
                                               value="<?php echo $vars['settings']['default_margin']; ?>" step="0.01">
                                        <small class="help-block">Default margin for all domains</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="minimum_margin">Minimum Margin:</label>
                                        <input type="number" class="form-control" id="minimum_margin" name="minimum_margin"
                                               value="<?php echo $vars['settings']['minimum_margin']; ?>" step="0.01">
                                        <small class="help-block">Minimum allowed margin</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Tiered Pricing -->
                            <div class="form-group">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="tiered_pricing_enabled" name="tiered_pricing_enabled" value="1"
                                               <?php echo $vars['settings']['tiered_pricing_enabled'] ? 'checked' : ''; ?>>
                                        Enable Tiered Pricing
                                    </label>
                                </div>
                            </div>

                            <div id="tiered-pricing-config" style="<?php echo $vars['settings']['tiered_pricing_enabled'] ? '' : 'display: none;'; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tier_1_threshold">Tier 1 Threshold (Rp):</label>
                                            <input type="number" class="form-control" id="tier_1_threshold" name="tier_1_threshold"
                                                   value="<?php echo $vars['settings']['tier_1_threshold']; ?>">
                                            <small class="help-block">Price threshold for tier 1 margin</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tier_1_margin">Tier 1 Margin:</label>
                                            <input type="number" class="form-control" id="tier_1_margin" name="tier_1_margin"
                                                   value="<?php echo $vars['settings']['tier_1_margin']; ?>" step="0.01">
                                            <small class="help-block">Margin for prices below tier 1 threshold</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tier_2_threshold">Tier 2 Threshold (Rp):</label>
                                            <input type="number" class="form-control" id="tier_2_threshold" name="tier_2_threshold"
                                                   value="<?php echo $vars['settings']['tier_2_threshold']; ?>">
                                            <small class="help-block">Price threshold for tier 2 margin</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tier_2_margin">Tier 2 Margin:</label>
                                            <input type="number" class="form-control" id="tier_2_margin" name="tier_2_margin"
                                                   value="<?php echo $vars['settings']['tier_2_margin']; ?>" step="0.01">
                                            <small class="help-block">Margin for prices above tier 2 threshold</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rounding Configuration -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-calculator"></i> Rounding Configuration
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" id="rounding_enabled" name="rounding_enabled" value="1"
                                               <?php echo $vars['settings']['rounding_enabled'] ? 'checked' : ''; ?>>
                                        Enable Price Rounding
                                    </label>
                                </div>
                            </div>

                            <div id="rounding-config" style="<?php echo $vars['settings']['rounding_enabled'] ? '' : 'display: none;'; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="rounding_rule">Rounding Rule:</label>
                                            <select class="form-control" id="rounding_rule" name="rounding_rule">
                                                <option value="nearest_thousand" <?php echo $vars['settings']['rounding_rule'] == 'nearest_thousand' ? 'selected' : ''; ?>>Nearest Thousand</option>
                                                <option value="nearest_hundred" <?php echo $vars['settings']['rounding_rule'] == 'nearest_hundred' ? 'selected' : ''; ?>>Nearest Hundred</option>
                                                <option value="custom" <?php echo $vars['settings']['rounding_rule'] == 'custom' ? 'selected' : ''; ?>>Custom Value</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="custom_rounding_value">Custom Rounding Value:</label>
                                            <input type="number" class="form-control" id="custom_rounding_value" name="custom_rounding_value"
                                                   value="<?php echo $vars['settings']['custom_rounding_value']; ?>">
                                            <small class="help-block">Used when rounding rule is 'custom'</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="rounding_direction">Rounding Direction:</label>
                                            <select class="form-control" id="rounding_direction" name="rounding_direction">
                                                <option value="up" <?php echo $vars['settings']['rounding_direction'] == 'up' ? 'selected' : ''; ?>>Round Up</option>
                                                <option value="down" <?php echo $vars['settings']['rounding_direction'] == 'down' ? 'selected' : ''; ?>>Round Down</option>
                                                <option value="nearest" <?php echo $vars['settings']['rounding_direction'] == 'nearest' ? 'selected' : ''; ?>>Round to Nearest</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automation Settings -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-clock-o"></i> Automation Settings
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="auto_update_enabled" name="auto_update_enabled" value="1"
                                                       <?php echo $vars['settings']['auto_update_enabled'] ? 'checked' : ''; ?>>
                                                Enable Automatic Updates
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="auto_update_time">Update Time:</label>
                                        <input type="time" class="form-control" id="auto_update_time" name="auto_update_time"
                                               value="<?php echo $vars['settings']['auto_update_time']; ?>">
                                        <small class="help-block">Daily update time (24-hour format)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="new_tld_import" name="new_tld_import" value="1"
                                                       <?php echo $vars['settings']['new_tld_import'] ? 'checked' : ''; ?>>
                                                Auto-import New TLDs
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="batch_size">Batch Size:</label>
                                        <input type="number" class="form-control" id="batch_size" name="batch_size"
                                               value="<?php echo $vars['settings']['batch_size']; ?>" min="10" max="500">
                                        <small class="help-block">Number of domains to process per batch</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logging and Notifications -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-bell"></i> Logging and Notifications
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="logging_enabled" name="logging_enabled" value="1"
                                                       <?php echo $vars['settings']['logging_enabled'] ? 'checked' : ''; ?>>
                                                Enable Logging
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="log_retention_days">Log Retention (days):</label>
                                        <input type="number" class="form-control" id="log_retention_days" name="log_retention_days"
                                               value="<?php echo $vars['settings']['log_retention_days']; ?>" min="1" max="365">
                                        <small class="help-block">Number of days to keep logs</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1"
                                                       <?php echo $vars['settings']['email_notifications'] ? 'checked' : ''; ?>>
                                                Enable Email Notifications
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="notification_email">Notification Email:</label>
                                        <input type="email" class="form-control" id="notification_email" name="notification_email"
                                               value="<?php echo htmlspecialchars($vars['settings']['notification_email']); ?>">
                                        <small class="help-block">Email address for notifications</small>
                                    </div>

                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="error_notifications" name="error_notifications" value="1"
                                                       <?php echo $vars['settings']['error_notifications'] ? 'checked' : ''; ?>>
                                                Send Error Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <i class="fa fa-cog"></i> Advanced Settings
                            </h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="base_currency">Base Currency:</label>
                                        <select class="form-control" id="base_currency" name="base_currency">
                                            <option value="IDR" <?php echo $vars['settings']['base_currency'] == 'IDR' ? 'selected' : ''; ?>>Indonesian Rupiah (IDR)</option>
                                            <option value="USD" <?php echo $vars['settings']['base_currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                            <option value="EUR" <?php echo $vars['settings']['base_currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="cache_duration">Cache Duration (seconds):</label>
                                        <input type="number" class="form-control" id="cache_duration" name="cache_duration"
                                               value="<?php echo $vars['settings']['cache_duration']; ?>" min="300" max="86400">
                                        <small class="help-block">API response cache duration</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="debug_mode" name="debug_mode" value="1"
                                                       <?php echo $vars['settings']['debug_mode'] ? 'checked' : ''; ?>>
                                                Debug Mode
                                            </label>
                                        </div>
                                        <small class="help-block">Enable detailed logging</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" id="registrar_validation" name="registrar_validation" value="1"
                                                       <?php echo $vars['settings']['registrar_validation'] ? 'checked' : ''; ?>>
                                                Registrar Validation
                                            </label>
                                        </div>
                                        <small class="help-block">Validate registrar support</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-body text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                            <button type="button" class="btn btn-default btn-lg" id="cancel-btn">
                                <i class="fa fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Toggle tiered pricing configuration
        $('#tiered_pricing_enabled').change(function() {
            if ($(this).is(':checked')) {
                $('#tiered-pricing-config').show();
            } else {
                $('#tiered-pricing-config').hide();
            }
        });

        // Toggle rounding configuration
        $('#rounding_enabled').change(function() {
            if ($(this).is(':checked')) {
                $('#rounding-config').show();
            } else {
                $('#rounding-config').hide();
            }
        });

        // Test API connection
        $('#test-api-btn').click(function() {
            var btn = $(this);
            var apiUrl = $('#api_url').val();
            var apiKey = $('#api_key').val();

            if (!apiUrl) {
                alert('Please enter API URL first');
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'test_api_connection',
                api_url: apiUrl,
                api_key: apiKey,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test API Connection');

                if (response.success) {
                    alert('API connection successful!');
                } else {
                    alert('API connection failed: ' + response.message);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test API Connection');
                alert('API test failed: Network error');
            });
        });

        // Reset settings
        $('#reset-settings-btn').click(function() {
            if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
                $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                    operation: 'reset_settings',
                    csrf_token: '<?php echo $vars['csrf_token']; ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Settings reset successfully!');
                        location.reload();
                    } else {
                        alert('Error resetting settings: ' + response.message);
                    }
                }, 'json');
            }
        });

        // Cancel button
        $('#cancel-btn').click(function() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                window.location.href = '<?php echo $vars['modulelink']; ?>';
            }
        });

        // Theme toggle
        $('.rdas-theme-toggle').on('click', function() {
            $('.rdas-pricing-updater').toggleClass('rdas-dark');
            var icon = $(this).find('i');
            if ($('.rdas-pricing-updater').hasClass('rdas-dark')) {
                icon.removeClass('fa-moon').addClass('fa-sun');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
            }
        });

        // Form validation
        $('#settings-form').submit(function(e) {
            var apiUrl = $('#api_url').val();
            var notificationEmail = $('#notification_email').val();
            var emailNotifications = $('#email_notifications').is(':checked');

            if (!apiUrl) {
                alert('API URL is required');
                e.preventDefault();
                return false;
            }

            if (emailNotifications && !notificationEmail) {
                alert('Notification email is required when email notifications are enabled');
                e.preventDefault();
                return false;
            }

            return true;
        });
    });
    </script>

    <style>
    .rdas-settings-container .panel-actions {
        float: right;
        margin-top: -5px;
    }

    .form-group .checkbox {
        margin-top: 0;
        margin-bottom: 10px;
    }

    .help-block {
        color: #737373;
        font-size: 1.2rem;
    }

    .panel-title {
        font-size: 1.6rem;
        font-weight: bold;
    }

    .btn-lg {
        padding: 10px 30px;
        font-size: 1.6rem;
    }
    </style>
    <?php
    return ob_get_clean();
}
