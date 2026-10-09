-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3308
-- Generation Time: Oct 09, 2026 at 01:44 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `palavra_a_bordo`
--
CREATE DATABASE IF NOT EXISTS `palavra_a_bordo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `palavra_a_bordo`;

-- --------------------------------------------------------

--
-- Table structure for table `alternativas`
--

CREATE TABLE `alternativas` (
  `id` int NOT NULL,
  `pergunta_id` int NOT NULL,
  `texto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correta` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alternativas`
--

INSERT INTO `alternativas` (`id`, `pergunta_id`, `texto`, `correta`) VALUES
(1, 1, 'Exessao', 0),
(2, 1, 'Exceção', 1),
(3, 1, 'Excessão', 0),
(4, 1, 'Exeção', 0),
(5, 2, 'Pasaro', 0),
(6, 2, 'Passaro', 1),
(7, 2, 'Paçaro', 0),
(8, 2, 'Pássaro', 1),
(9, 3, 'Enchergar', 0),
(10, 3, 'Enxergar', 1),
(11, 3, 'Enchegar', 0),
(12, 3, 'Enxerguar', 0),
(13, 4, 'Viajem', 0),
(14, 4, 'Viagem', 1),
(15, 4, 'Viagen', 0),
(16, 4, 'Viajém', 0);

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

