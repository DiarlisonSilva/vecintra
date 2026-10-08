<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($string): int
    {
        return strlen((string) $string);
    }
}

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

if (!empty($payload['empresa_site'])) {
    echo json_encode(['ok' => true, 'id' => 'ignored']);
    exit;
}

$nome = trim((string) ($payload['nome'] ?? ''));
$telefone = trim((string) ($payload['telefone'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$imovel = trim((string) ($payload['imovel'] ?? ''));
$servico = trim((string) ($payload['servico'] ?? ''));
$mensagem = trim((string) ($payload['mensagem'] ?? ''));

if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Informe seu nome.']);
    exit;
}
$digits = preg_replace('/\D+/', '', $telefone) ?? '';
if (strlen($digits) < 10 || strlen($digits) > 13) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Informe um telefone com DDD.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Informe um e-mail válido.']);
    exit;
}
if ($servico === '' || mb_strlen($servico) > 80 || mb_strlen($imovel) > 80) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Escolha o serviço de interesse.']);
    exit;
}
if (mb_strlen($mensagem) > 2000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'A mensagem passou do limite.']);
    exit;
}

$storage = dirname(__DIR__) . '/storage';
if (!is_dir($storage) && !mkdir($storage, 0755, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível guardar a solicitação.']);
    exit;
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rateFile = $storage . '/ratelimit.json';
$rateHandle = fopen($rateFile, 'c+');
if ($rateHandle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível guardar a solicitação.']);
    exit;
}
flock($rateHandle, LOCK_EX);
$rateRaw = stream_get_contents($rateHandle);
$rates = json_decode($rateRaw ?: '{}', true);
if (!is_array($rates)) {
    $rates = [];
}
$now = time();
$window = [];
foreach (($rates[$ip] ?? []) as $stamp) {
    if ($now - (int) $stamp < 3600) {
        $window[] = (int) $stamp;
    }
}
if (count($window) >= 6) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Muitas solicitações em pouco tempo. Fale pelo WhatsApp.']);
    exit;
}
$window[] = $now;
$rates[$ip] = $window;
ftruncate($rateHandle, 0);
rewind($rateHandle);
fwrite($rateHandle, json_encode($rates));
fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);

$leadFile = $storage . '/leads.json';
$leadHandle = fopen($leadFile, 'c+');
if ($leadHandle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível guardar a solicitação.']);
    exit;
}
flock($leadHandle, LOCK_EX);
$leadRaw = stream_get_contents($leadHandle);
$leads = json_decode($leadRaw ?: '[]', true);
if (!is_array($leads)) {
    $leads = [];
}

$id = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
$leads[] = [
    'id' => $id,
    'created_at' => date('c'),
    'nome' => $nome,
    'telefone' => $telefone,
    'email' => $email,
    'imovel' => $imovel,
    'servico' => $servico,
    'mensagem' => $mensagem,
    'status' => 'novo',
    'ip' => $ip,
];

ftruncate($leadHandle, 0);
rewind($leadHandle);
fwrite($leadHandle, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fflush($leadHandle);
flock($leadHandle, LOCK_UN);
fclose($leadHandle);

echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
