<?php
/**
 * RDAS Pricing Updater - Pricing Page
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
 * Show Pricing Page
 *
 * @param array $vars Template variables
 * @return string HTML output
 */
function showPricingPage($vars) {
    try {
        // Get domain pricing data
        $pricing_data = getDomainPricingData();

        // Get registrars
        $registrars = getActiveRegistrars();

        // Get filter options
        $filter_options = getPricingFilterOptions();

        // Prepare template variables
        $template_vars = array(
            'modulelink' => $vars['modulelink'],
            'pricing_data' => $pricing_data,
            'registrars' => $registrars,
            'filter_options' => $filter_options,
            'csrf_token' => rdasGenerateCSRFToken('pricing')
        );

        // Load pricing table template
        return renderPricingTableTemplate($template_vars);

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('RDAS Pricing Page Error: ' . $e->getMessage());
        }
        return '<div class="alert alert-danger">Error loading pricing data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Get domain pricing data with API comparison
 *
 * @return array Domain pricing data with current and API prices
 */
function getDomainPricingData() {
    $pricing_data = array();

    try {
        // Get addon config for margin calculation
        $config = rdasGetAddonConfig('rdas_pricing_updater');
        $marginType = $config['margin_type'] ?? 'percentage';
        $marginValue = floatval($config['profit_margin'] ?? $config['margin_value'] ?? 20);
        $roundingRule = $config['rounding_rule'] ?? 'up_1000';
        $customRounding = floatval($config['custom_rounding'] ?? 1000);

        // Fetch API prices
        $apiUrl = $config['api_url'] ?? 'https://api.rdash.id/api/domain-prices?currency=IDR';
        $apiPrices = rdasFetchDomainPrices($apiUrl);

        // Build API lookup
        $apiLookup = array();
        if ($apiPrices && is_array($apiPrices)) {
            foreach ($apiPrices as $apiDomain) {
                $apiLookup[$apiDomain['extension']] = $apiDomain;
            }
        }

        // Get all domains from WHMCS - GROUP BY extension to avoid duplicates
        $domainsResult = full_query(
            "SELECT MIN(id) as id, extension, MAX(autoreg) as autoreg, MAX(`group`) as `group` FROM tbldomainpricing GROUP BY extension ORDER BY extension ASC"
        );

        while ($domain = mysql_fetch_array($domainsResult)) {
            $domainId = intval($domain['id']);
            $extension = $domain['extension'];
            $domainGroup = $domain['group'] ?? '';

            // Year column mapping for WHMCS 8/9
            $yearColumnMap = [
                1 => 'msetupfee',
                2 => 'qsetupfee',
                3 => 'ssetupfee',
                4 => 'asetupfee',
                5 => 'bsetupfee',
                6 => 'monthly',
                7 => 'quarterly',
                8 => 'semiannually',
                9 => 'annually',
                10 => 'biennially'
            ];

            // First, get API data to determine promo terms
            $promoTerms = 1;
            $promoActive = false;

            if (isset($apiLookup[$extension])) {
                $apiData = $apiLookup[$extension];
                $promoData = $apiData['promo'] ?? null;
                if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
                    $now = time();
                    $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
                    $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

                    if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                        $promoActive = true;
                        $promoTerms = intval($promoData['terms'] ?? 1);
                    }
                }
            }

            // Determine which year column to use for database price lookup
            $yearColumn = $yearColumnMap[$promoTerms] ?? 'msetupfee';

            // Get current pricing from tblpricing (WHMCS 8/9) - use the correct year column
            $currentRegister = 0;
            $currentRenew = 0;
            $currentTransfer = 0;

            $regResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainregister' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($regRow = mysql_fetch_array($regResult)) {
                $currentRegister = floatval($regRow[$yearColumn]);
            }

            $renewResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domainrenew' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($renewRow = mysql_fetch_array($renewResult)) {
                $currentRenew = floatval($renewRow[$yearColumn]);
            }

            $transferResult = full_query(
                "SELECT {$yearColumn} FROM tblpricing WHERE type='domaintransfer' AND relid=" . $domainId . " LIMIT 1"
            );
            if ($transferRow = mysql_fetch_array($transferResult)) {
                $currentTransfer = floatval($transferRow[$yearColumn]);
            }

            // Get API pricing for this extension
            $apiRegister = 0;
            $apiRenew = 0;
            $apiTransfer = 0;
            $promoRegister = 0;
            $promoRenew = 0;
            $promoEnd = null;
            $hasApiData = false;

            if (isset($apiLookup[$extension])) {
                $hasApiData = true;
                $apiData = $apiLookup[$extension];

                // Parse API base prices
                $apiRegister = rdasParsePrice($apiData['registration'] ?? 0);
                $apiRenew = rdasParsePrice($apiData['renewal'] ?? 0);
                $apiTransfer = rdasParsePrice($apiData['transfer'] ?? 0);

                // Check for promo - API structure has promo.registration directly
                $promoData = $apiData['promo'] ?? null;
                if ($promoData && isset($promoData['registration']) && !empty($promoData['registration'])) {
                    $now = time();
                    $startDate = isset($promoData['start_date']) ? strtotime($promoData['start_date']) : null;
                    $endDate = isset($promoData['end_date']) ? strtotime($promoData['end_date']) : null;

                    // Check if promo is currently active (within date range)
                    if ($startDate && $endDate && $now >= $startDate && $now <= $endDate) {
                        $promoActive = true;
                        $promoTerms = intval($promoData['terms'] ?? 1); // Get promo terms (year)
                        $promoRegister = rdasParsePrice($promoData['registration']);
                        $promoRenew = rdasParsePrice($promoData['renewal'] ?? 0);
                        $promoEnd = $promoData['end_date'];
                    }
                }

                // Apply margin to API base prices
                $apiRegister = rdasApplyMargin($apiRegister, $marginType, $marginValue);
                $apiRegister = rdasApplyRounding($apiRegister, $roundingRule, $customRounding);

                $apiRenew = rdasApplyMargin($apiRenew, $marginType, $marginValue);
                $apiRenew = rdasApplyRounding($apiRenew, $roundingRule, $customRounding);

                $apiTransfer = rdasApplyMargin($apiTransfer, $marginType, $marginValue);
                $apiTransfer = rdasApplyRounding($apiTransfer, $roundingRule, $customRounding);

                // Apply margin to promo price
                if ($promoActive && $promoRegister > 0) {
                    $promoRegister = rdasApplyMargin($promoRegister, $marginType, $marginValue);
                    $promoRegister = rdasApplyRounding($promoRegister, $roundingRule, $customRounding);
                }
            }

            // Calculate price differences
            $registerDiff = $hasApiData ? ($apiRegister - $currentRegister) : 0;
            $renewDiff = $hasApiData ? ($apiRenew - $currentRenew) : 0;
            $transferDiff = $hasApiData ? ($apiTransfer - $currentTransfer) : 0;

            // Determine promo price to show
            $finalApiRegister = $promoActive ? $promoRegister : $apiRegister;
            $finalRegisterDiff = $hasApiData ? ($finalApiRegister - $currentRegister) : 0;

            $pricing_data[] = array(
                'id' => $domainId,
                'extension' => $extension,
                // Current prices from database
                'current_register' => $currentRegister,
                'current_renew' => $currentRenew,
                'current_transfer' => $currentTransfer,
                // API prices (with margin applied)
                'api_register' => $apiRegister,
                'api_renew' => $apiRenew,
                'api_transfer' => $apiTransfer,
                // Promo info
                'promo_active' => $promoActive,
                'promo_terms' => $promoTerms,
                'promo_register' => $promoRegister,
                'promo_end' => $promoEnd,
                'final_api_register' => $finalApiRegister,
                // Price differences
                'register_diff' => $finalRegisterDiff,
                'renew_diff' => $renewDiff,
                'transfer_diff' => $transferDiff,
                // Other info
                'autoreg' => $domain['autoreg'] ?? '',
                'registrar_name' => $domain['autoreg'] ?: 'Manual',
                'domain_group' => $domainGroup,
                'has_api_data' => $hasApiData
            );
        }

    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error getting domain pricing data: ' . $e->getMessage());
        }
    }

    return $pricing_data;
}

