<?php
// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

try {
    // ========== BUSCA DIAS DA SEMANA NÃO TRABALHADOS ==========
    // Busca quais dias da semana a clínica NÃO funciona
    // Exemplo: Se ativo = 0 para "Domingo", a clínica não abre aos domingos
    $diasNaoAtivos = $pdo->query("
        SELECT dia_semana 
        FROM Dias_Trabalhados 
        WHERE ativo = 0
    ")->fetchAll(PDO::FETCH_COLUMN); // FETCH_COLUMN retorna só os valores, não array associativo
    // Resultado: ['Domingo', 'Segunda'] ao invés de [['dia_semana' => 'Domingo'], ...]

    // ========== BUSCA FERIADOS ==========
    // Busca todos os feriados cadastrados (datas específicas em que não há atendimento)
    // Exemplo: 25/12/2024 - Natal, 01/01/2025 - Ano Novo
    $feriados = $pdo->query("
        SELECT nome, data 
        FROM Feriados
    ")->fetchAll(PDO::FETCH_ASSOC);
    // Resultado: [['nome' => 'Natal', 'data' => '2024-12-25'], ...]

    // ========== BUSCA PERÍODOS INATIVOS ==========
    // Busca períodos em que a clínica estará fechada (férias, reformas, etc)
    // Exemplo: De 20/12/2024 até 05/01/2025 - Férias
    $periodos = $pdo->query("
        SELECT motivo, data_inicio, data_fim 
        FROM Periodos_Inativos
    ")->fetchAll(PDO::FETCH_ASSOC);
    // Resultado: [['motivo' => 'Férias', 'data_inicio' => '2024-12-20', 'data_fim' => '2025-01-05'], ...]

    // ========== FORMATAÇÃO E RETORNO ==========
    // Monta o objeto JSON com todas as informações
    echo json_encode([
        // Dias da semana em que não há atendimento
        'dias_nao_ativos' => $diasNaoAtivos,
        
        // Feriados com formatação adicional
        'feriados' => array_map(function($feriado) {
            // array_map aplica esta função em cada feriado do array
            return [
                'nome' => $feriado['nome'],           // Nome do feriado
                'data' => $feriado['data'],           // Data do feriado
                'tipo' => 'feriado',                  // Identifica como feriado (para lógica do frontend)
                'preco_tipo' => 'feriado'             // Pode ter preço diferenciado em feriados
            ];
        }, $feriados),
        
        // Períodos de inatividade (sem formatação adicional)
        'periodos' => $periodos
    ]);

} catch (PDOException $e) {
    // ========== TRATAMENTO DE ERRO ==========
    // Salva o erro detalhado no log do servidor
    error_log("Erro em get_dias_indisponiveis.php: " . $e->getMessage());
    
    // Retorna mensagem genérica para o usuário
    echo json_encode(['error' => 'Erro ao consultar dias indisponíveis']);
}

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo retorna TODAS as informações sobre quando a clínica NÃO funciona:
//
// 1. dias_nao_ativos: Array simples com dias da semana
//    Exemplo: ["Domingo", "Segunda"]
//    Uso: Desabilitar esses dias no calendário
//
// 2. feriados: Array de objetos com feriados
//    Exemplo: [{"nome": "Natal", "data": "2024-12-25", "tipo": "feriado", "preco_tipo": "feriado"}]
//    Uso: Bloquear essas datas específicas no calendário
//
// 3. periodos: Array de objetos com períodos de férias/reforma
//    Exemplo: [{"motivo": "Férias", "data_inicio": "2024-12-20", "data_fim": "2025-01-05"}]
//    Uso: Bloquear todas as datas dentro desse intervalo
//
// Uso típico no frontend:
// - Ao carregar o calendário, busca esses dados
// - Desabilita os dias/datas retornados
// - Impede que usuário agende nesses períodos
// - Exibe mensagens explicativas (ex: "Fechado - Feriado: Natal")
?>