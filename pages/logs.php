<?php
/**
 * RDAS Pricing Updater - Logs Page
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
 * Show Logs Page
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showLogsPage($vars) {
    try {
        // Handle AJAX requests
        if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
            return handleLogsAjax($vars);
        }

        // Get logs with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
        $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $logs_data = rdasGetAddonLogs($page, $per_page, $filter_type, $search);

        // Get log statistics
        $log_stats = getLogStatistics();

        // Prepare template variables
        $template_vars = array(
            'modulelink' => $vars['modulelink'],
            'logs' => $logs_data['logs'],
            'total_logs' => $logs_data['total'],
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($logs_data['total'] / $per_page),
            'filter_type' => $filter_type,
            'search' => $search,
            'log_stats' => $log_stats,
            'csrf_token' => rdasGenerateCSRFToken('logs')
        );

        // Render logs template
        return renderLogsTemplate($template_vars);

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('RDAS Logs Page Error: ' . $e->getMessage());
        }
        return '<div class="alert alert-danger">Error loading logs page: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Handle AJAX requests for logs
 *
 * @param array $vars Template variables
 * @return string JSON response
 */
function handleLogsAjax($vars) {
    header('Content-Type: application/json');

    $operation = isset($_POST['operation']) ? $_POST['operation'] : '';

    switch ($operation) {
        case 'clear_logs':
            return json_encode(clearAllLogs());

        case 'export_logs':
            return json_encode(exportLogs());

        case 'delete_log':
            $log_id = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
            return json_encode(deleteLogEntry($log_id));

        case 'get_log_details':
            $log_id = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
            return json_encode(getLogDetails($log_id));

        default:
            return json_encode(array('success' => false, 'message' => 'Invalid operation'));
    }
}

/**
 * Get log statistics
 *
 * @return array Log statistics
 */
function getLogStatistics() {
    try {
        $stats = array(
            'total_logs' => 0,
            'error_logs' => 0,
            'warning_logs' => 0,
            'info_logs' => 0,
            'today_logs' => 0,
            'last_7_days' => 0
        );

        if (function_exists('full_query')) {
            // Total logs
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['total_logs'] = (int)$row['total'];
            }

            // Error logs
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE level = 'error'");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['error_logs'] = (int)$row['total'];
            }

            // Warning logs
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE level = 'warning'");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['warning_logs'] = (int)$row['total'];
            }

            // Info logs
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE level = 'info'");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['info_logs'] = (int)$row['total'];
            }

            // Today's logs
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE DATE(date) = CURDATE()");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['today_logs'] = (int)$row['total'];
            }

            // Last 7 days
            $result = full_query("SELECT COUNT(*) as total FROM mod_rdas_pricing_updater_log WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            if ($result) {
                $row = mysql_fetch_array($result);
                $stats['last_7_days'] = (int)$row['total'];
            }
        }

        return $stats;

    } catch (Exception $e) {
        return $stats;
    }
}

/**
 * Clear all logs
 *
 * @return array Operation result
 */
function clearAllLogs() {
    try {
        if (!function_exists('full_query')) {
            return array('success' => false, 'message' => 'Database functions not available');
        }

        $result = full_query("DELETE FROM mod_rdas_pricing_updater_log");

        if ($result) {
            if (function_exists('logActivity')) {
                logActivity('RDAS Pricing Updater: All logs cleared by admin');
            }
            return array('success' => true, 'message' => 'All logs cleared successfully');
        }

        return array('success' => false, 'message' => 'Failed to clear logs');

    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error clearing logs: ' . $e->getMessage());
    }
}

/**
 * Export logs to CSV
 *
 * @return array Operation result
 */
function exportLogs() {
    try {
        $logs = rdasGetAddonLogs(1, 10000);

        if (empty($logs['logs'])) {
            return array('success' => false, 'message' => 'No logs to export');
        }

        // Create CSV content
        $csv_content = "ID,Level,Message,Data,Date\n";

        foreach ($logs['logs'] as $log) {
            $csv_content .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $log['id'],
                $log['level'],
                str_replace('"', '""', $log['message']),
                str_replace('"', '""', json_encode($log['data'])),
                $log['date']
            );
        }

        $filename = 'rdas-logs-' . date('Y-m-d-H-i-s') . '.csv';

        return array(
            'success' => true,
            'message' => 'Logs exported successfully',
            'filename' => $filename,
            'content' => base64_encode($csv_content)
        );

    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error exporting logs: ' . $e->getMessage());
    }
}

