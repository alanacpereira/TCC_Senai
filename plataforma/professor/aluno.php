<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("../index.php?configuracao=pendente");
}
$professor = plataforma_exigir_login($pdo, "professor");
$alunoId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1]
]);
if ($alunoId === false || $alunoId === null) {
    http_response_code(400);
    exit("Identificador de estudante inválido.");
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.nome, u.nivel, u.experiencia
     FROM usuarios u
     WHERE u.id = :aluno_id
       AND u.perfil = 'aluno'
       AND EXISTS (
           SELECT 1
           FROM turma_alunos ta
           INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
           INNER JOIN turma_professores tp ON tp.turma_id = t.id
           WHERE ta.aluno_id = u.id
             AND tp.professor_id = :professor_id
       )
     LIMIT 1"
);
$stmt->execute([
    "aluno_id" => $alunoId,
    "professor_id" => (int) $professor["id"]
]);
$aluno = $stmt->fetch();
if (!$aluno) {
    http_response_code(404);
    exit("Estudante não encontrado nas suas turmas.");
}

$stmt = $pdo->prepare(
    "SELECT COUNT(resposta.id) AS total_respostas,
            COALESCE(SUM(resposta.correta = 1), 0) AS total_acertos,
            COUNT(DISTINCT tentativa.id) AS tentativas,
            COUNT(DISTINCT CASE WHEN tentativa.concluido = 1 THEN tentativa.id END) AS concluidas,
            MAX(tentativa.data_inicio) AS ultima_atividade
     FROM tentativas tentativa
     LEFT JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
     WHERE tentativa.usuario_id = :aluno_id"
);
$stmt->execute(["aluno_id" => (int) $aluno["id"]]);
$resumo = $stmt->fetch();
$totalRespostas = (int) $resumo["total_respostas"];
$percentualGeral = $totalRespostas > 0
    ? (int) round((int) $resumo["total_acertos"] * 100 / $totalRespostas)
    : null;

$stmt = $pdo->prepare(
    "SELECT j.nome AS jogo, c.nome AS categoria,
            COUNT(resposta.id) AS respostas,
            COALESCE(SUM(resposta.correta = 1), 0) AS acertos
     FROM tentativas tentativa
     INNER JOIN desafios d ON d.id = tentativa.desafio_id
     INNER JOIN categorias c ON c.id = d.categoria_id
     INNER JOIN jogos j ON j.id = c.jogo_id
     LEFT JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
     WHERE tentativa.usuario_id = :aluno_id
     GROUP BY j.id, j.nome, c.id, c.nome
     ORDER BY j.nome, c.nome"
);
$stmt->execute(["aluno_id" => (int) $aluno["id"]]);
$desempenhoCategorias = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(tentativa.data_inicio, '%Y-%m') AS periodo,
            COUNT(resposta.id) AS respostas,
            COALESCE(SUM(resposta.correta = 1), 0) AS acertos
     FROM tentativas tentativa
     INNER JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
     WHERE tentativa.usuario_id = :aluno_id
     GROUP BY DATE_FORMAT(tentativa.data_inicio, '%Y-%m')
     ORDER BY periodo DESC
     LIMIT 12"
);
$stmt->execute(["aluno_id" => (int) $aluno["id"]]);
$evolucaoMensal = array_reverse($stmt->fetchAll());

$stmt = $pdo->prepare(
    "SELECT j.nome AS jogo, c.nome AS categoria, d.nome AS desafio,
            tentativa.concluido, tentativa.pontuacao, tentativa.vidas_restantes,
            tentativa.data_inicio, tentativa.data_fim,
            COUNT(resposta.id) AS respostas,
            COALESCE(SUM(resposta.correta = 1), 0) AS acertos,
            COALESCE(SUM(resposta.correta = 0), 0) AS erros,
            (SELECT COUNT(*) FROM perguntas p WHERE p.desafio_id = d.id) AS total_perguntas
     FROM tentativas tentativa
     INNER JOIN desafios d ON d.id = tentativa.desafio_id
     INNER JOIN categorias c ON c.id = d.categoria_id
     INNER JOIN jogos j ON j.id = c.jogo_id
     LEFT JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
     WHERE tentativa.usuario_id = :aluno_id
     GROUP BY tentativa.id, j.nome, c.nome, d.nome,
              tentativa.concluido, tentativa.pontuacao,
              tentativa.vidas_restantes, tentativa.data_inicio,
              tentativa.data_fim, d.id
     ORDER BY tentativa.data_inicio DESC, tentativa.id DESC
     LIMIT 100"
);
$stmt->execute(["aluno_id" => (int) $aluno["id"]]);
$atividades = $stmt->fetchAll();

