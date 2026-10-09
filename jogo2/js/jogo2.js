const jogo = document.getElementById("jogo2");
const tokenCsrf = jogo.dataset.csrf;
const telaMissoes = document.getElementById("tela-missoes");
const telaJogo = document.getElementById("tela-jogo");
const telaResultado = document.getElementById("tela-resultado");
const listaMissoes = document.getElementById("lista-missoes");
const mensagemMissoes = document.getElementById("mensagem-missoes");
const mensagemJogo = document.getElementById("mensagem-jogo");
const alternativas = document.getElementById("alternativas");
const feedback = document.getElementById("feedback");
const botaoIniciarMissao = document.getElementById("botao-iniciar-missao");
const botaoResponder = document.getElementById("botao-responder");
const botaoContinuar = document.getElementById("botao-continuar");
const botaoVoltar = document.getElementById("botao-voltar");
const textoBotaoVoltar = document.getElementById("texto-botao-voltar");
const botaoMenu = document.getElementById("botao-menu");
const botaoTentarNovamente = document.getElementById("botao-tentar-novamente");
const statusPartida = document.getElementById("status-partida");
const vidas = document.getElementById("vidas");
const pontuacao = document.getElementById("pontuacao");
const enunciado = document.getElementById("enunciado");
const numeroPergunta = document.getElementById("numero-pergunta");
const barraExperiencia = document.querySelector("#barra-experiencia span");
const trilhoExperiencia = document.getElementById("barra-experiencia");
const barraProgressoMissao = document.getElementById("barra-progresso-missao");
const trilhoProgressoMissao = document.getElementById("trilho-progresso-missao");
const marcadoresProgresso = document.getElementById("progresso-perguntas");

let missoes = [];
let jogador = null;
let missaoAtual = null;
let tentativaId = null;
let perguntaAtual = null;
let numeroAtual = 1;
let alternativaSelecionadaId = null;
let resultadoResposta = null;
let podeResponder = false;
let ocupado = false;

function mostrarPainel(painel) {
    telaMissoes.hidden = painel !== telaMissoes;
    telaJogo.hidden = painel !== telaJogo;
    telaResultado.hidden = painel !== telaResultado;
    statusPartida.hidden = painel !== telaJogo;
    const textoVoltar = painel === telaMissoes
        ? "Voltar ao mapa"
        : "Sair do jogo";
    textoBotaoVoltar.textContent = textoVoltar;
    botaoVoltar.setAttribute("aria-label", textoVoltar);
}

async function requisitar(caminho, opcoes = {}) {
    const cabecalhos = new Headers(opcoes.headers || {});
    cabecalhos.set("Accept", "application/json");

    if (opcoes.method === "POST") {
        cabecalhos.set("Content-Type", "application/x-www-form-urlencoded");
        cabecalhos.set("X-CSRF-Token", tokenCsrf);
    }

    const resposta = await fetch(new URL(caminho, document.baseURI), {
        ...opcoes,
        headers: cabecalhos
    });
    const texto = await resposta.text();
    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch (erro) {
        console.error("Resposta inválida da API do Jogo 2:", texto);
        throw new Error("O servidor retornou uma resposta inválida.");
    }

    if (!resposta.ok || !resultado.sucesso) {
        throw new Error(resultado.mensagem || "Não foi possível concluir a operação.");
    }

    return resultado.dados;
}

function criarElemento(tag, classe, texto) {
    const elemento = document.createElement(tag);
    if (classe) {
        elemento.className = classe;
    }
    if (texto !== undefined) {
        elemento.textContent = texto;
    }
    return elemento;
}

function atualizarProgressoJogador() {
    if (!jogador) {
        return;
    }

    document.getElementById("nivel-jogador").textContent = jogador.nivel;
    document.getElementById("experiencia-jogador").textContent =
        Number(jogador.experiencia).toLocaleString("pt-BR");
    document.getElementById("missoes-concluidas").textContent =
        jogador.missoes_concluidas;
    const maximo = Math.max(1, Number(jogador.total_missoes));
    const percentual = Math.min(
        100,
        (Number(jogador.missoes_concluidas) / maximo) * 100
    );
    barraExperiencia.style.width = `${percentual}%`;
    trilhoExperiencia.setAttribute("aria-valuemax", String(maximo));
    trilhoExperiencia.setAttribute(
        "aria-valuenow",
        String(jogador.missoes_concluidas)
    );
}

