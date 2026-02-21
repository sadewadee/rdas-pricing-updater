<div class="rdas-pricing-table-container">
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-table"></i> Domain Pricing Table
            </h3>
            <div class="panel-actions">
                <button type="button" class="btn btn-sm btn-success" id="sync-all-prices">
                    <i class="fa fa-refresh"></i> Sync All Prices
                </button>
                <button type="button" class="btn btn-sm btn-info" id="import-new-tlds">
                    <i class="fa fa-download"></i> Import New TLDs
                </button>
                <button type="button" class="btn btn-sm btn-warning" id="export-pricing">
                    <i class="fa fa-upload"></i> Export Pricing
                </button>
            </div>
        </div>
        
        <div class="panel-body">
            <!-- Filter and Search Controls -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="filter-status">Filter by Status:</label>
                        <select class="form-control" id="filter-status">
                            <option value="">All Domains</option>
                            <option value="synced">Recently Synced</option>
                            <option value="outdated">Outdated</option>
                            <option value="error">Sync Errors</option>
                            <option value="new">New from API</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="filter-extension">Filter by Extension:</label>
                        <select class="form-control" id="filter-extension">
                            <option value="">All Extensions</option>
                            {foreach $extensions as $ext}
                            <option value="{$ext}">{$ext}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="search-domain">Search Domain:</label>
                        <input type="text" class="form-control" id="search-domain" placeholder="Search by extension name...">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-primary btn-block" id="apply-filters">
                            <i class="fa fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="bulk-actions" style="display: none;">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-success" id="bulk-sync">
                                <i class="fa fa-refresh"></i> Sync Selected
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" id="bulk-update-margin">
                                <i class="fa fa-percent"></i> Update Margin
                            </button>
                            <button type="button" class="btn btn-sm btn-info" id="bulk-apply-rounding">
                                <i class="fa fa-calculator"></i> Apply Rounding
                            </button>
                        </div>
                        <span class="selected-count ml-2">0 domains selected</span>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Summary -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="stats-summary">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.total_domains|default:0}</div>
                                    <div class="stat-label">Total Domains</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.synced_today|default:0}</div>
                                    <div class="stat-label">Synced Today</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.outdated|default:0}</div>
                                    <div class="stat-label">Outdated</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.errors|default:0}</div>
                                    <div class="stat-label">Errors</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.new_available|default:0}</div>
                                    <div class="stat-label">New Available</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-box">
                                    <div class="stat-number">{$stats.promo_active|default:0}</div>
                                    <div class="stat-label">Promo Active</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pricing Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="pricing-table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="select-all" title="Select All">
                            </th>
                            <th width="80">TLD</th>
                            <th width="60">Status</th>
                            <th width="80">Period</th>
                            <th width="120">Registration</th>
                            <th width="120">Renewal</th>
                            <th width="120">Transfer</th>
                            <th width="120">Redemption</th>
                            <th width="100">Promo</th>
                            <th width="100">Last Sync</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $domains && count($domains) > 0}
                            {foreach $domains as $domain}
                            <tr data-extension="{$domain.extension}" class="domain-row {if $domain.has_promo}promo-active{/if} {if $domain.sync_status == 'error'}sync-error{/if}">
                                <td>
                                    <input type="checkbox" class="domain-checkbox" value="{$domain.extension}">
                                </td>
                                <td>
                                    <strong>.{$domain.extension}</strong>
                                    {if $domain.is_new}
                                        <span class="label label-success">NEW</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $domain.sync_status == 'synced'}
                                        <span class="label label-success">Synced</span>
                                    {elseif $domain.sync_status == 'outdated'}
                                        <span class="label label-warning">Outdated</span>
                                    {elseif $domain.sync_status == 'error'}
                                        <span class="label label-danger">Error</span>
                                    {else}
                                        <span class="label label-default">Unknown</span>
                                    {/if}
                                </td>
                                <td>
                                    <select class="form-control input-sm period-selector" data-extension="{$domain.extension}">
                                        <option value="1" {if $domain.selected_period == 1}selected{/if}>1 Year</option>
                                        <option value="2" {if $domain.selected_period == 2}selected{/if}>2 Years</option>
                                        <option value="3" {if $domain.selected_period == 3}selected{/if}>3 Years</option>
                                        <option value="5" {if $domain.selected_period == 5}selected{/if}>5 Years</option>
                                        <option value="10" {if $domain.selected_period == 10}selected{/if}>10 Years</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="price-cell">
                                        {if $domain.register.promo && $domain.has_promo}
                                            <div class="promo-price">
                                                <span class="original-price">{$domain.register.current|number_format:0:",":"."}IDR</span>
                                                <span class="promo-price-value">{$domain.register.promo|number_format:0:",":"."}IDR</span>
                                            </div>
                                        {else}
                                            <span class="current-price">{$domain.register.current|number_format:0:",":"."}IDR</span>
                                        {/if}
                                        {if $domain.register.api_price}
                                            <div class="api-price">
                                                API: {$domain.register.api_price|number_format:0:",":"."}IDR
                                            </div>
                                        {/if}
                                        {if $domain.register.margin}
                                            <div class="margin-info">
                                                Margin: {$domain.register.margin}
                                            </div>
                                        {/if}
                                    </div>
                                </td>
                                <td>
                                    <div class="price-cell">
                                        <span class="current-price">{$domain.renew.current|number_format:0:",":"."}IDR</span>
                                        {if $domain.renew.api_price}
                                            <div class="api-price">
                                                API: {$domain.renew.api_price|number_format:0:",":"."}IDR
                                            </div>
                                        {/if}
                                        {if $domain.renew.margin}
                                            <div class="margin-info">
                                                Margin: {$domain.renew.margin}
                                            </div>
                                        {/if}
                                    </div>
                                </td>
                                <td>
                                    <div class="price-cell">
                                        <span class="current-price">{$domain.transfer.current|number_format:0:",":"."}IDR</span>
                                        {if $domain.transfer.api_price}
                                            <div class="api-price">
                                                API: {$domain.transfer.api_price|number_format:0:",":"."}IDR
                                            </div>
                                        {/if}
                                        {if $domain.transfer.margin}
                                            <div class="margin-info">
                                                Margin: {$domain.transfer.margin}
                                            </div>
                                        {/if}
                                    </div>
                                </td>
                                <td>
                                    <div class="price-cell">
                                        {if $domain.redemption.current}
                                            <span class="current-price">{$domain.redemption.current|number_format:0:",":"."}IDR</span>
                                            {if $domain.redemption.api_price}
                                                <div class="api-price">
                                                    API: {$domain.redemption.api_price|number_format:0:",":"."}IDR
                                                </div>
                                            {/if}
                                        {else}
                                            <span class="text-muted">N/A</span>
                                        {/if}
                                    </div>
                                </td>
                                <td>
                                    {if $domain.has_promo}
                                        <div class="promo-info">
                                            <span class="label label-info">ACTIVE</span>
                                            {if $domain.promo_dates}
                                                <div class="promo-dates">
                                                    {$domain.promo_dates}
                                                </div>
                                            {/if}
                                        </div>
                                    {else}
                                        <span class="text-muted">No Promo</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $domain.last_sync}
                                        <div class="sync-info">
                                            <div class="sync-date">{$domain.last_sync|date_format:"%d/%m/%Y"}</div>
                                            <div class="sync-time">{$domain.last_sync|date_format:"%H:%M"}</div>
                                        </div>
                                    {else}
                                        <span class="text-muted">Never</span>
                                    {/if}
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-success btn-sync" data-extension="{$domain.extension}" title="Sync Now">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                        <button type="button" class="btn btn-info btn-edit" data-extension="{$domain.extension}" title="Edit Pricing">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-warning btn-history" data-extension="{$domain.extension}" title="View History">
                                            <i class="fa fa-history"></i>
                                        </button>
                                        {if $domain.is_new}
                                            <button type="button" class="btn btn-primary btn-import" data-extension="{$domain.extension}" title="Import to WHMCS">
                                                <i class="fa fa-download"></i>
                                            </button>
                                        {/if}
                                    </div>
                                </td>
                            </tr>
                            {/foreach}
                        {else}
                            <tr>
                                <td colspan="11" class="text-center">
                                    <div class="empty-state">
                                        <i class="fa fa-table fa-3x text-muted"></i>
                                        <h4>No Domain Pricing Data</h4>
                                        <p>No domain pricing data available. Click "Import New TLDs" to fetch data from API.</p>
                                        <button type="button" class="btn btn-primary" id="import-first-time">
                                            <i class="fa fa-download"></i> Import Domain Data
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            {if $pagination && $pagination.total_pages > 1}
            <div class="row">
                <div class="col-md-6">
                    <div class="pagination-info">
                        Showing {$pagination.start} to {$pagination.end} of {$pagination.total_records} domains
                    </div>
                </div>
                <div class="col-md-6">
                    <nav aria-label="Domain pricing pagination">
                        <ul class="pagination pagination-sm pull-right">
                            {if $pagination.current_page > 1}
                                <li><a href="#" data-page="{$pagination.current_page - 1}">&laquo; Previous</a></li>
                            {/if}
                            
                            {for $page=1 to $pagination.total_pages}
                                {if $page == $pagination.current_page}
                                    <li class="active"><span>{$page}</span></li>
                                {else}
                                    <li><a href="#" data-page="{$page}">{$page}</a></li>
                                {/if}
                            {/for}
                            
                            {if $pagination.current_page < $pagination.total_pages}
                                <li><a href="#" data-page="{$pagination.current_page + 1}">Next &raquo;</a></li>
                            {/if}
                        </ul>
                    </nav>
                </div>
            </div>
            {/if}
        </div>
    </div>
