<?php

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

    <!--
        O CSS será criado depois.
        Por enquanto deixamos o link preparado.
    -->
    <link
        rel="stylesheet"
        href="css/jogo.css"
    >

</head>


<body>

    <main id="jogo">

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

            <!--
                Essas informações serão preenchidas
                pelo JavaScript quando a partida começar.
            -->

            <div class="progresso">

                <span id="numero-pergunta">
                    Pergunta 1 de 5
                </span>

            </div>


            <div class="pergunta-container">

                <h2 id="enunciado">
                    Carregando pergunta...
                </h2>


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
            class="tela-inicio"
        >

            <h2>
                Pronto para começar?
            </h2>

            <p>
                Você terá 3 vidas para responder
                5 perguntas.
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


            <button
                type="button"
                id="botao-jogar-novamente"
            >
                Jogar novamente
            </button>

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


    <!--
        O JavaScript será criado no próximo passo.
    -->

    <script src="js/jogo.js?v=3"></script>

</body>

</html>