<?php
include '../conexao.php';

header('Content-Type: application/json');

try {
    // Busca dias não trabalhados
    $diasNaoAtivos = $pdo->query("
        SELECT dia_semana 
        FROM Dias_Trabalhados 
        WHERE ativo = 0
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Busca feriados
    $feriados = $pdo->query("
        SELECT nome, data 
        FROM Feriados
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Busca períodos inativos
    $periodos = $pdo->query("
        SELECT motivo, data_inicio, data_fim 
        FROM Periodos_Inativos
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Retorno estruturado
    echo json_encode([
        'dias_nao_ativos' => $diasNaoAtivos,
        'feriados' => array_map(function($feriado) {
            return [
                'nome' => $feriado['nome'],
                'data' => $feriado['data'],
                'tipo' => 'feriado',
                'preco_tipo' => 'feriado'
            ];
        }, $feriados),
        'periodos' => $periodos
    ]);
} catch (PDOException $e) {
    error_log("Erro em get_dias_indisponiveis.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao consultar dias indisponíveis']);
}
?>