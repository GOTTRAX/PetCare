<?php
include '../conexao.php';

$cliente_id = 1; // depois substitui pelo ID do usuário logado (sessão)
$animal_id = $_POST['animal_id'];
$servico_id = $_POST['servico_id'];
$data = $_POST['data'];
$hora_inicio = $_POST['hora_inicio'];
$hora_final = $_POST['hora_final'];
$observacoes = $_POST['observacoes'] ?? '';

$sql = "INSERT INTO Agendamentos 
        (cliente_id, animal_id, servico_id, data_hora, hora_inicio, hora_final, status, observacoes) 
        VALUES (:cliente_id, :animal_id, :servico_id, :data, :hora_inicio, :hora_final, 'pendente', :observacoes)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':cliente_id' => $cliente_id,
    ':animal_id' => $animal_id,
    ':servico_id' => $servico_id,
    ':data' => $data,
    ':hora_inicio' => $hora_inicio,
    ':hora_final' => $hora_final,
    ':observacoes' => $observacoes
]);

echo json_encode(["sucesso" => true]);
//solicitar.php - INSERE um novo agendamento
//Recebe dados via POST (animal_id, servico_id, data, etc.)
//Cria um novo registro na tabela Agendamentos
//Define o status como 'pendente'
//Retorna um JSON confirmando sucesso