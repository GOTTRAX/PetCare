<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Lê e decodifica o JSON enviado no corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extrai o ID do agendamento do JSON
    $agendamento_id = $input['id'];
    
    // Pega o ID do usuário logado na sessão
    $usuario_id = $_SESSION['id'];
    
    try {
        // ========== VERIFICAÇÃO DE PERMISSÃO ==========
        // Busca o agendamento APENAS se pertencer ao usuário logado
        // Isso garante que só o dono pode cancelar
        $stmt = $pdo->prepare("SELECT id FROM Agendamentos WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$agendamento_id, $usuario_id]);
        
        // Se encontrou o agendamento (rowCount > 0), o usuário é o dono
        if ($stmt->rowCount() > 0) {
            // ========== CANCELAMENTO DO AGENDAMENTO ==========
            // Atualiza o status para 'cancelado'
            $update = $pdo->prepare("UPDATE Agendamentos SET status = 'cancelado' WHERE id = ?");
            $update->execute([$agendamento_id]);
            
            // Retorna sucesso
            echo json_encode(['success' => true]);
        } else {
            // Se não encontrou, significa que ou não existe ou não pertence ao usuário
            echo json_encode(['success' => false, 'error' => 'Agendamento não encontrado']);
        }
    } catch (Exception $e) {
        // Se der erro no banco, retorna a mensagem de erro
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>