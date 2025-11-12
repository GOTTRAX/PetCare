<?php
include '../conexao.php';
header('Content-Type: application/json');

// Função para sanitizar inputs
function sanitizar_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Função para calcular hora_final com base em hora_inicio e duração do serviço
function calcularHoraFinal($hora_inicio, $duracao) {
    if (!$hora_inicio || !$duracao) {
        return null;
    }

    // Tentar criar objeto DateTime aceitando formatos "H:i:s" ou "H:i"
    $hora_inicio_obj = DateTime::createFromFormat('H:i:s', $hora_inicio);
    if (!$hora_inicio_obj) {
        $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
    }

    if (!$hora_inicio_obj) {
        return null; // Formato de hora inválido
    }

    // Somar minutos de duração
    $hora_inicio_obj->modify("+{$duracao} minutes");

    // Retornar no formato de banco (HH:MM:SS)
    return $hora_inicio_obj->format('H:i:s');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    // Obter dados do formulário
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $data = isset($_POST['data']) ? sanitizar_input($_POST['data']) : null;
    $hora_inicio = isset($_POST['hora_inicio']) ? sanitizar_input($_POST['hora_inicio']) : null;
    $hora_final = isset($_POST['hora_final']) ? sanitizar_input($_POST['hora_final']) : null;
    $status = isset($_POST['status']) ? sanitizar_input($_POST['status']) : null;
    $observacoes = isset($_POST['observacoes']) ? sanitizar_input($_POST['observacoes']) : null;

    // Validações básicas
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID do agendamento não fornecido']);
        exit;
    }

    // Buscar agendamento existente
    $stmt = $pdo->prepare("SELECT cliente_id, servico_id, hora_inicio, hora_final, status FROM Agendamentos WHERE id = ?");
    $stmt->execute([$id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        echo json_encode(['success' => false, 'error' => 'Agendamento não encontrado']);
        exit;
    }

    // Preparar dados para atualização
    $update_fields = [];
    $params = [':id' => $id];

    // Verificar e validar data
    if ($data) {
        $data_obj = DateTime::createFromFormat('Y-m-d', $data);
        if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
            echo json_encode(['success' => false, 'error' => 'Data inválida']);
            exit;
        }
        $hoje = new DateTime();
        if ($data_obj < $hoje->setTime(0, 0, 0)) {
            echo json_encode(['success' => false, 'error' => 'Não é possível agendar em datas passadas']);
            exit;
        }
        $update_fields[] = "data_hora = :data";
        $params[':data'] = $data;
    }

    // Verificar e validar hora_inicio e hora_final
    if ($hora_inicio) {
        // Normalizar hora_inicio para incluir segundos se necessário
        if (strlen($hora_inicio) === 5) {
            $hora_inicio .= ':00';
        }
        // Validar formato da hora
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora_inicio)) {
            echo json_encode(['success' => false, 'error' => 'Hora de início inválida']);
            exit;
        }

        // Buscar duração do serviço
        $stmt = $pdo->prepare("SELECT duracao FROM Servicos WHERE id = ?");
        $stmt->execute([$agendamento['servico_id']]);
        $servico = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$servico) {
            echo json_encode(['success' => false, 'error' => 'Serviço inválido']);
            exit;
        }

        // Calcular hora_final esperada
        $hora_final_calculada = calcularHoraFinal($hora_inicio, $servico['duracao']);
        if (!$hora_final_calculada) {
            echo json_encode(['success' => false, 'error' => 'Erro ao calcular hora final']);
            exit;
        }

        // Usar hora_final recebida (para eventResize) ou calculada (para eventDrop)
        $hora_final_to_use = $hora_final ?: $hora_final_calculada;
        if ($hora_final && $hora_final !== $hora_final_calculada) {
            // Permitir hora_final diferente apenas para eventResize
            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora_final)) {
                echo json_encode(['success' => false, 'error' => 'Hora final inválida']);
                exit;
            }
        }

        $update_fields[] = "hora_inicio = :hora_inicio";
        $update_fields[] = "hora_final = :hora_final";
        $params[':hora_inicio'] = $hora_inicio;
        $params[':hora_final'] = $hora_final_to_use;
    }

    // Verificar e validar status
    if ($status) {
        $valid_statuses = ['pendente', 'confirmado', 'cancelado', 'finalizado'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'error' => 'Status inválido']);
            exit;
        }
        $update_fields[] = "status = :status";
        $params[':status'] = $status;
    }

    // Adicionar observações
    if ($observacoes !== null) {
        $update_fields[] = "observacoes = :observacoes";
        $params[':observacoes'] = $observacoes;
    }

    // Se não houver campos para atualizar
    if (empty($update_fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Construir e executar a query de atualização
    $sql = "UPDATE Agendamentos SET " . implode(', ', $update_fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Retornar resposta de sucesso sem mensagem
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Erro em editar_agendamento: " . $e->getMessage(), 3, 'logs/errors.log');
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar agendamento']);
} catch (Exception $e) {
    error_log("Erro geral em editar_agendamento: " . $e->getMessage(), 3, 'logs/errors.log');
    echo json_encode(['success' => false, 'error' => 'Erro inesperado']);
}

//Editar Agendamento
//Recebe dados de duas formas:
//Via JSON (Drag/Drop):

//Pega os dados do corpo da requisição em formato JSON
//Extrai: id, data_hora, hora_inicio, status, hora_final
//Monta um UPDATE dinâmico com apenas os campos enviados

//Via FormData (Modal):
//Pega os dados via POST ($_POST)
//Extrai: id, data, hora_inicio, observacoes, status
//Exige id, data e hora_inicio (validação obrigatória)

//Processa a atualização:

//Constrói uma query UPDATE SQL dinamicamente
//Só inclui os campos que foram enviados
//Adiciona o status se foi informado
//Executa a query com prepared statement

//Retorna resposta JSON:

//Se sucesso: ['success' => true]
//Se erro: ['success' => false, 'message' => motivo]
?>