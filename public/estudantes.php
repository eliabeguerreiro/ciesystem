<?php
session_start();
// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/Estudante.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/controllers/EstudanteController.php';
require_once __DIR__ . '/../app/models/DocumentoEstudante.php';
require_once __DIR__ . '/../app/models/Instituicao.php';

$database = new Database();
$db = $database->getConnection();
$estudanteCtrl = new EstudanteController($db);
$estudante = new Estudante($db);
$inscricaoModel = new Inscricao($db);
$docIdentidadeModel = new DocumentoEstudante($db);
$instituicaoModel = new Instituicao($db);

$erro = '';
$sucesso = '';

// ================================
// TRATAMENTO DE FORMULÁRIO (CADASTRO/EDIÇÃO)
// ================================
if ($_POST) {
    // Sanitiza dados simples
    $estudante->nome = $_POST['nome'] ?? '';
    $estudante->data_nascimento = $_POST['data_nascimento'] ?? '';
    $estudante->cpf = $_POST['cpf'] ?? '';
    $estudante->documento_tipo = $_POST['documento_tipo'] ?? 'RG';
    $estudante->documento_numero = $_POST['documento_numero'] ?? '';
    $estudante->documento_orgao = $_POST['documento_orgao'] ?? '';
    $estudante->instituicao_id = (int)($_POST['instituicao_id'] ?? 0);
    $estudante->campus = $_POST['campus'] ?? '';
    $estudante->curso = $_POST['curso'] ?? '';
    $estudante->nivel = $_POST['nivel'] ?? '';
    $estudante->matricula = $_POST['matricula'] ?? '';
    $estudante->situacao_academica = $_POST['situacao_academica'] ?? 'Matriculado';
    $estudante->status_validacao = $_POST['status_validacao'] ?? 'dados_aprovados'; // ← PADRÃO: dados_aprovados
    $estudante->email = $_POST['email'] ?? '';
    $estudante->telefone = $_POST['telefone'] ?? '';

    // ✅ CORREÇÃO: Validação da foto 3x4 (sem upload ainda)
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $erro = "Formato de foto inválido. Use JPG ou PNG.";
        }
    }

    // Processar Documentos de Identidade (Frente e Verso)
    $tipoDocIdentidade = $_POST['documento_tipo'] ?? null;
    $tipoDocIdentidade = strtolower($tipoDocIdentidade);
    $docFrente = $_FILES['doc_identidade_frente'] ?? null;
    $docVerso = $_FILES['doc_identidade_verso'] ?? null;

    // Validação para cadastro e edição
    if (!isset($_POST['id'])) { // Cadastro novo
        if ($tipoDocIdentidade && (!$docFrente || empty($docFrente['name']) || !$docVerso || empty($docVerso['name']))) {
            $erro = "Para o cadastro com documento de identidade, é necessário anexar ambos os arquivos: Frente e Verso.";
        }
        if ($tipoDocIdentidade && (!empty($docFrente['name']) && !empty($docVerso['name']))) {
            $tiposValidos = ['rg', 'cnh', 'passaporte', 'cpf'];
            if (!in_array(strtolower($tipoDocIdentidade), $tiposValidos)) {
                $erro = "Tipo de documento de identidade inválido.";
            }
        }
    } else { // Edição
        if ($tipoDocIdentidade && (!empty($docFrente['name']) || !empty($docVerso['name']))) {
            $tiposValidos = ['rg', 'cnh', 'passaporte', 'cpf'];
            if (!in_array(strtolower($tipoDocIdentidade), $tiposValidos)) {
                $erro = "Tipo de documento de identidade inválido.";
            } elseif (empty($docFrente['name']) || empty($docVerso['name'])) {
                $erro = "Ao enviar documentos de identidade, ambos os arquivos (Frente e Verso) devem ser anexados.";
            }
        }
    }

    // ================================
    // EDIÇÃO
    // ================================
    if (isset($_POST['id'])) {
        $estudante->id = $_POST['id'];
        $registroAtual = $estudante->buscarPorId($estudante->id);

        // ✅ CORREÇÃO: Se foi feito upload de nova foto → deleta a antiga da tabela documentos_anexados
        if (!empty($_FILES['foto']['name']) && empty($erro)) {
            // Buscar foto antiga para deletar
            $fotoAntiga = $docIdentidadeModel->buscarPorEstudanteETipo($estudante->id, 'foto_3x4');
            if (!empty($fotoAntiga)) {
                foreach ($fotoAntiga as $doc) {
                    $docIdentidadeModel->deletarArquivo($doc['caminho_arquivo']);
                    $docIdentidadeModel->deletarRegistroNovaTabela($doc['id']);
                }
            }
            // Salvar nova foto 3x4 como documento anexado
            if (!$docIdentidadeModel->salvarUnicoArquivo($estudante->id, $_FILES['foto'], 'foto_3x4', $estudante->status_validacao)) {
                $erro = "Erro ao salvar a foto 3x4 como documento anexado.";
            }
        }

        if (empty($erro)) {
            if ($estudante->atualizar()) {
                // --- Processar Documentos de Identidade na Edição ---
                if ($tipoDocIdentidade && !empty($docFrente['name']) && !empty($docVerso['name'])) {
                    // Deletar documentos antigos do mesmo tipo (frente e verso)
                    $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, $tipoDocIdentidade);
                    
                    // Obter o status_validacao atual do estudante para validação automática
                    $stmtStatus = $db->prepare("SELECT status_validacao FROM estudantes WHERE id = :estudante_id");
                    $stmtStatus->bindParam(':estudante_id', $estudante->id, PDO::PARAM_INT);
                    $stmtStatus->execute();
                    $statusAtualDoEstudante = $stmtStatus->fetch(PDO::FETCH_ASSOC)['status_validacao'] ?? 'pendente';

                    // Salvar os novos documentos (frente e verso)
                    if (!$docIdentidadeModel->salvarFrenteVerso($estudante->id, $docFrente, $docVerso, $tipoDocIdentidade, $statusAtualDoEstudante)) {
                        $erro = "Erro ao salvar os novos documentos de identidade (Frente e Verso).";
                    }
                }

                // --- Processar Comprovante de Matrícula na Edição ---
                if (!empty($_FILES['comprovante_matricula']['name'])) {
                    // Obter ID da inscrição associada ao estudante (mais recente)
                    $stmtInsc = $db->prepare("SELECT id FROM inscricoes WHERE estudante_id = :estudante_id ORDER BY id DESC LIMIT 1");
                    $stmtInsc->bindParam(':estudante_id', $estudante->id, PDO::PARAM_INT);
                    $stmtInsc->execute();
                    $inscricaoAssoc = $stmtInsc->fetch(PDO::FETCH_ASSOC);

                    if ($inscricaoAssoc) {
                        $inscricaoId = $inscricaoAssoc['id'];
                        $inscricaoTemp = new Inscricao($db);
                        $inscricaoTemp->id = $inscricaoId;
                        if ($inscricaoTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                            // --- LÓGICA DE VALIDAÇÃO AUTOMÁTICA NA EDIÇÃO ---
                            $dadosInscricao = $inscricaoTemp->buscarPorId($inscricaoTemp->id);
                            if ($dadosInscricao) {
                                $estudanteId = $dadosInscricao['estudante_id'];
                                $origemInscricao = $dadosInscricao['origem'];
                                $stmtEstudante = $db->prepare("SELECT status_validacao FROM estudantes WHERE id = :estudante_id");
                                $stmtEstudante->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
                                $stmtEstudante->execute();
                                $dadosEstudante = $stmtEstudante->fetch(PDO::FETCH_ASSOC);

                                if ($dadosEstudante && $dadosEstudante['status_validacao'] === 'dados_aprovados' && $origemInscricao === 'administrador') {
                                    if ($inscricaoTemp->atualizarMatriculaValidada(true)) {
                                        $sucesso = "Comprovante de matrícula anexado e validado automaticamente (origem admin, status aprovado).";
                                        require_once __DIR__ . '/../app/models/Log.php';
                                        $log = new Log($db);
                                        $log->registrar($_SESSION['user_id'], 'anexou_e_validou_comprovante_matricula_admin', "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}", $inscricaoTemp->id, 'inscricoes');
                                    } else {
                                        $sucesso = "Comprovante de matrícula anexado, mas falha ao validar automaticamente.";
                                    }
                                } else {
                                    $sucesso = "Comprovante de matrícula anexado. Aguardando validação (status do estudante ou origem não permitem validação automática).";
                                    require_once __DIR__ . '/../app/models/Log.php';
                                    $log = new Log($db);
                                    $log->registrar($_SESSION['user_id'], 'anexou_comprovante_matricula', "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}, Status: " . ($dadosEstudante['status_validacao'] ?? 'Desconhecido'), $inscricaoTemp->id, 'inscricoes');
                                }
                            } else {
                                $sucesso = "Comprovante de matrícula anexado (falha na busca de dados da inscrição/estudante).";
                                require_once __DIR__ . '/../app/models/Log.php';
                                $log = new Log($db);
                                $log->registrar($_SESSION['user_id'], 'anexou_comprovante_matricula', "Inscrição ID: {$inscricaoTemp->id} (Erro: dados do estudante não encontrados)", $inscricaoTemp->id, 'inscricoes');
                            }
                            // === FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
                        } else {
                            $erro = "Erro ao salvar o comprovante de matrícula.";
                        }
                    } else {
                        $erro = "Erro: Não foi encontrada uma inscrição associada para anexar o comprovante de matrícula.";
                    }
                }
                // --- FIM Processar Comprovante de Matrícula na Edição ---

                if (empty($erro)) {
                    require_once __DIR__ . '/../app/models/Log.php';
                    $log = new Log($db);
                    $log->registrar($_SESSION['user_id'], 'editou_estudante', "ID: {$estudante->id}, Nome: {$estudante->nome}, Matrícula: {$estudante->matricula}", $estudante->id, 'estudantes');
                    $sucesso = "Estudante atualizado com sucesso!";
                }
            } else {
                $erro = "Erro ao atualizar estudante.";
            }
        }
    }
    // ================================
    // CADASTRO (COM CRIAÇÃO MANUAL DE INSCRIÇÃO)
    // ================================
    else {
        if (empty($erro)) {
            if ($estudante->criar()) {
                $novoEstudanteId = $db->lastInsertId();
                
                // === CRIAR INSCRIÇÃO AUTOMATICAMENTE ===
                $inscricao = new Inscricao($db);
                $inscricao->estudante_id = $novoEstudanteId;
                $inscricao->origem = 'administrador';
                if ($inscricao->criar()) {
                    $idInscricaoRecemCriada = $db->lastInsertId();

                    // ✅ CORREÇÃO: Salvar foto 3x4 como documento anexado
                    if (!empty($_FILES['foto']['name']) && empty($erro)) {
                        if (!$docIdentidadeModel->salvarUnicoArquivo($novoEstudanteId, $_FILES['foto'], 'foto_3x4', $estudante->status_validacao)) {
                            $erro = "Erro ao salvar a foto 3x4 como documento anexado.";
                        }
                    }

                    // === SALVAR DOCUMENTOS DE IDENTIDADE ===
                    if ($tipoDocIdentidade && $docFrente && $docVerso) {
                        $statusDoNovoEstudante = $estudante->status_validacao;
                        if (!$docIdentidadeModel->salvarFrenteVerso($novoEstudanteId, $docFrente, $docVerso, $tipoDocIdentidade, $statusDoNovoEstudante)) {
                            $erro = "Erro ao salvar os documentos de identidade (Frente e Verso).";
                        } else {
                            // --- NOVO: Processar Comprovante de Matrícula no Cadastro ---
                            if (!empty($_FILES['comprovante_matricula']['name'])) {
                                $inscricaoTemp = new Inscricao($db);
                                $inscricaoTemp->id = $idInscricaoRecemCriada;
                                if ($inscricaoTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                                    // --- LÓGICA DE VALIDAÇÃO AUTOMÁTICA NO CADASTRO ---
                                    $dadosInscricao = $inscricaoTemp->buscarPorId($inscricaoTemp->id);
                                    if ($dadosInscricao) {
                                        $estudanteId = $dadosInscricao['estudante_id'];
                                        $origemInscricao = $dadosInscricao['origem'];
                                        $stmtEstudante = $db->prepare("SELECT status_validacao FROM estudantes WHERE id = :estudante_id");
                                        $stmtEstudante->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
                                        $stmtEstudante->execute();
                                        $dadosEstudante = $stmtEstudante->fetch(PDO::FETCH_ASSOC);

                                        if ($dadosEstudante && $dadosEstudante['status_validacao'] === 'dados_aprovados' && $origemInscricao === 'administrador') {
                                            if ($inscricaoTemp->atualizarMatriculaValidada(true)) {
                                                $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado e validado automaticamente (origem admin, status aprovado).";
                                                require_once __DIR__ . '/../app/models/Log.php';
                                                $log = new Log($db);
                                                $log->registrar($_SESSION['user_id'], 'anexou_e_validou_comprovante_matricula_admin', "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}", $inscricaoTemp->id, 'inscricoes');
                                            } else {
                                                $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado, mas falha ao validar automaticamente.";
                                            }
                                        } else {
                                            $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado. Aguardando validação.";
                                            require_once __DIR__ . '/../app/models/Log.php';
                                            $log = new Log($db);
                                            $log->registrar($_SESSION['user_id'], 'anexou_comprovante_matricula', "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}, Status: " . ($dadosEstudante['status_validacao'] ?? 'Desconhecido'), $inscricaoTemp->id, 'inscricoes');
                                        }
                                    } else {
                                        $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado (falha na busca de dados).";
                                        require_once __DIR__ . '/../app/models/Log.php';
                                        $log = new Log($db);
                                        $log->registrar($_SESSION['user_id'], 'anexou_comprovante_matricula', "Inscrição ID: {$inscricaoTemp->id} (Erro: dados não encontrados)", $inscricaoTemp->id, 'inscricoes');
                                    }
                                    // === FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
                                } else {
                                    $erro = "Erro ao salvar o comprovante de matrícula.";
                                }
                            } else {
                                $sucesso = "Estudante cadastrado com sucesso!";
                                require_once __DIR__ . '/../app/models/Log.php';
                                $log = new Log($db);
                                $log->registrar($_SESSION['user_id'], 'criou_estudante_admin', "Estudante: {$estudante->nome}, Matrícula: {$estudante->matricula}", $novoEstudanteId, 'estudantes');
                                foreach ($_POST as $key => $value) $_POST[$key] = '';
                            }
                            // --- FIM NOVO ---
                        }
                    } else {
                         // Caso sem docs de identidade mas com matrícula
                         if (!empty($_FILES['comprovante_matricula']['name'])) {
                            $inscricaoTemp = new Inscricao($db);
                            $inscricaoTemp->id = $idInscricaoRecemCriada;
                            if ($inscricaoTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                                // ... (mesma lógica de validação automática acima) ...
                                $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado.";
                                require_once __DIR__ . '/../app/models/Log.php';
                                $log = new Log($db);
                                $log->registrar($_SESSION['user_id'], 'anexou_comprovante_matricula', "Inscrição ID: {$inscricaoTemp->id}", $inscricaoTemp->id, 'inscricoes');
                            } else {
                                $erro = "Erro ao salvar o comprovante de matrícula.";
                            }
                         } else {
                            $sucesso = "Estudante cadastrado com sucesso!";
                            require_once __DIR__ . '/../app/models/Log.php';
                            $log = new Log($db);
                            $log->registrar($_SESSION['user_id'], 'criou_estudante_admin', "Estudante: {$estudante->nome}, Matrícula: {$estudante->matricula}", $novoEstudanteId, 'estudantes');
                            foreach ($_POST as $key => $value) $_POST[$key] = '';
                         }
                    }
                } else {
                    $erro = "Erro ao criar inscrição para o estudante.";
                }
                // === FIM CRIAR INSCRIÇÃO AUTOMATICAMENTE ===
            } else {
                $erro = "Erro ao cadastrar estudante. Verifique se a matrícula ou CPF já existem.";
            }
        }
    }
}

