<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

require '../conexao.php';

$usuario_id = $_SESSION['id'];
$tipo = $_SESSION['tipo_usuario'];

if ($tipo !== 'Veterinario' && $tipo !== 'Secretaria') {
    echo "Acesso negado.";
    exit;
}
include 'header.php';

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - PetAgenda</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
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
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
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
        font-family: 'Inter', sans-serif;
        background-color: var(--gray-50);
        color: var(--gray-900);
        min-height: 100vh;
        overflow-x: hidden;
    }

    .main-content {
        margin-left: 90px;
        padding: 16px;
        min-height: 100vh;
        max-width: 100%;
    }

    .container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0;
    }

    /* Page Header - Compacto */
    .page-header {
        margin-bottom: 16px;
    }

    .page-title h1 {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Layout em Grid - Calendário e Solicitações lado a lado */
    .calendar-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 16px;
        margin-bottom: 16px;
        max-height: calc(100vh - 120px);
    }

    /* Messages */
    .alert {
        padding: 8px 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #f87171;
    }

    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
    }

    /* Buttons */
    .btn {
        padding: 6px 14px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: var(--white);
    }

    .btn-success {
        background: var(--success);
        color: var(--white);
    }

    .btn-danger {
        background: var(--danger);
        color: var(--white);
    }

    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        justify-content: center;
        align-items: center;
        padding: 16px;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-xl);
        max-width: 550px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 14px 20px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 16px;
        color: var(--gray-600);
        cursor: pointer;
        transition: color 0.2s;
    }

    .modal-close:hover {
        color: var(--danger);
    }

    /* Formulários */
    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 12px;
    }

    .form-group label {
        font-size: 11px;
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 7px 10px;
        border: 2px solid var(--gray-200);
        border-radius: 5px;
        font-size: 12px;
        background: var(--white);
        font-family: 'Inter', sans-serif;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    /* Notificações */
    #notificacoes-container {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .notificacao {
        background: var(--white);
        padding: 10px 14px;
        border-radius: 6px;
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 280px;
        max-width: 380px;
        animation: slideIn 0.4s ease-out;
        border-left: 3px solid;
        font-size: 0.875rem;
    }

    .notificacao.sucesso {
        border-left-color: var(--success);
    }

    .notificacao.erro {
        border-left-color: var(--danger);
    }

    .notificacao.info {
        border-left-color: var(--info);
    }

    .notificacao i {
        font-size: 1rem;
    }

    .notificacao .fechar-notif {
        margin-left: auto;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--gray-500);
    }

    .notificacao .fechar-notif:hover {
        color: var(--gray-900);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Cards */
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
        padding: 10px 14px;
        background: var(--gray-50);
        font-size: 12px;
        font-weight: 700;
        color: var(--gray-700);
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        flex-shrink: 0;
    }

    .card-body {
        padding: 12px;
        flex: 1;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* Calendário - SUPER COMPACTO */
    .calendario {
        max-height: calc(100vh - 140px);
    }

    .calendario .card-body {
        padding: 8px;
    }

    .fc {
        font-family: 'Inter', sans-serif;
        font-size: 0.75rem;
        height: 100%;
    }

    .fc .fc-toolbar {
        padding: 6px 8px;
        background: var(--white);
        border-radius: 6px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 8px;
        flex-shrink: 0;
    }

    .fc .fc-toolbar-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--gray-900);
    }

    .fc .fc-button {
        background: var(--primary);
        border: none;
        color: var(--white);
        border-radius: 5px;
        padding: 4px 10px;
        font-weight: 600;
        font-size: 0.7rem;
    }

    .fc .fc-button:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .fc .fc-button:focus {
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
    }

    /* Células do calendário mais compactas */
    .fc .fc-view-harness {
        height: calc(100vh - 260px) !important;
    }

    .fc .fc-daygrid-day-frame {
        min-height: 55px !important;
    }

    .fc .fc-daygrid-day-number {
        padding: 2px 4px;
        font-size: 0.75rem;
    }

    .fc .fc-col-header-cell {
        padding: 4px 2px;
        font-size: 0.7rem;
    }

    .fc-event {
        border-radius: 3px !important;
        font-size: 0.65rem !important;
        padding: 1px 4px !important;
        cursor: pointer;
        line-height: 1.2 !important;
        white-space: normal !important;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--white);
        margin-bottom: 1px !important;
    }

    .fc-event.confirmado {
        background-color: var(--success) !important;
        border-color: var(--success) !important;
    }

    .fc-event.cancelado {
        background-color: var(--danger) !important;
        border-color: var(--danger) !important;
        text-decoration: line-through;
    }

    .fc-event.pendente {
        background-color: var(--warning) !important;
        border-color: var(--warning) !important;
        color: var(--gray-900) !important;
    }

    .fc-event.finalizado {
        background-color: var(--gray-600) !important;
        border-color: var(--gray-600) !important;
    }

    .fc-event.passado {
        opacity: 0.7;
    }

    .fc-event:hover {
        transform: scale(1.02);
        box-shadow: var(--shadow-sm);
        z-index: 10;
    }

    .fc-daygrid-day {
        background: var(--white);
        border: 1px solid var(--gray-200);
    }

    .fc-daygrid-day:hover {
        background: var(--gray-50);
    }

    .fc-daygrid-day.fc-day-disabled {
        background: var(--gray-100);
        opacity: 0.5;
    }

    .fc .fc-daygrid-more-link {
        font-size: 0.65rem;
        color: var(--primary);
    }

    /* Lista de Solicitações - COMPACTA */
    .solicitacoes {
        max-height: calc(100vh - 140px);
        overflow: hidden;
    }

    .solicitacoes .card-body {
        padding: 0;
        flex: 1;
        overflow: hidden;
    }

    #lista-solicitacoes {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
        padding: 10px;
        border-radius: 0 0 var(--border-radius) var(--border-radius);
        background-color: var(--gray-50);
    }

    #lista-solicitacoes::-webkit-scrollbar {
        width: 5px;
    }

    #lista-solicitacoes::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: 10px;
    }

    #lista-solicitacoes::-webkit-scrollbar-thumb {
        background: var(--gray-400);
        border-radius: 10px;
    }

    #lista-solicitacoes::-webkit-scrollbar-thumb:hover {
        background: var(--gray-600);
    }

    .solicitacao {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: 6px;
        padding: 8px;
        margin-bottom: 8px;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s ease;
    }

    .solicitacao:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary);
    }

    .solicitacao-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .solicitacao-data {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 3px;
    }

    .solicitacao-observacoes {
        font-size: 0.7rem;
        color: var(--gray-600);
        font-style: italic;
        background: var(--gray-100);
        padding: 4px 5px;
        border-radius: 3px;
        margin-top: 3px;
    }

    .solicitacao-actions {
        display: flex;
        gap: 3px;
    }

    .solicitacao-actions button {
        background: none;
        border: none;
        font-size: 0.9rem;
        cursor: pointer;
        padding: 3px 5px;
        border-radius: 3px;
        transition: all 0.2s;
    }

    .solicitacao-actions .aceitar {
        color: var(--success);
    }

    .solicitacao-actions .aceitar:hover {
        background: #d1fae5;
    }

    .solicitacao-actions .recusar {
        color: var(--danger);
    }

    .solicitacao-actions .recusar:hover {
        background: #fee2e2;
    }

    #contador-solicitacoes {
        background: var(--danger);
        color: var(--white);
        padding: 2px 5px;
        border-radius: 8px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 5px;
    }

    /* Modal Editar */
    .info-agendamento {
        background: var(--gray-50);
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        border-left: 3px solid var(--primary);
    }

    .info-item {
        margin: 5px 0;
        font-size: 12px;
        color: var(--gray-700);
    }

    .info-item strong {
        font-weight: 600;
        color: var(--gray-900);
    }

    /* Responsivo */
    @media (max-width: 1400px) {
        .calendar-layout {
            grid-template-columns: 1fr 320px;
        }
    }

    @media (max-width: 1200px) {
        .calendar-layout {
            grid-template-columns: 1fr 300px;
        }
    }

    @media (max-width: 992px) {
        .calendar-layout {
            grid-template-columns: 1fr;
        }
        
        .solicitacoes {
            max-height: 350px;
        }
        
        #lista-solicitacoes {
            max-height: 290px;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 10px;
        }
        
        .page-title h1 {
            font-size: 1.15rem;
        }
        
        .modal-content {
            max-width: 95%;
            margin: 8px;
        }
        
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 8px;
        }
        
        .fc .fc-toolbar-title {
            font-size: 0.9rem;
        }
        
        .fc .fc-button {
            padding: 4px 8px;
            font-size: 0.65rem;
        }
        
        .fc .fc-daygrid-day-frame {
            min-height: 50px !important;
        }
        
        .solicitacao-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
    }

    @media (max-width: 480px) {
        .page-title h1 {
            font-size: 1rem;
        }
        
        .fc-event {
            font-size: 0.6rem !important;
            padding: 1px 3px !important;
        }
    }
