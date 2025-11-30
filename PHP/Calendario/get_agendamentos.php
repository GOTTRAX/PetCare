<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// ========== VALIDAÇÃO DE AUTENTICAÇÃO ==========
// Verifica se o usuário está logado
if (!isset($_SESSION['id'])) {
    // Define código HTTP 401 (não autorizado)
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// ========== DADOS DO USUÁRIO LOGADO ==========
// Pega ID e tipo do usuário da sessão
$usuario_id = $_SESSION['id'];
$tipo_usuario = $_SESSION['tipo_usuario'];

// ========== FILTROS OPCIONAIS DE DATA ==========
// Pega parâmetros de data da URL (usado pelo FullCalendar para buscar eventos de um período)
// FILTER_SANITIZE_STRING remove caracteres perigosos
$start_date = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_STRING);

try {
    // ========== CONSTRUÇÃO DA QUERY ==========
    // Busca TODOS os dados necessários do agendamento com JOINs
    $query = "
        SELECT 
            a.id,                          -- ID do agendamento
            a.data_hora,                   -- Data do agendamento
            a.hora_inicio,                 -- Hora de início
            a.hora_final,                  -- Hora de término
            a.observacoes,                 -- Observações do agendamento
            a.status,                      -- Status (pendente, confirmado, etc)
            a.servico_id,                  -- ID do serviço
            a.animal_id,                   -- ID do animal
            a.cliente_id,                  -- ID do cliente
            s.nome AS servico,             -- Nome do serviço (JOIN)
            s.duracao AS servico_duracao,  -- Duração em minutos (JOIN)
            an.nome AS animal,             -- Nome do animal (JOIN)
            e.nome AS animal_especie,      -- Espécie do animal (JOIN)
            u.nome AS cliente,             -- Nome do cliente (JOIN)
            u.telefone AS cliente_telefone -- Telefone do cliente (JOIN)
        FROM Agendamentos a
        INNER JOIN Servicos s ON a.servico_id = s.id      -- Busca dados do serviço
        INNER JOIN Animais an ON a.animal_id = an.id      -- Busca dados do animal
        INNER JOIN Usuarios u ON a.cliente_id = u.id      -- Busca dados do cliente
        LEFT JOIN Especies e ON an.especie_id = e.id      -- Busca espécie (LEFT = opcional)
        WHERE 1=1                                          -- Facilita adicionar filtros
    ";
    
    // Array para armazenar os parâmetros da query (prepared statement)
    $params = [];

    // ========== FILTRO POR TIPO DE USUÁRIO ==========
    // Se for Cliente, só mostra seus próprios agendamentos
    // Se for Veterinário/Secretária, mostra TODOS os agendamentos
    if ($tipo_usuario === 'Cliente') {
        $query .= " AND a.cliente_id = ?";
        $params[] = $usuario_id;
    }

    // ========== FILTRO POR PERÍODO (FULLCALENDAR) ==========
    // Se o FullCalendar enviou datas de início e fim, filtra por esse período
    // Isso otimiza a busca, trazendo apenas eventos visíveis no calendário
    if ($start_date && $end_date) {
        $query .= " AND a.data_hora BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    // ========== ORDENAÇÃO ==========
    // Ordena por data e depois por hora de início
    $query .= " ORDER BY a.data_hora, a.hora_inicio";

    // ========== EXECUÇÃO DA QUERY ==========
    // Prepara e executa a query com os parâmetros
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== FORMATAÇÃO PARA FULLCALENDAR ==========
    // Transforma os dados do banco no formato que o FullCalendar espera
    $events = [];
    
    foreach ($appointments as $appt) {
        // ========== DEFINIÇÃO DE CORES POR STATUS ==========
        // Cor padrão (azul)
        $backgroundColor = '#6366f1';
        $borderColor = '#6366f1';
        
        // Muda a cor baseado no status do agendamento
        switch (strtolower($appt['status'])) {
            case 'confirmado':
                $backgroundColor = '#10b981'; // Verde
                $borderColor = '#10b981';
                break;
            case 'cancelado':
                $backgroundColor = '#ef4444'; // Vermelho
                $borderColor = '#ef4444';
                break;
            case 'pendente':
                $backgroundColor = '#f59e0b'; // Laranja/Amarelo
                $borderColor = '#f59e0b';
                break;
            case 'finalizado':
                $backgroundColor = '#64748b'; // Cinza
                $borderColor = '#64748b';
                break;
        }

        // ========== MONTAGEM DO EVENTO ==========
        // Cria o objeto no formato que o FullCalendar espera
        $events[] = [
            'id' => $appt['id'],                                      // ID do evento
            'title' => $appt['servico'] . ' - ' . $appt['animal'],   // Título visível no calendário
            'start' => $appt['data_hora'] . 'T' . $appt['hora_inicio'], // Data/hora início (formato ISO)
            'end' => $appt['data_hora'] . 'T' . $appt['hora_final'],   // Data/hora fim (formato ISO)
            'backgroundColor' => $backgroundColor,                    // Cor de fundo do evento
            'borderColor' => $borderColor,                           // Cor da borda
            'textColor' => '#ffffff',                                // Cor do texto (branco)
            
            // ========== DADOS EXTRAS (EXTENDEDPROPS) ==========
            // Dados adicionais que podem ser acessados no JavaScript
            'extendedProps' => [
                // Formato original (SEM underscore) - mantido para compatibilidade com código antigo
                'animal' => $appt['animal'],
                'servico' => $appt['servico'],
                'cliente' => $appt['cliente'],
                
                // Formato novo (COM underscore) - padrão mais descritivo
                'animal_nome' => $appt['animal'],
                'servico_nome' => $appt['servico'],
                'cliente_nome' => $appt['cliente'],
                
                // IDs para referência
                'servico_id' => $appt['servico_id'],
                'animal_id' => $appt['animal_id'],
                'cliente_id' => $appt['cliente_id'],
                
                // Status e observações
                'status' => $appt['status'],
                'observacoes' => $appt['observacoes'] ?? '',  // Se null, usa string vazia
                'hora_final' => $appt['hora_final'],
                
                // Dados adicionais
                'servico_duracao' => $appt['servico_duracao'] ?? 30,  // Padrão 30min se null
                'animal_especie' => $appt['animal_especie'] ?? '',    // Espécie (pode ser null)
                'cliente_telefone' => $appt['cliente_telefone'] ?? '' // Telefone (pode ser null)
            ]
        ];
    }

    // ========== RETORNA OS EVENTOS EM JSON ==========
    // Envia o array de eventos formatados para o frontend
    echo json_encode($events);

} catch (PDOException $e) {
    // ========== TRATAMENTO DE ERRO ==========
    // Salva o erro no log do servidor (para debug)
    error_log("Erro ao buscar agendamentos: " . $e->getMessage());
    
    // Define código HTTP 500 (erro interno do servidor)
    http_response_code(500);
    
    // Retorna erro em JSON
    echo json_encode([
        'error' => 'Erro ao buscar agendamentos',
        'message' => $e->getMessage()
    ]);
}

// Garante que nada mais será executado após retornar a resposta
exit;

// ========== RESUMO DO FUNCIONAMENTO ==========
// 1. Verifica se está logado
// 2. Busca agendamentos no banco com JOINs (pega dados de serviço, animal, cliente, espécie)
// 3. Filtra por tipo de usuário:
//    - Cliente: só vê seus agendamentos
//    - Veterinário/Secretária: vê todos
// 4. Aplica filtro de data se o FullCalendar enviar (otimização)
// 5. Formata cada agendamento no padrão do FullCalendar:
//    - Define cores por status
//    - Monta objeto com id, title, start, end, extendedProps
// 6. Retorna array de eventos em JSON
// 7. FullCalendar recebe e renderiza os eventos no calendário
?>