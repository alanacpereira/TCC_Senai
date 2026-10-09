-- Migração aditiva para a plataforma Palavra a Bordo.
-- Alvo verificado: MySQL 8.4.3, banco palavra_a_bordo.
-- Não recria nem remove tabelas e não altera dados existentes dos jogos,
-- perguntas, alternativas, tentativas ou respostas. Em usuarios, adiciona
-- perfil com padrão aluno e identifica a conta convidada legada verificada.
-- Pode ser reaplicada: tabelas e coluna são verificadas antes de sua criação.
-- Revise as mensagens de diagnóstico ao final antes de integrar a aplicação.

USE `palavra_a_bordo`;

-- Contas existentes e novos cadastros começam como estudantes.
SET @coluna_perfil_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'perfil'
);
SET @ddl_perfil := IF(
    @coluna_perfil_existe = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `perfil` ENUM(''aluno'', ''professor'', ''convidado'') NOT NULL DEFAULT ''aluno'' AFTER `senha`',
    'SELECT ''Coluna usuarios.perfil já existe; nenhuma alteração realizada.'' AS migration_status'
);
PREPARE stmt_perfil FROM @ddl_perfil;
EXECUTE stmt_perfil;
DEALLOCATE PREPARE stmt_perfil;

-- O usuário 1 é o jogador convidado legado usado pelos jogos sem login.
-- A atualização só ocorre se a coluna realmente permitir o valor convidado.
SET @perfil_convidado_disponivel := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'perfil'
      AND COLUMN_TYPE LIKE '%convidado%'
);
SET @sql_perfil_convidado := IF(
    @perfil_convidado_disponivel = 1,
    'UPDATE `usuarios` SET `perfil` = ''convidado'' WHERE `id` = 1 AND `nome` = ''Jogador convidado'' AND `perfil` = ''aluno''',
    'SELECT ''Conta convidada não classificada: confirme manualmente o perfil da conta legada.'' AS migration_status'
);
PREPARE stmt_perfil_convidado FROM @sql_perfil_convidado;
EXECUTE stmt_perfil_convidado;
DEALLOCATE PREPARE stmt_perfil_convidado;