async function carregarMissoes(selecionadaId = null) {
    mensagemMissoes.textContent = "Carregando as missões...";

    try {
        const dados = await requisitar("api/missoes.php");
        missoes = dados.missoes;
        jogador = dados.jogador;
        atualizarProgressoJogador();
        desenharMissoes();

        const selecao = missoes.find((missao) => missao.id === selecionadaId) ||
            missoes.find((missao) => missao.tentativa_aberta) ||
            missoes.find((missao) => !missao.concluida && missao.disponivel) ||
            missoes[0];

        if (selecao) {
            selecionarMissao(selecao, false);
            mensagemMissoes.textContent = "Missão pronta para navegação.";
        } else {
            mensagemMissoes.textContent = "Nenhuma missão está disponível.";
        }
    } catch (erro) {
        mensagemMissoes.textContent = erro.message;
        botaoIniciarMissao.disabled = true;
        console.error(erro);
    }
}

function desenharPalmeira() {
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 80 100");
    svg.setAttribute("class", "palmeira");
    svg.setAttribute("aria-hidden", "true");
    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute(
        "d",
        "M40 93 Q43 53 42 23 Q43 19 47 22 Q48 26 46 49 Q57 17 77 20 Q66 39 47 55 Q69 38 79 46 Q63 62 45 64 Q65 59 72 69 Q58 79 44 73 L43 94 Z M42 26 Q24 7 5 13 Q17 32 40 32 Q18 27 9 42 Q25 52 42 37 Q22 42 18 57 Q33 63 43 42 Q35 19 42 26 Z"
    );
    svg.appendChild(path);
    return svg;
}

function desenharMissoes() {
    listaMissoes.replaceChildren();

    const proximaMissao = missoes.find(
        (missao) => missao.disponivel && !missao.concluida
    );

    missoes.forEach((missao, indice) => {
        const posicao = indice + 1;
        const estado = missao.concluida
            ? "concluida"
            : missao.disponivel
                ? "disponivel"
                : "bloqueada";
        const botao = criarElemento("button", "marcador-missao");
        botao.type = "button";
        botao.dataset.posicao = String(posicao);
        botao.dataset.missaoId = String(missao.id);
        botao.classList.add(estado);
        if (missaoAtual?.id === missao.id) {
            botao.classList.add("selecionada");
        }
        if (!missao.concluida && missao === proximaMissao) {
            botao.classList.add("atual");
        }
        botao.disabled = !missao.disponivel;
        botao.setAttribute(
            "aria-label",
            `Missão ${missao.ordem}: ${missao.nome}. ${
                missao.concluida
                    ? "Concluída."
                    : missao.disponivel
                        ? "Disponível."
                        : "Indisponível."
            }`
        );

        const arteIlha = criarElemento("span", "arte-ilha");
        arteIlha.setAttribute("aria-hidden", "true");
        arteIlha.appendChild(criarElemento("span", "solo-ilha"));
        arteIlha.appendChild(desenharPalmeira());

        const numero = criarElemento("span", "numero-ilha", String(missao.ordem));
        numero.setAttribute("aria-hidden", "true");
        const nome = criarElemento("span", "nome-ilha", missao.nome);
        botao.append(arteIlha, numero, nome);

        if (missao.concluida) {
            const selo = criarElemento("span", "selo-concluida", "✓");
            selo.setAttribute("aria-hidden", "true");
            botao.appendChild(selo);
        }

        botao.addEventListener("click", () => selecionarMissao(missao));
        listaMissoes.appendChild(botao);
    });
}

