-- Extensao aditiva de perfil e consentimento de acessibilidade.
-- Nenhuma tabela existente e removida ou recriada.
USE `palavra_a_bordo`;

SET @avatar_id_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'avatar_id'
);
SET @ddl_avatar_id := IF(
    @avatar_id_existe = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `avatar_id` VARCHAR(32) NOT NULL DEFAULT ''marinheiro'' AFTER `perfil`',
    'SELECT ''Coluna usuarios.avatar_id ja existe; nenhuma alteracao realizada.'' AS migration_status'
);
PREPARE stmt_avatar_id FROM @ddl_avatar_id;
EXECUTE stmt_avatar_id;
DEALLOCATE PREPARE stmt_avatar_id;

SET @preferencias_configuradas_existe := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'preferencias_acessibilidade'
      AND COLUMN_NAME = 'configurada'
);
SET @ddl_preferencias_configuradas := IF(
    @preferencias_configuradas_existe = 0,
    'ALTER TABLE `preferencias_acessibilidade` ADD COLUMN `configurada` TINYINT(1) NOT NULL DEFAULT 0 AFTER `acessibilidade_visual`',
    'SELECT ''Coluna preferencias_acessibilidade.configurada ja existe; nenhuma alteracao realizada.'' AS migration_status'
);
PREPARE stmt_preferencias_configuradas FROM @ddl_preferencias_configuradas;
EXECUTE stmt_preferencias_configuradas;
DEALLOCATE PREPARE stmt_preferencias_configuradas;
