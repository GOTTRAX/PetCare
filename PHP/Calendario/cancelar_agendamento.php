<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $agendamento_id = $input['id'];
    $usuario_id = $_SESSION['id'];
    
    try {
        // Verificar se o agendamento pertence ao usuário
        $stmt = $pdo->prepare("SELECT id FROM Agendamentos WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$agendamento_id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            $update = $pdo->prepare("UPDATE Agendamentos SET status = 'cancelado' WHERE id = ?");
            $update->execute([$agendamento_id]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Agendamento não encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>