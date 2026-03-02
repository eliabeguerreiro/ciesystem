<?php
// app/config/payment_gateway_config.php

// --- MERCADO PAGO ---
// Defina estas constantes como variáveis de ambiente no seu servidor ou em um arquivo .env
// Exemplo de uso com getenv():
define('MERCADOPAGO_ACCESS_TOKEN', getenv('MERCADOPAGO_ACCESS_TOKEN') ?: 'APP_USR-6022213143310641-031403-62350f5e6494e87640809b7577c0f58f-801523614'); // Substitua pelo token real
// define('MERCADOPAGO_CLIENT_ID', getenv('MERCADOPAGO_CLIENT_ID') ?: '6022213143310641');
// define('MERCADOPAGO_CLIENT_SECRET', getenv('MERCADOPAGO_CLIENT_SECRET') ?: '...'); // Geralmente não é necessário para SDK
// define('MERCADOPAGO_RUNTIME_ENV', getenv('MERCADOPAGO_RUNTIME_ENV') ?: 'SERVER'); // Use 'LOCAL' para testes locais
// --- FIM MERCADO PAGO ---

// --- (Opcional) OUTROS GATEWAYS ---
// Ex: AbacatePay (se ainda for necessário)
// define('ABACATEPAY_API_KEY', getenv('ABACATEPAY_API_KEY') ?: '...');
// define('ABACATEPAY_WEBHOOK_SECRET', getenv('ABACATEPAY_WEBHOOK_SECRET') ?: '...');
// define('ABACATEPAY_API_BASE_URL', 'https://api.abacatepay.com/v1');
// define('ABACATEPAY_WEBHOOK_PUBLIC_KEY', getenv('ABACATEPAY_WEBHOOK_PUBLIC_KEY') ?: '...');
// --- FIM OUTROS GATEWAYS ---