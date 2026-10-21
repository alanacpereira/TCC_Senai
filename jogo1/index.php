<?php

require_once __DIR__ . "/../plataforma/config/app.php";
require_once __DIR__ . "/../plataforma/includes/services.php";
require_once __DIR__ . "/../plataforma/includes/layout.php";
$pdo = plataforma_conexao();
$usuario = plataforma_exigir_aluno($pdo, "../plataforma/login.php");
$csrfAcessibilidade = plataforma_token_csrf();

/*
 * Recebe o ID do desafio pela URL.
 *
 * Exemplo:
 * http://localhost/tcc/jogo1/index.php?desafio=1
 *
 * Se nenhum ID for informado, usamos o desafio 1
 * durante o desenvolvimento.
 */
$desafioId = filter_input(
    INPUT_GET,
    "desafio",
    FILTER_VALIDATE_INT
);

if (!$desafioId) {
    $desafioId = 1;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Caça ao Erro - Palavra a Bordo</title>

    <?php plataforma_acessibilidade_bootstrap(true, $usuario["preferencias"] ?? []); ?>
    <link
        rel="stylesheet"
        href="../plataforma/css/acessibilidade.css?v=12"
    >

    <!--
        O CSS será criado depois.
        Por enquanto deixamos o link preparado.
    -->
    <link
        rel="stylesheet"
        href="css/jogo.css?v=14"
    >
    <script
        src="../plataforma/js/acessibilidade.js?v=3"
        defer
    ></script>

</head>


<body class="jogo1-page">
    <?php plataforma_widget_acessibilidade(
        "../plataforma/api/preferencias-acessibilidade.php",
        $csrfAcessibilidade
    ); ?>

    <main id="jogo">
        <?php plataforma_convite_acessibilidade(); ?>

        <!-- =========================================
             CABEÇALHO DO JOGO
             ========================================= -->

        <header class="cabecalho-jogo">

            <div class="informacao-jogo">

                <h1>Caça ao Erro</h1>

                <p>
                    Encontre a forma correta da palavra.
                </p>

            </div>


            <!--
                Informações que serão atualizadas
                pelo JavaScript.
            -->

            <div
                class="status-jogo"
                aria-label="Informações da partida"
            >

                <div class="vidas">

                    <span aria-hidden="true">❤️</span>

                    <span>
                        Vidas:
                    </span>

                    <strong id="vidas">
                        3
                    </strong>

                </div>


                <div class="pontuacao">

                    <span>
                        Pontos:
                    </span>

                    <strong id="pontuacao">
                        0
                    </strong>

                </div>

            </div>

        </header>


        <!-- =========================================
             ÁREA PRINCIPAL DO DESAFIO
             ========================================= -->

        <section
            id="area-desafio"
            aria-live="polite"
        >

            <button
                type="button"
                id="botao-voltar-inicio"
                class="botao-voltar-inicio"
                aria-label="Sair do jogo e abandonar a partida atual"
                disabled
            >
                <span aria-hidden="true">←</span>
                Sair do jogo
            </button>

            <!--
                Essas informações serão preenchidas
                pelo JavaScript quando a partida começar.
            -->

            <div class="progresso">

                <div class="progresso-rotulo">
                    <span id="numero-pergunta">
                        Pergunta 1 de 10
                    </span>
                    <span class="progresso-legenda">
                        RUMO AO TESOURO
                    </span>
                </div>

                <div
                    class="trilho-progresso"
                    role="progressbar"
                    aria-label="Progresso das perguntas"
                    aria-valuemin="0"
                    aria-valuemax="10"
                    aria-valuenow="1"
                >
                    <span id="barra-progresso"></span>
                </div>

            </div>


            <div class="pergunta-container">

                <h2 id="enunciado" class="quadro-pergunta">
                    Carregando pergunta...
                </h2>

            </div>

            <!--
                As alternativas serão criadas
                pelo JavaScript dentro deste elemento.
            -->

            <div
                id="alternativas"
                class="alternativas"
                role="group"
                aria-label="Alternativas da pergunta"
            >
            </div>


            <!--
                Mensagem utilizada para informar
                carregamento ou erros.
            -->

            <p
                id="mensagem"
                role="status"
                aria-live="polite"
            ></p>

        </section>


        <!-- =========================================
             CARTÃO EDUCATIVO
             ========================================= -->

        <!--
            Fica escondido até o jogador errar.

            Quando aparecer, mostrará:
            - resposta escolhida;
            - resposta correta;
            - explicação.
        -->

        <section
            id="explicacao"
            class="explicacao"
            hidden
            aria-live="assertive"
        >

            <h2>
                Vamos aprender!
            </h2>


            <p>

                <strong>
                    Você escolheu:
                </strong>

                <span id="resposta-escolhida">
                </span>

            </p>


            <p>

                <strong>
                    Forma correta:
                </strong>

                <span id="resposta-correta">
                </span>

            </p>


            <div class="texto-explicacao">

                <h3>
                    Por que?
                </h3>

                <p id="texto-explicacao">
                </p>

            </div>


            <button
                type="button"
                id="botao-continuar"
            >
                Continuar
            </button>

        </section>


        <!-- =========================================
             TELA DE INÍCIO
             ========================================= -->

        <section
            id="inicio"
            class="tela-inicio painel-inicio"
        >

            <div class="arte-bau-inicio" aria-hidden="true">
                <img src="css/img/baú.png" alt="">
            </div>

            <div class="conteudo-inicio">
                <h2>
                    Pronto para começar?
                </h2>

                <p>
                    Você terá 3 vidas para responder
                    10 perguntas.
                </p>

                <p>
                    Cada resposta correta vale
                    <strong>100 pontos</strong>.
                </p>

                <button
                    type="button"
                    id="botao-iniciar"
                >
                    Começar desafio
                </button>

                <button
                    type="button"
                    id="botao-sair-inicial"
                    class="botao-voltar-inicio botao-sair-inicial"
                    aria-label="Sair do jogo e voltar para a plataforma"
                >
                    Sair do jogo
                </button>
            </div>

        </section>


        <!-- =========================================
             TELA FINAL
             ========================================= -->

        <section
            id="resultado"
            class="resultado"
            hidden
            aria-live="polite"
        >

            <h2 id="titulo-resultado">
                Desafio finalizado!
            </h2>


            <p id="mensagem-resultado">
            </p>


            <div class="resultado-pontos">

                <span>
                    Pontuação final:
                </span>

                <strong id="pontuacao-final">
                    0
                </strong>

            </div>


            <div class="resultado-acoes">

                <button
                    type="button"
                    id="botao-jogar-novamente"
                >
                    Jogar novamente
                </button>

                <button
                    type="button"
                    id="botao-voltar-menu"
                >
                    Voltar ao menu inicial
                </button>

            </div>

        </section>

    </main>


    <!--
        Guardamos o ID do desafio em um atributo
        para o JavaScript conseguir utilizá-lo.

        O JavaScript poderá acessar:
        elemento.dataset.desafio
    -->

    <div
        id="dados-jogo"
        data-desafio="<?= htmlspecialchars($desafioId) ?>"
        hidden
    ></div>

    <?php plataforma_widget_acessibilidade(
        "../plataforma/api/preferencias-acessibilidade.php",
        $csrfAcessibilidade
    ); ?>

    <!--
        O JavaScript será criado no próximo passo.
    -->

    <script src="js/jogo.js?v=8"></script>

</body>

</html>