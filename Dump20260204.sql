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
-- Table structure for table `documentos_estudante`
--

DROP TABLE IF EXISTS `documentos_estudante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_estudante` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estudante_id` int NOT NULL,
  `tipo` enum('rg','cnh','passaporte','cpf') COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `estudante_id` (`estudante_id`),
  CONSTRAINT `documentos_estudante_ibfk_1` FOREIGN KEY (`estudante_id`) REFERENCES `estudantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_estudante`
--

LOCK TABLES `documentos_estudante` WRITE;
/*!40000 ALTER TABLE `documentos_estudante` DISABLE KEYS */;
INSERT INTO `documentos_estudante` VALUES (1,10,'rg','uploads/documentos_estudante/doc_identidade_rg_697ac82fde21c.jpg','WhatsApp-Image-2026-01-14-at-13.04.10.jpg','2026-01-28 23:38:39');
/*!40000 ALTER TABLE `documentos_estudante` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_inscricao`
--

DROP TABLE IF EXISTS `documentos_inscricao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_inscricao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inscricao_id` int NOT NULL,
  `tipo` enum('matricula','pagamento') COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inscricao` (`inscricao_id`),
  CONSTRAINT `documentos_inscricao_ibfk_1` FOREIGN KEY (`inscricao_id`) REFERENCES `inscricoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inscricao` FOREIGN KEY (`inscricao_id`) REFERENCES `inscricoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_inscricao`
--

LOCK TABLES `documentos_inscricao` WRITE;
/*!40000 ALTER TABLE `documentos_inscricao` DISABLE KEYS */;
INSERT INTO `documentos_inscricao` VALUES (1,5,'matricula','uploads/comprovantes/matricula/doc_matricula_69807c444ce77.png','vivenciar_logov2.png','2026-02-02 07:28:20'),(2,5,'pagamento','uploads/comprovantes/pagamento/doc_pagamento_69807c4dbe9d7.jpg','WhatsApp-Image-2026-01-14-at-13.04.10.jpg','2026-02-02 07:28:29'),(5,8,'matricula','uploads/comprovantes/matricula/doc_matricula_6983673383047.jpg','1ebd.jpg','2026-02-04 12:35:15'),(6,9,'matricula','uploads/comprovantes/matricula/doc_matricula_69836a964f58e.jpg','channels4_profile.jpg','2026-02-04 12:49:42');
/*!40000 ALTER TABLE `documentos_inscricao` ENABLE KEYS */;
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
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instituicao` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  UNIQUE KEY `matricula` (`matricula`),
  UNIQUE KEY `cpf` (`cpf`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudantes`
--

LOCK TABLES `estudantes` WRITE;
/*!40000 ALTER TABLE `estudantes` DISABLE KEYS */;
INSERT INTO `estudantes` VALUES (8,'teste casa','2025-12-29','58998563255','RG','5689123','ssfs','uploads/fotos/estudante_697ac7616e6f2.jpeg','teste casa','teste casa','teste casa','teste casa','teste casa','Matriculado','dados_aprovados','eliabepaz.work@gmail.com','81988668870','2026-01-28 23:35:13',NULL),(10,'teste casa2','2026-01-16','56666566565','RG','9874656','kkak','uploads/fotos/estudante_697ac82fdd391.jpeg','teste casa2','teste casa2','teste casa2','teste casa2','teste casa2','Matriculado','dados_aprovados','eliabepaz.work@gmail.com','81988668870','2026-01-28 23:38:39',NULL),(14,'teste 04-02','2026-01-25','22235698565','RG','65669684','','uploads/fotos/estudante_698367337dff2.jpg','teste 04-02','teste 04-02','teste 04-02','teste 04-02','teste 04-02','Matriculado','pendente','eliabepaz.work@gmail.com','81988668870','2026-02-04 12:35:15',NULL),(15,'teste 04-02_novo','2024-01-29','12332112312','RG','56665565','aawsa','uploads/fotos/estudante_69836a964df64.jpeg','teste 04-02_novo','teste 04-02_novo','teste 04-02_novo','teste 04-02_novo','teste 04-02_novo','Matriculado','pendente','teste@teste.com','98688779944','2026-02-04 12:49:42',NULL);
/*!40000 ALTER TABLE `estudantes` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_estudante_insert` AFTER INSERT ON `estudantes` FOR EACH ROW BEGIN
    IF NEW.status_validacao = 'dados_aprovados' THEN
        INSERT INTO inscricoes (
            estudante_id,
            codigo_inscricao,
            data_validade,
            situacao
        ) VALUES (
            NEW.id,
            UUID(), -- ← Função nativa do MySQL, sempre disponível
            CONCAT(YEAR(CURDATE()) + 1, '-03-31'),
            'pagamento_pendente'
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

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
  `situacao` enum('aguardando_validacao','dados_aprovados','pagamento_pendente','documentos_anexados','pago','cie_emitida') COLLATE utf8mb4_unicode_ci DEFAULT 'aguardando_validacao',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cie_codigo` (`codigo_inscricao`),
  KEY `estudante_id` (`estudante_id`),
  CONSTRAINT `inscricoes_ibfk_1` FOREIGN KEY (`estudante_id`) REFERENCES `estudantes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscricoes`
--

LOCK TABLES `inscricoes` WRITE;
/*!40000 ALTER TABLE `inscricoes` DISABLE KEYS */;
INSERT INTO `inscricoes` VALUES (4,8,'2395db65-fcbb-11f0-9bad-0ccc47ea4795','2026-01-28','2027-03-31','pagamento_pendente','2026-01-28 23:35:13'),(5,10,'9ea36728-fcbb-11f0-9bad-0ccc47ea4795','2026-01-28','2027-03-31','pago','2026-01-28 23:38:39'),(8,14,'10520791-7d78-40ba-979b-b19cdccce7b6','2026-02-04','2027-03-31','pagamento_pendente','2026-02-04 12:35:15'),(9,15,'30834586-4e7b-44d6-9737-d8f7db5e77c6','2026-02-04','2027-03-31','pagamento_pendente','2026-02-04 12:49:42');
/*!40000 ALTER TABLE `inscricoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `acao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `registro_id` int DEFAULT NULL,
  `tabela` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,1,'excluiu_usuario','ID: 2',2,'usuarios','2025-12-18 07:38:17'),(2,1,'criou_usuario','Nome: bruce, Email: bruce@loses, Tipo: user',3,'usuarios','2025-12-18 07:38:53'),(3,1,'editou_usuario','ID: 3, Nome: bruces, Email: bruce@loses, Tipo: user',3,'usuarios','2025-12-18 07:39:25'),(4,1,'editou_estudante','ID: 1, Nome: Eliabe Guerreiro da Pazz, Matrícula: 201610040010',1,'estudantes','2025-12-18 07:43:34'),(5,1,'editou_estudante','ID: 1, Nome: Eliabe Guerreiro da Paz, Matrícula: 201610040010',1,'estudantes','2025-12-18 07:44:18'),(6,1,'criou_estudante','Nome: teste casa, Matrícula: 65989865, Curso: teste casa',2,'estudantes','2026-01-28 21:49:23'),(7,1,'excluiu_estudante','ID: 3, Nome: teste casa',3,'estudantes','2026-01-28 22:46:11'),(8,1,'excluiu_estudante','ID: 3, Nome: ',3,'estudantes','2026-01-28 23:20:37'),(9,1,'excluiu_estudante','ID: 5, Nome: teste casa',5,'estudantes','2026-01-28 23:20:47'),(10,1,'criou_estudante','Estudante: teste casa, Matrícula: teste casa',7,'estudantes','2026-01-28 23:22:05'),(11,1,'excluiu_estudante','ID: 5, Nome: ',5,'estudantes','2026-01-28 23:22:05'),(12,1,'criou_estudante','Estudante: teste casa2, Matrícula: teste casa2',10,'estudantes','2026-01-28 23:38:39'),(13,1,'confirmou_pagamento','Inscrição ID: 5',5,'inscricoes','2026-02-02 07:29:02'),(14,1,'excluiu_estudante','ID: 13, Nome: levy teste',13,'estudantes','2026-02-04 12:47:31'),(15,1,'excluiu_estudante','ID: 12, Nome: teste 02-02',12,'estudantes','2026-02-04 12:47:37');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'hants','hants@ciesytem.local','$2y$10$F4wcNlLHQyyn5Zj1ReSGE.aPtq.W2yzuIVdO/sMnjQG/qWjUZOWF.','admin','2025-12-17 19:13:52');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'ciesytem'
--

--
-- Dumping routines for database 'ciesytem'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-04 12:54:55
