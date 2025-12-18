<?php
require_once __DIR__ . '/../models/Carteirinha.php';

class CarteirinhaController {
    private $carteirinha;

    public function __construct($db) {
        $this->carteirinha = new Carteirinha($db);
    }

    // Upload de comprovante (aceita JPG, PNG, PDF)
    public function uploadComprovante($file, $tipo = 'matricula') {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = $tipo === 'pagamento' 
            ? ['jpg', 'jpeg', 'png', 'pdf'] 
            : ['jpg', 'jpeg', 'png', 'pdf']; // ambos aceitam os mesmos

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return null;
        }

        $nome = "comprovante_{$tipo}_" . uniqid() . '.' . $ext;
        $subdir = $tipo === 'pagamento' ? 'pagamento' : 'matricula';
        $caminhoAbsoluto = __DIR__ . "/../../public/uploads/comprovantes/{$subdir}/{$nome}";

        if (!is_dir(dirname($caminhoAbsoluto))) {
            mkdir(dirname($caminhoAbsoluto), 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
            return "uploads/comprovantes/{$subdir}/{$nome}";
        }
        return null;
    }
}