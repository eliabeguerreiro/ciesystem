<?php
// === DEBUG - HABILITAR ERROS EM TELA ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ========================================

// Sem sessão, sem autenticação — acesso público
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Estudante.php';
require_once __DIR__ . '/app/models/Inscricao.php';
require_once __DIR__ . '/app/controllers/EstudanteController.php';
require_once __DIR__ . '/app/models/DocumentoEstudante.php'; 
require_once __DIR__ . '/app/models/Instituicao.php';
require_once __DIR__ . '/app/models/Log.php';

$database = new Database();
$db = $database->getConnection();

// ✅ Verificar conexão com banco
if (!$db) {
    die("Erro: Não foi possível conectar ao banco de dados.");
}

$estudanteModel = new Estudante($db);
$inscricaoModel = new Inscricao($db);
$docIdentidadeModel = new DocumentoEstudante($db); 
$instituicaoModel = new Instituicao($db); 
$estudanteController = new EstudanteController($db); 

$erro = '';
$sucesso = '';
$codigoGerado = '';

// ================================
// PROCESSAMENTO DO FORMULÁRIO
// ================================
if ($_POST) {
    
    // ✅ Log do que está chegando
    error_log("=== INÍCIO DO POST ===");
    error_log("POST: " . print_r($_POST, true));
    error_log("FILES: " . print_r($_FILES, true));
    error_log("=== FIM DO POST ===");
    
    $camposObrigatorios = ['nome', 'data_nascimento', 'cpf', 'documento_tipo', 'documento_numero',
                          'instituicao_id', 'curso', 'nivel', 'matricula'];
    foreach ($camposObrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            $erro = "O campo '$campo' é obrigatório.";
            error_log("ERRO: Campo obrigatório vazio: $campo");
            break;
        }
    }

    // Verifica se comprovante de matrícula foi enviado
    if (empty($erro) && empty($_FILES['comprovante_matricula']['name'])) {
        $erro = "Comprovante de matrícula é obrigatório.";
        error_log("ERRO: Comprovante de matrícula não enviado");
    }

    // Verifica se documentos de identidade (frente e verso) e o tipo foram enviados
    $tipoDocIdentidade = $_POST['documento_tipo'] ?? null;
    $tipoDocIdentidade = strtolower($tipoDocIdentidade);
    $docFrente = $_FILES['doc_identidade_frente'] ?? null;
    $docVerso = $_FILES['doc_identidade_verso'] ?? null;

    if (empty($erro) && (!$tipoDocIdentidade || empty($docFrente['name']) || empty($docVerso['name']))) {
         $erro = "É obrigatório selecionar o tipo e anexar ambos os arquivos: Frente e Verso do Documento de Identificação.";
         error_log("ERRO: Documentos de identidade incompletos");
    }

    // Validação do tipo de documento
    if (empty($erro)) {
        $tiposValidos = ['rg', 'cnh', 'passaporte', 'cpf'];
        if ($tipoDocIdentidade && !in_array(strtolower($tipoDocIdentidade), $tiposValidos)) {
             $erro = "Tipo de documento de identidade inválido.";
             error_log("ERRO: Tipo de documento inválido: $tipoDocIdentidade");
        }
    }

    // ✅ Validação da foto 3x4 antes do processamento
    if (empty($erro) && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && !empty($_FILES['foto']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $erro = "Formato de foto inválido. Use JPG ou PNG.";
            error_log("ERRO: Formato de foto inválido: $ext");
        }
    }

    // ✅ Verificar erros de upload
    if (empty($erro)) {
        $arquivosUpload = ['comprovante_matricula', 'doc_identidade_frente', 'doc_identidade_verso', 'foto'];
        foreach ($arquivosUpload as $arq) {
            if (isset($_FILES[$arq]) && $_FILES[$arq]['error'] !== UPLOAD_ERR_OK && $_FILES[$arq]['error'] !== UPLOAD_ERR_NO_FILE) {
                $erro = "Erro no upload do arquivo '$arq'. Código: " . $_FILES[$arq]['error'];
                error_log("ERRO UPLOAD: $arq - Código: " . $_FILES[$arq]['error']);
                break;
            }
        }
    }

    if (empty($erro)) {
        try {
            // Verifica se CPF ou matrícula já existem
            $stmt = $db->prepare("SELECT id FROM estudantes WHERE cpf = ? OR matricula = ?");
            $stmt->execute([$_POST['cpf'], $_POST['matricula']]);
            if ($stmt->fetch()) {
                $erro = "CPF ou matrícula já cadastrados. Entre em contato com a administração.";
                error_log("ERRO: CPF ou matrícula duplicados");
            } else {
                
                // Cria estudante
                $estudante = new Estudante($db);
                $estudante->nome = $_POST['nome'];
                $estudante->data_nascimento = $_POST['data_nascimento'];
                $estudante->cpf = $_POST['cpf'];
                $estudante->documento_tipo = $_POST['documento_tipo'];
                $estudante->documento_numero = $_POST['documento_numero'];
                $estudante->documento_orgao = $_POST['documento_orgao'] ?? '';
                $estudante->instituicao_id = (int)($_POST['instituicao_id'] ?? 0);
                $estudante->campus = $_POST['campus'] ?? '';
                $estudante->curso = $_POST['curso'];
                $estudante->nivel = $_POST['nivel'];
                $estudante->matricula = $_POST['matricula'];
                $estudante->situacao_academica = $_POST['situacao_academica'] ?? 'Matriculado';
                $estudante->email = $_POST['email'] ?? '';
                $estudante->telefone = $_POST['telefone'] ?? '';
                $estudante->status_validacao = 'pendente';

                if ($estudante->criar()) {
                    $estudanteId = $db->lastInsertId();
                    error_log("Estudante criado com ID: $estudanteId");

                    // ✅ Verificar se ID foi gerado
                    if (!$estudanteId || $estudanteId <= 0) {
                        $erro = "Erro: Não foi possível gerar ID do estudante.";
                        error_log("ERRO: lastInsertId retornou: $estudanteId");
                    } else {
                        
                        // Cria inscrição
                        $inscricao = new Inscricao($db);
                        $inscricao->estudante_id = $estudanteId;
                        $inscricao->origem = 'estudante';
                        
                        if (!$inscricao->criar()) {
                            $erro = "Erro ao criar inscrição.";
                            error_log("ERRO: Falha ao criar inscrição");
                        }

                        if (empty($erro)) {
                            // Obter ID da inscrição
                            $stmt = $db->prepare("SELECT id, codigo_inscricao FROM inscricoes WHERE estudante_id = ? ORDER BY id DESC LIMIT 1");
                            $stmt->execute([$estudanteId]);
                            $resultado = $stmt->fetch();
                            if ($resultado) {
                                $inscricaoId = $resultado['id'];
                                $codigoGerado = $resultado['codigo_inscricao'];
                                error_log("Inscrição criada com ID: $inscricaoId, Código: $codigoGerado");

                                // ✅ Verificar/Criar pasta de uploads
                                $uploadDir = __DIR__ . '/uploads/';
                                if (!is_dir($uploadDir)) {
                                    if (!mkdir($uploadDir, 0755, true)) {
                                        $erro = "Erro: Não foi possível criar pasta de uploads.";
                                        error_log("ERRO: Falha ao criar pasta uploads: $uploadDir");
                                    }
                                }
                                
                                if (empty($erro) && !is_writable($uploadDir)) {
                                    $erro = "Erro: Pasta de uploads sem permissão de escrita.";
                                    error_log("ERRO: Pasta uploads sem permissão: $uploadDir");
                                }

                                // Salvar Foto 3x4
                                if (empty($erro) && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && !empty($_FILES['foto']['name'])) {
                                    if (!$docIdentidadeModel->salvarUnicoArquivo($estudanteId, $_FILES['foto'], 'foto_3x4', 'pendente')) {
                                        $erro = "Erro ao salvar a foto 3x4.";
                                        error_log("ERRO: Falha ao salvar foto 3x4");
                                    }
                                }

                                // Salvar comprovante de matrícula
                                if (empty($erro) && !empty($_FILES['comprovante_matricula']['name'])) {
                                    $inscTemp = new Inscricao($db);
                                    $inscTemp->id = $inscricaoId;
                                    if (!$inscTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                                        $erro = "Erro ao salvar comprovante de matrícula.";
                                        error_log("ERRO: Falha ao salvar comprovante de matrícula");
                                    }
                                }

                                // Salvar documentos de identidade (frente e verso) - OBRIGATÓRIO
                                if (empty($erro) && $tipoDocIdentidade && $docFrente && $docVerso) {
                                    if (!$docIdentidadeModel->salvarFrenteVerso($estudanteId, $docFrente, $docVerso, $tipoDocIdentidade, 'pendente')) {
                                        $erro = "Erro ao salvar os documentos de identidade (Frente e Verso).";
                                        error_log("ERRO: Falha ao salvar documentos de identidade");
                                    } else {
                                        $log = new Log($db);
                                        $log->registrar(
                                            null,
                                            'inscricao_publica_realizada',
                                            "Estudante: {$estudante->nome}, CPF: {$estudante->cpf}, Código Inscrição: {$codigoGerado}",
                                            $inscricaoId,
                                            'inscricoes'
                                        );
                                        $sucesso = "Inscrição realizada com sucesso!";
                                        error_log("SUCESSO: Inscrição realizada - ID: $inscricaoId, Código: $codigoGerado");
                                    }
                                } elseif (empty($erro)) {
                                    $erro = "Erro interno: Documentos de identidade não fornecidos.";
                                    error_log("ERRO: Documentos de identidade não fornecidos");
                                }

                            } else {
                                $erro = "Erro ao gerar inscrição.";
                                error_log("ERRO: Não foi possível obter dados da inscrição criada");
                            }
                        }
                    }
                } else {
                    $erro = "Erro ao cadastrar seus dados.";
                    error_log("ERRO: Método criar() do Estudante retornou false");
                }
            }
        } catch (Exception $e) {
            $erro = "Erro interno: " . $e->getMessage();
            error_log("EXCEÇÃO: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição para CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --success-color: #2e7d32;
            --error-color: #c62828;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-text: #666;
            --border-color: #ddd;
            --input-focus: rgba(25, 118, 210, 0.2);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .main-container {
            max-width: 900px;
            margin: 40px auto;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .header h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .content {
            padding: 40px;
        }

        /* Mensagens */
        .mensagem {
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            border-left: 5px solid;
            animation: fadeIn 0.5s ease-in-out;
        }

        .erro {
            background-color: #ffebee;
            color: var(--error-color);
            border-left-color: var(--error-color);
        }

        .sucesso {
            background-color: #e8f5e9;
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .debug-info {
            background: #fff3e0;
            color: #e65100;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 0.85em;
            border: 1px solid #ffe0b2;
        }

        /* Seções do Formulário */
        .form-section {
            margin-bottom: 40px;
            position: relative;
        }

        .section-title {
            display: flex;
            align-items: center;
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-icon {
            margin-right: 12px;
            font-size: 1.6rem;
        }

        /* Grid do Formulário */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        label span.required {
            color: var(--error-color);
            margin-left: 3px;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px var(--input-focus);
            background-color: #fff;
        }

        input[type="file"] {
            padding: 10px;
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            background: #fafafa;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }

        input[type="file"]:hover {
            border-color: var(--primary-color);
            background: #f0f7ff;
        }

        /* Botão */
        .btn-submit {
            display: block;
            width: 100%;
            padding: 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(25, 118, 210, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 118, 210, 0.3);
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .success-actions {
            text-align: center;
            margin-top: 30px;
        }

        .btn-success-action {
            display: inline-block;
            padding: 12px 30px;
            background: var(--success-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-success-action:hover {
            background: #1b5e20;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsividade */
        @media (max-width: 600px) {
            .content { padding: 20px; }
            .header { padding: 30px 15px; }
            .header h2 { font-size: 1.6rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="header">
            <h2>Solicitação de Carteira Estudantil</h2>
            <p>Preencha os dados abaixo para iniciar sua inscrição na CIE</p>
        </div>

        <div class="content">
            <?php if ($sucesso): ?>
                <div class="mensagem sucesso">
                    <h3 style="margin-bottom: 10px;">✅ Inscrição Realizada!</h3>
                    <?= htmlspecialchars($sucesso) ?><br><br>
                    <strong>Seu código de inscrição:</strong> <span style="font-size: 1.2em; background: #fff; padding: 5px 10px; border-radius: 4px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($codigoGerado) ?></span><br>
                    <small style="display:block; margin-top: 10px; color: #555;">Guarde este código com segurança. Você precisará dele para acompanhar o status.</small>
                </div>
                <div class="success-actions">
                    <a href="acompanhar.php" class="btn-success-action">Acompanhar minha inscrição</a>
                </div>
            <?php else: ?>
                
                <?php if ($erro): ?>
                    <div class="mensagem erro">
                        <strong>⚠️ Atenção:</strong> <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <!-- ✅ Debug info em desenvolvimento -->
                <?php if (ini_get('display_errors') == 1): ?>
                    <div class="debug-info">
                        <strong>🛠️ Modo Desenvolvedor:</strong> 
                        PHP: <?= phpversion() ?> | 
                        Upload Max: <?= ini_get('upload_max_filesize') ?> | 
                        Post Max: <?= ini_get('post_max_size') ?> |
                        Pasta Uploads: <?= is_writable(__DIR__ . '/uploads/') ? '<span style="color:green">OK</span>' : '<span style="color:red">SEM PERMISSÃO</span>' ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- Seção 1: Dados Pessoais -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="section-icon">👤</span> Dados Pessoais
                        </h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>Nome Completo <span class="required">*</span></label>
                                <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" placeholder="Digite seu nome completo como no documento" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Data de Nascimento <span class="required">*</span></label>
                                <input type="date" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>CPF <span class="required">*</span></label>
                                <input type="text" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                            </div>
                            <div class="form-group">
                                <label>Foto 3x4 (Opcional)</label>
                                <input type="file" name="foto" accept=".jpg,.jpeg,.png">
                                <small style="color: #888; font-size: 0.8em; margin-top: 4px;">Formatos: JPG, PNG</small>
                            </div>
                        </div>
                    </div>

                    <!-- Seção 2: Documentação -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="section-icon">📄</span> Documentação
                        </h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tipo de Documento <span class="required">*</span></label>
                                <select name="documento_tipo" required>
                                    <option value="">Selecione...</option>
                                    <option value="RG" <?= ($_POST['documento_tipo'] ?? '') === 'RG' ? 'selected' : '' ?>>RG (Carteira de Identidade)</option>
                                    <option value="CNH" <?= ($_POST['documento_tipo'] ?? '') === 'CNH' ? 'selected' : '' ?>>CNH (Carteira de Habilitação)</option>
                                    <option value="PASSAPORTE" <?= ($_POST['documento_tipo'] ?? '') === 'PASSAPORTE' ? 'selected' : '' ?>>Passaporte</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Número do Documento <span class="required">*</span></label>
                                <input type="text" name="documento_numero" value="<?= htmlspecialchars($_POST['documento_numero'] ?? '') ?>" placeholder="Ex: 12.345.678-9" required>
                            </div>
                            <div class="form-group">
                                <label>Órgão Expedidor</label>
                                <input type="text" name="documento_orgao" value="<?= htmlspecialchars($_POST['documento_orgao'] ?? '') ?>" placeholder="Ex: SSP/SP, DETRAN">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Documento (Frente) <span class="required">*</span></label>
                                <input type="file" name="doc_identidade_frente" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small style="color: #888; font-size: 0.8em; margin-top: 4px;">Foto nítida do frente do documento</small>
                            </div>
                            <div class="form-group">
                                <label>Documento (Verso) <span class="required">*</span></label>
                                <input type="file" name="doc_identidade_verso" accept=".jpg,.jpeg,.png,.pdf" required>
                                <small style="color: #888; font-size: 0.8em; margin-top: 4px;">Foto nítida do verso do documento</small>
                            </div>
                        </div>
                    </div>

                    <!-- Seção 3: Dados Acadêmicos -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="section-icon">🎓</span> Dados Acadêmicos
                        </h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>Instituição de Ensino <span class="required">*</span></label>
                                <select name="instituicao_id" required>
                                    <option value="">Selecione sua instituição...</option>
                                    <?php
                                    $instituicoesAtivas = $instituicaoModel->listarAtivas();
                                    foreach ($instituicoesAtivas as $inst): ?>
                                        <option value="<?= $inst['id'] ?>" <?= ($_POST['instituicao_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($inst['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Campus</label>
                                <input type="text" name="campus" value="<?= htmlspecialchars($_POST['campus'] ?? '') ?>" placeholder="Ex: Unidade Central">
                            </div>
                            <div class="form-group">
                                <label>Curso <span class="required">*</span></label>
                                <input type="text" name="curso" value="<?= htmlspecialchars($_POST['curso'] ?? '') ?>" placeholder="Ex: Engenharia Civil" required>
                            </div>
                            <div class="form-group">
                                <label>Nível <span class="required">*</span></label>
                                <input type="text" name="nivel" value="<?= htmlspecialchars($_POST['nivel'] ?? '') ?>" placeholder="Ex: Superior, Técnico" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nº Matrícula <span class="required">*</span></label>
                                <input type="text" name="matricula" value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Situação Acadêmica</label>
                                <select name="situacao_academica">
                                    <option value="Matriculado" <?= ($_POST['situacao_academica'] ?? 'Matriculado') === 'Matriculado' ? 'selected' : '' ?>>Matriculado</option>
                                    <option value="Trancado" <?= ($_POST['situacao_academica'] ?? '') === 'Trancado' ? 'selected' : '' ?>>Trancado</option>
                                    <option value="Formado" <?= ($_POST['situacao_academica'] ?? '') === 'Formado' ? 'selected' : '' ?>>Formado</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width" style="margin-top: 20px; border: 2px dashed #ddd; padding: 20px; border-radius: 8px; background: #fafafa;">
                            <label style="color: var(--primary-color); font-weight: 700;">Comprovante de Matrícula <span class="required">*</span></label>
                            <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf" required style="margin-top: 10px;">
                            <small style="color: #666; display: block; margin-top: 8px;">Anexe uma foto ou PDF do seu comprovante de matrícula atualizado.</small>
                        </div>
                    </div>

                    <!-- Seção 4: Contato -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="section-icon">📞</span> Contato (Opcional)
                        </h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>E-mail</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="seu@email.com">
                            </div>
                            <div class="form-group">
                                <label>Telefone / WhatsApp</label>
                                <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Enviar Solicitação de Inscrição</button>
                </form>

                <div style="text-align: center;">
                    <a href="index.php" class="back-link">← Voltar para a página inicial</a>
                </div>

            <?php endif; ?>
        </div>
    </div>

</body>
</html>