<?php
// Start session to verify user access
session_start();

// Security headers
header('Content-Type: application/json');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Verify session
if (!isset($_SESSION['id']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    error_log("Unauthorized access attempt to fetch_animais.php: tipo_usuario=" . ($_SESSION['tipo_usuario'] ?? 'unset'), 3, "C:/xampp/htdocs/PetCare/errors.log");
    echo json_encode(['erro' => 'Acesso não autorizado.']);
    http_response_code(403);
    exit();
}

// Include database connection
include '/PetCare/PHP/conexao.php';

// Ensure PDO throws exceptions
if (isset($pdo)) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// Query to fetch all species and count animals per species (including zero counts)
$query = "
    SELECT e.id, e.nome AS especie_nome, COUNT(a.id) AS quantidade
    FROM Especies e
    LEFT JOIN Animais a ON a.especie_id = e.id
    GROUP BY e.id, e.nome
    ORDER BY e.nome
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $especies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure the output is an array, even if empty
    echo json_encode($especies ?: []);
} catch (PDOException $e) {
    error_log("Error in fetch_animais.php: {$e->getMessage()}", 3, "C:/xampp/htdocs/PetCare/errors.log");
    echo json_encode(['erro' => 'Erro na consulta: ' . htmlspecialchars($e->getMessage())]);
    http_response_code(500);
}
?>