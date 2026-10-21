<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/layout.php";

$erro = "";
$pdo = null;
$configurada = false;

$perfilInicial = ($_GET["perfil"] ?? "") === "professor"
    ? "professor"
    : "aluno";

$emailForm = "";

try {
    $pdo = plataforma_conexao();
    $configurada = plataforma_migracao_aplicada($pdo);

    if (!$configurada) {
        http_response_code(503);
    }
} catch (Throwable $falha) {
    error_log(
        "[Plataforma] Falha ao preparar login: "
            . $falha->getMessage()
    );

    http_response_code(503);
}

if (
    ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST"
    && $configurada
) {
    try {
        plataforma_validar_csrf();

        $email = strtolower(trim(plataforma_campo($_POST, "email")));
        $senha = plataforma_campo($_POST, "senha");
        $perfilDesejado = plataforma_campo($_POST, "perfil");

        $emailForm = $email;

        if (!in_array($perfilDesejado, ["aluno", "professor"], true)) {
            throw new RuntimeException(
                "Selecione se deseja entrar como estudante ou professor."
            );
        }

        $perfilInicial = $perfilDesejado;

        if (
            strlen($email) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                "E-mail ou senha inválidos."
            );
        }

        $origem = (string) (
            $_SERVER["REMOTE_ADDR"] ?? "desconhecida"
        );

        if (plataforma_login_bloqueado($pdo, $email, $origem)) {
            throw new RuntimeException(
                "Não foi possível entrar. Aguarde alguns minutos e tente novamente."
            );
        }

        $stmt = $pdo->prepare(
            "SELECT id, nome, email, senha, perfil
             FROM usuarios
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute([
            "email" => $email
        ]);

        $conta = $stmt->fetch();

        /*
         * Primeiro validamos a existência da conta e a senha.
         * O perfil selecionado não concede permissões.
         */
        if (
            !$conta
            || $conta["perfil"] === "convidado"
            || !password_verify($senha, $conta["senha"])
        ) {
            plataforma_contabilizar_falha_login(
                $pdo,
                $email,
                $origem
            );

            throw new RuntimeException(
                "E-mail ou senha inválidos."
            );
        }

        /*
         * A senha está correta, mas o perfil solicitado
         * precisa corresponder ao perfil registrado no banco.
         */
        if ($conta["perfil"] !== $perfilDesejado) {
            throw new RuntimeException(
                $perfilDesejado === "professor"
                    ? "Esta conta não está cadastrada como professor. Selecione Estudante ou utilize uma conta docente autorizada."
                    : "Esta conta está cadastrada como professor. Selecione Professor para continuar."
            );
        }

        plataforma_limpar_falhas_login(
            $pdo,
            $email,
            $origem
        );

        session_regenerate_id(true);

        Auth::login($conta);

        /*
         * Guardar o perfil real da conta na sessão.
         * As páginas protegidas também devem verificar
         * o perfil no servidor.
         */
        $_SESSION["perfil"] = $conta["perfil"];

        $_SESSION["plataforma_csrf"] = bin2hex(
            random_bytes(32)
        );

        if ($conta["perfil"] === "professor") {
            plataforma_redirecionar("professor/index.php");
        }

        plataforma_redirecionar("inicio.php");
    } catch (Throwable $falha) {
        if ($falha instanceof PDOException) {
            error_log(
                "[Plataforma] Erro ao autenticar: "
                    . $falha->getMessage()
            );

            $erro = "Não foi possível validar o acesso agora. Tente novamente.";
        } else {
            $erro = $falha->getMessage();
        }
    }
}

plataforma_cabecalho("Entrar");
?>

<section
    class="cartao-formulario formulario-login"
    aria-labelledby="titulo-login">
    <header class="cabecalho-login">
        <span class="icone-cadastro" aria-hidden="true">⚓</span>

        <h1 id="titulo-login">
            Bem-vindo de volta!
        </h1>

        <p class="introducao-formulario">
            Entre na Palavra a Bordo e continue sua jornada.
        </p>
    </header>

    <?php if (!$configurada): ?>

        <p role="alert">
            O acesso depende da migração segura da plataforma.
            Revise as instruções na
            <a href="index.php">página inicial</a>.
            Nenhum dado foi alterado automaticamente.
        </p>

    <?php else: ?>

        <?php
        if ($erro !== "") {
            plataforma_mensagem_erro($erro);
        }
        ?>

        <form method="post" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= plataforma_escapar(plataforma_token_csrf()) ?>">

            <fieldset class="seletor-perfil seletor-perfil-login">
                <legend>Como deseja entrar?</legend>

                <label class="opcao-perfil">
                    <input
                        type="radio"
                        name="perfil"
                        value="aluno"
                        <?= $perfilInicial === "aluno" ? "checked" : "" ?>
                        required>

                    <span class="opcao-perfil-icone" aria-hidden="true">
                        ⚓
                    </span>

                    <span class="texto-opcao-perfil">
                        <strong>Estudante</strong>
                        <small>Jogos, progresso e recompensas.</small>
                    </span>
                </label>

                <label class="opcao-perfil">
                    <input
                        type="radio"
                        name="perfil"
                        value="professor"
                        <?= $perfilInicial === "professor" ? "checked" : "" ?>
                        required>

                    <span class="opcao-perfil-icone" aria-hidden="true">
                        ✦
                    </span>

                    <span class="texto-opcao-perfil">
                        <strong>Professor</strong>
                        <small>Turmas, resultados e relatórios.</small>
                    </span>
                </label>
            </fieldset>

            <div class="campo-formulario">
                <label for="email">E-mail</label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="150"
                    autocomplete="username"
                    value="<?= plataforma_escapar($emailForm) ?>"
                    required>
            </div>

            <div class="campo-formulario">
                <label for="senha">Senha</label>

                <div class="controle-senha">
                    <input
                        id="senha"
                        name="senha"
                        type="password"
                        autocomplete="current-password"
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

            <button type="submit" class="botao-enviar-login">
                Entrar como estudante
            </button>

        </form>

        <nav
            class="links-formulario"
            aria-label="Opções da conta">
            <a href="cadastro.php?perfil=aluno">
                Criar conta de estudante
            </a>

            <a href="cadastro.php?perfil=professor">
                Criar conta para professores
            </a>

            <a href="login.php?perfil=professor">
                Já tenho uma conta de professor
            </a>

            <a href="recuperar-senha.php">
                Esqueci minha senha
            </a>
        </nav>

    <?php endif; ?>
</section>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const formulario = document.querySelector(
            ".formulario-login form"
        );

        if (!formulario) {
            return;
        }

        const opcoesPerfil = formulario.querySelectorAll(
            'input[name="perfil"]'
        );

        const botaoEntrar = formulario.querySelector(
            ".botao-enviar-login"
        );

        function atualizarBotao() {
            const selecionado = formulario.querySelector(
                'input[name="perfil"]:checked'
            );

            const professor = selecionado?.value === "professor";

            botaoEntrar.textContent = professor ?
                "Entrar como professor" :
                "Entrar como estudante";
        }

        opcoesPerfil.forEach(function(opcao) {
            opcao.addEventListener("change", atualizarBotao);
        });

        atualizarBotao();
    });
</script>

<?php plataforma_rodape(); ?>