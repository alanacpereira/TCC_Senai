<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$erro = "";
$pronta = false;
$pdo = null;

try {
    $pdo = plataforma_conexao();
    $pronta = plataforma_migracao_aplicada($pdo);
    if (!$pronta) {
        http_response_code(503);
    }
} catch (Throwable $falha) {
    error_log("[Plataforma] Falha ao preparar cadastro: " . $falha->getMessage());
    http_response_code(503);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && $pronta) {
    try {
        plataforma_validar_csrf();
        $nome = trim(plataforma_campo($_POST, "nome"));
        $email = strtolower(trim(plataforma_campo($_POST, "email")));
        $senha = plataforma_campo($_POST, "senha");
        $confirmacao = plataforma_campo($_POST, "confirmacao");
        $acessibilidade = isset($_POST["acessibilidade_visual"]) ? 1 : 0;

        if ($nome === "" || strlen($nome) > 100) {
            throw new RuntimeException("Informe seu nome (até 100 caracteres).");
        }
        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Informe um endereço de e-mail válido.");
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

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nome, email, senha, perfil)
                 VALUES (:nome, :email, :senha, 'aluno')"
            );
            $stmt->execute([
                "nome" => $nome,
                "email" => $email,
                "senha" => password_hash($senha, PASSWORD_DEFAULT)
            ]);
            $usuarioId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare(
                "INSERT INTO preferencias_acessibilidade
                    (usuario_id, acessibilidade_visual)
                 VALUES (:usuario_id, :acessibilidade)"
            );
            $stmt->execute([
                "usuario_id" => $usuarioId,
                "acessibilidade" => $acessibilidade
            ]);
            $pdo->commit();
        } catch (Throwable $falha) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $falha;
        }

        session_regenerate_id(true);
        Auth::login([
            "id" => $usuarioId,
            "nome" => $nome,
            "email" => $email
        ]);
        $_SESSION["plataforma_csrf"] = bin2hex(random_bytes(32));
        plataforma_redirecionar("inicio.php");
    } catch (PDOException $falha) {
        if ($falha->getCode() === "23000") {
            $erro = "Não foi possível criar a conta. Verifique se o e-mail já está em uso.";
        } else {
            error_log("[Plataforma] Erro ao cadastrar estudante: " . $falha->getMessage());
            $erro = "Não foi possível criar a conta agora. Tente novamente.";
        }
    } catch (Throwable $falha) {
        $erro = $falha->getMessage();
    }
}

plataforma_cabecalho("Cadastro de estudante");
?>
<h1>Criar conta de estudante</h1>
<?php if (!$pronta): ?>
    <p role="alert">O cadastro depende da migração da plataforma. Consulte a <a href="index.php">página inicial</a>; nenhum script foi executado automaticamente.</p>
<?php else: ?>
    <?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
    <form method="post" action="cadastro.php">
        <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
        <label for="nome">Nome completo</label>
        <input id="nome" name="nome" maxlength="100" autocomplete="name" required>
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required>
        <label for="senha">Senha (mínimo 12 caracteres, letras e números)</label>
        <input id="senha" name="senha" type="password" minlength="12" autocomplete="new-password" required>
        <label for="confirmacao">Confirme a senha</label>
        <input id="confirmacao" name="confirmacao" type="password" minlength="12" autocomplete="new-password" required>
        <label>
            <input type="checkbox" name="acessibilidade_visual" value="1">
            Quero ativar os recursos de acessibilidade visual
        </label>
        <button type="submit">Criar conta</button>
    </form>
<?php endif; ?>
<?php plataforma_rodape(); ?>
