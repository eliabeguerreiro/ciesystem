<?php
// webhook_abacatepay.php

// Inclui a configuração da API e o banco de dados
require_once __DIR__ . '/app/config/abacatepay_config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Inscricao.php';

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
$paymentData = $event['data']['payment'] ?? null;
$pixData = $event['data']['pixQrCode'] ?? null; // Dados específicos do PIX
$devMode = $event['devMode'] ?? false; // Indica se é ambiente de testes

if ($eventType === 'billing.paid' && $pixData && $pixData['status'] === 'PAID') {
    // Este evento indica que um pagamento PIX foi confirmado
    // A descrição do pagamento pode conter o código da inscrição
    $descricaoPagamento = $pixData['description'] ?? '';
    $codigoInscricao = null;

    // Extrai o código da inscrição da descrição (assumindo o formato do seu sistema)
    // Ex: "Pagamento anuidade CIE - Inscrição: f10245d4-a932-4044-9a95-ef493af3986f - Nome do Estudante"
    if (preg_match('/Inscrição: ([a-f0-9\-]+)/i', $descricaoPagamento, $matches)) {
        $codigoInscricao = $matches[1];
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
                $logModel->registrar(null, 'webhook_pagamento_confirmado', "Pagamento PIX confirmado para inscrição {$codigoInscricao} (ID: {$inscricaoId})", $inscricaoId, 'inscricoes');

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
            error_log("Webhook: Inscrição {$codigoInscricao} não encontrada para pagamento confirmado.");
            // Mesmo não encontrando, retornamos 200 para evitar retries, pois pode ser um pagamento inválido ou antigo.
            echo json_encode(['received' => true, 'processed' => false, 'reason' => 'inscription_not_found']);
            exit;
        }
    } else {
        // Código da inscrição não encontrado na descrição
        error_log("Webhook: Código da inscrição não encontrado na descrição: {$descricaoPagamento}");
        http_response_code(400);
        echo json_encode(['error' => 'Inscription code not found in description']);
        exit;
    }
} elseif ($eventType === 'billing.paid' && $paymentData && $paymentData['method'] === 'CARD') {
    // Processar pagamento via CARTÃO (lógica similar ao PIX)
    // A descrição do pagamento ou outros campos do $event['data'] podem conter o código da inscrição
    // Exemplo: $descricaoPagamento = $event['data']['transaction']['description'] ?? $event['data']['payment']['description'] ?? '';
    // A lógica de extração do código e atualização do banco será a mesma.
    // Por simplicidade, vamos assumir o mesmo padrão de descrição para cartão.
    $descricaoPagamento = $paymentData['description'] ?? '';
    $codigoInscricao = null;

    if (preg_match('/Inscrição: ([a-f0-9\-]+)/i', $descricaoPagamento, $matches)) {
        $codigoInscricao = $matches[1];
    }

    if ($codigoInscricao) {
        $database = new Database();
        $db = $database->getConnection();
        $inscricaoModel = new Inscricao($db);

        $query = "SELECT id FROM inscricoes WHERE codigo_inscricao = :codigo";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo', $codigoInscricao);
        $stmt->execute();
        $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inscricao) {
            $inscricaoId = $inscricao['id'];
            $inscricaoModel->id = $inscricaoId;

            if ($inscricaoModel->atualizarPagamentoConfirmado(true)) {
                $inscricaoModel->atualizarSituacao('pago');

                require_once __DIR__ . '/../app/models/Log.php';
                $logModel = new Log($db);
                $logModel->registrar(null, 'webhook_pagamento_confirmado_cartao', "Pagamento CARTÃO confirmado para inscrição {$codigoInscricao} (ID: {$inscricaoId})", $inscricaoId, 'inscricoes');

                echo json_encode(['received' => true, 'processed' => true]);
                exit;
            } else {
                error_log("Erro ao atualizar pagamento_confirmado para inscrição {$codigoInscricao} (Cartão)");
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update database']);
                exit;
            }
        } else {
            error_log("Webhook: Inscrição {$codigoInscricao} não encontrada para pagamento via CARTÃO confirmado.");
            echo json_encode(['received' => true, 'processed' => false, 'reason' => 'inscription_not_found']);
            exit;
        }
    } else {
        error_log("Webhook: Código da inscrição não encontrado na descrição (Cartão): {$descricaoPagamento}");
        http_response_code(400);
        echo json_encode(['error' => 'Inscription code not found in description (Card)']);
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

// --- IMPORTANTE: Adicione esta constante no seu app/config/abacatepay_config.php ---
// define('ABACATEPAY_WEBHOOK_PUBLIC_KEY', 'sua_chave_publica_hmac_da_abacatepay_aqui');
// Esta chave pública é fornecida pela AbacatePay para verificar a integridade do webhook.
// Substitua 'sua_chave_publica_hmac_da_abacatepay_aqui' pela chave real obtida no dashboard da AbacatePay.
// --- FIM IMPORTANTE ---