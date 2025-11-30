<?php
// Inicia a sessão para manter dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON (para comunicação com JavaScript)
header('Content-Type: application/json');

// Verifica se a requisição é do tipo POST (envio de dados)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Tenta ler o corpo da requisição como JSON e decodificar
    // file_get_contents('php://input') pega os dados brutos enviados
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Se conseguiu decodificar JSON, significa que veio de arrastar/soltar
    if ($input) {
        // ========== REQUISIÇÃO VIA JSON - ARRASTAR/SOLTAR ==========
        
        // Extrai os dados do JSON usando operador null coalescing (??)
        // Se não existir, retorna null
        $id = $input['id'] ?? null;
        $data = $input['data_hora'] ?? null;
        $hora_inicio = $input['hora_inicio'] ?? null;
        $status = $input['status'] ?? null;
        $hora_final = $input['hora_final'] ?? null;
        
        // Verifica se pelo menos o ID foi enviado (obrigatório)
        if ($id) {
            try {
                // Inicia a construção da query SQL de forma dinâmica
                $sql = "UPDATE Agendamentos SET ";
                $params = []; // Array para os valores que serão substituídos na query
                $updates = []; // Array para as colunas que serão atualizadas
                
                // Adiciona apenas os campos que foram enviados na requisição
                if ($data) {
                    $updates[] = "data = ?"; // ? é um placeholder para prepared statement
                    $params[] = $data; // Adiciona o valor correspondente
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
                
                // Verifica se há pelo menos um campo para atualizar
                if (count($updates) > 0) {
                    // Junta todos os updates com vírgula: "campo1 = ?, campo2 = ?"
                    $sql .= implode(", ", $updates) . " WHERE id = ?";
                    $params[] = $id; // Adiciona o ID como último parâmetro
                    
                    // Prepara a query (previne SQL Injection)
                    $stmt = $pdo->prepare($sql);
                    // Executa substituindo os ? pelos valores do array $params
                    $stmt->execute($params);
                    
                    // Retorna sucesso em JSON
                    echo json_encode(['success' => true]);
                } else {
                    // Se não há campos para atualizar, retorna erro
                    echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
                }
            } catch (Exception $e) {
                // Se der erro no banco, retorna a mensagem de erro
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
    } else {
        // ========== REQUISIÇÃO VIA FORMDATA - EDIÇÃO NORMAL DO MODAL ==========
        
        // Extrai os dados via $_POST (formulário tradicional)
        $id = $_POST['id'] ?? null;
        $data = $_POST['data'] ?? null;
        $hora_inicio = $_POST['hora_inicio'] ?? null;
        $observacoes = $_POST['observacoes'] ?? ''; // Se vazio, usa string vazia
        $status = $_POST['status'] ?? null;
        
        // Valida se os campos obrigatórios foram preenchidos
        if ($id && $data && $hora_inicio) {
            try {
                // Monta a query base com os campos obrigatórios
                $sql = "UPDATE Agendamentos SET data = ?, hora_inicio = ?, observacoes = ?";
                $params = [$data, $hora_inicio, $observacoes];
                
                // Se o status foi enviado, adiciona na query
                if ($status) {
                    $sql .= ", status = ?";
                    $params[] = $status;
                }
                
                // Adiciona a cláusula WHERE para especificar qual registro atualizar
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                // Prepara e executa a query
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Retorna sucesso
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                // Se der erro, retorna a mensagem
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            // Se faltaram dados obrigatórios, retorna erro
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        }
    }
}

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo atualiza agendamentos de duas formas:
// 1. JSON (arrastar/soltar): Atualiza apenas os campos enviados (flexível)
// 2. FormData (modal): Atualiza campos fixos (data, hora_inicio, observacoes, status)
// Sempre usa prepared statements para segurança contra SQL Injection
// Retorna JSON para o JavaScript processar a resposta
?>