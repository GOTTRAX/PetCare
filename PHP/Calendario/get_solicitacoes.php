<?php
session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

include '../conexao.php';

try {
    // Query CORRIGIDA: buscar o TUTOR através do animal (an.usuario_id)
    $query = "
        SELECT 
            a.id,
            a.data_hora as data,
            a.hora_inicio,
            a.hora_final,
            a.observacoes,
            a.status,
            a.servico_id,
            a.animal_id,
            s.nome AS servico_nome,
            an.nome AS animal_nome,
            u.nome AS cliente_nome,
            u.telefone AS cliente_telefone
        FROM Agendamentos a
        INNER JOIN Servicos s ON a.servico_id = s.id
        INNER JOIN Animais an ON a.animal_id = an.id
        INNER JOIN Usuarios u ON an.usuario_id = u.id
        WHERE a.status = 'pendente'
        ORDER BY a.data_hora, a.hora_inicio
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar as solicitações
    $resultado = [];
    foreach ($solicitacoes as $s) {
        $resultado[] = [
            'id' => $s['id'],
            'data' => date('d/m/Y', strtotime($s['data'])),
            'hora_inicio' => substr($s['hora_inicio'], 0, 5),
            'hora_final' => substr($s['hora_final'], 0, 5),
            'animal_nome' => $s['animal_nome'],
            'cliente_nome' => $s['cliente_nome'],
            'servico_nome' => $s['servico_nome'],
            'observacoes' => $s['observacoes'] ?? '',
            'cliente_telefone' => $s['cliente_telefone'] ?? ''
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($resultado);

} catch (PDOException $e) {
    error_log("Erro ao buscar solicitações: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erro ao buscar solicitações',
        'message' => $e->getMessage()
    ]);
}
exit;
//get_solicitacoes.php - RECUPERA agendamentos pendentes
//Faz uma consulta SELECT com JOINs entre várias tabelas
//Busca todos os agendamentos com status 'pendente'
//Formata os dados (datas, horas) para exibição
//Retorna um JSON com a lista de solicitações
?>
