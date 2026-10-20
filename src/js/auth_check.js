(function () {

    const PAGE_MODULE_MAP = {
        'dashboard.html': 'dashboard',

        'product.html': 'products',
        'category.html': 'categories',
        'add-raw-materials.html': 'raw_materials',
        'raw-materials.html': 'raw_materials',

        'parties.html': 'suppliers',
        'suppliers-report.html': 'suppliers',

        'customers.html': 'customers',
        'customers-report.html': 'customers',
        'party_payment.html': 'customers',
        'party-invoices-detail.html': 'customers',

        'purchase.html': 'purchase',

        'sale2.html': 'sales',
        'counter-sale.html': 'sales',
        'orders.html': 'sales',
        'sale-invoices.html': 'sales',
        'delete-invoice.html': 'sales',
        'invoice.html': 'sales',

        'stock-report.html': 'stock',
        'stock-audit-report.html': 'stock',

        'balance-sheet.html': 'reports',
        'monthly-sale-repot.html': 'reports',

        'accounting-system.html': 'ledger',
        'employee-ledger.html': 'ledger',
        'print-ledger.html': 'ledger',

        'expenses.html': 'expenses',

        'attendance.html': 'attendance',
        'attendance-report.html': 'attendance',

        'employees.html': 'employees',
        'print-salary-slip.html': 'employees',
        'generate-salary.html': 'employees',
        'salary-sheet.html': 'employees',

        'cities.html': 'users',
        'area-zones.html': 'users',
        'branch-users.html': 'users',

        'roles.html': 'settings',

    };

    // Same "send them to a page they actually have access to" logic as
    // login.html, reused here for the case where an already-logged-in
    // user opens login.html directly (so they don't get bounced to a
    // dashboard.html 403 if their role has no Dashboard permission).
    const LANDING_PAGES = [
        ['dashboard', 'dashboard.html'],
        ['sales', 'counter-sale.html'],
        ['customers', 'customers.html'],
        ['products', 'product.html'],
        ['stock', 'stock-report.html'],
        ['purchase', 'purchase.html'],
        ['employees', 'employees.html'],
        ['ledger', 'accounting-system.html'],
        ['expenses', 'expenses.html'],
        ['attendance', 'attendance.html'],
        ['reports', 'balance-sheet.html'],
        ['suppliers', 'parties.html'],
        ['users', 'branch-users.html'],
        ['settings', 'roles.html'],
    ];

    function resolveLandingPage(user) {
        if (user && user.is_super) return 'dashboard.html';
        const permissions = (user && user.permissions) || {};
        for (const [moduleKey, page] of LANDING_PAGES) {
            if (permissions[moduleKey] && permissions[moduleKey].view) {
                return page;
            }
        }
        return 'dashboard.html';
    }

    const userStr = localStorage.getItem('user');
    const currentFile = window.location.pathname.split('/').pop();
    const isLoginPage = window.location.pathname.includes('login.html');

    if (!userStr && !isLoginPage) {
        window.location.href = 'login.html';
        return;
    }

    if (userStr && isLoginPage) {
        let landing = 'dashboard.html';
        try {
            landing = resolveLandingPage(JSON.parse(userStr));
        } catch (e) { /* fall back to dashboard.html */ }
        window.location.href = landing;
        return;
    }

    window.RBAC = {
        user: null,
        permissions: {},
        csrfToken: null,
        can(moduleKey, action) {
            if (this.user && this.user.is_super) return true;
            return !!(this.permissions[moduleKey] && this.permissions[moduleKey][action]);
        },
    };

    // ---- Auto-attach CSRF token to every mutating same-origin fetch ----
    const originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        init = init || {};
        const method = (init.method || 'GET').toUpperCase();
        const url = typeof input === 'string' ? input : (input && input.url) || '';
        const isSameOriginAction = url.indexOf('action/') !== -1 || url.indexOf('/action/') !== -1;

        if (window.RBAC.csrfToken && isSameOriginAction && method !== 'GET') {
            init.headers = new Headers(init.headers || {});
            if (!init.headers.has('X-CSRF-Token')) {
                init.headers.set('X-CSRF-Token', window.RBAC.csrfToken);
            }
        }
        return originalFetch(input, init);
    };

    function showAccessDenied() {
        document.documentElement.innerHTML =
            '<head><meta charset="UTF-8"><title>Access Denied</title>' +
            '<script src="https://cdn.tailwindcss.com"><\/script></head>' +
            '<body class="min-h-screen flex items-center justify-center bg-[#051E3D]">' +
            '<div class="text-center px-6">' +
            '<div class="text-6xl font-black text-amber-500 mb-2">403</div>' +
            '<h1 class="text-2xl font-bold text-white mb-2">Access Denied</h1>' +
            '<p class="text-gray-300 mb-6">You do not have permission to view this page.</p>' +
            '<a href="./dashboard.html" class="inline-block bg-amber-500 text-slate-900 font-semibold px-5 py-2 rounded-md hover:bg-amber-400 transition-colors">Back to Dashboard</a>' +
            '</div></body>';
    }

    // Hides sidebar links (any page's #sidebarNav) whose target page the
    // current user lacks View permission for. Runs after DOMContentLoaded
    // since the sidebar markup is present in each page's own HTML.
    function applyMenuPermissions() {
        const sidebar = document.getElementById('sidebarNav');
        if (!sidebar) return;

        sidebar.querySelectorAll('a[href$=".html"]').forEach((link) => {
            const file = link.getAttribute('href').split('/').pop();
            const moduleKey = PAGE_MODULE_MAP[file];
            if (!moduleKey) return; // unmapped links (e.g. anchors) stay visible
            if (!window.RBAC.can(moduleKey, 'view')) {
                link.style.display = 'none';
            }
        });

        // Collapse now-empty dropdown groups (Administration, Inventory, etc.)
        // Matches both the older ".dropdown-menu" markup and the current
        // "[data-dropdown-menu]" markup used across pages.
        sidebar.querySelectorAll('.dropdown-menu, [data-dropdown-menu]').forEach((menu) => {
            const hasVisibleLink = Array.from(menu.querySelectorAll('a')).some(
                (a) => a.style.display !== 'none'
            );
            if (!hasVisibleLink && menu.querySelectorAll('a').length > 0) {
                const group = menu.closest('.relative') || menu.parentElement;
                if (group) group.style.display = 'none';
            }
        });
    }

    // ---- Server-side session + permission check ----
    if (!isLoginPage) {
        fetch('../../action/auth/check.php')
            .then((res) => {
                if (!res.ok) {
                    localStorage.removeItem('user');
                    window.location.href = 'login.html';
                    return Promise.reject(new Error('not authenticated'));
                }
                return res.json();
            })
            .then((data) => {
                if (!data || !data.authenticated) {
                    localStorage.removeItem('user');
                    window.location.href = 'login.html';
                    return;
                }

                window.RBAC.user = data.user;
                window.RBAC.permissions = data.permissions || {};
                window.RBAC.csrfToken = data.csrf_token || null;

                const moduleKey = PAGE_MODULE_MAP[currentFile];
                if (moduleKey && !window.RBAC.can(moduleKey, 'view')) {
                    showAccessDenied();
                    return;
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', applyMenuPermissions);
                } else {
                    applyMenuPermissions();
                }
            })
            .catch((err) => {
                if (err && err.message !== 'not authenticated') {
                    console.error('Auth check failed', err);
                }
            });
    }

    // Setup Logout functionality
    document.addEventListener('DOMContentLoaded', () => {
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                fetch('../../action/auth/logout.php')
                    .then(() => {
                        localStorage.removeItem('user');
                        window.location.href = 'login.html';
                    });
            });
        }
    });
})();