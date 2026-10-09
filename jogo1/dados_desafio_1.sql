SET NAMES utf8mb4;

START TRANSACTION;

-- User 1 is the guest fallback used by the game's APIs; "!" is not a valid password hash.
INSERT INTO usuarios (id, nome, email, senha, nivel, experiencia)
SELECT 1, 'Jogador convidado', 'jogador-convidado@localhost.invalid', '!', 1, 0
WHERE NOT EXISTS (
    SELECT 1
    FROM usuarios
    WHERE id = 1
);

INSERT INTO perguntas (desafio_id, enunciado, explicacao, ordem)
SELECT 1, novas.enunciado, novas.explicacao, novas.ordem
FROM (
    SELECT 2 AS ordem, 'Qual frase está escrita corretamente?' AS enunciado,
        'A palavra “passeio” é escrita com ss, e a expressão correta é “ao passeio”.' AS explicacao
    UNION ALL
    SELECT 3, 'Qual palavra está escrita corretamente?',
        'A forma correta é “necessário”, com ss e c.'
    UNION ALL
    SELECT 4, 'Qual frase está escrita corretamente?',
        'A palavra “ótima” recebe acento agudo no ó, e “apresentação” é escrita com ç.'
    UNION ALL
    SELECT 5, 'Qual palavra está escrita corretamente?',
        'A forma correta é “acontecimento”, escrita com c e sem ç ou ss.'
    UNION ALL
    SELECT 6, 'Qual palavra está escrita corretamente?',
        'A forma correta é “privilégio”, com i depois do v e g antes do i final.'
    UNION ALL
    SELECT 7, 'Qual frase está escrita corretamente?',
        '“Analisou” é escrito com s, e “atenção” termina com ção.'
    UNION ALL
    SELECT 8, 'Qual palavra está escrita corretamente?',
        'A forma correta é “vigilância”, com i depois do g e com cia no final.'
    UNION ALL
    SELECT 9, 'Qual frase está escrita corretamente?',
        '“Com certeza” é escrito separado e “pesquisa” é escrito com s.'
    UNION ALL
    SELECT 10, 'Complete corretamente: A criança demonstrou muita ____.',
        '“Curiosidade” é escrita com s e termina com dade.'
) AS novas
WHERE NOT EXISTS (
    SELECT 1
    FROM perguntas existentes
    WHERE existentes.desafio_id = 1
      AND existentes.ordem = novas.ordem
);

INSERT INTO alternativas (pergunta_id, texto, correta)
SELECT p.id, novas.texto, novas.correta
FROM perguntas p
INNER JOIN (
    SELECT 2 AS ordem, 'Eu quero ir ao passeio amanhã.' AS texto, 1 AS correta
    UNION ALL SELECT 2, 'Eu quero ir au passeio amanhã.', 0
    UNION ALL SELECT 2, 'Eu quero ir ao paseio amanhã.', 0
    UNION ALL SELECT 2, 'Eu quero ir au paseio amanhã.', 0
    UNION ALL SELECT 3, 'Necessário', 1
    UNION ALL SELECT 3, 'Necesário', 0
    UNION ALL SELECT 3, 'Neceçário', 0
    UNION ALL SELECT 3, 'Nescessário', 0
    UNION ALL SELECT 4, 'O aluno fez uma ótima apresentação.', 1
    UNION ALL SELECT 4, 'O aluno fez uma otima apresentação.', 0
    UNION ALL SELECT 4, 'O aluno fez uma ótima apresenteção.', 0
    UNION ALL SELECT 4, 'O aluno fez uma otima apresenteção.', 0
    UNION ALL SELECT 5, 'Acontecimento', 1
    UNION ALL SELECT 5, 'Acontessimento', 0
    UNION ALL SELECT 5, 'Aconteçimento', 0
    UNION ALL SELECT 5, 'Acontesimento', 0
    UNION ALL SELECT 6, 'Privilégio', 1
    UNION ALL SELECT 6, 'Previlegio', 0
    UNION ALL SELECT 6, 'Priviléjio', 0
    UNION ALL SELECT 6, 'Privelégio', 0
    UNION ALL SELECT 7, 'A professora analisou os resultados com atenção.', 1
    UNION ALL SELECT 7, 'A professora analizou os resultados com atenção.', 0
    UNION ALL SELECT 7, 'A professora analisou os resultados com atensão.', 0
    UNION ALL SELECT 7, 'A professora analizou os resultados com atenssão.', 0
    UNION ALL SELECT 8, 'Vigilância', 1
    UNION ALL SELECT 8, 'Viguilância', 0
    UNION ALL SELECT 8, 'Vigilânsia', 0
    UNION ALL SELECT 8, 'Vijilância', 0
    UNION ALL SELECT 9, 'A pesquisa foi feita com certeza.', 1
    UNION ALL SELECT 9, 'A pesquisa foi feita comsertesa.', 0
    UNION ALL SELECT 9, 'A pesquiza foi feita com certeza.', 0
    UNION ALL SELECT 9, 'A pesquiza foi feita con certeza.', 0
    UNION ALL SELECT 10, 'curiosidade', 1
    UNION ALL SELECT 10, 'curiozidade', 0
    UNION ALL SELECT 10, 'curiosidadee', 0
    UNION ALL SELECT 10, 'curiossidade', 0
) AS novas ON novas.ordem = p.ordem
WHERE p.desafio_id = 1
  AND NOT EXISTS (
      SELECT 1
      FROM alternativas existente
      WHERE existente.pergunta_id = p.id
        AND existente.texto = novas.texto
  );

COMMIT;
