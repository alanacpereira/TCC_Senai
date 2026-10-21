<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$erro = "";
$pronta = false;
$pdo = null;

$nomeForm = "";
$emailForm = "";
$conviteForm = "";
$perfilInicial = (($_POST["perfil"] ?? $_GET["perfil"] ?? "") === "professor")
    ? "professor"
    : "aluno";

if ($perfilInicial === "professor") {
    header("Referrer-Policy: no-referrer");
}

if (isset($_GET["convite"]) && is_string($_GET["convite"])) {
    $conviteForm = $_GET["convite"];
}

try {
    $pdo = plataforma_conexao();
    $pronta = plataforma_migracao_aplicada($pdo);

    if (!$pronta) {
        http_response_code(503);
    }
} catch (Throwable $falha) {
    error_log(
        "[Plataforma] Falha ao preparar cadastro: "
            . $falha->getMessage()
    );

    http_response_code(503);
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" && $pronta) {
    try {
        plataforma_validar_csrf();

        $nome = trim(plataforma_campo($_POST, "nome"));
        $email = strtolower(trim(plataforma_campo($_POST, "email")));
        $senha = plataforma_campo($_POST, "senha");
        $confirmacao = plataforma_campo($_POST, "confirmacao");
        $perfil = plataforma_campo($_POST, "perfil");
        $avatarId = plataforma_campo(
            $_POST + ["avatar_id" => "marinheiro"],
            "avatar_id"
        );
        $conviteForm = plataforma_campo($_POST, "convite");

        $nomeForm = $nome;
        $emailForm = $email;

        if (!in_array($perfil, ["aluno", "professor"], true)) {
            throw new RuntimeException(
                "Selecione um tipo de conta válido."
            );
        }
        $perfilInicial = $perfil;

        if (
            $perfil === "aluno"
            && !array_key_exists($avatarId, plataforma_avatares())
        ) {
            throw new RuntimeException("Selecione um avatar disponível.");
        }

        if ($nome === "" || strlen($nome) > 100) {
            throw new RuntimeException(
                "Informe seu nome completo, com até 100 caracteres."
            );
        }

        if (
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                "Informe um endereço de e-mail válido."
            );
        }

        if (
            $perfil === "professor"
            && !preg_match('/\A[a-f0-9]{64}\z/i', $conviteForm)
        ) {
            throw new RuntimeException(
                "Informe um convite institucional válido e ainda não utilizado."
            );
        }

        if (
            strlen($senha) < 12 ||
            !preg_match('/[A-Za-z]/', $senha) ||
            !preg_match('/[0-9]/', $senha)
        ) {
            throw new RuntimeException(
                "A senha deve ter ao menos 12 caracteres, incluindo letras e números."
            );
        }

        if (!hash_equals($senha, $confirmacao)) {
            throw new RuntimeException(
                "A confirmação da senha não corresponde."
            );
        }

        $pdo->beginTransaction();

        try {
            $idConvite = null;
            if ($perfil === "professor") {
                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM convites_professores
                     WHERE token_hash = :token_hash
                       AND email = :email
                       AND resgatado_em IS NULL
                       AND expira_em > NOW()
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmt->execute([
                    "token_hash" => hash("sha256", $conviteForm),
                    "email" => $email
                ]);
                $idConvite = $stmt->fetchColumn();

                if ($idConvite === false) {
                    throw new RuntimeException(
                        "O convite não é válido para este e-mail, expirou ou já foi utilizado."
                    );
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nome, email, senha, perfil, avatar_id)
                 VALUES (:nome, :email, :senha, :perfil, :avatar_id)"
            );

            $stmt->execute([
                "nome" => $nome,
                "email" => $email,
                "senha" => password_hash($senha, PASSWORD_DEFAULT),
                "perfil" => $perfil,
                "avatar_id" => $avatarId
            ]);

            $usuarioId = (int) $pdo->lastInsertId();

            /*
             * Criar as preferências iniciais de acessibilidade.
             */
            $stmt = $pdo->prepare(
                "INSERT INTO preferencias_acessibilidade
                    (
                        usuario_id,
                        configurada,
                        acessibilidade_visual,
                        tamanho_fonte,
                        alto_contraste,
                        espacamento_linhas,
                        melhor_legibilidade,
                        reduzir_animacoes
                    )
                 VALUES (
                    :usuario_id,
                    0,
                    0,
                    'padrao',
                    0,
                    0,
                    0,
                    0
                 )"
            );

            $stmt->execute([
                "usuario_id" => $usuarioId
            ]);

            if ($idConvite !== null) {
                $stmt = $pdo->prepare(
                    "UPDATE convites_professores
                     SET resgatado_em = NOW(), resgatado_por_usuario_id = :usuario_id
                     WHERE id = :id
                       AND resgatado_em IS NULL
                       AND expira_em > NOW()"
                );
                $stmt->execute([
                    "usuario_id" => $usuarioId,
                    "id" => (int) $idConvite
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        "O convite já foi utilizado ou expirou. Solicite um novo convite à instituição."
                    );
                }
            }

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

        $_SESSION["perfil"] = $perfil;
        $_SESSION["plataforma_csrf"] = bin2hex(random_bytes(32));

        if ($perfil === "professor") {
            plataforma_redirecionar("professor/index.php");
        }

        plataforma_redirecionar("inicio.php");
    } catch (PDOException $falha) {
        if ($falha->getCode() === "23000") {
            $erro = "Não foi possível criar a conta. Verifique se o e-mail já está em uso.";
        } else {
            error_log(
                "[Plataforma] Erro ao cadastrar usuário: "
                    . $falha->getMessage()
            );

            $erro = "Não foi possível criar a conta agora. Tente novamente.";
        }
    } catch (Throwable $falha) {
        $erro = $falha->getMessage();
    }
}

