<?php
require_once __DIR__ . '/../models/Estudante.php';

class EstudanteController {
    private $estudante;

    public function __construct($db) {
        $this->estudante = new Estudante($db);
    }

    // Método para deletar foto antiga (antes de substituir)
    public function deletarFotoAntiga($caminhoRelativo) {
        if (empty($caminhoRelativo)) return;

        $caminhoAbsoluto = __DIR__ . '/../../public/' . ltrim($caminhoRelativo, '/');
        if (file_exists($caminhoAbsoluto)) {
            unlink($caminhoAbsoluto);
        }
    }

    public function uploadFoto($file) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return null;
        }

        $nome = 'estudante_' . uniqid() . '.' . $ext;
        $caminhoAbsoluto = __DIR__ . '/../../public/uploads/fotos/' . $nome;

        if (!is_dir(dirname($caminhoAbsoluto))) {
            mkdir(dirname($caminhoAbsoluto), 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
            return 'uploads/fotos/' . $nome;
        }
        return null;
    }
}