<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

$usuario_id = $_SESSION['id'];
$tipo_usuario = $_SESSION['tipo_usuario'];

// Get date range from FullCalendar (optional)
$start_date = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_STRING);

try {
    $query = "
        SELECT 
            a.id,
            a.data_hora,
            a.hora_inicio,
            a.hora_final,
            a.observacoes,
            a.status,
            a.servico_id,
            a.animal_id,
            a.cliente_id,
            s.nome AS servico,
            s.duracao AS servico_duracao,
            an.nome AS animal,
            e.nome AS animal_especie,
            u.nome AS cliente,
            u.telefone AS cliente_telefone
        FROM Agendamentos a
        INNER JOIN Servicos s ON a.servico_id = s.id
        INNER JOIN Animais an ON a.animal_id = an.id
        INNER JOIN Usuarios u ON a.cliente_id = u.id
        LEFT JOIN Especies e ON an.especie_id = e.id
        WHERE 1=1
    ";
    $params = [];

    // Filter by client ID if user is a client
    if ($tipo_usuario === 'Cliente') {
        $query .= " AND a.cliente_id = ?";
        $params[] = $usuario_id;
    }

    // Filter by date range if provided
    if ($start_date && $end_date) {
        $query .= " AND a.data_hora BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    // Order by date and time
    $query .= " ORDER BY a.data_hora, a.hora_inicio";

    // Execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format events for FullCalendar
    $events = [];
    foreach ($appointments as $appt) {
        // Definir cor baseada no status
        $backgroundColor = '#6366f1';
        $borderColor = '#6366f1';
        
        switch (strtolower($appt['status'])) {
            case 'confirmado':
                $backgroundColor = '#10b981'; 
                $borderColor = '#10b981';
                break;
            case 'cancelado':
                $backgroundColor = '#ef4444'; 
                $borderColor = '#ef4444';
                break;
            case 'pendente':
                $backgroundColor = '#f59e0b'; 
                $borderColor = '#f59e0b';
                break;
            case 'finalizado':
                $backgroundColor = '#64748b'; 
                $borderColor = '#64748b';
                break;
        }

        $events[] = [
            'id' => $appt['id'],
            'title' => $appt['servico'] . ' - ' . $appt['animal'],
            'start' => $appt['data_hora'] . 'T' . $appt['hora_inicio'],
            'end' => $appt['data_hora'] . 'T' . $appt['hora_final'],
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
            'textColor' => '#ffffff',
            'extendedProps' => [
                // Formato original (sem underscore) - para compatibilidade
                'animal' => $appt['animal'],
                'servico' => $appt['servico'],
                'cliente' => $appt['cliente'],
                // Formato com underscore (novo padrão)
                'animal_nome' => $appt['animal'],
                'servico_nome' => $appt['servico'],
                'cliente_nome' => $appt['cliente'],
                // IDs
                'servico_id' => $appt['servico_id'],
                'animal_id' => $appt['animal_id'],
                'cliente_id' => $appt['cliente_id'],
                // Status e observações
                'status' => $appt['status'],
                'observacoes' => $appt['observacoes'] ?? '',
                'hora_final' => $appt['hora_final'],
                // Dados adicionais
                'servico_duracao' => $appt['servico_duracao'] ?? 30,
                'animal_especie' => $appt['animal_especie'] ?? '',
                'cliente_telefone' => $appt['cliente_telefone'] ?? ''
            ]
        ];
    }

    echo json_encode($events);

} catch (PDOException $e) {
    error_log("Erro ao buscar agendamentos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar agendamentos',
        'message' => $e->getMessage()
    ]);
}
exit;
?>