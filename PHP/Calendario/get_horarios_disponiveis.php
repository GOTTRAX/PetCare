<?php
// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// ========== LOG DE DEBUG ==========
// Registra no log do servidor quando este arquivo é chamado e com quais parâmetros
error_log("get_horarios_disponiveis.php chamado com data: {$_GET['data']}, servico_id: {$_GET['servico_id']}");

// ========== VALIDAÇÃO DE PARÂMETROS OBRIGATÓRIOS ==========
// Verifica se data e servico_id foram enviados via GET
if (!isset($_GET['data']) || !isset($_GET['servico_id'])) {
    error_log("Erro: data ou servico_id não fornecidos");
    echo json_encode(['error' => 'Data ou serviço não fornecidos']);
    exit;
}

// Armazena os parâmetros em variáveis
$data = $_GET['data'];
$servico_id = $_GET['servico_id'];

// ========== VALIDAÇÃO DO FORMATO DA DATA ==========
// Usa regex para verificar se a data está no formato YYYY-MM-DD
// Exemplo válido: 2024-12-25
// Exemplo inválido: 25/12/2024 ou 2024-12-32
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    error_log("Erro: Formato de data inválido: $data");
    echo json_encode(['error' => 'Formato de data inválido']);
    exit;
}

// ========== DETERMINA O DIA DA SEMANA ==========
// date('N') retorna um número de 1 a 7:
// 1 = Segunda, 2 = Terça, 3 = Quarta, 4 = Quinta, 5 = Sexta, 6 = Sábado, 7 = Domingo
$diaSemana = date('N', strtotime($data));
error_log("Dia da semana: $diaSemana");