-- Preferências são separadas dos dados de autenticação e têm padrões neutros.
CREATE TABLE IF NOT EXISTS `preferencias_acessibilidade` (
    `usuario_id` INT NOT NULL,
    `acessibilidade_visual` TINYINT(1) NOT NULL DEFAULT 0,
    `tamanho_fonte` ENUM('padrao', 'grande', 'muito_grande') NOT NULL DEFAULT 'padrao',
    `alto_contraste` TINYINT(1) NOT NULL DEFAULT 0,
    `espacamento_linhas` TINYINT(1) NOT NULL DEFAULT 0,
    `melhor_legibilidade` TINYINT(1) NOT NULL DEFAULT 0,
    `reduzir_animacoes` TINYINT(1) NOT NULL DEFAULT 0,
    `destacar_interativos` TINYINT(1) NOT NULL DEFAULT 0,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`usuario_id`),
    CONSTRAINT `fk_preferencias_acessibilidade_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `ck_preferencias_acessibilidade_flags` CHECK (
        `acessibilidade_visual` IN (0, 1)
        AND `alto_contraste` IN (0, 1)
        AND `espacamento_linhas` IN (0, 1)
        AND `melhor_legibilidade` IN (0, 1)
        AND `reduzir_animacoes` IN (0, 1)
        AND `destacar_interativos` IN (0, 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carteira e extrato virtual. O saldo é atualizado sob transação e bloqueio
-- da carteira; cada operação usa uma chave idempotente gerada no servidor.
CREATE TABLE IF NOT EXISTS `carteiras` (
    `usuario_id` INT NOT NULL,
    `saldo_pontos` INT UNSIGNED NOT NULL DEFAULT 0,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`usuario_id`),
    CONSTRAINT `fk_carteiras_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transacoes_carteira` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT NOT NULL,
    `tipo` ENUM('credito', 'debito') NOT NULL,
    `valor_pontos` INT UNSIGNED NOT NULL,
    `origem_tipo` VARCHAR(40) NOT NULL,
    `origem_referencia_id` BIGINT UNSIGNED DEFAULT NULL,
    `chave_idempotencia` VARCHAR(128) NOT NULL,
    `descricao` VARCHAR(255) DEFAULT NULL,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transacoes_carteira_idempotencia` (`chave_idempotencia`),
    KEY `ix_transacoes_carteira_usuario_data` (`usuario_id`, `criada_em`),
    CONSTRAINT `fk_transacoes_carteira_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `ck_transacoes_carteira_valor` CHECK (`valor_pontos` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de recompensas; condições são avaliadas pelo servidor, não pelo navegador.
CREATE TABLE IF NOT EXISTS `recompensas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` VARCHAR(64) NOT NULL,
    `tipo` ENUM('bau', 'insignia', 'titulo', 'item', 'desafio') NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `descricao` TEXT NOT NULL,
    `custo_pontos` INT UNSIGNED NOT NULL DEFAULT 0,
    `condicoes` JSON DEFAULT NULL,
    `ativa` TINYINT(1) NOT NULL DEFAULT 1,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recompensas_codigo` (`codigo`),
    KEY `ix_recompensas_ativa_custo` (`ativa`, `custo_pontos`),
    CONSTRAINT `ck_recompensas_ativa` CHECK (`ativa` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Os resgates são únicos por estudante e recompensa. Itens gratuitos não
-- exigem uma movimentação financeira; resgates pagos referenciam o débito.
CREATE TABLE IF NOT EXISTS `resgates_recompensas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT NOT NULL,
    `recompensa_id` INT UNSIGNED NOT NULL,
    `custo_pago` INT UNSIGNED NOT NULL DEFAULT 0,
    `transacao_carteira_id` BIGINT UNSIGNED DEFAULT NULL,
    `resgatada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resgates_usuario_recompensa` (`usuario_id`, `recompensa_id`),
    UNIQUE KEY `uq_resgates_transacao_carteira` (`transacao_carteira_id`),
    KEY `ix_resgates_recompensa_data` (`recompensa_id`, `resgatada_em`),
    CONSTRAINT `fk_resgates_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_resgates_recompensa`
        FOREIGN KEY (`recompensa_id`) REFERENCES `recompensas` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_resgates_transacao_carteira`
        FOREIGN KEY (`transacao_carteira_id`) REFERENCES `transacoes_carteira` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turmas e vínculos explícitos limitam os dados disponíveis a cada professor.
CREATE TABLE IF NOT EXISTS `turmas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo` CHAR(12) NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `descricao` VARCHAR(500) DEFAULT NULL,
    `ativa` TINYINT(1) NOT NULL DEFAULT 1,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_turmas_codigo` (`codigo`),
    CONSTRAINT `ck_turmas_ativa` CHECK (`ativa` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `turma_professores` (
    `turma_id` INT UNSIGNED NOT NULL,
    `professor_id` INT NOT NULL,
    `vinculada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`turma_id`, `professor_id`),
    KEY `ix_turma_professores_professor` (`professor_id`, `turma_id`),
    CONSTRAINT `fk_turma_professores_turma`
        FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_turma_professores_usuario`
        FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `turma_alunos` (
    `turma_id` INT UNSIGNED NOT NULL,
    `aluno_id` INT NOT NULL,
    `vinculado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`turma_id`, `aluno_id`),
    KEY `ix_turma_alunos_aluno` (`aluno_id`, `turma_id`),
    CONSTRAINT `fk_turma_alunos_turma`
        FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_turma_alunos_usuario`
        FOREIGN KEY (`aluno_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convites de professor armazenam somente o hash do token de uso único.
