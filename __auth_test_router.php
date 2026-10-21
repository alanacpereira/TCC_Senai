<?php

declare(strict_types=1);

if (
    parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) !== "/__auth-test-reset-token"
) {
    return false;
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
    http_response_code(405);
    exit;
}

$arquivo = getenv("TCC_AUTH_TEST_SMTP_CAPTURE");
$mensagem = $arquivo === false ? false : file_get_contents($arquivo);
if ($mensagem === false || !preg_match(
    '~redefinir-senha\.php\?token=([a-f0-9]{64})~',
    $mensagem,
    $correspondencia
)) {
    http_response_code(404);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");
echo json_encode(["token" => $correspondencia[1]], JSON_THROW_ON_ERROR);
