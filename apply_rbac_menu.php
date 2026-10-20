<?php
/**
 * apply_rbac_menu.php
 * ------------------------------------------------------------------
 * One-time migration script (same convention as the existing
 * refactor_sidebar.php / inject_auth.php scripts) that inserts a
 * "Roles & Permissions" sidebar link right after the "Users&Areas"
 * link in every page's Administration dropdown, if it isn't already
 * there.
 *
 * Run once from the command line:
 *   php apply_rbac_menu.php
 *
 * Safe to re-run - it skips any file that already contains the link.
 * A handful of print-only / detail pages have no sidebar at all
 * (e.g. invoice.html, print-ledger.html) and are correctly left
 * untouched; add the link to any custom page manually if needed.
 * ------------------------------------------------------------------
 */

$pagesDir = __DIR__ . '/src/pages';
$htmlFiles = glob($pagesDir . '/*.html');

$newLink = '        <a href="./roles.html" data-nav-link class="block px-4 py-3 border-l-4 border-transparent text-gray-300 hover:text-white hover:bg-slate-700 hover:border-yellow-400 transition-all duration-200">Roles &amp; Permissions</a>' . "\n";

// Matches the existing "Users&Areas" sidebar link regardless of which
// page it happens to point to (some pages link to cities.html, others
// to branch-users.html).
$pattern = '/(<a href="\.\/[^"]+"[^>]*>\s*Users&amp;Areas\s*<\/a>\s*\n)/i';

$updated = 0;
$skipped = 0;

foreach ($htmlFiles as $file) {
    if (basename($file) === 'login.html' || basename($file) === 'roles.html') {
        continue;
    }

    $content = file_get_contents($file);

    if (strpos($content, 'roles.html') !== false) {
        $skipped++;
        continue; // already has the link
    }

    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, '$1' . $newLink, $content, 1);
        file_put_contents($file, $content);
        $updated++;
        echo "Updated: " . basename($file) . "\n";
    } else {
        echo "Skipped (no sidebar / no Users&Areas link found): " . basename($file) . "\n";
        $skipped++;
    }
}

echo "\nDone. Updated {$updated} file(s), skipped {$skipped}.\n";
