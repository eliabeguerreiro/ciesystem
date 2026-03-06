CREATE DATABASE  IF NOT EXISTS `ciesytem` 
USE `ciesytem`;

CREATE TABLE `documentos_anexados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entidade_tipo` enum('estudante','inscricao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int NOT NULL,
  `tipo` enum('rg_frente','rg_verso','cnh_frente','cnh_verso','cpf_frente','cpf_verso','matricula','pagamento','foto_3x4') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validado` enum('pendente','validado','invalido') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `observacoes_validacao` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entidade` (`entidade_tipo`,`entidade_id`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `documentos_anexados` VALUES (78,'inscricao',59,'foto_3x4','uploads/documentos/doc_foto_3x4_69a851efa3986.jpeg','WhatsApp Image 2026-01-14 at 13.04.10.jpeg','pendente',NULL,'2026-03-04 15:38:23','2026-03-04 15:38:23'),(79,'inscricao',59,'matricula','uploads/documentos/doc_matricula_69a851efa41aa.jpg','diploma_eliabe.jpg','pendente',NULL,'2026-03-04 15:38:23','2026-03-04 15:38:23'),(80,'inscricao',59,'rg_frente','uploads/documentos/doc_rg_frente_69a851efa4802.jpg','1ebdme.jpg','pendente',NULL,'2026-03-04 15:38:23','2026-03-04 15:38:23'),(81,'inscricao',59,'rg_verso','uploads/documentos/doc_rg_verso_69a851efa4dde.jpg','1ebdme.jpg','pendente',NULL,'2026-03-04 15:38:23','2026-03-04 15:38:23');

CREATE TABLE `estudantes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_nascimento` date NOT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_tipo` enum('RG','CNH','PASSAPORTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_orgao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instituicao_id` int NOT NULL,
  `campus` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curso` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matricula` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `situacao_academica` enum('Matriculado','Trancado','Formado','Cancelado') COLLATE utf8mb4_unicode_ci DEFAULT 'Matriculado',
  `status_validacao` enum('pendente','dados_aprovados') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpf` (`cpf`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `estudantes` VALUES (64,'eliabe teste da paz','1999-03-20','12297095414','RG','4146797','ssds',2,'campus imbiribeira','teste','superior','201610040010','Matriculado','pendente','zululu.zululu@gmail.com','81988668870','2026-03-04 12:38:23',NULL);

CREATE TABLE `inscricoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estudante_id` int NOT NULL,
  `codigo_inscricao` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_emissao` date DEFAULT (curdate()),
  `data_validade` date NOT NULL,
  `situacao` enum('aguardando_validacao','dados_aprovados','pagamento_pendente','documentos_anexados','pago','cie_emitida_aguardando_entrega','cie_entregue_na_instituicao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pagamento_pendente',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `pagamento_confirmado` tinyint(1) NOT NULL DEFAULT '0',
  `origem` enum('estudante','administrador') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'estudante',
  `matricula_validada` tinyint(1) NOT NULL DEFAULT '0',
  `foto_documento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cie_codigo` (`codigo_inscricao`),
  KEY `estudante_id` (`estudante_id`),
  KEY `fk_inscricao_foto_documento` (`foto_documento_id`),
  CONSTRAINT `fk_inscricao_foto_documento` FOREIGN KEY (`foto_documento_id`) REFERENCES `documentos_anexados` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `inscricoes_ibfk_1` FOREIGN KEY (`estudante_id`) REFERENCES `estudantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inscricoes` VALUES (59,64,'2940df94-cf59-4c7b-9288-e28cab940b30','2026-03-04','2027-03-31','aguardando_validacao','2026-03-04 12:38:23',0,'estudante',0,NULL);

CREATE TABLE `instituicoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endereco` text COLLATE utf8mb4_unicode_ci,
  `cidade` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('ativa','inativa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativa',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `instituicoes` VALUES (1,'teste 1','teste endereco','Recife','PE','50800250','ativa','2026-02-09 16:32:02','2026-02-09 16:32:02'),(2,'testeMV','testeMV','testeMV','TE','','ativa','2026-02-09 23:11:24','2026-02-09 23:11:24');

CREATE TABLE `logistica_entregas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inscricao_id` int NOT NULL,
  `instituicao_id` int NOT NULL,
  `status` enum('saida_para_entrega','entregue_na_instituicao') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saida_para_entrega',
  `responsavel_saida` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_saida` datetime DEFAULT NULL,
  `responsavel_entrega` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_entrega_instituicao` datetime DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `registrado_por` int DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inscricao_id` (`inscricao_id`),
  KEY `instituicao_id` (`instituicao_id`),
  KEY `registrado_por` (`registrado_por`),
  CONSTRAINT `logistica_entregas_ibfk_1` FOREIGN KEY (`inscricao_id`) REFERENCES `inscricoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logistica_entregas_ibfk_2` FOREIGN KEY (`instituicao_id`) REFERENCES `instituicoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logistica_entregas_ibfk_3` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `acao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `registro_id` int DEFAULT NULL,
  `tabela` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `logs` VALUES (79,NULL,'inscricao_publica_realizada','Estudante: eliabe teste da paz, CPF: 12297095414, Código Inscrição: 2940df94-cf59-4c7b-9288-e28cab940b30',59,'inscricoes','2026-03-04 12:38:23');

DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES (1,'hants','hants@ciesytem.local','$2y$10$F4wcNlLHQyyn5Zj1ReSGE.aPtq.W2yzuIVdO/sMnjQG/qWjUZOWF.','admin','2025-12-17 19:13:52'),(4,'levy','levy@admin.com','$2y$10$cc85/23ApJZKwMdgUxmPyuYp/CnggcfmJZxJP364HKIBCFppIjyMG','admin','2026-02-14 15:26:21'),(5,'arielly','arielly@teste.com','$2y$10$AT0se4NlZOi/iyvlbQVbA./CK43KvWMTjeQlavdYl8QFYeOsIAjry','user','2026-02-14 15:27:08');
