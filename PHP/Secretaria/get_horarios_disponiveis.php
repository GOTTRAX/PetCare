<?php
session_start();
require '../conexao.php';

if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

if (!isset($_GET['data'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$data = $_GET['data'];

// Buscar horários de funcionamento da clínica
$horarios_clinica = [];
try {
    $stmt = $pdo->query("SELECT * FROM Dias_Trabalhados WHERE ativo = 1");
    $horarios_clinica = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se não existir a tabela, usar valores padrão
    $horarios_clinica = [];
}

// Converter para formato mais fácil de usar
$horarios_por_dia = [];
foreach ($horarios_clinica as $horario) {
    $horarios_por_dia[$horario['dia_semana']] = $horario;
}

// Verificar se é um dia válido
$dia_semana = date('N', strtotime($data)); // 1=Segunda, 7=Domingo
$nomes_dias = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sábado', 'Domingo'];
$dia_nome = $nomes_dias[$dia_semana];

// Verificar se a clínica funciona neste dia
if (!isset($horarios_por_dia[$dia_nome]) || $data < date('Y-m-d')) {
    echo json_encode([]);
    exit;
}

$horario_dia = $horarios_por_dia[$dia_nome];
$horarios_disponiveis = [];

// Gerar todos os horários possíveis
$inicio = new DateTime($horario_dia['horario_abertura']);
$fim = new DateTime($horario_dia['horario_fechamento']);

// Verificar agendamentos existentes para esta data
$stmt = $pdo->prepare("SELECT hora_inicio FROM Agendamentos WHERE data_agendamento = ? AND status != 'cancelado'");
$stmt->execute([$data]);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$horarios_ocupados = [];
foreach ($agendamentos as $agendamento) {
    $horarios_ocupados[$agendamento['hora_inicio']] = true;
}

// Gerar horários disponíveis
while ($inicio < $fim) {
    $hora_atual = $inicio->format('H:i');
    
    // Verificar se está no horário de almoço
    $em_almoco = false;
    if (!empty($horario_dia['horario_almoco_inicio']) && !empty($horario_dia['horario_almoco_fim'])) {
        $almoco_inicio = new DateTime($horario_dia['horario_almoco_inicio']);
        $almoco_fim = new DateTime($horario_dia['horario_almoco_fim']);
        $hora_atual_obj = new DateTime($hora_atual);
        
        if ($hora_atual_obj >= $almoco_inicio && $hora_atual_obj < $almoco_fim) {
            $em_almoco = true;
        }
    }
    
    // Se não está ocupado e não é horário de almoço, adicionar aos disponíveis
    if (!isset($horarios_ocupados[$hora_atual]) && !$em_almoco) {
        $horarios_disponiveis[] = $hora_atual;
    }
    
    $inicio->modify('+1 hour');
}

header('Content-Type: application/json');
echo json_encode($horarios_disponiveis);