function selecionarMissao(missao, redesenhar = true) {
    missaoAtual = missao;
    document.getElementById("resumo-missao").hidden = false;
    document.getElementById("titulo-missoes").textContent =
        `MISSÃO ${missao.ordem} — ${missao.nome.toLocaleUpperCase("pt-BR")}`;
    document.getElementById("descricao-missao").textContent =
        missao.descricao || "Explore este mar da Língua Portuguesa.";
    document.getElementById("missao-numero").textContent =
        `Missão ${missao.ordem} de ${missoes.length}`;
    document.getElementById("missao-perguntas").textContent =
        `${missao.total_perguntas} perguntas · nível ${missao.nivel}`;

    const estado = missao.concluida
        ? `Concluída · melhor pontuação: ${missao.pontuacao_maxima}`
        : missao.tentativa_aberta
            ? `${missao.tentativa_aberta.respondidas} de ${missao.total_perguntas} respondidas`
            : "Pronta para navegar";
    document.getElementById("missao-estado").textContent = estado;

    botaoIniciarMissao.disabled = !missao.disponivel || ocupado;
    botaoIniciarMissao.textContent = !missao.disponivel
        ? "Missão indisponível"
        : missao.tentativa_aberta
            ? "Continuar missão"
            : missao.concluida
                ? "Jogar novamente"
                : "Iniciar missão";

    mensagemMissoes.textContent = missao.disponivel
        ? `Missão ${missao.ordem}: leia, responda e descubra algo novo.`
        : "Esta missão ainda não possui os dados necessários para ser iniciada.";

    if (redesenhar) {
        desenharMissoes();
    }
}

async function iniciarMissao(tentativaAnteriorId = null) {
    if (ocupado || !missaoAtual || !missaoAtual.disponivel) {
        return;
    }

    ocupado = true;
    tentativaId = null;
    perguntaAtual = null;
    resultadoResposta = null;
    numeroAtual = 1;
    alternativaSelecionadaId = null;
    botaoIniciarMissao.disabled = true;
    mensagemJogo.textContent = "Preparando sua expedição...";
    feedback.hidden = true;
    botaoResponder.hidden = false;
    botaoResponder.disabled = true;
    botaoContinuar.hidden = true;
    alternativas.replaceChildren();
    mostrarPainel(telaJogo);

    const corpo = new URLSearchParams({
        desafio_id: String(missaoAtual.id)
    });
    if (tentativaAnteriorId !== null) {
        corpo.set("tentativa_id", String(tentativaAnteriorId));
    }

    try {
        const dados = await requisitar("api/iniciar.php", {
            method: "POST",
            body: corpo
        });
        tentativaId = dados.tentativa_id;
        numeroAtual = dados.numero_pergunta;
        vidas.textContent = dados.vidas;
        pontuacao.textContent = dados.pontuacao;
        document.getElementById("nome-missao-atual").textContent =
            `MISSÃO ${missaoAtual.ordem} — ${missaoAtual.nome.toLocaleUpperCase("pt-BR")}`;
        atualizarProgressoMissao(numeroAtual - 1);
        await carregarPergunta(numeroAtual);
    } catch (erro) {
        mensagemJogo.textContent = erro.message;
        console.error(erro);
    } finally {
        ocupado = false;
        botaoIniciarMissao.disabled = !missaoAtual?.disponivel;
    }
}

async function carregarPergunta(numero) {
    ocupado = true;
    podeResponder = false;
    alternativaSelecionadaId = null;
    mensagemJogo.textContent = "Carregando pergunta...";
    alternativas.replaceChildren();
    botaoResponder.hidden = false;
    botaoResponder.disabled = true;
    botaoContinuar.hidden = true;

    try {
        const dados = await requisitar(
            `api/pergunta.php?tentativa_id=${encodeURIComponent(tentativaId)}&numero=${encodeURIComponent(numero)}`
        );
        perguntaAtual = dados;
        numeroAtual = dados.numero;
        resultadoResposta = null;
        desenharPergunta(dados);
    } catch (erro) {
        mensagemJogo.textContent = erro.message;
        botaoResponder.hidden = true;
        botaoContinuar.textContent = "Tentar carregar pergunta";
        botaoContinuar.hidden = false;
        console.error(erro);
    } finally {
        ocupado = false;
    }
}

