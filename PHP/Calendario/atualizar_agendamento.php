<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica se é JSON (arrastar/soltar) ou FormData (edição normal)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input) {
        // Requisição via JSON - arrastar/soltar
        $id = $input['id'] ?? null;
        $data = $input['data_hora'] ?? null;
        $hora_inicio = $input['hora_inicio'] ?? null;
        $status = $input['status'] ?? null;
        $hora_final = $input['hora_final'] ?? null;
        
        if ($id) {
            try {
                $sql = "UPDATE Agendamentos SET ";
                $params = [];
                $updates = [];
                
                if ($data) {
                    $updates[] = "data = ?";
                    $params[] = $data;
                }
                
                if ($hora_inicio) {
                    $updates[] = "hora_inicio = ?";
                    $params[] = $hora_inicio;
                }
                
                if ($status) {
                    $updates[] = "status = ?";
                    $params[] = $status;
                }
                
                if ($hora_final) {
                    $updates[] = "hora_final = ?";
                    $params[] = $hora_final;
                }
                
                if (count($updates) > 0) {
                    $sql .= implode(", ", $updates) . " WHERE id = ?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
    } else {
        // Requisição via FormData - edição normal do modal
        $id = $_POST['id'] ?? null;
        $data = $_POST['data'] ?? null;
        $hora_inicio = $_POST['hora_inicio'] ?? null;
        $observacoes = $_POST['observacoes'] ?? '';
        $status = $_POST['status'] ?? null;
        
        if ($id && $data && $hora_inicio) {
            try {
                $sql = "UPDATE Agendamentos SET data = ?, hora_inicio = ?, observacoes = ?";
                $params = [$data, $hora_inicio, $observacoes];
                
                // Se veio status, adiciona à atualização
                if ($status) {
                    $sql .= ", status = ?";
                    $params[] = $status;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        }
    }
}
//Atualizar Agendamento
//Valida a requisição:

//Verifica se é POST
//Tenta decodificar JSON do corpo da requisição

//Se for JSON (Drag/Drop):

//Extrai: id, data_hora, hora_inicio, status, hora_final
//Verifica se tem id
//Monta query UPDATE dinamicamente, adicionando apenas os campos que vieram preenchidos
//Adiciona à query WHERE id = ?

//Se for FormData (Modal):

//Extrai via $_POST: id, data, hora_inicio, observacoes, status
//Valida se id, data e hora_inicio foram preenchidos (obrigatórios)
//Monta query UPDATE com esses 3 campos + observacoes
//Se tiver status, adiciona também na query
//Adiciona à query WHERE id = ?

//Executa a atualização:

//Prepara a statement SQL
//Executa com os parâmetros montados

//Retorna resposta JSON:

//Se sucesso: ['success' => true]
//Se nenhum campo para atualizar: ['success' => false, 'message' => 'Nenhum campo para atualizar']
//Se dados incompletos (FormData): ['success' => false, 'message' => 'Dados incompletos']
//Se erro no banco: ['success' => false, 'message' => mensagem da exception]
?>