<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("../index.php?configuracao=pendente");
}
$professor = plataforma_exigir_login($pdo, "professor");

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT ta.aluno_id)
     FROM turma_alunos ta
     INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
     INNER JOIN turma_professores tp ON tp.turma_id = t.id
     INNER JOIN usuarios u ON u.id = ta.aluno_id AND u.perfil = 'aluno'
     WHERE tp.professor_id = :professor_id"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$totalAlunos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT tentativa.id)
     FROM tentativas tentativa
     INNER JOIN (
        SELECT DISTINCT ta.aluno_id
        FROM turma_alunos ta
        INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
        INNER JOIN turma_professores tp ON tp.turma_id = t.id
        WHERE tp.professor_id = :professor_id
     ) vinculados ON vinculados.aluno_id = tentativa.usuario_id
     WHERE tentativa.concluido = 1"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$atividadesConcluidas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(resposta.id) AS total_respostas,
            COALESCE(SUM(resposta.correta = 1), 0) AS total_acertos
     FROM (
        SELECT DISTINCT ta.aluno_id
        FROM turma_alunos ta
        INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
        INNER JOIN turma_professores tp ON tp.turma_id = t.id
        WHERE tp.professor_id = :professor_id
     ) vinculados
     INNER JOIN tentativas tentativa ON tentativa.usuario_id = vinculados.aluno_id
     INNER JOIN respostas resposta ON resposta.tentativa_id = tentativa.id"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$resumoRespostas = $stmt->fetch();
$totalRespostas = (int) $resumoRespostas["total_respostas"];
$percentualGeral = $totalRespostas > 0
    ? (int) round((int) $resumoRespostas["total_acertos"] * 100 / $totalRespostas)
    : null;

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT tentativa.usuario_id
        FROM (
            SELECT DISTINCT ta.aluno_id
            FROM turma_alunos ta
            INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
            INNER JOIN turma_professores tp ON tp.turma_id = t.id
            WHERE tp.professor_id = :professor_id
        ) vinculados
        INNER JOIN tentativas tentativa ON tentativa.usuario_id = vinculados.aluno_id
        INNER JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
        GROUP BY tentativa.usuario_id
        HAVING SUM(resposta.correta = 1) / COUNT(resposta.id) < 0.60
     ) alunos_com_baixo_aproveitamento"
);
$stmt->execute(["professor_id" => (int) $professor["id"]]);
$alunosParaRevisao = (int) $stmt->fetchColumn();

$busca = $_GET["busca"] ?? "";
if (!is_string($busca)) {
    $busca = "";
}
$busca = trim($busca);
if (strlen($busca) > 80) {
    $busca = substr($busca, 0, 80);
}
$pagina = filter_var($_GET["pagina"] ?? 1, FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1]
]);
$pagina = $pagina === false ? 1 : $pagina;
$porPagina = 20;
$parametrosBusca = ["professor_id" => (int) $professor["id"]];
$filtroNome = "";
if ($busca !== "") {
    $filtroNome = " AND u.nome LIKE :busca";
    $parametrosBusca["busca"] = "%" . $busca . "%";
}

$sqlVinculos = "
    SELECT DISTINCT ta.aluno_id
    FROM turma_alunos ta
    INNER JOIN turmas t ON t.id = ta.turma_id AND t.ativa = 1
    INNER JOIN turma_professores tp ON tp.turma_id = t.id
    WHERE tp.professor_id = :professor_id
";
$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM usuarios u
     INNER JOIN ({$sqlVinculos}) vinculados ON vinculados.aluno_id = u.id
     WHERE u.perfil = 'aluno'{$filtroNome}"
);
$stmt->execute($parametrosBusca);
$totalAlunosFiltrados = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalAlunosFiltrados / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$sqlAlunos = "
    SELECT u.id, u.nome, u.nivel, u.experiencia,
           COALESCE(atividade.tentativas, 0) AS tentativas,
           COALESCE(atividade.concluidas, 0) AS concluidas,
           COALESCE(atividade.respostas, 0) AS respostas,
           COALESCE(atividade.acertos, 0) AS acertos,
           atividade.ultima_atividade
    FROM usuarios u
    INNER JOIN ({$sqlVinculos}) vinculados ON vinculados.aluno_id = u.id
    LEFT JOIN (
        SELECT tentativa.usuario_id,
               COUNT(DISTINCT tentativa.id) AS tentativas,
               COUNT(DISTINCT CASE WHEN tentativa.concluido = 1 THEN tentativa.id END) AS concluidas,
               COUNT(resposta.id) AS respostas,
               COALESCE(SUM(resposta.correta = 1), 0) AS acertos,
               MAX(tentativa.data_inicio) AS ultima_atividade
        FROM tentativas tentativa
        LEFT JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
        GROUP BY tentativa.usuario_id
    ) atividade ON atividade.usuario_id = u.id
    WHERE u.perfil = 'aluno'{$filtroNome}
    ORDER BY u.nome, u.id
    LIMIT :limite OFFSET :deslocamento