// ================================
// EXCLUSÃO
// ================================
if (isset($_GET['deletar'])) {
    $estudante->id = (int)$_GET['deletar'];
    $registro = $estudante->buscarPorId($estudante->id);
    if ($estudante->deletar()) {
        // ✅ CORREÇÃO: Deleta a foto 3x4 associada
        $fotoDocs = $docIdentidadeModel->buscarPorEstudanteETipo($estudante->id, 'foto_3x4');
        if (!empty($fotoDocs)) {
            foreach ($fotoDocs as $doc) {
                $docIdentidadeModel->deletarArquivo($doc['caminho_arquivo']);
                $docIdentidadeModel->deletarRegistroNovaTabela($doc['id']);
            }
        }
        // Deleta TODOS os documentos de identidade associados
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'rg');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'cnh');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'passaporte');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'cpf');
        
        require_once __DIR__ . '/../app/models/Log.php';
        $log = new Log($db);
        $log->registrar($_SESSION['user_id'], 'excluiu_estudante', "ID: {$estudante->id}, Nome: {$registro['nome']}", $estudante->id, 'estudantes');
        $sucesso = "Estudante excluído com sucesso.";
    } else {
        $erro = "Erro ao excluir estudante.";
    }
}

// ================================
// EDIÇÃO (carrega dados)
// ================================
$editar = null;
if (isset($_GET['editar'])) {
    $editar = $estudante->buscarPorId((int)$_GET['editar']);
    // Carregar documentos de identidade para edição
    $docsRG = $docIdentidadeModel->buscarPorEstudanteETipo($editar['id'], 'rg');
    $docsCNH = $docIdentidadeModel->buscarPorEstudanteETipo($editar['id'], 'cnh');
    $docsPassaporte = $docIdentidadeModel->buscarPorEstudanteETipo($editar['id'], 'passaporte');
    $docsCPF = $docIdentidadeModel->buscarPorEstudanteETipo($editar['id'], 'cpf');
    $documentosExistentes = [
        'rg' => $docsRG,
        'cnh' => $docsCNH,
        'passaporte' => $docsPassaporte,
        'cpf' => $docsCPF
    ];
    // Carregar documentos de inscrição (matrícula) para edição
    if ($editar) {
        $stmtInsc = $db->prepare("SELECT id FROM inscricoes WHERE estudante_id = :estudante_id ORDER BY id DESC LIMIT 1");
        $stmtInsc->bindParam(':estudante_id', $editar['id'], PDO::PARAM_INT);
        $stmtInsc->execute();
        $inscricaoAssoc = $stmtInsc->fetch(PDO::FETCH_ASSOC);
        $documentosMatricula = [];
        if ($inscricaoAssoc) {
            $inscricaoId = $inscricaoAssoc['id'];
            $inscricaoTemp = new Inscricao($db);
            $documentosMatricula = $inscricaoTemp->getDocumentos();
        }
    }
}

