<?php

declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../config/app.php";

if ($argc !== 2) {
    fwrite(STDERR, "Uso: php convidar-professor.php email\n");
    exit(2);
}

$email = strtolower(trim($argv[1]));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    fwrite(STDERR, "Informe um endereço de e-mail válido, com até 150 caracteres.\n");
    exit(2);
}

try {
    $pdo = plataforma_conexao();
    if (!plataforma_migracao_aplicada($pdo)) {
        fwrite(STDERR, "A migração da plataforma ainda não foi aplicada.\n");
        exit(3);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id FROM usuarios WHERE email = :email LIMIT 1 FOR UPDATE"
    );
    $stmt->execute(["email" => $email]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        fwrite(STDERR, "Esse e-mail já possui uma conta; nenhum convite foi criado.\n");
        exit(4);
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM convites_professores
         WHERE email = :email
           AND resgatado_em IS NULL
           AND expira_em > NOW()
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(["email" => $email]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        fwrite(STDERR, "Já existe um convite válido para esse e-mail.\n");
        exit(5);
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        "INSERT INTO convites_professores (email, token_hash, expira_em)
         VALUES (:email, :token_hash, DATE_ADD(NOW(), INTERVAL 7 DAY))"
    );
    $stmt->execute([
        "email" => $email,
        "token_hash" => hash("sha256", $token)
    ]);
    $pdo->commit();

    $baseUrl = rtrim(
        getenv("PLATAFORMA_BASE_URL") ?: "http://localhost/TCC/plataforma",
        "/"
    );
    $url = $baseUrl
        . "/cadastro.php?perfil=professor&convite="
        . rawurlencode($token);

    fwrite(STDOUT, "Convite válido por 7 dias, vinculado a {$email} e de uso único:\n");
    fwrite(STDOUT, $url . "\n");
    fwrite(STDOUT, "Envie este link ao professor por um canal institucional seguro.\n");
} catch (Throwable $erro) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("[Plataforma] Falha ao criar convite de professor: " . $erro->getMessage());
    fwrite(STDERR, "Não foi possível criar o convite. Confira a conexão e tente novamente.\n");
    exit(1);
}
