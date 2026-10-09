<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$erro = "";
$pdo = null;
$configurada = false;

try {
    $pdo = plataforma_conexao();
    $configurada = plataforma_migracao_aplicada($pdo);
    if (!$configurada) {
        http_response_code(503);
    }
} catch (Throwable $falha) {
    error_log("[Plataforma] Falha ao preparar login: " . $falha->getMessage());
    http_response_code(503);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && $configurada) {
    try {
        plataforma_validar_csrf();
        $email = strtolower(trim(plataforma_campo($_POST, "email")));
        $senha = plataforma_campo($_POST, "senha");
        $origem = (string) ($_SERVER["REMOTE_ADDR"] ?? "desconhecida");

        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("E-mail ou senha inválidos.");
        }
        if (plataforma_login_bloqueado($pdo, $email, $origem)) {
            throw new RuntimeException("Não foi possível entrar. Aguarde alguns minutos e tente novamente.");
        }

        $stmt = $pdo->prepare(
            "SELECT id, nome, email, senha, perfil
             FROM usuarios
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->execute(["email" => $email]);
        $conta = $stmt->fetch();

        if (
            !$conta ||
            $conta["perfil"] === "convidado" ||
            !password_verify($senha, $conta["senha"])
        ) {
            plataforma_contabilizar_falha_login($pdo, $email, $origem);
            throw new RuntimeException("E-mail ou senha inválidos.");
        }

        plataforma_limpar_falhas_login($pdo, $email, $origem);
        session_regenerate_id(true);
        Auth::login($conta);
        $_SESSION["plataforma_csrf"] = bin2hex(random_bytes(32));
        plataforma_redirecionar(
            $conta["perfil"] === "professor"
                ? "professor/index.php"
                : "inicio.php"
        );
    } catch (Throwable $falha) {
        if ($falha instanceof PDOException) {
            error_log("[Plataforma] Erro ao autenticar: " . $falha->getMessage());
            $erro = "Não foi possível validar o acesso agora. Tente novamente.";
        } else {
            $erro = $falha->getMessage();
        }
    }
}

plataforma_cabecalho("Entrar");
?>
<h1>Entrar na Palavra a Bordo</h1>
<?php if (!$configurada): ?>
    <p role="alert">O acesso depende da migração segura da plataforma. Revise as instruções na <a href="index.php">página inicial</a>; nenhum dado foi alterado automaticamente.</p>
<?php else: ?>
    <?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="username" required>
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        <button type="submit">Entrar</button>
    </form>
    <p><a href="cadastro.php">Criar conta de estudante</a></p>
    <p><a href="recuperar-senha.php">Esqueci minha senha</a></p>
<?php endif; ?>
<?php plataforma_rodape(); ?>
