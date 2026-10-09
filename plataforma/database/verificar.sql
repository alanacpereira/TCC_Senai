-- Consultas somente leitura para conferir o banco antes/depois da migração.
USE `palavra_a_bordo`;

SELECT TABLE_NAME, TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

SELECT 'jogos' AS tabela, COUNT(*) AS registros FROM jogos
UNION ALL SELECT 'categorias', COUNT(*) FROM categorias
UNION ALL SELECT 'desafios', COUNT(*) FROM desafios
UNION ALL SELECT 'perguntas', COUNT(*) FROM perguntas
UNION ALL SELECT 'alternativas', COUNT(*) FROM alternativas
UNION ALL SELECT 'tentativas', COUNT(*) FROM tentativas
UNION ALL SELECT 'respostas', COUNT(*) FROM respostas
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'conquistas', COUNT(*) FROM conquistas
UNION ALL SELECT 'usuario_conquistas', COUNT(*) FROM usuario_conquistas;

SELECT j.id, j.nome,
       COUNT(DISTINCT c.id) AS categorias,
       COUNT(DISTINCT d.id) AS desafios,
       COUNT(DISTINCT p.id) AS perguntas,
       COUNT(DISTINCT a.id) AS alternativas
FROM jogos j
LEFT JOIN categorias c ON c.jogo_id = j.id
LEFT JOIN desafios d ON d.categoria_id = c.id
LEFT JOIN perguntas p ON p.desafio_id = d.id
LEFT JOIN alternativas a ON a.pergunta_id = p.id
GROUP BY j.id, j.nome
ORDER BY j.id;

SELECT tentativa_id, pergunta_id, COUNT(*) AS respostas_duplicadas
FROM respostas
GROUP BY tentativa_id, pergunta_id
HAVING COUNT(*) > 1;
