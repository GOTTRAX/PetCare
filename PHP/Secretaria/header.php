<?php

// header.php - Cabeçalho com sidebar
$paginaTitulo = isset($paginaTitulo) ? $paginaTitulo : "Sistema PetCare";

// Iniciar sessão (só se ainda não estiver ativa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se usuário está logado (adaptar conforme sua lógica)
if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] !== "Secretaria") {
    header("Location: ../index.php");
    exit();
}


// =================== DETERMINAR PÁGINA ATIVA ===================
$current_script = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];

// Verificar páginas ativas (SUA LÓGICA)
$home_active = ($current_script == 'sec_home.php' || $current_script == 'index.php') ? 'active' : '';
$usuarios_active        = (strpos($current_path, 'usuarios.php') !== false) ? 'active' : '';
$animais_active         = (strpos($current_path, 'CrudAnimal.php') !== false) ? 'active' : '';
$perfil_active          = (strpos($current_path, 'perfil.php') !== false) ? 'active' : '';
$calendario_active      = (strpos($current_path, 'calendario.php') !== false) ? 'active' : '';
$equipe_active          = (strpos($current_path, 'equipe.php') !== false) ? 'active' : '';
$relatorios_active      = (strpos($current_path, 'relatorios') !== false) ? 'active' : '';
$configuracoes_active   = (strpos($current_path, 'config.php') !== false) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $paginaTitulo; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS DA SIDEBAR */
        #secretaria-sidebar {
            height: 100vh;
            width: 70px;
            position: fixed;
            top: 0;
            left: 0;
            background: linear-gradient(to bottom, #2c3e50, #1a2530);
            overflow-x: hidden;
            transition: width 0.3s ease;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.2);
            padding-top: 20px;
        }
        * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

        #secretaria-sidebar:hover {
            width: 250px;
        }

        .sidebar-logo-container {
            display: flex;
            align-items: center;
            padding: 0 16px;
            margin-bottom: 30px;
        }

        .sidebar-logo {
            color: white;
            font-size: 28px;
            min-width: 40px;
            text-align: center;
        }

        .sidebar-logo-text {
            color: white;
            margin-left: 15px;
            font-weight: bold;
            font-size: 18px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s ease 0.1s;
        }

        #secretaria-sidebar:hover .sidebar-logo-text {
            opacity: 1;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            transition: background-color 0.2s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu li:hover, .sidebar-menu li.active {
            background-color: #34495e;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu a {
            text-decoration: none;
            color: #ecf0f1;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .sidebar-menu i {
            font-size: 22px;
            min-width: 40px;
            text-align: center;
        }

        .sidebar-menu-text {
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s ease 0.1s;
            margin-left: 10px;
            font-size: 16px;
        }

        #secretaria-sidebar:hover .sidebar-menu-text {
            opacity: 1;
        }

        /* CONTEÚDO PRINCIPAL */
        .content {
            margin-left: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        /* AJUSTE PARA QUANDO O MENU EXPANDIR */
        #secretaria-sidebar:hover ~ .content {
            margin-left: 250px;
        }

        /* BOTÃO TOGGLE MOBILE */
        #sidebar-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 22px;
            z-index: 1001;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: none;
            align-items: center;
            justify-content: center;
        }

        /* RESPONSIVIDADE PARA MOBILE */
        @media (max-width: 768px) {
            #secretaria-sidebar {
                width: 0;
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            #sidebar-toggle {
                display: flex;
            }
            
            #secretaria-sidebar.mobile-open {
                width: 250px;
            }
            
            #secretaria-sidebar.mobile-open ~ .content {
                margin-left: 0;
                position: fixed;
                filter: brightness(0.7);
                pointer-events: none;
            }
        }
    </style>
</head>
<body>
    <?php
    include 'sidebar.php';
    renderSidebar();
    ?>
    
    <div class="content">