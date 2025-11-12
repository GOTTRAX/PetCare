<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] !== "Veterinario") {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Área do Veterinário - PetCare</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Fontes e ícones -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../Estilos/vet.css">
    <!-- CSS Interno -->
    
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="card">
                <div class="card-header">
                    <div class="user-info">
                        <h1>Bem-vindo(a), Dr(a). <?php echo htmlspecialchars($_SESSION["nome"]); ?></h1>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($_SESSION["email"]); ?></p>
                    </div>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
                
                <div class="card-body">
                    <h2 class="section-title">Área do Veterinário</h2>
                    <p class="welcome-text">
                        Gerencie todos os aspectos dos atendimentos veterinários, visualize fichas completas dos animais 
                        e acesse prontuários médicos de forma rápida e organizada.
                    </p>
                    
                    <div class="actions-grid">
                        <div class="action-card">
                            <div class="action-icon icon-ficha">
                                <i class="fas fa-notes-medical"></i>
                            </div>
                            <h3 class="action-title">Fichas dos Animais</h3>
                            <p class="action-desc">
                                Acesse o histórico completo e informações detalhadas de todos os animais atendidos.
                            </p>
                            <a href="FichaAnimal.php" class="action-link">
                                Acessar <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="action-card">
                            <div class="action-icon icon-atendimento">
                                <i class="fas fa-paw"></i>
                            </div>
                            <h3 class="action-title">Novo Atendimento</h3>
                            <p class="action-desc">
                                Registre um novo atendimento médico, incluindo diagnóstico, tratamento e prescrições.
                            </p>
                            <a href="atendimentos.php" class="action-link">
                                Iniciar <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="action-card">
                            <div class="action-icon icon-consulta">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="action-title">Consultas Agendadas</h3>
                            <p class="action-desc">
                                Visualize sua agenda de consultas marcadas e gerencie seus compromissos.
                            </p>
                            <a href="calendario.php" class="action-link">
                                Ver Agenda <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <footer>
            © 2025 PetCare Clínica Veterinária. Todos os direitos reservados.
            <div style="margin-top: 10px;">
                <small>Versão 2.0.0</small>
            </div>
        </footer>
    </div>
</body>
</html>