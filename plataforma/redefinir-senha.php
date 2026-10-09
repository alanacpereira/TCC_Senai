<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

header("Cache-Control: no-store");
header("Referrer-Policy: no-referrer");

$token = $_SERVER["REQUEST_METHOD"] === "POST"
    ? plataforma_campo($_POST, "token")
    : ($_GET["token"] ?? "");
$tokenValido = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$usuarioId = null;

if ($tokenValido) {
    $stmt = plataforma_conexao()->prepare(
        "SELECT usuario_id
         FROM tokens_recuperacao_senha
         WHERE token_hash = :token_hash
           AND utilizado_em IS NULL AND expira_em > NOW()
         LIMIT 1"
    );
    $stmt->execute(["token_hash" => hash("sha256", $token)]);
    $idEncontrado = $stmt->fetchColumn();
    $usuarioId = $idEncontrado === false ? null : (int) $idEncontrado;
}

$erro = "";
$concluido = false;

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    try {
        plataforma_validar_csrf();
        $senha = plataforma_campo($_POST, "senha");
        $confirmacao = plataforma_campo($_POST, "confirmacao");

        if ($usuarioId === null) {
            throw new RuntimeException("Este link é inválido ou expirou. Solicite uma nova redefinição.");
        }
        if (
            strlen($senha) < 12 ||
            !preg_match('/[A-Za-z]/', $senha) ||
            !preg_match('/[0-9]/', $senha)
        ) {
            throw new RuntimeException("A senha deve ter ao menos 12 caracteres, incluindo letras e números.");
        }
        if (!hash_equals($senha, $confirmacao)) {
            throw new RuntimeException("A confirmação da senha não corresponde.");
        }

        $pdo = plataforma_conexao();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT usuario_id
             FROM tokens_recuperacao_senha
             WHERE token_hash = :token_hash
               AND utilizado_em IS NULL AND expira_em > NOW()
             FOR UPDATE"
        );
        $stmt->execute(["token_hash" => hash("sha256", $token)]);
        if ((int) $stmt->fetchColumn() !== $usuarioId) {
            throw new RuntimeException("Este link já foi utilizado ou expirou. Solicite uma nova redefinição.");
        }
        $stmt = $pdo->prepare(
            "UPDATE usuarios SET senha = :senha WHERE id = :usuario_id"
        );
        $stmt->execute([
            "senha" => password_hash($senha, PASSWORD_DEFAULT),
            "usuario_id" => $usuarioId
        ]);
        $stmt = $pdo->prepare(
            "UPDATE tokens_recuperacao_senha
             SET utilizado_em = NOW()
             WHERE usuario_id = :usuario_id AND utilizado_em IS NULL"
        );
        $stmt->execute(["usuario_id" => $usuarioId]);
        $pdo->commit();
        $usuarioId = null;
        $concluido = true;
    } catch (Throwable $falha) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($falha instanceof PDOException) {
            error_log("[Plataforma] Falha ao redefinir senha: " . $falha->getMessage());
            $erro = "Não foi possível redefinir a senha agora. Tente novamente.";
        } else {
            $erro = $falha->getMessage();
        }
    }
}

plataforma_cabecalho("Redefinir senha");
?>
<h1>Redefinir senha</h1>
<?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
<?php if ($concluido): ?>
    <p role="status">Senha atualizada. Você já pode entrar com sua nova senha.</p>
    <p><a href="login.php">Entrar</a></p>
<?php elseif ($usuarioId === null): ?>
    <p>Este link é inválido ou expirou. Solicite uma nova redefinição de senha.</p>
    <p><a href="recuperar-senha.php">Solicitar outro link</a></p>
<?php else: ?>
    <form method="post" action="redefinir-senha.php">
        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
        <input type="hidden" name="token" value="<?= plataforma_escapar($token) ?>">
        <label for="senha">Nova senha (mínimo 12 caracteres, letras e números)</label>
        <input id="senha" name="senha" type="password" minlength="12" autocomplete="new-password" required>
        <label for="confirmacao">Confirme a nova senha</label>
        <input id="confirmacao" name="confirmacao" type="password" minlength="12" autocomplete="new-password" required>
        <button type="submit">Salvar nova senha</button>
    </form>
<?php endif; ?>
<?php plataforma_rodape(); ?>
