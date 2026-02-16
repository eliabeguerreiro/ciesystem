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
$docIdentidadeModel = new DocumentoEstudante($db); // Instância do modelo de doc (atualizado)
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
    $estudante->instituicao_id = (int)($_POST['instituicao_id'] ?? 0); // Sanitiza como inteiro
    $estudante->instituicao = ''; // Mantido por compatibilidade com o modelo, mas agora é instituicao_id que conta
    $estudante->campus = $_POST['campus'] ?? '';
    $estudante->curso = $_POST['curso'] ?? '';
    $estudante->nivel = $_POST['nivel'] ?? '';
    $estudante->matricula = $_POST['matricula'] ?? '';
    $estudante->situacao_academica = $_POST['situacao_academica'] ?? 'Matriculado';
    $estudante->status_validacao = $_POST['status_validacao'] ?? 'dados_aprovados'; // ← PADRÃO: dados_aprovados
    $estudante->email = $_POST['email'] ?? '';
    $estudante->telefone = $_POST['telefone'] ?? '';

    $uploadFoto = null;
    if (!empty($_FILES['foto']['name'])) {
        $uploadFoto = $estudanteCtrl->uploadFoto($_FILES['foto']);
        if ($uploadFoto === null) {
            $erro = "Erro ao fazer upload da foto. Use JPG ou PNG.";
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

        // Se foi feito upload de nova foto → deleta a antiga
        if ($uploadFoto !== null) {
            if (!empty($registroAtual['foto'])) {
                $estudanteCtrl->deletarFotoAntiga($registroAtual['foto']);
            }
            $estudante->foto = $uploadFoto;
        } else {
            $estudante->foto = $registroAtual['foto'] ?? null;
        }

        if (empty($erro)) {
            if ($estudante->atualizar()) {

                // --- Processar Documentos de Identidade na Edição ---
                if ($tipoDocIdentidade && !empty($docFrente['name']) && !empty($docVerso['name'])) {
                    // Deletar documentos antigos do mesmo tipo (frente e verso) - O modelo DocumentoEstudante.php atualizado lida com isso
                    $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, $tipoDocIdentidade);

                    // Obter o status_validacao atual do estudante para validação automática
                    $stmtStatus = $db->prepare("SELECT status_validacao FROM estudantes WHERE id = :estudante_id");
                    $stmtStatus->bindParam(':estudante_id', $estudante->id, PDO::PARAM_INT);
                    $stmtStatus->execute();
                    $statusAtualDoEstudante = $stmtStatus->fetch(PDO::FETCH_ASSOC)['status_validacao'] ?? 'pendente';

                    // Salvar os novos documentos (frente e verso) - O modelo DocumentoEstudante.php atualizado lida com isso
                    // Passar o status_validacao do estudante para validação automática
                    if (!$docIdentidadeModel->salvarFrenteVerso($estudante->id, $docFrente, $docVerso, $tipoDocIdentidade, $statusAtualDoEstudante)) { // <-- Passa o status
                        $erro = "Erro ao salvar os novos documentos de identidade (Frente e Verso).";
                    }
                }
                // ---

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
                                         $log->registrar(
                                             $_SESSION['user_id'],
                                             'anexou_e_validou_comprovante_matricula_admin',
                                             "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}",
                                             $inscricaoTemp->id,
                                             'inscricoes'
                                         );
                                     } else {
                                         $sucesso = "Comprovante de matrícula anexado, mas falha ao validar automaticamente.";
                                     }
                                 } else {
                                     $sucesso = "Comprovante de matrícula anexado. Aguardando validação (status do estudante ou origem não permitem validação automática).";
                                     require_once __DIR__ . '/../app/models/Log.php';
                                     $log = new Log($db);
                                     $log->registrar(
                                         $_SESSION['user_id'],
                                         'anexou_comprovante_matricula',
                                         "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}, Status: " . ($dadosEstudante['status_validacao'] ?? 'Desconhecido'),
                                         $inscricaoTemp->id,
                                         'inscricoes'
                                     );
                                 }
                             } else {
                                  $sucesso = "Comprovante de matrícula anexado (falha na busca de dados da inscrição/estudante).";
                                  require_once __DIR__ . '/../app/models/Log.php';
                                  $log = new Log($db);
                                  $log->registrar(
                                      $_SESSION['user_id'],
                                      'anexou_comprovante_matricula',
                                      "Inscrição ID: {$inscricaoTemp->id} (Erro: dados do estudante não encontrados)",
                                      $inscricaoTemp->id,
                                      'inscricoes'
                                  );
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

                if (empty($erro)) { // Prosseguir com sucesso se não houve erro no upload
                    require_once __DIR__ . '/../app/models/Log.php';
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'editou_estudante',
                        "ID: {$estudante->id}, Nome: {$estudante->nome}, Matrícula: {$estudante->matricula}",
                        $estudante->id,
                        'estudantes'
                    );
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
        if (empty($erro)) { // Apenas prosseguir se as validações acima estiverem OK
            $estudante->foto = $uploadFoto;
            if ($estudante->criar()) {
                $novoEstudanteId = $db->lastInsertId();

                // === CRIAR INSCRIÇÃO AUTOMATICAMENTE ===
                $inscricao = new Inscricao($db);
                $inscricao->estudante_id = $novoEstudanteId;
                $inscricao->origem = 'administrador';
                if ($inscricao->criar()) {
                    $idInscricaoRecemCriada = $db->lastInsertId();

                    // === SALVAR DOCUMENTOS DE IDENTIDADE (Opcional, mas obrigatório se o tipo for selecionado) ===
                    if ($tipoDocIdentidade && $docFrente && $docVerso) {
                        $statusDoNovoEstudante = $estudante->status_validacao;

                        // Salvar os novos documentos (frente e verso) - O modelo DocumentoEstudante.php atualizado lida com isso
                        // Passar o status_validacao do estudante para validação automática
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
                                                  $log->registrar(
                                                      $_SESSION['user_id'],
                                                      'anexou_e_validou_comprovante_matricula_admin',
                                                      "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}",
                                                      $inscricaoTemp->id,
                                                      'inscricoes'
                                                  );
                                              } else {
                                                  $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado, mas falha ao validar automaticamente.";
                                              }
                                          } else {
                                              $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado. Aguardando validação (status do estudante ou origem não permitem validação automática).";
                                              require_once __DIR__ . '/../app/models/Log.php';
                                              $log = new Log($db);
                                              $log->registrar(
                                                  $_SESSION['user_id'],
                                                  'anexou_comprovante_matricula',
                                                  "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}, Status: " . ($dadosEstudante['status_validacao'] ?? 'Desconhecido'),
                                                  $inscricaoTemp->id,
                                                  'inscricoes'
                                              );
                                          }
                                      } else {
                                           $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado (falha na busca de dados da inscrição/estudante).";
                                           require_once __DIR__ . '/../app/models/Log.php';
                                           $log = new Log($db);
                                           $log->registrar(
                                               $_SESSION['user_id'],
                                               'anexou_comprovante_matricula',
                                               "Inscrição ID: {$inscricaoTemp->id} (Erro: dados do estudante não encontrados)",
                                               $inscricaoTemp->id,
                                               'inscricoes'
                                           );
                                      }
                                      // === FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
                                 } else {
                                     $erro = "Erro ao salvar o comprovante de matrícula.";
                                 }
                             } else {
                                  $sucesso = "Estudante cadastrado com sucesso!";
                                  require_once __DIR__ . '/../app/models/Log.php';
                                  $log = new Log($db);
                                  $log->registrar(
                                      $_SESSION['user_id'], // ID do usuário admin
                                      'criou_estudante_admin',
                                      "Estudante: {$estudante->nome}, Matrícula: {$estudante->matricula} (Sem documentos de identidade)",
                                      $novoEstudanteId, // ID do estudante recém-criado
                                      'estudantes'
                                  );
                                  foreach ($_POST as $key => $value) $_POST[$key] = '';
                             }
                             // --- FIM NOVO ---
                        }
                    } else {
                         // Se não for enviado tipo e arquivos, é aceitável para cadastro.
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
                                              $log->registrar(
                                                  $_SESSION['user_id'],
                                                  'anexou_e_validou_comprovante_matricula_admin',
                                                  "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}",
                                                  $inscricaoTemp->id,
                                                  'inscricoes'
                                              );
                                          } else {
                                              $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado, mas falha ao validar automaticamente.";
                                          }
                                      } else {
                                          $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado. Aguardando validação (status do estudante ou origem não permitem validação automática).";
                                          require_once __DIR__ . '/../app/models/Log.php';
                                          $log = new Log($db);
                                          $log->registrar(
                                              $_SESSION['user_id'],
                                              'anexou_comprovante_matricula',
                                              "Inscrição ID: {$inscricaoTemp->id}, Estudante ID: {$estudanteId}, Origem: {$origemInscricao}, Status: " . ($dadosEstudante['status_validacao'] ?? 'Desconhecido'),
                                              $inscricaoTemp->id,
                                              'inscricoes'
                                          );
                                      }
                                  } else {
                                       $sucesso = "Estudante cadastrado com sucesso! Comprovante de matrícula anexado (falha na busca de dados da inscrição/estudante).";
                                       require_once __DIR__ . '/../app/models/Log.php';
                                       $log = new Log($db);
                                       $log->registrar(
                                           $_SESSION['user_id'],
                                           'anexou_comprovante_matricula',
                                           "Inscrição ID: {$inscricaoTemp->id} (Erro: dados do estudante não encontrados)",
                                           $inscricaoTemp->id,
                                           'inscricoes'
                                       );
                                  }
                                  // === FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
                             } else {
                                 $erro = "Erro ao salvar o comprovante de matrícula.";
                             }
                         } else {
                              $sucesso = "Estudante cadastrado com sucesso!";
                              require_once __DIR__ . '/../app/models/Log.php';
                              $log = new Log($db);
                              $log->registrar(
                                  $_SESSION['user_id'], // ID do usuário admin
                                  'criou_estudante_admin',
                                  "Estudante: {$estudante->nome}, Matrícula: {$estudante->matricula} (Sem documentos de identidade)",
                                  $novoEstudanteId, // ID do estudante recém-criado
                                  'estudantes'
                              );
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
        // Deleta a foto associada, se existir
        if (!empty($registro['foto'])) {
            $estudanteCtrl->deletarFotoAntiga($registro['foto']);
        }
        // Deleta TODOS os documentos de identidade associados (irá apagar frente e verso de todos os tipos)
        // O modelo DocumentoEstudante.php atualizado lida com 'documentos_anexados' e encontra a inscrição correta
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'rg');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'cnh');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'passaporte');
        $docIdentidadeModel->deletarPorEstudanteETipo($estudante->id, 'cpf');

        require_once __DIR__ . '/../app/models/Log.php';
        $log = new Log($db);
        $log->registrar(
            $_SESSION['user_id'],
            'excluiu_estudante',
            "ID: {$estudante->id}, Nome: {$registro['nome']}",
            $estudante->id,
            'estudantes'
        );
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
    // Carregar documentos de identidade para edição (busca por tipo específico ou todos)
    // O modelo DocumentoEstudante.php atualizado lida com 'documentos_anexados' e encontra a inscrição mais recente
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
            $documentosMatricula = $inscricaoTemp->getDocumentos(); // Obtém todos os docs da inscrição
        }
    }
}

