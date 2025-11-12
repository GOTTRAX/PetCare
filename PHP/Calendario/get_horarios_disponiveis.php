<?php
include '../conexao.php';

// Habilitar logs para depuração
error_log("get_horarios_disponiveis.php chamado com data: {$_GET['data']}, servico_id: {$_GET['servico_id']}");

// Validar parâmetros de entrada
if (!isset($_GET['data']) || !isset($_GET['servico_id'])) {
    error_log("Erro: data ou servico_id não fornecidos");
    echo json_encode(['error' => 'Data ou serviço não fornecidos']);
    exit;
}

$data = $_GET['data'];
$servico_id = $_GET['servico_id'];

// Validar formato da data (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    error_log("Erro: Formato de data inválido: $data");
    echo json_encode(['error' => 'Formato de data inválido']);
    exit;
}

// Determinar o dia da semana (1=Segunda, 7=Domingo)
$diaSemana = date('N', strtotime($data));
error_log("Dia da semana: $diaSemana");

// Buscar a duração do serviço
try {
    $stmt = $pdo->prepare("SELECT duracao FROM Servicos WHERE id = :id");
    $stmt->execute([':id' => $servico_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servico) {
        error_log("Erro: Serviço com id $servico_id não encontrado");
        echo json_encode(['error' => 'Serviço não encontrado']);
        exit;
    }
    $duracao = intval($servico['duracao']);
    error_log("Duração do serviço: $duracao minutos");
} catch (PDOException $e) {
    error_log("Erro ao buscar serviço: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao consultar serviço']);
    exit;
}

// Verificar se a clínica trabalha nesse dia
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

$horarios = [];
if ($config) {
    $horaAtual = strtotime($config['horario_abertura']);
    $horaFim = strtotime($config['horario_fechamento']);
    error_log("Horário de abertura: {$config['horario_abertura']}, fechamento: {$config['horario_fechamento']}");

    // Validar horários de abertura e fechamento
    if (!$horaAtual || !$horaFim) {
        error_log("Erro: Horários de abertura ou fechamento inválidos");
        echo json_encode(['error' => 'Horários de funcionamento inválidos']);
        exit;
    }

    while ($horaAtual + ($duracao * 60) <= $horaFim) {
        $inicio = date('H:i:s', $horaAtual);
        $fim = date('H:i:s', strtotime("+{$duracao} minutes", $horaAtual));
        error_log("Verificando horário: $inicio - $fim");

        // Pular horário de almoço
        if ($config['horario_almoco_inicio'] && $config['horario_almoco_fim']) {
            $almoco_inicio = strtotime($config['horario_almoco_inicio']);
            $almoco_fim = strtotime($config['horario_almoco_fim']);
            if ($horaAtual >= $almoco_inicio && $horaAtual < $almoco_fim) {
                error_log("Horário $inicio - $fim pulado (almoço)");
                $horaAtual = $almoco_fim; // Pular para o fim do almoço
                continue;
            }
        }

        // Verificar conflitos com agendamentos existentes
        try {
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
            $ocupado = $stmt->fetchColumn();
            error_log("Horário $inicio - $fim ocupado: " . ($ocupado ? 'Sim' : 'Não'));
        } catch (PDOException $e) {
            error_log("Erro ao verificar agendamentos: " . $e->getMessage());
            echo json_encode(['error' => 'Erro ao verificar agendamentos']);
            exit;
        }

        if (!$ocupado) {
            $horarios[] = [
                'inicio' => $inicio,
                'final' => $fim
            ];
        }

        // Avançar conforme a duração do serviço
        $horaAtual = strtotime("+{$duracao} minutes", $horaAtual);
    }
} else {
    error_log("Nenhuma configuração encontrada para o dia da semana $diaSemana");
    echo json_encode(['error' => 'Clínica fechada neste dia']);
}

error_log("Horários disponíveis: " . print_r($horarios, true));
header('Content-Type: application/json');
echo json_encode($horarios);
?>