/**
 * Delete a specific log entry
 *
 * @param int $log_id Log ID
 * @return array Operation result
 */
function deleteLogEntry($log_id) {
    try {
        if ($log_id <= 0) {
            return array('success' => false, 'message' => 'Invalid log ID');
        }

        if (!function_exists('full_query')) {
            return array('success' => false, 'message' => 'Database functions not available');
        }

        $result = full_query("DELETE FROM mod_rdas_pricing_updater_log WHERE id = " . intval($log_id));

        return $result
            ? array('success' => true, 'message' => 'Log entry deleted successfully')
            : array('success' => false, 'message' => 'Log entry not found or could not be deleted');

    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error deleting log entry: ' . $e->getMessage());
    }
}

/**
 * Get detailed information about a log entry
 *
 * @param int $log_id Log ID
 * @return array Log details
 */
function getLogDetails($log_id) {
    try {
        if ($log_id <= 0) {
            return array('success' => false, 'message' => 'Invalid log ID');
        }

        if (!function_exists('full_query')) {
            return array('success' => false, 'message' => 'Database functions not available');
        }

        $result = full_query("SELECT * FROM mod_rdas_pricing_updater_log WHERE id = " . intval($log_id));
        if ($result) {
            $log = mysql_fetch_array($result);
            if ($log) {
                return array(
                    'success' => true,
                    'log' => array(
                        'id' => $log['id'],
                        'level' => $log['level'],
                        'message' => $log['message'],
                        'data' => $log['data'],
                        'date' => $log['date']
                    )
                );
            }
        }

        return array('success' => false, 'message' => 'Log entry not found');

    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error getting log details: ' . $e->getMessage());
    }
}

/**
 * Render logs template
 *
 * @param array $vars Template variables
 * @return string Rendered HTML
 */
