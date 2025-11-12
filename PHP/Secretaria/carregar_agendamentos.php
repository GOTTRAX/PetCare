<?php
session_start();
require_once '../conexao.php';

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    echo json_encode(['html' => '', 'more' => false]);
    exit;
}

$offset = (int)($_GET['offset'] ?? 0);
$limit = (int)($_GET['limit'] ?? 10);

try {
    $stmt = $pdo->prepare("
        SELECT a.data_hora, a.hora_inicio, a.status, u.nome as tutor_nome, an.nome as animal_nome, COALESCE(s.nome, 'Não especificado') as procedimento
        FROM Agendamentos a 
        INNER JOIN Usuarios u ON a.cliente_id = u.id AND u.tipo_usuario = 'Cliente'
        INNER JOIN Animais an ON a.animal_id = an.id 
        LEFT JOIN Servicos s ON a.servico_id = s.id 
        WHERE a.data_hora >= CURDATE() 
        ORDER BY a.data_hora, a.hora_inicio 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    foreach ($agendamentos as $ag) {
        $html .= "<li class='agendamento-item'>
            <div class='agendamento-info'>
                <h4>" . htmlspecialchars($ag['animal_nome']) . "</h4>
                <p>" . htmlspecialchars($ag['tutor_nome']) . "</p>
                <p class='agendamento-procedimento'>" . htmlspecialchars($ag['procedimento']) . "</p>
            </div>
            <div class='agendamento-details'>
                <span class='agendamento-time'>" . date('d/m', strtotime($ag['data_hora'])) . " • " . substr($ag['hora_inicio'], 0, 5) . "</span>
                <span class='agendamento-status status-" . strtolower($ag['status']) . "'>" . htmlspecialchars(ucfirst($ag['status'])) . "</span>
            </div>
        </li>";
    }

    $stmtCount = $pdo->query("SELECT COUNT(*) FROM Agendamentos a INNER JOIN Usuarios u ON a.cliente_id = u.id AND u.tipo_usuario = 'Cliente' WHERE a.data_hora >= CURDATE()");
    $total = $stmtCount->fetchColumn();
    $more = ($offset + $limit) < $total;

    echo json_encode(['html' => $html, 'more' => $more]);
} catch (Exception $e) {
    error_log("Erro AJAX: " . $e->getMessage());
    echo json_encode(['html' => '', 'more' => false]);
}
?>