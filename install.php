<?php

/**
 * Convenience entry point for first-run installs. Some shared hosts route
 * directly to .php files; this lets users open /install.php in a browser
 * after upload without depending on .htaccess rewrites being active yet.
 */

$_SERVER['REQUEST_URI'] = '/install';
require __DIR__ . '/index.php';