</style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <!-- Notificações Toast -->
            <div id="notificacoes-container"></div>

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="far fa-calendar-alt"></i> Calendário - PetAgenda</h1>
                </div>
            </div>

            <!-- Layout em Grid: Calendário + Solicitações -->
            <div class="calendar-layout">
                <!-- Calendário -->
                <div class="card calendario">
                    <div class="card-header"><i class="far fa-calendar-alt"></i> Calendário</div>
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>

                <!-- Solicitações -->
                <div class="card solicitacoes">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i> Solicitações Pendentes
                        <span id="contador-solicitacoes" style="display: none;">0</span>
                    </div>
                    <div class="card-body">
                        <div id="lista-solicitacoes">Carregando...</div>
                    </div>
                </div>
            </div> <!-- Fecha calendar-layout -->

            <!-- Modal Novo Agendamento -->
            <div id="modalAgendamento" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-calendar-plus"></i> Novo Agendamento</h2>
                        <button class="modal-close" onclick="fecharModal('modalAgendamento')">&times;</button>
                    </div>
                    <form action="/Bruno/PetCare/PHP/Calendario/salvar_agendamento.php" method="POST" id="formAgendamento">
                        <div class="modal-body">
                            <?php if ($tipo === 'Secretaria'): ?>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Cliente</label>
                                    <select name="cliente_id" id="cliente_id" class="form-control" required>
                                        <option value="">Selecione</option>
                                        <?php
                                        $clientes = $pdo->query("SELECT id, nome FROM Usuarios WHERE tipo_usuario = 'Cliente'")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($clientes as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="cliente_id" value="<?= $usuario_id ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label><i class="fas fa-paw"></i> Animal</label>
                                <select name="animal_id" id="animal_id" class="form-control" required>
                                    <option value="">Selecione um cliente primeiro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-concierge-bell"></i> Serviço</label>
                                <select name="servico_id" class="form-control" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $servicos = $pdo->query("SELECT id, nome FROM Servicos")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($servicos as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="far fa-calendar"></i> Data</label>
                                <input type="text" name="data" id="data" class="form-control" readonly required>
                            </div>

                            <div class="form-group">
                                <label><i class="far fa-clock"></i> Horário</label>
                                <select name="hora_inicio" id="horarios" class="form-control" required>
                                    <option value="">Selecione uma data</option>
                                </select>
                                <input type="hidden" name="hora_final" id="hora_final">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Observações</label>
                                <textarea name="observacoes" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAgendamento')">Cancelar</button>
                            <button type="submit" class="btn btn-success"><i class="fas fa-calendar-check"></i> Agendar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Editar -->
            <div id="modal-editar" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-edit"></i> Editar Agendamento</h2>
                        <button class="modal-close" id="fechar-modal-editar">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="info-agendamento">
                            <div class="info-item"><strong>Cliente:</strong> <span id="info-cliente"></span></div>
                            <div class="info-item"><strong>Animal:</strong> <span id="info-animal"></span></div>
                            <div class="info-item"><strong>Serviço:</strong> <span id="info-servico"></span></div>
                        </div>
                        <form id="form-editar">
                            <input type="hidden" name="id" id="edit-id">
                            <div class="form-group">
                                <label><i class="far fa-calendar"></i> Data</label>
                                <input type="date" name="data" id="edit-data" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label><i class="far fa-clock"></i> Hora Início</label>
                                <input type="time" name="hora_inicio" id="edit-hora" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Observações</label>
                                <textarea name="observacoes" id="edit-observacoes" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-info-circle"></i> Status</label>
                                <select name="status" id="edit-status" class="form-control" required>
                                    <option value="pendente">Pendente</option>
                                    <option value="confirmado">Confirmado</option>
                                    <option value="cancelado">Cancelado</option>
                                    <option value="finalizado">Finalizado</option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" id="btn-excluir"><i class="fas fa-trash"></i> Excluir</button>
                                <button type="button" class="btn btn-secondary" id="btn-cancelar"><i class="fas fa-times"></i> Cancelar</button>
                                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Função para abrir/fechar modal
    function abrirModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    // Função de Notificação
    function mostrarNotificacao(mensagem, tipo = 'info', duracao = 5000) {
        const container = document.getElementById('notificacoes-container');
        const id = Date.now();
        const div = document.createElement('div');
        div.className = `notificacao ${tipo}`;
        div.id = `notif-${id}`;

        const icon = tipo === 'sucesso' ? 'check-circle' : 
                     tipo === 'erro' ? 'exclamation-circle' : 'info-circle';

        div.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${mensagem}</span>
            <i class="fas fa-times fechar-notif"></i>
        `;

        container.appendChild(div);

        div.querySelector('.fechar-notif').addEventListener('click', () => {
            div.style.animation = 'slideIn 0.4s reverse';
            setTimeout(() => div.remove(), 400);
        });

        setTimeout(() => {
            if (div && div.parentNode) {
                div.style.animation = 'slideIn 0.4s reverse';
                setTimeout(() => div.remove(), 400);
            }
        }, duracao);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const BASE_URL = '/Bruno/PetCare/PHP/Calendario/';
        let diasIndisponiveis = {};
        let calendar;

        // Carregar dias indisponíveis
        async function carregarDiasIndisponiveis() {
            try {
                const res = await fetch(BASE_URL + 'get_dias_indisponiveis.php');
                if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                const data = await res.json();
                diasIndisponiveis = data || { feriados: [], dias_nao_ativos: [], periodos: [] };
            } catch (error) {
                console.error('Erro ao carregar dias indisponíveis:', error);
                mostrarNotificacao('Erro ao carregar dias indisponíveis.', 'erro');
                diasIndisponiveis = { feriados: [], dias_nao_ativos: [], periodos: [] };
            }
        }

        function formatarDataLocal(date) {
            const ano = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${ano}-${mes}-${dia}`;
        }

        function verificarDisponibilidade(dateStr) {
            if (!diasIndisponiveis.feriados) return null;
            const data = new Date(dateStr + 'T00:00:00');
            const nomesDias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            const nomeDia = nomesDias[data.getDay()];
            const { feriados = [], dias_nao_ativos = [], periodos = [] } = diasIndisponiveis;

            const feriado = feriados.find(f => f.data === dateStr);
            const periodo = periodos.find(p => dateStr >= p.data_inicio && dateStr <= p.data_fim);
            const diaNaoAtivo = dias_nao_ativos.includes(nomeDia);

            if (diaNaoAtivo) return "Clínica fechada neste dia.";
            if (feriado) return `Feriado: ${feriado.nome}`;
            if (periodo) return `Indisponível (${periodo.motivo})`;
            return null;
        }

        // Configuração do FullCalendar
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            selectable: true,
            editable: true,
            eventResizableFromStart: true,
            events: function(info, successCallback, failureCallback) {
                console.log('Carregando eventos do calendário...');
                fetch(BASE_URL + 'get_agendamentos.php')
                    .then(response => {
                        console.log('Status da resposta:', response.status);
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Eventos recebidos:', data);
                        if (data.length > 0) {
                            console.log('Primeiro evento detalhado:', data[0]);
                            console.log('ExtendedProps do primeiro:', data[0].extendedProps);
                        }
                        successCallback(data);
                    })
                    .catch(error => {
                        console.error('Erro ao carregar eventos:', error);
                        mostrarNotificacao('Erro ao carregar agendamentos: ' + error.message, 'erro');
                        failureCallback(error);
                    });
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            dayMaxEvents: 3,
            eventMinHeight: 20,
            slotDuration: '00:15:00',
            slotLabelInterval: '01:00:00',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            dayCellDidMount: function (info) {
                const dataStr = formatarDataLocal(info.date);
                const hoje = formatarDataLocal(new Date());
                const motivo = verificarDisponibilidade(dataStr);

                if (dataStr < hoje) {
                    info.el.style.opacity = '0.4';
                    info.el.style.cursor = 'not-allowed';
                }

                if (motivo) {
                    if (motivo.includes('Feriado')) info.el.style.backgroundColor = 'rgba(255, 247, 194, 0.5)';
                    else if (motivo.includes('Indisponível')) info.el.style.backgroundColor = 'rgba(248, 215, 218, 0.5)';
                    else if (motivo.includes('fechada')) info.el.style.backgroundColor = 'rgba(253, 236, 234, 0.5)';
                    info.el.title = motivo;
                }
            },
            eventDidMount: function (info) {
                const props = info.event.extendedProps;
                const hoje = new Date();
                const eventDate = info.event.start;

                if (props && props.status) {
                    info.el.classList.add(props.status.toLowerCase());
                }

                if (eventDate < hoje) {
                    info.el.classList.add('passado');
                }

                let tooltip = `${props.servico_nome || 'Serviço'} - ${props.animal_nome || 'Animal'}`;
                if (props.status) tooltip += `\nStatus: ${props.status}`;
                if (props.cliente_nome) tooltip += `\nCliente: ${props.cliente_nome}`;
                if (props.observacoes) tooltip += `\nObs: ${props.observacoes}`;
                info.el.title = tooltip;
            },
            eventDrop: async function (info) {
                const props = info.event.extendedProps || {};
                const novoStatus = props.status === 'confirmado' ? 'pendente' : props.status || 'pendente';
                const novaData = formatarDataLocal(info.event.start);
                const novoHoraInicio = info.event.start.toTimeString().split(' ')[0].substring(0, 5);

                if (!confirm(`Mover para ${novaData} às ${novoHoraInicio}?` + (novoStatus === 'pendente' ? '\nStatus voltará para Pendente.' : ''))) {
                    info.revert();
                    return;
                }

                try {
                    const fd = new FormData();
                    fd.append('id', info.event.id);
                    fd.append('data', novaData);
                    fd.append('hora_inicio', novoHoraInicio);
                    fd.append('status', novoStatus);

                    const res = await fetch(BASE_URL + 'editar_agendamento.php', { method: 'POST', body: fd });
                    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    const result = await res.json();

                    if (result.success) {
                        mostrarNotificacao('Agendamento movido com sucesso!', 'sucesso');
                        calendar.refetchEvents();
                    } else {
                        mostrarNotificacao('Erro ao mover: ' + (result.message || 'Tente novamente'), 'erro');
                        info.revert();
                    }
                } catch (error) {
                    console.error('Erro ao mover agendamento:', error);
                    mostrarNotificacao('Erro de conexão: ' + error.message, 'erro');
                    info.revert();
                }
            },
            eventResize: async function (info) {
                const novaHoraFinal = info.event.end ? info.event.end.toTimeString().split(' ')[0].substring(0, 5) : null;
                if (!novaHoraFinal) { 
                    mostrarNotificacao('Hora final inválida.', 'erro'); 
                    info.revert(); 
                    return; 
                }

                if (!confirm(`Alterar término para ${novaHoraFinal}?`)) {
                    info.revert();
                    return;
                }

                try {
                    const fd = new FormData();
                    fd.append('id', info.event.id);
                    fd.append('hora_final', novaHoraFinal);

                    const res = await fetch(BASE_URL + 'editar_agendamento.php', { method: 'POST', body: fd });
                    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    const result = await res.json();

                    if (result.success) {
                        mostrarNotificacao('Duração atualizada!', 'sucesso');
                        calendar.refetchEvents();
                    } else {
                        mostrarNotificacao('Erro ao atualizar duração: ' + (result.message || 'Tente novamente'), 'erro');
                        info.revert();
                    }
                } catch (error) {
                    console.error('Erro ao redimensionar agendamento:', error);
                    mostrarNotificacao('Erro de conexão: ' + error.message, 'erro');
                    info.revert();
                }
            },
            eventClick: function (info) {
                const event = info.event;
                const props = event.extendedProps;

                console.log('=== EVENTO CLICADO ===');
                console.log('ID:', event.id);
                console.log('Título:', event.title);
                console.log('Início:', event.startStr);
                console.log('Props completas:', props);

                document.getElementById('edit-id').value = event.id || '';
                document.getElementById('edit-data').value = event.startStr.split('T')[0] || '';
                document.getElementById('edit-hora').value = event.startStr.split('T')[1]?.slice(0, 5) || '09:00';
                document.getElementById('edit-observacoes').value = props.observacoes || '';
                document.getElementById('edit-status').value = props.status || 'pendente';

                const clienteNome = props.cliente_nome || props.cliente || 'Não informado';
                const animalNome = props.animal_nome || props.animal || 'Não informado';
                const servicoNome = props.servico_nome || props.servico || 'Não informado';

                document.getElementById('info-cliente').textContent = clienteNome;
                document.getElementById('info-animal').textContent = animalNome;
                document.getElementById('info-servico').textContent = servicoNome;

                abrirModal('modal-editar');
            },
            dateClick: function (info) {
                const dataStr = formatarDataLocal(info.date);
                const hoje = formatarDataLocal(new Date());

                if (dataStr < hoje) {
                    mostrarNotificacao('Não é possível agendar em dias passados.', 'erro');
                    return;
                }

                const motivo = verificarDisponibilidade(dataStr);
                if (motivo) {
                    mostrarNotificacao(motivo, 'erro');
                    return;
                }

                document.getElementById('data').value = dataStr;
                abrirModal('modalAgendamento');
            }
        });

        // Manipulação de Modais
        const modalNovo = document.getElementById("modalAgendamento");
        const modalEditar = document.getElementById("modal-editar");
        const fecharModalEditar = document.getElementById("fechar-modal-editar");
        const btnCancelar = document.getElementById('btn-cancelar');

        fecharModalEditar.addEventListener("click", () => fecharModal('modal-editar'));
        btnCancelar.addEventListener("click", () => fecharModal('modal-editar'));

        window.addEventListener("click", (e) => {
            if (e.target === modalNovo) fecharModal('modalAgendamento');
            if (e.target === modalEditar) fecharModal('modal-editar');
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
                document.body.style.overflow = 'auto';
            }
        });

        // Edição de Agendamento
        document.getElementById('form-editar').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const res = await fetch(BASE_URL + 'editar_agendamento.php', { method: 'POST', body: formData });
                if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                const result = await res.json();

                if (result.success) {
                    mostrarNotificacao('Agendamento atualizado com sucesso!', 'sucesso');
                    fecharModal('modal-editar');
                    calendar.refetchEvents();
                } else {
                    mostrarNotificacao('Erro ao atualizar: ' + (result.message || 'Tente novamente'), 'erro');
                }
            } catch (error) {
                console.error('Erro ao salvar agendamento:', error);
                mostrarNotificacao('Erro de conexão: ' + error.message, 'erro');
            }
        });

        // Excluir Agendamento
        document.getElementById('btn-excluir').addEventListener('click', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('edit-id').value;
            const cliente = document.getElementById('info-cliente').textContent;
            const data = document.getElementById('edit-data').value;

            if (!id || id === '') {
                mostrarNotificacao('ID do agendamento inválido', 'erro');
                return;
            }

            if (confirm(`Tem certeza que deseja excluir o agendamento de ${cliente} em ${data}?`)) {
                try {
                    const res = await fetch(BASE_URL + 'excluir_agendamento.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${encodeURIComponent(id)}`
                    });
                    
                    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    const result = await res.json();

                    if (result.success) {
                        mostrarNotificacao('Agendamento excluído com sucesso!', 'sucesso');
                        fecharModal('modal-editar');
                        calendar.refetchEvents();
                    } else {
                        mostrarNotificacao('Erro ao excluir: ' + (result.message || 'Tente novamente'), 'erro');
                    }
                } catch (error) {
                    console.error('Erro ao excluir agendamento:', error);
                    mostrarNotificacao('Erro de conexão: ' + error.message, 'erro');
                }
            }
        });

        // Carregar Solicitações
        function carregarSolicitacoes() {
            fetch(BASE_URL + 'get_solicitacoes.php')
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    return r.json();
                })
                .then(data => {
                    const container = document.getElementById('lista-solicitacoes');
                    const contador = document.getElementById('contador-solicitacoes');
                    
                    container.innerHTML = '';
                    
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-muted" style="text-align: center; margin: 20px 0;">Sem solicitações pendentes.</p>';
                        contador.style.display = 'none';
                    } else {
                        contador.textContent = data.length;
                        contador.style.display = 'inline';
                        
                        data.forEach(s => {
                            const div = document.createElement('div');
                            div.classList.add('solicitacao');
                            div.innerHTML = `
                                <div class="solicitacao-header">
                                    <span><strong>${s.animal_nome}</strong> (${s.cliente_nome})</span>
                                    <div class="solicitacao-actions">
                                        <button class="aceitar" data-id="${s.id}"><i class="fas fa-check"></i></button>
                                        <button class="recusar" data-id="${s.id}"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <div class="solicitacao-data">${s.data} ${s.hora_inicio}</div>
                                ${s.observacoes ? `<div class="solicitacao-observacoes">${s.observacoes}</div>` : ''}
                            `;
                            container.appendChild(div);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar solicitações:', error);
                    document.getElementById('lista-solicitacoes').innerHTML = '<p class="text-muted">Erro ao carregar.</p>';
                    mostrarNotificacao('Erro ao carregar solicitações: ' + error.message, 'erro');
                });
        }

        // Ações de Aceitar/Recusar
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.aceitar, .recusar');
            if (!btn) return;

            const id = btn.dataset.id;
            const status = btn.classList.contains('aceitar') ? 'confirmado' : 'cancelado';

            try {
                const res = await fetch(BASE_URL + 'atualizar_status.php', {
                    method: 'POST',
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `id=${id}&status=${status}`
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                const dados = await res.json();

                if (dados.status === "ok") {
                    mostrarNotificacao(`Agendamento ${status === 'confirmado' ? 'confirmado' : 'cancelado'}!`, 'sucesso');
                    carregarSolicitacoes();
                    calendar.refetchEvents();
                } else {
                    mostrarNotificacao(dados.erro || "Erro ao atualizar.", 'erro');
                }
            } catch (error) {
                console.error('Erro ao atualizar status:', error);
                mostrarNotificacao("Erro de conexão: " + error.message, 'erro');
            }
        });

        // Carregar Animais por Cliente
        const clienteSelect = document.getElementById('cliente_id');
        if (clienteSelect) {
            clienteSelect.addEventListener('change', function () {
                fetch(BASE_URL + 'get_animais.php?cliente_id=' + this.value)
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        return r.json();
                    })
                    .then(data => {
                        const animalSelect = document.getElementById('animal_id');
                        animalSelect.innerHTML = '<option value="">Selecione</option>';
                        data.forEach(animal => {
                            const opt = document.createElement('option');
                            opt.value = animal.id;
                            opt.textContent = animal.nome;
                            animalSelect.appendChild(opt);
                        });
                    })
                    .catch(error => {
                        console.error('Erro ao carregar animais:', error);
                        mostrarNotificacao('Erro ao carregar animais: ' + error.message, 'erro');
                    });
            });
        }

        // Carregar Horários Disponíveis
        const servicoSelect = document.querySelector('[name="servico_id"]');
        const dataInput = document.getElementById('data');
        const horariosSelect = document.getElementById('horarios');
        const horaFinalInput = document.getElementById('hora_final');

        function carregarHorarios() {
            if (!servicoSelect.value || !dataInput.value) return;

            fetch(`${BASE_URL}get_horarios_disponiveis.php?servico_id=${servicoSelect.value}&data=${dataInput.value}`)
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    return r.json();
                })
                .then(data => {
                    horariosSelect.innerHTML = '<option value="">Selecione</option>';
                    data.forEach(h => {
                        const opt = document.createElement('option');
                        opt.value = h.inicio;
                        opt.textContent = h.inicio.substring(0, 5) + ' - ' + h.final.substring(0, 5);
                        opt.dataset.final = h.final;
                        horariosSelect.appendChild(opt);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar horários:', error);
                    mostrarNotificacao('Erro ao carregar horários: ' + error.message, 'erro');
                });
        }

        servicoSelect.addEventListener('change', carregarHorarios);
        dataInput.addEventListener('change', carregarHorarios);
        horariosSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            horaFinalInput.value = opt.dataset.final || '';
        });

        // === ENVIO DO AGENDAMENTO VIA AJAX (CORRIGIDO E DENTRO DO ESCOPO) ===
        document.getElementById('formAgendamento').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const BASE_URL = '/Bruno/PetCare/PHP/Calendario/';

            try {
                const btnSubmit = this.querySelector('button[type="submit"]');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agendando...';
                btnSubmit.disabled = true;

                const res = await fetch(BASE_URL + 'salvar_agendamento.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await res.json();

                if (result.success) {
                    mostrarNotificacao('Agendamento realizado com sucesso!', 'sucesso');
                    fecharModal('modalAgendamento');

                    calendar.refetchEvents();

                    if (typeof carregarSolicitacoes === 'function') {
                        carregarSolicitacoes();
                    }

                    this.reset();
                    document.getElementById('animal_id').innerHTML = '<option value="">Selecione um cliente primeiro</option>';
                    document.getElementById('horarios').innerHTML = '<option value="">Selecione uma data</option>';

                } else {
                    mostrarNotificacao('Erro: ' + (result.message || 'Tente novamente'), 'erro');
                }
            } catch (error) {
                console.error('Erro no envio:', error);
                mostrarNotificacao('Erro de conexão. Tente novamente.', 'erro');
            } finally {
                const btnSubmit = this.querySelector('button[type="submit"]');
                btnSubmit.innerHTML = '<i class="fas fa-calendar-check"></i> Agendar';
                btnSubmit.disabled = false;
            }
        });

        // Inicialização
        carregarDiasIndisponiveis().then(() => {
            calendar.render();
            carregarSolicitacoes();
            setInterval(carregarSolicitacoes, 30000);
        });

        // Verificar URL para mensagens
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        const tipo = urlParams.get('tipo');
        if (msg) {
            mostrarNotificacao(decodeURIComponent(msg), tipo || 'info');
            window.history.replaceState({}, '', window.location.pathname);
        }
    });
</script>


</body>
</html>