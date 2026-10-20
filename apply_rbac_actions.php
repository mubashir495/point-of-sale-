<?php
/**
 * apply_rbac_actions.php
 * ------------------------------------------------------------------
 * One-time migration script that inserts:
 *
 *   require_once __DIR__ . '/../../config/rbac.php';
 *   rbac_require_permission('<module>', '<action>');
 *
 * right after the opening <?php tag of every action/*.php endpoint,
 * based on a folder -> module map and a filename -> action heuristic
 * (add/create = create, update/edit/toggle/status = edit,
 *  delete/remove = delete, export = export, print = print,
 *  everything else = view).
 *
 * Run once from the command line:
 *   php apply_rbac_actions.php
 *
 * Safe to re-run - any file that already contains "config/rbac.php"
 * is left untouched. Review the diff before committing; a couple of
 * multi-method endpoints (e.g. invoice_api.php, which handles both
 * GET and DELETE in one file) were hand-adjusted afterwards to use
 * rbac_action_for_method() instead of a fixed action - grep for
 * REQUEST_METHOD in any file this script touches and double check.
 * ------------------------------------------------------------------
 */

$folderModuleMap = [
    'category'              => 'categories',
    'products'               => 'products',
    'raw_materials'          => 'raw_materials',
    'raw_material_purchase'  => 'purchase',
    'parties'                => 'suppliers',
    'parties_2'              => 'suppliers',
    'customers'              => 'customers',
    'orders'                 => 'sales',
    'invoices'                => 'sales',
    'quotations'              => 'sales',
    'payments'                => 'ledger',
    'reports'                 => 'reports',
    'ledger'                  => 'ledger',
    'accounting'              => 'ledger',
    'expenses'                => 'expenses',
    'attendance'              => 'attendance',
    'employees'               => 'employees',
    'salary'                  => 'employees',
    'dashboard'               => 'dashboard',
    'area-zone'               => 'users',
    'cities'                  => 'users',
];

// Filenames that are helper/class includes, not real HTTP endpoints -
// never protect these directly (they get pulled in by real endpoints).
$excludeFiles = ['Employee.php'];

// Filenames whose action was hand-corrected after the automatic pass
// because they don't follow the simple verb-in-filename convention.
$manualOverrides = [
    'attendance/save_attendance.php'    => 'create',
    'salary/generate_salary.php'        => 'create',
    'salary/pay_salary.php'             => 'edit',
    'quotations/convert_to_invoice.php' => 'edit',
];

// Files that internally branch on REQUEST_METHOD for more than one
// verb - use the dynamic rbac_action_for_method() helper instead of a
// single fixed action.
$dynamicMethodFiles = [
    'invoices/invoice_api.php',
];

function inferAction(string $filename): string
{
    $f = strtolower($filename);
    if (str_contains($f, 'export')) return 'export';
    if (str_contains($f, 'print')) return 'print';
    if (preg_match('/add|create|insert/', $f)) return 'create';
    if (preg_match('/update|edit|toggle|status/', $f)) return 'edit';
    if (preg_match('/delete|remove/', $f)) return 'delete';
    return 'view';
}

$updated = 0;
$skipped = 0;

foreach ($folderModuleMap as $folder => $module) {
    $dir = __DIR__ . "/action/{$folder}";
    if (!is_dir($dir)) {
        continue;
    }

    foreach (glob("{$dir}/*.php") as $filepath) {
        $filename = basename($filepath);
        $relative = "{$folder}/{$filename}";

        if (in_array($filename, $excludeFiles, true)) {
            $skipped++;
            continue;
        }

        $content = file_get_contents($filepath);

        if (str_contains($content, 'config/rbac.php')) {
            $skipped++;
            continue; // already protected
        }

        if (in_array($relative, $dynamicMethodFiles, true)) {
            $guard = "require_once __DIR__ . '/../../config/rbac.php';\n"
                   . "rbac_require_permission('{$module}', rbac_action_for_method());\n";
        } else {
            $action = $manualOverrides[$relative] ?? inferAction($filename);
            $guard = "require_once __DIR__ . '/../../config/rbac.php';\n"
                   . "rbac_require_permission('{$module}', '{$action}');\n";
        }

        $tagPos = strpos($content, '<?php');
        if ($tagPos === false) {
            echo "SKIP (no <?php tag): {$relative}\n";
            $skipped++;
            continue;
        }

        $insertAt = $tagPos + strlen('<?php');
        $newContent = substr($content, 0, $insertAt) . "\n" . $guard . substr($content, $insertAt);

        file_put_contents($filepath, $newContent);
        echo "Protected: {$relative}\n";
        $updated++;
    }
}

echo "\nDone. Updated {$updated} file(s), skipped {$skipped}.\n";
