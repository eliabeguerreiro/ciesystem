<?php
// retornosucesso.php
$status = $_GET['status'] ?? '';
if ($status === 'approved') {
    echo "<h2>Pagamento aprovado!</h2>";
    echo "<a href='acompanhar.php'>Acompanhar inscrição</a>";
} else {
    echo "<h2>Verifique o status</h2>";
}
?>