// ================================
// LISTAGEM
// ================================

$estudantes = $estudante->listar();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Estudantes</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        form { background: white; padding: 20px; border-radius: 6px; margin-bottom: 30px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9em; }
        input, select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #1565c0; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .acoes { white-space: nowrap; }
        .foto-preview { width: 60px; height: 60px; object-fit: cover; border: 1px solid #ddd; }
        a { color: #1976d2; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .doc-preview { width: 150px; height: auto; max-height: 100px; object-fit: contain; border: 1px solid #ddd; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2><?= $editar ? 'Editar Estudante' : 'Cadastrar Novo Estudante' ?></h2>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php if ($editar): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
            <?php endif; ?>

            <!-- Dados Civis -->
            <h3>Dados Civis</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? ($_POST['nome'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento *</label>
                    <input type="date" name="data_nascimento" value="<?= htmlspecialchars($editar['data_nascimento'] ?? ($_POST['data_nascimento'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>CPF *</label>
                    <input type="text" name="cpf" value="<?= htmlspecialchars($editar['cpf'] ?? ($_POST['cpf'] ?? '')) ?>" placeholder="000.000.000-00" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Documento *</label>
                    <select name="documento_tipo" required>
                        <option value="RG" <?= ($editar && $editar['documento_tipo'] === 'RG') ? 'selected' : '' ?>>RG</option>
                        <option value="CNH" <?= ($editar && $editar['documento_tipo'] === 'CNH') ? 'selected' : '' ?>>CNH</option>
                        <option value="PASSAPORTE" <?= ($editar && $editar['documento_tipo'] === 'PASSAPORTE') ? 'selected' : '' ?>>Passaporte</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Número do Documento *</label>
                    <input type="text" name="documento_numero" value="<?= htmlspecialchars($editar['documento_numero'] ?? ($_POST['documento_numero'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Órgão Expedidor</label>
                    <input type="text" name="documento_orgao" value="<?= htmlspecialchars($editar['documento_orgao'] ?? ($_POST['documento_orgao'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Foto 3x4 (JPG/PNG)</label>
                    <input type="file" name="foto" accept="image/jpeg,image/png">
                    <?php if ($editar && !empty($editar['foto'])): ?>
                        <br><img src="<?= htmlspecialchars($editar['foto']) ?>" class="foto-preview" alt="Foto">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documentos de Identidade (Frente e Verso) -->
            <div class="form-row">
                <div class="form-group">
                    <label>Documento de Identidade - Frente</label>
                    <input type="file" name="doc_identidade_frente" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($editar):
                        $tipoEdit = $editar['documento_tipo'];
                        if (isset($documentosExistentes[strtolower($tipoEdit)]) && count($documentosExistentes[strtolower($tipoEdit)]) > 0):
                            $docsDoTipo = $documentosExistentes[strtolower($tipoEdit)];
                            $docFrente = null;
                            if (isset($docsDoTipo[0])) $docFrente = $docsDoTipo[0];
                            if ($docFrente): ?>
                                <br><small>Arquivo atual (Frente): <?= htmlspecialchars($docFrente['descricao']) ?></small>
                                <br><a href="../public/<?= htmlspecialchars($docFrente['caminho_arquivo']) ?>" target="_blank">Visualizar Frente</a>
                            <?php endif;
                        endif;
                     endif; ?>
                </div>
                <div class="form-group">
                    <label>Documento de Identidade - Verso</label>
                    <input type="file" name="doc_identidade_verso" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($editar):
                        $tipoEdit = $editar['documento_tipo'];
                        if (isset($documentosExistentes[strtolower($tipoEdit)]) && count($documentosExistentes[strtolower($tipoEdit)]) > 0):
                            $docsDoTipo = $documentosExistentes[strtolower($tipoEdit)];
                            $docVerso = null;
                            if (isset($docsDoTipo[1])) $docVerso = $docsDoTipo[1];
                            if ($docVerso): ?>
                                <br><small>Arquivo atual (Verso): <?= htmlspecialchars($docVerso['descricao']) ?></small>
                                <br><a href="../public/<?= htmlspecialchars($docVerso['caminho_arquivo']) ?>" target="_blank">Visualizar Verso</a>
                            <?php endif;
                        endif;
                     endif; ?>
                </div>
            </div>

            <!-- Comprovante de Matrícula -->
            <div class="form-row">
                <div class="form-group">
                    <label>Comprovante de Matrícula *</label>
                    <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($editar && !empty($documentosMatricula)):
                        foreach ($documentosMatricula as $doc):
                            if ($doc['tipo'] === 'matricula'): ?>
                                <br><small>Arquivo atual (Matrícula): <?= htmlspecialchars($doc['descricao']) ?></small>
                                <br><a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank">Visualizar Matrícula</a>
                            <?php endif;
                        endforeach;
                     endif; ?>
                </div>
            </div>



            <hr>

            <!-- Dados Acadêmicos -->
            <h3>Dados Acadêmicos</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Instituição *</label>
                    <select name="instituicao_id" required>
                        <option value="">Selecione uma instituição...</option>
                        <?php
                        $instituicoesAtivas = $instituicoesAtivas ?? $instituicaoModel->listarAtivas();
                        foreach ($instituicoesAtivas as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($editar && $editar['instituicao_id'] == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Campus</label>
                    <input type="text" name="campus" value="<?= htmlspecialchars($editar['campus'] ?? ($_POST['campus'] ?? '')) ?>" placeholder="Ex: João Pessoa">
                </div>
                <div class="form-group">
                    <label>Curso *</label>
                    <input type="text" name="curso" value="<?= htmlspecialchars($editar['curso'] ?? ($_POST['curso'] ?? '')) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Nível/Modalidade *</label>
                    <input type="text" name="nivel" value="<?= htmlspecialchars($editar['nivel'] ?? ($_POST['nivel'] ?? '')) ?>" required placeholder="Ex: Técnico, Superior">
                </div>
                <div class="form-group">
                    <label>Matrícula *</label>
                    <input type="text" name="matricula" value="<?= htmlspecialchars($editar['matricula'] ?? ($_POST['matricula'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Situação Acadêmica *</label>
                    <select name="situacao_academica" required>
                        <option value="Matriculado" <?= ($editar && $editar['situacao_academica'] === 'Matriculado') ? 'selected' : '' ?>>Matriculado</option>
                        <option value="Trancado" <?= ($editar && $editar['situacao_academica'] === 'Trancado') ? 'selected' : '' ?>>Trancado</option>
                        <option value="Formado" <?= ($editar && $editar['situacao_academica'] === 'Formado') ? 'selected' : '' ?>>Formado</option>
                        <option value="Cancelado" <?= ($editar && $editar['situacao_academica'] === 'Cancelado') ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
            </div>

            <!-- Status de Validação -->
            <?php if ($editar): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Status de Validação *</label>
                        <select name="status_validacao" required>
                            <option value="pendente" <?= ($editar && $editar['status_validacao'] === 'pendente') ? 'selected' : '' ?>>Pendente</option>
                            <option value="dados_aprovados" <?= ($editar && $editar['status_validacao'] === 'dados_aprovados') ? 'selected' : '' ?>>Dados Aprovados</option>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="status_validacao" value="dados_aprovados">
            <?php endif; ?>

            <!-- Contato -->
            <h3>Contato (Opcional)</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editar['email'] ?? ($_POST['email'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($editar['telefone'] ?? ($_POST['telefone'] ?? '')) ?>" placeholder="(00) 00000-0000">
                </div>
            </div>

            <button type="submit"><?= $editar ? 'Atualizar Estudante' : 'Cadastrar Estudante' ?></button>
            <?php if ($editar): ?>
                <a href="estudantes.php" style="margin-left: 10px;">Cancelar</a>
            <?php endif; ?>
        </form>

        <!-- Listagem -->
        <h3>Lista de Estudantes</h3>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Matrícula</th>
                    <th>Curso</th>
                    <th>Instituição</th>
                    <th>Situação</th>
                    <th>Status Validação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudantes as $e): ?>
                <tr>
                    <td>
                        <?php if (!empty($e['foto'])): ?>
                            <img src="<?= htmlspecialchars($e['foto']) ?>" class="foto-preview" alt="Foto">
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($e['nome']) ?></td>
                    <td><?= htmlspecialchars($e['matricula']) ?></td>
                    <td><?= htmlspecialchars($e['curso']) ?></td>
                    <td><?= htmlspecialchars($e['instituicao_nome'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($e['situacao_academica']) ?></td>
                    <td><?= htmlspecialchars($e['status_validacao']) ?></td>
                    <td class="acoes">
                        <a href="?editar=<?= $e['id'] ?>">Editar</a> |
                        <a href="?deletar=<?= $e['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este estudante?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>