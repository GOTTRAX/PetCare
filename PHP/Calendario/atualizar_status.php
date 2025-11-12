<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

$usuario_id = $_SESSION['id'] ?? null;
$tipo_usuario = $_SESSION['tipo_usuario'] ?? null;

if (!$usuario_id) {
    echo json_encode(['status' => 'erro', 'erro' => 'Usuário não autenticado.']);
    exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['status' => 'erro', 'erro' => 'Dados incompletos.']);
    exit;
}

$permitidos = ['confirmado', 'cancelado'];
if (!in_array($status, $permitidos)) {
    echo json_encode(['status' => 'erro', 'erro' => 'Status inválido.']);
    exit;
}

// Verifica se o usuário é dono OU superusuário
$stmt = $pdo->prepare("SELECT cliente_id, veterinario_id FROM Agendamentos WHERE id = ?");
$stmt->execute([$id]);
$agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agendamento) {
    echo json_encode(['status' => 'erro', 'erro' => 'Agendamento não encontrado.']);
    exit;
}

$isDono = ($agendamento['cliente_id'] == $usuario_id || $agendamento['veterinario_id'] == $usuario_id);
$isSuperUsuario = ($tipo_usuario === 'Secretaria' || $tipo_usuario === 'Veterinario');

if (!$isDono && !$isSuperUsuario) {
    echo json_encode(['status' => 'erro', 'erro' => 'Sem permissão para alterar este agendamento.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE Agendamentos SET status = ? WHERE id = ?");
if ($stmt->execute([$status, $id])) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'erro', 'erro' => 'Erro ao atualizar.']);
}
//Atualizar Status
//Valida autenticação:

//Verifica se usuário está logado na sessão
//Pega o ID e tipo de usuário da sessão

//Valida entrada:

//Exige id e status (ambos obrigatórios)
//Só aceita status 'confirmado' ou 'cancelado'

//Verifica permissão:

//Busca o agendamento no banco de dados
//Verifica se é cliente dono OU veterinário responsável OU superusuário (Secretaria/Veterinario)
//Nega acesso se não tiver permissão

//Atualiza o status:

//se passou em todas validações, faz UPDATE apenas do status
//Retorna ['status' => 'ok'] ou ['status' => 'erro', 'erro' => motivo]