";
$stmt = $pdo->prepare($sqlAlunos);
foreach ($parametrosBusca as $nome => $valor) {
    $stmt->bindValue(":" . $nome, $valor);
}
$stmt->bindValue(":limite", $porPagina, PDO::PARAM_INT);
$stmt->bindValue(":deslocamento", $offset, PDO::PARAM_INT);
$stmt->execute();
$alunos = $stmt->fetchAll();

plataforma_cabecalho("Painel do professor", $professor);
?>
<div class="painel-professor">
    <header class="cabecalho-painel">
        <p class="sobretitulo">Acompanhamento pedagógico</p>
        <h1>Olá, <?= plataforma_escapar($professor["nome"]) ?>!</h1>
        <p>Acompanhe suas turmas e os resultados dos estudantes.</p>
    </header>
    <h2 class="titulo-painel">Painel do professor</h2>

    <section class="indicadores-docente" aria-label="Resumo das turmas">
        <article><span>Estudantes vinculados</span><strong><?= $totalAlunos ?></strong></article>
        <article><span>Atividades concluídas</span><strong><?= $atividadesConcluidas ?></strong></article>
        <article>
            <span>Aproveitamento geral</span>
            <strong><?= $percentualGeral === null ? "—" : "{$percentualGeral}%" ?></strong>
            <?php if ($percentualGeral === null): ?><small>Ainda não há resultados suficientes para gerar este indicador.</small><?php endif; ?>
        </article>
        <article>
            <span>Para revisar</span>
            <strong><?= $alunosParaRevisao ?></strong>
            <small>Estudantes com aproveitamento abaixo de 60% nas respostas registradas.</small>
        </article>
    </section>

    <section aria-labelledby="titulo-alunos">
        <div class="cabecalho-secao">
            <h2 id="titulo-alunos">Estudantes das suas turmas</h2>
            <a href="turmas.php">Administrar turmas</a>
        </div>
        <form method="get" action="index.php" class="form-busca">
            <label for="busca-aluno">Buscar por nome</label>
            <input id="busca-aluno" name="busca" type="search" maxlength="80" value="<?= plataforma_escapar($busca) ?>">
            <button type="submit">Buscar</button>
            <?php if ($busca !== ""): ?><a href="index.php">Limpar busca</a><?php endif; ?>
        </form>
        <?php if (!$alunos): ?>
            <p><?= $totalAlunos === 0 ? "Ainda não há estudantes vinculados às suas turmas." : "Nenhum estudante corresponde à busca." ?></p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Estudante</th>
                            <th scope="col">Atividades</th>
                            <th scope="col">Aproveitamento</th>
                            <th scope="col">Última atividade</th>
                            <th scope="col">Situação</th>
                            <th scope="col">Relatório</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($alunos as $aluno): ?>
                        <?php
                        $respostas = (int) $aluno["respostas"];
                        $aproveitamento = $respostas > 0
                            ? (int) round((int) $aluno["acertos"] * 100 / $respostas)
                            : null;
                        ?>
                        <tr>
                            <th scope="row"><?= plataforma_escapar($aluno["nome"]) ?></th>
                            <td><?= (int) $aluno["tentativas"] ?></td>
                            <td><?= $aproveitamento === null ? "Sem resultados" : "{$aproveitamento}%" ?></td>
                            <td><?= plataforma_escapar($aluno["ultima_atividade"] ?? "Sem atividade") ?></td>
                            <td>
                                <?php if ($aproveitamento === null): ?>
                                    Sem dados suficientes
                                <?php elseif ($aproveitamento < 60): ?>
                                    Revisão sugerida
                                <?php else: ?>
                                    Em acompanhamento
                                <?php endif; ?>
                            </td>
                            <td><a href="aluno.php?id=<?= (int) $aluno["id"] ?>">Ver detalhes</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginação de estudantes" class="paginacao">
                    <?php if ($pagina > 1): ?>
                        <a href="?<?= http_build_query(["busca" => $busca, "pagina" => $pagina - 1]) ?>">Anterior</a>
                    <?php endif; ?>
                    <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?<?= http_build_query(["busca" => $busca, "pagina" => $pagina + 1]) ?>">Próxima</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <p class="nota-pedagogica">Os indicadores são apoio à avaliação pedagógica e não substituem a análise do professor. Aproveitamento abaixo de 60% indica um conteúdo que pode merecer revisão; não é um diagnóstico do estudante.</p>
</div>
<?php plataforma_rodape(); ?>
