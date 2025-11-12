<?php
session_start();
require '../conexao.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    
    if (!$id || $id === '') {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }

    $usuario_id = $_SESSION['id'];
    $tipo_usuario = $_SESSION['tipo_usuario'];

    // Verificar se o agendamento existe e se o usuário tem permissão
    $sql = "SELECT a.id, a.cliente_id, u.nome as cliente_nome 
            FROM Agendamentos a
            JOIN Usuarios u ON a.cliente_id = u.id
            WHERE a.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agendamento) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
        exit;
    }

    // Verificar permissão
    // Cliente só pode excluir seus próprios agendamentos
    if ($tipo_usuario === 'Cliente' && $agendamento['cliente_id'] != $usuario_id) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este agendamento']);
        exit;
    }

    // Veterinário e Secretária podem excluir qualquer agendamento
    // Excluir o agendamento
    $stmt = $pdo->prepare("DELETE FROM Agendamentos WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Agendamento excluído com sucesso',
            'deleted_id' => $id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir agendamento no banco de dados']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>