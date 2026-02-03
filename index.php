<?php
// Página pública — sem sessão, sem login
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sistema de Inscrição - CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; text-align: center; }
        h1 { color: #1976d2; }
        .btn {
            display: inline-block;
            margin: 20px 10px;
            padding: 12px 24px;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn:hover { background: #1565c0; }
        .info { background: white; padding: 20px; border-radius: 8px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Carteira de Identificação Estudantil (CIE)</h1>
        <p>Bem-vindo ao sistema de inscrição para a CIE.</p>
        
        <a href="inscricao.php" class="btn">Quero minha CIE</a>
        <a href="acompanhar.php" class="btn">Acompanhar Inscrição</a>

        <div class="info">
            <h3>Como funciona?</h3>
            <p>1. Preencha seus dados e anexe seu comprovante de matrícula<br>
               2. Aguarde a validação pela equipe<br>
               3. Após aprovação, realize o pagamento<br>
               4. Sua CIE será emitida</p>
        </div>
    </div>
</body>
</html>