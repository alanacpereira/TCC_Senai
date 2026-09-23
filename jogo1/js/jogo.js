/*
 * ============================================================
 * CAÇA AO ERRO — ORTOGRAFIA
 * JavaScript principal do jogo
 * ============================================================
 *
 * Responsabilidades deste arquivo:
 *
 * 1. Iniciar a partida
 * 2. Buscar as perguntas
 * 3. Mostrar as alternativas
 * 4. Enviar a resposta escolhida
 * 5. Atualizar pontos e vidas
 * 6. Mostrar a explicação quando houver erro
 * 7. Avançar pelas 5 perguntas
 * 8. Mostrar o resultado final
 *
 * O JavaScript NÃO decide se uma resposta está correta.
 * Quem faz essa verificação é o PHP.
 */


// ============================================================
// ELEMENTOS DA PÁGINA
// ============================================================

const botaoIniciar = document.getElementById("botao-iniciar");

const botaoContinuar = document.getElementById("botao-continuar");

const botaoJogarNovamente = document.getElementById(
    "botao-jogar-novamente"
);

const inicio = document.getElementById("inicio");

const areaDesafio = document.getElementById(
    "area-desafio"
);

const explicacao = document.getElementById(
    "explicacao"
);

const resultado = document.getElementById(
    "resultado"
);

const enunciado = document.getElementById(
    "enunciado"
);

const alternativas = document.getElementById(
    "alternativas"
);

const vidasElemento = document.getElementById(
    "vidas"
);

const pontuacaoElemento = document.getElementById(
    "pontuacao"
);

const numeroPerguntaElemento = document.getElementById(
    "numero-pergunta"
);

const mensagem = document.getElementById(
    "mensagem"
);

const respostaEscolhidaElemento =
    document.getElementById(
        "resposta-escolhida"
    );

const respostaCorretaElemento =
    document.getElementById(
        "resposta-correta"
    );

const textoExplicacaoElemento =
    document.getElementById(
        "texto-explicacao"
    );

const tituloResultado =
    document.getElementById(
        "titulo-resultado"
    );

const mensagemResultado =
    document.getElementById(
        "mensagem-resultado"
    );

const pontuacaoFinal =
    document.getElementById(
        "pontuacao-final"
    );

const dadosJogo =
    document.getElementById(
        "dados-jogo"
    );


// ============================================================
// VARIÁVEIS DO JOGO
// ============================================================

/*
 * ID do desafio escolhido.
 *
 * Ele vem do index.php através de:
 *
 * data-desafio="1"
 */
const desafioId = dadosJogo.dataset.desafio;


/*
 * ID da tentativa criada no banco.
 *
 * No começo ainda não temos uma tentativa.
 */
let tentativaId = null;


/*
 * Número da pergunta atual.
 *
 * Começamos na primeira.
 */
let numeroPerguntaAtual = 1;


/*
 * Guarda os dados da pergunta que está
 * sendo mostrada na tela.
 */
let perguntaAtual = null;


/*
 * Controla se o jogador pode clicar
 * em uma alternativa.
 *
 * Isso evita vários cliques enquanto
 * o servidor está processando a resposta.
 */
let podeResponder = false;


// ============================================================
// EVENTOS DOS BOTÕES
// ============================================================

/*
 * Quando o jogador clicar em
 * "Começar desafio".
 */
botaoIniciar.addEventListener(
    "click",
    iniciarJogo
);


/*
 * Botão utilizado depois de uma resposta errada.
 */
botaoContinuar.addEventListener(
    "click",
    continuarJogo
);


/*
 * Permite jogar novamente depois
 * que o desafio terminar.
 */
botaoJogarNovamente.addEventListener(
    "click",
    iniciarJogo
);


// ============================================================
// INICIAR O JOGO
// ============================================================