plataforma_cabecalho("Relatório individual", $professor);
?>
<div class="relatorio-aluno">
    <p class="sobretitulo"><a href="index.php">Painel do professor</a> / Relatório individual</p>
    <h1><?= plataforma_escapar($aluno["nome"]) ?></h1>
    <p>Este relatório reúne atividades realizadas nos jogos e considera somente os registros disponíveis.</p>

    <section class="indicadores-docente" aria-label="Resumo do estudante">
        <article><span>Nível</span><strong><?= (int) $aluno["nivel"] ?></strong></article>
        <article><span>Experiência</span><strong><?= number_format((int) $aluno["experiencia"], 0, ",", ".") ?> XP</strong></article>
        <article><span>Tentativas registradas</span><strong><?= (int) $resumo["tentativas"] ?></strong></article>
        <article>
            <span>Aproveitamento nas respostas</span>
            <strong><?= $percentualGeral === null ? "—" : "{$percentualGeral}%" ?></strong>
            <?php if ($percentualGeral === null): ?><small>Ainda não há respostas suficientes para gerar este indicador.</small><?php endif; ?>
        </article>
    </section>

    <section aria-labelledby="titulo-categorias">
        <h2 id="titulo-categorias">Aproveitamento por conteúdo</h2>
        <?php if (!$desempenhoCategorias): ?>
            <p>Ainda não há resultados suficientes para analisar conteúdos.</p>
        <?php else: ?>
            <div class="lista-desempenho">
                <?php foreach ($desempenhoCategorias as $categoria): ?>
                    <?php
                    $respostasCategoria = (int) $categoria["respostas"];
                    $aproveitamentoCategoria = $respostasCategoria > 0
                        ? (int) round((int) $categoria["acertos"] * 100 / $respostasCategoria)
                        : null;
                    ?>
                    <article>
                        <h3><?= plataforma_escapar($categoria["categoria"]) ?></h3>
                        <p><?= plataforma_escapar($categoria["jogo"]) ?> · <?= (int) $categoria["acertos"] ?> acertos de <?= $respostasCategoria ?> respostas</p>
                        <?php if ($aproveitamentoCategoria === null): ?>
                            <p>Sem resultados suficientes para este conteúdo.</p>
                        <?php else: ?>
                            <progress class="grafico-progresso" max="100" value="<?= $aproveitamentoCategoria ?>" aria-label="Aproveitamento em <?= plataforma_escapar($categoria["categoria"]) ?>: <?= $aproveitamentoCategoria ?> por cento"></progress>
                            <p><?= $aproveitamentoCategoria ?>% de aproveitamento</p>
                            <?php if ($aproveitamentoCategoria < 60): ?>
                                <p class="recomendacao-pedagogica"><strong>Possível ponto para revisão:</strong> considere retomar este conteúdo e acompanhar as próximas atividades. Este indicador é um apoio pedagógico, não um diagnóstico.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (count($evolucaoMensal) >= 2): ?>
        <section aria-labelledby="titulo-evolucao">
            <h2 id="titulo-evolucao">Evolução mensal do aproveitamento</h2>
            <p>Percentual de acertos nas respostas registradas em cada mês.</p>
            <div class="lista-desempenho">
                <?php foreach ($evolucaoMensal as $mes): ?>
                    <?php
                    $respostasMes = (int) $mes["respostas"];
                    $aproveitamentoMes = $respostasMes > 0
                        ? (int) round((int) $mes["acertos"] * 100 / $respostasMes)
                        : 0;
                    $dataMes = DateTimeImmutable::createFromFormat("!Y-m", $mes["periodo"]);
                    ?>
                    <article>
                        <h3><?= plataforma_escapar($dataMes ? $dataMes->format("m/Y") : $mes["periodo"]) ?></h3>
                        <progress class="grafico-progresso" max="100" value="<?= $aproveitamentoMes ?>" aria-label="Aproveitamento em <?= plataforma_escapar($dataMes ? $dataMes->format("m/Y") : $mes["periodo"]) ?>: <?= $aproveitamentoMes ?> por cento"></progress>
                        <p><?= $aproveitamentoMes ?>% · <?= $respostasMes ?> respostas</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section aria-labelledby="titulo-historico-aluno">
        <h2 id="titulo-historico-aluno">Histórico de atividades</h2>
        <?php if (!$atividades): ?>
            <p>Este estudante ainda não tem atividades registradas nos jogos.</p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Atividade</th>
                            <th scope="col">Conteúdo</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Respostas</th>
                            <th scope="col">Acertos / erros</th>
                            <th scope="col">Pontos</th>
                            <th scope="col">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($atividades as $atividade): ?>
                        <tr>
                            <th scope="row"><?= plataforma_escapar($atividade["desafio"]) ?></th>
                            <td><?= plataforma_escapar($atividade["jogo"] . " · " . $atividade["categoria"]) ?></td>
                            <td><?= (int) $atividade["concluido"] === 1 ? "Concluída" : "Em andamento" ?></td>
                            <td><?= (int) $atividade["respostas"] ?> de <?= (int) $atividade["total_perguntas"] ?></td>
                            <td><?= (int) $atividade["acertos"] ?> / <?= (int) $atividade["erros"] ?></td>
                            <td><?= (int) $atividade["pontuacao"] ?></td>
                            <td><?= plataforma_escapar($atividade["data_inicio"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($atividades) === 100): ?><p>Exibindo as 100 atividades mais recentes.</p><?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php plataforma_rodape(); ?>
