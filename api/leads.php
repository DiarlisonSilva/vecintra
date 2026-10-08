<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

$key = (string) ($_SERVER['HTTP_X_ADMIN_KEY'] ?? '');
if ($key === '' || !hash_equals($config['admin_password'], $key)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autorizado.']);
    exit;
}

$file = dirname(__DIR__) . '/storage/leads.json';
if (!is_file($file)) {
    echo json_encode(['ok' => true, 'leads' => []]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $leads = json_decode((string) file_get_contents($file), true);
    echo json_encode(['ok' => true, 'leads' => is_array($leads) ? array_reverse($leads) : []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$id = (string) ($payload['id'] ?? '');
$status = (string) ($payload['status'] ?? '');
$allowed = ['novo', 'em_contato', 'concluido'];
if ($id === '' || !in_array($status, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Status inválido.']);
    exit;
}

$handle = fopen($file, 'c+');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível atualizar.']);
    exit;
}
flock($handle, LOCK_EX);
$leads = json_decode(stream_get_contents($handle) ?: '[]', true);
if (!is_array($leads)) {
    $leads = [];
}
$found = false;
foreach ($leads as &$lead) {
    if (($lead['id'] ?? '') === $id) {
        $lead['status'] = $status;
        $found = true;
        break;
    }
}
unset($lead);
if (!$found) {
    flock($handle, LOCK_UN);
    fclose($handle);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Pedido não encontrado.']);
    exit;
}
ftruncate($handle, 0);
rewind($handle);
fwrite($handle, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);
echo json_encode(['ok' => true]);