function desenharMarcadoresProgresso(numero, respondidas = numero - 1) {
    marcadoresProgresso.replaceChildren();

    for (let indice = 1; indice <= 5; indice += 1) {
        const marcador = criarElemento("span", "marcador-progresso");
        if (indice <= respondidas) {
            marcador.classList.add("concluido");
        } else if (indice === numero) {
            marcador.classList.add("atual");
        }
        marcadoresProgresso.appendChild(marcador);
    }
}

function atualizarProgressoMissao(respondidas) {
    const total = 5;
    const percentual = Math.min(100, Math.max(0, (respondidas / total) * 100));
    document.getElementById("texto-progresso-missao").textContent =
        `${respondidas} de ${total} perguntas respondidas`;
    document.getElementById("percentual-progresso").textContent =
        `${Math.round(percentual)}%`;
    barraProgressoMissao.style.width = `${percentual}%`;
    trilhoProgressoMissao.setAttribute("aria-valuemax", String(total));
    trilhoProgressoMissao.setAttribute("aria-valuenow", String(respondidas));
}

function desenharPergunta(dados) {
    enunciado.textContent = dados.pergunta.enunciado;
    numeroPergunta.textContent = `PERGUNTA ${dados.numero} DE ${dados.total}`;
    vidas.textContent = dados.vidas;
    pontuacao.textContent = dados.pontuacao;
    mensagemJogo.textContent = "Escolha uma alternativa e confirme sua resposta.";
    feedback.hidden = true;
    feedback.classList.remove("correta", "errada");
    botaoContinuar.hidden = true;
    botaoResponder.hidden = false;
    botaoResponder.disabled = true;
    atualizarProgressoMissao(dados.numero - 1);
    desenharMarcadoresProgresso(dados.numero);
    alternativas.replaceChildren();

    dados.alternativas.forEach((alternativa, indice) => {
        const botao = criarElemento("button", "alternativa");
        botao.type = "button";
        botao.dataset.alternativaId = String(alternativa.id);
        botao.setAttribute("aria-pressed", "false");
        botao.appendChild(
            criarElemento("span", "letra-alternativa", String.fromCharCode(65 + indice))
        );
        botao.appendChild(
            criarElemento("span", "texto-alternativa", alternativa.texto)
        );
        botao.addEventListener("click", () => selecionarAlternativa(alternativa.id));
        alternativas.appendChild(botao);
    });

    podeResponder = true;
}

function selecionarAlternativa(alternativaId) {
    if (!podeResponder || ocupado) {
        return;
    }

    alternativaSelecionadaId = Number(alternativaId);
    alternativas.querySelectorAll(".alternativa").forEach((botao) => {
        const selecionada =
            Number(botao.dataset.alternativaId) === alternativaSelecionadaId;
        botao.classList.toggle("selecionada", selecionada);
        botao.setAttribute("aria-pressed", String(selecionada));
    });
    botaoResponder.disabled = false;
    mensagemJogo.textContent = "Resposta selecionada. Confirme para registrar.";
}

async function responder() {
    if (
        !podeResponder ||
        ocupado ||
        !perguntaAtual ||
        alternativaSelecionadaId === null
    ) {
        return;
    }

    ocupado = true;
    podeResponder = false;
    botaoResponder.disabled = true;
    alternativas.querySelectorAll("button").forEach((botao) => {
        botao.disabled = true;
    });
    mensagemJogo.textContent = "Registrando sua resposta...";

    const corpo = new URLSearchParams({
        tentativa_id: String(tentativaId),
        pergunta_id: String(perguntaAtual.pergunta.id),
        alternativa_id: String(alternativaSelecionadaId)
    });

    try {
        resultadoResposta = await requisitar("api/responder.php", {
            method: "POST",
            body: corpo
        });
        mostrarFeedback(resultadoResposta);
    } catch (erro) {
        podeResponder = true;
        botaoResponder.disabled = false;
        alternativas.querySelectorAll("button").forEach((botao) => {
            botao.disabled = false;
        });
        mensagemJogo.textContent = erro.message;
        console.error(erro);
    } finally {
        ocupado = false;
    }
}