</div>

<!-- Edit Pricing Modal -->
<div class="modal fade" id="edit-pricing-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Domain Pricing - <span id="modal-extension"></span></h4>
            </div>
            <div class="modal-body">
                <form id="edit-pricing-form">
                    <input type="hidden" id="edit-extension" name="extension">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Registration Pricing</h5>
                            <div class="form-group">
                                <label>API Price (IDR)</label>
                                <input type="text" class="form-control" id="api-register" readonly>
                            </div>
                            <div class="form-group">
                                <label>Current WHMCS Price (IDR)</label>
                                <input type="number" class="form-control" id="current-register" name="register_price">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Renewal Pricing</h5>
                            <div class="form-group">
                                <label>API Price (IDR)</label>
                                <input type="text" class="form-control" id="api-renew" readonly>
                            </div>
                            <div class="form-group">
                                <label>Current WHMCS Price (IDR)</label>
                                <input type="number" class="form-control" id="current-renew" name="renew_price">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Transfer Pricing</h5>
                            <div class="form-group">
                                <label>API Price (IDR)</label>
                                <input type="text" class="form-control" id="api-transfer" readonly>
                            </div>
                            <div class="form-group">
                                <label>Current WHMCS Price (IDR)</label>
                                <input type="number" class="form-control" id="current-transfer" name="transfer_price">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Margin Settings</h5>
                            <div class="form-group">
                                <label>Margin Type</label>
                                <select class="form-control" id="margin-type" name="margin_type">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (IDR)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Margin Value</label>
                                <input type="number" class="form-control" id="margin-value" name="margin_value" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h5>Rounding Options</h5>
                            <div class="form-group">
                                <label>Rounding Rule</label>
                                <select class="form-control" id="rounding-rule" name="rounding_rule">
                                    <option value="none">No Rounding</option>
                                    <option value="up_1000">Round Up to 1,000</option>
                                    <option value="up_5000">Round Up to 5,000</option>
                                    <option value="nearest_1000">Round to Nearest 1,000</option>
                                    <option value="custom">Custom Rounding</option>
                                </select>
                            </div>
                            <div class="form-group" id="custom-rounding-group" style="display: none;">
                                <label>Custom Rounding Value</label>
                                <input type="number" class="form-control" id="custom-rounding" name="custom_rounding" step="1">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="apply-calculated-prices">Apply Calculated Prices</button>
                <button type="button" class="btn btn-primary" id="save-pricing">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Margin Modal -->
