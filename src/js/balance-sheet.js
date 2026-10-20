/**
 * Balance Sheet Module
 * Handles fetching, rendering, filtering, printing, and exporting.
 */

(function() {
    'use strict';

    // DOM Elements
    const fromDateInput = document.getElementById('fromDate');
    const toDateInput = document.getElementById('toDate');
    const applyBtn = document.getElementById('applyFilterBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const balanceSheetContent = document.getElementById('balanceSheetContent');
    const emptyState = document.getElementById('emptyState');
    const dateRangeDisplay = document.getElementById('dateRangeDisplay');
    const printDateRange = document.getElementById('printDateRange');
    const balanceStatus = document.getElementById('balanceStatus');
    const differenceDisplay = document.getElementById('differenceDisplay');

    // Container elements
    const currentAssetsList = document.getElementById('currentAssetsList');
    const fixedAssetsList = document.getElementById('fixedAssetsList');
    const currentLiabilitiesList = document.getElementById('currentLiabilitiesList');
    const longTermLiabilitiesList = document.getElementById('longTermLiabilitiesList');
    const equityList = document.getElementById('equityList');

    // Total elements
    const totalAssetsEl = document.getElementById('totalAssets');
    const totalLiabilitiesEl = document.getElementById('totalLiabilities');
    const totalEquityEl = document.getElementById('totalEquity');
    const totalLiabilitiesEquityEl = document.getElementById('totalLiabilitiesEquity');
    const balanceCheckEl = document.getElementById('balanceCheck');

    // API endpoint
    const API_URL = '../../action/accounting/get_balance_sheet.php';

    // Current data cache
    let currentData = null;

    // Initialize dates to last 12 months
    function initDates() {
        const now = new Date();
        const oneYearAgo = new Date(now);
        oneYearAgo.setFullYear(now.getFullYear() - 1);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        fromDateInput.value = formatDate(oneYearAgo);
        toDateInput.value = formatDate(now);
    }

    // Fetch balance sheet data
    async function fetchBalanceSheet(from, to) {
        showLoading(true);
        hideError();
        hideEmptyState();
        hideContent();

        try {
            const url = `${API_URL}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load balance sheet.');
            }

            // Check if there's any meaningful data
            const hasData = data.assets.some(a => a.amount !== 0) ||
                           data.liabilities.some(l => l.amount !== 0) ||
                           data.equity.some(e => e.amount !== 0);

            if (!hasData) {
                showEmptyState();
                return;
            }

            currentData = data;
            renderBalanceSheet(data);
            updateDateRangeDisplay(from, to);
            showContent();

        } catch (error) {
            console.error('Balance Sheet Error:', error);
            showError(error.message || 'An unexpected error occurred.');
        } finally {
            showLoading(false);
        }
    }

    // Render the balance sheet
    function renderBalanceSheet(data) {
        // Clear previous content
        currentAssetsList.innerHTML = '';
        fixedAssetsList.innerHTML = '';
        currentLiabilitiesList.innerHTML = '';
        longTermLiabilitiesList.innerHTML = '';
        equityList.innerHTML = '';

        // Separate assets into current and fixed
        const currentAssetNames = ['Cash in Hand', 'Bank Account', 'Accounts Receivable', 'Inventory / Stock'];
        const fixedAssetNames = ['Furniture & Machinery', 'Other Assets'];

        let currentAssets = [];
        let fixedAssets = [];

        data.assets.forEach(item => {
            if (currentAssetNames.includes(item.account)) {
                currentAssets.push(item);
            } else {
                fixedAssets.push(item);
            }
        });

        // Render Current Assets
        currentAssets.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700 text-gray-700 dark:text-gray-300';
            div.innerHTML = `
                <span>${item.account}</span>
                <span class="font-medium">${formatCurrency(item.amount)}</span>
            `;
            currentAssetsList.appendChild(div);
        });

        // Render Fixed Assets
        fixedAssets.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700 text-gray-700 dark:text-gray-300';
            div.innerHTML = `
                <span>${item.account}</span>
                <span class="font-medium">${formatCurrency(item.amount)}</span>
            `;
            fixedAssetsList.appendChild(div);
        });

        // Separate liabilities
        const currentLiabilityNames = ['Accounts Payable'];
        const longTermLiabilityNames = ['Loans'];

        let currentLiabilities = [];
        let longTermLiabilities = [];

        data.liabilities.forEach(item => {
            if (currentLiabilityNames.includes(item.account)) {
                currentLiabilities.push(item);
            } else {
                longTermLiabilities.push(item);
            }
        });

        // Render Current Liabilities
        currentLiabilities.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700 text-gray-700 dark:text-gray-300';
            div.innerHTML = `
                <span>${item.account}</span>
                <span class="font-medium">${formatCurrency(item.amount)}</span>
            `;
            currentLiabilitiesList.appendChild(div);
        });

        // Render Long Term Liabilities
        longTermLiabilities.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700 text-gray-700 dark:text-gray-300';
            div.innerHTML = `
                <span>${item.account}</span>
                <span class="font-medium">${formatCurrency(item.amount)}</span>
            `;
            longTermLiabilitiesList.appendChild(div);
        });

        // Render Equity
        data.equity.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-700 text-gray-700 dark:text-gray-300';
            const isProfit = item.account === 'Current Year Profit';
            const colorClass = isProfit && item.amount < 0 ? 'text-red-600' : '';
            div.innerHTML = `
                <span>${item.account}</span>
                <span class="font-medium ${colorClass}">${formatCurrency(item.amount)}</span>
            `;
            equityList.appendChild(div);
        });

        // Update totals
        totalAssetsEl.textContent = formatCurrency(data.total_assets);
        totalLiabilitiesEl.textContent = formatCurrency(data.total_liabilities);
        totalEquityEl.textContent = formatCurrency(data.total_equity);
        totalLiabilitiesEquityEl.textContent = formatCurrency(data.total_liabilities_equity);

        // Balance status
        updateBalanceStatus(data);
    }

    // Update balance status badge
    function updateBalanceStatus(data) {
        const diff = Math.abs(data.total_assets - data.total_liabilities_equity);

        if (data.balanced) {
            balanceStatus.innerHTML = `
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <i class="fas fa-check-circle mr-2"></i> Balanced ✓
                </span>
            `;
            balanceCheckEl.className = 'mt-2 text-sm font-medium text-green-600 dark:text-green-400';
            balanceCheckEl.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Balance Sheet is balanced.';
            differenceDisplay.textContent = '';
        } else {
            balanceStatus.innerHTML = `
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Not Balanced ✗
                </span>
            `;
            balanceCheckEl.className = 'mt-2 text-sm font-medium text-red-600 dark:text-red-400';
            balanceCheckEl.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Balance Sheet is NOT balanced.';
            differenceDisplay.textContent = `Difference: ${formatCurrency(diff)} (Assets - Liabilities - Equity)`;
        }
    }

    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // Update date range display
    function updateDateRangeDisplay(from, to) {
        const fromFormatted = new Date(from + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
        const toFormatted = new Date(to + 'T00:00:00').toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
        const display = `${fromFormatted} - ${toFormatted}`;
        dateRangeDisplay.textContent = display;
        if (printDateRange) {
            printDateRange.textContent = display;
        }
    }

    // UI Helpers
    function showLoading(show) {
        loadingSpinner.classList.toggle('hidden', !show);
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.classList.remove('hidden');
    }

    function hideError() {
        errorMessage.classList.add('hidden');
    }

    function showContent() {
        balanceSheetContent.classList.remove('hidden');
        emptyState.classList.add('hidden');
        balanceStatus.classList.remove('hidden');
        differenceDisplay.classList.remove('hidden');
    }

    function hideContent() {
        balanceSheetContent.classList.add('hidden');
        balanceStatus.classList.add('hidden');
        differenceDisplay.classList.add('hidden');
    }

    function showEmptyState() {
        emptyState.classList.remove('hidden');
        balanceSheetContent.classList.add('hidden');
        balanceStatus.classList.add('hidden');
        differenceDisplay.classList.add('hidden');
    }

    function hideEmptyState() {
        emptyState.classList.add('hidden');
    }

    // Export to Excel (CSV)
    function exportExcel() {
        if (!currentData) {
            alert('No data to export. Please load the balance sheet first.');
            return;
        }

        const rows = [];
        // Header
        rows.push(['Account', 'Category', 'Amount']);

        // Assets
        currentData.assets.forEach(item => {
            rows.push([item.account, 'Asset', item.amount]);
        });
        rows.push(['TOTAL ASSETS', '', currentData.total_assets]);

        // Liabilities
        currentData.liabilities.forEach(item => {
            rows.push([item.account, 'Liability', item.amount]);
        });
        rows.push(['TOTAL LIABILITIES', '', currentData.total_liabilities]);

        // Equity
        currentData.equity.forEach(item => {
            rows.push([item.account, 'Equity', item.amount]);
        });
        rows.push(['TOTAL EQUITY', '', currentData.total_equity]);

        rows.push([]);
        rows.push(['TOTAL LIABILITIES + EQUITY', '', currentData.total_liabilities_equity]);
        rows.push(['Balanced', '', currentData.balanced ? 'Yes' : 'No']);

        // Convert to CSV
        let csv = rows.map(row => row.join(',')).join('\n');

        // Download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `balance-sheet-${currentData.from}_to_${currentData.to}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // Make export function global for onclick
    window.exportExcel = exportExcel;

    // Event Listeners
    applyBtn.addEventListener('click', function() {
        const from = fromDateInput.value;
        const to = toDateInput.value;

        if (!from || !to) {
            showError('Please select both From and To dates.');
            return;
        }

        if (from > to) {
            showError('From date cannot be later than To date.');
            return;
        }

        fetchBalanceSheet(from, to);
    });

    // Enter key support
    fromDateInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') applyBtn.click();
    });
    toDateInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') applyBtn.click();
    });

    // Initial load
    initDates();
    // Fetch after a short delay
    setTimeout(() => {
        applyBtn.click();
    }, 100);

})();