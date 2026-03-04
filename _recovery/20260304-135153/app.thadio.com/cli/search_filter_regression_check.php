<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * Lightweight regression checks for search/filter truncation risks.
 */
final class SearchFilterRegressionCheck
{
    /** @var array<int, string> */
    private array $failures = [];

    public function run(): int
    {
        $this->checkHardcodedLimitsRemoved();
        $this->checkServerModeEnabledForPaginatedConsignmentTables();
        $this->checkControllerFilterBindings();
        $this->checkConsignmentNoSilentLimit50();
        $this->checkProductWriteoffServerPagination();
        $this->checkPeopleListServerPagination();
        $this->checkVoucherAccountsServerPagination();
        $this->checkFinanceListServerPagination();
        $this->checkTimeclockListServerPagination();
        $this->checkCustomerPurchasesNoClientSideSlice();
        $this->checkServerModeMultiSelectGuard();
        $this->checkNoPaginatedClientSideTables();

        // === Phase 2 audit fixes (BUSCA_ID) ===
        $this->checkRepositoryDefaultLimitZero();
        $this->checkDeliveryTrackingNoHardcodedLimit();
        $this->checkConsignmentIntegrityNoHardcodedLimit();
        $this->checkCustomerHistoryNoHardCap();

        if (!empty($this->failures)) {
            fwrite(STDERR, "Search/filter regression checks FAILED:\n");
            foreach ($this->failures as $failure) {
                fwrite(STDERR, " - {$failure}\n");
            }
            return 1;
        }

        fwrite(STDOUT, "Search/filter regression checks passed.\n");
        return 0;
    }

