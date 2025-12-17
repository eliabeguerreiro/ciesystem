<?php
if (isset($_SESSION['contador'])) {
    $_SESSION['contador']++;
} else {
    $_SESSION['contador'] = 1;
}
echo "Sessão está funcionando! Contador: " . $_SESSION['contador'];
?>