plataforma_cabecalho(
    $perfilInicial === "professor"
        ? "Criar conta para professor"
        : "Criar conta"
);
?>

<section
    class="cartao-formulario formulario-cadastro<?= $perfilInicial === "professor" ? " formulario-cadastro-professor" : "" ?>"
    aria-labelledby="titulo-cadastro">

    <header class="cabecalho-cadastro">
        <span class="icone-cadastro" aria-hidden="true">⚓</span>

        <h1 id="titulo-cadastro">
            <?= $perfilInicial === "professor"
                ? "Crie sua conta de professor"
                : "Faça parte da Palavra a Bordo" ?>
        </h1>

        <p class="introducao-formulario">
            <?= $perfilInicial === "professor"
                ? "Use o convite enviado pela instituição para acessar sua área docente."
                : "Crie sua conta de estudante e comece sua jornada." ?>
        </p>
    </header>

    <?php if (!$pronta): ?>

        <p role="alert">
            O cadastro depende da migração da plataforma.
            Consulte a <a href="index.php">página inicial</a>.
            Nenhum script foi executado automaticamente.
        </p>

    <?php else: ?>

        <?php
        if ($erro !== "") {
            plataforma_mensagem_erro($erro);
        }
        ?>

        <form
            method="post"
            action="cadastro.php?perfil=<?= plataforma_escapar($perfilInicial) ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">
            <input type="hidden" name="perfil" value="<?= plataforma_escapar($perfilInicial) ?>">

            <?php if ($perfilInicial === "professor"): ?>
                <div class="campo-formulario">
                    <label for="convite-professor">Convite institucional</label>
                    <input
                        id="convite-professor"
                        name="convite"
                        type="text"
                        maxlength="64"
                        pattern="[A-Fa-f0-9]{64}"
                        autocomplete="off"
                        spellcheck="false"
                        value="<?= plataforma_escapar($conviteForm) ?>"
                        aria-describedby="ajuda-convite-professor"
                        required>
                    <small id="ajuda-convite-professor">
                        O convite é pessoal, de uso único e vinculado ao seu e-mail.
                    </small>
                </div>
            <?php else: ?>
                <input type="hidden" name="convite" value="">
            <?php endif; ?>

            <div class="campo-formulario">
                <label for="nome">Nome completo</label>

                <input
                    id="nome"
                    name="nome"
                    maxlength="100"
                    autocomplete="name"
                    value="<?= plataforma_escapar($nomeForm) ?>"
                    required>
            </div>

            <div class="campo-formulario">
                <label for="email">E-mail</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="150"
                    autocomplete="email"
                    value="<?= plataforma_escapar($emailForm) ?>"
                    required>
            </div>

            <div class="campo-formulario">
                <label for="senha">
                    Senha (mínimo de 12 caracteres, letras e números)
                </label>

                <div class="controle-senha">
                    <input
                        id="senha"
                        name="senha"
                        type="password"
                        minlength="12"
                        autocomplete="new-password"
                        required>

                    <button
                        type="button"
                        data-toggle-password
                        aria-controls="senha"
                        aria-pressed="false">
                        Mostrar
                    </button>
                </div>
            </div>

            <div class="campo-formulario">
                <label for="confirmacao">Confirme a senha</label>

                <input
                    id="confirmacao"
                    name="confirmacao"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required>
            </div>

            <?php if ($perfilInicial === "aluno"): ?>
                <fieldset class="galeria-avatares">
                    <legend>Escolha seu avatar</legend>
                    <p>Você poderá trocar o avatar depois no seu perfil.</p>
                    <div class="opcoes-avatar">
                        <?php foreach (plataforma_avatares() as $idAvatar => $avatar): ?>
                            <label class="opcao-avatar">
                                <input
                                    type="radio"
                                    name="avatar_id"
                                    value="<?= plataforma_escapar($idAvatar) ?>"
                                    <?= $idAvatar === "marinheiro" ? "checked" : "" ?>>
                                <span aria-hidden="true"><?= plataforma_escapar($avatar["icone"]) ?></span>
                                <strong><?= plataforma_escapar($avatar["nome"]) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <p class="nota-acessibilidade-formulario">
                Os recursos de acessibilidade não são ativados automaticamente.
                Você pode escolher se deseja ativá-los quando visitar a plataforma.
            </p>

            <button
                type="submit"
                class="botao-enviar-cadastro">
                <?= $perfilInicial === "professor"
                    ? "Criar conta de professor"
                    : "Criar conta de estudante" ?>
            </button>

        </form>

        <p class="link-formulario link-ja-tenho-conta">
            <span>Já tem uma conta?</span>
            <a class="botao-link-conta" href="login.php?perfil=<?= plataforma_escapar($perfilInicial) ?>">Entrar</a>
        </p>

        <?php if ($perfilInicial === "aluno"): ?>
            <p class="link-formulario">
                É professor?
                <a href="cadastro.php?perfil=professor">Criar conta para professores</a>
            </p>
        <?php endif; ?>

    <?php endif; ?>
</section>

<?php plataforma_rodape(); ?>