const dadosJogo = document.getElementById("dados-jogo");
const areaDesafio = document.getElementById("area-desafio");
const telaInicio = document.getElementById("inicio");
const telaResultado = document.getElementById("resultado");
const botaoIniciar = document.getElementById("botao-iniciar");
const botaoJogarNovamente = document.getElementById("botao-jogar-novamente");
const botaoVoltarMenu = document.getElementById("botao-voltar-menu");
const botaoVoltarInicio = document.getElementById("botao-voltar-inicio");
const botaoContinuar = document.getElementById("botao-continuar");
const alternativas = document.getElementById("alternativas");
const enunciado = document.getElementById("enunciado");
const mensagem = document.getElementById("mensagem");
const explicacao = document.getElementById("explicacao");
const vidas = document.getElementById("vidas");
const pontuacao = document.getElementById("pontuacao");
const numeroPergunta = document.getElementById("numero-pergunta");
const barraProgresso = document.getElementById("barra-progresso");
const trilhoProgresso = document.querySelector(".trilho-progresso");

let desafioId = Number(dadosJogo.dataset.desafio);
let tentativaId = null;
let perguntaAtual = null;
let numeroPerguntaAtual = 1;
let podeResponder = false;
let contextoAudio = null;

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
    const percentualProgresso = Math.min(100, Math.max(0, (dados.numero / dados.total) * 100));
    barraProgresso.style.width = `${percentualProgresso}%`;
    trilhoProgresso.setAttribute("aria-valuemax", String(dados.total));
    trilhoProgresso.setAttribute("aria-valuenow", String(dados.numero));
    enunciado.textContent = dados.pergunta.enunciado;
    vidas.textContent = dados.vidas;
    pontuacao.textContent = dados.pontuacao;
    alternativas.innerHTML = "";

    dados.alternativas.forEach((alternativa) => {
        const botao = document.createElement("button");
        botao.type = "button";
        botao.className = "alternativa";
        botao.dataset.alternativaId = String(alternativa.id);
        const rotulo = document.createElement("span");
        rotulo.className = "alternativa-texto";
        rotulo.textContent = alternativa.texto;
        botao.appendChild(rotulo);
        botao.addEventListener("click", () => responderPergunta(alternativa.id));
        alternativas.appendChild(botao);
    });

    podeResponder = true;
    botaoVoltarInicio.disabled = false;
    mensagem.textContent = "Escolha uma alternativa.";
    mensagem.classList.remove("feedback-correta", "feedback-errada");
}

async function carregarPergunta() {
    /*
     * Enquanto carregamos a pergunta,
     * o jogador não pode responder.
     */
    podeResponder = false;
    botaoVoltarInicio.disabled = true;


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
        const resposta = await fetch(url);
        const resultadoAPI = await lerResposta(resposta);
        mostrarPergunta(resultadoAPI.dados);

    } catch (erro) {
        botaoVoltarInicio.disabled = false;
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
    botaoVoltarInicio.disabled = true;
    prepararAudio();
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
        botaoVoltarInicio.disabled = false;
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
        mensagem.textContent = "Resposta errada.";
        mensagem.classList.remove("feedback-correta");
        mensagem.classList.add("feedback-errada");
    } else {
        document.getElementById("resposta-escolhida").textContent = textoEscolhido;
        document.getElementById("resposta-correta").textContent = textoCorreto;
        document.getElementById("texto-explicacao").textContent = resultado.explicacao || "Muito bem!";
        explicacao.hidden = false;
        mensagem.textContent = "Resposta correta!";
        mensagem.classList.remove("feedback-errada");
        mensagem.classList.add("feedback-correta");
    }

    marcarAlternativas(resultado);
    tocarFeedback(resultado.correta);

    if (resultado.finalizou) {
        botaoContinuar.hidden = true;
        mostrarResultadoFinal(resultado);
    } else {
        botaoVoltarInicio.disabled = false;
        botaoContinuar.hidden = false;
    }
}

