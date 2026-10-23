-- Colecionaveis digitais adicionais para o Tesouro da Palavra a Bordo.
-- INSERT IGNORE preserva recompensas existentes e pode ser executado novamente.
USE `palavra_a_bordo`;
SET NAMES utf8mb4;

INSERT IGNORE INTO `recompensas`
    (`codigo`, `tipo`, `nome`, `descricao`, `custo_pontos`, `condicoes`, `ativa`)
VALUES
    (
        'companheiro-peixinho',
        'item',
        'Peixinho de Bordo',
        'Um pequeno companheiro digital para sua colecao maritima. Colecionavel visual; nao representa um animal fisico.',
        350,
        NULL,
        1
    ),
    (
        'gema-do-mar',
        'item',
        'Gema do Mar',
        'Uma gema digital azul para guardar entre os tesouros da sua expedicao. Colecionavel visual, sem valor monetario.',
        500,
        NULL,
        1
    ),
    (
        'chapeu-de-pirata',
        'item',
        'Chapeu de Pirata',
        'Um chapeu digital para sua colecao de navegadores. Colecionavel visual; nao concede vantagens nos jogos nem representa um item fisico.',
        750,
        NULL,
        1
    );