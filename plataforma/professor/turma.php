<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/layout.php";

$pdo = plataforma_conexao();
if (!plataforma_migracao_aplicada($pdo)) {
    plataforma_redirecionar("../index.php?configuracao=pendente");
}
$professor = plataforma_exigir_login($pdo, "professor");
$turmaId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1]
]);
if ($turmaId === false || $turmaId === null) {
    http_response_code(400);
    exit("Identificador de turma inválido.");
}

$stmt = $pdo->prepare(
    "SELECT t.id, t.nome, t.codigo
     FROM turmas t
     INNER JOIN turma_professores tp ON tp.turma_id = t.id
     WHERE t.id = :turma_id
       AND tp.professor_id = :professor_id
       AND t.ativa = 1
     LIMIT 1"
);
$stmt->execute([
    "turma_id" => $turmaId,
    "professor_id" => (int) $professor["id"]
]);
$turma = $stmt->fetch();
if (!$turma) {
    http_response_code(404);
    exit("Turma não encontrada ou sem autorização.");
}

$stmt = $pdo->prepare(
    "SELECT
        u.id, u.nome, u.nivel, u.experiencia,
        COALESCE(t.tentativas, 0) AS tentativas,
        COALESCE(t.concluidas, 0) AS concluidas,
        COALESCE(t.pontos, 0) AS pontos,
        COALESCE(r.acertos, 0) AS acertos,
        t.atividade_recente
     FROM turma_alunos ta
     INNER JOIN usuarios u ON u.id = ta.aluno_id AND u.perfil = 'aluno'
     LEFT JOIN (
        SELECT usuario_id, COUNT(*) AS tentativas,
            SUM(concluido = 1) AS concluidas,
            SUM(pontuacao) AS pontos,
            MAX(data_inicio) AS atividade_recente
        FROM tentativas
        GROUP BY usuario_id
     ) t ON t.usuario_id = u.id
     LEFT JOIN (
        SELECT tentativa.usuario_id, COUNT(*) AS acertos
        FROM respostas resposta
        INNER JOIN tentativas tentativa ON tentativa.id = resposta.tentativa_id
        WHERE resposta.correta = 1
        GROUP BY tentativa.usuario_id
     ) r ON r.usuario_id = u.id
     WHERE ta.turma_id = :turma_id
     GROUP BY u.id, u.nome, u.nivel, u.experiencia,
              t.tentativas, t.concluidas, t.pontos, r.acertos, t.atividade_recente
     ORDER BY u.nome"
);
$stmt->execute(["turma_id" => (int) $turma["id"]]);
$alunos = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT
        j.nome AS jogo,
        c.nome AS categoria,
        COUNT(*) AS tentativas,
        COALESCE(SUM(t.concluido = 1), 0) AS concluidas,
        COALESCE(SUM(t.pontuacao), 0) AS pontos,
        COALESCE(SUM(t.erros), 0) AS erros
     FROM turma_alunos ta
     INNER JOIN (
        SELECT tentativa.id, tentativa.usuario_id, tentativa.desafio_id,
            tentativa.concluido, tentativa.pontuacao,
            COUNT(CASE WHEN resposta.correta = 0 THEN resposta.id END) AS erros
        FROM tentativas tentativa
        LEFT JOIN respostas resposta ON resposta.tentativa_id = tentativa.id
        GROUP BY tentativa.id, tentativa.usuario_id, tentativa.desafio_id,
                 tentativa.concluido, tentativa.pontuacao
     ) t ON t.usuario_id = ta.aluno_id
     INNER JOIN desafios d ON d.id = t.desafio_id
     INNER JOIN categorias c ON c.id = d.categoria_id
     INNER JOIN jogos j ON j.id = c.jogo_id
     WHERE ta.turma_id = :turma_id
     GROUP BY j.id, j.nome, c.id, c.nome
     ORDER BY j.id, c.nome"
);
$stmt->execute(["turma_id" => (int) $turma["id"]]);
$categorias = $stmt->fetchAll();

plataforma_cabecalho("Relatório da turma", $professor);
?>
<h1><?= plataforma_escapar($turma["nome"]) ?></h1>
<p>Código de entrada: <?= plataforma_escapar($turma["codigo"]) ?></p>
<?php if (!$alunos): ?>
    <p>A turma ainda não tem estudantes vinculados.</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th scope="col">Estudante</th><th scope="col">Nível</th><th scope="col">XP</th><th scope="col">Tentativas</th><th scope="col">Concluídas</th><th scope="col">Acertos</th><th scope="col">Pontos</th><th scope="col">Atividade recente</th></tr>
        </thead>
        <tbody>
        <?php foreach ($alunos as $aluno): ?>
            <tr>
                <th scope="row"><?= plataforma_escapar($aluno["nome"]) ?></th>
                <td><?= (int) $aluno["nivel"] ?></td>
                <td><?= (int) $aluno["experiencia"] ?></td>
                <td><?= (int) $aluno["tentativas"] ?></td>
                <td><?= (int) $aluno["concluidas"] ?></td>
                <td><?= (int) $aluno["acertos"] ?></td>
                <td><?= (int) $aluno["pontos"] ?></td>
                <td><?= plataforma_escapar($aluno["atividade_recente"] ?? "Sem atividade") ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<section aria-labelledby="desempenho-categorias">
    <h2 id="desempenho-categorias">Desempenho por jogo e categoria</h2>
    <?php if (!$categorias): ?>
        <p>Ainda não há resultados para analisar.</p>
    <?php else: ?>
        <table>
            <thead><tr><th scope="col">Jogo</th><th scope="col">Categoria</th><th scope="col">Tentativas</th><th scope="col">Concluídas</th><th scope="col">Pontos</th><th scope="col">Respostas erradas</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <th scope="row"><?= plataforma_escapar($categoria["jogo"]) ?></th>
                    <td><?= plataforma_escapar($categoria["categoria"]) ?></td>
                    <td><?= (int) $categoria["tentativas"] ?></td>
                    <td><?= (int) $categoria["concluidas"] ?></td>
                    <td><?= (int) $categoria["pontos"] ?></td>
                    <td><?= (int) $categoria["erros"] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php plataforma_rodape(); ?>