<div class="modal fade" id="bulk-margin-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Bulk Update Margin</h4>
            </div>
            <div class="modal-body">
                <form id="bulk-margin-form">
                    <div class="form-group">
                        <label>Margin Type</label>
                        <select class="form-control" id="bulk-margin-type" name="margin_type">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (IDR)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Margin Value</label>
                        <input type="number" class="form-control" id="bulk-margin-value" name="margin_value" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Apply to Price Types</label>
                        <div class="checkbox">
                            <label><input type="checkbox" name="price_types[]" value="register" checked> Registration</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="price_types[]" value="renew" checked> Renewal</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="price_types[]" value="transfer" checked> Transfer</label>
                        </div>
                    </div>
                    <div class="selected-domains-info">
                        <strong>Selected Domains:</strong> <span id="bulk-selected-count">0</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="apply-bulk-margin">Apply Margin</button>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="progress-modal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Processing...</h4>
            </div>
            <div class="modal-body">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%">
                        <span class="sr-only">0% Complete</span>
                    </div>
                </div>
                <div class="progress-info">
                    <div class="current-action">Initializing...</div>
                    <div class="progress-details">
                        <span class="processed">0</span> of <span class="total">0</span> domains processed
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rdas-pricing-table-container .panel-actions {
    float: right;
}

