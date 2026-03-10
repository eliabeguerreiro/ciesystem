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
<html>
<head>
    <meta charset="UTF-8">
    <title>Inscrição para CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { color: #1976d2; text-align: center; }
        .mensagem { padding: 10px; margin: 15px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9em; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 10px; }
        button:hover { background: #1565c0; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .debug-info { background: #fff3e0; color: #e65100; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Inscrição para Carteira Estudantil (CIE)</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso">
                <?= htmlspecialchars($sucesso) ?><br>
                <strong>Seu código de inscrição:</strong> <?= htmlspecialchars($codigoGerado) ?><br>
                <small>Guarde este código para acompanhar seu status.</small>
            </div>
            <a href="acompanhar.php">Acompanhar minha inscrição</a>
        <?php else: ?>
            <?php if ($erro): ?>
                <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <!-- ✅ Debug info em desenvolvimento -->
            <?php if (ini_get('display_errors') == 1): ?>
                <div class="debug-info">
                    <strong>Debug:</strong> 
                    PHP Version: <?= phpversion() ?> | 
                    Upload Max: <?= ini_get('upload_max_filesize') ?> | 
                    Post Max: <?= ini_get('post_max_size') ?> |
                    Upload Dir: <?= is_writable(__DIR__ . '/uploads/') ? 'OK' : 'SEM PERMISSÃO' ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Dados Civis -->
                <h3>Dados Pessoais</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento *</label>
                        <input type="date" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>CPF *</label>
                        <input type="text" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Documento *</label>
                        <select name="documento_tipo" required>
                            <option value="RG" <?= ($_POST['documento_tipo'] ?? '') === 'RG' ? 'selected' : '' ?>>RG</option>
                            <option value="CNH" <?= ($_POST['documento_tipo'] ?? '') === 'CNH' ? 'selected' : '' ?>>CNH</option>
                            <option value="PASSAPORTE" <?= ($_POST['documento_tipo'] ?? '') === 'PASSAPORTE' ? 'selected' : '' ?>>Passaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número do Documento *</label>
                        <input type="text" name="documento_numero" value="<?= htmlspecialchars($_POST['documento_numero'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Órgão Expedidor</label>
                        <input type="text" name="documento_orgao" value="<?= htmlspecialchars($_POST['documento_orgao'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Foto 3x4 (JPG, PNG) (Opcional)</label>
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png">
                    </div>
                </div>

                <!-- Documentos de Identidade -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Documento de Identidade - Frente *</label>
                        <input type="file" name="doc_identidade_frente" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="form-group">
                        <label>Documento de Identidade - Verso *</label>
                        <input type="file" name="doc_identidade_verso" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                </div>

                <!-- Dados Acadêmicos -->
                <h3>Dados Acadêmicos</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Instituição *</label>
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
                    <div class="form-group">
                        <label>Campus</label>
                        <input type="text" name="campus" value="<?= htmlspecialchars($_POST['campus'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Curso *</label>
                        <input type="text" name="curso" value="<?= htmlspecialchars($_POST['curso'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nível *</label>
                        <input type="text" name="nivel" value="<?= htmlspecialchars($_POST['nivel'] ?? '') ?>" placeholder="Ex: Técnico, Superior" required>
                    </div>
                    <div class="form-group">
                        <label>Matrícula *</label>
                        <input type="text" name="matricula" value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Situação Acadêmica</label>
                    <select name="situacao_academica">
                        <option value="Matriculado" <?= ($_POST['situacao_academica'] ?? 'Matriculado') === 'Matriculado' ? 'selected' : '' ?>>Matriculado</option>
                        <option value="Trancado" <?= ($_POST['situacao_academica'] ?? '') === 'Trancado' ? 'selected' : '' ?>>Trancado</option>
                        <option value="Formado" <?= ($_POST['situacao_academica'] ?? '') === 'Formado' ? 'selected' : '' ?>>Formado</option>
                    </select>
                </div>

                <!-- Contato -->
                <h3>Contato (Opcional)</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <!-- Comprovante de Matrícula -->
                <h3>Comprovante de Matrícula *</h3>
                <div class="form-group">
                    <label>Anexe seu comprovante (JPG, PNG ou PDF)</label>
                    <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>

                <button type="submit">Enviar Inscrição</button>
            </form>
            <a href="index.php">← Voltar</a>
        <?php endif; ?>
    </div>
</body>
</html>