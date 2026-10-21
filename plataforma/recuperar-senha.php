<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/email.php";
require_once __DIR__ . "/includes/layout.php";

$pdo = plataforma_conexao();
$enviado = false;

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    try {
        plataforma_validar_csrf();
        $email = strtolower(trim(plataforma_campo($_POST, "email")));

        if (strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare(
                "SELECT id, nome, email
                 FROM usuarios
                 WHERE email = :email AND perfil <> 'convidado'
                 LIMIT 1"
            );
            $stmt->execute(["email" => $email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "SELECT id FROM usuarios WHERE id = :id FOR UPDATE"
                );
                $stmt->execute(["id" => (int) $usuario["id"]]);
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM tokens_recuperacao_senha
                     WHERE usuario_id = :usuario_id
                       AND criado_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
                );
                $stmt->execute(["usuario_id" => (int) $usuario["id"]]);

                if ((int) $stmt->fetchColumn() < 3) {
                    $stmt = $pdo->prepare(
                        "UPDATE tokens_recuperacao_senha
                         SET utilizado_em = NOW()
                         WHERE usuario_id = :usuario_id AND utilizado_em IS NULL"
                    );
                    $stmt->execute(["usuario_id" => (int) $usuario["id"]]);

                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash("sha256", $token);
                    $stmt = $pdo->prepare(
                        "INSERT INTO tokens_recuperacao_senha
                            (usuario_id, token_hash, expira_em)
                         VALUES
                            (:usuario_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
                    );
                    $stmt->execute([
                        "usuario_id" => (int) $usuario["id"],
                        "token_hash" => $tokenHash
                    ]);
                    $pdo->commit();

                    try {
                        $baseUrl = getenv("PLATAFORMA_BASE_URL")
                            ?: "http://localhost/TCC/plataforma";
                        $partesUrl = parse_url($baseUrl);
                        $hostUrl = strtolower((string) ($partesUrl["host"] ?? ""));
                        $hostLocal = in_array($hostUrl, ["localhost", "127.0.0.1", "::1"], true);
                        if (
                            $partesUrl === false ||
                            !in_array($partesUrl["scheme"] ?? "", ["https", "http"], true) ||
                            empty($partesUrl["host"]) ||
                            (!isset($partesUrl["scheme"]) || $partesUrl["scheme"] !== "https") && !$hostLocal ||
                            isset($partesUrl["user"]) ||
                            isset($partesUrl["pass"]) ||
                            isset($partesUrl["query"]) ||
                            isset($partesUrl["fragment"])
                        ) {
                            throw new RuntimeException("A URL pública da plataforma não está configurada com segurança.");
                        }
                        $urlRedefinicao = rtrim($baseUrl, "/")
                            . "/redefinir-senha.php?token=" . rawurlencode($token);
                        plataforma_enviar_email(
                            $usuario["email"],
                            "Redefinição de senha — Palavra a Bordo",
                            "Olá, " . $usuario["nome"] . ".\n\n"
                                . "Recebemos um pedido para redefinir sua senha. Abra este link em até uma hora:\n"
                                . $urlRedefinicao . "\n\n"
                                . "Se você não fez este pedido, ignore esta mensagem. Sua senha não será alterada.\n"
                        );
                    } catch (Throwable $falha) {
                        $stmt = $pdo->prepare(
                            "DELETE FROM tokens_recuperacao_senha
                             WHERE usuario_id = :usuario_id AND token_hash = :token_hash"
                        );
                        $stmt->execute([
                            "usuario_id" => (int) $usuario["id"],
                            "token_hash" => $tokenHash
                        ]);
                        throw $falha;
                    }
                } else {
                    $pdo->commit();
                }
            }
        }
        $enviado = true;
    } catch (Throwable $falha) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("[Plataforma] Falha na solicitação de recuperação de senha: " . $falha->getMessage());
        $enviado = true;
    }
}

plataforma_cabecalho("Recuperação de senha");
?>
<section class="pagina-centralizada pagina-compacta" aria-labelledby="titulo-recuperacao">
<h1 id="titulo-recuperacao">Recuperação de senha</h1>
<?php if ($enviado): ?>
    <p role="status">Se o endereço estiver associado a uma conta e o serviço de e-mail estiver disponível, enviaremos um link válido por uma hora.</p>
<?php else: ?>
    <p>Informe o e-mail da sua conta. Se ele estiver cadastrado, enviaremos um link seguro para criar uma nova senha.</p>
    <form method="post" action="recuperar-senha.php">
        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required>
        <button type="submit">Enviar link de redefinição</button>
    </form>
<?php endif; ?>
<p><a href="login.php">Voltar ao login</a></p>
</section>
<?php plataforma_rodape(); ?>
