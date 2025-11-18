<?php
include("conexao.php");

// Atualiza status automaticamente
$pdo->query("
    UPDATE Agendamentos
    SET status = 'finalizado'
    WHERE status = 'confirmado'
      AND TIMESTAMP(data_hora, hora_final) < NOW()
");

$stmt = $pdo->query("SELECT * FROM Agendamentos ORDER BY data_hora DESC");
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
