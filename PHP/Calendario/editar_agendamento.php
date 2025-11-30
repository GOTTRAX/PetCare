<?php
// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// ========== FUNÇÕES AUXILIARES ==========

// Remove caracteres perigosos e espaços extras para evitar XSS
function sanitizar_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Calcula automaticamente a hora final baseado na hora de início + duração do serviço
function calcularHoraFinal($hora_inicio, $duracao) {
    // Se não tiver hora de início ou duração, não pode calcular
    if (!$hora_inicio || !$duracao) {
        return null;
    }

    // Tenta criar objeto DateTime com formato "H:i:s" (ex: 14:30:00)
    $hora_inicio_obj = DateTime::createFromFormat('H:i:s', $hora_inicio);
    
    // Se não deu certo, tenta formato "H:i" (ex: 14:30)
    if (!$hora_inicio_obj) {
        $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
    }

    // Se ainda não conseguiu criar o objeto, formato inválido
    if (!$hora_inicio_obj) {
        return null;
    }

    // Adiciona os minutos de duração à hora de início
    // Ex: 14:30 + 60 minutos = 15:30
    $hora_inicio_obj->modify("+{$duracao} minutes");

    // Retorna no formato do banco de dados (HH:MM:SS)
    return $hora_inicio_obj->format('H:i:s');
}

