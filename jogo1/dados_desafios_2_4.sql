SET NAMES utf8mb4;

START TRANSACTION;

UPDATE perguntas
SET explicacao = 'Pássaro é escrita com SS e recebe acento agudo no primeiro A.'
WHERE desafio_id = 2
  AND ordem = 1;

UPDATE alternativas a
INNER JOIN perguntas p ON p.id = a.pergunta_id
SET a.correta = (BINARY a.texto = BINARY 'Pássaro')
WHERE p.desafio_id = 2
  AND p.ordem = 1
  AND a.texto IN ('Passaro', 'Pássaro');

INSERT INTO perguntas (desafio_id, enunciado, explicacao, ordem)
SELECT novas.desafio_id, novas.enunciado, novas.explicacao, novas.ordem
FROM (
    SELECT 2 AS desafio_id, 2 AS ordem, 'Qual palavra está escrita corretamente?' AS enunciado,
        'Passeio é escrita com SS.' AS explicacao
    UNION ALL SELECT 2, 3, 'Complete: O aniversário teve uma grande ___.',
        'Surpresa é escrita com S no início e S entre as vogais.'
    UNION ALL SELECT 2, 4, 'A ___ de pedestres fica perto da escola.',
        'Passagem é escrita com SS.'
    UNION ALL SELECT 2, 5, 'Qual palavra está escrita corretamente?',
        'Assunto é escrita com SS.'
    UNION ALL SELECT 2, 6, 'A turma foi nadar na ___.',
        'Piscina é escrita com SC.'
    UNION ALL SELECT 2, 7, 'O rapaz saiu com muita ___ para não se atrasar.',
        'Pressa é escrita com SS.'
    UNION ALL SELECT 2, 8, 'Qual palavra está escrita corretamente?',
        'Professor é escrita com SS.'
    UNION ALL SELECT 2, 9, 'Ela fez uma ___ para a biblioteca.',
        'Pesquisa é escrita com S.'
    UNION ALL SELECT 2, 10, 'Cada ___ apresentou seu trabalho.',
        'Pessoa é escrita com SS.'
    UNION ALL SELECT 3, 2, 'A bebida foi servida em uma ___.',
        'Xícara é escrita com X e recebe acento agudo no primeiro I.'
    UNION ALL SELECT 3, 3, 'Use a ___ para abrir a porta.',
        'Chave é escrita com CH.'
    UNION ALL SELECT 3, 4, 'O apicultor encontrou um ___ perto da árvore.',
        'Enxame é escrita com X.'
    UNION ALL SELECT 3, 5, 'A cozinheira vai ___ a sopa.',
        'Mexer é escrita com X.'
    UNION ALL SELECT 3, 6, 'Guarde os livros dentro da ___.',
        'Mochila é escrita com CH.'
    UNION ALL SELECT 3, 7, 'O ___ dormiu perto da casinha.',
        'Cachorro é escrito com CH e RR.'
    UNION ALL SELECT 3, 8, 'As crianças jogaram ___ na aula.',
        'Xadrez é escrito com X.'
    UNION ALL SELECT 3, 9, 'O menino tirou um ___ depois do almoço.',
        'Cochilo é escrito com CH.'
    UNION ALL SELECT 3, 10, 'A criança tocou o ___ na aula de música.',
        'Xilofone é escrito com X.'
    UNION ALL SELECT 4, 2, 'Qual palavra está escrita corretamente?',
        'Girassol começa com G e é escrita com SS.'
    UNION ALL SELECT 4, 3, 'O ___ é um legume de cor roxa.',
        'Jiló é escrito com J e recebe acento agudo no O.'
    UNION ALL SELECT 4, 4, 'A sopa foi servida em uma ___.',
        'Tigela é escrita com G.'
    UNION ALL SELECT 4, 5, 'A família fez uma ___ durante as férias.',
        'Viagem, quando significa deslocamento, é escrita com G.'
    UNION ALL SELECT 4, 6, 'Qual palavra completa a frase: Ela tem um bom ___ para desenhar.',
        'Jeito é escrito com J.'
    UNION ALL SELECT 4, 7, 'O carro ficou guardado na ___.',
        'Garagem é escrita com G.'
    UNION ALL SELECT 4, 8, 'A ___ cobriu o campo durante a madrugada.',
        'Geada é escrita com G.'
    UNION ALL SELECT 4, 9, 'A rainha foi tratada com respeito por sua ___.',
        'Majestade é escrita com J.'
    UNION ALL SELECT 4, 10, 'O gato tentou ___ pela janela aberta.',
        'Fugir é escrito com G antes de I.'
) AS novas
WHERE NOT EXISTS (
    SELECT 1
    FROM perguntas existentes
    WHERE existentes.desafio_id = novas.desafio_id
      AND existentes.ordem = novas.ordem
);