CREATE TABLE `categorias` (
  `id` int NOT NULL,
  `jogo_id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`id`, `jogo_id`, `nome`, `descricao`) VALUES
(1, 1, 'Uso do C', 'Uso correto da letra C e do cedilha.'),
(2, 1, 'S e SS', 'Diferenca entre S e SS nas palavras.'),
(3, 1, 'X e CH', 'Uso correto de X e CH.'),
(4, 1, 'G e J', 'Uso correto das letras G e J.');

-- --------------------------------------------------------

--
-- Table structure for table `conquistas`
--

CREATE TABLE `conquistas` (
  `id` int NOT NULL,
  `jogo_id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicao` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conquistas`
--

INSERT INTO `conquistas` (`id`, `jogo_id`, `nome`, `descricao`, `icone`, `condicao`) VALUES
(1, 1, 'Primeiro Acerto', 'Responda corretamente a uma pergunta.', 'medalha-primeiro-acerto', 'Acertar pelo menos uma pergunta.'),
(2, 1, 'Olho de Aguia', 'Acerte cinco perguntas.', 'medalha-olho-de-aguia', 'Acertar cinco perguntas.'),
(3, 1, 'Mestre da Ortografia', 'Conclua um desafio com todas as respostas corretas.', 'medalha-mestre-ortografia', 'Concluir um desafio sem errar.');

-- --------------------------------------------------------

--
-- Table structure for table `desafios`
--

CREATE TABLE `desafios` (
  `id` int NOT NULL,
  `categoria_id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `nivel` int NOT NULL,
  `ordem` int NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `desafios`
--

INSERT INTO `desafios` (`id`, `categoria_id`, `nome`, `descricao`, `nivel`, `ordem`, `ativo`) VALUES
(1, 1, 'Uso do C - Facil', 'Identifique a palavra escrita corretamente.', 1, 1, 1),
(2, 2, 'S e SS - Facil', 'Pratique o uso de S e SS.', 1, 2, 1),
(3, 3, 'X e CH - Medio', 'Pratique o uso de X e CH.', 2, 3, 1),
(4, 4, 'G e J - Medio', 'Pratique o uso de G e J.', 2, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `jogos`
--

CREATE TABLE `jogos` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jogos`
--

INSERT INTO `jogos` (`id`, `nome`, `descricao`, `ativo`) VALUES
(1, 'Caca ao Erro - Ortografia', 'Jogo educativo para aprender ortografia por meio de desafios.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `perguntas`
--

CREATE TABLE `perguntas` (
  `id` int NOT NULL,
  `desafio_id` int NOT NULL,
  `enunciado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `explicacao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perguntas`
--

INSERT INTO `perguntas` (`id`, `desafio_id`, `enunciado`, `explicacao`, `ordem`) VALUES
(1, 1, 'Qual palavra esta escrita corretamente?', 'Excecao e escrita com C cedilha (Ç), seguida de A.', 1),
(2, 2, 'Qual palavra esta escrita corretamente?', 'Passaro e escrita com SS.', 1),
(3, 3, 'Qual palavra esta escrita corretamente?', 'Enxergar e escrita com X.', 1),
(4, 4, 'Qual palavra esta escrita corretamente?', 'Viagem, quando significa deslocamento, e escrita com G.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `respostas`
--

CREATE TABLE `respostas` (
  `id` int NOT NULL,
  `tentativa_id` int NOT NULL,
  `pergunta_id` int NOT NULL,
  `alternativa_id` int NOT NULL,
  `correta` tinyint(1) NOT NULL,
  `pontos` int NOT NULL DEFAULT '0',
  `data_resposta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tentativas`
--

CREATE TABLE `tentativas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `desafio_id` int NOT NULL,
  `pontuacao` int NOT NULL DEFAULT '0',
  `vidas_restantes` int NOT NULL DEFAULT '3',
  `concluido` tinyint(1) NOT NULL DEFAULT '0',
  `data_inicio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` int NOT NULL DEFAULT '1',
  `experiencia` int NOT NULL DEFAULT '0',
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuario_conquistas`
--

CREATE TABLE `usuario_conquistas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `conquista_id` int NOT NULL,
  `data_conquista` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alternativas`
--
ALTER TABLE `alternativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_alternativas_perguntas` (`pergunta_id`);

--
-- Indexes for table `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_categorias_jogos` (`jogo_id`);

--
-- Indexes for table `conquistas`
--
ALTER TABLE `conquistas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_conquistas_jogos` (`jogo_id`);

--
-- Indexes for table `desafios`
--
ALTER TABLE `desafios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_desafios_categorias` (`categoria_id`);

--
-- Indexes for table `jogos`
--
ALTER TABLE `jogos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `perguntas`
--
ALTER TABLE `perguntas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_perguntas_desafios` (`desafio_id`);

--
-- Indexes for table `respostas`
--
ALTER TABLE `respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_respostas_tentativas` (`tentativa_id`),
  ADD KEY `fk_respostas_perguntas` (`pergunta_id`),
  ADD KEY `fk_respostas_alternativas` (`alternativa_id`);

--
-- Indexes for table `tentativas`
--
ALTER TABLE `tentativas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tentativas_usuarios` (`usuario_id`),
  ADD KEY `fk_tentativas_desafios` (`desafio_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `usuario_conquistas`
--
ALTER TABLE `usuario_conquistas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_conquista` (`usuario_id`,`conquista_id`),
  ADD KEY `fk_usuario_conquistas_conquistas` (`conquista_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alternativas`
--
ALTER TABLE `alternativas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `conquistas`
--
ALTER TABLE `conquistas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `desafios`
--
ALTER TABLE `desafios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jogos`
--
ALTER TABLE `jogos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `perguntas`
--
ALTER TABLE `perguntas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `respostas`
--
ALTER TABLE `respostas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tentativas`
--
ALTER TABLE `tentativas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuario_conquistas`
--
ALTER TABLE `usuario_conquistas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alternativas`
--
ALTER TABLE `alternativas`
  ADD CONSTRAINT `fk_alternativas_perguntas` FOREIGN KEY (`pergunta_id`) REFERENCES `perguntas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categorias_jogos` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conquistas`
--
ALTER TABLE `conquistas`
  ADD CONSTRAINT `fk_conquistas_jogos` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `desafios`
--
ALTER TABLE `desafios`
  ADD CONSTRAINT `fk_desafios_categorias` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `perguntas`
--
ALTER TABLE `perguntas`
  ADD CONSTRAINT `fk_perguntas_desafios` FOREIGN KEY (`desafio_id`) REFERENCES `desafios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `respostas`
--
ALTER TABLE `respostas`
  ADD CONSTRAINT `fk_respostas_alternativas` FOREIGN KEY (`alternativa_id`) REFERENCES `alternativas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respostas_perguntas` FOREIGN KEY (`pergunta_id`) REFERENCES `perguntas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respostas_tentativas` FOREIGN KEY (`tentativa_id`) REFERENCES `tentativas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tentativas`
--
ALTER TABLE `tentativas`
  ADD CONSTRAINT `fk_tentativas_desafios` FOREIGN KEY (`desafio_id`) REFERENCES `desafios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tentativas_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `usuario_conquistas`
--
ALTER TABLE `usuario_conquistas`
  ADD CONSTRAINT `fk_usuario_conquistas_conquistas` FOREIGN KEY (`conquista_id`) REFERENCES `conquistas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuario_conquistas_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
--
-- Database: `saep_db`
--
CREATE DATABASE IF NOT EXISTS `saep_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `saep_db`;

-- --------------------------------------------------------

--
-- Table structure for table `agendamentos`
--

CREATE TABLE `agendamentos` (
  `id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data_hora` datetime NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agendamentos`
--

INSERT INTO `agendamentos` (`id`, `pet_id`, `data_hora`, `motivo`, `observacoes`) VALUES
(1, 1, '2026-11-20 10:00:00', 'Consulta', 'Primeiro atendimento'),
(2, 2, '2026-11-22 14:00:00', 'Vacinação', 'Verificar carteira'),
(3, 3, '2026-11-27 16:00:00', 'Retorno', 'Reavaliação'),
(4, 4, '2026-10-15 15:01:00', 'doente', ''),
(5, 5, '2026-10-15 15:01:00', 'Dodói', ''),
(6, 6, '2026-10-12 15:02:00', 'doentinha', '');

-- --------------------------------------------------------

--
-- Table structure for table `pets`
--

CREATE TABLE `pets` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `especie` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raca` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_nascimento` date NOT NULL,
  `tutor_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pets`
--

INSERT INTO `pets` (`id`, `nome`, `especie`, `raca`, `data_nascimento`, `tutor_id`) VALUES
(1, 'Scott', 'Cão', 'Huscky', '2023-07-10', 1),
(2, 'Stiles', 'Gato', 'Persa', '2024-08-06', 2),
(3, 'Lydia', 'Peixe', 'Piranha', '2025-09-17', 3),
(4, 'Jackson', 'Lagarto', 'calango', '2026-07-16', 7),
(5, 'Liam', 'Gatinho', 'siames', '2026-03-25', 6),
(6, 'Malia', 'cachorra', 'Huscky', '2026-08-02', 5);

-- --------------------------------------------------------

--
-- Table structure for table `tutores`
--

CREATE TABLE `tutores` (
  `id` int NOT NULL,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf_criptografado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tutores`
--

INSERT INTO `tutores` (`id`, `nome`, `cpf_criptografado`, `telefone`, `email`) VALUES
(1, 'Ana', '2YlHo6EkanKCIUdVvf6osENDhyFlhBltO7MM/2L5bBselJkZMJWQ', '17999990001', 'Ana@gmail.com'),
(2, 'Duda', 'FpBdcWvQ3Uv18YmhV5/8ZzD/JLddWXjkvtRED8/hvmPAbJdSIY7j', '17999990002', 'Duda@gmail.com'),
(3, 'Manda', 'Z54/l7XrzL71VrjGwP0V3Xgq6Du1lRaxhyBdo6E4ioukq7SwToNx', '17999990003', 'Manda@gmail.com'),
(5, 'Lala', 'lBtMHKJMGafpTSI/mN9WFCnkevuHySX1DSMaZT5M8fzW5Ojfd3lr', '17991012525', 'Lala@gmail.com'),
(6, 'Lana', 'Mof3pyrK5E8Qk/SSnK7W3oaI/cqhf82z05qGGjoeb3tGH2czGlc5', '991018989', 'Lana@gmail.com'),
(7, 'MariaD', 'tf6v5LJ5X+WIdLgZiHZXFWWOwuM1Y3kxWrSj777KgQO9RxbDERmo', '991012222', 'MariaD@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `login`, `senha_hash`) VALUES
(1, 'Administrador', 'admin', '$2b$12$G3u2JYle3DwdtBB3JUfD4eMvYHkL2p2ry8D0zxTl0m2zj1KBWT0si'),
(2, 'Recepção', 'recepcao', '$2b$12$IniF9Wusd.PODBe4pXHwxeoKz.i/ILI.uql7zmxgiBhVm9ZSaE3zy'),
(3, 'Veterinário', 'vet', '$2b$12$XdUNZqjiTCxpedU45wvzZu6S7CyG2ebeNvpsEVp9la03SgkJUJbCW');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_agendamento_pet` (`pet_id`);

--
-- Indexes for table `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pet_tutor` (`tutor_id`);

--
-- Indexes for table `tutores`
--
ALTER TABLE `tutores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agendamentos`
--
ALTER TABLE `agendamentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pets`
--
ALTER TABLE `pets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tutores`
--
ALTER TABLE `tutores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD CONSTRAINT `fk_agendamento_pet` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `fk_pet_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `tutores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