try {
    // ========== VALIDAÇÃO DO MÉTODO HTTP ==========
    // Só aceita requisições POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    // ========== COLETA E SANITIZAÇÃO DOS DADOS ==========
    // Recebe e limpa os dados enviados via POST
    $id = isset($_POST['id']) ? intval($_POST['id']) : null; // Converte para inteiro
    $data = isset($_POST['data']) ? sanitizar_input($_POST['data']) : null;
    $hora_inicio = isset($_POST['hora_inicio']) ? sanitizar_input($_POST['hora_inicio']) : null;
    $hora_final = isset($_POST['hora_final']) ? sanitizar_input($_POST['hora_final']) : null;
    $status = isset($_POST['status']) ? sanitizar_input($_POST['status']) : null;
    $observacoes = isset($_POST['observacoes']) ? sanitizar_input($_POST['observacoes']) : null;

    // ========== VALIDAÇÃO OBRIGATÓRIA ==========
    // ID é obrigatório para saber qual agendamento editar
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID do agendamento não fornecido']);
        exit;
    }

    // ========== BUSCA O AGENDAMENTO NO BANCO ==========
    // Verifica se o agendamento existe e pega os dados atuais
    $stmt = $pdo->prepare("SELECT cliente_id, servico_id, hora_inicio, hora_final, status FROM Agendamentos WHERE id = ?");
    $stmt->execute([$id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não encontrou o agendamento, retorna erro
    if (!$agendamento) {
        echo json_encode(['success' => false, 'error' => 'Agendamento não encontrado']);
        exit;
    }

    // ========== PREPARAÇÃO PARA UPDATE DINÂMICO ==========
    // Arrays para construir a query dinamicamente
    $update_fields = []; // Guarda os campos que serão atualizados (ex: "data_hora = :data")
    $params = [':id' => $id]; // Guarda os valores que serão substituídos na query

    // ========== VALIDAÇÃO E PROCESSAMENTO DA DATA ==========
    if ($data) {
        // Cria objeto DateTime para validar se a data é válida
        $data_obj = DateTime::createFromFormat('Y-m-d', $data);
        
        // Verifica se a data é válida e está no formato correto
        if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
            echo json_encode(['success' => false, 'error' => 'Data inválida']);
            exit;
        }
        
        // Verifica se a data não é no passado
        $hoje = new DateTime();
        if ($data_obj < $hoje->setTime(0, 0, 0)) {
            echo json_encode(['success' => false, 'error' => 'Não é possível agendar em datas passadas']);
            exit;
        }
        
        // Adiciona o campo data_hora para ser atualizado
        $update_fields[] = "data_hora = :data";
        $params[':data'] = $data;
    }

    // ========== VALIDAÇÃO E PROCESSAMENTO DA HORA ==========
    if ($hora_inicio) {
        // Normaliza a hora para incluir segundos se vier só HH:MM
        // Ex: "14:30" vira "14:30:00"
        if (strlen($hora_inicio) === 5) {
            $hora_inicio .= ':00';
        }
        
        // Valida se a hora está no formato correto (HH:MM:SS)
        // Aceita horas de 00:00:00 até 23:59:59
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora_inicio)) {
            echo json_encode(['success' => false, 'error' => 'Hora de início inválida']);
            exit;
        }

        // ========== BUSCA A DURAÇÃO DO SERVIÇO ==========
        // Precisa da duração para calcular a hora final
        $stmt = $pdo->prepare("SELECT duracao FROM Servicos WHERE id = ?");
        $stmt->execute([$agendamento['servico_id']]);
        $servico = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$servico) {
            echo json_encode(['success' => false, 'error' => 'Serviço inválido']);
            exit;
        }

        // ========== CALCULA A HORA FINAL ==========
        // Usa a função para calcular hora_inicio + duração
        $hora_final_calculada = calcularHoraFinal($hora_inicio, $servico['duracao']);
        
        if (!$hora_final_calculada) {
            echo json_encode(['success' => false, 'error' => 'Erro ao calcular hora final']);
            exit;
        }

        // ========== DECIDE QUAL HORA FINAL USAR ==========
        // Se veio hora_final do frontend (eventResize), usa ela
        // Senão, usa a calculada (eventDrop)
        $hora_final_to_use = $hora_final ?: $hora_final_calculada;
        
        // Se veio hora_final diferente da calculada, valida também
        if ($hora_final && $hora_final !== $hora_final_calculada) {
            // Valida o formato da hora_final recebida
            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora_final)) {
                echo json_encode(['success' => false, 'error' => 'Hora final inválida']);
                exit;
            }
        }

        // Adiciona os campos de hora para serem atualizados
        $update_fields[] = "hora_inicio = :hora_inicio";
        $update_fields[] = "hora_final = :hora_final";
        $params[':hora_inicio'] = $hora_inicio;
        $params[':hora_final'] = $hora_final_to_use;
    }

    // ========== VALIDAÇÃO E PROCESSAMENTO DO STATUS ==========
    if ($status) {
        // Lista de status válidos no sistema
        $valid_statuses = ['pendente', 'confirmado', 'cancelado', 'finalizado'];
        
        // Verifica se o status enviado está na lista
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'error' => 'Status inválido']);
            exit;
        }
        
        // Adiciona o campo status para ser atualizado
        $update_fields[] = "status = :status";
        $params[':status'] = $status;
    }

    // ========== PROCESSAMENTO DAS OBSERVAÇÕES ==========
    // Adiciona observações se foram enviadas (pode ser string vazia)
    if ($observacoes !== null) {
        $update_fields[] = "observacoes = :observacoes";
        $params[':observacoes'] = $observacoes;
    }

    // ========== VERIFICA SE HÁ ALGO PARA ATUALIZAR ==========
    // Se nenhum campo foi adicionado, retorna sucesso sem fazer nada
    if (empty($update_fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    // ========== EXECUÇÃO DO UPDATE ==========
    // Monta a query dinamicamente juntando todos os campos
    // Ex: "UPDATE Agendamentos SET data_hora = :data, status = :status WHERE id = :id"
    $sql = "UPDATE Agendamentos SET " . implode(', ', $update_fields) . " WHERE id = :id";
    
    // Prepara e executa a query com os parâmetros
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Retorna sucesso
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Erro no banco de dados - salva no log
    error_log("Erro em editar_agendamento: " . $e->getMessage(), 3, 'logs/errors.log');
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar agendamento']);
    
} catch (Exception $e) {
    // Qualquer outro erro - salva no log
    error_log("Erro geral em editar_agendamento: " . $e->getMessage(), 3, 'logs/errors.log');
    echo json_encode(['success' => false, 'error' => 'Erro inesperado']);
}

// ========== RESUMO DO FUNCIONAMENTO ==========
// 1. Recebe dados via POST (FormData ou JSON)
// 2. Sanitiza todos os inputs para evitar XSS
// 3. Valida se o agendamento existe
// 4. Valida data (não pode ser passada)
// 5. Valida hora_inicio e calcula hora_final baseado na duração do serviço
// 6. Valida status (só aceita: pendente, confirmado, cancelado, finalizado)
// 7. Monta UPDATE dinâmico com apenas os campos enviados
// 8. Executa a atualização no banco
// 9. Retorna JSON com sucesso ou erro
?>