function marcarAlternativas(resultado) {
    const botoes = alternativas.querySelectorAll(".alternativa");
    const alternativaEscolhida = resultado.alternativa_escolhida;
    const alternativaCorreta = resultado.alternativa_correta;

    botoes.forEach((botao) => {
        const id = botao.dataset.alternativaId;
        const escolhida = alternativaEscolhida
            ? (alternativaEscolhida.id !== undefined
                ? id === String(alternativaEscolhida.id)
                : botao.textContent === alternativaEscolhida.texto)
            : false;
        const correta = resultado.correta && escolhida
            ? true
            : alternativaCorreta
                ? (alternativaCorreta.id !== undefined
                    ? id === String(alternativaCorreta.id)
                    : botao.textContent === alternativaCorreta.texto)
                : false;

        botao.disabled = true;
        botao.setAttribute("aria-pressed", String(escolhida));
        botao.setAttribute("data-correta", String(correta));

        if (escolhida && !correta) {
            botao.classList.add("wrong");
        } else if (correta) {
            botao.classList.add("correct");
        }
    });
}

function prepararAudio() {
    const AudioContextAtual = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextAtual) {
        return;
    }

    if (!contextoAudio) {
        contextoAudio = new AudioContextAtual();
    }

    if (contextoAudio.state === "suspended") {
        contextoAudio.resume().catch((erro) => {
            console.error("Não foi possível iniciar o áudio de feedback:", erro);
        });
    }
}

function tocarFeedback(correta) {
    if (!contextoAudio || contextoAudio.state !== "running") {
        return;
    }

    const agora = contextoAudio.currentTime;
    const notas = correta
        ? [{ frequencia: 660, inicio: 0 }, { frequencia: 880, inicio: 0.14 }]
        : [{ frequencia: 310, inicio: 0 }, { frequencia: 220, inicio: 0.16 }];

    notas.forEach(({ frequencia, inicio }) => {
        const oscilador = contextoAudio.createOscillator();
        const volume = contextoAudio.createGain();
        const inicioNota = agora + inicio;
        const fimNota = inicioNota + 0.13;

        oscilador.type = "triangle";
        oscilador.frequency.setValueAtTime(frequencia, inicioNota);
        volume.gain.setValueAtTime(0.0001, inicioNota);
        volume.gain.exponentialRampToValueAtTime(0.12, inicioNota + 0.015);
        volume.gain.exponentialRampToValueAtTime(0.0001, fimNota);
        oscilador.connect(volume);
        volume.connect(contextoAudio.destination);
        oscilador.start(inicioNota);
        oscilador.stop(fimNota);
    });
}

function continuarJogo() {
    if (botaoContinuar.hidden || !perguntaAtual) {
        return;
    }

    explicacao.hidden = true;
    botaoContinuar.hidden = true;
    numeroPerguntaAtual += 1;
    carregarPergunta();
}

function mostrarResultadoFinal(resultado) {
    explicacao.hidden = true;
    botaoContinuar.hidden = true;
    document.getElementById("pontuacao-final").textContent = resultado.pontuacao;
    document.getElementById("mensagem-resultado").textContent = resultado.venceu
        ? "Parabéns! Você concluiu o desafio."
        : "Você ficou sem vidas antes de concluir o desafio.";
    mostrarTela(telaResultado);
}

function voltarAoMenu() {
    botaoIniciar.disabled = false;
    explicacao.hidden = true;
    botaoContinuar.hidden = true;
    mostrarTela(telaInicio);
}

function voltarAoInicio() {
    if (
        tentativaId &&
        !window.confirm("Se sair do jogo, a partida atual será abandonada. Deseja continuar?")
    ) {
        return;
    }

    podeResponder = false;
    botaoVoltarInicio.disabled = true;
    botaoIniciar.disabled = false;
    explicacao.hidden = true;
    botaoContinuar.hidden = true;
    mostrarTela(telaInicio);
}

botaoIniciar.addEventListener("click", iniciarDesafio);
botaoJogarNovamente.addEventListener("click", iniciarDesafio);
botaoContinuar.addEventListener("click", continuarJogo);
botaoVoltarMenu.addEventListener("click", voltarAoMenu);
botaoVoltarInicio.addEventListener("click", voltarAoInicio);

mostrarTela(telaInicio);