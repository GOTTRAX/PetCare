<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

include '../conexao.php';
$pdo->query("
    UPDATE Agendamentos
    SET status = 'finalizado'
    WHERE status = 'confirmado'
      AND TIMESTAMP(data_hora, hora_final) < NOW()
");

$stmt = $pdo->query("SELECT * FROM Agendamentos ORDER BY data_hora DESC");
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$usuario_id = $_SESSION['id'];
$tipo_usuario = $_SESSION['tipo_usuario'];
$eh_cliente = ($tipo_usuario === 'Cliente');
$eh_admin = ($tipo_usuario === 'Veterinario' || $tipo_usuario === 'Secretaria');

if (!in_array($tipo_usuario, ['Cliente', 'Veterinario', 'Secretaria'])) {
    echo "Acesso negado.";
    exit;
}

// Constantes
define('UPLOAD_URL', '/Bruno/PetCare/assets/uploads/pets/');

// Buscar dados do cliente
if ($eh_cliente) {
    $stmt = $pdo->prepare("SELECT id, nome, foto FROM Animais WHERE usuario_id = ? ORDER BY nome");
    $stmt->execute([$usuario_id]);
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $animais = [];
}

// Buscar serviços
$servicos = $pdo->query("SELECT id, nome, preco_normal, preco_feriado, duracao FROM Servicos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Mapa de ícones
$icones_por_servico = [
    'banho' => 'fa-shower',
    'tosa' => 'fa-scissors',
    'consulta' => 'fa-stethoscope',
    'vacinação' => 'fa-syringe',
    'limpeza' => 'fa-broom',
    'corte' => 'fa-cut',
    'ouvido' => 'fa-ear',
    'dental' => 'fa-tooth'
];

// Incluir header apropriado
if ($eh_admin && $tipo_usuario === 'Secretaria') {
    // Include header específico da secretária
    if (file_exists('../../Secretaria/header.php')) {
        include '../../Secretaria/header.php';
    } else {
        include 'header.php';
    }
} else {
    include '../header.php';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - PetAgenda</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
            --border-radius: 10px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            overflow-x: hidden;
            padding-top: 80px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 16px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title p {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: 8px;
        }

        .calendar-layout {
            display: grid;
            grid-template-columns: 1.3fr 400px;
            gap: 16px;
            max-height: calc(100vh - 180px);
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .card-header {
            padding: 10px 16px;
            background: var(--gray-50);
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--gray-700);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-body {
            padding: 12px;
            flex: 1;
            overflow: auto;
            display: flex;
            flex-direction: column;
        }

        .card.calendar-card .card-body {
            overflow: hidden;
            padding: 8px;
        }

        #calendar {
            flex: 1;
            font-size: 0.75rem;
        }

        .fc {
            font-family: 'Montserrat', sans-serif;
        }

        .fc .fc-toolbar {
            padding: 6px 8px;
            background: var(--white);
            border-radius: 6px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 6px;
        }

        .fc .fc-toolbar-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .fc .fc-button {
            background: var(--primary) !important;
            border: none !important;
            color: var(--white) !important;
            border-radius: 5px !important;
            padding: 4px 10px !important;
            font-weight: 600 !important;
            font-size: 0.7rem !important;
        }

        .fc .fc-button:hover {
            background: var(--primary-dark) !important;
        }

        .fc .fc-daygrid-day-frame {
            min-height: 85px !important;
        }

        .fc .fc-col-header-cell {
            padding: 8px 2px !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
        }

        .fc .fc-daygrid-day-number {
            padding: 4px 6px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
        }

        /* ========== EVENTOS DO CALENDÁRIO (VISUAL MELHORADO) ========== */
        /* ========== EVENTOS DO CALENDÁRIO (VISUAL MELHORADO) ========== */
        .fc-event {
            border: none !important;
            border-radius: 6px !important;
            font-size: 0.72rem !important;
            padding: 6px 10px !important;
            cursor: pointer !important;
            margin-bottom: 3px !important;
            color: var(--white) !important;
            font-weight: 700 !important;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25) !important;
            transition: all 0.2s ease !important;
            overflow: visible !important;
            white-space: normal !important;
            opacity: 1 !important;
            line-height: 1.4 !important;
        }

        .fc-event:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.35) !important;
            transform: translateY(-2px) scale(1.03) !important;
            z-index: 999 !important;
        }

        /* Forçar exibição do conteúdo */
        .fc-event-main {
            display: block !important;
            padding: 0 !important;
        }

        .fc-event-main-frame {
            display: block !important;
        }

        .fc-event-title-container {
            display: block !important;
        }

        .fc-event-title {
            font-weight: 700 !important;
            display: block !important;
            font-size: 0.72rem !important;
            line-height: 1.3 !important;
        }

        .fc-event-time {
            font-weight: 600 !important;
            opacity: 0.95 !important;
            display: block !important;
            font-size: 0.7rem !important;
            margin-bottom: 2px !important;
        }

        /* ========== CORES SÓLIDAS POR STATUS ========== */
        .fc-event.confirmado {
            background: #10b981 !important;
            border: 2px solid #059669 !important;
            color: #ffffff !important;
        }

        .fc-event.confirmado:hover {
            background: #059669 !important;
        }

        .fc-event.confirmado .fc-event-title::before {
            content: "✓ ";
            font-weight: 900;
            font-size: 0.9rem;
            margin-right: 4px;
            color: #ffffff;
        }

        .fc-event.cancelado {
            background: #ef4444 !important;
            border: 2px solid #dc2626 !important;
            color: #ffffff !important;
            text-decoration: line-through !important;
        }

        .fc-event.cancelado:hover {
            background: #dc2626 !important;
        }

        .fc-event.cancelado .fc-event-title::before {
            content: "✕ ";
            font-weight: 900;
            font-size: 0.9rem;
            margin-right: 4px;
            color: #ffffff;
        }

        .fc-event.pendente {
            background: #f59e0b !important;
            border: 2px solid #d97706 !important;
            color: #1e293b !important;
        }

        .fc-event.pendente:hover {
            background: #d97706 !important;
        }

        .fc-event.pendente .fc-event-title::before {
            content: "⏳ ";
            font-size: 0.85rem;
            margin-right: 4px;
        }

        .fc-event.pendente .fc-event-time,
        .fc-event.pendente .fc-event-title {
            color: #1e293b !important;
        }

        .fc-event.finalizado {
            background: #64748b !important;
            border: 2px solid #475569 !important;
            color: #ffffff !important;
        }

        .fc-event.finalizado:hover {
            background: #475569 !important;
        }

        .fc-event.finalizado .fc-event-title::before {
            content: "✔ ";
            font-weight: 900;
            font-size: 0.9rem;
            margin-right: 4px;
            color: #ffffff;
        }

        /* Dias passados (desabilitados) */
        .fc-day-past {
            background-color: rgba(0, 0, 0, 0.03) !important;
            opacity: 0.6;
        }

        .fc-day-past .fc-daygrid-day-number {
            color: var(--gray-400) !important;
        }

        .fc-daygrid-day:hover {
            background-color: var(--gray-50) !important;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-control,
        select,
        textarea,
        input[type="date"],
        input[type="time"] {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid var(--gray-200);
            border-radius: 6px;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .animal-card {
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .animal-card:hover {
            border-color: var(--primary-light);
            background: var(--gray-100);
        }

        .animal-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .animal-image {
            width: 60px;
            height: 60px;
            margin: 0 auto 8px;
            background: var(--gray-300);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border: 2px solid var(--gray-400);
        }

        .animal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .animal-image i {
            font-size: 24px;
            color: var(--gray-500);
        }

        .animal-name {
            font-weight: 600;
            font-size: 0.8rem;
        }

        .custom-select-wrapper {
            position: relative;
        }

        .custom-select-wrapper select {
            padding-left: 36px;
            appearance: none;
            background: var(--white) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%23475569" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
        }

        .select-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            color: var(--primary);
            pointer-events: none;
        }

        .preco-info {
            display: none;
            background: #ffffff;
            padding: 16px 20px;
            border-radius: 10px;
            border-left: 4px solid #6366f1;
            gap: 10px;
            margin-top: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .preco-info.show {
            display: flex;
            flex-direction: column;
        }

        .preco-info.feriado {
            background: #fffbf5;
            border-left-color: #f59e0b;
        }

        .preco-valor {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: #1e3a8a;
            font-size: 1.75rem;
            font-family: 'Montserrat', sans-serif;
        }

        .preco-info.feriado .preco-valor {
            color: #b45309;
        }

        .preco-valor i {
            font-size: 1.4rem;
            color: #6366f1;
        }

        .preco-info.feriado .preco-valor i {
            color: #f59e0b;
        }

        .preco-tipo {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.813rem;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preco-tipo::before {
            content: '▸';
            color: #6366f1;
            font-size: 0.875rem;
            font-weight: 900;
        }

        .preco-info.feriado .preco-tipo {
            color: #92400e;
        }

        .preco-info.feriado .preco-tipo::before {
            color: #f59e0b;
        }

        .duracao-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 8px;
            padding: 8px;
            background: #f1f5f9;
            border-radius: 6px;
        }

        .duracao-info i {
            color: var(--primary);
            font-size: 1rem;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .tabs-admin {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            border-bottom: 2px solid var(--gray-200);
        }

        .tab-btn {
            padding: 8px 16px;
            border: none;
            background: none;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        #notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 18px;
            border-radius: 8px;
            color: var(--white);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s;
            transform: translateY(-20px);
        }

        #notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        #notification.success {
            background: var(--success);
        }

        #notification.error {
            background: var(--danger);
        }

        .solicitacao {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .solicitacao:hover {
            border-color: var(--primary);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .solicitacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .solicitacao-header strong {
            font-size: 0.8rem;
            color: var(--gray-900);
        }

        .solicitacao-info {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 4px;
        }

        .solicitacao-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .solicitacao-actions button {
            flex: 1;
            padding: 6px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .solicitacao-actions .aceitar {
            background: #d1fae5;
            color: var(--success);
        }

        .solicitacao-actions .recusar {
            background: #fee2e2;
            color: var(--danger);
        }

        #contador-solicitacoes {
            background: var(--danger);
            color: var(--white);
            padding: 2px 5px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 5px;
            display: none;
        }

        #contador-solicitacoes.show {
            display: inline;
        }

        /* Modal para motivo cancelamento */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-xl);
        }

        .modal-box h3 {
            margin-bottom: 12px;
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-box textarea {
            width: 100%;
            margin: 12px 0;
        }

        .modal-buttons {
            display: flex;
            gap: 8px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-buttons .confirmar {
            background: var(--danger);
            color: var(--white);
        }

        .modal-buttons .cancelar {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        @media (max-width: 992px) {
            .calendar-layout {
                grid-template-columns: 1fr;
            }

            #calendar {
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }

            .container {
                padding: 10px;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div id="notification"></div>

        <!-- Modal Cancelamento -->
        <div id="modalCancelamento" class="modal-overlay">
            <div class="modal-box">
                <h3><i class="fas fa-exclamation-triangle"></i> Motivo do Cancelamento</h3>
                <p style="font-size: 0.85rem; color: #666; margin-bottom: 12px;">Por favor, explique por que deseja
                    cancelar este agendamento:</p>
                <textarea id="motivo-input" class="form-control" placeholder="Descreva o motivo do cancelamento..."
                    required></textarea>
                <div class="modal-buttons">
                    <button type="button" class="confirmar" onclick="confirmarCancelamento()">Confirmar</button>
                    <button type="button" class="cancelar" onclick="fecharModalCancelamento()">Voltar</button>
                </div>
            </div>
        </div>

        <div class="page-title">
            <h1>
                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                Calendário de Agendamentos
            </h1>
            <p>Gerencie seus agendamentos e consulte a disponibilidade da clínica</p>
        </div>

        <div class="calendar-layout">
            <!-- Calendário -->
            <div class="card calendar-card">
                <div class="card-header">
                    <i class="far fa-calendar-alt"></i> Agenda
                </div>
                <div class="card-body">
                    <div id='calendar'></div>
                </div>
            </div>

            <!-- Formulário Lateral / Admin Panel -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-plus"></i>
                    <span id="form-titulo">Novo Agendamento</span>
                </div>
                <div class="card-body" style="overflow-y: auto;">
                    <!-- Tabs para Admin -->
                    <?php if ($eh_admin): ?>
                        <div class="tabs-admin">
                            <button class="tab-btn active" onclick="mudarTab('novo')">
                                <i class="fas fa-plus"></i> Novo
                            </button>
                            <button class="tab-btn" onclick="mudarTab('solicitacoes')">
                                <i class="fas fa-tasks"></i> Solicitações <span id="contador-solicitacoes"></span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- TAB: Novo Agendamento -->
                    <div id="tab-novo" class="tab-content active">
                        <form id="formAgendamento">
                            <input type="hidden" id="modo" value="novo">
                            <input type="hidden" id="agendamento_id">

                            <!-- CLIENTE: Animals -->
                            <?php if ($eh_cliente): ?>
                                <div class="form-group">
                                    <label><i class="fas fa-paw"></i> Escolha o Animal:</label>
                                    <div class="animals-grid">
                                        <?php foreach ($animais as $animal): ?>
                                            <div class="animal-card" data-id="<?= $animal['id'] ?>">
                                                <div class="animal-image">
                                                    <?php if (!empty($animal['foto'])): ?>
                                                        <img src="<?= UPLOAD_URL . htmlspecialchars($animal['foto']) ?>"
                                                            alt="<?= htmlspecialchars($animal['nome']) ?>"
                                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <i class="fas fa-paw" style="display:none;"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-paw"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="animal-name"><?= htmlspecialchars($animal['nome']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="animal_id" id="animal_id" required>
                                </div>
                            <?php else: ?>
                                <!-- ADMIN: Cliente + Animal -->
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Cliente:</label>
                                    <div class="custom-select-wrapper">
                                        <i class="fas fa-user select-icon"></i>
                                        <select name="cliente_id" id="cliente_id" class="form-control" required>
                                            <option value="">Selecione um cliente</option>
                                            <?php
                                            $clientes = $pdo->query("SELECT id, nome FROM Usuarios WHERE tipo_usuario = 'Cliente' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($clientes as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-paw"></i> Animal:</label>
                                    <div class="custom-select-wrapper">
                                        <i class="fas fa-paw select-icon"></i>
                                        <select name="animal_id" id="animal_id" class="form-control" required>
                                            <option value="">Selecione um cliente primeiro</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Serviço -->
                            <div class="form-group">
                                <label><i class="fas fa-concierge-bell"></i> Serviço:</label>
                                <div class="custom-select-wrapper">
                                    <i class="fas fa-concierge-bell select-icon"></i>
                                    <select name="servico_id" id="servico_id" class="form-control" required>
                                        <option value="">Selecione um serviço</option>
                                        <?php foreach ($servicos as $s):
                                            $servico_nome = strtolower($s['nome']);
                                            $icone = 'fa-concierge-bell';
                                            foreach ($icones_por_servico as $key => $icon) {
                                                if (stripos($servico_nome, $key) !== false) {
                                                    $icone = $icon;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <option value="<?= $s['id'] ?>" data-preco-normal="<?= $s['preco_normal'] ?>"
                                                data-preco-feriado="<?= $s['preco_feriado'] ?>"
                                                data-duracao="<?= $s['duracao'] ?>"
                                                data-icone="<?= htmlspecialchars($icone) ?>">
                                                <?= htmlspecialchars($s['nome']) ?> - R$
                                                <?= number_format($s['preco_normal'], 2, ',', '.') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Info de Preço + Duração -->
                                <div id="preco-info" class="preco-info">
                                    <div class="preco-valor" id="preco-text"></div>
                                    <div class="preco-tipo" id="tipo-preco"></div>
                                    <div class="duracao-info" id="duracao-info"></div>
                                </div>
                            </div>

                            <!-- Data -->
                            <div class="form-group">
                                <label><i class="far fa-calendar"></i> Data:</label>
                                <input type="date" name="data" id="data" class="form-control" required>
                            </div>

                            <!-- Horário -->
                            <div class="form-group">
                                <label><i class="far fa-clock"></i> Horário:</label>
                                <select name="hora_inicio" id="horarios" class="form-control" required disabled>
                                    <option value="">Selecione uma data</option>
                                </select>
                                <input type="hidden" name="hora_final" id="hora_final" required>
                            </div>

                            <!-- Status (Admin) -->
                            <?php if ($eh_admin): ?>
                                <div class="form-group">
                                    <label><i class="fas fa-info-circle"></i> Status:</label>
                                    <div class="custom-select-wrapper">
                                        <i class="fas fa-info-circle select-icon"></i>
                                        <select name="status" id="status" class="form-control">
                                            <option value="pendente">Pendente</option>
                                            <option value="confirmado">Confirmado</option>
                                            <option value="cancelado">Cancelado</option>
                                            <option value="finalizado">Finalizado</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Observações -->
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Observações:</label>
                                <textarea name="observacoes" id="observacoes" class="form-control"
                                    placeholder="Digite suas observações..."></textarea>
                            </div>

                            <!-- Botões Novo -->
                            <div class="btn-group" id="botoes-novo">
                                <button type="submit" class="btn btn-primary" id="btn-submit" disabled>
                                    <i class="fas fa-calendar-check"></i> Agendar
                                </button>
                            </div>

                            <!-- Botões Edição -->
                            <div class="btn-group" id="botoes-edicao" style="display: none; flex-direction: column;">
                                <button type="submit" class="btn btn-success" id="btn-salvar">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>
                                <button type="button" class="btn btn-danger" id="btn-cancelar">
                                    <i class="fas fa-ban"></i> Cancelar Agendamento
                                </button>
                                <?php if ($eh_admin): ?>
                                    <button type="button" class="btn btn-danger" id="btn-excluir">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" id="btn-voltar">
                                    <i class="fas fa-arrow-left"></i> Voltar
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB: Solicitações Pendentes (Admin) -->
                    <?php if ($eh_admin): ?>
                        <div id="tab-solicitacoes" class="tab-content">
                            <div id="lista-solicitacoes" style="max-height: 600px; overflow-y: auto;"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CONFIG = {
            tipoUsuario: '<?= $tipo_usuario ?>',
            usuarioId: <?= $usuario_id ?>,
            ehCliente: <?= json_encode($eh_cliente) ?>,
            ehAdmin: <?= json_encode($eh_admin) ?>,
            basePath: '/Bruno/PetCare/PHP/Calendario/'
        };

        let formulario = {
            modo: 'novo',
            agendamentoId: null,
            diasIndisponiveis: {},
            motivoCancelamentoTemp: null
        };

        function mostrarNotificacao(mensagem, tipo = 'success') {
            const notif = document.getElementById('notification');
            const iconClass = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            notif.innerHTML = `<i class="fas ${iconClass}"></i> ${mensagem}`;
            notif.className = `show ${tipo}`;
            setTimeout(() => notif.classList.remove('show'), 3000);
        }

        function mudarTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

            event.target.closest('.tab-btn').classList.add('active');
            document.getElementById(`tab-${tab}`).classList.add('active');

            if (tab === 'solicitacoes') {
                carregarSolicitacoes();
            }
        }

        function abrirModalCancelamento(agendamentoId) {
            formulario.motivoCancelamentoTemp = agendamentoId;
            document.getElementById('modalCancelamento').classList.add('active');
            document.getElementById('motivo-input').focus();
        }

        function fecharModalCancelamento() {
            document.getElementById('modalCancelamento').classList.remove('active');
            document.getElementById('motivo-input').value = '';
            formulario.motivoCancelamentoTemp = null;
        }

        async function confirmarCancelamento() {
            const motivo = document.getElementById('motivo-input').value.trim();
            if (!motivo) {
                mostrarNotificacao('Por favor, descreva o motivo do cancelamento', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('id', formulario.motivoCancelamentoTemp);
            formData.append('status', 'cancelado');
            formData.append('motivo_cancelamento', motivo);

            try {
                const res = await fetch(CONFIG.basePath + 'atualizar_status.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.status === 'ok' || result.success) {
                    mostrarNotificacao('Agendamento cancelado com sucesso', 'success');
                    fecharModalCancelamento();
                    formulario.modo = 'novo';
                    formulario.agendamentoId = null;
                    document.getElementById('formAgendamento').reset();
                    document.getElementById('form-titulo').textContent = 'Novo Agendamento';
                    document.getElementById('botoes-novo').style.display = 'flex';
                    document.getElementById('botoes-edicao').style.display = 'none';
                    calendar.refetchEvents();
                } else {
                    mostrarNotificacao('Erro ao cancelar', 'error');
                }
            } catch (error) {
                mostrarNotificacao('Erro de conexão', 'error');
            }
        }

        function formatarDataLocal(date) {
            const ano = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${ano}-${mes}-${dia}`;
        }

        async function carregarDiasIndisponiveis() {
            try {
                const res = await fetch(CONFIG.basePath + 'get_dias_indisponiveis.php');
                if (!res.ok) throw new Error();
                formulario.diasIndisponiveis = await res.json();
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        function verificarDisponibilidade(dateStr) {
            if (!formulario.diasIndisponiveis.feriados) return null;
            const data = new Date(dateStr + 'T00:00:00');
            const nomesDias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            const nomeDia = nomesDias[data.getDay()];
            const feriado = formulario.diasIndisponiveis.feriados?.find(f => f.data === dateStr);
            const periodo = formulario.diasIndisponiveis.periodos?.find(p => dateStr >= p.data_inicio && dateStr <= p.data_fim);
            const diaNaoAtivo = formulario.diasIndisponiveis.dias_nao_ativos?.includes(nomeDia);

            if (diaNaoAtivo && !feriado) return { tipo: 'fechado', nome: 'Clínica fechada' };
            if (feriado) return { tipo: 'feriado', nome: feriado.nome };
            if (periodo) return { tipo: 'periodo', nome: periodo.motivo };
            return null;
        }

        function formatarMinutos(minutos) {
            if (minutos < 60) {
                return `${minutos} min`;
            } else {
                const horas = Math.floor(minutos / 60);
                const mins = minutos % 60;
                return mins > 0 ? `${horas}h ${mins}min` : `${horas}h`;
            }
        }

        function atualizarInfoPreco() {
            const dataInput = document.getElementById('data');
            const servicoSelect = document.getElementById('servico_id');
            const precoInfo = document.getElementById('preco-info');
            const precoTexto = document.getElementById('preco-text');
            const tipoPreco = document.getElementById('tipo-preco');
            const duracaoInfo = document.getElementById('duracao-info');

            if (!dataInput.value || !servicoSelect.value) {
                precoInfo.classList.remove('show');
                return;
            }

            const opcaoServico = servicoSelect.options[servicoSelect.selectedIndex];
            const precoNormal = parseFloat(opcaoServico.dataset.precoNormal);
            const precoFeriado = parseFloat(opcaoServico.dataset.precoFeriado);
            const duracao = parseInt(opcaoServico.dataset.duracao);
            const iconSelect = opcaoServico.dataset.icone;

            const disponibilidade = verificarDisponibilidade(dataInput.value);
            const ehFeriado = disponibilidade && disponibilidade.tipo === 'feriado';
            const preco = ehFeriado ? precoFeriado : precoNormal;

            precoTexto.innerHTML = `<i class="fas ${iconSelect}"></i> R$ ${preco.toFixed(2).replace('.', ',')}`;
            tipoPreco.textContent = ehFeriado ? 'Preço de Feriado' : 'Preço Normal';
            duracaoInfo.innerHTML = `<i class="fas fa-hourglass-half"></i> Duração: ${formatarMinutos(duracao)}`;

            precoInfo.classList.toggle('feriado', ehFeriado);
            precoInfo.classList.add('show');
        }

        async function carregarHorarios() {
            const dataInput = document.getElementById('data');
            const servicoSelect = document.getElementById('servico_id');
            const horariosSelect = document.getElementById('horarios');

            if (!dataInput.value || !servicoSelect.value) {
                horariosSelect.disabled = true;
                horariosSelect.innerHTML = '<option value="">Selecione uma data e serviço</option>';
                document.getElementById('btn-submit').disabled = true;
                return;
            }

            // Verificar se é data passada
            const dataSelecionada = new Date(dataInput.value + 'T00:00:00');
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            if (dataSelecionada < hoje) {
                horariosSelect.disabled = true;
                horariosSelect.innerHTML = '<option value="">Data passada - não disponível</option>';
                document.getElementById('btn-submit').disabled = true;
                mostrarNotificacao('Não é possível agendar em datas passadas!', 'error');
                return;
            }

            const disponibilidade = verificarDisponibilidade(dataInput.value);
            if (disponibilidade && disponibilidade.tipo !== 'feriado') {
                horariosSelect.disabled = true;
                horariosSelect.innerHTML = `<option value="">${disponibilidade.nome}</option>`;
                document.getElementById('btn-submit').disabled = true;
                return;
            }

            try {
                const res = await fetch(`${CONFIG.basePath}get_horarios_disponiveis.php?servico_id=${servicoSelect.value}&data=${dataInput.value}`);
                const data = await res.json();

                horariosSelect.innerHTML = '<option value="">Selecione um horário</option>';

                if (data.length === 0) {
                    horariosSelect.innerHTML = '<option value="">Sem horários disponíveis</option>';
                    horariosSelect.disabled = true;
                    document.getElementById('btn-submit').disabled = true;
                } else {
                    // Se for hoje, filtrar horários passados
                    const agora = new Date();
                    const ehHoje = dataSelecionada.toDateString() === agora.toDateString();

                    const horariosValidos = data.filter(h => {
                        if (!ehHoje) return true; // Se não é hoje, todos os horários são válidos

                        const [hora, minuto] = h.inicio.split(':').map(Number);
                        const horarioData = new Date();
                        horarioData.setHours(hora, minuto, 0, 0);

                        return horarioData > agora; // Só mostra horários futuros
                    });

                    if (horariosValidos.length === 0) {
                        horariosSelect.innerHTML = '<option value="">Sem horários disponíveis para hoje</option>';
                        horariosSelect.disabled = true;
                        document.getElementById('btn-submit').disabled = true;
                    } else {
                        horariosValidos.forEach(h => {
                            const opt = document.createElement('option');
                            opt.value = h.inicio;
                            opt.textContent = `${h.inicio.substring(0, 5)} - ${h.final.substring(0, 5)}`;
                            opt.dataset.final = h.final;
                            horariosSelect.appendChild(opt);
                        });
                        horariosSelect.disabled = false;
                    }
                }
            } catch (error) {
                mostrarNotificacao('Erro ao carregar horários', 'error');
            }
        }
        let calendar;
        document.addEventListener('DOMContentLoaded', async () => {
            await carregarDiasIndisponiveis();

            const calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                selectable: true,
                editable: true,
                eventDrop: async function (info) {
                    const event = info.event;
                    const novaData = event.startStr.split('T')[0];
                    const novaHora = event.startStr.split('T')[1]?.slice(0, 8) || event.extendedProps.hora_inicio;

                    // Verificar se está movendo para dia/hora passados
                    const agora = new Date();
                    const dataEvento = new Date(event.start);

                    if (dataEvento < agora) {
                        mostrarNotificacao('Não é possível mover agendamento para data/hora passada!', 'error');
                        info.revert();
                        return;
                    }

                    // Verificar se o agendamento original é de data passada
                    const dataOriginal = new Date(info.oldEvent.start);
                    if (dataOriginal < agora) {
                        mostrarNotificacao('Não é possível mover agendamentos de datas passadas!', 'error');
                        info.revert();
                        return;
                    }

                    // Confirmar mudança e avisar sobre status
                    const confirmacao = confirm(
                        '⚠️ ATENÇÃO!\n\n' +
                        'Ao mover este agendamento, o status será alterado para PENDENTE.\n\n' +
                        'Deseja continuar?'
                    );

                    if (!confirmacao) {
                        info.revert();
                        return;
                    }

                    try {
                        const response = await fetch(CONFIG.basePath + 'editar_agendamento.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                id: event.id,
                                data: novaData,
                                hora_inicio: novaHora,
                                status: 'pendente' // FORÇA STATUS PENDENTE
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            mostrarNotificacao('Agendamento movido! Status: PENDENTE', 'success');
                            calendar.refetchEvents();
                        } else {
                            mostrarNotificacao('Erro ao mover: ' + (result.error || 'Desconhecido'), 'error');
                            info.revert();
                        }
                    } catch (error) {
                        mostrarNotificacao('Erro de conexão', 'error');
                        info.revert();
                    }
                },
                events: async (info, successCallback) => {
                    try {
                        const res = await fetch(CONFIG.basePath + 'get_agendamentos.php');
                        const data = await res.json();
                        successCallback(data);
                    } catch (error) {
                        mostrarNotificacao('Erro ao carregar agendamentos', 'error');
                    }
                },
                eventDidMount: function (info) {
                    // Adicionar classe do status ao evento
                    const status = info.event.extendedProps.status;
                    if (status) {
                        info.el.classList.add(status.toLowerCase());
                    }
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                dayMaxEvents: 4,
                dayCellDidMount: (info) => {
                    const dataStr = formatarDataLocal(info.date);
                    const disponibilidade = verificarDisponibilidade(dataStr);

                    // Marcar dias passados
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);

                    if (info.date < hoje) {
                        info.el.classList.add('fc-day-past');
                    }

                    if (disponibilidade) {
                        info.el.style.backgroundColor = disponibilidade.tipo === 'feriado' ? 'rgba(251, 191, 36, 0.1)' : 'rgba(239, 68, 68, 0.1)';
                        info.el.title = disponibilidade.nome;
                    }
                },
                eventClick: (info) => {
                    const event = info.event;
                    const props = event.extendedProps;

                    formulario.modo = 'editar';
                    formulario.agendamentoId = event.id;

                    document.getElementById('agendamento_id').value = event.id;
                    document.getElementById('data').value = event.startStr.split('T')[0];
                    document.getElementById('horarios').value = event.startStr.split('T')[1]?.slice(0, 5);
                    document.getElementById('observacoes').value = props.observacoes || '';

                    if (CONFIG.ehAdmin) {
                        document.getElementById('status').value = props.status || 'pendente';
                    }

                    if (CONFIG.ehCliente) {
                        document.querySelector(`[data-id="${props.animal_id}"]`)?.classList.add('selected');
                        document.getElementById('animal_id').value = props.animal_id;
                    }

                    document.getElementById('form-titulo').textContent = 'Editar Agendamento';
                    document.getElementById('botoes-novo').style.display = 'none';
                    document.getElementById('botoes-edicao').style.display = 'flex';

                    document.querySelector('.card:nth-child(2)').scrollIntoView({ behavior: 'smooth' });
                },
                dateClick: (info) => {
                    const dataStr = formatarDataLocal(info.date);

                    // Bloquear clique em dias passados
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);

                    if (info.date < hoje) {
                        mostrarNotificacao('Não é possível agendar em datas passadas!', 'error');
                        return;
                    }

                    const disponibilidade = verificarDisponibilidade(dataStr);

                    if (disponibilidade && disponibilidade.tipo !== 'feriado') {
                        mostrarNotificacao(disponibilidade.nome, 'error');
                        return;
                    }

                    formulario.modo = 'novo';
                    formulario.agendamentoId = null;
                    document.getElementById('formAgendamento').reset();
                    document.getElementById('data').value = dataStr;

                    document.getElementById('form-titulo').textContent = 'Novo Agendamento';
                    document.getElementById('botoes-novo').style.display = 'flex';
                    document.getElementById('botoes-edicao').style.display = 'none';
                    document.getElementById('btn-submit').disabled = true;

                    document.querySelector('.card:nth-child(2)').scrollIntoView({ behavior: 'smooth' });
                    carregarHorarios();
                }
            });

            calendar.render();

            // Animal selection (Cliente)
            if (CONFIG.ehCliente) {
                document.querySelectorAll('.animal-card').forEach(card => {
                    card.addEventListener('click', () => {
                        document.querySelectorAll('.animal-card').forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        document.getElementById('animal_id').value = card.dataset.id;
                        carregarHorarios();
                    });
                });
            } else {
                // Admin: Load animals by client
                document.getElementById('cliente_id').addEventListener('change', async (e) => {
                    const animalSelect = document.getElementById('animal_id');
                    animalSelect.innerHTML = '<option value="">Carregando...</option>';
                    try {
                        const res = await fetch(`${CONFIG.basePath}get_animais.php?cliente_id=${e.target.value}`);
                        const data = await res.json();
                        animalSelect.innerHTML = '<option value="">Selecione</option>';
                        data.forEach(animal => {
                            const opt = document.createElement('option');
                            opt.value = animal.id;
                            opt.textContent = animal.nome;
                            animalSelect.appendChild(opt);
                        });
                    } catch (error) {
                        mostrarNotificacao('Erro ao carregar animais', 'error');
                    }
                });
            }

            document.getElementById('servico_id').addEventListener('change', () => {
                atualizarInfoPreco();
                carregarHorarios();
            });

            document.getElementById('data').addEventListener('change', () => {
                atualizarInfoPreco();
                carregarHorarios();
            });

            document.getElementById('horarios').addEventListener('change', (e) => {
                const opt = e.target.options[e.target.selectedIndex];
                if (opt.dataset.final) {
                    document.getElementById('hora_final').value = opt.dataset.final;
                    document.getElementById('btn-submit').disabled = false;
                }
            });

            // Form submission
            document.getElementById('formAgendamento').addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(e.target);
                formData.append('id', formulario.agendamentoId);

                if (CONFIG.ehCliente && formulario.modo === 'editar') {
                    formData.set('status', 'pendente');
                }

                try {
                    const url = formulario.modo === 'novo' ? 'salvar_agendamento.php' : 'editar_agendamento.php';
                    const res = await fetch(CONFIG.basePath + url, { method: 'POST', body: formData });
                    const result = await res.json();

                    if (result.success) {
                        mostrarNotificacao(formulario.modo === 'novo' ? 'Agendamento criado!' : 'Agendamento atualizado!', 'success');
                        formulario.modo = 'novo';
                        formulario.agendamentoId = null;
                        document.getElementById('formAgendamento').reset();
                        document.getElementById('form-titulo').textContent = 'Novo Agendamento';
                        document.getElementById('botoes-novo').style.display = 'flex';
                        document.getElementById('botoes-edicao').style.display = 'none';
                        calendar.refetchEvents();
                        if (CONFIG.ehAdmin) carregarSolicitacoes();
                    } else {
                        mostrarNotificacao('Erro: ' + result.message, 'error');
                    }
                } catch (error) {
                    mostrarNotificacao('Erro de conexão', 'error');
                }
            });

            // Cancel appointment
            document.getElementById('btn-cancelar').addEventListener('click', () => {
                if (!formulario.agendamentoId) return;
                abrirModalCancelamento(formulario.agendamentoId);
            });

            // Delete (Admin only)
            if (CONFIG.ehAdmin) {
                document.getElementById('btn-excluir').addEventListener('click', async () => {
                    if (!confirm('EXCLUIR este agendamento? Esta ação não pode ser desfeita.')) return;

                    try {
                        const res = await fetch(CONFIG.basePath + 'excluir_agendamento.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${formulario.agendamentoId}`
                        });

                        const result = await res.json();
                        if (result.success) {
                            mostrarNotificacao('Agendamento excluído', 'success');
                            formulario.modo = 'novo';
                            formulario.agendamentoId = null;
                            document.getElementById('formAgendamento').reset();
                            document.getElementById('form-titulo').textContent = 'Novo Agendamento';
                            document.getElementById('botoes-novo').style.display = 'flex';
                            document.getElementById('botoes-edicao').style.display = 'none';
                            calendar.refetchEvents();
                        } else {
                            mostrarNotificacao('Erro ao excluir', 'error');
                        }
                    } catch (error) {
                        mostrarNotificacao('Erro de conexão', 'error');
                    }
                });
            }

            // Return to new form
            document.getElementById('btn-voltar').addEventListener('click', () => {
                formulario.modo = 'novo';
                formulario.agendamentoId = null;
                document.getElementById('formAgendamento').reset();
                document.getElementById('form-titulo').textContent = 'Novo Agendamento';
                document.getElementById('botoes-novo').style.display = 'flex';
                document.getElementById('botoes-edicao').style.display = 'none';
            });

            if (CONFIG.ehAdmin) {
                setInterval(carregarSolicitacoes, 30000);
            }
        });

        async function carregarSolicitacoes() {
            if (!CONFIG.ehAdmin) return;

            try {
                const res = await fetch(CONFIG.basePath + 'get_solicitacoes.php');
                const data = await res.json();

                const container = document.getElementById('lista-solicitacoes');
                const contador = document.getElementById('contador-solicitacoes');

                if (data.length === 0) {
                    container.innerHTML = '<p style="font-size: 0.85rem; color: #999; text-align: center; padding: 20px;">Sem solicitações pendentes</p>';
                    contador.classList.remove('show');
                } else {
                    contador.textContent = data.length;
                    contador.classList.add('show');

                    container.innerHTML = '';
                    data.forEach(s => {
                        const div = document.createElement('div');
                        div.classList.add('solicitacao');
                        div.innerHTML = `
                        <div class="solicitacao-header">
                            <strong>${s.animal_nome}</strong>
                        </div>
                        <div class="solicitacao-info"><i class="fas fa-user"></i> ${s.cliente_nome}</div>
                        <div class="solicitacao-info"><i class="fas fa-concierge-bell"></i> ${s.servico_nome}</div>
                        <div class="solicitacao-info"><i class="far fa-calendar"></i> ${s.data} às ${s.hora_inicio}</div>
                        <div class="solicitacao-actions">
                            <button class="aceitar" onclick="confirmarSolicitacao(${s.id}, 'confirmado'); event.stopPropagation();"><i class="fas fa-check"></i> Confirmar</button>
                            <button class="recusar" onclick="confirmarSolicitacao(${s.id}, 'cancelado'); event.stopPropagation();"><i class="fas fa-times"></i> Recusar</button>
                        </div>
                    `;
                        container.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }

        async function confirmarSolicitacao(id, status) {
            try {
                const res = await fetch(CONFIG.basePath + 'atualizar_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}&status=${status}`
                });

                const result = await res.json();
                if (result.status === 'ok' || result.success) {
                    mostrarNotificacao(`Agendamento ${status === 'confirmado' ? 'confirmado' : 'recusado'}!`, 'success');
                    carregarSolicitacoes();
                    calendar.refetchEvents();
                } else {
                    mostrarNotificacao('Erro ao atualizar', 'error');
                }
            } catch (error) {
                mostrarNotificacao('Erro de conexão', 'error');
            }
        }
    </script>
</body>

</html>