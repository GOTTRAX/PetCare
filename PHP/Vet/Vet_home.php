<?php
session_start();
if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] !== "Veterinario") {
    header("Location: ../index.php");
    exit();
}

include '../conexao.php';

$veterinario_nome = $_SESSION['nome'];
$veterinario_email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Veterinário - PetCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ========================================
   VARIÁVEIS CSS
======================================== */
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #60a5fa;
            --secondary: #10b981;
            --secondary-dark: #059669;
            
            --bg-page: #e8ecf1;
            --bg-card: #ffffff;
            --bg-header: #5B6FE8;
            
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-white: #ffffff;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            
            --transition: 300ms ease;
        }

        /* ========================================
   RESET
======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background-color: var(--bg-page);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* ========================================
   CONTAINER PRINCIPAL
======================================== */
        .main-container {
            max-width: 900px;
            width: 100%;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========================================
   CARD PRINCIPAL
======================================== */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }

        /* ========================================
   HEADER DO CARD
======================================== */
        .card-header {
            background: linear-gradient(135deg, var(--bg-header) 0%, var(--primary) 100%);
            padding: 2rem 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -150px;
            right: -100px;
        }

        .card-header::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -100px;
            left: -50px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .header-left {
            flex: 1;
        }

        .header-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 0.5rem;
        }

        .header-email {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-email i {
            font-size: 0.9rem;
        }

        .btn-logout-header {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            color: var(--text-white);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all var(--transition);
        }

        .btn-logout-header:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-logout-header i {
            font-size: 1rem;
        }

        /* ========================================
   BODY DO CARD
======================================== */
        .card-body {
            padding: 2.5rem;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .section-description {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* ========================================
   GRID DE AÇÕES
======================================== */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .action-card {
            background: var(--bg-card);
            border: 2px solid #e8ecf1;
            border-radius: var(--radius-lg);
            padding: 2rem 1.5rem;
            text-align: center;
            text-decoration: none;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, transparent);
            transition: all var(--transition);
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }

        .action-card.card-blue:hover::before {
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .action-card.card-purple:hover::before {
            background: linear-gradient(90deg, #a78bfa, #8b5cf6);
        }

        .action-card.card-pink:hover::before {
            background: linear-gradient(90deg, #f472b6, #ec4899);
        }

        .action-card.card-blue:hover {
            border-color: var(--primary-light);
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }

        .action-card.card-purple:hover {
            border-color: #a78bfa;
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
        }

        .action-card.card-pink:hover {
            border-color: #f472b6;
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
        }

        .action-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            transition: all var(--transition);
        }

        .card-blue .action-icon-wrapper {
            background: linear-gradient(135deg, #3b82f6 0%, var(--primary) 100%);
        }

        .card-purple .action-icon-wrapper {
            background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%);
        }

        .card-pink .action-icon-wrapper {
            background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%);
        }

        .action-card:hover .action-icon-wrapper {
            transform: scale(1.1) rotate(-5deg);
        }

        .action-icon {
            font-size: 1.75rem;
            color: var(--text-white);
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .action-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-white);
            border: none;
            cursor: pointer;
            transition: all var(--transition);
        }

        .card-blue .action-button {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .card-purple .action-button {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .card-pink .action-button {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }

        .action-button:hover {
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-button i {
            font-size: 0.8rem;
        }

        /* ========================================
   FOOTER DO CARD
======================================== */
        .card-footer {
            background: #f8fafc;
            padding: 1.5rem 2.5rem;
            text-align: center;
            border-top: 1px solid #e8ecf1;
        }

        .footer-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .footer-text strong {
            color: var(--text-primary);
        }

        /* ========================================
   RESPONSIVIDADE
======================================== */
        @media (max-width: 968px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .header-email {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .card-header {
                padding: 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .card-footer {
                padding: 1.25rem 1.5rem;
            }

            .header-title {
                font-size: 1.4rem;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .actions-grid {
                gap: 1rem;
            }

            .action-card {
                padding: 1.5rem 1rem;
            }

            .action-icon-wrapper {
                width: 56px;
                height: 56px;
                margin-bottom: 1rem;
            }

            .action-icon {
                font-size: 1.5rem;
            }

            .action-title {
                font-size: 1rem;
            }

            .action-description {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .card-header {
                padding: 1.25rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .header-title {
                font-size: 1.2rem;
            }

            .header-email {
                font-size: 0.85rem;
            }

            .btn-logout-header {
                padding: 0.625rem 1.25rem;
                font-size: 0.85rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .section-description {
                font-size: 0.875rem;
            }

            .action-icon-wrapper {
                width: 48px;
                height: 48px;
            }

            .action-icon {
                font-size: 1.3rem;
            }
        }

        /* ========================================
   ANIMAÇÕES
======================================== */
        .action-card {
            animation: slideUp 0.6s ease both;
        }

        .action-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .action-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .action-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Efeito de onda no hover */
        .action-card {
            position: relative;
            overflow: hidden;
        }

        .action-card::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .action-card:hover::after {
            left: 100%;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="card">
            <!-- Header -->
            <div class="card-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="header-title">Bem-vindo(a), Dr(a). <?= htmlspecialchars($veterinario_nome) ?>!</h1>
                        <div class="header-email">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($veterinario_email) ?>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn-logout-header">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>

            <!-- Body -->
            <div class="card-body">
                <h2 class="section-title">Área do Veterinário</h2>
                <p class="section-description">
                    Gerencie todos os aspectos dos atendimentos veterinários, visualize fichas completas dos animais e acesse prontuários médicos de forma rápida e organizada.
                </p>

                <!-- Grid de Ações -->
                <div class="actions-grid">
                    <!-- Fichas dos Animais -->
                    <a href="FichaAnimal.php" class="action-card card-blue">
                        <div class="action-icon-wrapper">
                            <i class="fas fa-notes-medical action-icon"></i>
                        </div>
                        <h3 class="action-title">Fichas dos Animais</h3>
                        <p class="action-description">
                            Acesso completo às fichas médicas e informações detalhadas de todos os animais atendidos.
                        </p>
                        <button class="action-button">
                            Acessar
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </a>

                    <!-- Novo Atendimento -->
                    <a href="atendimentos.php" class="action-card card-purple">
                        <div class="action-icon-wrapper">
                            <i class="fas fa-file-medical action-icon"></i>
                        </div>
                        <h3 class="action-title">Prontuários</h3>
                        <p class="action-description">
                            Registre novos atendimentos incluindo diagnósticos, tratamentos e prescrições.
                        </p>
                        <button class="action-button">
                            Iniciar
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </a>

                    <!-- Consultas Agendadas -->
                    <a href="calendario.php" class="action-card card-pink">
                        <div class="action-icon-wrapper">
                            <i class="fas fa-calendar-alt action-icon"></i>
                        </div>
                        <h3 class="action-title">Consultas Agendadas</h3>
                        <p class="action-description">
                            Visualize e gerencie seus agendamentos e compromissos de forma eficiente.
                        </p>
                        <button class="action-button">
                            Ver Agenda
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer">
                <p class="footer-text">
                    &copy; <?= date('Y') ?> <strong>PetCare</strong> | Clínica Veterinária. Todos os direitos reservados.<br>
                    Versão 2.0.1
                </p>
            </div>
        </div>
    </div>
</body>

</html>