<?php
/**
 * RDAS Pricing Updater - Simple Dashboard (Fallback)
 * 
 * Simple fallback dashboard untuk mengatasi error loading
 *
 * @package    WHMCS
 * @author     Morden Team
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Show Simple Dashboard (Fallback)
 */
function showSimpleDashboard($vars) {
    try {
        echo '<div class="alert alert-info">';
        echo '<h4><i class="fa fa-info-circle"></i> RDAS Pricing Updater</h4>';
        echo '<p>Dashboard sedang dalam mode fallback. Beberapa fitur mungkin tidak tersedia.</p>';
        echo '</div>';
        
        echo '<div class="row">';
        
        // Basic Info Panel
        echo '<div class="col-md-6">';
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title"><i class="fa fa-cog"></i> Addon Information</h3>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<p><strong>Name:</strong> RDAS Pricing Updater</p>';
        echo '<p><strong>Version:</strong> 2.0.7</p>';
        echo '<p><strong>Status:</strong> <span class="label label-success">Active</span></p>';
        echo '<p><strong>Mode:</strong> <span class="label label-warning">Fallback</span></p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Quick Actions Panel
        echo '<div class="col-md-6">';
        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title"><i class="fa fa-bolt"></i> Quick Actions</h3>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="btn-group-vertical" style="width: 100%;">';
        echo '<a href="addonmodules.php?module=rdas_pricing_updater&page=pricing" class="btn btn-primary">';
        echo '<i class="fa fa-money"></i> Manage Pricing';
        echo '</a>';
        echo '<a href="addonmodules.php?module=rdas_pricing_updater&page=settings" class="btn btn-info">';
        echo '<i class="fa fa-cog"></i> Settings';
        echo '</a>';
        echo '<a href="addonmodules.php?module=rdas_pricing_updater&page=api_test" class="btn btn-warning">';
        echo '<i class="fa fa-flask"></i> API Test';
        echo '</a>';
        echo '<a href="addonmodules.php?module=rdas_pricing_updater&page=logs" class="btn btn-default">';
        echo '<i class="fa fa-file-text"></i> View Logs';
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // End row
        
        // Troubleshooting Panel
        echo '<div class="row">';
        echo '<div class="col-md-12">';
        echo '<div class="panel panel-warning">';
        echo '<div class="panel-heading">';
        echo '<h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> Troubleshooting</h3>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<p>Jika Anda melihat dashboard ini, kemungkinan ada masalah dengan:</p>';
        echo '<ul>';
        echo '<li>Database connection</li>';
        echo '<li>Missing addon tables</li>';
        echo '<li>PHP errors atau missing functions</li>';
        echo '<li>File permissions</li>';
        echo '</ul>';
        echo '<p><strong>Langkah troubleshooting:</strong></p>';
        echo '<ol>';
        echo '<li>Periksa WHMCS Activity Log untuk error messages</li>';
        echo '<li>Pastikan addon sudah diaktivasi dengan benar</li>';
        echo '<li>Periksa file permissions di direktori addon</li>';
        echo '<li>Test database connection</li>';
        echo '</ol>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">';
        echo '<h4>Critical Error</h4>';
        echo '<p>Unable to load even the fallback dashboard.</p>';
        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Please check your WHMCS installation and addon files.</p>';
        echo '</div>';
    }
}

?>