/**
 * Get active registrars
 *
 * @return array Active registrars
 */
function getActiveRegistrars() {
    $registrars = array();

    try {
        if (class_exists('WHMCS\Database\Capsule\Manager')) {
            $registrar_data = \WHMCS\Database\Capsule\Manager::table('tblregistrars')
                ->select('registrar')
                ->distinct()
                ->orderBy('registrar')
                ->get();

            foreach ($registrar_data as $registrar) {
                $registrars[] = $registrar->registrar;
            }
        } else {
            // Legacy fallback
            $result = select_query('tblregistrars', 'DISTINCT registrar', '', 'registrar', 'ASC');
            if ($result) {
                while ($row = mysql_fetch_array($result)) {
                    $registrars[] = $row['registrar'];
                }
            }
        }
    } catch (Exception $e) {
        if (function_exists('logActivity')) {
            logActivity('Error getting registrars: ' . $e->getMessage());
        }
    }

    return $registrars;
}

/**
 * Get pricing filter options
 *
 * @return array Filter options
 */
function getPricingFilterOptions() {
    return array(
        'price_ranges' => array(
            '0-100000' => 'Rp 0 - 100k',
            '100000-500000' => 'Rp 100k - 500k',
            '500000-1000000' => 'Rp 500k - 1M',
            '1000000-999999999' => 'Rp 1M+'
        ),
        'currencies' => array(
            'IDR' => 'Indonesian Rupiah',
            'USD' => 'US Dollar',
            'EUR' => 'Euro'
        ),
        'tld_categories' => array(
            'generic' => 'Generic (.com, .net, .org)',
            'country' => 'Country Code (.id, .us, .uk)',
            'new' => 'New gTLD (.tech, .online, .store)',
            'premium' => 'Premium TLD'
        )
    );
}