function renderLogsTemplate($vars) {
    ob_start();
    ?>
    <div class="rdas-logs-container">
        <!-- Page Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <i class="fa fa-list-alt"></i> RDAS Pricing Updater Logs
                        </h3>
                        <div class="panel-actions">
                            <button type="button" class="btn btn-sm btn-info" id="refresh-logs-btn">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="export-logs-btn">
                                <i class="fa fa-download"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" id="clear-logs-btn">
                                <i class="fa fa-trash"></i> Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Statistics -->
        <div class="row">
            <div class="col-md-2">
                <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['total_logs']); ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box error">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['error_logs']); ?></div>
                    <div class="stat-label">Errors</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box warning">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['warning_logs']); ?></div>
                    <div class="stat-label">Warnings</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box info">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['info_logs']); ?></div>
                    <div class="stat-label">Info</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['today_logs']); ?></div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-box">
                    <div class="stat-value"><?php echo number_format($vars['log_stats']['last_7_days']); ?></div>
                    <div class="stat-label">Last 7 Days</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-filter"></i> Filters
                        </h4>
                    </div>
                    <div class="panel-body">
                        <form method="GET" class="form-inline">
                            <input type="hidden" name="module" value="rdas_pricing_updater">
                            <input type="hidden" name="action" value="logs">

                            <div class="form-group">
                                <label for="filter_type">Type:</label>
                                <select name="filter_type" id="filter_type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="error" <?php echo $vars['filter_type'] == 'error' ? 'selected' : ''; ?>>Error</option>
                                    <option value="success" <?php echo $vars['filter_type'] == 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="warning" <?php echo $vars['filter_type'] == 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    <option value="info" <?php echo $vars['filter_type'] == 'info' ? 'selected' : ''; ?>>Info</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="search">Search:</label>
                                <input type="text" name="search" id="search" class="form-control"
                                       value="<?php echo htmlspecialchars($vars['search']); ?>"
                                       placeholder="Search in messages...">
                            </div>

                            <div class="form-group">
                                <label for="per_page">Per Page:</label>
                                <select name="per_page" id="per_page" class="form-control">
                                    <option value="25" <?php echo $vars['per_page'] == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $vars['per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $vars['per_page'] == 100 ? 'selected' : ''; ?>>100</option>
                                    <option value="200" <?php echo $vars['per_page'] == 200 ? 'selected' : ''; ?>>200</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Filter
                            </button>

                            <a href="<?php echo $vars['modulelink']; ?>&action=logs" class="btn btn-default">
                                <i class="fa fa-times"></i> Clear
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="fa fa-list"></i> Log Entries
                            <span class="badge"><?php echo number_format($vars['total_logs']); ?></span>
                        </h4>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($vars['logs'])): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No log entries found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">ID</th>
                                            <th width="10%">Type</th>
                                            <th width="50%">Message</th>
                                            <th width="20%">Created At</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vars['logs'] as $log): ?>
                                            <tr class="log-row log-<?php echo $log['level']; ?>">
                                                <td><?php echo $log['id']; ?></td>
                                                <td>
                                                    <span class="label label-<?php echo getLogTypeClass($log['level']); ?>">
                                                        <?php echo ucfirst($log['level']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="log-message" title="<?php echo htmlspecialchars($log['message']); ?>">
                                                        <?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?>
                                                        <?php if (strlen($log['message']) > 100): ?>
                                                            <span class="text-muted">...</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span title="<?php echo $log['date']; ?>">
                                                        <?php echo date('M j, Y H:i', strtotime($log['date'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-info view-log-btn"
                                                            data-log-id="<?php echo $log['id']; ?>">
                                                        <i class="fa fa-eye"></i> View
                                                    </button>
                                                    <button type="button" class="btn btn-xs btn-danger delete-log-btn"
                                                            data-log-id="<?php echo $log['id']; ?>">
                                                        <i class="fa fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($vars['total_pages'] > 1): ?>
                                <div class="text-center">
                                    <ul class="pagination">
                                        <?php if ($vars['current_page'] > 1): ?>
                                            <li>
                                                <a href="<?php echo $vars['modulelink']; ?>&action=logs&page=<?php echo $vars['current_page'] - 1; ?>&per_page=<?php echo $vars['per_page']; ?>&filter_type=<?php echo $vars['filter_type']; ?>&search=<?php echo urlencode($vars['search']); ?>">
                                                    <i class="fa fa-chevron-left"></i> Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $vars['current_page'] - 2); $i <= min($vars['total_pages'], $vars['current_page'] + 2); $i++): ?>
                                            <li class="<?php echo $i == $vars['current_page'] ? 'active' : ''; ?>">
                                                <a href="<?php echo $vars['modulelink']; ?>&action=logs&page=<?php echo $i; ?>&per_page=<?php echo $vars['per_page']; ?>&filter_type=<?php echo $vars['filter_type']; ?>&search=<?php echo urlencode($vars['search']); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($vars['current_page'] < $vars['total_pages']): ?>
                                            <li>
                                                <a href="<?php echo $vars['modulelink']; ?>&action=logs&page=<?php echo $vars['current_page'] + 1; ?>&per_page=<?php echo $vars['per_page']; ?>&filter_type=<?php echo $vars['filter_type']; ?>&search=<?php echo urlencode($vars['search']); ?>">
                                                    Next <i class="fa fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <i class="fa fa-list-alt"></i> Log Details
                    </h4>
                </div>
                <div class="modal-body">
                    <div id="log-details-content">
                        <div class="text-center">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                            <p>Loading log details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Refresh logs
        $('#refresh-logs-btn').click(function() {
            window.location.reload();
        });

        // Export logs
        $('#export-logs-btn').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');

            $.post('<?php echo $vars['modulelink']; ?>&action=logs&ajax=1', {
                operation: 'export_logs',
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-download"></i> Export');

                if (response.success) {
                    // Create download link
                    var blob = new Blob([atob(response.content)], {type: 'text/csv'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = response.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);

                    alert('Logs exported successfully!');
                } else {
                    alert('Error exporting logs: ' + response.message);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fa fa-download"></i> Export');
                alert('Network error occurred while exporting logs.');
            });
        });

        // Clear all logs
        $('#clear-logs-btn').click(function() {
            if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing...');

            $.post('<?php echo $vars['modulelink']; ?>&action=logs&ajax=1', {
                operation: 'clear_logs',
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Clear All');

                if (response.success) {
                    alert('All logs cleared successfully!');
                    window.location.reload();
                } else {
                    alert('Error clearing logs: ' + response.message);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Clear All');
                alert('Network error occurred while clearing logs.');
            });
        });

        // View log details
        $('.view-log-btn').click(function() {
            var logId = $(this).data('log-id');

            $('#log-details-content').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Loading log details...</p></div>');
            $('#logDetailsModal').modal('show');

            $.post('<?php echo $vars['modulelink']; ?>&action=logs&ajax=1', {
                operation: 'get_log_details',
                log_id: logId,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                if (response.success) {
                    var log = response.log;
                    var html = '<table class="table table-bordered">';
                    html += '<tr><th width="20%">ID</th><td>' + log.id + '</td></tr>';
                    html += '<tr><th>Type</th><td><span class="label label-' + getLogTypeClass(log.type) + '">' + log.type.charAt(0).toUpperCase() + log.type.slice(1) + '</span></td></tr>';
                    html += '<tr><th>Message</th><td>' + escapeHtml(log.message) + '</td></tr>';
                    html += '<tr><th>Details</th><td><pre>' + escapeHtml(log.details) + '</pre></td></tr>';
                    html += '<tr><th>Created At</th><td>' + log.created_at + '</td></tr>';
                    html += '</table>';

                    $('#log-details-content').html(html);
                } else {
                    $('#log-details-content').html('<div class="alert alert-danger">Error loading log details: ' + response.message + '</div>');
                }
            }, 'json').fail(function() {
                $('#log-details-content').html('<div class="alert alert-danger">Network error occurred while loading log details.</div>');
            });
        });

        // Delete log entry
        $('.delete-log-btn').click(function() {
            if (!confirm('Are you sure you want to delete this log entry?')) {
                return;
            }

            var logId = $(this).data('log-id');
            var btn = $(this);
            var row = btn.closest('tr');

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.post('<?php echo $vars['modulelink']; ?>&action=logs&ajax=1', {
                operation: 'delete_log',
                log_id: logId,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        row.remove();
                    });
                } else {
                    btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
                    alert('Error deleting log entry: ' + response.message);
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
                alert('Network error occurred while deleting log entry.');
            });
        });

        function getLogTypeClass(type) {
            switch (type) {
                case 'error': return 'danger';
                case 'success': return 'success';
                case 'warning': return 'warning';
                case 'info': return 'info';
                default: return 'default';
            }
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    });
    </script>

    <style>
    .rdas-logs-container .panel-actions {
        float: right;
        margin-top: -5px;
    }

    .stat-box {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        text-align: center;
        margin-bottom: 20px;
    }

    .stat-box.error {
        border-color: #d9534f;
        background-color: #f2dede;
    }

    .stat-box.success {
        border-color: #5cb85c;
        background-color: #dff0d8;
    }

    .stat-box.warning {
        border-color: #f0ad4e;
        background-color: #fcf8e3;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #337ab7;
    }

    .stat-box.error .stat-value {
        color: #d9534f;
    }

    .stat-box.success .stat-value {
        color: #5cb85c;
    }

    .stat-box.warning .stat-value {
        color: #f0ad4e;
    }

    .stat-label {
        font-size: 12px;
        color: #777;
        text-transform: uppercase;
        margin-top: 5px;
    }

    .log-message {
        word-break: break-word;
    }

    .log-row.log-error {
        background-color: #f2dede;
    }

    .log-row.log-success {
        background-color: #dff0d8;
    }

    .log-row.log-warning {
        background-color: #fcf8e3;
    }

    .form-inline .form-group {
        margin-right: 15px;
    }

    .pagination {
        margin: 20px 0;
    }

    pre {
        max-height: 200px;
        overflow-y: auto;
        background-color: #f5f5f5;
        border: 1px solid #ccc;
        padding: 10px;
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Get log type CSS class
 *
 * @param string $type Log type
 * @return string CSS class
 */
function getLogTypeClass($type) {
    $classes = [
        'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        'debug' => 'default'
    ];
    return $classes[$type] ?? 'default';
}