// ========== BUSCA A DURAÇÃO DO SERVIÇO ==========
// Precisa saber quanto tempo o serviço leva para calcular os horários
try {
    $stmt = $pdo->prepare("SELECT duracao FROM Servicos WHERE id = :id");
    $stmt->execute([':id' => $servico_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o serviço não existe, retorna erro
    if (!$servico) {
        error_log("Erro: Serviço com id $servico_id não encontrado");
        echo json_encode(['error' => 'Serviço não encontrado']);
        exit;
    }
    
    // Converte a duração para inteiro (em minutos)
    $duracao = intval($servico['duracao']);
    error_log("Duração do serviço: $duracao minutos");
    
} catch (PDOException $e) {
    error_log("Erro ao buscar serviço: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao consultar serviço']);
    exit;
}

// ========== VERIFICA SE A CLÍNICA TRABALHA NESSE DIA ==========
// Busca na tabela Dias_Trabalhados a configuração para o dia da semana específico
// Exemplo: Se for segunda-feira (diaSemana=1), busca o registro de "Segunda" que esteja ativo
$sql = "SELECT * FROM Dias_Trabalhados WHERE ativo = 1 AND 
        CASE 
          WHEN dia_semana='Segunda' AND :ds=1 THEN TRUE
          WHEN dia_semana='Terça'   AND :ds=2 THEN TRUE
          WHEN dia_semana='Quarta'  AND :ds=3 THEN TRUE
          WHEN dia_semana='Quinta'  AND :ds=4 THEN TRUE
          WHEN dia_semana='Sexta'   AND :ds=5 THEN TRUE
          WHEN dia_semana='Sábado'  AND :ds=6 THEN TRUE
          WHEN dia_semana='Domingo' AND :ds=7 THEN TRUE
          ELSE FALSE
        END";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ds' => $diaSemana]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Configuração do dia: " . print_r($config, true));
    
} catch (PDOException $e) {
    error_log("Erro ao consultar Dias_Trabalhados: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao consultar dias trabalhados']);
    exit;
}

// ========== GERAÇÃO DOS HORÁRIOS DISPONÍVEIS ==========
// Array que armazenará todos os horários livres
$horarios = [];

// Se encontrou configuração para o dia (clínica funciona nesse dia)
if ($config) {
    // Converte os horários de abertura e fechamento para timestamp
    // Exemplo: "08:00:00" vira timestamp para poder fazer cálculos
    $horaAtual = strtotime($config['horario_abertura']);
    $horaFim = strtotime($config['horario_fechamento']);
    error_log("Horário de abertura: {$config['horario_abertura']}, fechamento: {$config['horario_fechamento']}");

    // ========== VALIDAÇÃO DOS HORÁRIOS ==========
    // Verifica se conseguiu converter os horários (podem estar em formato inválido)
    if (!$horaAtual || !$horaFim) {
        error_log("Erro: Horários de abertura ou fechamento inválidos");
        echo json_encode(['error' => 'Horários de funcionamento inválidos']);
        exit;
    }

    // ========== LOOP PARA GERAR TODOS OS HORÁRIOS POSSÍVEIS ==========
    // Itera de hora em hora (baseado na duração) até o fechamento
    // Exemplo: Se abre 08:00, fecha 18:00 e serviço dura 30min
    // Vai gerar: 08:00, 08:30, 09:00, 09:30... até 17:30
    while ($horaAtual + ($duracao * 60) <= $horaFim) {
        // Calcula o horário de início e fim deste slot
        $inicio = date('H:i:s', $horaAtual);
        $fim = date('H:i:s', strtotime("+{$duracao} minutes", $horaAtual));
        error_log("Verificando horário: $inicio - $fim");

        // ========== VERIFICAÇÃO DO HORÁRIO DE ALMOÇO ==========
        // Se existe horário de almoço configurado, pula esse período
        if ($config['horario_almoco_inicio'] && $config['horario_almoco_fim']) {
            $almoco_inicio = strtotime($config['horario_almoco_inicio']);
            $almoco_fim = strtotime($config['horario_almoco_fim']);
            
            // Se o horário atual está dentro do período de almoço
            if ($horaAtual >= $almoco_inicio && $horaAtual < $almoco_fim) {
                error_log("Horário $inicio - $fim pulado (almoço)");
                $horaAtual = $almoco_fim; // Pula direto para o fim do almoço
                continue; // Vai para a próxima iteração do loop
            }
        }

        // ========== VERIFICAÇÃO DE CONFLITOS COM AGENDAMENTOS ==========
        // Verifica se já existe algum agendamento nesse horário
        try {
            // Esta query verifica se há sobreposição de horários
            // :inicio < hora_final AND :fim > hora_inicio = lógica de overlap de intervalos
            $sql = "SELECT COUNT(*) FROM Agendamentos 
                    WHERE data_hora = :data 
                    AND status != 'cancelado'
                    AND (:inicio < hora_final AND :fim > hora_inicio)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':data' => $data,
                ':inicio' => $inicio,
                ':fim' => $fim
            ]);
            
            // fetchColumn() retorna apenas o valor da contagem
            $ocupado = $stmt->fetchColumn();
            error_log("Horário $inicio - $fim ocupado: " . ($ocupado ? 'Sim' : 'Não'));
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar agendamentos: " . $e->getMessage());
            echo json_encode(['error' => 'Erro ao verificar agendamentos']);
            exit;
        }

        // ========== ADICIONA HORÁRIO SE DISPONÍVEL ==========
        // Se não está ocupado (count = 0), adiciona ao array de horários disponíveis
        if (!$ocupado) {
            $horarios[] = [
                'inicio' => $inicio,
                'final' => $fim
            ];
        }

        // ========== AVANÇA PARA O PRÓXIMO SLOT ==========
        // Move horaAtual para frente baseado na duração do serviço
        // Exemplo: Se duração = 30min, avança 30 minutos
        $horaAtual = strtotime("+{$duracao} minutes", $horaAtual);
    }
    
} else {
    // ========== CLÍNICA FECHADA NESTE DIA ==========
    // Se não encontrou configuração, significa que a clínica não funciona nesse dia
    error_log("Nenhuma configuração encontrada para o dia da semana $diaSemana");
    echo json_encode(['error' => 'Clínica fechada neste dia']);
}

// ========== LOG FINAL E RETORNO ==========
// Loga todos os horários disponíveis encontrados
error_log("Horários disponíveis: " . print_r($horarios, true));

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Retorna o array de horários disponíveis
// Exemplo: [{"inicio":"08:00:00","final":"08:30:00"}, {"inicio":"08:30:00","final":"09:00:00"}, ...]
echo json_encode($horarios);

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo é usado para popular o campo "horário" no formulário de agendamento
//
// Fluxo completo:
// 1. Recebe data (ex: 2024-12-25) e servico_id (ex: 3) via GET
// 2. Valida formato da data
// 3. Descobre qual dia da semana é (1-7)
// 4. Busca quanto tempo o serviço dura (ex: 30 minutos)
// 5. Verifica se a clínica funciona nesse dia da semana
// 6. Pega horário de abertura/fechamento e almoço
// 7. Gera todos os slots possíveis (ex: 08:00, 08:30, 09:00...)
// 8. Para cada slot:
//    - Pula se for horário de almoço
//    - Verifica se já tem agendamento nesse horário
//    - Se livre, adiciona na lista
// 9. Retorna JSON com todos os horários disponíveis
//
// Uso no frontend:
// fetch('get_horarios_disponiveis.php?data=2024-12-25&servico_id=3')
//   .then(response => response.json())
//   .then(horarios => {
//     // Popular <select> com os horários disponíveis
//   });
?>