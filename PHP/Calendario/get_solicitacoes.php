<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// ========== VALIDAÇÃO DE AUTENTICAÇÃO ==========
// Verifica se o usuário está logado
if (!isset($_SESSION['id'])) {
    // Define código HTTP 401 (não autorizado)
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

try {
    // ========== QUERY PARA BUSCAR SOLICITAÇÕES PENDENTES ==========
    // Busca todos os agendamentos com status 'pendente' (aguardando aprovação)
    // Faz JOINs para pegar informações relacionadas
    $query = "
        SELECT 
            a.id,                           -- ID do agendamento
            a.data_hora as data,            -- Data do agendamento
            a.hora_inicio,                  -- Hora de início
            a.hora_final,                   -- Hora de término
            a.observacoes,                  -- Observações do cliente
            a.status,                       -- Status (sempre será 'pendente' nesta query)
            a.servico_id,                   -- ID do serviço
            a.animal_id,                    -- ID do animal
            s.nome AS servico_nome,         -- Nome do serviço (JOIN com Servicos)
            an.nome AS animal_nome,         -- Nome do animal (JOIN com Animais)
            u.nome AS cliente_nome,         -- Nome do TUTOR (JOIN via animal -> usuario_id)
            u.telefone AS cliente_telefone  -- Telefone do TUTOR
        FROM Agendamentos a
        INNER JOIN Servicos s ON a.servico_id = s.id     -- Pega dados do serviço
        INNER JOIN Animais an ON a.animal_id = an.id     -- Pega dados do animal
        INNER JOIN Usuarios u ON an.usuario_id = u.id    -- Pega dados do TUTOR do animal
        WHERE a.status = 'pendente'                      -- Filtra apenas pendentes
        ORDER BY a.data_hora, a.hora_inicio              -- Ordena por data e hora
    ";

    // ========== EXECUÇÃO DA QUERY ==========
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== FORMATAÇÃO DOS DADOS ==========
    // Transforma os dados brutos do banco em formato amigável para exibição
    $resultado = [];
    
    foreach ($solicitacoes as $s) {
        $resultado[] = [
            'id' => $s['id'],
            
            // Formata data de YYYY-MM-DD para DD/MM/YYYY
            // Exemplo: 2024-12-25 -> 25/12/2024
            'data' => date('d/m/Y', strtotime($s['data'])),
            
            // Remove os segundos do horário (HH:MM:SS -> HH:MM)
            // Exemplo: 14:30:00 -> 14:30
            'hora_inicio' => substr($s['hora_inicio'], 0, 5),
            'hora_final' => substr($s['hora_final'], 0, 5),
            
            // Informações do agendamento
            'animal_nome' => $s['animal_nome'],
            'cliente_nome' => $s['cliente_nome'],
            'servico_nome' => $s['servico_nome'],
            
            // Se observacoes ou telefone forem null, usa string vazia
            'observacoes' => $s['observacoes'] ?? '',
            'cliente_telefone' => $s['cliente_telefone'] ?? ''
        ];
    }

    // ========== RETORNO DOS DADOS ==========
    header('Content-Type: application/json');
    echo json_encode($resultado);

} catch (PDOException $e) {
    // ========== TRATAMENTO DE ERRO ==========
    // Salva erro detalhado no log do servidor
    error_log("Erro ao buscar solicitações: " . $e->getMessage());
    
    // Define código HTTP 500 (erro interno)
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Retorna erro em JSON
    echo json_encode([
        'error' => 'Erro ao buscar solicitações',
        'message' => $e->getMessage()
    ]);
}

// Garante que nada mais será executado
exit;

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo é usado para listar solicitações de agendamento que precisam ser aprovadas
//
// Fluxo completo:
// 1. Verifica se o usuário está logado (sessão)
// 2. Busca TODOS os agendamentos com status = 'pendente'
// 3. Faz JOINs para pegar:
//    - Nome do serviço (tabela Servicos)
//    - Nome do animal (tabela Animais)
//    - Nome e telefone do TUTOR (tabela Usuarios via animal.usuario_id)
// 4. Formata os dados:
//    - Data: YYYY-MM-DD -> DD/MM/YYYY
//    - Hora: HH:MM:SS -> HH:MM
// 5. Retorna array JSON com todas as solicitações
//
// Uso típico:
// - Painel de administração (Veterinário/Secretária)
// - Lista todas as solicitações pendentes de aprovação
// - Permite aprovar ou rejeitar cada uma
//
// Exemplo de retorno:
// [
//   {
//     "id": 123,
//     "data": "25/12/2024",
//     "hora_inicio": "14:30",
//     "hora_final": "15:00",
//     "animal_nome": "Rex",
//     "cliente_nome": "João Silva",
//     "servico_nome": "Consulta",
//     "observacoes": "Animal com tosse",
//     "cliente_telefone": "(11) 98765-4321"
//   },
//   ...
// ]
?>