CREATE TABLE IF NOT EXISTS `convites_professores` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expira_em` DATETIME NOT NULL,
    `resgatado_em` DATETIME DEFAULT NULL,
    `criado_por_usuario_id` INT DEFAULT NULL,
    `resgatado_por_usuario_id` INT DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_convites_professores_token_hash` (`token_hash`),
    KEY `ix_convites_professores_email_expira` (`email`, `expira_em`),
    CONSTRAINT `fk_convites_professores_criador`
        FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_convites_professores_resgatado`
        FOREIGN KEY (`resgatado_por_usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de recuperação guardam o hash, expiração e uso único; envio de e-mail
-- depende de configuração posterior e não é realizado por esta migração.
CREATE TABLE IF NOT EXISTS `tokens_recuperacao_senha` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expira_em` DATETIME NOT NULL,
    `utilizado_em` DATETIME DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tokens_recuperacao_hash` (`token_hash`),
    KEY `ix_tokens_recuperacao_usuario_expira` (`usuario_id`, `expira_em`),
    CONSTRAINT `fk_tokens_recuperacao_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitação de tentativas de login sem guardar e-mail ou endereço IP em claro.
-- A aplicação deve gerar os identificadores com HMAC e uma chave secreta externa.
CREATE TABLE IF NOT EXISTS `limites_login` (
    `email_hash` BINARY(32) NOT NULL,
    `origem_hash` BINARY(32) NOT NULL,
    `falhas` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `janela_iniciada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `bloqueado_ate` DATETIME DEFAULT NULL,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`email_hash`, `origem_hash`),
    KEY `ix_limites_login_bloqueado` (`bloqueado_ate`),
    CONSTRAINT `ck_limites_login_falhas` CHECK (`falhas` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Defesa adicional no banco contra registrar duas respostas à mesma pergunta
-- na mesma tentativa. Se dados duplicados surgirem antes da aplicação,
-- preserva os registros e informa que a criação do índice foi ignorada.
SET @respostas_duplicadas := (
    SELECT COUNT(*)
    FROM (
        SELECT `tentativa_id`, `pergunta_id`
        FROM `respostas`
        GROUP BY `tentativa_id`, `pergunta_id`
        HAVING COUNT(*) > 1
    ) AS pares_duplicados
);
SET @indice_respostas_existe := (
    SELECT COUNT(*)
    FROM (
        SELECT `INDEX_NAME`
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'respostas'
          AND NON_UNIQUE = 0
        GROUP BY `INDEX_NAME`
        HAVING GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',')
            = 'tentativa_id,pergunta_id'
    ) AS indices_validos
);
SET @ddl_indice_respostas := IF(
    @respostas_duplicadas > 0,
    'SELECT ''Índice único não criado: há respostas duplicadas por tentativa/pergunta. Investigue os registros sem removê-los.'' AS migration_status',
    IF(
        @indice_respostas_existe = 0,
        'ALTER TABLE `respostas` ADD UNIQUE INDEX `uq_respostas_tentativa_pergunta` (`tentativa_id`, `pergunta_id`)',
        'SELECT ''Índice único de respostas já existe; nenhuma alteração realizada.'' AS migration_status'
    )
);
PREPARE stmt_indice_respostas FROM @ddl_indice_respostas;
EXECUTE stmt_indice_respostas;
DEALLOCATE PREPARE stmt_indice_respostas;

-- Verificação pós-migração. A ausência das novas tabelas antes de executar
-- esta migração não afeta os dados atuais dos jogos.
SELECT `TABLE_NAME`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND `TABLE_NAME` IN (
      'preferencias_acessibilidade',
      'carteiras',
      'transacoes_carteira',
      'recompensas',
      'resgates_recompensas',
      'turmas',
      'turma_professores',
      'turma_alunos',
      'convites_professores',
      'tokens_recuperacao_senha',
      'limites_login'
  )
ORDER BY `TABLE_NAME`;

SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_DEFAULT`
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'usuarios'
  AND COLUMN_NAME = 'perfil';

SELECT `INDEX_NAME`, `COLUMN_NAME`, `NON_UNIQUE`
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'respostas'
  AND `INDEX_NAME` = 'uq_respostas_tentativa_pergunta'
ORDER BY `SEQ_IN_INDEX`;
