<?php
// app/config/abacatepay_config.php

// Defina estas constantes como variáveis de ambiente no seu servidor ou em um arquivo .env
// Exemplo de uso com getenv():
define('ABACATEPAY_API_KEY', getenv('ABACATEPAY_API_KEY') ?: 'sua_chave_api_dev_aqui');
define('ABACATEPAY_WEBHOOK_SECRET', getenv('ABACATEPAY_WEBHOOK_SECRET') ?: 'seu_webhook_secret_aqui');
define('ABACATEPAY_API_BASE_URL', 'https://api.abacatepay.com/v1'); // URL base da API

// Constante para o valor do pagamento (pode vir de configuração ou DB)
define('VALOR_PAGAMENTO_CIE', 25.00); // Exemplo: R$ 25,00