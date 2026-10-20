
        // =============================================
        // EXISTING DASHBOARD JAVASCRIPT (unchanged)
        // =============================================
        const API_URL          = '../../action/dashboard/dashboard.php';
        const SUMMARY_API_URL  = '../../action/dashboard/dashbord_summery.php';
        const ORDERS_STATS_URL = '../../action/orders/get_orders_stats.php';

        const statsCards = document.getElementById('statsCards');
        const opsStatsCards = document.getElementById('opsStatsCards');
        const warehouseStock = document.getElementById('warehouseStock');
        const totalDelivered = document.getElementById('totalDelivered');
        const totalReturned = document.getElementById('totalReturned');
        const todayTableBody = document.getElementById('todayTableBody');
        const overallTableBody = document.getElementById('overallTableBody');
        const todayBtn = document.getElementById('todayBtn');
        const overallBtn = document.getElementById('overallBtn');
        const todayTableView = document.getElementById('todayTableView');
        const overallTableView = document.getElementById('overallTableView');
        const lastOrdersContainer = document.getElementById('lastOrdersContainer');
        const currentDate = document.getElementById('currentDate');
        const statOrdersCount = document.getElementById('statOrdersCount');
        const statOrdersSale = document.getElementById('statOrdersSale');
        const statOrdersProducts = document.getElementById('statOrdersProducts');

        let currentFilter = 'today';
        let dashboardData = null;
        let summaryData = null;
        let orderStatsData = null;

        function formatCurrency(amount) {
            return parseFloat(amount || 0).toFixed(2);
        }

        function formatNumber(num) {
            return parseInt(num || 0).toLocaleString();
        }

        function getStatusBadge(status) {
            const statusMap = {
                'Completed': 'status-completed',
                'Complete': 'status-complete',
                'Pending': 'status-pending',
                'Returned': 'status-returned',
                'Processing': 'status-processing',
                'Cancelled': 'status-cancelled'
            };
            return statusMap[status] || 'status-pending';
        }

        function getStatusIcon(status) {
            const iconMap = {
                'Completed': 'fa-circle-check',
                'Complete': 'fa-circle-check',
                'Pending': 'fa-clock',
                'Returned': 'fa-rotate-left',
                'Processing': 'fa-spinner',
                'Cancelled': 'fa-circle-xmark'
            };
            return iconMap[status] || 'fa-circle';
        }

        function setCurrentDate() {
            const now = new Date();
            const options = { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' };
            currentDate.textContent = now.toLocaleDateString('en-US', options);
        }

        function renderCards(data, orderStats) {
            const cards = data.cards || {};
            const stock = cards.stock || {};
            const receivable = cards.receivable || {};
            const payable = cards.payable || {};
            const parties = cards.parties || {};
            const purchase = cards.purchase || {};

            const salesOverall = orderStats?.overall?.total_sale ?? cards.sales?.overall ?? 0;
            const salesToday = orderStats?.today?.total_sale ?? cards.sales?.today ?? 0;

            statsCards.innerHTML = `
                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-cyan-400 to-sky-500 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-boxes-stacked text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Stock</span>
                    </div>
                    <p class="text-xs text-white/90">Cost: ${formatNumber(stock.cost)}</p>
                    <p class="text-xs text-white/90">Price: ${formatNumber(stock.price)}</p>
                </div>

                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-cart-shopping text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Sales</span>
                    </div>
                    <p class="text-2xl font-bold">${formatNumber(salesOverall)}</p>
                    <p class="text-xs text-white/70 mt-1">Today: ${formatNumber(salesToday)}</p>
                </div>

                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-green-500 to-emerald-600 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-bag-shopping text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Purchase</span>
                    </div>
                    <p class="text-2xl font-bold">${formatNumber(purchase.overall)}</p>
                    <p class="text-xs text-white/70 mt-1">Today: ${formatNumber(purchase.today)}</p>
                </div>

                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-yellow-400 to-amber-500 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-user-check text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Receivable</span>
                    </div>
                    <p class="text-2xl font-bold">${formatNumber(receivable.overall)}</p>
                    <p class="text-xs text-white/70 mt-1">Today: ${formatNumber(receivable.today)}</p>
                </div>

                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-red-500 to-red-600 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-user-clock text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Payable</span>
                    </div>
                    <p class="text-2xl font-bold">${formatNumber(payable.overall)}</p>
                    <p class="text-xs text-white/70 mt-1">Today: ${formatNumber(payable.today)}</p>
                </div>

                <div class="stat-card rounded-2xl p-4 text-white bg-gradient-to-br from-yellow-500 to-orange-400 shadow-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-users text-white/80 text-lg"></i>
                        <span class="font-semibold text-sm">Parties</span>
                    </div>
                    <p class="text-xs text-white/90">Customers: <span class="font-bold">${formatNumber(parties.customers)}</span></p>
                    <p class="text-xs text-white/90">Suppliers: <span class="font-bold">${formatNumber(parties.suppliers)}</span></p>
                </div>
            `;
        }

        function renderOpsCards(summary) {
            const employeeCount = summary?.employee_count ?? 0;
            const productCount = summary?.product_count ?? 0;

            opsStatsCards.innerHTML = `

                <a href="./employees.html" class="stat-card rounded-2xl p-5 text-white bg-gradient-to-br from-teal-500 to-cyan-600 shadow-lg flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-user-tie text-white/80"></i>
                            <span class="font-semibold text-sm">Employees</span>
                        </div>
                        <p class="text-3xl font-bold">${formatNumber(employeeCount)}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-white/60"></i>
                </a>

                <a href="./product.html" class="stat-card rounded-2xl p-5 text-white bg-gradient-to-br from-pink-500 to-rose-600 shadow-lg flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-box-open text-white/80"></i>
                            <span class="font-semibold text-sm">Products</span>
                        </div>
                        <p class="text-3xl font-bold">${formatNumber(productCount)}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-white/60"></i>
                </a>
            `;
        }

        function renderOrderStatsSummary(orderStats, filter) {
            const bucket = filter === 'today' ? orderStats?.today : orderStats?.overall;
            statOrdersCount.textContent = formatNumber(bucket?.total_orders);
            statOrdersSale.textContent = formatNumber(bucket?.total_sale);
            statOrdersProducts.textContent = formatNumber(bucket?.total_products);
        }

        function renderDeliveryTables(data, filter) {
            const delivery = data.delivery_summary || {};
            const delivered = delivery.delivered || {};
            const returned = delivery.returned || {};

            const todayDelivered = delivered.today || {};
            const todayReturned = returned.today || {};
            const overallDelivered = delivered.overall || {};
            const overallReturned = returned.overall || {};

            if (filter === 'today') {
                totalDelivered.textContent = formatNumber(todayDelivered.orders || 0);
                totalReturned.textContent = formatNumber(todayReturned.orders || 0);
            } else {
                totalDelivered.textContent = formatNumber(overallDelivered.orders || 0);
                totalReturned.textContent = formatNumber(overallReturned.orders || 0);
            }

            const stock = data.cards?.stock || {};
            warehouseStock.textContent = `Warehouse Stock: ${formatNumber(stock.warehouse_stock || 0)}`;

            if (filter === 'today') {
                todayTableView.classList.remove('hidden');
                overallTableView.classList.add('hidden');
            } else {
                todayTableView.classList.add('hidden');
                overallTableView.classList.remove('hidden');
            }

            renderTableData(data, filter);
        }

        function renderTableData(data, filter) {
            const tableBody = filter === 'today' ? todayTableBody : overallTableBody;

            const orderStatus = data.order_status || [];
            const recentOrders = data.recent_orders || [];

            const grouped = {};
            const today = new Date().toISOString().split('T')[0];

            recentOrders.forEach(order => {
                if (filter === 'today') {
                    const orderDate = order.order_date ? order.order_date.split(' ')[0] : '';
                    if (orderDate !== today) return;
                }

                const name = order.customer_name || order.employee_name || 'Unknown';
                if (!grouped[name]) {
                    grouped[name] = { delivered: 0, returned: 0 };
                }
                if (order.status === 'Completed' || order.status === 'Complete') {
                    grouped[name].delivered += 1;
                } else if (order.status === 'Returned') {
                    grouped[name].returned += 1;
                }
            });

            if (Object.keys(grouped).length === 0) {
                let delivered = 0, returned = 0;
                orderStatus.forEach(item => {
                    if (item.status === 'Completed' || item.status === 'Complete') {
                        delivered = parseInt(item.count) || 0;
                    }
                    if (item.status === 'Returned') {
                        returned = parseInt(item.count) || 0;
                    }
                });
                if (delivered > 0 || returned > 0) {
                    grouped['Total'] = { delivered: delivered, returned: returned };
                }
            }

            let html = '';
            if (Object.keys(grouped).length === 0) {
                html = `
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-400">
                            <i class="fa-solid fa-inbox mr-2"></i>No activity ${filter === 'today' ? 'today' : 'available'}
                        </td>
                    </tr>
                `;
            } else {
                for (const [name, stats] of Object.entries(grouped)) {
                    const balance = stats.delivered - stats.returned;
                    html += `
                        <tr class="table-row-hover slide-in dark:border-gray-700">
                            <td class="p-3 font-medium"><i class="fa-regular fa-user mr-2 text-gray-400"></i>${name}</td>
                            <td class="p-3 text-center text-green-600 dark:text-green-400 font-semibold">${formatNumber(stats.delivered)}</td>
                            <td class="p-3 text-center text-red-600 dark:text-red-400 font-semibold">${formatNumber(stats.returned)}</td>
                            <td class="p-3 text-center font-bold ${balance >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400'}">
                                ${formatNumber(balance)}
                            </td>
                        </tr>
                    `;
                }
            }

            tableBody.innerHTML = html;
        }

        function renderLastOrders(data) {
            const recentOrders = data.recent_orders || [];

            if (recentOrders.length === 0) {
                lastOrdersContainer.innerHTML = `
                    <div class="text-center py-8 text-gray-400">
                        <i class="fa-regular fa-circle-check text-3xl text-green-400"></i>
                        <p class="mt-2">No orders found</p>
                    </div>
                `;
                return;
            }

            let html = '';
            recentOrders.slice(0, 10).forEach((order, index) => {
                const statusClass = getStatusBadge(order.status);
                const statusIcon = getStatusIcon(order.status);
                const orderDate = order.order_date ? new Date(order.order_date).toLocaleString() : 'N/A';

                html += `
                    <div class="order-card bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 slide-in" style="animation-delay: ${index * 0.05}s">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                                ${order.order_no ? order.order_no.substring(0, 2) : 'OR'}
                            </div>
                            <div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">${order.order_no || 'N/A'}</span>
                                    <span class="status-badge ${statusClass}">
                                        <i class="fa-solid ${statusIcon} mr-1"></i>
                                        ${order.status || 'N/A'}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-4 flex-wrap">
                                    <span><i class="fa-regular fa-user mr-1"></i>${order.customer_name || 'N/A'}</span>
                                    <span><i class="fa-regular fa-calendar mr-1"></i>${orderDate}</span>
                                    <span><i class="fa-solid fa-user-tie mr-1"></i>${order.employee_name || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-right">
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Total</span>
                                <p class="font-bold text-blue-600 dark:text-blue-400 text-lg">${formatCurrency(order.total || 0)}</p>
                            </div>
                            <div class="text-gray-300 dark:text-gray-600">|</div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Shift</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-300">${order.shift || 'N/A'}</p>
                            </div>
                            <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors" title="View Details">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            lastOrdersContainer.innerHTML = html;
        }

        async function safeFetchJSON(url) {
            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error('Network error: ' + res.status);
                return await res.json();
            } catch (error) {
                console.error(`Fetch failed for ${url}:`, error);
                return null;
            }
        }

        async function fetchAllDashboardData() {
            const [dashboardRes, summaryRes, orderStatsRes] = await Promise.all([
                safeFetchJSON(API_URL),
                safeFetchJSON(SUMMARY_API_URL),
                safeFetchJSON(ORDERS_STATS_URL)
            ]);

            if (!dashboardRes || !dashboardRes.success) {
                showError((dashboardRes && dashboardRes.message) || 'Failed to load dashboard data.');
                return null;
            }

            dashboardData = dashboardRes.data;
            summaryData = (summaryRes && summaryRes.status) ? summaryRes : null;
            orderStatsData = (orderStatsRes && orderStatsRes.status) ? orderStatsRes.orders : null;

            return { dashboardData, summaryData, orderStatsData };
        }

        function showError(message) {
            statsCards.innerHTML = `
                <div class="col-span-full text-center py-10 text-red-500">
                    <i class="fa-solid fa-circle-exclamation text-3xl"></i>
                    <p class="mt-2">${message}</p>
                </div>
            `;
            lastOrdersContainer.innerHTML = `
                <div class="text-center py-8 text-red-400">
                    <i class="fa-solid fa-circle-exclamation text-3xl"></i>
                    <p class="mt-2">${message}</p>
                </div>
            `;
        }

        async function loadDashboard(filter = 'today') {
            statsCards.innerHTML = `
                <div class="col-span-full text-center py-10 text-gray-500">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    <p class="mt-2">Loading dashboard data...</p>
                </div>
            `;
            lastOrdersContainer.innerHTML = `
                <div class="text-center py-8 text-gray-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
                    <p class="mt-2">Loading orders...</p>
                </div>
            `;

            const result = await fetchAllDashboardData();
            if (!result || !result.dashboardData) return;

            renderCards(result.dashboardData, result.orderStatsData);
            renderOpsCards(result.summaryData);
            renderOrderStatsSummary(result.orderStatsData, filter);
            renderDeliveryTables(result.dashboardData, filter);
            renderLastOrders(result.dashboardData);

            // Also load reports with default dates
            loadAllReports();
        }

        function switchTab(filter) {
            currentFilter = filter;

            if (filter === 'today') {
                todayBtn.className = 'tab-btn active px-6 py-2 rounded font-medium transition-colors';
                overallBtn.className = 'tab-btn inactive px-6 py-2 rounded font-medium transition-colors';
            } else {
                todayBtn.className = 'tab-btn inactive px-6 py-2 rounded font-medium transition-colors';
                overallBtn.className = 'tab-btn active px-6 py-2 rounded font-medium transition-colors';
            }

            if (dashboardData) {
                renderOrderStatsSummary(orderStatsData, filter);
                renderDeliveryTables(dashboardData, filter);
            } else {
                loadDashboard(filter);
            }
        }

        function initDropdowns() {
            document.querySelectorAll('.dropdown-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const menu = btn.nextElementSibling;
                    const isOpen = menu.classList.contains('open');

                    document.querySelectorAll('.dropdown-menu').forEach(m => {
                        m.classList.remove('open');
                        m.classList.add('hidden');
                    });
                    document.querySelectorAll('.dropdown-toggle').forEach(b => b.classList.remove('open'));

                    if (!isOpen) {
                        menu.classList.remove('hidden');
                        menu.classList.add('open');
                        btn.classList.add('open');
                    }
                });
            });
        }

        function initThemeSwitch() {
            const toggle = document.getElementById('theme-toggle');
            const html = document.documentElement;
            const saved = localStorage.getItem('theme');
            if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.classList.add('dark');
                toggle.checked = true;
            }
            toggle.addEventListener('change', function() {
                if (this.checked) {
                    html.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            });
        }

        // =============================================
        // NEW: FINANCIAL REPORTS JAVASCRIPT
        // =============================================
        const reportFrom = document.getElementById('reportFromDate');
        const reportTo = document.getElementById('reportToDate');
        const applyReport = document.getElementById('applyReportFilter');

        // Set default dates to last 30 days
        function initReportDates() {
            const now = new Date();
            const thirtyDaysAgo = new Date(now);
            thirtyDaysAgo.setDate(now.getDate() - 30);
            reportFrom.value = thirtyDaysAgo.toISOString().split('T')[0];
            reportTo.value = now.toISOString().split('T')[0];
        }
        initReportDates();

        // Tab switching
        document.querySelectorAll('.report-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.report-tab-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.add('inactive');
                });
                this.classList.add('active');
                this.classList.remove('inactive');

                const tab = this.dataset.tab;
                document.querySelectorAll('.report-tab-content').forEach(el => el.classList.add('hidden'));
                document.getElementById('tab-' + tab).classList.remove('hidden');

                // Reload data for the selected tab (if needed)
                loadAllReports();
            });
        });

        // Load all reports (called on filter apply)
        async function loadAllReports() {
            const from = reportFrom.value;
            const to = reportTo.value;
            if (!from || !to) return;

            await loadBalanceSheet(from, to);
            await loadTrialBalance(from, to);
            await loadProfitLoss(from, to);
            await loadOrdersReport(from, to);
        }

        // 1. Balance Sheet
        async function loadBalanceSheet(from, to) {
            try {
                const res = await fetch(`../../action/accounting/get_balance_sheet.php?from=${from}&to=${to}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Balance sheet error');

                const assetsList = document.getElementById('bsAssetsList');
                const liabList = document.getElementById('bsLiabEquityList');
                assetsList.innerHTML = '';
                liabList.innerHTML = '';

                data.assets.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'flex justify-between py-1.5';
                    div.innerHTML = `<span>${item.account}</span><span>${item.amount.toFixed(2)}</span>`;
                    assetsList.appendChild(div);
                });

                const allRight = [...data.liabilities, ...data.equity];
                allRight.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'flex justify-between py-1.5';
                    div.innerHTML = `<span>${item.account}</span><span>${item.amount.toFixed(2)}</span>`;
                    liabList.appendChild(div);
                });

                document.getElementById('bsTotalAssets').textContent = data.total_assets.toFixed(2);
                document.getElementById('bsTotalLiabEquity').textContent = data.total_liabilities_equity.toFixed(2);

                const statusDiv = document.getElementById('bsStatus');
                if (data.balanced) {
                    statusDiv.innerHTML = '<span class="text-green-600 dark:text-green-400">✓ Balanced</span>';
                } else {
                    statusDiv.innerHTML = '<span class="text-red-600 dark:text-red-400">✗ Not Balanced</span>';
                }
            } catch (err) {
                console.error('Balance Sheet error:', err);
                document.getElementById('bsAssetsList').innerHTML = '<div class="text-red-500">Error loading</div>';
            }
        }

        // 2. Trial Balance
        async function loadTrialBalance(from, to) {
            try {
                const res = await fetch(`../../action/accounting/get_trial_balance.php?from=${from}&to=${to}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Trial balance error');

                const tbody = document.getElementById('trialBody');
                tbody.innerHTML = '';
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-gray-400">No data</td></tr>';
                    return;
                }
                data.data.forEach(row => {
                    tbody.innerHTML += `
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2">${row.account_name}</td>
                            <td class="text-right py-2">${row.debit.toFixed(2)}</td>
                            <td class="text-right py-2">${row.credit.toFixed(2)}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML += `
                    <tr class="border-t-2 dark:border-gray-600 font-bold">
                        <td class="py-2">TOTAL</td>
                        <td class="text-right py-2">${data.total_debit.toFixed(2)}</td>
                        <td class="text-right py-2">${data.total_credit.toFixed(2)}</td>
                    </tr>
                `;
            } catch (err) {
                console.error('Trial Balance error:', err);
                document.getElementById('trialBody').innerHTML = '<tr><td colspan="3" class="text-red-500">Error loading</td></tr>';
            }
        }

        // 3. Profit & Loss
        async function loadProfitLoss(from, to) {
            try {
                const res = await fetch(`../../action/accounting/get_profit_loss.php?from=${from}&to=${to}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'P&L error');

                document.getElementById('profitRevenue').textContent = data.revenue.toFixed(2);
                document.getElementById('profitCogs').textContent = data.cogs.toFixed(2);
                document.getElementById('profitGross').textContent = data.gross_profit.toFixed(2);
                document.getElementById('profitExpenses').textContent = data.operating_expenses.toFixed(2);
                document.getElementById('profitNet').textContent = data.net_profit.toFixed(2);
            } catch (err) {
                console.error('P&L error:', err);
            }
        }

        // 4. Orders Report – reuse recent_orders from dashboard data if available, or fetch from orders API
        async function loadOrdersReport(from, to) {
            try {
                // We can use the same orders data from dashboard, but filtered by date.
                // Since dashboard data is already loaded, we can filter it.
                // Alternatively, call a dedicated orders API if exists.
                // For now, we'll use the dashboard's recent_orders.
                if (dashboardData && dashboardData.recent_orders) {
                    const orders = dashboardData.recent_orders.filter(o => {
                        const d = o.order_date ? o.order_date.split(' ')[0] : '';
                        return d >= from && d <= to;
                    });
                    renderOrdersTable(orders);
                } else {
                    // Fallback: fetch from orders API if you have one
                    // For now, show message.
                    document.getElementById('ordersBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-400">Please load dashboard first</td></tr>';
                }
            } catch (err) {
                console.error('Orders report error:', err);
            }
        }

        function renderOrdersTable(orders) {
            const tbody = document.getElementById('ordersBody');
            tbody.innerHTML = '';
            if (orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-400">No orders found</td></tr>';
                return;
            }
            orders.slice(0, 20).forEach(order => {
                const statusClass = getStatusBadge(order.status);
                const statusIcon = getStatusIcon(order.status);
                tbody.innerHTML += `
                    <tr class="border-b dark:border-gray-700">
                        <td class="py-2">${order.order_no || 'N/A'}</td>
                        <td class="py-2">${order.customer_name || 'N/A'}</td>
                        <td class="py-2">${order.order_date ? new Date(order.order_date).toLocaleDateString() : 'N/A'}</td>
                        <td class="text-right py-2">${formatCurrency(order.total)}</td>
                        <td class="text-center py-2"><span class="status-badge ${statusClass}"><i class="fa-solid ${statusIcon} mr-1"></i>${order.status || 'N/A'}</span></td>
                    </tr>
                `;
            });
        }

        // Event listener for Apply button
        applyReport.addEventListener('click', () => {
            loadAllReports();
        });

        // =============================================
        // INITIALIZATION
        // =============================================
        function init() {
            setCurrentDate();
            loadDashboard('today');
            initDropdowns();
            initThemeSwitch();

            todayBtn.addEventListener('click', () => switchTab('today'));
            overallBtn.addEventListener('click', () => switchTab('overall'));

            // Auto-refresh dashboard every 5 minutes
            setInterval(() => {
                loadDashboard(currentFilter);
            }, 300000);

            // Load reports on initial load after dashboard data is ready
            // (reports will be loaded inside loadDashboard)
        }

        document.addEventListener('DOMContentLoaded', init);