.rdas-pricing-table-container .panel-actions .btn {
    margin-left: 5px;
}

.stats-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.stat-box {
    text-align: center;
    padding: 10px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    font-size: 12px;
    color: #7f8c8d;
    text-transform: uppercase;
}

.price-cell {
    font-size: 12px;
}

.price-cell .current-price {
    font-weight: bold;
    color: #2c3e50;
}

.price-cell .promo-price .original-price {
    text-decoration: line-through;
    color: #95a5a6;
    font-size: 11px;
}

.price-cell .promo-price-value {
    color: #e74c3c;
    font-weight: bold;
}

.price-cell .api-price {
    color: #3498db;
    font-size: 10px;
}

.price-cell .margin-info {
    color: #27ae60;
    font-size: 10px;
}

.domain-row.promo-active {
    background-color: #fff5f5;
}

.domain-row.sync-error {
    background-color: #fdf2f2;
}

.promo-info .promo-dates {
    font-size: 10px;
    color: #7f8c8d;
}

.sync-info {
    font-size: 11px;
}

.sync-info .sync-date {
    font-weight: bold;
}

.sync-info .sync-time {
    color: #7f8c8d;
}

.bulk-actions {
    background: #ecf0f1;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.selected-count {
    font-weight: bold;
    color: #2c3e50;
}

.empty-state {
    padding: 40px;
    text-align: center;
}

.empty-state h4 {
    margin-top: 20px;
    color: #7f8c8d;
}

.empty-state p {
    color: #95a5a6;
    margin-bottom: 20px;
}

.pagination-info {
    padding: 8px 0;
    color: #7f8c8d;
}

#custom-rounding-group {
    margin-top: 10px;
}

.progress-info {
    margin-top: 15px;
    text-align: center;
}

.current-action {
    font-weight: bold;
    margin-bottom: 5px;
}

.progress-details {
    color: #7f8c8d;
    font-size: 12px;
}
</style>
