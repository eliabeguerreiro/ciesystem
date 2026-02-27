<?php
// webhook_abacatepay.php

// Inclui a configuração da API e o banco de dados
require_once __DIR__ . '/../app/config/abacatepay_config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Inscricao.php';

// Lê o corpo da requisição (raw body)
$rawBody = file_get_contents('php://input');
$headers = getallheaders();

$signature = $headers['X-Webhook-Signature'] ?? null;
$webhookSecret = $_GET['webhookSecret'] ?? null;

if (!$signature || !$webhookSecret) {
    http_response_code(400);
    echo json_encode(['error' => 'Signature or secret missing']);
    exit;
}

// Validação do secret na URL (primeira camada)
if ($webhookSecret !== ABACATEPAY_WEBHOOK_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook secret']);
    exit;
}

// Validação da assinatura HMAC (segunda camada - mais segura)
$isValidSignature = verifyAbacateSignature($rawBody, $signature);

if (!$isValidSignature) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Decodifica o JSON do corpo
$event = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Processa o evento
$eventType = $event['event'] ?? null;
$devMode = $event['devMode'] ?? false; // Indica se é ambiente de testes

if ($eventType === 'billing.paid') {
    $paymentData = $event['data']['payment'] ?? null;
    $pixData = $event['data']['pixQrCode'] ?? null; // Dados específicos do PIX (se for PIX direto)
    $billingData = $event['data']['billing'] ?? null; // Dados da cobrança (se for via /billing/create)

    $codigoInscricao = null;

    if ($pixData) {
        // Evento de PIX direto (provavelmente não será o caso se usarmos /billing/create para tudo)
        // A descrição pode estar em $pixData['description']
        $descricaoPagamento = $pixData['description'] ?? '';
        if (preg_match('/Inscrição: ([a-f0-9\-]+)/i', $descricaoPagamento, $matches)) {
            $codigoInscricao = $matches[1];
        }
    } elseif ($billingData && $paymentData) {
        // Evento de cobrança via /billing/create (PIX ou CARTÃO)
        // A descrição do produto está em $billingData['products'][0]['description']
        // Ou poderia estar em $billingData['metadata'] se adicionarmos lá
        $produtos = $billingData['products'] ?? [];
        if (!empty($produtos) && isset($produtos[0]['description'])) {
             $descricaoPagamento = $produtos[0]['description'];
             if (preg_match('/CIE 2026 - (.+)/', $descricaoPagamento, $matches)) {
                // Extrai o nome do estudante da descrição e tenta encontrar a inscrição
                // Isto é menos robusto que usar um código único.
                // A melhor prática é usar METADATA.
                // Por ora, vamos tentar encontrar uma inscrição ativa com esse nome e status pendente.
                // MAS, é melhor adicionar o código da inscrição nos METADADOS ao criar a cobrança.
                // Vamos supor que adicionamos o código nos metadados:
                $codigoInscricao = $billingData['metadata']['codigo_inscricao'] ?? null;
             }
        }
    }

    if ($codigoInscricao) {
        $database = new Database();
        $db = $database->getConnection();
        $inscricaoModel = new Inscricao($db);

        // Busca a inscrição pelo código
        $query = "SELECT id FROM inscricoes WHERE codigo_inscricao = :codigo";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo', $codigoInscricao);
        $stmt->execute();
        $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inscricao) {
            $inscricaoId = $inscricao['id'];
            $inscricaoModel->id = $inscricaoId;

            // Atualiza o status do pagamento e da inscrição
            if ($inscricaoModel->atualizarPagamentoConfirmado(true)) {
                // Opcional: Atualizar a situação da inscrição para 'pago'
                $inscricaoModel->atualizarSituacao('pago');

                // Log do evento (opcional)
                require_once __DIR__ . '/../app/models/Log.php';
                $logModel = new Log($db);
                $logModel->registrar(null, 'webhook_pagamento_confirmado', "Pagamento confirmado ({$paymentData['method']} - via billing) para inscrição {$codigoInscricao} (ID: {$inscricaoId})", $inscricaoId, 'inscricoes');

                echo json_encode(['received' => true, 'processed' => true]);
                exit;
            } else {
                // Erro ao atualizar o banco
                error_log("Erro ao atualizar pagamento_confirmado para inscrição {$codigoInscricao}");
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update database']);
                exit;
            }
        } else {
            // Inscrição não encontrada
            error_log("Webhook: Inscrição {$codigoInscricao} não encontrada para pagamento confirmado (billing.paid).");
            // Mesmo não encontrando, retornamos 200 para evitar retries, pois pode ser um pagamento inválido ou antigo.
            echo json_encode(['received' => true, 'processed' => false, 'reason' => 'inscription_not_found']);
            exit;
        }
    } else {
        // Código da inscrição não encontrado na descrição ou metadata
        error_log("Webhook: Código da inscrição não encontrado no evento billing.paid: " . print_r($event, true));
        http_response_code(400);
        echo json_encode(['error' => 'Inscription code not found in event data']);
        exit;
    }
} else {
    // Outro tipo de evento ou status que não requer ação imediata no banco de inscrições
    // Ex: withdraw.done, withdraw.failed, billing.created, etc.
    // Registre o evento para auditoria se necessário.
    error_log("Webhook: Evento recebido e ignorado: {$eventType} (devMode: " . ($devMode ? 'sim' : 'não') . ")");
    // Responda com 200 OK para evitar retries desnecessários para eventos não críticos.
    echo json_encode(['received' => true, 'processed' => false, 'reason' => 'event_ignored']);
    exit;
}

// Se nenhum dos blocos anteriores for atendido
http_response_code(400);
echo json_encode(['error' => 'Unhandled event type or data']);

// Função para verificar a assinatura HMAC-SHA256 (copiada da documentação)
function verifyAbacateSignature($rawBody, $signatureFromHeader) {
    $ABACATEPAY_PUBLIC_KEY = ABACATEPAY_WEBHOOK_PUBLIC_KEY; // Defina esta constante no seu config.php
    if (!defined('ABACATEPAY_WEBHOOK_PUBLIC_KEY')) {
        error_log("ABACATEPAY_WEBHOOK_PUBLIC_KEY não definida!");
        return false;
    }

    $bodyBuffer = mb_convert_encoding($rawBody, 'UTF-8', 'UTF-8');

    $expectedSig = hash_hmac("sha256", $bodyBuffer, $ABACATEPAY_PUBLIC_KEY, true);
    $expectedSigBase64 = base64_encode($expectedSig);

    $A = $expectedSigBase64;
    $B = $signatureFromHeader;

    // timingSafeEquals para evitar ataques de temporização
    if (strlen($A) !== strlen($B)) {
        return false;
    }

    $result = 0;
    for ($i = 0; $i < strlen($A); $i++) {
        $result |= ord($A[$i]) ^ ord($B[$i]);
    }

    return $result === 0;
}