const dadosJogo = document.getElementById("dados-jogo");
const areaDesafio = document.getElementById("area-desafio");
const telaInicio = document.getElementById("inicio");
const telaResultado = document.getElementById("resultado");
const botaoIniciar = document.getElementById("botao-iniciar");
const botaoJogarNovamente = document.getElementById("botao-jogar-novamente");
const botaoContinuar = document.getElementById("botao-continuar");
const alternativas = document.getElementById("alternativas");
const enunciado = document.getElementById("enunciado");
const mensagem = document.getElementById("mensagem");
const explicacao = document.getElementById("explicacao");
const vidas = document.getElementById("vidas");
const pontuacao = document.getElementById("pontuacao");
const numeroPergunta = document.getElementById("numero-pergunta");

let desafioId = Number(dadosJogo.dataset.desafio);
let tentativaId = null;
let perguntaAtual = null;
let numeroPerguntaAtual = 1;
let podeResponder = false;

function mostrarTela(tela) {
    telaInicio.hidden = tela !== telaInicio;
    areaDesafio.hidden = tela !== areaDesafio;
    telaResultado.hidden = tela !== telaResultado;
}

function mostrarErro(erro) {
    console.error(erro);
    mensagem.textContent = erro.message || "Ocorreu um erro inesperado.";
}

async function lerResposta(resposta) {
    const texto = await resposta.text();
    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch (erroJSON) {
        console.error("Resposta recebida da API:", texto);
        throw new Error("A API retornou uma resposta inválida.");
    }

    if (!resposta.ok || !resultado.sucesso) {
        throw new Error(resultado.mensagem || "Não foi possível concluir a operação.");
    }

    return resultado;
}

async function iniciarDesafio() {
    botaoIniciar.disabled = true;
    mensagem.textContent = "Iniciando desafio...";
    explicacao.hidden = true;
    botaoContinuar.hidden = true;

    try {
        const corpo = new URLSearchParams({
            desafio_id: String(desafioId)
        });
        const resposta = await fetch("api/iniciar.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: corpo
        });
        const resultado = await lerResposta(resposta);

        tentativaId = Number(resultado.tentativa_id);
        numeroPerguntaAtual = 1;
        vidas.textContent = resultado.vidas;
        pontuacao.textContent = resultado.pontuacao;
        mostrarTela(areaDesafio);
        await carregarPergunta();
    } catch (erro) {
        mostrarErro(erro);
        botaoIniciar.disabled = false;
    }
}

function mostrarPergunta(dados) {
    perguntaAtual = dados;
    numeroPergunta.textContent = `Pergunta ${dados.numero} de ${dados.total}`;
    enunciado.textContent = dados.pergunta.enunciado;
    vidas.textContent = dados.vidas;
    pontuacao.textContent = dados.pontuacao;
    alternativas.innerHTML = "";

    dados.alternativas.forEach((alternativa) => {
        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "alternativa";
        botao.textContent = alternativa.texto;
        botao.addEventListener("click", () => responderPergunta(alternativa.id));
        alternativas.appendChild(botao);
    });

    podeResponder = true;
    mensagem.textContent = "Escolha uma alternativa.";
}

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


        /*
         * Faz o pedido para o PHP.
         */
        const resultadoAPI = await lerResposta(resposta);
        mostrarPergunta(resultadoAPI.dados);

    } catch (erro) {

        /*
         * Mostra o erro.
         */
        mostrarErro(erro);
    }
}

async function responderPergunta(alternativaId) {
    if (!podeResponder || !perguntaAtual) {
        return;
    }

    podeResponder = false;
    mensagem.textContent = "Registrando resposta...";

    try {
        const corpo = new URLSearchParams({
            tentativa_id: String(tentativaId),
            pergunta_id: String(perguntaAtual.pergunta.id),
            alternativa_id: String(alternativaId)
        });
        const resposta = await fetch("api/responder.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: corpo
        });
        const resultado = await lerResposta(resposta);
        mostrarResultadoResposta(resultado.dados);
    } catch (erro) {
        podeResponder = true;
        mostrarErro(erro);
    }
}

function mostrarResultadoResposta(resultado) {
    vidas.textContent = resultado.vidas;
    pontuacao.textContent = resultado.pontuacao;
    const textoEscolhido = resultado.alternativa_escolhida?.texto || "Resposta não identificada.";
    const textoCorreto = resultado.alternativa_correta?.texto || "Resposta correta não cadastrada.";

    if (!resultado.correta) {
        document.getElementById("resposta-escolhida").textContent = textoEscolhido;
        document.getElementById("resposta-correta").textContent = textoCorreto;
        document.getElementById("texto-explicacao").textContent = resultado.explicacao;
        explicacao.hidden = false;
    } else {
        document.getElementById("resposta-escolhida").textContent = textoEscolhido;
        document.getElementById("resposta-correta").textContent = textoCorreto;
        document.getElementById("texto-explicacao").textContent = resultado.explicacao || "Muito bem!";
        explicacao.hidden = false;
        mensagem.textContent = "Resposta correta!";
    }

    if (resultado.finalizou) {
        mostrarResultadoFinal(resultado);
    } else if (resultado.correta) {
        botaoContinuar.hidden = false;
    }
}

function continuarJogo() {
    explicacao.hidden = true;
    botaoContinuar.hidden = true;
    numeroPerguntaAtual += 1;
    carregarPergunta();
}

function mostrarResultadoFinal(resultado) {
    document.getElementById("pontuacao-final").textContent = resultado.pontuacao;
    document.getElementById("mensagem-resultado").textContent = resultado.venceu
        ? "Parabéns! Você concluiu o desafio."
        : "Você ficou sem vidas antes de concluir o desafio.";
    mostrarTela(telaResultado);
}

botaoIniciar.addEventListener("click", iniciarDesafio);
botaoJogarNovamente.addEventListener("click", iniciarDesafio);
botaoContinuar.addEventListener("click", continuarJogo);

mostrarTela(telaInicio);