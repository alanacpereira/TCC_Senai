<?php

declare(strict_types=1);

require_once __DIR__ . "/config/app.php";
require_once __DIR__ . "/includes/services.php";
require_once __DIR__ . "/includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("index.php?configuracao=pendente");
}
$usuario = plataforma_exigir_login($pdo);
if ($usuario["perfil"] === "professor") {
    plataforma_redirecionar("professor/index.php");
}

plataforma_sincronizar_carteira($pdo, (int) $usuario["id"]);
plataforma_atualizar_conquistas($pdo, (int) $usuario["id"]);
$jogos = plataforma_resumo_jogos($pdo, (int) $usuario["id"]);
$stmt = $pdo->prepare(
    "SELECT saldo_pontos FROM carteiras WHERE usuario_id = :usuario_id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$saldo = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM usuario_conquistas WHERE usuario_id = :usuario_id"
);
$stmt->execute(["usuario_id" => (int) $usuario["id"]]);
$conquistas = (int) $stmt->fetchColumn();

plataforma_cabecalho("Início", $usuario);
?>
<div class="painel-estudante">
<header class="cabecalho-painel">
    <p class="sobretitulo">Sua jornada continua</p>
    <h1>Olá, <?= plataforma_escapar($usuario["nome"]) ?>!</h1>
    <p>Continue sua jornada de aprendizagem na Palavra a Bordo.</p>
</header>
<section class="resumo-jogador" aria-labelledby="resumo-pessoal">
    <h2 id="resumo-pessoal">Seu progresso</h2>
    <dl class="indicadores-jogador">
        <div><dt>Nível</dt><dd><?= (int) $usuario["nivel"] ?></dd></div>
        <div><dt>Experiência</dt><dd><?= number_format((int) $usuario["experiencia"], 0, ",", ".") ?> XP</dd></div>
        <div><dt>Pontos no tesouro</dt><dd><?= number_format($saldo, 0, ",", ".") ?></dd></div>
        <div><dt>Conquistas obtidas</dt><dd><?= $conquistas ?></dd></div>
    </dl>
</section>
<section class="secao-jogos" aria-labelledby="jogos">
    <h2 id="jogos">Jogar</h2>
    <?php if (!$jogos): ?>
        <p>Nenhum jogo ativo está cadastrado no momento.</p>
    <?php else: ?>
        <ul class="lista-jogos-estudante">
            <?php foreach ($jogos as $jogo): ?>
                <?php
                $jogoId = (int) $jogo["jogo_id"];
                $urlJogo = match ($jogoId) {
                    1 => "../jogo1/index.php?desafio=1",
                    2 => "../jogo2/index.php",
                    default => null
                };
                ?>
                <li class="cartao-jogo-estudante">
                    <strong><?= plataforma_escapar($jogo["jogo"]) ?></strong>
                    <?php if ($urlJogo !== null): ?>
                        <a class="botao-jogo-estudante" href="<?= plataforma_escapar($urlJogo) ?>">Acessar jogo</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<section class="progresso-jogos" aria-labelledby="atividade-recente">
    <h2 id="atividade-recente">Progresso por jogo</h2>
    <?php if (!$jogos): ?>
        <p>Nenhum jogo ativo está cadastrado.</p>
    <?php else: ?>
        <ul class="lista-progresso-jogos">
            <?php foreach ($jogos as $jogo): ?>
                <li>
                    <strong><?= plataforma_escapar($jogo["jogo"]) ?></strong>:
                    <?= (int) $jogo["tentativas"] ?> tentativas,
                    <?= (int) $jogo["concluidas"] ?> concluídas,
                    <?= (int) $jogo["acertos"] ?> acertos,
                    <?= (int) $jogo["pontos"] ?> pontos registrados.
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>
<?php plataforma_rodape(); ?>
