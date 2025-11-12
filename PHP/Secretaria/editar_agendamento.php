<?php
// Ativar exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Buffer de saída para capturar qualquer erro
ob_start();

session_start();

// Verificar se conexao.php existe
$conexaoPath = '../conexao.php';
if (!file_exists($conexaoPath)) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Arquivo conexao.php não encontrado']);
    exit;
}

require $conexaoPath;

// Limpar qualquer saída anterior
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        exit;
    }

    // Buscar informações do agendamento atual
    $stmt = $pdo->prepare("SELECT data, status FROM Agendamentos WHERE id = ?");
    $stmt->execute([$id]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
        exit;
    }

    // Construir SQL dinamicamente baseado nos campos enviados
    $campos = [];
    $valores = [];

    // Verificar se está tentando editar data
    if (isset($_POST['data'])) {
        $novaData = $_POST['data'];
        $hoje = date('Y-m-d');
        
        // Permitir editar se:
        // 1. A nova data é hoje ou no futuro, OU
        // 2. Está apenas mudando o status para 'finalizado' na mesma data
        $dataAtual = $agendamento['data'];
        $statusNovo = $_POST['status'] ?? $agendamento['status'];
        
        // Se está mudando para finalizado, permitir qualquer data
        if ($statusNovo !== 'finalizado' && $novaData < $hoje) {
            echo json_encode(['success' => false, 'error' => 'Não é possível agendar em datas passadas']);
            exit;
        }
        
        $campos[] = "data = ?";
        $valores[] = $novaData;
    }

    if (isset($_POST['hora_inicio'])) {
        $campos[] = "hora_inicio = ?";
        $valores[] = $_POST['hora_inicio'];
    }

    if (isset($_POST['hora_final'])) {
        $campos[] = "hora_final = ?";
        $valores[] = $_POST['hora_final'];
    }

    if (isset($_POST['status'])) {
        $campos[] = "status = ?";
        $valores[] = $_POST['status'];
    }

    if (isset($_POST['observacoes'])) {
        $campos[] = "observacoes = ?";
        $valores[] = $_POST['observacoes'];
    }

    if (empty($campos)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
        exit;
    }

    $valores[] = $id; // ID no final para WHERE

    $sql = "UPDATE Agendamentos SET " . implode(", ", $campos) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($valores)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Agendamento atualizado com sucesso',
            'updated_fields' => array_keys($_POST)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao executar atualização']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>