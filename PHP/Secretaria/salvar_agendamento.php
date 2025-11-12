<?php
session_start();
require '../conexao.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar se é secretaria ou veterinário
        $tipo_usuario = $_SESSION['tipo_usuario'];
        
        // A chave aqui é SEMPRE usar o cliente_id do formulário, não da sessão
        $cliente_id = $_POST['cliente_id'];
        $animal_id = $_POST['animal_id'];
        $servico_id = $_POST['servico_id'];
        $data = $_POST['data'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_final = $_POST['hora_final'];
        $observacoes = $_POST['observacoes'] ?? '';
        
        // Validar dados
        if (empty($cliente_id) || empty($animal_id) || empty($servico_id) || empty($data) || empty($hora_inicio)) {
            throw new Exception("Todos os campos obrigatórios devem ser preenchidos.");
        }
        
        // Verificar se o animal pertence ao cliente
        $stmt = $pdo->prepare("SELECT id FROM Animais WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$animal_id, $cliente_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Animal não pertence ao cliente selecionado.");
        }
        
        // Inserir agendamento
        $stmt = $pdo->prepare("
            INSERT INTO Agendamentos 
            (cliente_id, animal_id, servico_id, data, hora_inicio, hora_final, observacoes, status, criado_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?)
        ");
        
        $criado_por = $_SESSION['id']; // Quem criou o agendamento (secretaria/veterinário)
        
        $success = $stmt->execute([
            $cliente_id, 
            $animal_id, 
            $servico_id, 
            $data, 
            $hora_inicio, 
            $hora_final, 
            $observacoes,
            $criado_por
        ]);
        
        if ($success) {
            // Buscar informações para o dashboard
            $agendamento_id = $pdo->lastInsertId();
            
            // Buscar informações completas do agendamento
            $stmt = $pdo->prepare("
                SELECT a.*, u.nome as cliente_nome, an.nome as animal_nome, s.nome as servico_nome
                FROM Agendamentos a
                JOIN Usuarios u ON a.cliente_id = u.id
                JOIN Animais an ON a.animal_id = an.id
                JOIN Servicos s ON a.servico_id = s.id
                WHERE a.id = ?
            ");
            $stmt->execute([$agendamento_id]);
            $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Atualizar dashboard com novo agendamento
            $_SESSION['ultimo_agendamento'] = $agendamento;
            
            header("Location: ../dashboard.php?msg=Agendamento+criado+com+sucesso&tipo=sucesso");
            exit;
        } else {
            throw new Exception("Erro ao salvar agendamento no banco de dados.");
        }
        
    } catch (Exception $e) {
        header("Location: calendario.php?msg=" . urlencode($e->getMessage()) . "&tipo=erro");
        exit;
    }
} else {
    header("Location: calendario.php");
    exit;
}
?>