<?php
$pagesDir = __DIR__ . '/src/pages';
$htmlFiles = glob($pagesDir . '/*.html');

foreach ($htmlFiles as $file) {
    if (basename($file) === 'login.html') continue; // Login page has its own logic or we can add it there too

    $content = file_get_contents($file);
    
    // Check if auth_check.js is already included
    if (strpos($content, 'auth_check.js') === false) {
        // Insert right before </head>
        $scriptTag = "    <script src=\"../js/auth_check.js\"></script>\n";
        $content = preg_replace('/<\/head>/i', $scriptTag . '</head>', $content);
        file_put_contents($file, $content);
        echo "Injected auth_check.js into: " . basename($file) . "\n";
    }
}
echo "Complete!\n";
