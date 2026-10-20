<?php
$pagesDir = __DIR__ . '/src/pages';
$htmlFiles = glob($pagesDir . '/*.html');

$headerTemplate = <<<'EOD'
    <!-- Top Navbar -->
    <header class="bg-[#001F4D] h-16 flex items-center justify-between px-6 text-white shadow sticky top-0 z-50">
      <div class="flex items-center gap-4">
        <button id="menuToggle" type="button" class="text-white text-xl hover:text-amber-400 transition-colors">
          <i class="fa-solid fa-bars"></i>
        </button>
        <img src="../../assets/images/logo.png" class="w-10 h-10 rounded-full" alt="Logo" />
        <h1 class="text-xl font-bold">MARKING BOOSTER</h1>
      </div>
      <div class="bg-white text-gray-700 px-4 py-2 rounded-full text-sm">
        <i class="fa-regular fa-clock mr-1"></i>
        <span id="currentDate"></span>
      </div>
    </header>
EOD;

$sidebarTemplate = <<<'EOD'
      <!-- Sidebar -->
      <aside id="main-sidebar" class="w-[320px] shrink-0 bg-[#051E3D] fixed lg:sticky top-[64px] left-0 dark:text-gray-300 flex flex-col justify-between h-[calc(100vh-64px)] overflow-y-auto shadow-xl border-r border-slate-800 transition-all duration-300 z-40 transform -translate-x-full lg:translate-x-0">
        <div class="flex flex-col items-center pt-8 pb-6 border-b border-slate-700/40 relative">
          <div class="relative w-24 h-24 rounded-full border-2 border-white/20 p-1 bg-white flex items-center justify-center shadow-sm">
            <span class="text-[#051E3D] font-black text-xl tracking-wider"><img src="../../assets/images/logo.png" class="rounded-full" alt="Logo" /></span>
            <button class="absolute bottom-0 right-1 bg-amber-500 text-slate-900 rounded-full w-6 h-6 flex items-center justify-center text-xs border border-[#051E3D]">
              <i class="fa-solid fa-camera"></i>
            </button>
          </div>
          <h2 class="mt-3 text-lg font-bold text-white tracking-wide">MARKING BOOSTER</h2>
          <span class="mt-1 px-3 py-0.5 bg-amber-500 text-slate-950 font-bold text-[11px] uppercase tracking-wider rounded-md">Branch</span>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar px-3 py-4 space-y-1" id="sidebarNav">
          <a href="./dashboard.html" class="flex items-center space-x-3 px-3 py-3 rounded-md hover:bg-slate-800/50 text-gray-300 hover:text-amber-400 transition-colors">
            <i class="fa-solid fa-gauge-high text-lg"></i>
            <span class="font-medium text-sm">Dashboard</span>
          </a>

          <div class="relative">
            <button type="button" class="dropdown-toggle w-full flex items-center justify-between px-3 py-3 rounded-md hover:bg-slate-800/50 transition-colors group">
              <div class="flex items-center space-x-3 pointer-events-none select-none text-gray-300 group-hover:text-amber-400">
                <i class="fa-solid fa-user-gear text-lg"></i>
                <span class="font-medium text-sm">Administration</span>
              </div>
              <i class="fa-solid fa-chevron-right text-xs text-gray-400 pointer-events-none transition-transform duration-200"></i>
            </button>
            <div class="dropdown-menu hidden mt-1 space-y-1 pl-6 transition-all">
              <a href="./employees.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Employees</a>
              <a href="./cities.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Users&amp;Areas</a>
              <a href="./area-zones.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Area/Zones</a>
              <a href="./branch-users.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Branch Users</a>
            </div>
          </div>

          <div class="relative">
            <button type="button" class="dropdown-toggle w-full flex items-center justify-between px-3 py-3 rounded-md hover:bg-slate-800/50 transition-colors group">
              <div class="flex items-center space-x-3 pointer-events-none select-none text-gray-300 group-hover:text-amber-400">
                <i class="fa-solid fa-boxes-stacked text-lg"></i>
                <span class="font-medium text-sm">Inventory</span>
              </div>
              <i class="fa-solid fa-chevron-right text-xs text-gray-400 pointer-events-none transition-transform duration-200"></i>
            </button>
            <div class="dropdown-menu hidden mt-1 space-y-1 pl-6 transition-all">
              <a href="./category.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Category</a>
              <a href="./product.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Products</a>
              <a href="./stock-audit-report.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Stock Audit &amp; Report</a>
            </div>
          </div>

          <div class="relative">
            <button type="button" class="dropdown-toggle w-full flex items-center justify-between px-3 py-3 rounded-md hover:bg-slate-800/50 transition-colors group">
              <div class="flex items-center space-x-3 pointer-events-none select-none text-gray-300 group-hover:text-amber-400">
                <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                <span class="font-medium text-sm">Billing &amp; Payments</span>
              </div>
              <i class="fa-solid fa-chevron-right text-xs text-gray-400 pointer-events-none transition-transform duration-200"></i>
            </button>
            <div class="dropdown-menu hidden mt-1 space-y-1 pl-6 transition-all">

              <a href="./counter-sale.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Sale</a>
              <a href="./orders.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Orders</a>
              <a href="./sale-invoices.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Invoice History</a>
            </div>
          </div>

          <div class="relative">
            <button type="button" class="dropdown-toggle w-full flex items-center justify-between px-3 py-3 rounded-md hover:bg-slate-800/50 transition-colors group">
              <div class="flex items-center space-x-3 pointer-events-none select-none text-gray-300 group-hover:text-amber-400">
                <i class="fa-solid fa-users-viewfinder text-lg"></i>
                <span class="font-medium text-sm">HR &amp; Payroll</span>
              </div>
              <i class="fa-solid fa-chevron-right text-xs text-gray-400 pointer-events-none transition-transform duration-200"></i>
            </button>
            <div class="dropdown-menu hidden mt-1 space-y-1 pl-6 transition-all">
              <a href="./attendance.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Attendance</a>
              <a href="./generate-salary.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Generate Salary</a>
              <a href="./salary-sheet.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Salary Sheet</a>
            </div>
          </div>

          <div class="relative">
            <button type="button" class="dropdown-toggle w-full flex items-center justify-between px-3 py-3 rounded-md hover:bg-slate-800/50 transition-colors group">
              <div class="flex items-center space-x-3 pointer-events-none select-none text-gray-300 group-hover:text-amber-400">
                <i class="fa-solid fa-chart-line text-lg"></i>
                <span class="font-medium text-sm">Reports</span>
              </div>
              <i class="fa-solid fa-chevron-right text-xs text-gray-400 pointer-events-none transition-transform duration-200"></i>
            </button>
            <div class="dropdown-menu hidden mt-1 space-y-1 pl-6 transition-all">
              <a href="./monthly-sale-repot.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Sales Report</a>
              <a href="./invoice-report.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Invoice Report</a>
              <a href="./stock-audit-report.html" class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Stock Report</a>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-slate-700/40 space-y-4">
          <div class="flex items-center justify-between px-2">
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
                <i class="fa-solid fa-palette text-sm"></i>
              </div>
              <span class="text-sm font-medium text-white">Theme</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="theme-toggle" class="sr-only peer" checked />
              <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
          </div>
          <button id="logoutBtn" class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 px-4 rounded-md flex items-center justify-center space-x-2 transition-colors">
            <i class="fa-solid fa-power-off text-sm"></i>
            <span>Logout</span>
          </button>
        </div>
      </aside>
EOD;

foreach ($htmlFiles as $file) {
    $content = file_get_contents($file);
    
    // Branding replacements
    $replacements = [
        "MARKETINH BOOSTER" => "MARKING BOOSTER",
        "Marketinh Booster" => "Marking Booster",
        "NOWRUZ DAIRY" => "MARKING BOOSTER",
        "Nowruz Dairy" => "Marking Booster",
        "nowruz dairy" => "Marking Booster",
    ];
    $content = str_ireplace(array_keys($replacements), array_values($replacements), $content);
    
    // Replace <header> ... </header>
    $headerPattern = '/<header\b[^>]*>.*?<\/header>/is';
    $content = preg_replace($headerPattern, $headerTemplate, $content);
    
    // Replace <aside id="main-sidebar"> ... </aside>
    $sidebarPattern = '/<aside[^>]*id=["\']?main-sidebar["\']?[^>]*>.*?<\/aside>/is';
    $content = preg_replace($sidebarPattern, $sidebarTemplate, $content);
    
    file_put_contents($file, $content);
    echo "Processed: " . basename($file) . "\n";
}

echo "Complete!\n";
