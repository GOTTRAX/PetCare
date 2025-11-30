<?php 
// Inclui o arquivo de conexão com o banco de dados (disponibiliza o objeto $pdo)
include("conexao.php");

// Atualiza automaticamente o status dos agendamentos que já passaram
// Busca agendamentos com status 'confirmado' onde a data/hora final já passou
// e muda o status deles para 'finalizado'
$pdo->query("
    UPDATE Agendamentos
    SET status = 'finalizado'
    WHERE status = 'confirmado'
      AND TIMESTAMP(data_hora, hora_final) < NOW()
");

// Busca todos os agendamentos do banco de dados
// ORDER BY data_hora DESC = ordena do mais recente para o mais antigo
$stmt = $pdo->query("SELECT * FROM Agendamentos ORDER BY data_hora DESC");

// Transforma o resultado da query em um array associativo
// Cada agendamento vira um array com as colunas como chaves
// Exemplo: $agendamentos[0]['nome_cliente'], $agendamentos[0]['data_hora'], etc
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>