async function iniciarJogo() {
    /*
     * Desativa temporariamente o botão
     * para evitar dois pedidos ao mesmo tempo.
     */
    botaoIniciar.disabled = true;

    botaoJogarNovamente.disabled = true;


    /*
     * Esconde as telas que não devem aparecer
     * durante o início da partida.
     */
    explicacao.hidden = true;
    resultado.hidden = true;


    /*
     * Mostra a área principal.
     */
    areaDesafio.hidden = false;


    /*
     * Mostra mensagem enquanto o PHP
     * cria a tentativa.
     */
    mensagem.textContent =
        "Iniciando desafio...";


    try {

        /*
         * Envia o ID do desafio para:
         *
         * api/iniciar.php
         */
        const dados = new URLSearchParams();

        dados.append(
            "desafio_id",
            desafioId
        );


        const resposta = await fetch(
            "api/iniciar.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: dados
            }
        );


        /*
         * Converte a resposta do PHP
         * de JSON para objeto JavaScript.
         */
        const resultadoAPI =
            await resposta.json();


        /*
         * Verifica se o PHP informou algum erro.
         */
        if (!resposta.ok || !resultadoAPI.sucesso) {

            throw new Error(
                resultadoAPI.mensagem ||
                "Não foi possível iniciar o jogo."
            );
        }


        /*
         * Guarda o ID da tentativa.
         *
         * Exemplo:
         *
         * tentativaId = 6
         */
        tentativaId =
            resultadoAPI.tentativa_id;


        /*
         * Reinicia o contador.
         */
        numeroPerguntaAtual = 1;


        /*
         * Atualiza a interface.
         */
        vidasElemento.textContent =
            resultadoAPI.vidas;

        pontuacaoElemento.textContent =
            resultadoAPI.pontuacao;


        /*
         * Esconde a tela inicial.
         */
        inicio.hidden = true;


        /*
         * Carrega a primeira pergunta.
         */
        await carregarPergunta();


    } catch (erro) {

        /*
         * Mostra o erro para o jogador.
         */
        mensagem.textContent =
            erro.message;


        /*
         * Libera o botão novamente.
         */
        botaoIniciar.disabled = false;
        botaoJogarNovamente.disabled = false;
    }
}


// ============================================================
// CARREGAR PERGUNTA
// ============================================================

async function carregarPergunta() {
    /*
     * Enquanto carregamos a pergunta,
     * o jogador não pode responder.
     */
    podeResponder = false;


    /*
     * Limpa as alternativas anteriores.
     */
    alternativas.innerHTML = "";


    /*
     * Mostra uma mensagem temporária.
     */
    mensagem.textContent =
        "Carregando pergunta...";


    try {

        /*
         * Monta a URL da API.
         *
         * Exemplo:
         *
         * api/pergunta.php?tentativa_id=6&numero=1
         */
        const url =
            "api/pergunta.php" +
            "?tentativa_id=" +
            encodeURIComponent(tentativaId) +
            "&numero=" +
            encodeURIComponent(numeroPerguntaAtual);


        const resposta = await fetch(url);

        const textoResposta = await resposta.text();

        let resultadoAPI;

        try {
            resultadoAPI = JSON.parse(textoResposta);
        } catch (erroJSON) {
            console.error("Resposta recebida da API:", textoResposta);

            throw new Error(
                "A API retornou um erro. Veja o Console do navegador para saber qual é."
            );
        }
        if (!resposta.ok || !resultadoAPI.sucesso) {

            throw new Error(
                resultadoAPI.mensagem ||
                "Não foi possível carregar a pergunta."
            );
        }


        /*
         * Guarda os dados recebidos.
         */
        perguntaAtual =
            resultadoAPI.dados;


        /*
         * Mostra a pergunta na tela.
         */
        mostrarPergunta(
            perguntaAtual
        );


    } catch (erro) {

        mensagem.textContent =
            erro.message;
    }
}


// ============================================================
// MOSTRAR PERGUNTA
// ============================================================

