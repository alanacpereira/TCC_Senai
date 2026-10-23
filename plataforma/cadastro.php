<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$erro = "";
$pronta = false;
$pdo = null;

$perfilInicial = ($_GET["perfil"] ?? "") === "professor"
    ? "professor"
    : "aluno";

$nomeForm = "";
$emailForm = "";

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

if (
    ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST"
    && $pronta
) {
    try {
        plataforma_validar_csrf();

        $nome = trim(plataforma_campo($_POST, "nome"));
        $email = strtolower(
            trim(plataforma_campo($_POST, "email"))
        );
        $senha = plataforma_campo($_POST, "senha");
        $confirmacao = plataforma_campo(
            $_POST,
            "confirmacao"
        );
        $perfil = plataforma_campo($_POST, "perfil");

        $nomeForm = $nome;
        $emailForm = $email;
        $perfilInicial = $perfil;

        // 1. Validar o perfil escolhido.
        if (!in_array($perfil, ["aluno", "professor"], true)) {
            throw new RuntimeException(
                "Selecione estudante ou professor."
            );
        }

        // 2. Validar nome.
        if ($nome === "" || strlen($nome) > 100) {
            throw new RuntimeException(
                "Informe seu nome completo, com até 100 caracteres."
            );
        }

        // 3. Validar e-mail.
        if (
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                "Informe um endereço de e-mail válido."
            );
        }

        // 4. Validar senha.
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

        // 5. Criar a conta sem exigir convite.
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios
                    (nome, email, senha, perfil)
                 VALUES
                    (:nome, :email, :senha, :perfil)"
            );

            $stmt->execute([
                "nome" => $nome,
                "email" => $email,
                "senha" => password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                ),
                "perfil" => $perfil
            ]);

            $usuarioId = (int) $pdo->lastInsertId();

            // Manter as preferências iniciais de acessibilidade.
            $stmt = $pdo->prepare(
                "INSERT INTO preferencias_acessibilidade
                    (
                        usuario_id,
                        acessibilidade_visual,
                        tamanho_fonte,
                        alto_contraste,
                        espacamento_linhas,
                        melhor_legibilidade,
                        reduzir_animacoes
                    )
                 VALUES
                    (
                        :usuario_id,
                        1,
                        'grande',
                        1,
                        1,
                        1,
                        1
                    )"
            );

            $stmt->execute([
                "usuario_id" => $usuarioId
            ]);

            $pdo->commit();
        } catch (Throwable $falha) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $falha;
        }

        // 6. Iniciar a sessão do usuário.
        session_regenerate_id(true);

        Auth::login([
            "id" => $usuarioId,
            "nome" => $nome,
            "email" => $email
        ]);

        $_SESSION["perfil"] = $perfil;
        $_SESSION["plataforma_csrf"] = bin2hex(
            random_bytes(32)
        );

        // 7. Direcionar conforme o perfil.
        if ($perfil === "professor") {
            plataforma_redirecionar(
                "professor/index.php"
            );
        }

        plataforma_redirecionar("inicio.php");
    } catch (PDOException $falha) {
        if ($falha->getCode() === "23000") {
            $erro = "Este e-mail já está cadastrado.";
        } else {
            error_log(
                "[Plataforma] Erro no cadastro: "
                    . $falha->getMessage()
            );

            $erro = "Não foi possível criar a conta. Tente novamente.";
        }
    } catch (Throwable $falha) {
        $erro = $falha->getMessage();
    }
}

plataforma_cabecalho("Criar conta");
?>

<section
    class="cartao-formulario formulario-cadastro"
    aria-labelledby="titulo-cadastro">

    <header class="cabecalho-cadastro">
        <span class="icone-cadastro" aria-hidden="true">⚓</span>

        <h1 id="titulo-cadastro">
            Faça parte da Palavra a Bordo
        </h1>

        <p class="introducao-formulario">
            Escolha seu perfil e comece sua jornada.
        </p>
    </header>

    <?php if (!$pronta): ?>

        <p role="alert">
            O cadastro depende da preparação do banco de dados.
            Consulte a <a href="index.php">página inicial</a>.
        </p>

    <?php else: ?>

        <?php
        if ($erro !== "") {
            plataforma_mensagem_erro($erro);
        }
        ?>

        <form method="post" action="cadastro.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= plataforma_escapar(
                            plataforma_token_csrf()
                        ) ?>">

            <fieldset class="seletor-perfil">
                <legend>Como você vai usar a plataforma?</legend>

                <label class="opcao-perfil">
                    <input
                        type="radio"
                        name="perfil"
                        value="aluno"
                        <?= $perfilInicial === "aluno"
                            ? "checked"
                            : "" ?>
                        required>

                    <span
                        class="opcao-perfil-icone"
                        aria-hidden="true">⚓</span>

                    <span class="texto-opcao-perfil">
                        <strong>Sou estudante</strong>
                        <small>
                            Jogar, aprender e conquistar recompensas.
                        </small>
                    </span>
                </label>

                <label class="opcao-perfil">
                    <input
                        type="radio"
                        name="perfil"
                        value="professor"
                        <?= $perfilInicial === "professor"
                            ? "checked"
                            : "" ?>
                        required>

                    <span
                        class="opcao-perfil-icone"
                        aria-hidden="true">✦</span>

                    <span class="texto-opcao-perfil">
                        <strong>Sou professor</strong>
                        <small>
                            Acompanhar turmas e resultados dos estudantes.
                        </small>
                    </span>
                </label>
            </fieldset>

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
                <label for="confirmacao">
                    Confirme a senha
                </label>

                <input
                    id="confirmacao"
                    name="confirmacao"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required>
            </div>

            <p class="nota-acessibilidade-formulario">
                As opções de acessibilidade podem ser ajustadas
                pela Central de Acessibilidade.
            </p>

            <button
                type="submit"
                class="botao-enviar-cadastro">
                Criar conta de estudante
            </button>

        </form>

        <p class="link-formulario">
            Já tem uma conta?
            <a href="login.php">Entrar</a>
        </p>

    <?php endif; ?>

</section>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const formulario = document.querySelector(
            ".formulario-cadastro form"
        );

        if (!formulario) {
            return;
        }

        const opcoes = formulario.querySelectorAll(
            'input[name="perfil"]'
        );

        const botao = formulario.querySelector(
            ".botao-enviar-cadastro"
        );

        function atualizarBotao() {
            const selecionado = formulario.querySelector(
                'input[name="perfil"]:checked'
            );

            botao.textContent =
                selecionado && selecionado.value === "professor" ?
                "Criar conta de professor" :
                "Criar conta de estudante";
        }

        opcoes.forEach(function(opcao) {
            opcao.addEventListener("change", atualizarBotao);
        });

        atualizarBotao();
    });
</script>

<?php plataforma_rodape(); ?>