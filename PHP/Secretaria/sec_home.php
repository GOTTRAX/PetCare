<?php
// Inicia a sessão
session_start();
include '../conexao.php';

// Verifica se o usuário está logado e é Secretaria
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    header("Location: ../../index.php");
    exit;
}

// Carrega dados do usuário do banco de dados
$usuario_id = $_SESSION['id'];
$nome_usuario = $_SESSION['nome'] ?? 'Secretaria';
$email_usuario = $_SESSION['email'] ?? 'sec@sec.com';
$foto_perfil = $_SESSION['foto'] ?? 'default_profile.jpg';
$iniciais = 'SC';

try {
    if (!isset($pdo)) {
        throw new Exception("Erro: Conexão com o banco de dados não está definida.");
    }

    $stmt = $pdo->prepare("SELECT nome, email, foto FROM Usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $_SESSION['nome'] = $usuario['nome'] ?? $nome_usuario;
        $_SESSION['email'] = $usuario['email'] ?? $email_usuario;
        $_SESSION['foto'] = $usuario['foto'] ?? $foto_perfil;
        $nome_usuario = $_SESSION['nome'];
        $email_usuario = $_SESSION['email'];
        $foto_perfil = $_SESSION['foto'];

        // Calcula iniciais
        $nomes = explode(' ', trim($nome_usuario));
        if (count($nomes) > 0) {
            $iniciais = strtoupper(substr($nomes[0], 0, 1) . (count($nomes) > 1 ? substr(end($nomes), 0, 1) : ''));
        }
    } else {
        error_log("Usuário com ID $usuario_id não encontrado no banco de dados.");
    }
} catch (Exception $e) {
    error_log("Erro ao carregar dados do usuário: " . $e->getMessage());
    // Continuar com valores padrão
}

// Consultas para obter os dados do dashboard
$usuarios = ['total' => 0];
$animais = ['total' => 0];
$agendamentos = ['total' => 0];
$agendamentos_pendentes = ['total' => 0];
$consultas_hoje = ['total' => 0];
$especies_data = [];
$agendamentos_mes = [];
$proximos_agendamentos = [];

