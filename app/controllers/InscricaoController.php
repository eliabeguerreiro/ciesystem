<?php
require_once __DIR__ . '/../models/Inscricao.php';

class InscricaoController {
    private $inscricao;

    public function __construct($db) {
        $this->inscricao = new Inscricao($db);
    }

    // Reutiliza o mesmo método de upload (opcional, mas mantemos consistência)
    public function uploadComprovante($file, $tipo = 'matricula') {
        // Mesma lógica do CarteirinhaController, mas opcional
        // Na verdade, o upload é feito no modelo agora, então este controller pode ficar vazio
        // ou ser usado futuramente para validações específicas.
    }
}
?>