// ================================
// LISTAGEM
// ================================
$estudantes = $estudante->listar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Estudantes - CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --success-color: #2e7d32;
            --error-color: #c62828;
            --warning-color: #f57c00;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-text: #666;
            --border-color: #ddd;
            --shadow: 0 4px 6px rgba(0,0,0,0.05);
            --radius: 8px;
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-section h2 {
            color: var(--text-color);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            font-size: 0.9rem;
        }

        .btn-back:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease-out;
        }

        .card-title {
            font-size: 1.3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Formulário */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
            margin-bottom: 6px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        label span.required {
            color: var(--error-color);
            margin-left: 3px;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        input:focus, select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
            background-color: #fff;
        }

        input.error-field {
            border-color: var(--error-color);
            background-color: #ffebee;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            font-size: 0.9rem;
            padding: 8px;
            background: #fff;
            border: 1px dashed var(--border-color);
            width: 100%;
        }

        .file-preview {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--light-text);
        }

        .file-preview img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(25, 118, 210, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 118, 210, 0.3);
        }

        .btn-cancel {
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            padding: 12px 20px;
            transition: color 0.3s;
            border: 1px solid transparent;
            border-radius: 6px;
        }

        .btn-cancel:hover {
            color: var(--text-color);
            background: #f5f5f5;
            text-decoration: none;
        }

        /* Mensagens */
        .mensagem {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
        }

        .sucesso {
            background-color: #e8f5e9;
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .erro {
            background-color: #ffebee;
            color: var(--error-color);
            border-left-color: var(--error-color);
        }

        /* Tabela */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px; /* Garante scroll em telas pequenas */
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            color: var(--light-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        .foto-preview-table {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #eee;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-pendente { background-color: #fff3e0; color: var(--warning-color); }
        .badge-aprovado { background-color: #e8f5e9; color: var(--success-color); }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-edit {
            background-color: rgba(25, 118, 210, 0.1);
            color: var(--primary-color);
        }
        .btn-edit:hover { background-color: var(--primary-color); color: white; }

        .btn-delete {
            background-color: rgba(198, 40, 40, 0.1);
            color: var(--error-color);
        }
        .btn-delete:hover { background-color: var(--error-color); color: white; }

        hr {
            border: 0;
            height: 1px;
            background: #eee;
            margin: 25px 0;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .header-section { flex-direction: column; align-items: flex-start; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-back { margin-bottom: 10px; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .btn-submit, .btn-cancel { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="header-section">
            <h2><?= $editar ? 'Editar Estudante' : 'Cadastrar Novo Estudante' ?></h2>
            <a href="dashboard.php" class="btn-back">← Voltar ao Dashboard</a>
        </div>

        <?php if ($erro): ?>
            <div class="mensagem erro">
                <strong>⚠️ Erro:</strong> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso">
                <strong>✅ Sucesso:</strong> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <!-- Card de Formulário -->
        <div class="card">
            <h3 class="card-title">
                <span><?= $editar ? '✏️' : '➕' ?></span> 
                <?= $editar ? 'Dados do Estudante' : 'Nova Inscrição' ?>
            </h3>
            
            <form method="POST" enctype="multipart/form-data" id="formEstudante" novalidate>
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Dados Civis -->
                    <div class="form-group full-width">
                        <label>Nome Completo <span class="required">*</span></label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? ($_POST['nome'] ?? '')) ?>" required placeholder="Digite o nome completo">
                    </div>

                    <div class="form-group">
                        <label>Data de Nascimento <span class="required">*</span></label>
                        <input type="date" name="data_nascimento" value="<?= htmlspecialchars($editar['data_nascimento'] ?? ($_POST['data_nascimento'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>CPF <span class="required">*</span></label>
                        <input type="text" name="cpf" id="cpf" value="<?= htmlspecialchars($editar['cpf'] ?? ($_POST['cpf'] ?? '')) ?>" placeholder="000.000.000-00" required maxlength="14">
                    </div>

                    <div class="form-group">
                        <label>Tipo de Documento <span class="required">*</span></label>
                        <select name="documento_tipo" required>
                            <option value="">Selecione...</option>
                            <option value="RG" <?= ($editar && $editar['documento_tipo'] === 'RG') ? 'selected' : '' ?>>RG</option>
                            <option value="CNH" <?= ($editar && $editar['documento_tipo'] === 'CNH') ? 'selected' : '' ?>>CNH</option>
                            <option value="PASSAPORTE" <?= ($editar && $editar['documento_tipo'] === 'PASSAPORTE') ? 'selected' : '' ?>>Passaporte</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número do Documento <span class="required">*</span></label>
                        <input type="text" name="documento_numero" value="<?= htmlspecialchars($editar['documento_numero'] ?? ($_POST['documento_numero'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Órgão Expedidor</label>
                        <input type="text" name="documento_orgao" value="<?= htmlspecialchars($editar['documento_orgao'] ?? ($_POST['documento_orgao'] ?? '')) ?>">
                    </div>

                    <!-- Uploads -->
                    <div class="form-group">
                        <label>Foto 3x4 (JPG/PNG)</label>
                        <input type="file" name="foto" id="fotoInput" accept="image/jpeg,image/png" onchange="previewImage(this, 'fotoPreview')">
                        <div id="fotoPreview" class="file-preview">
                            <?php if ($editar):
                                $fotoDoc = $docIdentidadeModel->buscarPorEstudanteETipo($editar['id'], 'foto_3x4');
                                if (!empty($fotoDoc) && isset($fotoDoc[0]['caminho_arquivo'])): ?>
                                <img src="../public/<?= htmlspecialchars($fotoDoc[0]['caminho_arquivo']) ?>" alt="Foto Atual">
                                <span>Foto atual carregada</span>
                            <?php endif; endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Documento (Frente)</label>
                        <input type="file" name="doc_identidade_frente" accept=".jpg,.jpeg,.png,.pdf" onchange="showFileName(this, 'nomeFrente')">
                        <div id="nomeFrente" class="file-preview">
                            <?php if ($editar):
                                $tipoEdit = $editar['documento_tipo'];
                                if (isset($documentosExistentes[strtolower($tipoEdit)]) && count($documentosExistentes[strtolower($tipoEdit)]) > 0):
                                    $docsDoTipo = $documentosExistentes[strtolower($tipoEdit)];
                                    if (isset($docsDoTipo[0])): ?>
                                    <span>Atual: <?= htmlspecialchars($docsDoTipo[0]['descricao']) ?></span>
                                    <a href="../public/<?= htmlspecialchars($docsDoTipo[0]['caminho_arquivo']) ?>" target="_blank" style="margin-left:5px;">👁️</a>
                                <?php endif; endif; endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Documento (Verso)</label>
                        <input type="file" name="doc_identidade_verso" accept=".jpg,.jpeg,.png,.pdf" onchange="showFileName(this, 'nomeVerso')">
                        <div id="nomeVerso" class="file-preview">
                            <?php if ($editar):
                                $tipoEdit = $editar['documento_tipo'];
                                if (isset($documentosExistentes[strtolower($tipoEdit)]) && count($documentosExistentes[strtolower($tipoEdit)]) > 0):
                                    $docsDoTipo = $documentosExistentes[strtolower($tipoEdit)];
                                    if (isset($docsDoTipo[1])): ?>
                                    <span>Atual: <?= htmlspecialchars($docsDoTipo[1]['descricao']) ?></span>
                                    <a href="../public/<?= htmlspecialchars($docsDoTipo[1]['caminho_arquivo']) ?>" target="_blank" style="margin-left:5px;">👁️</a>
                                <?php endif; endif; endif; ?>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Comprovante de Matrícula <span class="required">*</span></label>
                        <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf" onchange="showFileName(this, 'nomeMatricula')">
                        <div id="nomeMatricula" class="file-preview">
                            <?php if ($editar && !empty($documentosMatricula)):
                                foreach ($documentosMatricula as $doc):
                                    if ($doc['tipo'] === 'matricula'): ?>
                                    <span>Atual: <?= htmlspecialchars($doc['descricao']) ?></span>
                                    <a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" style="margin-left:5px;">👁️</a>
                                <?php endif; endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Dados Acadêmicos -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Instituição <span class="required">*</span></label>
                        <select name="instituicao_id" required>
                            <option value="">Selecione...</option>
                            <?php
                            $instituicoesAtivas = $instituicaoModel->listarAtivas();
                            foreach ($instituicoesAtivas as $inst): ?>
                                <option value="<?= $inst['id'] ?>" <?= ($editar && $editar['instituicao_id'] == $inst['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Campus</label>
                        <input type="text" name="campus" value="<?= htmlspecialchars($editar['campus'] ?? ($_POST['campus'] ?? '')) ?>" placeholder="Ex: Unidade Central">
                    </div>

                    <div class="form-group">
                        <label>Curso <span class="required">*</span></label>
                        <input type="text" name="curso" value="<?= htmlspecialchars($editar['curso'] ?? ($_POST['curso'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Nível <span class="required">*</span></label>
                        <input type="text" name="nivel" value="<?= htmlspecialchars($editar['nivel'] ?? ($_POST['nivel'] ?? '')) ?>" required placeholder="Ex: Superior">
                    </div>

                    <div class="form-group">
                        <label>Matrícula <span class="required">*</span></label>
                        <input type="text" name="matricula" value="<?= htmlspecialchars($editar['matricula'] ?? ($_POST['matricula'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Situação Acadêmica <span class="required">*</span></label>
                        <select name="situacao_academica" required>
                            <option value="Matriculado" <?= ($editar && $editar['situacao_academica'] === 'Matriculado') ? 'selected' : '' ?>>Matriculado</option>
                            <option value="Trancado" <?= ($editar && $editar['situacao_academica'] === 'Trancado') ? 'selected' : '' ?>>Trancado</option>
                            <option value="Formado" <?= ($editar && $editar['situacao_academica'] === 'Formado') ? 'selected' : '' ?>>Formado</option>
                            <option value="Cancelado" <?= ($editar && $editar['situacao_academica'] === 'Cancelado') ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>

                    <?php if ($editar): ?>
                    <div class="form-group">
                        <label>Status de Validação <span class="required">*</span></label>
                        <select name="status_validacao" required>
                            <option value="pendente" <?= ($editar && $editar['status_validacao'] === 'pendente') ? 'selected' : '' ?>>Pendente</option>
                            <option value="dados_aprovados" <?= ($editar && $editar['status_validacao'] === 'dados_aprovados') ? 'selected' : '' ?>>Dados Aprovados</option>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="status_validacao" value="dados_aprovados">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($editar['email'] ?? ($_POST['email'] ?? '')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($editar['telefone'] ?? ($_POST['telefone'] ?? '')) ?>" placeholder="(00) 00000-0000" maxlength="15">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <span><?= $editar ? '💾 Atualizar' : '➕ Cadastrar' ?></span>
                    </button>
                    <?php if ($editar): ?>
                        <a href="estudantes.php" class="btn-cancel">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Card de Listagem -->
        <div class="card">
            <h3 class="card-title">📋 Estudantes Cadastrados</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nome</th>
                            <th>Matrícula</th>
                            <th>Curso</th>
                            <th>Instituição</th>
                            <th>Situação</th>
                            <th>Status</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($estudantes)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--light-text);">
                                    Nenhum estudante encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($estudantes as $e): ?>
                            <tr>
                                <td>
                                    <?php
                                    $fotoDoc = $docIdentidadeModel->buscarPorEstudanteETipo($e['id'], 'foto_3x4');
                                    if (!empty($fotoDoc) && isset($fotoDoc[0]['caminho_arquivo'])) {
                                        echo '<img src="../public/' . htmlspecialchars($fotoDoc[0]['caminho_arquivo']) . '" class="foto-preview-table" alt="Foto">';
                                    } else {
                                        echo '<div style="width:50px;height:50px;background:#eee;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#aaa;">👤</div>';
                                    }
                                    ?>
                                </td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($e['nome']) ?></td>
                                <td><?= htmlspecialchars($e['matricula']) ?></td>
                                <td><?= htmlspecialchars($e['curso']) ?></td>
                                <td><?= htmlspecialchars($e['instituicao_nome'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($e['situacao_academica']) ?></td>
                                <td>
                                    <span class="badge-status <?= $e['status_validacao'] === 'dados_aprovados' ? 'badge-aprovado' : 'badge-pendente' ?>">
                                        <?= $e['status_validacao'] === 'dados_aprovados' ? 'Aprovado' : 'Pendente' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <a href="?editar=<?= $e['id'] ?>" class="btn-action btn-edit" title="Editar">✏️</a>
                                        <a href="?deletar=<?= $e['id'] ?>" 
                                           class="btn-action btn-delete" 
                                           title="Excluir"
                                           onclick="return confirmDelete('<?= htmlspecialchars($e['nome']) ?>', <?= $e['id'] ?>)">
                                            🗑️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Scripts de Prevenção de Erros -->
    <script>
        // Máscara de CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2})/);
            e.target.value = !x[2] ? x[1] : x[1] + '.' + x[2] + (x[3] ? '.' + x[3] : '') + (x[4] ? '-' + x[4] : '');
        });

        // Máscara de Telefone
        const telInput = document.getElementById('telefone');
        if(telInput) {
            telInput.addEventListener('input', function(e) {
                let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
                e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
            });
        }

        // Preview de Imagem
        function previewImage(input, previewId) {
            const previewContainer = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validação de tamanho (Max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('O arquivo é muito grande. O máximo permitido é 5MB.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview"><span>${file.name}</span>`;
                };
                reader.readAsDataURL(file);
            }
        }

        // Mostrar nome do arquivo
        function showFileName(input, previewId) {
            const previewContainer = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                 // Validação de tamanho (Max 10MB para docs)
                 if (file.size > 10 * 1024 * 1024) {
                    alert('O arquivo é muito grande. O máximo permitido é 10MB.');
                    input.value = '';
                    return;
                }
                previewContainer.innerHTML = `<span style="color:var(--primary-color); font-weight:bold;">Novo: ${file.name}</span>`;
            }
        }

        // Confirmação de Exclusão Personalizada
        function confirmDelete(nome, id) {
            return confirm(`⚠️ ATENÇÃO:\n\nVocê está prestes a excluir o estudante:\n"${nome}" (ID: ${id}).\n\nEsta ação apagará todos os dados e documentos associados permanentemente.\n\nDeseja continuar?`);
        }

        // Validação de Formulário antes do Envio
        document.getElementById('formEstudante').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error-field');
                    field.focus();
                } else {
                    field.classList.remove('error-field');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios marcados com *.');
            }
        });

        // Remover destaque de erro ao digitar
        document.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('input', () => {
                field.classList.remove('error-field');
            });
        });
    </script>
</body>
</html>