function mostrarPergunta(dados) {
    /*
     * Atualiza o número da pergunta.
     */
    numeroPerguntaElemento.textContent =
        "Pergunta " +
        dados.numero +
        " de " +
        dados.total;


    /*
     * Coloca o enunciado na tela.
     *
     * textContent é utilizado em vez de innerHTML
     * para evitar que conteúdo vindo do banco
     * seja interpretado como HTML.
     */
    enunciado.textContent =
        dados.pergunta.enunciado;


    /*
     * Atualiza vidas.
     */
    vidasElemento.textContent =
        dados.vidas;


    /*
     * Atualiza pontuação.
     */
    pontuacaoElemento.textContent =
        dados.pontuacao;


    /*
     * Limpa mensagens antigas.
     */
    mensagem.textContent = "";


    /*
     * Limpa as alternativas anteriores.
     */
    alternativas.innerHTML = "";


    /*
     * Cria um botão para cada alternativa.
     */
    dados.alternativas.forEach(
        function (alternativa) {
            const botao =
                document.createElement(
                    "button"
                );


            /*
             * O botão será do tipo button
             * para não enviar nenhum formulário.
             */
            botao.type = "button";


            /*
             * Coloca o texto da alternativa.
             */
            botao.textContent =
                alternativa.texto;


            /*
             * Guarda o ID da alternativa
             * no próprio botão.
             */
            botao.dataset.alternativaId =
                alternativa.id;


            /*
             * Quando o jogador clicar,
             * enviamos a resposta.
             */
            botao.addEventListener(
                "click",
                function () {
                    responderPergunta(
                        alternativa.id
                    );
                }
            );


            /*
             * Coloca o botão na tela.
             */
            alternativas.appendChild(
                botao
            );
        }
    );


    /*
     * Agora o jogador pode responder.
     */
    podeResponder = true;
}


// ============================================================
// RESPONDER PERGUNTA
// ============================================================

async function responderPergunta(
    alternativaId
) {
    /*
     * Impede cliques duplicados.
     */
    if (!podeResponder) {
        return;
    }


    /*
     * Bloqueia novos cliques enquanto
     * a resposta está sendo processada.
     */
    podeResponder = false;


    /*
     * Desativa todos os botões de alternativa.
     */
    const botoes =
        alternativas.querySelectorAll(
            "button"
        );


    botoes.forEach(
        function (botao) {
            botao.disabled = true;
        }
    );


    try {

        /*
         * Prepara os dados que serão enviados
         * para responder.php.
         */
        const dados =
            new URLSearchParams();


        dados.append(
            "tentativa_id",
            tentativaId
        );


        dados.append(
            "pergunta_id",
            perguntaAtual.pergunta.id
        );


        dados.append(
            "alternativa_id",
            alternativaId
        );


        /*
         * Envia a resposta.
         */
        const resposta = await fetch(
            "api/responder.php",
            {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: dados
            }
        );


        /*
         * Converte o retorno para JSON.
         */
        const resultadoAPI =
            await resposta.json();


        /*
         * Verifica se houve erro.
         */
        if (!resposta.ok || !resultadoAPI.sucesso) {

            throw new Error(
                resultadoAPI.mensagem ||
                "Não foi possível registrar a resposta."
            );
        }


        /*
         * Guarda o resultado enviado pelo PHP.
         */
        const dadosResposta =
            resultadoAPI.dados;


        /*
         * Atualiza a pontuação.
         */
        pontuacaoElemento.textContent =
            dadosResposta.pontuacao;


        /*
         * Atualiza as vidas.
         */
        vidasElemento.textContent =
            dadosResposta.vidas;


        /*
         * Verifica se o jogador acertou.
         */
        if (dadosResposta.correta) {

            /*
             * Mostra uma mensagem de acerto.
             */
            mensagem.textContent =
                "Resposta correta! +100 pontos.";


            /*
             * Se a partida terminou depois do acerto,
             * mostramos o resultado.
             */
            if (dadosResposta.finalizou) {

                setTimeout(
                    function () {
                        mostrarResultado(
                            dadosResposta
                        );
                    },
                    800
                );

                return;
            }


            /*
             * Passa para a próxima pergunta.
             */
            setTimeout(
                function () {
                    proximaPergunta();
                },
                800
            );


        } else {

            /*
             * Se errou, mostramos o cartão educativo.
             */
            mostrarExplicacao(
                dadosResposta
            );
        }


    } catch (erro) {

        /*
         * Mostra o erro.
         */
        mensagem.textContent =
            erro.message;


        /*
         * Libera os botões novamente.
         *
         * Isso permite tentar novamente caso
         * tenha ocorrido um problema técnico.
         */
        botoes.forEach(
            function (botao) {
                botao.disabled = false;
            }
        );


        podeResponder = true;
    }
}


// ============================================================
// MOSTRAR EXPLICAÇÃO
// ============================================================