INSERT INTO alternativas (pergunta_id, texto, correta)
SELECT p.id, novas.texto, novas.correta
FROM perguntas p
INNER JOIN (
    SELECT 2 AS desafio_id, 2 AS ordem, 'passeio' AS texto, 1 AS correta
    UNION ALL SELECT 2, 2, 'paseio', 0
    UNION ALL SELECT 2, 2, 'passeyo', 0
    UNION ALL SELECT 2, 2, 'passei-o', 0
    UNION ALL SELECT 2, 3, 'surpresa', 1
    UNION ALL SELECT 2, 3, 'surpressa', 0
    UNION ALL SELECT 2, 3, 'surprêsa', 0
    UNION ALL SELECT 2, 3, 'supriessa', 0
    UNION ALL SELECT 2, 4, 'passagem', 1
    UNION ALL SELECT 2, 4, 'pasagem', 0
    UNION ALL SELECT 2, 4, 'paxagem', 0
    UNION ALL SELECT 2, 4, 'passajem', 0
    UNION ALL SELECT 2, 5, 'assunto', 1
    UNION ALL SELECT 2, 5, 'asunto', 0
    UNION ALL SELECT 2, 5, 'açunto', 0
    UNION ALL SELECT 2, 5, 'assuntho', 0
    UNION ALL SELECT 2, 6, 'piscina', 1
    UNION ALL SELECT 2, 6, 'picina', 0
    UNION ALL SELECT 2, 6, 'pisina', 0
    UNION ALL SELECT 2, 6, 'pissina', 0
    UNION ALL SELECT 2, 7, 'pressa', 1
    UNION ALL SELECT 2, 7, 'presa', 0
    UNION ALL SELECT 2, 7, 'preça', 0
    UNION ALL SELECT 2, 7, 'presça', 0
    UNION ALL SELECT 2, 8, 'professor', 1
    UNION ALL SELECT 2, 8, 'profesor', 0
    UNION ALL SELECT 2, 8, 'proffessor', 0
    UNION ALL SELECT 2, 8, 'profeçor', 0
    UNION ALL SELECT 2, 9, 'pesquisa', 1
    UNION ALL SELECT 2, 9, 'pesquiza', 0
    UNION ALL SELECT 2, 9, 'pesqquisa', 0
    UNION ALL SELECT 2, 9, 'pesquicia', 0
    UNION ALL SELECT 2, 10, 'pessoa', 1
    UNION ALL SELECT 2, 10, 'pesoa', 0
    UNION ALL SELECT 2, 10, 'pessôa', 0
    UNION ALL SELECT 2, 10, 'peçoa', 0
    UNION ALL SELECT 3, 2, 'xícara', 1
    UNION ALL SELECT 3, 2, 'chícara', 0
    UNION ALL SELECT 3, 2, 'xicára', 0
    UNION ALL SELECT 3, 2, 'xíçara', 0
    UNION ALL SELECT 3, 3, 'chave', 1
    UNION ALL SELECT 3, 3, 'xave', 0
    UNION ALL SELECT 3, 3, 'chavi', 0
    UNION ALL SELECT 3, 3, 'xhavi', 0
    UNION ALL SELECT 3, 4, 'enxame', 1
    UNION ALL SELECT 3, 4, 'enchame', 0
    UNION ALL SELECT 3, 4, 'enxami', 0
    UNION ALL SELECT 3, 4, 'enxane', 0
    UNION ALL SELECT 3, 5, 'mexer', 1
    UNION ALL SELECT 3, 5, 'mecher', 0
    UNION ALL SELECT 3, 5, 'mexêr', 0
    UNION ALL SELECT 3, 5, 'meçer', 0
    UNION ALL SELECT 3, 6, 'mochila', 1
    UNION ALL SELECT 3, 6, 'mochilla', 0
    UNION ALL SELECT 3, 6, 'moxila', 0
    UNION ALL SELECT 3, 6, 'muchila', 0
    UNION ALL SELECT 3, 7, 'cachorro', 1
    UNION ALL SELECT 3, 7, 'caxorro', 0
    UNION ALL SELECT 3, 7, 'cachoro', 0
    UNION ALL SELECT 3, 7, 'cachouro', 0
    UNION ALL SELECT 3, 8, 'xadrez', 1
    UNION ALL SELECT 3, 8, 'chadrez', 0
    UNION ALL SELECT 3, 8, 'xadres', 0
    UNION ALL SELECT 3, 8, 'xadrezs', 0
    UNION ALL SELECT 3, 9, 'cochilo', 1
    UNION ALL SELECT 3, 9, 'coxilo', 0
    UNION ALL SELECT 3, 9, 'cochilho', 0
    UNION ALL SELECT 3, 9, 'coçilo', 0
    UNION ALL SELECT 3, 10, 'xilofone', 1
    UNION ALL SELECT 3, 10, 'chilofone', 0
    UNION ALL SELECT 3, 10, 'xillofone', 0
    UNION ALL SELECT 3, 10, 'silofone', 0
    UNION ALL SELECT 4, 2, 'girassol', 1
    UNION ALL SELECT 4, 2, 'jirassol', 0
    UNION ALL SELECT 4, 2, 'girassou', 0
    UNION ALL SELECT 4, 2, 'girassoll', 0
    UNION ALL SELECT 4, 3, 'jiló', 1
    UNION ALL SELECT 4, 3, 'giló', 0
    UNION ALL SELECT 4, 3, 'jilô', 0
    UNION ALL SELECT 4, 3, 'gilô', 0
    UNION ALL SELECT 4, 4, 'tigela', 1
    UNION ALL SELECT 4, 4, 'tijela', 0
    UNION ALL SELECT 4, 4, 'tigella', 0
    UNION ALL SELECT 4, 4, 'tiguela', 0
    UNION ALL SELECT 4, 5, 'viagem', 1
    UNION ALL SELECT 4, 5, 'viajem', 0
    UNION ALL SELECT 4, 5, 'viagen', 0
    UNION ALL SELECT 4, 5, 'viajen', 0
    UNION ALL SELECT 4, 6, 'jeito', 1
    UNION ALL SELECT 4, 6, 'geito', 0
    UNION ALL SELECT 4, 6, 'jeitu', 0
    UNION ALL SELECT 4, 6, 'geitu', 0
    UNION ALL SELECT 4, 7, 'garagem', 1
    UNION ALL SELECT 4, 7, 'jaragem', 0
    UNION ALL SELECT 4, 7, 'garajem', 0
    UNION ALL SELECT 4, 7, 'garagen', 0
    UNION ALL SELECT 4, 8, 'geada', 1
    UNION ALL SELECT 4, 8, 'jeada', 0
    UNION ALL SELECT 4, 8, 'geiada', 0
    UNION ALL SELECT 4, 8, 'geadda', 0
    UNION ALL SELECT 4, 9, 'majestade', 1
    UNION ALL SELECT 4, 9, 'magestade', 0
    UNION ALL SELECT 4, 9, 'majestadi', 0
    UNION ALL SELECT 4, 9, 'majistade', 0
    UNION ALL SELECT 4, 10, 'fugir', 1
    UNION ALL SELECT 4, 10, 'fujir', 0
    UNION ALL SELECT 4, 10, 'fugirr', 0
    UNION ALL SELECT 4, 10, 'fujirr', 0
) AS novas
    ON novas.desafio_id = p.desafio_id
   AND novas.ordem = p.ordem
WHERE NOT EXISTS (
    SELECT 1
    FROM alternativas existente
    WHERE existente.pergunta_id = p.id
      AND BINARY existente.texto = BINARY novas.texto
);

COMMIT;
