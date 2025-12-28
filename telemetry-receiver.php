<?php
/**
 * Telemetry Receiver Script
 * 
 * HOSTING INSTRUCTIONS:
 * 1. Upload this file to any PHP server (e.g., your portfolio site, a subdomain).
 *    Example URL: https://your-site.com/nueva-telemetry.php
 * 2. Create a folder named `data` in the same directory.
 * 3. Make sure the `data` folder is writable (chmod 777 or 755).
 * 4. Update your plugin's `class-nueva-telemetry.php` to point to this URL.
 */

// 1. Receive JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// 2. Validate (Simple check)
if (!isset($data['plugin_version'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing Data']);
    exit;
}

// 3. Prepare Log Entry
$entry = [
    'received_at' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'], // Anonymize if needed
    'data' => $data
];

// 4. Save to File (Appends to data/stats.log)
// In a real app, you would INSERT into a MySQL Database here.
if (!is_dir('data')) {
    mkdir('data', 0755, true);
}

// Option A: Single Log File
$log_file = 'data/stats.log';
file_put_contents($log_file, json_encode($entry) . ",\n", FILE_APPEND);

// Option B: One file per site (better for debugging)
// $filename = 'data/site_' . md5($data['url']) . '.json';
// file_put_contents($filename, json_encode($entry));

// 5. Respond
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
