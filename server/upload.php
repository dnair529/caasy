<?php
/**
 * caasy — HTTPS upload endpoint
 *
 * POST https://dnair.us/caasy/upload.php
 *   Authorization: Bearer <CAASY_TOKEN>
 *   multipart fields: file=@..., subpath=<relative path under this dir, e.g. "docs/readme.md">
 *
 * Token comes from the environment: put CAASY_UPLOAD_TOKEN in the php-fpm /
 * fastcgi env (or a dotenv the server loads). Never hardcode it here.
 *
 * Security: path traversal is blocked (.. and absolute paths rejected);
 * only the token holder can write; files land strictly under this directory.
 */

declare(strict_types=1);

header('Content-Type: application/json');

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST only');
}

// --- Auth ----------------------------------------------------------------
$expected = getenv('CAASY_UPLOAD_TOKEN') ?: '';
if ($expected === '') {
    fail(500, 'Server not configured: CAASY_UPLOAD_TOKEN missing');
}
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!hash_equals('Bearer ' . $expected, $header)) {
    fail(401, 'Unauthorized');
}

// --- Input ---------------------------------------------------------------
$subpath = trim((string)($_POST['subpath'] ?? ''), '/');
if (isset($_FILES['file']) === false || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    fail(400, 'Missing or failed file upload');
}

// --- Path safety ---------------------------------------------------------
if ($subpath === '' || str_contains($subpath, '..') || str_starts_with($subpath, '/')) {
    fail(400, 'Invalid subpath');
}
$base = __DIR__;

// Re-verify token-protected dir is not the upload script itself
$target = $base . '/' . $subpath;
$realBase = realpath($base);

// Create missing parent dirs FIRST (realpath fails on non-existent paths)
$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    fail(500, 'Could not create directory');
}

// Now that dirs exist, re-verify the resolved path stays inside the base
$realParent = realpath(dirname($target));
if ($realParent === false || !str_starts_with($realParent, $realBase)) {
    fail(400, 'Invalid subpath');
}
if (basename($target) === 'upload.php' || basename($target) === '.htaccess') {
    fail(403, 'Refusing to overwrite server files');
}

// --- Write ---------------------------------------------------------------
if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    fail(500, 'Write failed');
}
@chmod($target, 0644);

echo json_encode([
    'ok' => true,
    'path' => $subpath,
    'bytes' => filesize($target),
    'url' => 'https://dnair.us/caasy/' . $subpath,
]);