function mostrarExplicacao(
    dados
) {
    /*
     * Mostra o que o jogador escolheu.
     */
    respostaEscolhidaElemento.textContent =
        dados.alternativa_escolhida.texto;


    /*
     * Mostra a resposta correta.
     */
    respostaCorretaElemento.textContent =
        dados.alternativa_correta.texto;


    /*
     * Mostra a explicação educativa.
     */
    textoExplicacaoElemento.textContent =
        dados.explicacao;


    /*
     * Exibe o cartão.
     */
    explicacao.hidden = false;


    /*
     * Altera o texto da mensagem
     * dependendo da situação.
     */
    if (dados.vidas <= 0) {

        mensagem.textContent =
            "Você ficou sem vidas.";

    } else {

        mensagem.textContent =
            "Resposta incorreta.";
    }


    /*
     * Se a partida terminou porque as vidas acabaram,
     * o botão não deve levar para uma nova pergunta.
     */
    if (dados.finalizou) {

        botaoContinuar.textContent =
            "Ver resultado";

    } else {

        botaoContinuar.textContent =
            "Continuar";
    }


    /*
     * Coloca o foco no botão.
     *
     * Isso ajuda quem utiliza teclado
     * ou tecnologia assistiva.
     */
    botaoContinuar.focus();
}


// ============================================================
// CONTINUAR DEPOIS DA EXPLICAÇÃO
// ============================================================

function continuarJogo() {
    /*
     * Esconde o cartão educativo.
     */
    explicacao.hidden = true;


    /*
     * Se a partida terminou, mostramos
     * o resultado em vez da próxima pergunta.
     */
    if (
        perguntaAtual &&
        numeroPerguntaAtual >= 5
    ) {

        /*
         * Nesse caso o resultado já foi
         * registrado pelo PHP.
         *
         * Buscamos novamente os dados
         * da tentativa.
         */
        carregarResultadoFinal();

        return;
    }


    /*
     * Verificamos se ainda existem vidas.
     */
    const vidas =
        parseInt(
            vidasElemento.textContent
        );


    if (vidas <= 0) {

        carregarResultadoFinal();

        return;
    }


    /*
     * Avança para a próxima pergunta.
     */
    proximaPergunta();
}


// ============================================================
// PRÓXIMA PERGUNTA
// ============================================================

function proximaPergunta() {
    numeroPerguntaAtual++;

    carregarPergunta();
}


// ============================================================
// BUSCAR RESULTADO FINAL
// ============================================================

async function carregarResultadoFinal() {
    /*
     * Fazemos uma nova busca da tentativa
     * diretamente pelo banco através de uma API.
     *
     * Como ainda não criamos essa API,
     * utilizaremos os dados disponíveis na tela
     * quando possível.
     */

    /*
     * Neste momento o resultado será calculado
     * com as informações que já temos.
     */
    mostrarResultado({
        pontuacao:
            parseInt(
                pontuacaoElemento.textContent
            ),

        vidas:
            parseInt(
                vidasElemento.textContent
            ),

        venceu:
            parseInt(
                vidasElemento.textContent
            ) > 0,

        finalizou: true
    });
}


// ============================================================
// MOSTRAR RESULTADO
// ============================================================

function mostrarResultado(
    dados
) {
    /*
     * Esconde as áreas que não são mais necessárias.
     */
    areaDesafio.hidden = true;

    explicacao.hidden = true;

    inicio.hidden = true;


    /*
     * Mostra a tela de resultado.
     */
    resultado.hidden = false;


    /*
     * Mostra a pontuação final.
     */
    pontuacaoFinal.textContent =
        dados.pontuacao;


    /*
     * Verifica se o jogador venceu.
     */
    if (dados.venceu) {

        tituloResultado.textContent =
            "Parabéns! Desafio concluído!";

        mensagemResultado.textContent =
            "Você completou o desafio e ganhou " +
            dados.pontuacao +
            " pontos.";

    } else {

        tituloResultado.textContent =
            "Fim da partida";

        mensagemResultado.textContent =
            "Você ficou sem vidas. " +
            "Continue praticando para melhorar!";
    }


    /*
     * Libera o botão para jogar novamente.
     */
    botaoJogarNovamente.disabled = false;


    /*
     * Coloca o foco no botão.
     *
     * Isso melhora a navegação por teclado.
     */
    botaoJogarNovamente.focus();
}