    private function checkHardcodedLimitsRemoved(): void
    {
        $targets = [
            __DIR__ . '/../app/Controllers/OrderController.php',
            __DIR__ . '/../app/Controllers/VoucherAccountController.php',
            __DIR__ . '/../app/Controllers/CustomerPurchaseController.php',
        ];

        foreach ($targets as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                $this->failures[] = "Unable to read {$path}";
                continue;
            }

            if (preg_match('/LIMIT\s+5000/i', $content) === 1) {
                $this->failures[] = "Found forbidden LIMIT 5000 in {$path}";
            }
            if (preg_match('/LIMIT\s+1000/i', $content) === 1) {
                $this->failures[] = "Found forbidden LIMIT 1000 in {$path}";
            }
            if (preg_match('/\b3000\b/', $content) === 1) {
                $this->failures[] = "Found forbidden fixed window 3000 in {$path}";
            }
        }
    }

    private function checkServerModeEnabledForPaginatedConsignmentTables(): void
    {
        $views = [
            __DIR__ . '/../app/Views/consignment_module/products.php',
            __DIR__ . '/../app/Views/consignment_module/sales.php',
            __DIR__ . '/../app/Views/consignment_module/payout_list.php',
        ];

        foreach ($views as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                $this->failures[] = "Unable to read {$path}";
                continue;
            }

            if (strpos($content, 'data-filter-mode="server"') === false) {
                $this->failures[] = "Server filter mode is missing in {$path}";
            }
        }
    }

    private function checkControllerFilterBindings(): void
    {
        $path = __DIR__ . '/../app/Controllers/ConsignmentModuleController.php';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }

        $requiredSnippets = [
            "filter_sku",
            "filter_product_name",
            "filter_supplier_name",
            "filter_order_id",
            "filter_sale_status",
            "filter_payout_status",
            "filter_reference",
            "filter_total_amount",
        ];

        foreach ($requiredSnippets as $snippet) {
            if (strpos($content, $snippet) === false) {
                $this->failures[] = "Missing filter binding '{$snippet}' in ConsignmentModuleController";
            }
        }
    }

    private function checkConsignmentNoSilentLimit50(): void
    {
        $path = __DIR__ . '/../app/Controllers/ConsignmentModuleController.php';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }

        if (preg_match('/LIMIT\s+50/i', $content) === 1) {
            $this->failures[] = "Found forbidden LIMIT 50 in {$path}";
        }
        if (preg_match('/LIMIT\s+200/i', $content) === 1) {
            $this->failures[] = "Found forbidden LIMIT 200 in {$path}";
        }
    }

    private function checkProductWriteoffServerPagination(): void
    {
        $viewPath = __DIR__ . '/../app/Views/products/form.php';
        $controllerPath = __DIR__ . '/../app/Controllers/ProductController.php';

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-page-param="writeoff_page"') === false) {
            $this->failures[] = "Writeoff table page parameter is missing in {$viewPath}";
        }
        if (strpos($view, 'data-filter-mode="server"') === false) {
            $this->failures[] = "Writeoff table server mode is missing in {$viewPath}";
        }

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        if (strpos($controller, 'countForProduct(') === false || strpos($controller, 'paginateForProduct(') === false) {
            $this->failures[] = "ProductController is not using count/paginate for writeoff history";
        }
    }

    private function checkCustomerPurchasesNoClientSideSlice(): void
    {
        $controllerPath = __DIR__ . '/../app/Controllers/CustomerPurchaseController.php';
        $viewPath = __DIR__ . '/../app/Views/customers/purchases.php';

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        $requiredControllerSnippets = [
            'countCustomerPurchaseOrders(',
            'countCustomerPurchasedProducts(',
            'per_page',
            'page',
        ];
        foreach ($requiredControllerSnippets as $snippet) {
            if (strpos($controller, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in CustomerPurchaseController pagination flow";
            }
        }

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-filter-global') !== false) {
            $this->failures[] = "customers/purchases still contains client-side global filter";
        }
        if (strpos($view, 'data-table="interactive"') !== false) {
            $this->failures[] = "customers/purchases still uses interactive client-side table mode";
        }
    }

    private function checkPeopleListServerPagination(): void
    {
        $controllerPath = __DIR__ . '/../app/Controllers/PersonController.php';
        $viewPath = __DIR__ . '/../app/Views/pessoas/list.php';

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        $requiredControllerSnippets = [
            'countForList(',
            'paginateForList(',
            'per_page',
            'page',
            'sort_key',
            'filter_',
        ];
        foreach ($requiredControllerSnippets as $snippet) {
            if (strpos($controller, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in PersonController pagination flow";
            }
        }

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-filter-mode="server"') === false) {
            $this->failures[] = "pessoas/list is missing server filter mode";
        }
    }

    private function checkVoucherAccountsServerPagination(): void
    {
        $controllerPath = __DIR__ . '/../app/Controllers/VoucherAccountController.php';
        $viewPath = __DIR__ . '/../app/Views/voucher_accounts/list.php';

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        $requiredControllerSnippets = [
            'countForList(',
            'paginateForList(',
            'per_page',
            'page',
            'sort_key',
            'filter_',
        ];
        foreach ($requiredControllerSnippets as $snippet) {
            if (strpos($controller, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in VoucherAccountController pagination flow";
            }
        }

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-filter-mode="server"') === false) {
            $this->failures[] = "voucher_accounts/list is missing server filter mode";
        }
    }

    private function checkFinanceListServerPagination(): void
    {
        $controllerPath = __DIR__ . '/../app/Controllers/FinanceController.php';
        $repositoryPath = __DIR__ . '/../app/Repositories/FinanceEntryRepository.php';
        $viewPath = __DIR__ . '/../app/Views/finance/list.php';

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        $requiredControllerSnippets = [
            'countForList(',
            'paginateForList(',
            'per_page',
            'page',
            'sort_key',
            'filter_',
        ];
        foreach ($requiredControllerSnippets as $snippet) {
            if (strpos($controller, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in FinanceController pagination flow";
            }
        }

        $repository = @file_get_contents($repositoryPath);
        if ($repository === false) {
            $this->failures[] = "Unable to read {$repositoryPath}";
            return;
        }
        $requiredRepositorySnippets = [
            'public function countForList(',
            'public function paginateForList(',
            'public function statementSummary(',
        ];
        foreach ($requiredRepositorySnippets as $snippet) {
            if (strpos($repository, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in FinanceEntryRepository";
            }
        }

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-filter-mode="server"') === false) {
            $this->failures[] = "finance/list is missing server filter mode";
        }
    }

    private function checkTimeclockListServerPagination(): void
    {
        $controllerPath = __DIR__ . '/../app/Controllers/TimeClockController.php';
        $repositoryPath = __DIR__ . '/../app/Repositories/TimeEntryRepository.php';
        $viewPath = __DIR__ . '/../app/Views/timeclock/list.php';

        $controller = @file_get_contents($controllerPath);
        if ($controller === false) {
            $this->failures[] = "Unable to read {$controllerPath}";
            return;
        }
        $requiredControllerSnippets = [
            'countForList(',
            'paginateForList(',
            'per_page',
            'page',
            'sort_key',
            'filter_',
        ];
        foreach ($requiredControllerSnippets as $snippet) {
            if (strpos($controller, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in TimeClockController pagination flow";
            }
        }

        $repository = @file_get_contents($repositoryPath);
        if ($repository === false) {
            $this->failures[] = "Unable to read {$repositoryPath}";
            return;
        }
        $requiredRepositorySnippets = [
            'public function countForList(',
            'public function paginateForList(',
        ];
        foreach ($requiredRepositorySnippets as $snippet) {
            if (strpos($repository, $snippet) === false) {
                $this->failures[] = "Missing '{$snippet}' in TimeEntryRepository";
            }
        }

        $view = @file_get_contents($viewPath);
        if ($view === false) {
            $this->failures[] = "Unable to read {$viewPath}";
            return;
        }
        if (strpos($view, 'data-filter-mode="server"') === false) {
            $this->failures[] = "timeclock/list is missing server filter mode";
        }
    }

    private function checkServerModeMultiSelectGuard(): void
    {
        $path = __DIR__ . '/../assets/table.js';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }

        if (strpos($content, 'const multiSelectEnabled = !serverMode || hasRemoteOptions;') === false) {
            $this->failures[] = "table.js is missing server multi-select guard";
        }
        if (strpos($content, 'if (!multiSelectEnabled) {') === false) {
            $this->failures[] = "table.js is missing fallback when remote options are absent";
        }
    }

    private function checkNoPaginatedClientSideTables(): void
    {
        $viewsRoot = realpath(__DIR__ . '/../app/Views');
        if ($viewsRoot === false) {
            $this->failures[] = "Unable to resolve app/Views directory";
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getRealPath();
            if (!is_string($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            if (strpos($content, 'data-table="interactive"') === false) {
                continue;
            }
            if (strpos($content, 'data-filter-mode="server"') !== false) {
                continue;
            }
            if (preg_match('/Mostrando|Página|per_page|page=/', $content) !== 1) {
                continue;
            }
            $this->failures[] = "Paginated interactive table without server mode: {$path}";
        }
    }

    // =========================================================================
    // Phase 2 – BUSCA_ID audit: ensure hardcoded limits stay removed
    // =========================================================================

    /**
     * Verify that key repositories default to $limit = 0 (no limit) on their
     * non-paginated listing methods.  If a signature is changed back to a
     * hardcoded default > 0 the check will fire.
     */
    private function checkRepositoryDefaultLimitZero(): void
    {
        $checks = [
            [
                'file' => __DIR__ . '/../app/Repositories/CustomerRepository.php',
                'method' => 'function search',
                'forbidden' => '/function\s+search\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'CustomerRepository::search() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/ProductWriteOffRepository.php',
                'method' => 'function listBySupplier',
                'forbidden' => '/function\s+listBySupplier\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'ProductWriteOffRepository::listBySupplier() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/ProductWriteOffRepository.php',
                'method' => 'function listRecent',
                'forbidden' => '/function\s+listRecent\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'ProductWriteOffRepository::listRecent() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/ProductWriteOffRepository.php',
                'method' => 'function listForProduct',
                'forbidden' => '/function\s+listForProduct\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'ProductWriteOffRepository::listForProduct() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/CreditEntryRepository.php',
                'method' => 'function listByAccount',
                'forbidden' => '/function\s+listByAccount\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'CreditEntryRepository::listByAccount() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/OrderRepository.php',
                'method' => 'function listOrdersForProduct',
                'forbidden' => '/function\s+listOrdersForProduct\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'OrderRepository::listOrdersForProduct() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/OrderRepository.php',
                'method' => 'function listDeliveries',
                'forbidden' => '/function\s+listDeliveries\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'OrderRepository::listDeliveries() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/OrderRepository.php',
                'method' => 'function listCustomerPurchaseOrders',
                'forbidden' => '/function\s+listCustomerPurchaseOrders\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'OrderRepository::listCustomerPurchaseOrders() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/OrderRepository.php',
                'method' => 'function listCustomerPurchasedProducts',
                'forbidden' => '/function\s+listCustomerPurchasedProducts\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'OrderRepository::listCustomerPurchasedProducts() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/FinanceEntryRepository.php',
                'method' => 'function listPayablesWindow',
                'forbidden' => '/function\s+listPayablesWindow\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'FinanceEntryRepository::listPayablesWindow() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/CommemorativeDateRepository.php',
                'method' => 'function listForDates',
                'forbidden' => '/function\s+listForDates\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'CommemorativeDateRepository::listForDates() default limit must be 0',
            ],
            [
                'file' => __DIR__ . '/../app/Repositories/CustomerHistoryRepository.php',
                'method' => 'function listByCustomer',
                'forbidden' => '/function\s+listByCustomer\s*\([^)]*\$limit\s*=\s*[1-9]/',
                'label' => 'CustomerHistoryRepository::listByCustomer() default limit must be 0',
            ],
        ];

        foreach ($checks as $check) {
            $content = @file_get_contents($check['file']);
            if ($content === false) {
                $this->failures[] = "Unable to read {$check['file']}";
                continue;
            }
            if (preg_match($check['forbidden'], $content) === 1) {
                $this->failures[] = $check['label'];
            }
        }
    }

    /**
     * DeliveryTrackingController must NOT pass a hardcoded limit to listDeliveries.
     */
    private function checkDeliveryTrackingNoHardcodedLimit(): void
    {
        $path = __DIR__ . '/../app/Controllers/DeliveryTrackingController.php';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }
        // Ensure no hardcoded numeric limit in listDeliveries call
        if (preg_match('/listDeliveries\s*\([^)]*,\s*\d{2,}/', $content) === 1) {
            $this->failures[] = "DeliveryTrackingController passes hardcoded limit to listDeliveries()";
        }
    }

    /**
     * ConsignmentIntegrityService::getCheckDetails must default to $limit = 0
     * and must NOT have unconditional LIMIT :lim in the base queries.
     */
    private function checkConsignmentIntegrityNoHardcodedLimit(): void
    {
        $path = __DIR__ . '/../app/Services/ConsignmentIntegrityService.php';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }
        // Default must be 0
        if (preg_match('/function\s+getCheckDetails\s*\([^)]*\$limit\s*=\s*[1-9]/', $content) === 1) {
            $this->failures[] = "ConsignmentIntegrityService::getCheckDetails() default limit must be 0";
        }
        // Base query strings must not contain LIMIT (only the conditional block should)
        if (preg_match('/["\']SELECT\s+.*LIMIT\s+:lim/i', $content) === 1) {
            $this->failures[] = "ConsignmentIntegrityService has LIMIT :lim baked into a base query string";
        }
    }

    /**
     * CustomerHistoryRepository must not contain a hardcoded cap like `$limit > 50 ? 50 : $limit`
     */
    private function checkCustomerHistoryNoHardCap(): void
    {
        $path = __DIR__ . '/../app/Repositories/CustomerHistoryRepository.php';
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->failures[] = "Unable to read {$path}";
            return;
        }
        if (preg_match('/\$limit\s*>\s*50\s*\?\s*50/', $content) === 1) {
            $this->failures[] = "CustomerHistoryRepository still has hardcoded cap of 50";
        }
        if (preg_match('/min\s*\(\s*50\s*,\s*\$limit\s*\)/', $content) === 1) {
            $this->failures[] = "CustomerHistoryRepository still has min(50, limit) cap";
        }
    }
}

$check = new SearchFilterRegressionCheck();
exit($check->run());
