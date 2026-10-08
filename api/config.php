<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

return [
    'admin_password' => 'painel-9f3c-vecintra',
    'timezone' => 'America/Sao_Paulo',
];
