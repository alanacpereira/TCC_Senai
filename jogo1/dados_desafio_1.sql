SET NAMES utf8mb4;

START TRANSACTION;

UPDATE perguntas
SET enunciado = 'Qual palavra está escrita corretamente?',
    explicacao = 'A forma correta é “exceção”, escrita com ç.'
WHERE id = 1 AND desafio_id = 1;

UPDATE alternativas SET texto = 'Exessão', correta = 0 WHERE id = 9 AND pergunta_id = 1;
UPDATE alternativas SET texto = 'Excessão', correta = 0 WHERE id = 10 AND pergunta_id = 1;
UPDATE alternativas SET texto = 'Exceção', correta = 1 WHERE id = 11 AND pergunta_id = 1;
UPDATE alternativas SET texto = 'Esceção', correta = 0 WHERE id = 12 AND pergunta_id = 1;

UPDATE perguntas
SET enunciado = 'Qual frase está escrita corretamente?',
    explicacao = 'A palavra “passeio” é escrita com ss, e a expressão correta é “ao passeio”.'
WHERE id = 2 AND desafio_id = 1;

UPDATE alternativas SET texto = 'Eu quero ir ao passeio amanhã.', correta = 1 WHERE pergunta_id = 2 AND id = (SELECT id FROM (SELECT MIN(id) AS id FROM alternativas WHERE pergunta_id = 2) AS primeira);
UPDATE alternativas SET texto = 'Eu quero ir au passeio amanhã.', correta = 0 WHERE pergunta_id = 2 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 2 ORDER BY id LIMIT 1 OFFSET 1) AS segunda);
UPDATE alternativas SET texto = 'Eu quero ir ao paseio amanhã.', correta = 0 WHERE pergunta_id = 2 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 2 ORDER BY id LIMIT 1 OFFSET 2) AS terceira);
UPDATE alternativas SET texto = 'Eu quero ir au paseio amanhã.', correta = 0 WHERE pergunta_id = 2 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 2 ORDER BY id LIMIT 1 OFFSET 3) AS quarta);

UPDATE perguntas
SET enunciado = 'Qual palavra está escrita corretamente?',
    explicacao = 'A forma correta é “necessário”, com ss e c.'
WHERE id = 3 AND desafio_id = 1;
UPDATE alternativas SET texto = 'Necessário', correta = 1 WHERE pergunta_id = 3 AND id = (SELECT id FROM (SELECT MIN(id) AS id FROM alternativas WHERE pergunta_id = 3) AS primeira);
UPDATE alternativas SET texto = 'Necesário', correta = 0 WHERE pergunta_id = 3 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 3 ORDER BY id LIMIT 1 OFFSET 1) AS segunda);
UPDATE alternativas SET texto = 'Neceçário', correta = 0 WHERE pergunta_id = 3 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 3 ORDER BY id LIMIT 1 OFFSET 2) AS terceira);
UPDATE alternativas SET texto = 'Nescessário', correta = 0 WHERE pergunta_id = 3 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 3 ORDER BY id LIMIT 1 OFFSET 3) AS quarta);

UPDATE perguntas
SET enunciado = 'Qual frase está escrita corretamente?',
    explicacao = 'A palavra “ótima” recebe acento agudo no ó, e “apresentação” é escrita com ç.'
WHERE id = 4 AND desafio_id = 1;
UPDATE alternativas SET texto = 'O aluno fez uma ótima apresentação.', correta = 1 WHERE pergunta_id = 4 AND id = (SELECT id FROM (SELECT MIN(id) AS id FROM alternativas WHERE pergunta_id = 4) AS primeira);
UPDATE alternativas SET texto = 'O aluno fez uma otima apresentação.', correta = 0 WHERE pergunta_id = 4 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 4 ORDER BY id LIMIT 1 OFFSET 1) AS segunda);
UPDATE alternativas SET texto = 'O aluno fez uma ótima apresenteção.', correta = 0 WHERE pergunta_id = 4 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 4 ORDER BY id LIMIT 1 OFFSET 2) AS terceira);
UPDATE alternativas SET texto = 'O aluno fez uma otima apresenteção.', correta = 0 WHERE pergunta_id = 4 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 4 ORDER BY id LIMIT 1 OFFSET 3) AS quarta);

UPDATE perguntas
SET enunciado = 'Qual palavra está escrita corretamente?',
    explicacao = 'A forma correta é “acontecimento”, escrita com c e sem ç ou ss.'
WHERE id = 5 AND desafio_id = 1;
UPDATE alternativas SET texto = 'Acontecimento', correta = 1 WHERE pergunta_id = 5 AND id = (SELECT id FROM (SELECT MIN(id) AS id FROM alternativas WHERE pergunta_id = 5) AS primeira);
UPDATE alternativas SET texto = 'Acontessimento', correta = 0 WHERE pergunta_id = 5 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 5 ORDER BY id LIMIT 1 OFFSET 1) AS segunda);
UPDATE alternativas SET texto = 'Aconteçimento', correta = 0 WHERE pergunta_id = 5 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 5 ORDER BY id LIMIT 1 OFFSET 2) AS terceira);
UPDATE alternativas SET texto = 'Acontesimento', correta = 0 WHERE pergunta_id = 5 AND id = (SELECT id FROM (SELECT id FROM alternativas WHERE pergunta_id = 5 ORDER BY id LIMIT 1 OFFSET 3) AS quarta);

COMMIT;
