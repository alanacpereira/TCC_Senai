<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Método não permitido.");
}

plataforma_validar_csrf();
Auth::logout();
plataforma_redirecionar("index.php");
