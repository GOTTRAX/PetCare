<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

require '../conexao.php';

try {
    $usuario_id = $_SESSION['id'];
    $tipo = $_SESSION['tipo_usuario'];

    // SQL com JOINs para buscar TODOS os dados
    $sql = "SELECT 
        a.id, 
        a.data, 
        a.hora_inicio, 
        a.hora_final, 
        a.status,
        a.observacoes,
        a.cliente_id,
        a.animal_id,
        a.servico_id,
        s.nome as servico_nome,
        s.duracao as servico_duracao,
        an.nome as animal_nome,
        an.especie as animal_especie,
        u.nome as cliente_nome,
        u.telefone as cliente_telefone
    FROM Agendamentos a
    INNER JOIN Servicos s ON a.servico_id = s.id
    INNER JOIN Animais an ON a.animal_id = an.id
    INNER JOIN Usuarios u ON a.cliente_id = u.id";

    // Filtrar por tipo de usuário
    if ($tipo === 'Cliente') {
        // Cliente vê apenas seus próprios agendamentos
        $sql .= " WHERE a.cliente_id = :usuario_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    } else {
        // Veterinário e Secretária veem todos
        $stmt = $pdo->prepare($sql);
    }

    $stmt->execute();
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];

    foreach ($agendamentos as $row) {
        // Definir cores por status
        $backgroundColor = '#6366f1';
        $borderColor = '#6366f1';
        
        switch (strtolower($row['status'])) {
            case 'confirmado':
                $backgroundColor = '#10b981'; // verde
                $borderColor = '#10b981';
                break;
            case 'cancelado':
                $backgroundColor = '#ef4444'; // vermelho
                $borderColor = '#ef4444';
                break;
            case 'pendente':
                $backgroundColor = '#f59e0b'; // laranja
                $borderColor = '#f59e0b';
                break;
            case 'finalizado':
                $backgroundColor = '#64748b'; // cinza
                $borderColor = '#64748b';
                break;
        }

        // Montar evento no formato FullCalendar
        $events[] = [
            'id' => $row['id'],
            'title' => $row['servico_nome'] . ' - ' . $row['animal_nome'],
            'start' => $row['data'] . 'T' . $row['hora_inicio'],
            'end' => $row['data'] . 'T' . $row['hora_final'],
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $row['status'],
                'observacoes' => $row['observacoes'],
                // Formato com underscore (padrão)
                'servico_nome' => $row['servico_nome'],
                'animal_nome' => $row['animal_nome'],
                'cliente_nome' => $row['cliente_nome'],
                // Formato sem underscore (compatibilidade)
                'servico' => $row['servico_nome'],
                'animal' => $row['animal_nome'],
                'cliente' => $row['cliente_nome'],
                // Dados adicionais
                'servico_duracao' => $row['servico_duracao'],
                'servico_id' => $row['servico_id'],
                'animal_especie' => $row['animal_especie'],
                'animal_id' => $row['animal_id'],
                'cliente_telefone' => $row['cliente_telefone'],
                'cliente_id' => $row['cliente_id']
            ]
        ];
    }

    // Retornar JSON
    header('Content-Type: application/json');
    echo json_encode($events);

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro no banco de dados',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro inesperado',
        'message' => $e->getMessage()
    ]);
}
?>