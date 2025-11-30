<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// ========== VALIDAÇÃO DE AUTENTICAÇÃO ==========
// Verifica se o usuário está logado (se existe ID na sessão)
if (!isset($_SESSION['id'])) {
    // Define código HTTP 401 (não autorizado)
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit; // Interrompe a execução
}

// ========== VALIDAÇÃO DO MÉTODO HTTP ==========
// Só aceita requisições POST (método correto para exclusão)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    // ========== COLETA DO ID DO AGENDAMENTO ==========
    // Pega o ID enviado via POST, se não existir retorna null
    $id = $_POST['id'] ?? null;
    
    // Valida se o ID foi fornecido e não está vazio
    if (!$id || $id === '') {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }

    // ========== DADOS DO USUÁRIO LOGADO ==========
    // Pega o ID e tipo do usuário da sessão
    $usuario_id = $_SESSION['id'];
    $tipo_usuario = $_SESSION['tipo_usuario'];

    // ========== BUSCA O AGENDAMENTO E VERIFICA EXISTÊNCIA ==========
    // Busca o agendamento com JOIN para pegar o nome do cliente
    $sql = "SELECT a.id, a.cliente_id, u.nome as cliente_nome 
            FROM Agendamentos a
            JOIN Usuarios u ON a.cliente_id = u.id
            WHERE a.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou o agendamento, retorna erro
    if (!$agendamento) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
        exit;
    }

    // ========== VERIFICAÇÃO DE PERMISSÃO ==========
    // Cliente só pode excluir seus próprios agendamentos
    // Verifica se é Cliente E se o agendamento não pertence a ele
    if ($tipo_usuario === 'Cliente' && $agendamento['cliente_id'] != $usuario_id) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este agendamento']);
        exit;
    }

    // ========== LÓGICA DE PERMISSÃO ==========
    // Se chegou aqui, significa que:
    // - OU é o próprio cliente (dono do agendamento)
    // - OU é Veterinário ou Secretária (podem excluir qualquer agendamento)

    // ========== EXCLUSÃO DO AGENDAMENTO ==========
    // Prepara a query para deletar o agendamento
    $stmt = $pdo->prepare("DELETE FROM Agendamentos WHERE id = ?");
    
    // Executa a exclusão
    if ($stmt->execute([$id])) {
        // Se executou com sucesso, retorna sucesso
        echo json_encode([
            'success' => true, 
            'message' => 'Agendamento excluído com sucesso',
            'deleted_id' => $id // Retorna o ID excluído para o frontend remover da tela
        ]);
    } else {
        // Se falhou a execução, retorna erro
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir agendamento no banco de dados']);
    }

} catch (PDOException $e) {
    // ========== TRATAMENTO DE ERRO DO BANCO ==========
    // Define código HTTP 500 (erro interno do servidor)
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // ========== TRATAMENTO DE ERRO GERAL ==========
    // Captura qualquer outro tipo de erro
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

// ========== RESUMO DO FLUXO ==========
// 1. Verifica se está logado
// 2. Valida se é método POST
// 3. Valida se o ID foi enviado
// 4. Busca o agendamento no banco
// 5. Verifica permissão:
//    - Cliente: só pode excluir seus próprios agendamentos
//    - Veterinário/Secretária: podem excluir qualquer agendamento
// 6. Exclui do banco de dados
// 7. Retorna resposta JSON com sucesso ou erro
?>