-- Extensao aditiva de perfil e consentimento de acessibilidade.
-- Execute uma única vez, depois da migration 001 da plataforma.
-- Nenhuma tabela existente e removida ou recriada.

ALTER TABLE `usuarios`
    ADD COLUMN `avatar_id` VARCHAR(32) NOT NULL DEFAULT 'marinheiro'
    AFTER `perfil`;

ALTER TABLE `preferencias_acessibilidade`
    ADD COLUMN `configurada` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `acessibilidade_visual`;
