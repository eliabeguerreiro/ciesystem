<?php
// webhook_mp.php

// === PASSO 1: DECLARAÇÕES 'use' DEVEM SER AS PRIMEIRAS LINHAS ===
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

require_once __DIR__ . '../app/config/payment_gateway_config.php';
require_once __DIR__ . '../vendor/autoload.php';
require_once __DIR__ . '../app/config/database.php';
require_once __DIR__ . '../app/models/Inscricao.php';

// === PASSO 3: LÓGICA DO WEBHOOK ===
$rawBody = file_get_contents('php://input');
$headers = getallheaders();

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
$paymentId = $event['data']['id'] ?? null;

if ($eventType === 'payment' && $paymentId) {
    try {
        // Configura o SDK
        MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

        // Busca os detalhes do pagamento
        $paymentClient = new PaymentClient();
        $payment = $paymentClient->get($paymentId);

        $externalReference = $payment->external_reference ?? null;
        $paymentStatus = $payment->status ?? null;

        if ($paymentStatus === 'approved' && $externalReference) {
            $database = new Database();
            $db = $database->getConnection();
            $inscricaoModel = new Inscricao($db);

            // Busca a inscrição pelo external_reference
            $query = "SELECT id FROM inscricoes WHERE codigo_inscricao = :codigo";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $externalReference);
            $stmt->execute();
            $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($inscricao) {
                $inscricaoModel->id = $inscricao['id'];
                if ($inscricaoModel->atualizarPagamentoConfirmado(true)) {
                    $inscricaoModel->atualizarSituacao('pago');
                    error_log("Webhook MP: Pagamento aprovado para inscrição {$externalReference} (ID: {$inscricao['id']})");
                    echo json_encode(['status' => 'success', 'message' => 'Payment confirmed and inscription updated.']);
                    http_response_code(200);
                    exit;
                } else {
                    error_log("Erro ao atualizar pagamento_confirmado para inscrição {$externalReference}");
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update database']);
                    exit;
                }
            } else {
                error_log("Inscrição não encontrada para external_reference: {$externalReference}");
                http_response_code(200); // 200 para evitar retries
                echo json_encode(['status' => 'warning', 'message' => 'Inscription not found']);
                exit;
            }
        } else {
            error_log("Pagamento não aprovado ou external_reference ausente. Status: {$paymentStatus}, Ref: {$externalReference}");
            http_response_code(200);
            echo json_encode(['status' => 'info', 'message' => 'Payment not approved or reference missing']);
            exit;
        }
    } catch (MPApiException $e) {
        error_log("Erro na API do MP: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'MP API error']);
        exit;
    } catch (Exception $e) {
        error_log("Erro geral: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'General error']);
        exit;
    }
} else {
    // Evento não suportado ou dados inválidos
    error_log("Evento ignorado: " . print_r($event, true));
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'message' => 'Event type not handled']);
    exit;
}