
<?php
include "conexao.php";

// Definir base_path no início
$base_path = '/Bruno/PetCare/';

// Verificar login
$isLogged = isset($_SESSION['id']);

// Inicializar variáveis de menu ativo
$perfil_active = '';
$configuracoes_active = '';
$pets_active = '';
$agendamento_active = '';

// Definir menu ativo baseado na URL
if (isset($_GET['aba'])) {
    switch($_GET['aba']) {
        case 'config': $configuracoes_active = 'active'; break;
        case 'pets': $pets_active = 'active'; break;
        case 'Agendamentos': $agendamento_active = 'active'; break;
        default: $perfil_active = 'active';
    }
}

// Processar nome do usuário
$abbreviatedName = '';
if ($isLogged && isset($_SESSION['nome'])) {
    $nameParts = explode(' ', $_SESSION['nome']);
    if (count($nameParts) > 0) {
        $abbreviatedName = $nameParts[0];
        if (count($nameParts) > 1) {
            $abbreviatedName .= ' ' . strtoupper(substr($nameParts[count($nameParts)-1], 0, 1)) . '.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo $base_path; ?>Estilos/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>PetCare</title>
</head>
<body>

<header>
    <nav class="navbar">
        <a href="<?php echo $base_path; ?>index.php" class="logo">PetCare</a>

        <ul class="nav-links">
            <!-- Itens que SEMPRE aparecem -->
            <li><a href="<?php echo $base_path; ?>index.php">Home</a></li>
            <li><a href="<?php echo $base_path; ?>PHP/sobre.php">Sobre</a></li>
            <li><a href="<?php echo $base_path; ?>PHP/equipe.php">Equipe</a></li>

            <!-- Itens que aparecem SOMENTE se estiver logado -->
            <?php if ($isLogged): ?>
                <li><a href="<?php echo $base_path; ?>PHP/Calendario/Calendario.php">Calendário</a></li>
                <li class="user-menu">
                    <div class="user-name-container">
                        <span class="user-name"><?php echo htmlspecialchars($abbreviatedName); ?></span>
                        <div class="dropdown-menu">
                            <a href="<?php echo $base_path; ?>PHP/Cliente/perfil.php" class="<?php echo $perfil_active; ?>">Perfil</a>
                            <a href="<?php echo $base_path; ?>PHP/Cliente/perfil.php?aba=config" class="<?php echo $configuracoes_active; ?>">Configurações</a>
                            <a href="<?php echo $base_path; ?>PHP/Cliente/perfil.php?aba=pets" class="<?php echo $pets_active; ?>">Pets</a>
                            <a href="<?php echo $base_path; ?>PHP/Cliente/perfil.php?aba=Agendamentos" class="<?php echo $agendamento_active; ?>">Agendamento</a>
                            <a href="<?php echo $base_path; ?>PHP/logout.php" class="logout">Sair</a>
                        </div>
                    </div>
                </li>
            <!-- Itens que aparecem se NÃO estiver logado -->
            <?php else: ?>
                <li><a href="<?php echo $base_path; ?>PHP/login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>