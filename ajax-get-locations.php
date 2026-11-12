<?php
require_once 'inc/auth.php';
require_once 'inc/locations.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$state = trim($_GET['state'] ?? '');
$district = trim($_GET['district'] ?? '');

if ($action === 'get_districts' && $state !== '') {
    echo json_encode(get_districts($state));
    exit;
}

if ($action === 'get_cities' && $state !== '' && $district !== '') {
    echo json_encode(get_cities($state, $district));
    exit;
}

echo json_encode([]);
?>