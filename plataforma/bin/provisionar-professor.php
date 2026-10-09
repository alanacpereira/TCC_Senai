<?php

declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../config/app.php";

if ($argc !== 3) {
    fwrite(STDERR, "Uso: php provisionar-professor.php \"Nome\" email\n");
    exit(2);
}

$nome = trim($argv[1]);
$email = strtolower(trim($argv[2]));
$senha = getenv("PLATAFORMA_PROFESSOR_PASSWORD");

if (
    $nome === "" ||
    strlen($nome) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 150 ||
    $senha === false ||
    strlen($senha) < 12 ||
    !preg_match('/[A-Za-z]/', $senha) ||
    !preg_match('/[0-9]/', $senha)
) {
    fwrite(
        STDERR,
        "Dados inválidos. Defina PLATAFORMA_PROFESSOR_PASSWORD no ambiente com uma senha de 12+ caracteres, letras e números.\n"
    );
    exit(2);
}

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    fwrite(STDERR, "A migração da plataforma ainda não foi aplicada.\n");
    exit(3);
}

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
$stmt->execute(["email" => $email]);
if ($stmt->fetchColumn()) {
    fwrite(STDERR, "Esse e-mail já possui uma conta. O provisionamento não altera contas existentes.\n");
    exit(4);
}

$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nome, email, senha, perfil)
     VALUES (:nome, :email, :senha, 'professor')"
);
$stmt->execute([
    "nome" => $nome,
    "email" => $email,
    "senha" => password_hash($senha, PASSWORD_DEFAULT)
]);
putenv("PLATAFORMA_PROFESSOR_PASSWORD");
fwrite(STDOUT, "Conta de professor criada. Remova a variável de senha do ambiente após o uso.\n");
