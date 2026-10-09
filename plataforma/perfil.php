<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/services.php";
require_once __DIR__ . "/includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("index.php?configuracao=pendente");
}
$usuario = plataforma_exigir_login($pdo);
$erro = "";
$sucesso = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    try {
        plataforma_validar_csrf();
        $nome = trim(plataforma_campo($_POST, "nome"));
        if ($nome === "" || strlen($nome) > 100) {
            throw new RuntimeException("Informe um nome com até 100 caracteres.");
        }
        $tamanhoFonte = (string) ($_POST["tamanho_fonte"] ?? "padrao");
        if (!in_array($tamanhoFonte, ["padrao", "grande", "muito_grande"], true)) {
            throw new RuntimeException("Tamanho de fonte inválido.");
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = :nome WHERE id = :id");
        $stmt->execute(["nome" => $nome, "id" => (int) $usuario["id"]]);

        $senhaAtual = plataforma_campo($_POST, "senha_atual");
        $senhaNova = plataforma_campo($_POST, "senha_nova");
        $confirmacao = plataforma_campo($_POST, "confirmacao_senha");
        $alterarSenha = $senhaAtual !== "" || $senhaNova !== "" || $confirmacao !== "";
        if ($alterarSenha) {
            if ($senhaAtual === "" || $senhaNova === "" || $confirmacao === "") {
                throw new RuntimeException("Preencha a senha atual, a nova senha e a confirmação para alterar a senha.");
            }
            $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id FOR UPDATE");
            $stmt->execute(["id" => (int) $usuario["id"]]);
            $hashAtual = (string) $stmt->fetchColumn();
            if (!password_verify($senhaAtual, $hashAtual)) {
                throw new RuntimeException("A senha atual está incorreta.");
            }
            if (
                strlen($senhaNova) < 12 ||
                !preg_match('/[A-Za-z]/', $senhaNova) ||
                !preg_match('/[0-9]/', $senhaNova)
            ) {
                throw new RuntimeException("A nova senha precisa ter 12 caracteres e incluir letras e números.");
            }
            if (!hash_equals($senhaNova, $confirmacao)) {
                throw new RuntimeException("A confirmação da nova senha não corresponde.");
            }
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
            $stmt->execute([
                "senha" => password_hash($senhaNova, PASSWORD_DEFAULT),
                "id" => (int) $usuario["id"]
            ]);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO preferencias_acessibilidade
                (usuario_id, acessibilidade_visual, tamanho_fonte,
                 alto_contraste, espacamento_linhas, melhor_legibilidade,
                 reduzir_animacoes, destacar_interativos)
             VALUES
                (:usuario_id, :acessibilidade_visual, :tamanho_fonte,
                 :alto_contraste, :espacamento_linhas, :melhor_legibilidade,
                 :reduzir_animacoes, :destacar_interativos)
             ON DUPLICATE KEY UPDATE
                acessibilidade_visual = VALUES(acessibilidade_visual),
                tamanho_fonte = VALUES(tamanho_fonte),
                alto_contraste = VALUES(alto_contraste),
                espacamento_linhas = VALUES(espacamento_linhas),
                melhor_legibilidade = VALUES(melhor_legibilidade),
                reduzir_animacoes = VALUES(reduzir_animacoes),
                destacar_interativos = VALUES(destacar_interativos)"
        );
        $preferenciasPost = [
            "acessibilidade_visual" => isset($_POST["acessibilidade_visual"]) ? 1 : 0,
            "alto_contraste" => isset($_POST["alto_contraste"]) ? 1 : 0,
            "espacamento_linhas" => isset($_POST["espacamento_linhas"]) ? 1 : 0,
            "melhor_legibilidade" => isset($_POST["melhor_legibilidade"]) ? 1 : 0,
            "reduzir_animacoes" => isset($_POST["reduzir_animacoes"]) ? 1 : 0,
            "destacar_interativos" => isset($_POST["destacar_interativos"]) ? 1 : 0
        ];
        $stmt->execute([
            "usuario_id" => (int) $usuario["id"],
            "tamanho_fonte" => $tamanhoFonte
        ] + $preferenciasPost);
        $pdo->commit();
        $_SESSION["usuario_nome"] = $nome;
        if ($alterarSenha) {
            session_regenerate_id(true);
        }
        $sucesso = "Perfil atualizado.";
        $usuario = plataforma_usuario($pdo);
    } catch (Throwable $falha) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $falha instanceof PDOException
            ? "Não foi possível atualizar o perfil."
            : $falha->getMessage();
        if ($falha instanceof PDOException) {
            error_log("[Plataforma] Falha ao atualizar perfil: " . $falha->getMessage());
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT saldo_pontos FROM carteiras WHERE usuario_id = :usuario_id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$saldo = $stmt->fetchColumn();
$preferencias = plataforma_preferencias($pdo, (int) $usuario["id"]);

plataforma_cabecalho("Perfil", $usuario);
?>
<h1>Meu perfil</h1>
<?php if ($erro !== ""): plataforma_mensagem_erro($erro); endif; ?>
<?php if ($sucesso !== ""): ?><p role="status"><?= plataforma_escapar($sucesso) ?></p><?php endif; ?>
<dl>
    <dt>E-mail</dt><dd><?= plataforma_escapar($usuario["email"]) ?></dd>
    <dt>Perfil</dt><dd><?= $usuario["perfil"] === "professor" ? "Professor" : "Estudante" ?></dd>
    <dt>Nível</dt><dd><?= (int) $usuario["nivel"] ?></dd>
    <dt>Experiência</dt><dd><?= number_format((int) $usuario["experiencia"], 0, ",", ".") ?> XP</dd>
    <dt>Pontos no tesouro</dt><dd><?= number_format((int) $saldo, 0, ",", ".") ?></dd>
</dl>
<form method="post" action="perfil.php">
    <input type="hidden" name="csrf_token" value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
    <label for="nome">Nome</label>
    <input id="nome" name="nome" maxlength="100" value="<?= plataforma_escapar($usuario["nome"]) ?>" required>
    <fieldset>
        <legend>Alterar senha (opcional)</legend>
        <label for="senha-atual">Senha atual</label>
        <input id="senha-atual" name="senha_atual" type="password" autocomplete="current-password">
        <label for="senha-nova">Nova senha</label>
        <input id="senha-nova" name="senha_nova" type="password" minlength="12" autocomplete="new-password">
        <label for="confirmacao-senha">Confirme a nova senha</label>
        <input id="confirmacao-senha" name="confirmacao_senha" type="password" minlength="12" autocomplete="new-password">
    </fieldset>
    <fieldset>
        <legend>Preferências de acessibilidade visual</legend>
        <span id="preferencias-acessibilidade"></span>
        <label><input type="checkbox" name="acessibilidade_visual" value="1" <?= !empty($preferencias["acessibilidade_visual"]) ? "checked" : "" ?>> Ativar recursos visuais</label>
        <label for="tamanho-fonte">Tamanho da fonte</label>
        <select id="tamanho-fonte" name="tamanho_fonte">
            <?php foreach (["padrao" => "Padrão", "grande" => "Grande", "muito_grande" => "Muito grande"] as $valor => $rotulo): ?>
                <option value="<?= $valor ?>" <?= ($preferencias["tamanho_fonte"] ?? "padrao") === $valor ? "selected" : "" ?>><?= $rotulo ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        $opcoesAcessibilidade = [
            "alto_contraste" => "Alto contraste",
            "espacamento_linhas" => "Aumentar espaçamento entre linhas",
            "melhor_legibilidade" => "Melhorar espaçamento e legibilidade do texto",
            "reduzir_animacoes" => "Reduzir animações",
            "destacar_interativos" => "Destacar controles interativos"
        ];
        foreach ($opcoesAcessibilidade as $campo => $rotulo):
        ?>
            <label><input type="checkbox" name="<?= plataforma_escapar($campo) ?>" value="1" <?= !empty($preferencias[$campo]) ? "checked" : "" ?>> <?= plataforma_escapar($rotulo) ?></label>
        <?php endforeach; ?>
    </fieldset>
    <button type="submit">Salvar alterações</button>
</form>
<?php plataforma_rodape(); ?>
