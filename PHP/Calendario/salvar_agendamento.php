<?php
session_start();
include '../conexao.php';

// Define que a resposta será em JSON
header('Content-Type: application/json');

// Verifica se é uma requisição POST válida
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['animal_id'])) {
    echo json_encode(['success' => false, 'message' => 'Método inválido ou dados incompletos.']);
    exit;
}

// === RECEBE OS DADOS DO FORMULÁRIO ===
$cliente_id   = $_POST['cliente_id'] ?? $_SESSION['id']; // Secretaria escolhe cliente, Veterinário usa seu ID
$animal_id    = $_POST['animal_id'];
$servico_id   = $_POST['servico_id'];
$data         = $_POST['data'];
$hora_inicio  = $_POST['hora_inicio'];
$hora_final   = $_POST['hora_final'] ?? date("H:i:s", strtotime($hora_inicio . " +1 hour"));
$observacoes  = $_POST['observacoes'] ?? '';

// === VALIDAÇÃO DOS CAMPOS OBRIGATÓRIOS ===
if (empty($cliente_id) || empty($animal_id) || empty($servico_id) || empty($data) || empty($hora_inicio)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// === MONTA A DATA/HORA COMPLETA PARA O CAMPO `data_hora` ===
$data_hora = $data . ' ' . $hora_inicio;

try {
    // === INSERE NO BANCO DE DADOS ===
    $sql = "INSERT INTO Agendamentos 
            (cliente_id, animal_id, servico_id, data_hora, hora_inicio, hora_final, status, observacoes) 
            VALUES (:cliente_id, :animal_id, :servico_id, :data_hora, :hora_inicio, :hora_final, 'pendente', :observacoes)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cliente_id'   => $cliente_id,
        ':animal_id'    => $animal_id,
        ':servico_id'   => $servico_id,
        ':data_hora'    => $data_hora,
        ':hora_inicio'  => $hora_inicio,
        ':hora_final'   => $hora_final,
        ':observacoes'  => $observacoes
    ]);

    // === RETORNA SUCESSO EM JSON ===
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // === ERRO NO BANCO ===
    error_log("Erro ao salvar agendamento: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar. Tente novamente.']);
}

exit;
?>