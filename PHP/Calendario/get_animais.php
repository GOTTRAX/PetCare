<?php
include '../conexao.php'; 
header('Content-Type: application/json');

if (!isset($_GET['cliente_id']) || !is_numeric($_GET['cliente_id'])) {
    echo json_encode([]);
    exit;
}

$cliente_id = (int)$_GET['cliente_id'];

try {
    
    $coluna_cliente = 'usuario_id'; 

    $tabela_animais = 'Animais'; 

    $sql = "SELECT id, nome FROM $tabela_animais WHERE $coluna_cliente = ? ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($animais);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados']);
    error_log("Erro em get_animais.php: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
    error_log("Erro em get_animais.php: " . $e->getMessage());
}
?>