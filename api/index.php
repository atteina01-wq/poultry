<?php
// Vercel Serverless Entry Router for Poultry Management System

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = urldecode($uri);

$baseDir = realpath(__DIR__ . '/..');

// Route root / or /index.php
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit();
}

$targetFile = realpath($baseDir . $uri);

// Prevent directory traversal and check existence
if ($targetFile && str_starts_with($targetFile, $baseDir) && file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    if ($ext === 'php') {
        chdir(dirname($targetFile));
        require $targetFile;
        exit();
    }

    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    $contentType = $mimes[$ext] ?? 'application/octet-stream';
    header("Content-Type: $contentType");
    readfile($targetFile);
    exit();
}

// If user requested a path without .php
$phpFile = realpath($baseDir . $uri . '.php');
if ($phpFile && str_starts_with($phpFile, $baseDir) && file_exists($phpFile)) {
    chdir(dirname($phpFile));
    require $phpFile;
    exit();
}

http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);
