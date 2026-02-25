CREATE DATABASE  IF NOT EXISTS `ciesytem` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `ciesytem`;
-- MySQL dump 10.13  Distrib 8.0.40, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ciesytem
-- ------------------------------------------------------
-- Server version	8.0.40

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `documentos_anexados`
--

DROP TABLE IF EXISTS `documentos_anexados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_anexados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entidade_tipo` enum('estudante','inscricao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidade_id` int NOT NULL,
  `tipo` enum('rg_frente','rg_verso','cnh_frente','cnh_verso','cpf_frente','cpf_verso','matricula','pagamento','selfie_documento') COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validado` enum('pendente','validado','invalido') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `observacoes_validacao` text COLLATE utf8mb4_unicode_ci,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entidade` (`entidade_tipo`,`entidade_id`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_anexados`
--

LOCK TABLES `documentos_anexados` WRITE;
/*!40000 ALTER TABLE `documentos_anexados` DISABLE KEYS */;
INSERT INTO `documentos_anexados` VALUES (37,'inscricao',46,'rg_frente','uploads/documentos/doc_rg_frente_699c844e03366.jpg','1ebdm.jpg','validado',NULL,'2026-02-23 16:46:06','2026-02-23 16:46:06'),(38,'inscricao',46,'rg_verso','uploads/documentos/doc_rg_verso_699c844e03abd.jpg','1ebdm.jpg','validado',NULL,'2026-02-23 16:46:06','2026-02-23 16:46:06'),(39,'inscricao',50,'rg_frente','uploads/documentos/doc_rg_frente_699dccf9b66f2.jpg','1ebdme.jpg','validado',NULL,'2026-02-24 16:08:25','2026-02-24 16:08:25'),(40,'inscricao',50,'rg_verso','uploads/documentos/doc_rg_verso_699dccf9b6f3e.jpg','1ebdme.jpg','validado',NULL,'2026-02-24 16:08:25','2026-02-24 16:08:25'),(41,'inscricao',50,'matricula','uploads/documentos/doc_matricula_699dccf9b76fa.jpg','diploma_eliabe.jpg','validado',NULL,'2026-02-24 16:08:25','2026-02-24 16:08:25');
/*!40000 ALTER TABLE `documentos_anexados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estudantes`
--

DROP TABLE IF EXISTS `estudantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudantes`
--

LOCK TABLES `estudantes` WRITE;
/*!40000 ALTER TABLE `estudantes` DISABLE KEYS */;
INSERT INTO `estudantes` VALUES (55,'teste_adm','2026-01-26','31298309183','RG','466351','aaaf',1,'teste_adm','teste_adm','teste_adm','teste_adm','Matriculado','dados_aprovados','teste@testre','81988668888','2026-02-24 13:08:25',NULL);
/*!40000 ALTER TABLE `estudantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inscricoes`
--

DROP TABLE IF EXISTS `inscricoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscricoes`
--

LOCK TABLES `inscricoes` WRITE;
/*!40000 ALTER TABLE `inscricoes` DISABLE KEYS */;
INSERT INTO `inscricoes` VALUES (50,55,'e7330c5e-ef6f-4ee3-83fb-c1ce0cb7d7df','2026-02-24','2027-03-31','aguardando_validacao','2026-02-24 13:08:25',0,'administrador',1,NULL);
/*!40000 ALTER TABLE `inscricoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instituicoes`
--

DROP TABLE IF EXISTS `instituicoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instituicoes`
--

LOCK TABLES `instituicoes` WRITE;
/*!40000 ALTER TABLE `instituicoes` DISABLE KEYS */;
INSERT INTO `instituicoes` VALUES (1,'teste 1','teste endereco','Recife','PE','50800250','ativa','2026-02-09 16:32:02','2026-02-09 16:32:02'),(2,'testeMV','testeMV','testeMV','TE','','ativa','2026-02-09 23:11:24','2026-02-09 23:11:24');
/*!40000 ALTER TABLE `instituicoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica_entregas`
--

DROP TABLE IF EXISTS `logistica_entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica_entregas`
--

LOCK TABLES `logistica_entregas` WRITE;
/*!40000 ALTER TABLE `logistica_entregas` DISABLE KEYS */;
/*!40000 ALTER TABLE `logistica_entregas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (48,NULL,'inscricao_publica_realizada','Estudante: Teste_Publico, CPF: 12332112312, Código Inscrição: a8175ed0-e14c-4e82-884f-eacbb1e43af4',44,'inscricoes','2026-02-23 13:17:04'),(49,1,'excluiu_estudante','ID: 50, Nome: teste_publico',50,'estudantes','2026-02-23 13:45:23'),(50,1,'criou_estudante_admin','Estudante: teste sistema, Matrícula: teste sistema (Sem documentos de identidade)',51,'estudantes','2026-02-23 13:46:06'),(51,1,'excluiu_estudante','ID: 50, Nome: ',50,'estudantes','2026-02-23 13:46:06'),(52,1,'excluiu_estudante','ID: 54, Nome: Teste_Publico',54,'estudantes','2026-02-24 13:07:23'),(53,1,'anexou_e_validou_comprovante_matricula_admin','Inscrição ID: 50, Estudante ID: 55, Origem: administrador',50,'inscricoes','2026-02-24 13:08:25'),(54,1,'excluiu_estudante','ID: 54, Nome: ',54,'estudantes','2026-02-24 13:08:25');
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'hants','hants@ciesytem.local','$2y$10$F4wcNlLHQyyn5Zj1ReSGE.aPtq.W2yzuIVdO/sMnjQG/qWjUZOWF.','admin','2025-12-17 19:13:52'),(4,'levy','levy@admin.com','$2y$10$cc85/23ApJZKwMdgUxmPyuYp/CnggcfmJZxJP364HKIBCFppIjyMG','admin','2026-02-14 15:26:21'),(5,'arielly','arielly@teste.com','$2y$10$AT0se4NlZOi/iyvlbQVbA./CK43KvWMTjeQlavdYl8QFYeOsIAjry','user','2026-02-14 15:27:08');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-24 13:19:05
