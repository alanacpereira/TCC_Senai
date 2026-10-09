<?php

require_once __DIR__ . "/../jogo1/config/Auth.php";
Auth::iniciarSessao();

if (
    !isset($_SESSION["jogo2_csrf"]) ||
    !is_string($_SESSION["jogo2_csrf"])
) {
    $_SESSION["jogo2_csrf"] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#073d48">
    <title>Expedição dos Sete Mares — Palavra a Bordo</title>
    <link rel="stylesheet" href="css/jogo2.css?v=2">
    <script src="js/jogo2.js?v=2" defer></script>
</head>
<body class="jogo2-page">
    <main id="jogo2" data-csrf="<?= htmlspecialchars($_SESSION["jogo2_csrf"], ENT_QUOTES, "UTF-8") ?>">
        <header class="cabecalho">
            <a class="marca" href="index.php" aria-label="Palavra a Bordo, mapa das missões">
                <span class="brasao" aria-hidden="true">⚓</span>
                <span class="marca-texto">
                    <span class="marca-nome">PALAVRA <strong>A BORDO</strong></span>
                    <span class="marca-jogo">EXPEDIÇÃO DOS SETE MARES</span>
                </span>
            </a>

            <div class="progresso-jogador" aria-label="Progresso do jogador">
                <div class="nivel-jogador">
                    <span class="nivel-medalha" aria-hidden="true">✦</span>
                    <span>NÍVEL <strong id="nivel-jogador">1</strong></span>
                </div>
                <div class="experiencia-jogador">
                    <div class="experiencia-rotulo">
                        <span>EXPEDIÇÃO</span>
                        <strong><span id="missoes-concluidas">0</span> / 7 MISSÕES</strong>
                    </div>
                    <div
                        id="barra-experiencia"
                        class="trilho-experiencia"
                        role="progressbar"
                        aria-label="Missões concluídas"
                        aria-valuemin="0"
                        aria-valuemax="7"
                        aria-valuenow="0"
                    ><span></span></div>
                    <span class="xp-acumulada"><span id="experiencia-jogador">0</span> XP acumulada</span>
                </div>
            </div>

            <div id="status-partida" class="status-partida" hidden aria-label="Status da missão">
                <div class="indicador"><span aria-hidden="true">❤️</span><span>Vidas</span><strong id="vidas">3</strong></div>
                <div class="indicador"><span aria-hidden="true">✦</span><span>Pontos</span><strong id="pontuacao">0</strong></div>
            </div>

            <button type="button" id="botao-voltar" class="botao-voltar">
                <span aria-hidden="true">←</span>
                <span id="texto-botao-voltar">Voltar ao mapa</span>
            </button>
        </header>

        <div class="area-expedicao">
            <section id="mapa-missoes" class="mapa-missoes" aria-label="Mapa das sete ilhas e missões">
                <div class="mapa-legenda">
                    <span class="selo-mapa" aria-hidden="true">✦</span>
                    <span>
                        <strong>MAPA DA EXPEDIÇÃO</strong>
                        <small>Escolha uma ilha para navegar</small>
                    </span>
                </div>
                <svg class="rota-mapa" viewBox="0 0 1000 800" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M115 160 C215 95 305 115 405 145 S590 165 740 205 C825 250 790 315 675 355 S455 360 315 435 C205 480 145 525 205 605 S370 710 515 650 C625 605 700 625 820 560"></path>
                </svg>
                <div id="lista-missoes" class="lista-missoes" role="group" aria-label="Selecione uma das sete missões"></div>
                <svg class="barquinho-mapa" viewBox="0 0 120 100" aria-hidden="true">
                    <path class="vela" d="M53 10 L53 70 L20 70 Z"></path>
                    <path class="vela vela-clara" d="M61 22 L61 70 L91 70 Z"></path>
                    <path class="mastro" d="M57 8 L57 76"></path>
                    <path class="casco" d="M15 77 L101 77 L85 94 L33 94 Z"></path>
                    <path class="onda" d="M13 99 Q26 92 39 99 T65 99 T91 99 T115 99"></path>
                </svg>
                <p class="mapa-nota">Cada ilha guarda um novo desafio de Língua Portuguesa.</p>
            </section>

            <aside class="painel-missao" aria-label="Painel da missão">
                <section id="tela-missoes" class="painel-conteudo painel-selecao" aria-labelledby="titulo-missoes">
                    <header class="painel-cabecalho">
                        <span class="icone-painel" aria-hidden="true">⚓</span>
                        <div>
                            <p class="sobretitulo">DIÁRIO DE BORDO</p>
                            <h1 id="titulo-missoes">Escolha uma ilha</h1>
                            <p id="descricao-missao">Selecione um marcador no mapa para conhecer sua missão.</p>
                        </div>
                    </header>
                    <div class="painel-mensagem">
                        <span class="estrela-decorativa" aria-hidden="true">✦</span>
                        <p id="mensagem-missoes" role="status" aria-live="polite">Carregando as missões...</p>
                    </div>
                    <div id="resumo-missao" class="resumo-missao" hidden>
                        <div class="resumo-linha"><span>Rota</span><strong id="missao-numero">Missão 1 de 7</strong></div>
                        <div class="resumo-linha"><span>Desafio</span><strong id="missao-perguntas">5 perguntas</strong></div>
                        <div class="resumo-linha"><span>Estado</span><strong id="missao-estado">Pronta para navegar</strong></div>
                    </div>
                    <div class="recompensa-missao">
                        <span class="bau-icone" aria-hidden="true">♜</span>
                        <div class="recompensa-info">
                            <strong>Recompensa de experiência</strong>
                            <span>Os pontos da missão viram EXP ao concluí-la.</span>
                        </div>
                        <span class="recompensa-simbolo" aria-hidden="true">✦</span>
                    </div>
                    <button type="button" id="botao-iniciar-missao" class="botao-principal" disabled>Escolha uma ilha no mapa</button>
                    <button
                        type="button"
                        class="botao-secundario botao-sair-inicial"
                        disabled
                        title="O destino será configurado quando o menu principal estiver disponível."
                        aria-label="Sair do jogo. O destino será configurado posteriormente."
                    >Sair do jogo</button>
                    <p class="nota-seguranca">3 vidas · 100 pontos por acerto · bônus de 200 pontos ao concluir</p>
                </section>

                <section id="tela-jogo" class="painel-conteudo painel-jogo" hidden aria-labelledby="enunciado">
                    <header class="painel-cabecalho painel-cabecalho-jogo">
                        <span class="icone-painel" aria-hidden="true">⚓</span>
                        <div>
                            <p class="sobretitulo" id="nome-missao-atual">MISSÃO 1 — MAR DAS PALAVRAS</p>
                            <h1>Desafio das palavras</h1>
                            <p>Leia com atenção e escolha a resposta correta.</p>
                        </div>
                    </header>
                    <div class="progresso-pergunta">
                        <span id="numero-pergunta">PERGUNTA 1 DE 5</span>
                        <div id="progresso-perguntas" class="marcadores-progresso" aria-hidden="true"></div>
                    </div>
                    <div class="quadro-pergunta">
                        <span class="selo-pergunta" aria-hidden="true">?</span>
                        <h2 id="enunciado">Carregando pergunta...</h2>
                    </div>
                    <p id="mensagem-jogo" class="mensagem-jogo" role="status" aria-live="polite">Escolha uma alternativa.</p>
                    <div id="alternativas" class="alternativas" role="group" aria-label="Alternativas da pergunta"></div>
                    <section id="feedback" class="feedback" hidden aria-live="polite">
                        <h2 id="titulo-feedback"></h2>
                        <p><strong>Você escolheu:</strong> <span id="resposta-escolhida"></span></p>
                        <p><strong>Resposta correta:</strong> <span id="resposta-correta"></span></p>
                        <div class="explicacao">
                            <strong>Por que?</strong>
                            <p id="texto-explicacao"></p>
                        </div>
                    </section>
                    <button type="button" id="botao-responder" class="botao-principal" disabled>Responder</button>
                    <button type="button" id="botao-continuar" class="botao-secundario" hidden>Continuar</button>
                    <div class="recompensa-missao recompensa-jogo">
                        <span class="bau-icone" aria-hidden="true">♜</span>
                        <div class="recompensa-info">
                            <strong>Progresso da missão</strong>
                            <span id="texto-progresso-missao">0 de 5 perguntas respondidas</span>
                            <span id="texto-recompensa-xp">EXP: pontuação conquistada ao concluir</span>
                            <div id="trilho-progresso-missao" class="trilho-progresso-missao" role="progressbar" aria-label="Progresso da missão" aria-valuemin="0" aria-valuemax="5" aria-valuenow="0"><span id="barra-progresso-missao"></span></div>
                        </div>
                        <strong id="percentual-progresso" class="percentual-progresso">0%</strong>
                    </div>
                </section>

                <section id="tela-resultado" class="painel-conteudo painel-resultado" hidden aria-labelledby="titulo-resultado" aria-live="polite">
                    <header class="painel-cabecalho">
                        <span class="icone-painel" aria-hidden="true">✦</span>
                        <div>
                            <p class="sobretitulo">DIÁRIO DE BORDO</p>
                            <h1 id="titulo-resultado">Missão concluída</h1>
                            <p id="mensagem-resultado"></p>
                        </div>
                    </header>
                    <div class="resumo-resultado">
                        <div><span>Perguntas</span><strong id="resultado-perguntas">0 / 5</strong></div>
                        <div><span>Acertos</span><strong id="resultado-acertos">0</strong></div>
                        <div><span>Erros</span><strong id="resultado-erros">0</strong></div>
                        <div><span>Pontuação</span><strong id="resultado-pontos">0</strong></div>
                    </div>
                    <div class="recompensa-missao">
                        <span class="bau-icone" aria-hidden="true">♜</span>
                        <div class="recompensa-info">
                            <strong>Experiência registrada</strong>
                            <span id="resultado-experiencia">A experiência acompanha a pontuação final.</span>
                        </div>
                    </div>
                    <button type="button" id="botao-tentar-novamente" class="botao-principal">Tentar novamente</button>
                    <button type="button" id="botao-menu" class="botao-secundario">Voltar ao mapa</button>
                </section>
            </aside>
        </div>

        <footer class="rodape">UMA AVENTURA DE LÍNGUA PORTUGUESA <span aria-hidden="true">✦</span> NAVEGUE E APRENDA</footer>
    </main>
</body>
</html>
