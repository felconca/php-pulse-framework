<?php
// server.php - router for PHP built-in server

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// --- 1. Root layer rewrite ---
// Block sensitive folders
if (preg_match('#^/(app|includes)#', $uri)) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied";
    exit;
}

// Redirect everything to public/ internally
$publicPath = __DIR__ . '/public' . $uri;

// Serve static files in public/
if ($uri !== '/' && file_exists($publicPath)) {
    return false; // let PHP serve the static file
}

// --- 2. Public folder rewrite ---
// Preserve Authorization header for API
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
}

// Include Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Mimic public/.htaccess: route everything to index.php
$_GET['path'] = ltrim($uri, '/');
require_once __DIR__ . '/public/index.php';
