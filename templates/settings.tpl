<div class="rdas-settings-container">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-cogs"></i> RDAS Pricing Updater Settings
            </h3>
            <div class="panel-actions">
                <button type="button" class="btn btn-sm btn-info" id="test-api-connection">
                    <i class="fa fa-plug"></i> Test API Connection
                </button>
                <button type="button" class="btn btn-sm btn-warning" id="reset-settings">
                    <i class="fa fa-refresh"></i> Reset to Defaults
                </button>
            </div>
        </div>
        
        <div class="panel-body">
            <form method="post" action="{$modulelink}&action=settings" id="settings-form">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                
                <!-- API Configuration -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-cloud"></i> API Configuration
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="api_url">API URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" id="api_url" name="api_url" value="{$settings.api_url|default:'https://rdash.id/api/domain-pricing'}" required>
                                    <small class="help-block">RDASH.ID API endpoint for domain pricing data</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="api_key">API Key <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="api_key" name="api_key" value="{$settings.api_key}" required>
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" id="toggle-api-key">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <small class="help-block">Your RDASH.ID API authentication key</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="api_timeout">API Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="api_timeout" name="api_timeout" value="{$settings.api_timeout|default:30}" min="5" max="120">
                                    <small class="help-block">Maximum time to wait for API response</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="api_retry_attempts">Retry Attempts</label>
                                    <input type="number" class="form-control" id="api_retry_attempts" name="api_retry_attempts" value="{$settings.api_retry_attempts|default:3}" min="1" max="10">
                                    <small class="help-block">Number of retry attempts on API failure</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Margin Configuration -->
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
                                    <label for="margin_type">Margin Type</label>
                                    <select class="form-control" id="margin_type" name="margin_type">
                                        <option value="percentage" {if $settings.margin_type == 'percentage' || !$settings.margin_type}selected{/if}>Percentage (%)</option>
                                        <option value="fixed" {if $settings.margin_type == 'fixed'}selected{/if}>Fixed Amount (IDR)</option>
                                        <option value="tiered" {if $settings.margin_type == 'tiered'}selected{/if}>Tiered Pricing</option>
                                    </select>
                                    <small class="help-block">How to calculate profit margin</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="default_margin">Default Margin</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="default_margin" name="default_margin" value="{$settings.default_margin|default:20}" min="0" step="0.01">
                                        <span class="input-group-addon" id="margin-unit">%</span>
                                    </div>
                                    <small class="help-block">Default margin for all domains</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="minimum_margin">Minimum Margin</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="minimum_margin" name="minimum_margin" value="{$settings.minimum_margin|default:5}" min="0" step="0.01">
                                        <span class="input-group-addon" id="min-margin-unit">%</span>
                                    </div>
                                    <small class="help-block">Minimum allowed margin</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tiered Pricing Configuration -->
                        <div id="tiered-pricing-config" style="display: {if $settings.margin_type == 'tiered'}block{else}none{/if};">
                            <h5>Tiered Pricing Rules</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tiered-pricing-table">
                                    <thead>
                                        <tr>
                                            <th>Price Range (IDR)</th>
                                            <th>Margin Type</th>
                                            <th>Margin Value</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {if $settings.tiered_pricing && count($settings.tiered_pricing) > 0}
                                            {foreach $settings.tiered_pricing as $tier}
                                            <tr>
                                                <td>
                                                    <div class="row">
                                                        <div class="col-xs-5">
                                                            <input type="number" class="form-control input-sm" name="tiered_pricing[{$tier@index}][min_price]" value="{$tier.min_price}" placeholder="Min">
                                                        </div>
                                                        <div class="col-xs-2 text-center">to</div>
                                                        <div class="col-xs-5">
                                                            <input type="number" class="form-control input-sm" name="tiered_pricing[{$tier@index}][max_price]" value="{$tier.max_price}" placeholder="Max">
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <select class="form-control input-sm" name="tiered_pricing[{$tier@index}][type]">
                                                        <option value="percentage" {if $tier.type == 'percentage'}selected{/if}>Percentage</option>
                                                        <option value="fixed" {if $tier.type == 'fixed'}selected{/if}>Fixed</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control input-sm" name="tiered_pricing[{$tier@index}][value]" value="{$tier.value}" step="0.01">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger remove-tier">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            {/foreach}
                                        {else}
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No tiered pricing rules configured</td>
                                            </tr>
                                        {/if}
                                    </tbody>
                                </table>
                                <button type="button" class="btn btn-sm btn-success" id="add-tier">
                                    <i class="fa fa-plus"></i> Add Pricing Tier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rounding Configuration -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-calculator"></i> Rounding Configuration
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="rounding_rule">Rounding Rule</label>
                                    <select class="form-control" id="rounding_rule" name="rounding_rule">
                                        <option value="none" {if $settings.rounding_rule == 'none'}selected{/if}>No Rounding</option>
                                        <option value="up_1000" {if $settings.rounding_rule == 'up_1000'}selected{/if}>Round Up to 1,000</option>
                                        <option value="up_5000" {if $settings.rounding_rule == 'up_5000'}selected{/if}>Round Up to 5,000</option>
                                        <option value="up_10000" {if $settings.rounding_rule == 'up_10000'}selected{/if}>Round Up to 10,000</option>
                                        <option value="nearest_1000" {if $settings.rounding_rule == 'nearest_1000'}selected{/if}>Round to Nearest 1,000</option>
                                        <option value="nearest_5000" {if $settings.rounding_rule == 'nearest_5000'}selected{/if}>Round to Nearest 5,000</option>
                                        <option value="custom" {if $settings.rounding_rule == 'custom'}selected{/if}>Custom Rounding</option>
                                    </select>
                                    <small class="help-block">How to round calculated prices</small>
                                </div>
                            </div>
                            <div class="col-md-4" id="custom-rounding-group" style="display: {if $settings.rounding_rule == 'custom'}block{else}none{/if};">
                                <div class="form-group">
                                    <label for="custom_rounding_value">Custom Rounding Value</label>
                                    <input type="number" class="form-control" id="custom_rounding_value" name="custom_rounding_value" value="{$settings.custom_rounding_value|default:1000}" min="1" step="1">
                                    <small class="help-block">Custom rounding increment (IDR)</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="rounding_direction">Rounding Direction</label>
                                    <select class="form-control" id="rounding_direction" name="rounding_direction">
                                        <option value="up" {if $settings.rounding_direction == 'up' || !$settings.rounding_direction}selected{/if}>Always Round Up</option>
                                        <option value="down" {if $settings.rounding_direction == 'down'}selected{/if}>Always Round Down</option>
                                        <option value="nearest" {if $settings.rounding_direction == 'nearest'}selected{/if}>Round to Nearest</option>
                                    </select>
                                    <small class="help-block">Direction for rounding calculations</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Rounding Preview -->
                        <div class="rounding-preview">
                            <h5>Rounding Preview</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="preview_price">Test Price (IDR)</label>
                                        <input type="number" class="form-control" id="preview_price" value="123456" step="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Rounded Price</label>
                                        <div class="form-control-static" id="rounded_preview">
                                            <strong>Rp 124,000</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Automation Settings -->
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
                                            <input type="checkbox" name="auto_update" value="1" {if $settings.auto_update}checked{/if}>
                                            <strong>Enable Automatic Price Updates</strong>
                                        </label>
                                        <small class="help-block">Automatically sync domain prices daily via cron job</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="auto_update_time">Update Time</label>
                                    <input type="time" class="form-control" id="auto_update_time" name="auto_update_time" value="{$settings.auto_update_time|default:'02:00'}">
                                    <small class="help-block">Daily update time (server timezone)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="auto_import_new_tlds" value="1" {if $settings.auto_import_new_tlds}checked{/if}>
                                            <strong>Auto Import New TLDs</strong>
                                        </label>
                                        <small class="help-block">Automatically import new TLDs from API</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="batch_size">Batch Size</label>
                                    <input type="number" class="form-control" id="batch_size" name="batch_size" value="{$settings.batch_size|default:50}" min="10" max="500">
                                    <small class="help-block">Number of domains to process per batch</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Logging & Notifications -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-bell"></i> Logging & Notifications
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="enable_logging" value="1" {if $settings.enable_logging}checked{/if}>
                                            <strong>Enable Activity Logging</strong>
                                        </label>
                                        <small class="help-block">Log all pricing update activities</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="log_retention_days">Log Retention (days)</label>
                                    <input type="number" class="form-control" id="log_retention_days" name="log_retention_days" value="{$settings.log_retention_days|default:30}" min="1" max="365">
                                    <small class="help-block">How long to keep log entries</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notification_email">Notification Email</label>
                                    <input type="email" class="form-control" id="notification_email" name="notification_email" value="{$settings.notification_email}">
                                    <small class="help-block">Email for update notifications</small>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="notify_on_errors" value="1" {if $settings.notify_on_errors}checked{/if}>
                                            <strong>Notify on Errors</strong>
                                        </label>
                                        <small class="help-block">Send email notifications for sync errors</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-cog"></i> Advanced Settings
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="currency">Base Currency</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="IDR" {if $settings.currency == 'IDR' || !$settings.currency}selected{/if}>Indonesian Rupiah (IDR)</option>
                                        <option value="USD" {if $settings.currency == 'USD'}selected{/if}>US Dollar (USD)</option>
                                        <option value="EUR" {if $settings.currency == 'EUR'}selected{/if}>Euro (EUR)</option>
                                        <option value="GBP" {if $settings.currency == 'GBP'}selected{/if}>British Pound (GBP)</option>
                                    </select>
                                    <small class="help-block">Base currency for pricing calculations</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="debug_mode" value="1" {if $settings.debug_mode}checked{/if}>
                                            <strong>Debug Mode</strong>
                                        </label>
                                        <small class="help-block">Enable detailed logging for troubleshooting</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cache_duration">Cache Duration (hours)</label>
                                    <input type="number" class="form-control" id="cache_duration" name="cache_duration" value="{$settings.cache_duration|default:24}" min="1" max="168">
                                    <small class="help-block">How long to cache API responses</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="validate_registrar" value="1" {if $settings.validate_registrar}checked{/if}>
                                            <strong>Validate Registrar Support</strong>
                                        </label>
                                        <small class="help-block">Only sync TLDs supported by active registrars</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-success btn-lg" id="save-and-test">
                        <i class="fa fa-check"></i> Save & Test API
                    </button>
                    <a href="{$modulelink}" class="btn btn-default btn-lg">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Pricing Settings</h3>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="default_margin_type" class="col-sm-3 control-label">Margin Type</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="default_margin_type" name="default_margin_type">
                            <option value="percentage" {if $settings.default_margin_type eq "percentage"}selected{/if}>
                                Percentage (%)
                            </option>
                            <option value="fixed" {if $settings.default_margin_type eq "fixed"}selected{/if}>
                                Fixed Amount (IDR)
                            </option>
                        </select>
                        <p class="help-block">Choose how to apply profit margin</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="default_margin_value" class="col-sm-3 control-label">Margin Value</label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <input type="number" class="form-control" id="default_margin_value"
                                   name="default_margin_value" value="{$settings.default_margin_value}"
                                   step="0.01" min="0">
                            <span class="input-group-addon margin-type-label">
                                {if $settings.default_margin_type eq "percentage"}%{else}IDR{/if}
                            </span>
                        </div>
                        <p class="help-block">Set the profit margin to apply to base prices</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="rounding_option" class="col-sm-3 control-label">Round To</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="rounding_option" name="rounding_option">
                            <option value="none" {if $settings.rounding_option eq "none"}selected{/if}>
                                No Rounding
                            </option>
                            <option value="up_1000" {if $settings.rounding_option eq "up_1000"}selected{/if}>
                                Round Up to 1,000
                            </option>
                            <option value="up_5000" {if $settings.rounding_option eq "up_5000"}selected{/if}>
                                Round Up to 5,000
                            </option>
                            <option value="nearest_1000" {if $settings.rounding_option eq "nearest_1000"}selected{/if}>
                                Round to Nearest 1,000
                            </option>
                            <option value="custom" {if $settings.rounding_option eq "custom"}selected{/if}>
                                Custom Rounding
                            </option>
                        </select>
                        <p class="help-block">Select rounding method for final prices</p>
                    </div>
                </div>

                <div class="form-group custom-rounding-group" {if $settings.rounding_option neq "custom"}style="display:none"{/if}>
                    <label for="custom_rounding" class="col-sm-3 control-label">Custom Rounding Value</label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <input type="number" class="form-control" id="custom_rounding"
                                   name="custom_rounding" value="{$settings.custom_rounding}"
                                   step="100" min="100">
                            <span class="input-group-addon">IDR</span>
                        </div>
                        <p class="help-block">Round up to nearest multiple of this value</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Update Settings</h3>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="update_frequency" class="col-sm-3 control-label">Auto-Update Frequency</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="update_frequency" name="update_frequency">
                            <option value="manual" {if $settings.update_frequency eq "manual"}selected{/if}>
                                Manual Only
                            </option>
                            <option value="daily" {if $settings.update_frequency eq "daily"}selected{/if}>
                                Daily
                            </option>
                            <option value="weekly" {if $settings.update_frequency eq "weekly"}selected{/if}>
                                Weekly
                            </option>
                        </select>
                        <p class="help-block">How often to automatically update prices</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="log_level" class="col-sm-3 control-label">Log Level</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="log_level" name="log_level">
                            <option value="none" {if $settings.log_level eq "none"}selected{/if}>
                                No Logging
                            </option>
                            <option value="errors" {if $settings.log_level eq "errors"}selected{/if}>
                                Errors Only
                            </option>
                            <option value="all" {if $settings.log_level eq "all"}selected{/if}>
                                All Events
                            </option>
                        </select>
                        <p class="help-block">Detail level for activity logging</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Price Calculation Preview</h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="preview_price" class="col-sm-4 control-label">Base Price</label>
                            <div class="col-sm-8">
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input type="number" class="form-control" id="preview_price"
                                           name="preview_price" value="199000"
                                           step="1000" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <button type="button" id="calculatePreview" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> Calculate
                        </button>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="panel panel-info">
                            <div class="panel-heading">Base Price</div>
                            <div class="panel-body text-center">
                                <h3 id="preview_base">Rp 199,000</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="panel panel-warning">
                            <div class="panel-heading">After Margin</div>
                            <div class="panel-body text-center">
                                <h3 id="preview_margin">Rp 238,800</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="panel panel-success">
                            <div class="panel-heading">Final Price</div>
                            <div class="panel-body text-center">
                                <h3 id="preview_final">Rp 239,000</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center margin-bottom-20">
            <button type="submit" class="btn btn-lg btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <button type="button" id="updatePricesNowBtn" class="btn btn-lg btn-success margin-left-10">
                <i class="fas fa-sync"></i> Update Prices Now
            </button>
        </div>
    </form>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Update History</h3>
        </div>
        <div class="panel-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Updated TLDs</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    {if count($updateHistory) > 0}
                        {foreach from=$updateHistory item=history}
                            <tr>
                                <td>{$history.timestamp}</td>
                                <td>{$history.updated}</td>
                                <td>
                                    {if $history.status eq 'success'}
                                        <span class="label label-success">Success</span>
                                    {elseif $history.status eq 'warning'}
                                        <span class="label label-warning">Warning</span>
                                    {else}
                                        <span class="label label-danger">Error</span>
                                    {/if}
                                </td>
                                <td>{$history.message}</td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="4" class="text-center">No update history available</td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function() {
    // Update margin label when type changes
    jQuery('#default_margin_type').change(function() {
        var marginType = jQuery(this).val();
        if (marginType === 'percentage') {
            jQuery('.margin-type-label').text('%');
        } else {
            jQuery('.margin-type-label').text('IDR');
        }
    });

    // Show/hide custom rounding field
    jQuery('#rounding_option').change(function() {
        if (jQuery(this).val() === 'custom') {
            jQuery('.custom-rounding-group').show();
        } else {
            jQuery('.custom-rounding-group').hide();
        }
    });

    // Price preview calculation
    jQuery('#calculatePreview').click(function() {
        // Get form values
        var basePrice = parseFloat(jQuery('#preview_price').val()) || 0;
        var marginType = jQuery('#default_margin_type').val();
        var marginValue = parseFloat(jQuery('#default_margin_value').val()) || 0;
        var roundingOption = jQuery('#rounding_option').val();
        var customRounding = parseFloat(jQuery('#custom_rounding').val()) || 1000;

        // Calculate with margin
        var priceWithMargin = basePrice;
        if (marginType === 'percentage') {
            priceWithMargin = basePrice * (1 + (marginValue / 100));
        } else {
            priceWithMargin = basePrice + marginValue;
        }

        // Apply rounding
        var finalPrice = priceWithMargin;
        switch (roundingOption) {
            case 'up_1000':
                finalPrice = Math.ceil(priceWithMargin / 1000) * 1000;
                break;

            case 'up_5000':
                finalPrice = Math.ceil(priceWithMargin / 5000) * 5000;
                break;

            case 'nearest_1000':
                finalPrice = Math.round(priceWithMargin / 1000) * 1000;
                break;

            case 'custom':
                finalPrice = Math.ceil(priceWithMargin / customRounding) * customRounding;
                break;
        }

        // Update preview panels
        jQuery('#preview_base').text('Rp ' + basePrice.toLocaleString('id-ID'));
        jQuery('#preview_margin').text('Rp ' + priceWithMargin.toLocaleString('id-ID', {
            maximumFractionDigits: 2
        }));
        jQuery('#preview_final').text('Rp ' + finalPrice.toLocaleString('id-ID'));
    });

    // Test API connection
    jQuery('#testApiBtn').click(function() {
        var btn = jQuery(this);
        var result = jQuery('#apiTestResult');

        btn.attr('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        result.removeClass().text('');

        // Make AJAX request to test API
        WHMCS.http.jqClient.post(
            '{$modulelink}&action=api_test&ajax=1',
            {},
            function(data) {
                btn.attr('disabled', false).html('<i class="fas fa-plug"></i> Test API Connection');

                if (data.status === 'success') {
                    result.addClass('text-success').html('<i class="fas fa-check-circle"></i> ' + data.message);
                } else {
                    result.addClass('text-danger').html('<i class="fas fa-exclamation-circle"></i> ' + data.message);
                }
            },
            'json'
        ).fail(function() {
            btn.attr('disabled', false).html('<i class="fas fa-plug"></i> Test API Connection');
            result.addClass('text-danger').html('<i class="fas fa-exclamation-circle"></i> Connection failed');
        });
    });

    // Update prices now button
    jQuery('#updatePricesNowBtn').click(function() {
        if (confirm('Are you sure you want to update domain prices now?')) {
            var btn = jQuery(this);

            btn.attr('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

            // Make AJAX request to update prices
            WHMCS.http.jqClient.post(
                '{$modulelink}&action=pricing&ajax=1',
                {
                    action: 'update_prices'
                },
                function(data) {
                    btn.attr('disabled', false).html('<i class="fas fa-sync"></i> Update Prices Now');

                    if (data.status === 'success') {
                        alert('Success: ' + data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                },
                'json'
            ).fail(function() {
                btn.attr('disabled', false).html('<i class="fas fa-sync"></i> Update Prices Now');
                alert('Connection failed. Please try again.');
            });
        }
    });

    // Run calculation on page load
    jQuery('#calculatePreview').click();
});
</script>
