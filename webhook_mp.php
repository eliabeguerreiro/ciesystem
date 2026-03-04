<?php
// webhook_mp.php (atualizado para lidar com GET e POST)

// === PASSO 1: DECLARAÇÕES 'use' DEVEM SER AS PRIMEIRAS LINHAS (depois de <?php) ===
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

// === PASSO 2: INCLUIR DEPENDÊNCIAS (Caminhos RELATIVOS à RAIZ do projeto) ===
require_once __DIR__ . '/app/config/payment_gateway_config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Inscricao.php';

// === PASSO 3: VERIFICAÇÃO DE MÉTODO E PROCESSAMENTO ===

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- PROCESSAMENTO DO WEBHOOK (POST + JSON) ---
    // Lê o corpo da requisição (raw body)
    $rawBody = file_get_contents('php://input');
    $headers = getallheaders();

    // Decodifica o JSON do corpo da requisição
    $event = json_decode($rawBody, true);

    // Validação básica do JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        error_log("Webhook MP (POST): Erro de JSON - " . json_last_error_msg() . " - Raw Body: " . $rawBody);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Verifica o tipo de evento - Esperamos um evento de pagamento
    $eventType = $event['action'] ?? $event['type'] ?? null;
    $resourceId = $event['data']['id'] ?? null;

    if ($eventType === 'payment.created' || $eventType === 'payment.updated' || $eventType === 'payment') {
        if ($resourceId) {
            // ... (lógica de busca e atualização do pagamento aprovado, igual ao código anterior) ...
            // (Insira aqui o bloco de código que faz a chamada à API do MP e atualiza o banco)
            // Exemplo resumido:
            if (!defined('MERCADOPAGO_ACCESS_TOKEN') || empty(MERCADOPAGO_ACCESS_TOKEN)) {
                 error_log("Webhook MP: MERCADOPAGO_ACCESS_TOKEN não está definido ou está vazio.");
                 http_response_code(500);
                 echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
                 exit;
             }
             MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);
             $paymentClient = new PaymentClient();

             try {
                 $payment = $paymentClient->get($resourceId);
                 $paymentStatus = $payment->status;
                 $externalReference = $payment->external_reference;

                 if ($paymentStatus === 'approved' && $externalReference) {
                     $database = new Database();
                     $db = $database->getConnection();
                     $inscricaoModel = new Inscricao($db);

                     $query = "SELECT id FROM inscricoes WHERE codigo_inscricao = :codigo";
                     $stmt = $db->prepare($query);
                     $stmt->bindParam(':codigo', $externalReference);
                     $stmt->execute();
                     $inscricao = $stmt->fetch(PDO::FETCH_ASSOC);

                     if ($inscricao) {
                         $inscricaoModel->id = $inscricao['id'];
                         if ($inscricaoModel->atualizarPagamentoConfirmado(true)) {
                             $inscricaoModel->atualizarSituacao('pago');
                             error_log("Webhook MP: Pagamento aprovado confirmado para inscrição {$externalReference} (DB ID: {$inscricao['id']}, MP ID: {$resourceId}).");
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
                         http_response_code(200);
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
            error_log("Webhook MP: ID do recurso ausente na notificação POST. Raw Body: " . $rawBody);
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Resource ID missing in notification.']);
            exit;
        }
    } else {
         error_log("Webhook MP: Evento recebido e ignorado (POST). Tipo: {$eventType}. Detalhes: " . print_r($event, true));
         http_response_code(200);
         echo json_encode(['status' => 'ignored', 'message' => 'Event type ignored.', 'type' => $eventType]);
         exit;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- PROCESSAMENTO DO REDIRECIONAMENTO (GET + Query String) ---
    // Aqui você pode lidar com os parâmetros recebidos via GET.
    // O mais importante é o 'external_reference' que você usou na preferência.
    $externalReference = $_GET['external_reference'] ?? null;
    $paymentId = $_GET['payment_id'] ?? null; // Pode vir como string "null" ou estar ausente
    $status = $_GET['status'] ?? null;       // Pode vir como string "null" ou estar ausente
    $collectionStatus = $_GET['collection_status'] ?? null; // Pode vir como string "null" ou estar ausente

    // Verifique se external_reference foi fornecido
    if ($externalReference) {
        // Opcional: Você pode verificar o status recebido via GET (pending, approved, rejected)
        // Mas a confirmação definitiva deve vir do webhook POST.
        // Por enquanto, apenas redirecione para acompanhar.php com o código da inscrição.
        // É importante que o webhook POST já tenha sido processado antes disso para atualizar o banco.
        // Esta página GET serve mais para o usuário visualizar o status no frontend.
        $redirectUrl = "acompanhar.php"; // Redireciona para a página de acompanhamento
        header("Location: $redirectUrl");
        exit; // Encerra após o redirecionamento
    } else {
        // external_reference ausente, redireciona para início ou mostra erro
        error_log("Webhook MP (GET): external_reference ausente na URL: " . $_SERVER['REQUEST_URI']);
        header("Location: index.php");
        exit;
    }
} else {
    // Método não suportado (talvez PUT, DELETE, etc.)
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Se nenhum bloco if for atendido
http_response_code(400);
echo json_encode(['error' => 'Unhandled request method or structure.']);
exit;