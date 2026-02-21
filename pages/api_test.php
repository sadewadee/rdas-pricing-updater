<?php
/**
 * RDAS Pricing Updater - API Test Page
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
 * Show API Test Page
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showApiTestPage($vars) {
    try {
        $settings = getApiTestSettings();

        $template_vars = array(
            'modulelink' => $vars['modulelink'],
            'settings' => $settings,
            'csrf_token' => rdasGenerateCSRFToken('api_test')
        );

        return renderApiTestTemplate($template_vars);

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('RDAS API Test Page Error: ' . $e->getMessage());
        }
        return '<div class="alert alert-danger">Error loading API test page: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Get API test settings
 *
 * @return array Current API settings
 */
function getApiTestSettings() {
    $defaults = array(
        'api_url' => 'https://api.rdash.id/api/domain-prices?currency=IDR',
        'api_key' => '',
        'api_timeout' => 30
    );

    $config = rdasGetAddonConfig('rdas_pricing_updater');
    return array_merge($defaults, $config);
}

/**
 * Test API connection
 *
 * @param array $params Test parameters
 * @return array Test result
 */
function testApiConnection($params) {
    try {
        $api_url = $params['api_url'] ?? '';
        $api_key = $params['api_key'] ?? '';
        $timeout = max(5, (int)($params['timeout'] ?? 30));

        if (empty($api_url)) {
            return array('success' => false, 'message' => 'API URL is required');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        if (!empty($api_key)) {
            $headers[] = 'X-API-Key: ' . $api_key;
        }

        $result = rdasHttpRequest($api_url, [
            'timeout' => $timeout,
            'headers' => $headers
        ]);

        if (!$result['success']) {
            return array(
                'success' => false,
                'message' => $result['error'] ?? 'HTTP Error: ' . $result['http_code'],
                'details' => array(
                    'http_code' => $result['http_code'],
                    'error' => $result['error']
                )
            );
        }

        return array(
            'success' => true,
            'message' => 'API connection successful',
            'details' => array(
                'http_code' => $result['http_code'],
                'data_count' => is_array($result['data']) ? count($result['data']) : 0,
                'response_size' => strlen(json_encode($result['data']))
            ),
            'data' => $result['data']
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Render API test template
 *
 * @param array $vars Template variables
 * @return string Rendered HTML
 */
function renderApiTestTemplate($vars) {
    ob_start();
    ?>
    <div class="rdas-pricing-updater">
        <!-- Page Header -->
        <div class="rdas-page-header">
            <h1 class="rdas-page-title">
                <div class="rdas-page-title-icon">
                    <i class="fa fa-plug"></i>
                </div>
                API Test
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
            <a href="<?php echo $vars['modulelink']; ?>&page=settings" class="rdas-nav-tab">
                <i class="fa fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=api_test" class="rdas-nav-tab active">
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
            <button type="button" class="rdas-btn rdas-btn-secondary" id="load-settings-btn">
                <i class="fa fa-refresh"></i> Load Current Settings
            </button>
        </div>

        <div class="rdas-api-test-container">

        <!-- Test Form -->
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-cogs"></i> API Configuration
                        </h4>
                    </div>
                    <div class="panel-body">
                        <form id="api-test-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $vars['csrf_token']; ?>">

                            <div class="form-group">
                                <label for="api_url">API URL:</label>
                                <input type="url" class="form-control" id="api_url" name="api_url"
                                       value="<?php echo htmlspecialchars($vars['settings']['api_url']); ?>"
                                       placeholder="https://rdash.id/api/domain-prices" required>
                                <small class="help-block">RDASH.ID API endpoint URL</small>
                            </div>

                            <div class="form-group">
                                <label for="api_key">API Key:</label>
                                <input type="password" class="form-control" id="api_key" name="api_key"
                                       value="<?php echo htmlspecialchars($vars['settings']['api_key']); ?>"
                                       placeholder="Your API key">
                                <small class="help-block">Your RDASH.ID API key (optional for testing)</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="timeout">Timeout (seconds):</label>
                                        <input type="number" class="form-control" id="timeout" name="timeout"
                                               value="<?php echo $vars['settings']['api_timeout']; ?>"
                                               min="5" max="300">
                                        <small class="help-block">Request timeout in seconds</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="test_type">Test Type:</label>
                                        <select class="form-control" id="test_type" name="test_type">
                                            <option value="connection">Connection Test</option>
                                            <option value="data_fetch">Data Fetch Test</option>
                                            <option value="full_test">Full API Test</option>
                                        </select>
                                        <small class="help-block">Type of test to perform</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary btn-lg" id="test-api-btn">
                                    <i class="fa fa-play"></i> Run API Test
                                </button>
                                <button type="button" class="btn btn-default btn-lg" id="clear-results-btn">
                                    <i class="fa fa-trash"></i> Clear Results
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Test Status -->
            <div class="col-md-4">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-info-circle"></i> Test Status
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="test-status" class="text-center">
                            <p class="text-muted">
                                <i class="fa fa-clock-o fa-2x"></i><br>
                                Ready to test
                            </p>
                        </div>

                        <div id="test-progress" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    Testing...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-tachometer"></i> Quick Stats
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="stat-item">
                                    <div class="stat-value" id="response-time">-</div>
                                    <div class="stat-label">Response Time</div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="stat-item">
                                    <div class="stat-value" id="http-status">-</div>
                                    <div class="stat-label">HTTP Status</div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="stat-item">
                                    <div class="stat-value" id="data-count">-</div>
                                    <div class="stat-label">Data Count</div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="stat-item">
                                    <div class="stat-value" id="response-size">-</div>
                                    <div class="stat-label">Response Size</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default" id="results-panel" style="display: none;">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-list-alt"></i> Test Results
                        </h4>
                        <div class="panel-actions">
                            <button type="button" class="btn btn-sm btn-info" id="export-results-btn">
                                <i class="fa fa-download"></i> Export Results
                            </button>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div id="test-results"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sample Data Preview -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default" id="data-preview-panel" style="display: none;">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-eye"></i> Sample Data Preview
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="data-preview"></div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Load current settings
        $('#load-settings-btn').click(function() {
            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'get_api_settings',
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                if (response.success && response.settings) {
                    $('#api_url').val(response.settings.api_url || '');
                    $('#api_key').val(response.settings.api_key || '');
                    $('#timeout').val(response.settings.api_timeout || 30);
                }
            }, 'json');
        });

        // API test form submission
        $('#api-test-form').submit(function(e) {
            e.preventDefault();

            var formData = {
                operation: 'test_api_connection',
                api_url: $('#api_url').val(),
                api_key: $('#api_key').val(),
                timeout: $('#timeout').val(),
                test_type: $('#test_type').val(),
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            };

            // Validate form
            if (!formData.api_url) {
                alert('API URL is required');
                return;
            }

            // Show progress
            $('#test-status').hide();
            $('#test-progress').show();
            $('#test-api-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

            // Clear previous results
            clearTestResults();

            // Start timer
            var startTime = new Date().getTime();

            // Make AJAX request
            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', formData, function(response) {
                var endTime = new Date().getTime();
                var totalTime = endTime - startTime;

                // Hide progress
                $('#test-progress').hide();
                $('#test-api-btn').prop('disabled', false).html('<i class="fa fa-play"></i> Run API Test');

                // Show results
                displayTestResults(response, totalTime);

            }, 'json').fail(function(xhr, status, error) {
                // Hide progress
                $('#test-progress').hide();
                $('#test-api-btn').prop('disabled', false).html('<i class="fa fa-play"></i> Run API Test');

                // Show error
                displayTestResults({
                    success: false,
                    message: 'Network error: ' + error,
                    details: {
                        status: status,
                        error: error,
                        response_text: xhr.responseText
                    }
                }, 0);
            });
        });

        // Clear results
        $('#clear-results-btn').click(function() {
            clearTestResults();
        });

        // Export results
        $('#export-results-btn').click(function() {
            var results = $('#test-results').html();
            if (results) {
                var blob = new Blob([results], {type: 'text/html'});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'rdas-api-test-results-' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.html';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }
        });

        function displayTestResults(response, totalTime) {
            var statusHtml = '';
            var resultsHtml = '';

            if (response.success) {
                // Success status
                statusHtml = '<p class="text-success"><i class="fa fa-check-circle fa-2x"></i><br>Test Successful</p>';

                // Update quick stats
                $('#response-time').text((response.details.response_time || 0) + ' ms');
                $('#http-status').text(response.details.http_code || '-');
                $('#data-count').text(response.details.data_count || 0);
                $('#response-size').text(formatBytes(response.details.response_size || 0));

                // Results HTML
                resultsHtml = '<div class="alert alert-success">';
                resultsHtml += '<h4><i class="fa fa-check"></i> Test Successful</h4>';
                resultsHtml += '<p>' + response.message + '</p>';
                resultsHtml += '</div>';

                // Details table
                resultsHtml += '<table class="table table-bordered">';
                resultsHtml += '<tr><th>Metric</th><th>Value</th></tr>';
                resultsHtml += '<tr><td>HTTP Status Code</td><td><span class="label label-success">' + (response.details.http_code || '-') + '</span></td></tr>';
                resultsHtml += '<tr><td>Response Time</td><td>' + (response.details.response_time || 0) + ' ms</td></tr>';
                resultsHtml += '<tr><td>Total Request Time</td><td>' + totalTime + ' ms</td></tr>';
                resultsHtml += '<tr><td>Data Count</td><td>' + (response.details.data_count || 0) + ' items</td></tr>';
                resultsHtml += '<tr><td>Response Size</td><td>' + formatBytes(response.details.response_size || 0) + '</td></tr>';
                resultsHtml += '</table>';

                // Show sample data if available
                if (response.data && Array.isArray(response.data) && response.data.length > 0) {
                    var sampleData = response.data.slice(0, 5); // Show first 5 items
                    var dataHtml = '<h5>Sample Data (first 5 items):</h5>';
                    dataHtml += '<pre>' + JSON.stringify(sampleData, null, 2) + '</pre>';

                    $('#data-preview').html(dataHtml);
                    $('#data-preview-panel').show();
                }

            } else {
                // Error status
                statusHtml = '<p class="text-danger"><i class="fa fa-times-circle fa-2x"></i><br>Test Failed</p>';

                // Update quick stats
                $('#response-time').text((response.details && response.details.response_time) ? response.details.response_time + ' ms' : '-');
                $('#http-status').text((response.details && response.details.http_code) ? response.details.http_code : 'Error');
                $('#data-count').text('-');
                $('#response-size').text('-');

                // Results HTML
                resultsHtml = '<div class="alert alert-danger">';
                resultsHtml += '<h4><i class="fa fa-times"></i> Test Failed</h4>';
                resultsHtml += '<p>' + response.message + '</p>';
                resultsHtml += '</div>';

                // Error details
                if (response.details) {
                    resultsHtml += '<h5>Error Details:</h5>';
                    resultsHtml += '<pre>' + JSON.stringify(response.details, null, 2) + '</pre>';
                }
            }

            // Update status
            $('#test-status').html(statusHtml).show();

            // Show results
            $('#test-results').html(resultsHtml);
            $('#results-panel').show();
        }

        function clearTestResults() {
            $('#test-status').html('<p class="text-muted"><i class="fa fa-clock-o fa-2x"></i><br>Ready to test</p>');
            $('#response-time').text('-');
            $('#http-status').text('-');
            $('#data-count').text('-');
            $('#response-size').text('-');
            $('#results-panel').hide();
            $('#data-preview-panel').hide();
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

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
    });
    </script>

    <style>
    .rdas-api-test-container .panel-actions {
        float: right;
        margin-top: -5px;
    }

    .stat-item {
        text-align: center;
        margin-bottom: 15px;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #337ab7;
    }

    .stat-label {
        font-size: 1.2rem;
        color: #777;
        text-transform: uppercase;
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

    pre {
        max-height: 300px;
        overflow-y: auto;
    }

    .progress {
        margin-bottom: 0;
    }
    </style>
    <?php
    return ob_get_clean();
}
