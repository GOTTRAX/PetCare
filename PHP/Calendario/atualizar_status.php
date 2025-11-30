<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// Pega o ID e tipo do usuário da sessão (se existir, senão retorna null)
$usuario_id = $_SESSION['id'] ?? null;
$tipo_usuario = $_SESSION['tipo_usuario'] ?? null;

// ========== VALIDAÇÃO DE AUTENTICAÇÃO ==========
// Verifica se o usuário está logado
if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'erro' => 'Usuário não autenticado.']);
    exit; // Interrompe a execução do script
}

// ========== VALIDAÇÃO DE ENTRADA ==========
// Recebe os dados enviados via POST
$id = $_POST['id'] ?? null; // ID do agendamento
$status = $_POST['status'] ?? null; // Novo status

// Verifica se ambos os campos foram preenchidos
if (!$id || !$status) {
    echo json_encode(['status' => 'erro', 'erro' => 'Dados incompletos.']);
    exit;
}

// Define quais status são permitidos (apenas esses dois)
$permitidos = ['confirmado', 'cancelado'];

// Verifica se o status enviado está na lista de permitidos
if (!in_array($status, $permitidos)) {
    echo json_encode(['status' => 'erro', 'erro' => 'Status inválido.']);
    exit;
}

// ========== VALIDAÇÃO DE PERMISSÃO ==========
// Busca o agendamento no banco para verificar quem é o dono
$stmt = $pdo->prepare("SELECT cliente_id, veterinario_id FROM Agendamentos WHERE id = ?");
$stmt->execute([$id]);
$agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o agendamento não existe, retorna erro
if (!$agendamento) {
    echo json_encode(['status' => 'erro', 'erro' => 'Agendamento não encontrado.']);
    exit;
}

// Verifica se o usuário é dono do agendamento
// (pode ser o cliente OU o veterinário responsável)
$isDono = ($agendamento['cliente_id'] == $usuario_id || $agendamento['veterinario_id'] == $usuario_id);

// Verifica se o usuário é superusuário (Secretaria ou Veterinário)
// Esses tipos têm permissão para alterar qualquer agendamento
$isSuperUsuario = ($tipo_usuario === 'Secretaria' || $tipo_usuario === 'Veterinario');

// Se não é dono E não é superusuário, nega o acesso
if (!$isDono && !$isSuperUsuario) {
    echo json_encode(['status' => 'erro', 'erro' => 'Sem permissão para alterar este agendamento.']);
    exit;
}

// ========== ATUALIZAÇÃO DO STATUS ==========
// Se passou em todas as validações, atualiza o status
$stmt = $pdo->prepare("UPDATE Agendamentos SET status = ? WHERE id = ?");

if ($stmt->execute([$status, $id])) {
    // Se executou com sucesso, retorna ok
    echo json_encode(['status' => 'ok']);
} else {
    // Se deu erro na execução, retorna erro
    echo json_encode(['status' => 'erro', 'erro' => 'Erro ao atualizar.']);
}

// ========== RESUMO DO FLUXO ==========
// 1. Verifica se está logado
// 2. Valida se enviou id e status
// 3. Valida se o status é permitido (confirmado ou cancelado)
// 4. Busca o agendamento no banco
// 5. Verifica se tem permissão (dono ou superusuário)
// 6. Atualiza o status no banco
// 7. Retorna resposta em JSON
?>