<?php
// webhook_mp.php

// Inclui a configuração do banco de dados
require_once __DIR__ . '../app/config/database.php';
require_once __DIR__ . '../app/models/Inscricao.php';
// Inclui Log se quiser registrar eventos
// require_once __DIR__ . '/../app/models/Log.php';

// Lê o corpo da requisição (raw body)
$rawBody = file_get_contents('php://input');
$headers = getallheaders();

// O Mercado Pago envia um header 'x-idempotency-key' e 'x-platform-id', mas a confirmação real vem via notificação POST
// O mais importante é o 'topic' e o 'id' enviado via query string ou o conteúdo do body
// Para pagamentos, o body contém o 'merchant_order_id' ou 'payment_id'.
// O fluxo típico é: Receber notificação -> Buscar detalhes do pagamento via API -> Atualizar banco.

// Exemplo de como pode vir o body (JSON):
/*
{
  "id": "123456789",
  "live_mode": true,
  "type": "payment",
  "date_created": "2026-03-04T10:00:00.000-03:00",
  "application_id": null,
  "user_id": 123456789,
  "version": null,
  "data": {
    "id": "987654321" // Este é o ID do pagamento
  }
}
*/

// Decodifica o JSON do corpo
$event = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    error_log("Webhook MP: Invalid JSON received: " . $rawBody);
    exit;
}

// Processa o evento
$eventType = $event['type'] ?? null;
$paymentId = $event['data']['id'] ?? null; // ID do pagamento no Mercado Pago

if ($eventType === 'payment' && $paymentId) {
    // Agora, precisamos buscar os detalhes do pagamento para confirmar status e external_reference
    // Isso requer uma chamada à API do Mercado Pago usando o SDK ou cURL.
    // Para simplificar, vamos simular a obtenção do external_reference a partir do ID do pagamento.
    // Na prática, você faria: $payment = MercadoPago\Payment::find_by_id($paymentId);

    // --- SIMULAÇÃO (SUBSTITUA PELO CÓDIGO REAL DA API DO MERCADO PAGO) ---
    // Suponha que você tenha uma função que busca os detalhes do pagamento pelo ID
    // e retorne o 'external_reference' e o 'status'.
    // Exemplo com SDK (precisa instanciar o cliente):
    // use MercadoPago\Client\Payment\PaymentClient; // Adicione ao topo
    // $paymentClient = new PaymentClient(); // Adicione aqui
    // $payment = $paymentClient->get($paymentId); // Adicione aqui
    // $externalReference = $payment->external_reference;
    // $paymentStatus = $payment->status;

    // --- FIM SIMULAÇÃO ---

    // --- IMPLEMENTACAO REAL (Exemplo Básico com cURL e SDK) ---
    // Primeiro, configure o SDK para obter o pagamento
    require_once __DIR__ . '/../app/config/payment_gateway_config.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    use MercadoPago\Client\Payment\PaymentClient;
    use MercadoPago\MercadoPagoConfig;
    use MercadoPago\Exceptions\MPApiException;

    MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

    $paymentClient = new PaymentClient();
    try {
        $payment = $paymentClient->get($paymentId);

        $externalReference = $payment->external_reference; // O código da inscrição
        $paymentStatus = $payment->status; // 'approved', 'rejected', 'in_process', etc.

        if ($paymentStatus === 'approved' && $externalReference) {
            $database = new Database();
            $db = $database->getConnection();
            $inscricaoModel = new Inscricao($db);

            // Busca a inscrição pelo external_reference (código da inscrição)
            $query = "SELECT id FROM inscricoes WHERE codigo_inscricao = :codigo";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $externalReference);
            $stmt->execute();
            $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($inscricao) {
                $inscricaoId = $inscricao['id'];
                $inscricaoModel->id = $inscricaoId;

                // Atualiza o status do pagamento e da inscrição
                if ($inscricaoModel->atualizarPagamentoConfirmado(true)) {
                    $inscricaoModel->atualizarSituacao('pago'); // Atualiza situação para 'pago'

                    // Log do evento (opcional)
                    // require_once __DIR__ . '/../app/models/Log.php';
                    // $logModel = new Log($db);
                    // $logModel->registrar(null, 'webhook_pagamento_mp_confirmado', "Pagamento MP confirmado para inscrição {$externalReference} (ID: {$inscricaoId})", $inscricaoId, 'inscricoes');

                    echo json_encode(['received' => true, 'processed' => true]);
                    error_log("Webhook MP: Pagamento aprovado para inscrição {$externalReference} (ID: {$inscricaoId})");
                    exit;
                } else {
                    // Erro ao atualizar o banco
                    error_log("Erro ao atualizar pagamento_confirmado para inscrição {$externalReference} (MP ID: {$paymentId})");
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update database']);
                    exit;
                }
            } else {
                // Inscrição não encontrada
                error_log("Webhook MP: Inscrição {$externalReference} não encontrada para pagamento MP {$paymentId}.");
                // Mesmo não encontrando, retornamos 200 para evitar retries, pois pode ser um pagamento inválido ou antigo.
                echo json_encode(['received' => true, 'processed' => false, 'reason' => 'inscription_not_found']);
                exit;
            }
        } else {
            // Pagamento não aprovado ou external_reference ausente
            error_log("Webhook MP: Pagamento {$paymentId} não aprovado (status: {$paymentStatus}) ou external_reference ausente ({$externalReference}).");
            echo json_encode(['received' => true, 'processed' => false, 'reason' => 'payment_not_approved_or_no_ref']);
            exit;
        }
    } catch (MPApiException $e) {
        error_log("Erro na API do MP ao buscar pagamento {$paymentId}: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error fetching payment details from MP']);
        exit;
    } catch (Exception $e) {
        error_log("Erro geral ao processar webhook MP para pagamento {$paymentId}: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'General error processing webhook']);
        exit;
    }
    // --- FIM IMPLEMENTACAO REAL ---

} else {
    // Outro tipo de evento ou dados inválidos
    error_log("Webhook MP: Evento recebido e ignorado ou inválido: " . print_r($event, true));
    echo json_encode(['received' => true, 'processed' => false, 'reason' => 'event_ignored_or_invalid']);
    exit;
}

// Se nenhum dos blocos anteriores for atendido
http_response_code(400);
echo json_encode(['error' => 'Unhandled event type or data']);