try {
    // Total de usuários
    $query_usuarios = "SELECT COUNT(*) as total FROM Usuarios WHERE ativo = TRUE AND tipo_usuario = 'Cliente'";

    $result = $pdo->query($query_usuarios);
    if ($result) {
        $usuarios = $result->fetch(PDO::FETCH_ASSOC);
    }

    // Total de animais
    $query_animais = "SELECT COUNT(*) as total FROM Animais";
    $result = $pdo->query($query_animais);
    if ($result) {
        $animais = $result->fetch(PDO::FETCH_ASSOC);
    }

    // Total de agendamentos confirmados
    $query_agendamentos = "SELECT COUNT(*) as total FROM Agendamentos WHERE status = 'confirmado'";
    $result = $pdo->query($query_agendamentos);
    if ($result) {
        $agendamentos = $result->fetch(PDO::FETCH_ASSOC);
    }

    // Total de agendamentos pendentes
    $query_agendamentos_pendentes = "SELECT COUNT(*) as total FROM Agendamentos WHERE status = 'pendente'";
    $result = $pdo->query($query_agendamentos_pendentes);
    if ($result) {
        $agendamentos_pendentes = $result->fetch(PDO::FETCH_ASSOC);
    }

    // Total de consultas hoje
    $query_consultas_hoje = "SELECT COUNT(*) as total FROM Consultas WHERE DATE(data_consulta) = CURDATE()";
    $result = $pdo->query($query_consultas_hoje);
    if ($result) {
        $consultas_hoje = $result->fetch(PDO::FETCH_ASSOC);
    }

    // Dados para gráficos (espécies)
    $query_especies = "SELECT e.nome, COUNT(a.id) as total 
                       FROM Animais a 
                       INNER JOIN Especies e ON a.especie_id = e.id 
                       GROUP BY e.id, e.nome";
    $result = $pdo->query($query_especies);
    if ($result) {
        $especies_data = $result->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agendamentos dos últimos 30 dias
    $query_agendamentos_mes = "SELECT DATE(data_hora) as dia, COUNT(*) as total 
                               FROM Agendamentos 
                               WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                               GROUP BY DATE(data_hora) 
                               ORDER BY dia";
    $result = $pdo->query($query_agendamentos_mes);
    if ($result) {
        $agendamentos_mes = $result->fetchAll(PDO::FETCH_ASSOC);
    }

    // Próximos agendamentos

    $query_proximos_agendamentos = "SELECT a.data_hora, a.hora_inicio, u.nome as cliente, an.nome as animal, a.status, s.nome as procedimento 
                                FROM Agendamentos a 
                                INNER JOIN Animais an ON a.animal_id = an.id 
                                INNER JOIN Usuarios u ON an.usuario_id = u.id 
                                LEFT JOIN Servicos s ON a.servico_id = s.id 
                                WHERE a.data_hora >= CURDATE() 
                                ORDER BY a.data_hora, a.hora_inicio 
                                LIMIT 5";
    $result = $pdo->query($query_proximos_agendamentos);
    if ($result) {
        $proximos_agendamentos = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao executar consultas do dashboard: " . $e->getMessage());
    // Continuar com valores padrão (já inicializados como arrays vazios ou zeros)
}

// Definir título da página
$paginaTitulo = "Dashboard Secretaria";

// Incluir header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paginaTitulo) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            background-image: url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm28-65c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm23-11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm-6 60c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm35 35c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zM73 10c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm-27 8c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zM32 63c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm57 0c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm-68-30c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 16c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm-28 29c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4z" fill="%23e5e7eb" fill-opacity="0.1" fill-rule="evenodd"/%3E%3C/svg%3E');
            background-repeat: repeat;
            min-height: 100vh;
            color: var(--gray-900);
        }

        /* Main Content */
        .main-content {
            margin-left: 100px;
            padding: 32px 16px 32px 32px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin-left: 0;
            margin-right: auto;
        }

        /* Page Header */
        .page-header {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title h1 i {
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-details span {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .user-details small {
            font-size: 12px;
            color: var(--gray-500);
        }

        .search-container {
            position: relative;
            max-width: 300px;
            width: 100%;
        }

        .search-container input {
            padding: 10px 14px 10px 40px;
            /* Espaço à esquerda para o ícone */
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: var(--white);
            color: var(--gray-900);
            font-family: 'Inter', sans-serif;
            width: 100%;
        }

        .search-container input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-container .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            font-size: 16px;
            pointer-events: none;
            /* Evita que o ícone seja clicável */
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .stat-card.primary::before {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-card.success::before {
            background: var(--success);
        }

        .stat-card.danger::before {
            background: var(--danger);
        }

        .stat-card.warning::before {
            background: var(--warning);
        }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-info p {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
            color: var(--primary);
        }

        .stat-card.success .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-card.danger .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .stat-card.warning .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        /* Charts Section */
        .charts-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
        }

        .charts-section h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .charts-section h2 i {
            color: var(--primary);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .chart-card {
            background: var(--gray-50);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
        }

        .chart-card canvas {
            max-height: 250px;
        }

        /* Table Card */
        .table-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            margin-bottom: 32px;
            overflow: hidden;
        }

        .table-header {
            padding: 20px 32px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h3 i {
            color: var(--primary);
        }

        .see-all {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .see-all:hover {
            color: var(--primary-dark);
        }

        .agendamento-list {
            list-style: none;
            max-height: 300px;
            overflow-y: auto;
            padding: 20px 32px;
        }

        .agendamento-list::-webkit-scrollbar {
            width: 8px;
        }

        .agendamento-list::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        .agendamento-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .agendamento-list::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .agendamento-list {
            scrollbar-width: thin;
            scrollbar-color: var(--primary) var(--gray-100);
        }

        .agendamento-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .agendamento-item:last-child {
            border-bottom: none;
        }

        .agendamento-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .agendamento-info p {
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 4px;
        }

        .agendamento-procedimento {
            font-size: 13px;
            color: var(--gray-600);
        }

        .agendamento-details {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .agendamento-time {
            font-size: 13px;
            color: var(--gray-600);
            white-space: nowrap;
        }

        .agendamento-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-confirmado {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-pendente {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-cancelado {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 24px 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px 12px;
            }

            .page-header {
                padding: 20px;
            }

            .page-title h1 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .table-card {
                padding: 20px;
            }

            .agendamento-list {
                padding: 16px;
            }

            .agendamento-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .agendamento-details {
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 16px;
            }

            .page-title h1 {
                font-size: 20px;
            }

            .search-container {
                max-width: 100%;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-info p {
                font-size: 28px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 13px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-end;
                gap: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-paw"></i> Dashboard Secretaria</h1>
                </div>
                <div class="user-info">
                    <div class="search-container">
                        <form onsubmit="return handleSearch(event)" role="search">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="q" placeholder="Pesquisar animais, usuários, perfil..." aria-label="Pesquisar por animais, usuários, perfil, calendário, equipe ou configurações" required>
                        </form>
                    </div>
                    <div class="user-avatar">
                        <?php if ($foto_perfil && file_exists("../../assets/uploads/perfil/$foto_perfil")): ?>
                            <img src="../../assets/uploads/perfil/<?= htmlspecialchars($foto_perfil) ?>?t=<?= time() ?>" alt="Foto de Perfil">
                        <?php else: ?>
                            <?= htmlspecialchars($iniciais) ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <span><?= htmlspecialchars($nome_usuario) ?></span>
                        <small><?= htmlspecialchars($email_usuario) ?></small>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Total de Clientes</h3>
                            <p><?= htmlspecialchars($usuarios['total']) ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Animais Cadastrados</h3>
                            <p><?= htmlspecialchars($animais['total']) ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Agendamentos Confirmados</h3>
                            <p><?= htmlspecialchars($agendamentos['total']) ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Consultas Hoje</h3>
                            <p><?= htmlspecialchars($consultas_hoje['total']) ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <h2><i class="fas fa-chart-pie"></i> Estatísticas</h2>
                <div class="charts-grid">
                    <div class="chart-card">
                        <canvas id="agendamentosChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <canvas id="especiesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tables Section -->
            <div class="stats-grid">
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar"></i> Próximos Agendamentos</h3>
                        <a href="calendario.php" class="see-all">Ver todos</a>
                    </div>
                    <ul class="agendamento-list">
                        <?php if (empty($proximos_agendamentos)): ?>
                            <li style="padding: 40px; text-align: center; color: var(--gray-500);">
                                <i class="fas fa-calendar" style="font-size: 32px; margin-bottom: 16px; opacity: 0.3;"></i>
                                <p style="font-size: 14px;">Nenhum agendamento próximo.</p>
                            </li>
                        <?php else: ?>
                            <?php foreach ($proximos_agendamentos as $agendamento): ?>
                                <li class="agendamento-item">
                                    <div class="agendamento-info">
                                        <h4><?= htmlspecialchars($agendamento['animal']) ?></h4>
                                        <p><?= htmlspecialchars($agendamento['cliente']) ?></p>
                                        <p class="agendamento-procedimento"><?= htmlspecialchars($agendamento['procedimento'] ?? 'Não especificado') ?></p>
                                    </div>
                                    <div class="agendamento-details">
                                        <span class="agendamento-time">
                                            <?= date('d/m', strtotime($agendamento['data_hora'])) ?> •
                                            <?= substr($agendamento['hora_inicio'], 0, 5) ?>
                                        </span>
                                        <span class="agendamento-status status-<?= strtolower($agendamento['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($agendamento['status'])) ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-chart-bar"></i> Estatísticas Rápidas</h3>
                    </div>
                    <ul class="agendamento-list">
                        <li class="agendamento-item">
                            <div class="agendamento-info">
                                <h4>Agendamentos Pendentes</h4>
                            </div>
                            <div class="agendamento-time"><?= htmlspecialchars($agendamentos_pendentes['total']) ?></div>
                        </li>
                        <li class="agendamento-item">
                            <div class="agendamento-info">
                                <h4>Consultas Realizadas (Mês)</h4>
                            </div>
                            <div class="agendamento-time"><?= htmlspecialchars(count($agendamentos_mes)) ?></div>
                        </li>
                        <li class="agendamento-item">
                            <div class="agendamento-info">
                                <h4>Espécies Cadastradas</h4>
                            </div>
                            <div class="agendamento-time"><?= htmlspecialchars(count($especies_data)) ?></div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script>
        // Gráfico de Agendamentos
        const agendamentosCtx = document.getElementById('agendamentosChart').getContext('2d');
        const agendamentosData = {
            labels: [<?php
                        $labels = [];
                        foreach ($agendamentos_mes as $ag) {
                            $labels[] = "'" . date('d/m', strtotime($ag['dia'])) . "'";
                        }
                        echo implode(', ', $labels) ?: "'Nenhum dado'";
                        ?>],
            datasets: [{
                label: 'Agendamentos por Dia',
                data: [<?php
                        $values = [];
                        foreach ($agendamentos_mes as $ag) {
                            $values[] = $ag['total'];
                        }
                        echo implode(', ', $values) ?: '0';
                        ?>],
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };

        new Chart(agendamentosCtx, {
            type: 'line',
            data: agendamentosData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false,
                        labels: {
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Agendamentos dos Últimos 30 Dias',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Espécies
        const especiesCtx = document.getElementById('especiesChart').getContext('2d');
        const especiesData = {
            labels: [<?php
                        $labels = [];
                        foreach ($especies_data as $especie) {
                            $labels[] = "'" . htmlspecialchars($especie['nome']) . "'";
                        }
                        echo implode(', ', $labels) ?: "'Nenhuma espécie'";
                        ?>],
            datasets: [{
                data: [<?php
                        $values = [];
                        foreach ($especies_data as $especie) {
                            $values[] = $especie['total'];
                        }
                        echo implode(', ', $values) ?: '0';
                        ?>],
                backgroundColor: [
                    '#6366f1', '#f59e0b', '#10b981', '#8b5cf6', '#3b82f6',
                    '#1abc9c', '#d35400', '#34495e', '#16a085', '#27ae60'
                ]
            }]
        };

        new Chart(especiesCtx, {
            type: 'doughnut',
            data: especiesData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Distribuição por Espécie',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                }
            }
        });

        // Sistema de Pesquisa
        const searchForm = document.querySelector('.search-container form');
        const input = document.querySelector('.search-container input');

        function normalizeText(text) {
            return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function handleSearch(event) {
            event.preventDefault();
            const searchTerm = normalizeText(input.value.trim());

            if (searchTerm === '') {
                return false;
            }

            const redirects = {
                'animais': 'CrudAnimal.php',
                'usuarios': 'CrudUsu.php',
                'usuários': 'CrudUsu.php',
                'perfil': 'perfil.php',
                'calendario': 'calendario.php',
                'calendário': 'calendario.php',
                'equipe': 'equipe.php',
                'configuracoes': 'config.php',
                'configurações': 'config.php',
                'config': 'config.php'
            };

            if (redirects[searchTerm]) {
                window.location.href = redirects[searchTerm];
            } else {
                alert('Nenhuma página encontrada para "' + input.value + '". Tente: Animais, Usuários, Perfil, Calendário, Equipe ou Configurações.');
            }

            return false;
        }

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && input.value.trim() !== '') {
                handleSearch(e);
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>