/**
 * Render pricing table template
 *
 * @param array $vars Template variables
 * @return string Rendered HTML
 */
function renderPricingTableTemplate($vars) {
    ob_start();
    ?>
    <div class="rdas-pricing-updater">
        <!-- Page Header -->
        <div class="rdas-page-header">
            <h1 class="rdas-page-title">
                <div class="rdas-page-title-icon">
                    <i class="fa fa-list-alt"></i>
                </div>
                Domain Pricing
            </h1>
            <div class="rdas-page-actions">
                <button class="rdas-btn rdas-btn-ghost rdas-theme-toggle" title="Toggle Dark Mode">
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
            <a href="<?php echo $vars['modulelink']; ?>&page=pricing" class="rdas-nav-tab active">
                <i class="fa fa-list-alt"></i>
                <span>Pricing</span>
            </a>
            <a href="<?php echo $vars['modulelink']; ?>&page=settings" class="rdas-nav-tab">
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
            <button class="rdas-btn rdas-btn-primary" id="sync-all-btn">
                <i class="fa fa-sync"></i> Sync All Prices
            </button>
            <button class="rdas-btn rdas-btn-promo" id="sync-promo-btn">
                <i class="fa fa-tag"></i> Sync Promo Only
            </button>
            <button class="rdas-btn rdas-btn-outline" id="export-csv-btn">
                <i class="fa fa-download"></i> Export CSV
            </button>
        </div>

        <!-- Statistics Grid -->
        <?php
        $total_tlds = count($vars['pricing_data']);
        $active_promos = 0;
        $needs_sync = 0;
        $with_api_data = 0;
        $auto_registrars = 0;
        $avg_current = 0;
        $avg_api = 0;

        if (!empty($vars['pricing_data'])) {
            $current_prices = [];
            $api_prices = [];

            foreach ($vars['pricing_data'] as $domain) {
                if ($domain['promo_active']) $active_promos++;
                if ($domain['register_diff'] != 0) $needs_sync++;
                if ($domain['has_api_data']) $with_api_data++;
                if (!empty($domain['autoreg'])) $auto_registrars++;

                if ($domain['current_register'] > 0) $current_prices[] = $domain['current_register'];
                if ($domain['api_register'] > 0) $api_prices[] = $domain['api_register'];
            }

            $avg_current = !empty($current_prices) ? array_sum($current_prices) / count($current_prices) : 0;
            $avg_api = !empty($api_prices) ? array_sum($api_prices) / count($api_prices) : 0;
        }
        ?>
        <div class="rdas-stats-grid">
            <div class="rdas-stat-card">
                <div class="rdas-stat-icon">
                    <i class="fa fa-globe"></i>
                </div>
                <div class="rdas-stat-value"><?php echo $total_tlds; ?></div>
                <div class="rdas-stat-label">Total TLDs</div>
            </div>
            <div class="rdas-stat-card promo">
                <div class="rdas-stat-icon">
                    <i class="fa fa-tag"></i>
                </div>
                <div class="rdas-stat-value"><?php echo $active_promos; ?></div>
                <div class="rdas-stat-label">Active Promos</div>
            </div>
            <div class="rdas-stat-card warning">
                <div class="rdas-stat-icon">
                    <i class="fa fa-exchange-alt"></i>
                </div>
                <div class="rdas-stat-value"><?php echo $needs_sync; ?></div>
                <div class="rdas-stat-label">Need Sync</div>
            </div>
            <div class="rdas-stat-card success">
                <div class="rdas-stat-icon">
                    <i class="fa fa-check-circle"></i>
                </div>
                <div class="rdas-stat-value"><?php echo $with_api_data; ?></div>
                <div class="rdas-stat-label">With API Data</div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="rdas-card rdas-mb-3">
            <div class="rdas-card-header">
                <h3 class="rdas-card-title">
                    <i class="fa fa-filter"></i>
                    Filters & Search
                </h3>
            </div>
            <div class="rdas-card-body">
                <div class="rdas-flex rdas-gap-2" style="flex-wrap: wrap;">
                    <div class="rdas-form-group">
                        <label class="rdas-label">Search TLD</label>
                        <input type="text" class="rdas-input" id="search-tld" placeholder="e.g., .com, .id" style="width: 200px;">
                    </div>
                    <div class="rdas-form-group">
                        <label class="rdas-label">Registrar</label>
                        <select class="rdas-select" id="filter-registrar" style="width: 150px;">
                            <option value="">All Registrars</option>
                            <?php foreach ($vars['registrars'] as $registrar): ?>
                            <option value="<?php echo htmlspecialchars($registrar); ?>"><?php echo htmlspecialchars($registrar); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rdas-form-group">
                        <label class="rdas-label">Price Range</label>
                        <select class="rdas-select" id="filter-price-range" style="width: 150px;">
                            <option value="">All Prices</option>
                            <?php foreach ($vars['filter_options']['price_ranges'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rdas-form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button class="rdas-btn rdas-btn-primary" id="apply-filters">
                            <i class="fa fa-search"></i> Apply
                        </button>
                        <button class="rdas-btn rdas-btn-ghost" id="clear-filters">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Table Card -->
        <div class="rdas-card">
            <div class="rdas-card-header">
                <h3 class="rdas-card-title">
                    <i class="fa fa-table"></i>
                    Domain Pricing Comparison
                </h3>
                <div class="rdas-flex rdas-gap-2">
                    <label class="rdas-checkbox">
                        <input type="checkbox" id="select-all">
                        <span class="rdas-checkbox-label">Select All</span>
                    </label>
                </div>
            </div>
            <div class="rdas-table-wrapper">
                <table class="rdas-table" id="pricing-table">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="select-all-header"></th>
                            <th>TLD</th>
                            <th>Current Price</th>
                            <th>Promo Price<br><small style="font-weight:normal">(inc + margin)</small></th>
                            <th>Price Diff</th>
                            <th>Year</th>
                            <th>Group</th>
                            <th>Registrar</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($vars['pricing_data'])): ?>
                        <?php foreach ($vars['pricing_data'] as $domain): ?>
                        <?php
                        $rowClass = '';
                        if ($domain['promo_active']) {
                            $rowClass = 'rdas-row-promo';
                        } elseif ($domain['has_api_data'] && $domain['register_diff'] != 0) {
                            $rowClass = $domain['register_diff'] < 0 ? 'rdas-row-success' : 'rdas-row-warning';
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>" data-id="<?php echo $domain['id']; ?>" data-extension="<?php echo htmlspecialchars($domain['extension']); ?>">
                            <td>
                                <input type="checkbox" class="domain-checkbox rdas-row-checkbox" value="<?php echo $domain['id']; ?>">
                            </td>
                            <td>
                                <span class="rdas-tld"><?php echo htmlspecialchars($domain['extension']); ?></span>
                                <?php if ($domain['promo_active']): ?>
                                <span class="rdas-promo-indicator"><i class="fa fa-tag"></i> PROMO YR<?php echo $domain['promo_terms']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rdas-price">Rp <?php echo number_format($domain['current_register'], 0); ?></span>
                                <small class="rdas-tld-small">Renew: Rp <?php echo number_format($domain['current_renew'], 0); ?></small>
                            </td>
                            <td>
                                <?php if ($domain['has_api_data']): ?>
                                    <?php if ($domain['promo_active']): ?>
                                        <span class="rdas-price-original">Rp <?php echo number_format($domain['api_register'], 0); ?></span>
                                        <span class="rdas-price-promo">Rp <?php echo number_format($domain['promo_register'], 0); ?></span>
                                        <small class="rdas-tld-small" style="color: #e74c3c;">Promo Year <?php echo $domain['promo_terms']; ?></small>
                                        <?php if ($domain['promo_end']): ?>
                                        <small class="rdas-promo-countdown"><i class="fa fa-clock"></i> Until: <?php echo date('M j, Y', strtotime($domain['promo_end'])); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="rdas-price">Rp <?php echo number_format($domain['api_register'], 0); ?></span>
                                        <small class="rdas-tld-small">Renew: Rp <?php echo number_format($domain['api_renew'], 0); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="rdas-text-muted">No API data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($domain['has_api_data']): ?>
                                    <?php
                                    $diff = $domain['register_diff'];
                                    $diffClass = $diff < 0 ? 'rdas-text-success' : ($diff > 0 ? 'rdas-text-warning' : 'rdas-text-muted');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="<?php echo $diffClass; ?>" style="font-weight: 600;">
                                        <?php echo $diffSign; ?>Rp <?php echo number_format(abs($diff), 0); ?>
                                    </span>
                                    <?php if ($diff != 0): ?>
                                    <small class="rdas-tld-small">
                                        <?php if ($diff < 0): ?>
                                            <i class="fa fa-arrow-down"></i> Cheaper
                                        <?php else: ?>
                                            <i class="fa fa-arrow-up"></i> More expensive
                                        <?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="rdas-text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($domain['promo_active']): ?>
                                    <span style="color: #e74c3c; font-weight: 600;">Year <?php echo $domain['promo_terms']; ?></span>
                                <?php else: ?>
                                    <span>1 Year</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($domain['domain_group'])): ?>
                                    <span class="rdas-badge rdas-badge-info"><?php echo htmlspecialchars($domain['domain_group']); ?></span>
                                <?php else: ?>
                                    <span class="rdas-text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rdas-status <?php echo !empty($domain['autoreg']) ? 'rdas-status-active' : 'rdas-status-inactive'; ?>">
                                    <?php echo htmlspecialchars($domain['registrar_name']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="rdas-flex rdas-gap-1">
                                    <?php if ($domain['has_api_data'] && $domain['register_diff'] != 0): ?>
                                    <button class="rdas-btn rdas-btn-sm rdas-btn-success sync-price-btn"
                                            data-id="<?php echo $domain['id']; ?>"
                                            data-extension="<?php echo htmlspecialchars($domain['extension']); ?>"
                                            title="Sync to Promo Price">
                                        <i class="fa fa-sync"></i> Sync
                                    </button>
                                    <?php elseif ($domain['has_api_data']): ?>
                                    <span class="rdas-status rdas-status-active"><i class="fa fa-check"></i> Synced</span>
                                    <?php else: ?>
                                    <button class="rdas-btn rdas-btn-sm rdas-btn-ghost edit-price-btn"
                                            data-id="<?php echo $domain['id']; ?>"
                                            title="Edit Manually">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="9" class="rdas-text-center rdas-text-muted" style="padding: 40px;">
                                <div class="rdas-empty">
                                    <div class="rdas-empty-icon"><i class="fa fa-table"></i></div>
                                    <div class="rdas-empty-title">No Pricing Data</div>
                                    <div class="rdas-empty-text">No domain pricing data found in WHMCS</div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="rdas-card-footer" id="bulk-actions-bar" style="display: none;">
                <div class="rdas-flex-between">
                    <span><strong id="selected-count">0</strong> domains selected</span>
                    <div class="rdas-flex rdas-gap-1">
                        <button class="rdas-btn rdas-btn-sm rdas-btn-success" id="bulk-sync-btn">
                            <i class="fa fa-sync"></i> Sync Selected
                        </button>
                        <button class="rdas-btn rdas-btn-sm rdas-btn-primary" id="bulk-margin-selected-btn">
                            <i class="fa fa-percent"></i> Apply Margin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Price Modal -->
    <div class="rdas-modal-backdrop" id="editPriceModal">
        <div class="rdas-modal">
            <div class="rdas-modal-header">
                <h3 class="rdas-modal-title">Edit Domain Pricing</h3>
                <button class="rdas-modal-close" onclick="RDAS.closeModal()">&times;</button>
            </div>
            <div class="rdas-modal-body">
                <input type="hidden" id="edit-domain-id">
                <div class="rdas-form-group">
                    <label class="rdas-label">TLD Extension</label>
                    <input type="text" class="rdas-input" id="edit-extension" readonly>
                </div>
                <div class="rdas-flex rdas-gap-2">
                    <div class="rdas-form-group" style="flex: 1;">
                        <label class="rdas-label">Register Price</label>
                        <input type="number" class="rdas-input" id="edit-register" step="0.01">
                    </div>
                    <div class="rdas-form-group" style="flex: 1;">
                        <label class="rdas-label">Renew Price</label>
                        <input type="number" class="rdas-input" id="edit-renew" step="0.01">
                    </div>
                </div>
                <div class="rdas-flex rdas-gap-2">
                    <div class="rdas-form-group" style="flex: 1;">
                        <label class="rdas-label">Transfer Price</label>
                        <input type="number" class="rdas-input" id="edit-transfer" step="0.01">
                    </div>
                    <div class="rdas-form-group" style="flex: 1;">
                        <label class="rdas-label">Restore Price</label>
                        <input type="number" class="rdas-input" id="edit-restore" step="0.01">
                    </div>
                </div>
            </div>
            <div class="rdas-modal-footer">
                <button class="rdas-btn rdas-btn-ghost" onclick="RDAS.closeModal()">Cancel</button>
                <button class="rdas-btn rdas-btn-primary" id="save-price-btn">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Bulk Margin Modal -->
    <div class="rdas-modal-backdrop" id="bulkMarginModal">
        <div class="rdas-modal">
            <div class="rdas-modal-header">
                <h3 class="rdas-modal-title">Bulk Margin Update</h3>
                <button class="rdas-modal-close" onclick="RDAS.closeModal()">&times;</button>
            </div>
            <div class="rdas-modal-body">
                <div class="rdas-form-group">
                    <label class="rdas-label">Margin Type</label>
                    <select class="rdas-select" id="bulk-margin-type">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (Rp)</option>
                    </select>
                </div>
                <div class="rdas-form-group">
                    <label class="rdas-label">Margin Value</label>
                    <input type="number" class="rdas-input" id="bulk-margin-value" step="0.01" placeholder="e.g., 10 for 10%">
                </div>
                <div class="rdas-form-group">
                    <label class="rdas-label">Apply To</label>
                    <div class="rdas-flex rdas-gap-2" style="flex-wrap: wrap;">
                        <label class="rdas-checkbox">
                            <input type="checkbox" id="apply-register" checked>
                            <span class="rdas-checkbox-label">Register</span>
                        </label>
                        <label class="rdas-checkbox">
                            <input type="checkbox" id="apply-renew" checked>
                            <span class="rdas-checkbox-label">Renew</span>
                        </label>
                        <label class="rdas-checkbox">
                            <input type="checkbox" id="apply-transfer">
                            <span class="rdas-checkbox-label">Transfer</span>
                        </label>
                    </div>
                </div>
                <div class="rdas-alert rdas-alert-info">
                    <i class="fa fa-info-circle"></i>
                    This will update <strong id="margin-preview-count">0</strong> selected domains.
                </div>
            </div>
            <div class="rdas-modal-footer">
                <button class="rdas-btn rdas-btn-ghost" onclick="RDAS.closeModal()">Cancel</button>
                <button class="rdas-btn rdas-btn-primary" id="apply-bulk-margin-btn">Apply Margin</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Initialize DataTable
        var table = $('#pricing-table').DataTable({
            "pageLength": 25,
            "order": [[ 1, "asc" ]],
            "columnDefs": [
                { "orderable": false, "targets": [0, 7] }
            ]
        });

        // Select all functionality
        $('#select-all, #select-all-header').change(function() {
            var isChecked = $(this).is(':checked');
            $('.domain-checkbox').prop('checked', isChecked);
            updateBulkActions();
        });

        // Individual checkbox change
        $(document).on('change', '.domain-checkbox', function() {
            updateBulkActions();
        });

        // Update bulk actions visibility
        function updateBulkActions() {
            var selectedCount = $('.domain-checkbox:checked').length;
            $('#selected-count').text(selectedCount);
            $('#margin-preview-count').text(selectedCount);

            if (selectedCount > 0) {
                $('#bulk-actions-bar').slideDown();
            } else {
                $('#bulk-actions-bar').slideUp();
            }
        }

        // Edit price button
        $(document).on('click', '.edit-price-btn', function() {
            var domainId = $(this).data('id');
            var row = $(this).closest('tr');

            $('#edit-domain-id').val(domainId);
            $('#edit-extension').val(row.data('extension'));
            $('#edit-register').val(row.find('.rdas-price').eq(0).text().replace(/[^0-9]/g, ''));
            $('#edit-renew').val(row.find('.rdas-price').eq(1).text().replace(/[^0-9]/g, ''));
            $('#edit-transfer').val(row.find('.rdas-price').eq(2).text().replace(/[^0-9]/g, ''));

            $('#editPriceModal').addClass('show');
        });

        // Close modal
        $(document).on('click', '.rdas-modal-backdrop', function(e) {
            if (e.target === this) {
                $(this).removeClass('show');
            }
        });

        // Save price changes
        $('#save-price-btn').click(function() {
            var domainId = $('#edit-domain-id').val();
            var prices = {
                register: $('#edit-register').val(),
                renew: $('#edit-renew').val(),
                transfer: $('#edit-transfer').val(),
                restore: $('#edit-restore').val()
            };

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'update_domain_price',
                domain_id: domainId,
                prices: prices,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('Save Changes');
                if (response.success) {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('success', 'Success', 'Price updated successfully');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Error', response.message);
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            }, 'json');
        });

        // Sync individual price
        $(document).on('click', '.sync-price-btn', function() {
            var btn = $(this);
            var domainId = btn.data('id');
            var extension = btn.data('extension');

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'sync_domain_price',
                domain_id: domainId,
                extension: extension,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-sync"></i>');
                if (response.success) {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('success', 'Synced', extension + ' price updated');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Sync Failed', response.message);
                    } else {
                        alert('Sync failed: ' + response.message);
                    }
                }
            }, 'json');
        });

        // Sync all prices
        $('#sync-all-btn').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'bulk_sync_prices',
                domain_ids: $('.domain-checkbox').map(function() { return $(this).val(); }).get(),
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-sync"></i> Sync All Prices');
                if (response.success) {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('success', 'Sync Complete', response.message);
                    }
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Sync Failed', response.message);
                    }
                }
            }, 'json');
        });

        // Bulk sync
        $('#bulk-sync-btn').click(function() {
            var selectedIds = $('.domain-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) {
                if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                    RDAS.showToast('warning', 'No Selection', 'Please select domains to sync');
                }
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Syncing...');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'bulk_sync_prices',
                domain_ids: selectedIds,
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-sync"></i> Sync Selected');
                if (response.success) {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('success', 'Sync Complete', response.message);
                    }
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Sync Failed', response.message);
                    }
                }
            }, 'json');
        });

        // Bulk margin modal
        $('#bulk-margin-btn, #bulk-margin-selected-btn').click(function() {
            $('#bulkMarginModal').addClass('show');
        });

        // Apply bulk margin
        $('#apply-bulk-margin-btn').click(function() {
            var selectedIds = $('.domain-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) {
                if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                    RDAS.showToast('warning', 'No Selection', 'Please select domains');
                }
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Applying...');

            $.post('<?php echo $vars['modulelink']; ?>&action=ajax', {
                operation: 'bulk_apply_margin',
                domain_ids: selectedIds,
                margin_type: $('#bulk-margin-type').val(),
                margin_value: $('#bulk-margin-value').val(),
                apply_to: {
                    register: $('#apply-register').is(':checked'),
                    renew: $('#apply-renew').is(':checked'),
                    transfer: $('#apply-transfer').is(':checked')
                },
                csrf_token: '<?php echo $vars['csrf_token']; ?>'
            }, function(response) {
                btn.prop('disabled', false).html('Apply Margin');
                if (response.success) {
                    $('#bulkMarginModal').removeClass('show');
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('success', 'Success', response.message);
                    }
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    if (typeof RDAS !== 'undefined' && RDAS.showToast) {
                        RDAS.showToast('error', 'Error', response.message);
                    }
                }
            }, 'json');
        });

        // Export CSV
        $('#export-csv-btn').click(function() {
            window.location.href = '<?php echo $vars['modulelink']; ?>&action=export_csv';
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

        // Filters
        $('#apply-filters').click(function() {
            var searchTld = $('#search-tld').val();
            table.search(searchTld).draw();
        });

        $('#clear-filters').click(function() {
            $('#search-tld').val('');
            $('#filter-registrar').val('');
            $('#filter-price-range').val('');
            table.search('').draw();
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
