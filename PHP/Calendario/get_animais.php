<?php
include '../conexao.php'; 
header('Content-Type: application/json');

// Verifica se o parâmetro cliente_id foi enviado
if (!isset($_GET['cliente_id']) || !is_numeric($_GET['cliente_id'])) {
    echo json_encode([]);
    exit;
}

$cliente_id = (int)$_GET['cliente_id'];

try {
    // MUDE AQUI: Nome da coluna que relaciona o animal ao cliente
    // Exemplos comuns:
    //   - usuario_id
    //   - dono_id
    //   - proprietario_id
    //   - cliente_id (se realmente existir)
    $coluna_cliente = 'usuario_id'; // <--- ALTERE ESTA LINHA SE NECESSÁRIO

    // MUDE AQUI: Nome da tabela dos animais (se for diferente de "Animais")
    $tabela_animais = 'Animais'; // <--- ALTERE SE FOR "animais", "pets", etc.

    $sql = "SELECT id, nome FROM $tabela_animais WHERE $coluna_cliente = ? ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retorna lista de animais como JSON
    echo json_encode($animais);

} catch (PDOException $e) {
    // Erro no banco (ex: coluna ou tabela não existe)
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados']);
    error_log("Erro em get_animais.php: " . $e->getMessage());
    
} catch (Exception $e) {
    // Qualquer outro erro
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
    error_log("Erro em get_animais.php: " . $e->getMessage());
}
?>