function mostrarFeedback(resultado) {
    vidas.textContent = resultado.vidas;
    pontuacao.textContent = resultado.pontuacao;

    const acertou = resultado.correta;
    document.getElementById("titulo-feedback").textContent = acertou
        ? "Resposta correta! Muito bem!"
        : "Resposta incorreta. Vamos aprender!";
    document.getElementById("resposta-escolhida").textContent =
        resultado.alternativa_escolhida.texto;
    document.getElementById("resposta-correta").textContent =
        resultado.alternativa_correta.texto;
    document.getElementById("texto-explicacao").textContent =
        resultado.explicacao || "Continue praticando para aprender mais.";
    feedback.classList.toggle("correta", acertou);
    feedback.classList.toggle("errada", !acertou);
    feedback.hidden = false;
    mensagemJogo.textContent = acertou
        ? `Resposta certa: +${resultado.pontos_ganhos} pontos.`
        : `Resposta errada: restam ${resultado.vidas} vidas.`;

    alternativas.querySelectorAll(".alternativa").forEach((botao) => {
        const id = Number(botao.dataset.alternativaId);
        if (id === resultado.alternativa_correta.id) {
            botao.classList.add("correta");
        } else if (id === resultado.alternativa_escolhida.id) {
            botao.classList.add("errada");
        }
    });

    desenharMarcadoresProgresso(
        resultado.pergunta_atual,
        resultado.pergunta_atual
    );
    atualizarProgressoMissao(resultado.pergunta_atual);
    botaoResponder.hidden = true;
    botaoContinuar.textContent = resultado.finalizou
        ? "Ver resultado"
        : "Continuar";
    botaoContinuar.hidden = false;
}

async function continuar() {
    if (ocupado) {
        return;
    }

    if (resultadoResposta?.finalizou) {
        await mostrarResultado();
        return;
    }

    const proximaPergunta = resultadoResposta
        ? numeroAtual + 1
        : numeroAtual;
    await carregarPergunta(proximaPergunta);
}

async function mostrarResultado() {
    ocupado = true;
    botaoContinuar.disabled = true;

    try {
        const resultado = await requisitar(
            `api/resultado.php?tentativa_id=${encodeURIComponent(tentativaId)}`
        );
        document.getElementById("titulo-resultado").textContent =
            resultado.venceu ? "Missão concluída!" : "Fim da expedição";
        document.getElementById("mensagem-resultado").textContent =
            resultado.mensagem;
        document.getElementById("resultado-perguntas").textContent =
            `${resultado.respondidas} / ${resultado.total_perguntas}`;
        document.getElementById("resultado-acertos").textContent =
            resultado.acertos;
        document.getElementById("resultado-erros").textContent =
            resultado.erros;
        document.getElementById("resultado-pontos").textContent =
            resultado.pontuacao;
        document.getElementById("resultado-experiencia").textContent =
            resultado.venceu
                ? `+${resultado.pontuacao} XP adicionada à sua experiência.`
                : "Nenhuma EXP foi adicionada; conclua a missão para recebê-la.";
        mostrarPainel(telaResultado);
        await carregarMissoes(missaoAtual.id);
    } catch (erro) {
        mensagemJogo.textContent = erro.message;
        console.error(erro);
    } finally {
        ocupado = false;
        botaoContinuar.disabled = false;
    }
}

async function voltarAoMapa() {
    if (ocupado) {
        return;
    }

    if (
        tentativaId &&
        !resultadoResposta?.finalizou &&
        !window.confirm(
            "A tentativa atual ficará salva para você continuar depois. Deseja voltar ao mapa?"
        )
    ) {
        return;
    }

    podeResponder = false;
    mostrarPainel(telaMissoes);
    await carregarMissoes(missaoAtual?.id ?? null);
}

botaoIniciarMissao.addEventListener("click", () => {
    iniciarMissao(missaoAtual?.tentativa_aberta?.id ?? null);
});
botaoResponder.addEventListener("click", responder);
botaoContinuar.addEventListener("click", continuar);
botaoVoltar.addEventListener("click", voltarAoMapa);
botaoMenu.addEventListener("click", voltarAoMapa);
botaoTentarNovamente.addEventListener("click", () => iniciarMissao());

mostrarPainel(telaMissoes);
carregarMissoes();
