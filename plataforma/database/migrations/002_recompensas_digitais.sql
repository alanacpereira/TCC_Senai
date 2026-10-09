-- Catálogo inicial de recompensas exclusivamente digitais e cosméticas.
-- INSERT IGNORE preserva itens já cadastrados ou personalizados pelo responsável.
USE `palavra_a_bordo`;
SET NAMES utf8mb4;

INSERT IGNORE INTO `recompensas`
    (`codigo`, `tipo`, `nome`, `descricao`, `custo_pontos`, `condicoes`, `ativa`)
VALUES
    (
        'titulo-grumete-das-palavras',
        'titulo',
        'Título: Grumete das Palavras',
        'Título digital decorativo para sua coleção no Tesouro da Palavra a Bordo. Não concede vantagens nos jogos nem representa um item físico.',
        100,
        NULL,
        1
    ),
    (
        'insignia-olho-de-aguia',
        'insignia',
        'Insígnia: Olho de Águia',
        'Insígnia digital colecionável de atenção à escrita. É um item visual do Tesouro, sem vantagem competitiva ou entrega física.',
        250,
        NULL,
        1
    ),
    (
        'bau-mapa-do-tesouro',
        'bau',
        'Baú: Mapa do Tesouro',
        'Baú digital colecionável para registrar sua conquista no Tesouro. Não altera os jogos e não corresponde a um produto físico.',
        400,
        NULL,
        1
    ),
    (
        'titulo-capitao-das-palavras',
        'titulo',
        'Título: Capitão das Palavras',
        'Título digital especial para sua coleção de aprendizagem. Não concede vantagens nos jogos nem representa um item físico.',
        600,
        NULL,
        1
    );
