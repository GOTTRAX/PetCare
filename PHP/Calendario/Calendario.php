<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    include '../conexao.php';
} 

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit;
}

$usuario_id = $_SESSION['id'];
$tipo = $_SESSION['tipo_usuario'];

define('UPLOAD_URL', '/assets/uploads/pets/');

$animais = [];
if ($tipo === 'Cliente') {
    $stmt = $pdo->prepare("SELECT id, nome, foto FROM Animais WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $pdo->query("SELECT id, nome, preco_normal, preco_feriado, duracao FROM Servicos ORDER BY nome");
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$icones_por_servico = [
    'consulta' => 'fa-stethoscope',
    'vacina' => 'fa-syringe',
    'tosa' => 'fa-cut',
    'banho' => 'fa-bath',
    'cirurgia' => 'fa-scalpel',
    'exame' => 'fa-vial',
];

function calcularHoraFinal($hora_inicio, $duracao)
{
    if (!$hora_inicio || !$duracao) {
        return null;
    }

    $hora_inicio_obj = DateTime::createFromFormat('H:i:s', $hora_inicio);
    if (!$hora_inicio_obj) {
        $hora_inicio_obj = DateTime::createFromFormat('H:i', $hora_inicio);
    }

    if (!$hora_inicio_obj) {
        return null;
    }

    $hora_inicio_obj->modify("+{$duracao} minutes");
    return $hora_inicio_obj->format('H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['servico_id']) && !isset($_GET['buscar_preco'])) {
    $id = intval($_GET['servico_id']);
    $stmt = $pdo->prepare("SELECT duracao FROM Servicos WHERE id = ?");
    $stmt->execute([$id]);
    $duracao = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($duracao ?: ['error' => 'Serviço não encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['buscar_preco']) && isset($_GET['servico_id']) && isset($_GET['data'])) {
    $servico_id = intval($_GET['servico_id']);
    $data = $_GET['data'];

    $stmt = $pdo->prepare("SELECT nome, preco_normal, preco_feriado FROM Servicos WHERE id = ?");
    $stmt->execute([$servico_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servico) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Serviço não encontrado']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nome FROM Feriados WHERE data = ?");
    $stmt->execute([$data]);
    $feriado = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_feriado = $feriado !== false;

    $preco = $is_feriado ? $servico['preco_feriado'] : $servico['preco_normal'];
    $label = $is_feriado ? 'Preço de Feriado' : 'Preço Normal';

    $servico_nome = strtolower($servico['nome']);
    $icone = 'fa-concierge-bell';
    foreach ($icones_por_servico as $key => $icon) {
        if (stripos($servico_nome, $key) !== false) {
            $icone = $icon;
            break;
        }
    }

    $emoji = $is_feriado ? '🎉' : "<i class='fas $icone service-icon'></i>";

    header('Content-Type: application/json');
    echo json_encode([
        'class' => $is_feriado ? 'highlight' : '',
        'valor' => "$emoji R$ " . number_format($preco, 2, ',', '.'),
        'tipo' => $label,
        'is_feriado' => $is_feriado,
        'preco_numerico' => $preco,
        'nome_feriado' => $is_feriado ? $feriado['nome'] : null
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cancelar') {
    error_log("Tentativa de cancelamento - ID: " . ($_POST['id'] ?? 'N/A') . ", Usuário: $usuario_id");

    $agendamento_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $motivo_cancelamento = filter_input(INPUT_POST, 'motivo_cancelamento', FILTER_SANITIZE_STRING) ?: '';

    if (!$agendamento_id) {
        error_log("Erro: ID do agendamento inválido ou não fornecido");
        http_response_code(400);
        echo json_encode(['error' => 'ID do agendamento inválido ou não fornecido']);
        exit;
    }

    if (empty($motivo_cancelamento)) {
        error_log("Erro: Motivo do cancelamento não fornecido");
        http_response_code(400);
        echo json_encode(['error' => 'Por favor, informe o motivo do cancelamento']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT status FROM Agendamentos WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$agendamento_id, $usuario_id]);
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agendamento) {
            error_log("Erro: Agendamento $agendamento_id não encontrado ou não pertence ao usuário $usuario_id");
            http_response_code(403);
            echo json_encode(['error' => 'Agendamento não encontrado ou não autorizado']);
            exit;
        }

        if ($agendamento['status'] === 'cancelado' || $agendamento['status'] === 'finalizado') {
            error_log("Erro: Agendamento $agendamento_id já está cancelado ou finalizado");
            http_response_code(400);
            echo json_encode(['error' => 'Este agendamento já está cancelado ou finalizado']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE Agendamentos SET status = 'cancelado', motivo_cancelamento = ? WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$motivo_cancelamento, $agendamento_id, $usuario_id]);

        if ($stmt->rowCount() > 0) {
            error_log("Agendamento $agendamento_id cancelado com sucesso por usuário $usuario_id");
            echo json_encode(['success' => true, 'message' => 'Agendamento cancelado com sucesso']);
        } else {
            error_log("Erro: Falha ao atualizar agendamento $agendamento_id");
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao cancelar agendamento']);
        }
    } catch (PDOException $e) {
        error_log("Erro ao cancelar agendamento $agendamento_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao cancelar agendamento: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Tentativa de criar/atualizar agendamento - Usuário: $usuario_id, Dados: " . json_encode($_POST));

    $animal_id = filter_input(INPUT_POST, 'animal_id', FILTER_VALIDATE_INT);
    $servico_id = filter_input(INPUT_POST, 'servico_id', FILTER_VALIDATE_INT);
    $veterinario_id = filter_input(INPUT_POST, 'veterinario_id', FILTER_VALIDATE_INT) ?: null;
    $data_hora = filter_input(INPUT_POST, 'data_hora', FILTER_SANITIZE_STRING);
    $hora_inicio = filter_input(INPUT_POST, 'hora_inicio', FILTER_SANITIZE_STRING);
    $hora_final = filter_input(INPUT_POST, 'hora_final', FILTER_SANITIZE_STRING);
    $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_STRING) ?: '';
    $agendamento_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;

    if (!$animal_id || !$servico_id || !$data_hora || !$hora_inicio) {
        error_log("Erro: Campos obrigatórios faltando");
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios (animal, serviço, data, horário inicial) devem ser preenchidos']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT duracao, nome FROM Servicos WHERE id = ?");
    $stmt->execute([$servico_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$servico) {
        error_log("Erro: Serviço inválido - ID: $servico_id");
        http_response_code(400);
        echo json_encode(['error' => 'Serviço inválido']);
        exit;
    }

    if (!$hora_final) {
        $hora_final = calcularHoraFinal($hora_inicio, $servico['duracao']);
        if (!$hora_final) {
            error_log("Erro: Não foi possível calcular hora final");
            http_response_code(400);
            echo json_encode(['error' => 'Não foi possível calcular a hora final']);
            exit;
        }
    }

    if ($tipo === 'Cliente') {
        // NOVA VALIDAÇÃO: Verifica se o mesmo animal já tem o mesmo serviço no mesmo dia
        $stmt = $pdo->prepare("
            SELECT a.id, s.nome as servico_nome, an.nome as animal_nome
            FROM Agendamentos a
            INNER JOIN Servicos s ON a.servico_id = s.id
            INNER JOIN Animais an ON a.animal_id = an.id
            WHERE a.cliente_id = ? 
            AND a.animal_id = ?
            AND a.servico_id = ?
            AND a.data_hora = ? 
            AND a.status NOT IN ('cancelado', 'finalizado')
            AND (? IS NULL OR a.id != ?)
        ");
        $stmt->execute([
            $usuario_id,
            $animal_id,
            $servico_id,
            $data_hora,
            $agendamento_id,
            $agendamento_id
        ]);
        
        $agendamento_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agendamento_existente) {
            error_log("Erro: Animal {$agendamento_existente['animal_nome']} já possui agendamento de {$agendamento_existente['servico_nome']} para esta data");
            http_response_code(400);
            echo json_encode([
                'error' => "Você já possui um agendamento de {$agendamento_existente['servico_nome']} para {$agendamento_existente['animal_nome']} nesta data. Por favor, escolha outra data ou cancele o agendamento anterior."
            ]);
            exit;
        }

        // Validação de conflito de horário
        $stmt = $pdo->prepare("
            SELECT a.id, s.nome as servico_nome, an.nome as animal_nome
            FROM Agendamentos a
            INNER JOIN Servicos s ON a.servico_id = s.id
            INNER JOIN Animais an ON a.animal_id = an.id
            WHERE a.cliente_id = ? 
            AND a.data_hora = ? 
            AND a.status NOT IN ('cancelado', 'finalizado')
            AND (? IS NULL OR a.id != ?)
            AND (
                (a.hora_inicio <= ? AND a.hora_final > ?) OR 
                (a.hora_inicio < ? AND a.hora_final >= ?) OR 
                (a.hora_inicio >= ? AND a.hora_inicio < ?)
            )
        ");
        $stmt->execute([
            $usuario_id,
            $data_hora,
            $agendamento_id,
            $agendamento_id,
            $hora_inicio,
            $hora_inicio,
            $hora_final,
            $hora_final,
            $hora_inicio,
            $hora_final
        ]);
        
        $conflito_horario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conflito_horario) {
            error_log("Erro: Conflito de horário detectado para {$conflito_horario['animal_nome']} - {$conflito_horario['servico_nome']}");
            http_response_code(400);
            echo json_encode([
                'error' => "Você já possui um agendamento de {$conflito_horario['servico_nome']} para {$conflito_horario['animal_nome']} que conflita com este horário."
            ]);
            exit;
        }
    }

    try {
        if ($agendamento_id) {
            $stmt = $pdo->prepare("
                UPDATE Agendamentos SET
                    animal_id = ?, veterinario_id = ?, servico_id = ?, 
                    data_hora = ?, hora_inicio = ?, hora_final = ?, observacoes = ?
                WHERE id = ? AND cliente_id = ?
            ");
            $stmt->execute([
                $animal_id,
                $veterinario_id,
                $servico_id,
                $data_hora,
                $hora_inicio,
                $hora_final,
                $observacoes,
                $agendamento_id,
                $usuario_id
            ]);
            error_log("Agendamento $agendamento_id atualizado com sucesso");
            echo json_encode(['success' => true, 'message' => 'Agendamento atualizado com sucesso']);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO Agendamentos (
                    cliente_id, animal_id, veterinario_id, servico_id, 
                    data_hora, hora_inicio, hora_final, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario_id,
                $animal_id,
                $veterinario_id,
                $servico_id,
                $data_hora,
                $hora_inicio,
                $hora_final,
                $observacoes
            ]);
            error_log("Novo agendamento criado com sucesso");
            echo json_encode(['success' => true, 'message' => 'Agendamento realizado com sucesso']);
        }
    } catch (PDOException $e) {
        error_log("Erro ao salvar agendamento: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao realizar agendamento: ' . $e->getMessage()]);
    }
    exit;
}

function gerarPrecoHTML($servico_id, $data, $pdo, $icones_por_servico)
{
    if (!$servico_id || !$data) {
        return ['class' => 'hidden', 'valor' => '', 'tipo' => ''];
    }

    $stmt = $pdo->prepare("SELECT nome, preco_normal, preco_feriado FROM Servicos WHERE id = ?");
    $stmt->execute([$servico_id]);
    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id FROM Feriados WHERE data = ?");
    $stmt->execute([$data]);
    $is_feriado = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

    $preco = $is_feriado ? $servico['preco_feriado'] : $servico['preco_normal'];
    $label = $is_feriado ? 'Preço de Feriado' : 'Preço Normal';
    $servico_nome = strtolower($servico['nome']);
    $icone = 'fa-concierge-bell';
    foreach ($icones_por_servico as $key => $icon) {
        if (stripos($servico_nome, $key) !== false) {
            $icone = $icon;
            break;
        }
    }
    $emoji = $is_feriado ? '🎉' : "<i class='fas $icone service-icon'></i>";
    $class = $is_feriado ? 'highlight' : '';

    return [
        'class' => '',
        'valor' => "$emoji R$ " . number_format($preco, 2, ',', '.'),
        'tipo' => $label
    ];
}

$preco_info = ['class' => 'hidden', 'valor' => '', 'tipo' => ''];

include '../header.php';
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

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

    .calendar-layout {
        display: grid;
        grid-template-columns: 1.3fr 400px;
        gap: 16px;
        max-height: calc(100vh - 140px);
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
    }

    .card.calendar-card .card-body {
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    #calendar {
        height: calc(100vh - 180px);
        font-size: 0.75rem;
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
    }

    .fc .fc-daygrid-day-frame {
        min-height: 70px !important;
    }

    .fc .fc-col-header-cell {
        padding: 6px 2px;
        font-size: 0.7rem;
    }

    .fc .fc-daygrid-day-number {
        padding: 3px 5px;
        font-size: 0.8rem;
    }

    .fc-event {
        border-radius: 3px !important;
        font-size: 0.65rem !important;
        padding: 1px 4px !important;
        cursor: pointer;
        margin-bottom: 1px !important;
        color: var(--white);
        transition: none !important;
    }

    .fc-event:hover {
        transform: none !important;
        filter: none !important;
        box-shadow: none !important;
        opacity: 1 !important;
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
    textarea:focus,
    input[type="date"]:focus,
    input[type="time"]:focus {
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
        transition: none !important;
    }

    .animal-card:hover {
        background: var(--gray-50) !important;
        transform: none !important;
        box-shadow: none !important;
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

    .animal-card.selected .animal-image {
        border-color: var(--white);
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

    .preco-info * {
        all: initial;
    }

    .preco-info {
        display: flex !important;
        flex-direction: column !important;
        background: #ffffff !important;
        padding: 16px 20px !important;
        border-radius: 10px !important;
        border-left: 4px solid #6366f1 !important;
        gap: 10px !important;
        margin-top: 12px !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
        transition: all 0.3s ease !important;
        width: 100% !important;
        box-sizing: border-box !important;
        font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
    }

    .preco-info.hidden {
        display: none !important;
    }

    .preco-info.highlight {
        background: #fffbf5 !important;
        border-left-color: #f59e0b !important;
    }

    .preco-valor {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        font-weight: 800 !important;
        color: #1e3a8a !important;
        font-size: 1.75rem !important;
        line-height: 1.2 !important;
        font-family: 'Montserrat', sans-serif !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .preco-info.highlight .preco-valor {
        color: #b45309 !important;
    }

    .preco-valor i,
    .preco-valor i.service-icon {
        font-size: 1.4rem !important;
        color: #6366f1 !important;
        display: inline-block !important;
        font-style: normal !important;
        font-weight: 900 !important;
        font-family: 'Font Awesome 6 Free' !important;
    }

    .preco-info.highlight .preco-valor i,
    .preco-info.highlight .preco-valor i.service-icon {
        color: #f59e0b !important;
    }

    .preco-tipo {
        display: flex !important;
        align-items: center !important;
        gap: 6px !important;
        font-size: 0.813rem !important;
        color: #475569 !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        font-family: 'Montserrat', sans-serif !important;
        margin: 0 !important;
        line-height: 1 !important;
    }

    .preco-tipo::before {
        content: '▸' !important;
        display: inline-block !important;
        color: #6366f1 !important;
        font-size: 0.875rem !important;
        font-weight: 900 !important;
        line-height: 1 !important;
    }

    .preco-info.highlight .preco-tipo {
        color: #92400e !important;
    }

    .preco-info.highlight .preco-tipo::before {
        color: #f59e0b !important;
    }

    .preco-info.highlight::before {
        content: 'Feriado' !important;
        display: inline-block !important;
        background: #fef3c7 !important;
        color: #92400e !important;
        font-size: 0.688rem !important;
        font-weight: 700 !important;
        padding: 3px 10px !important;
        border-radius: 4px !important;
        letter-spacing: 0.5px !important;
        font-family: 'Montserrat', sans-serif !important;
        line-height: 1 !important;
        text-transform: uppercase !important;
        margin-bottom: 8px !important;
        align-self: flex-start !important;
        border: 1px solid #fcd34d !important;
    }

    @keyframes slideInPrice {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .preco-info:not(.hidden) {
        animation: slideInPrice 0.5s ease-out !important;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: none !important;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: var(--white);
    }

    .btn-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        transform: none !important;
        box-shadow: none !important;
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

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
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
        padding: 14px 18px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body {
        padding: 18px;
    }

    .modal-details {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .modal-details p {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: var(--gray-700);
    }

    .modal-details p i {
        color: var(--primary);
        font-size: 1rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--gray-500);
        cursor: pointer;
        transition: color 0.2s;
    }

    .modal-close:hover {
        color: var(--danger);
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

    #notification i {
        font-size: 1.1rem;
    }

    .close-notification {
        cursor: pointer;
        margin-left: auto;
        font-size: 1rem;
    }

    #cancelamento-form {
        display: none;
        margin-top: 16px;
        padding: 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border-left: 4px solid var(--danger);
    }

    #cancelamento-form.active {
        display: block;
    }

    @media (max-width: 1200px) {
        .calendar-layout {
            grid-template-columns: 1fr 360px;
        }
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
        .container {
            padding: 10px;
        }
        .animals-grid {
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        }
        .animal-image {
            width: 55px;
            height: 55px;
        }
        .modal-content {
            max-width: 95%;
        }
    }

    @media (max-width: 480px) {
        .btn {
            font-size: 0.8rem;
            padding: 7px 12px;
        }
        .preco-valor {
            font-size: 1.5rem !important;
        }
        .preco-info.highlight .preco-valor {
            font-size: 1.7rem !important;
        }
        .preco-tipo {
            font-size: 0.8125rem !important;
        }
        .preco-info.highlight::before {
            font-size: 0.65rem !important;
            padding: 3px 10px !important;
        }
    }
</style>
</head>

<body>
    <div class="container">
        <div id="notification"></div>
        <div style="margin-bottom: 20px;">
            <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--gray-900); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-calendar-alt" style="color: var(--primary);"></i>
                Calendário de Agendamentos
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem; margin-top: 8px;">
                Gerencie seus agendamentos e consulte a disponibilidade da clínica
            </p>
        </div>

        <div class="calendar-layout">
            <div class="card calendar-card">
                <div class="card-header">
                    <i class="far fa-calendar-alt"></i> Agenda
                </div>
                <div class="card-body">
                    <div id='calendar'></div>
                </div>
            </div>

            <?php if ($tipo === 'Cliente'): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calendar-plus"></i> Novo Agendamento
                    </div>
                    <div class="card-body">
                        <form action="Calendario.php" method="POST" id="formAgendamento">
                            <input type="hidden" name="animal_id" id="animal_id" required>

                            <div class="form-group">
                                <label><i class="fas fa-paw"></i> Escolha o Animal:</label>
                                <div class="animals-grid">
                                    <?php foreach ($animais as $animal): ?>
                                        <div class="animal-card" data-id="<?= $animal['id'] ?>">
                                            <div class="animal-image">
                                                <?php if (!empty($animal['foto'])): ?>
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($animal['foto']) ?>"
                                                        alt="<?= htmlspecialchars($animal['nome']) ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-paw"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="animal-name"><?= htmlspecialchars($animal['nome']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

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
                                            <option value="<?= $s['id'] ?>"
                                                data-preco-normal="<?= $s['preco_normal'] ?>"
                                                data-preco-feriado="<?= $s['preco_feriado'] ?>"
                                                data-duracao="<?= $s['duracao'] ?>"
                                                data-icone="<?= htmlspecialchars($icone) ?>">
                                                <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['preco_normal'], 2, ',', '.') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="preco-info" class="preco-info hidden">
                                    <div class="preco-valor" id="preco-text"></div>
                                    <div class="preco-tipo" id="tipo-preco"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label><i class="far fa-calendar"></i> Data:</label>
                                <input type="date" name="data_hora" id="data" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label><i class="far fa-clock"></i> Horário:</label>
                                <select name="hora_inicio" id="horarios" class="form-control" required disabled>
                                    <option value="">Selecione uma data</option>
                                </select>
                                <input type="hidden" name="hora_final" id="hora_final" required>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-sticky-note"></i> Observações (opcional):</label>
                                <textarea name="observacoes" class="form-control" placeholder="Digite suas observações..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" id="btn-agendar" disabled>
                                <i class="fas fa-calendar-check"></i> Agendar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="modalDetalhes" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title"><i class="fas fa-info-circle"></i> Detalhes do Agendamento</h3>
                    <button class="modal-close close-details">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-details">
                        <p id="detalhes-servico"><i class="fas fa-concierge-bell"></i> <span></span></p>
                        <p id="detalhes-animal"><i class="fas fa-paw"></i> <span></span></p>
                        <p id="detalhes-data"><i class="far fa-calendar"></i> <span></span></p>
                        <p id="detalhes-horario"><i class="far fa-clock"></i> <span></span></p>
                        <p id="detalhes-status"><i class="fas fa-info-circle"></i> <span></span></p>
                        <p id="detalhes-observacoes"><i class="fas fa-sticky-note"></i> <span></span></p>
                    </div>

                    <div id="cancelamento-form">
                        <div class="form-group">
                            <label style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Motivo do Cancelamento:</label>
                            <textarea id="motivo-cancelamento" class="form-control" placeholder="Por favor, informe o motivo do cancelamento..." required></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-danger" id="btnConfirmarCancelamento">
                                <i class="fas fa-check"></i> Confirmar Cancelamento
                            </button>
                            <button type="button" class="btn btn-secondary" id="btnCancelarFormCancelamento">
                                <i class="fas fa-times"></i> Voltar
                            </button>
                        </div>
                    </div>

                    <div class="btn-group" id="botoes-principais">
                        <button type="button" class="btn btn-primary" id="btnEditar">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button type="button" class="btn btn-danger" id="btnCancelarDetalhes" disabled>
                            <i class="fas fa-times"></i> Cancelar Agendamento
                        </button>
                        <button type="button" class="btn btn-secondary close-details">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let calendar;
            let dataSelecionada = '';
            let isFeriado = false;
            let diasIndisponiveis = {};
            let agendamentoEditando = null;
            let eventoSelecionado = null;

            const iconesPorServico = {
                'consulta': 'fa-stethoscope',
                'vacina': 'fa-syringe',
                'tosa': 'fa-cut',
                'banho': 'fa-bath',
                'cirurgia': 'fa-scalpel',
                'exame': 'fa-vial'
            };

            const modalDetalhes = document.getElementById('modalDetalhes');
            const closeModalDetalhes = modalDetalhes.querySelectorAll('.close-details');
            const formNovo = document.getElementById('formAgendamento');
            const btnAgendar = document.getElementById('btn-agendar');
            const btnEditar = document.getElementById('btnEditar');
            const btnCancelarDetalhes = document.getElementById('btnCancelarDetalhes');
            const notification = document.getElementById('notification');
            const servicoSelect = document.getElementById('servico_id');
            const selectIcon = document.querySelector('.select-icon');
            const dataInput = document.getElementById('data');
            const cancelamentoForm = document.getElementById('cancelamento-form');
            const btnConfirmarCancelamento = document.getElementById('btnConfirmarCancelamento');
            const btnCancelarFormCancelamento = document.getElementById('btnCancelarFormCancelamento');
            const motivoCancelamento = document.getElementById('motivo-cancelamento');
            const botoesPrincipais = document.getElementById('botoes-principais');

            const showNotification = (msg, type = 'success') => {
                notification.innerHTML = `
        <i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'}"></i>
        <span>${msg}</span>
        <i class="fas fa-times close-notification"></i>`;
                notification.className = type;
                notification.classList.add('show');
                setTimeout(() => notification.classList.remove('show'), 5000);
                notification.querySelector('.close-notification')?.addEventListener('click', () => notification.classList.remove('show'));
            };

            const atualizarIconeSelect = () => {
                const opt = servicoSelect.options[servicoSelect.selectedIndex];
                const icone = opt?.dataset.icone || 'fa-concierge-bell';
                selectIcon.className = `fas ${icone} select-icon`;
            };

            const pad = n => String(n).padStart(2, '0');
            const formatarDataLocal = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

            const calcularHoraFinalCliente = (inicio, duracao) => {
                if (!inicio || !duracao) return '';
                const [h, m] = inicio.split(':').map(Number);
                const dt = new Date();
                dt.setHours(h, m, 0);
                dt.setMinutes(dt.getMinutes() + parseInt(duracao));
                return `${pad(dt.getHours())}:${pad(dt.getMinutes())}:00`;
            };

            const carregarDiasIndisponiveis = async () => {
                try {
                    const r = await fetch('get_dias_indisponiveis.php');
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    const data = await r.json();
                    diasIndisponiveis = data || {feriados: [], dias_nao_ativos: [], periodos: []};
                    window.diasIndisponiveis = diasIndisponiveis;
                } catch (e) {
                    console.error('Erro ao carregar dias indisponíveis', e);
                    diasIndisponiveis = {feriados: [], dias_nao_ativos: [], periodos: []};
                }
            };

            const atualizarPreco = async () => {
                const info = document.getElementById('preco-info');
                const valor = document.getElementById('preco-text');
                const tipo = document.getElementById('tipo-preco');

                if (!servicoSelect.value || !dataSelecionada) {
                    info.classList.add('hidden');
                    return;
                }

                try {
                    const res = await fetch(`Calendario.php?buscar_preco=1&servico_id=${servicoSelect.value}&data=${dataSelecionada}`, {
                        headers: {'Cache-Control': 'no-cache'}
                    });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();
                    if (data.error) throw new Error(data.error);

                    info.classList.remove('hidden');
                    info.classList.toggle('highlight', data.is_feriado);
                    valor.innerHTML = data.valor;
                    tipo.textContent = data.tipo;
                } catch (e) {
                    console.warn('Fetch preço falhou → fallback local', e);
                    const opt = servicoSelect.options[servicoSelect.selectedIndex];
                    const feriado = diasIndisponiveis.feriados?.some(f => f.data === dataSelecionada);
                    const preco = feriado ? opt.dataset.precoFeriado : opt.dataset.precoNormal;
                    const label = feriado ? 'Preço de Feriado' : 'Preço Normal';
                    const emoji = feriado ? '🎉' : `<i class="fas ${opt.dataset.icone||'fa-concierge-bell'} service-icon"></i>`;

                    info.classList.remove('hidden');
                    info.classList.toggle('highlight', feriado);
                    valor.innerHTML = `${emoji} R$ ${Number(preco).toLocaleString('pt-BR', {minimumFractionDigits:2})}`;
                    tipo.textContent = label;
                }
            };

            const inicializarCalendario = () => {
                calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                    initialView: 'dayGridMonth',
                    locale: 'pt-br',
                    selectable: true,
                    editable: false,
                    navLinks: true,
                    dayMaxEvents: 4,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    views: {
                        timeGridWeek: {slotMinTime: '06:00:00', slotMaxTime: '22:00:00', slotDuration: '00:30:00'},
                        timeGridDay: {slotMinTime: '06:00:00', slotMaxTime: '22:00:00', slotDuration: '00:30:00'}
                    },
                    dayCellDidMount: info => {
                        if (!window.diasIndisponiveis) return;
                        const ds = formatarDataLocal(info.date);
                        const hoje = formatarDataLocal(new Date());
                        const {feriados = [], dias_nao_ativos = [], periodos = []} = window.diasIndisponiveis;
                        const nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                        const diaSemana = nomes[info.date.getDay()];

                        const feriado = feriados.find(f => f.data === ds);
                        const periodo = periodos.find(p => ds >= p.data_inicio && ds <= p.data_fim);
                        const naoAtivo = dias_nao_ativos.includes(diaSemana);

                        if (ds < hoje) {
                            info.el.style.opacity = '0.4';
                            info.el.style.cursor = 'not-allowed';
                        }
                        if (feriado) {
                            info.el.style.backgroundColor = 'rgba(255,247,194,0.5)';
                            info.el.title = `🎉 Feriado: ${feriado.nome}`;
                        } else if (periodo) {
                            info.el.style.backgroundColor = 'rgba(248,215,218,0.5)';
                            info.el.title = `Fechado (${periodo.motivo})`;
                        } else if (naoAtivo) {
                            info.el.style.backgroundColor = 'rgba(253,236,234,0.5)';
                            info.el.title = `Clínica fechada (${diaSemana})`;
                        }
                    },
                    dateClick: info => {
                        const ds = formatarDataLocal(info.date);
                        const hoje = formatarDataLocal(new Date());

                        if (ds < hoje) {
                            showNotification('Não é possível agendar em dias passados.', 'error');
                            return;
                        }

                        const feriado = diasIndisponiveis.feriados?.find(f => f.data === ds);
                        const nomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                        const diaSemana = nomes[info.date.getDay()];
                        const naoAtivo = (diasIndisponiveis.dias_nao_ativos || []).includes(diaSemana);
                        const periodoFechado = (diasIndisponiveis.periodos || []).some(p => ds >= p.data_inicio && ds <= p.data_fim);

                        if (naoAtivo || periodoFechado) {
                            showNotification('Este dia está indisponível.', 'error');
                            return;
                        }
                        if (feriado && !confirm(`${feriado.nome}\nEste é um feriado. Continuar?`)) return;

                        dataSelecionada = ds;
                        dataInput.value = ds;
                        isFeriado = !!feriado;

                        atualizarPreco();
                        if (servicoSelect.value) buscarHorariosDisponiveis(ds, servicoSelect.value);
                        else {
                            const h = document.getElementById('horarios');
                            h.innerHTML = '<option value="">Selecione um serviço primeiro</option>';
                            h.disabled = true;
                            document.getElementById('hora_final').value = '';
                        }
                        verificarFormulario();
                    },
                    events: {
                        url: 'get_agendamentos.php',
                        method: 'GET',
                        extraParams: () => ({tipo_usuario: '<?= $tipo ?>', usuario_id: '<?= $usuario_id ?>'}),
                        failure: () => showNotification('Erro ao carregar agendamentos.', 'error')
                    },
                    eventDidMount: info => {
                        const p = info.event.extendedProps;
                        const hoje = new Date();
                        const evDate = info.event.start;
                        if (p?.status) info.el.classList.add(p.status.toLowerCase());
                        if (evDate < hoje) info.el.classList.add('passado');

                        if (p?.servico) {
                            let ic = 'fa-concierge-bell';
                            const nome = p.servico.toLowerCase();
                            for (const [k, v] of Object.entries(iconesPorServico))
                                if (nome.includes(k)) {ic = v; break;}
                            const i = document.createElement('i');
                            i.className = `fas ${ic}`;
                            i.style.marginRight = '3px';
                            info.el.insertBefore(i, info.el.firstChild);
                        }
                        info.el.title = `${p.servico} - ${p.animal}\nStatus: ${p.status}\nClique para detalhes`;
                    },
                    eventClick: info => {
                        eventoSelecionado = info.event;
                        const p = info.event.extendedProps || {};

                        let ic = 'fa-concierge-bell';
                        if (p.servico) {
                            const n = p.servico.toLowerCase();
                            for (const [k, v] of Object.entries(iconesPorServico))
                                if (n.includes(k)) {ic = v; break;}
                        }

                        document.getElementById('detalhes-servico').innerHTML = `<i class="fas ${ic}"></i> <span>${p.servico||'-'}</span>`;
                        document.getElementById('detalhes-animal').innerHTML = `<i class="fas fa-paw"></i> <span>${p.animal||'-'}</span>`;
                        document.getElementById('detalhes-data').innerHTML = `<i class="far fa-calendar"></i> <span>${formatarDataLocal(info.event.start)}</span>`;
                        document.getElementById('detalhes-horario').innerHTML = `<i class="far fa-clock"></i> <span>${info.event.start.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</span>`;
                        document.getElementById('detalhes-status').innerHTML = `<i class="fas fa-info-circle"></i> <span>${p.status||'-'}</span>`;
                        document.getElementById('detalhes-observacoes').innerHTML = `<i class="fas fa-sticky-note"></i> <span>${p.observacoes||'Nenhuma observação'}</span>`;

                        btnCancelarDetalhes.disabled = ['cancelado', 'finalizado'].includes(p.status);
                        cancelamentoForm.classList.remove('active');
                        botoesPrincipais.style.display = 'flex';
                        motivoCancelamento.value = '';
                        modalDetalhes.classList.add('active');
                    }
                });
                calendar.render();
            };

            const buscarHorariosDisponiveis = async (data, servicoId, selecionar = null) => {
                const sel = document.getElementById('horarios');
                const fim = document.getElementById('hora_final');
                sel.disabled = true;
                sel.innerHTML = '<option>Carregando...</option>';

                try {
                    const r = await fetch(`../Calendario/get_horarios_disponiveis.php?data=${data}&servico_id=${servicoId}`);
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    const horarios = await r.json();

                    sel.innerHTML = '';
                    if (!Array.isArray(horarios) || horarios.length === 0) {
                        sel.innerHTML = '<option value="">Nenhum horário disponível</option>';
                        fim.value = '';
                        showNotification('Nenhum horário disponível para esta data.', 'error');
                    } else {
                        const dur = servicoSelect.options[servicoSelect.selectedIndex]?.dataset.duracao || 30;
                        horarios.forEach(h => {
                            const opt = document.createElement('option');
                            opt.value = h.inicio;
                            opt.textContent = `${h.inicio.substring(0,5)} - ${h.final.substring(0,5)}`;
                            opt.dataset.horaFinal = h.final || calcularHoraFinalCliente(h.inicio, dur);
                            if (selecionar && h.inicio === selecionar) {
                                opt.selected = true;
                                fim.value = opt.dataset.horaFinal;
                            }
                            sel.appendChild(opt);
                        });
                        sel.disabled = false;
                        if (!selecionar && horarios[0]) {
                            sel.value = horarios[0].inicio;
                            fim.value = horarios[0].final || calcularHoraFinalCliente(horarios[0].inicio, dur);
                        }
                    }
                    verificarFormulario();
                } catch (e) {
                    console.error(e);
                    sel.innerHTML = '<option value="">Erro ao carregar</option>';
                    fim.value = '';
                    showNotification('Erro ao carregar horários.', 'error');
                }
            };

            const verificarFormulario = () => {
                const ok = document.getElementById('animal_id')?.value &&
                    servicoSelect?.value &&
                    dataInput?.value &&
                    document.getElementById('horarios')?.value &&
                    document.getElementById('hora_final')?.value;
                if (btnAgendar) btnAgendar.disabled = !ok;
            };

            // ✅ CORREÇÃO APLICADA: Tratamento adequado de erros HTTP
            formNovo?.addEventListener('submit', async e => {
                e.preventDefault();
                const editing = formNovo.classList.contains('editing');
                const fd = new FormData(formNovo);

                const inicio = document.getElementById('horarios').value;
                const dur = servicoSelect.options[servicoSelect.selectedIndex]?.dataset.duracao || 30;
                const fim = document.getElementById('hora_final');
                if (!fim.value && inicio) fim.value = calcularHoraFinalCliente(inicio, dur);

                btnAgendar.disabled = true;
                btnAgendar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

                try {
                    const r = await fetch('Calendario.php', {
                        method: 'POST',
                        body: fd
                    });
                    
                    // ✅ CORREÇÃO: Ler o JSON antes de verificar o status
                    // Isso permite que mensagens de erro detalhadas do servidor sejam exibidas
                    const res = await r.json();
                    
                    // ✅ CORREÇÃO: Verificar sucesso depois de ler a resposta
                    if (r.ok && res.success) {
                        showNotification(res.message || (editing ? 'Atualizado!' : 'Agendado!'), 'success');
                        if (editing) limparModoEdicao();
                        formNovo.reset();
                        document.querySelectorAll('.animal-card').forEach(c => c.classList.remove('selected'));
                        document.getElementById('animal_id').value = '';
                        calendar.refetchEvents();
                        document.getElementById('hora_final').value = '';
                        document.getElementById('preco-info').classList.add('hidden');
                        verificarFormulario();
                    } else {
                        // ✅ AGORA MOSTRA A MENSAGEM ESPECÍFICA DO SERVIDOR
                        // Exemplo: "Você já possui um agendamento de Consulta para Rex nesta data..."
                        showNotification(res.error || res.message || 'Erro ao processar agendamento', 'error');
                    }
                } catch (err) {
                    // ✅ CORREÇÃO: Mensagem genérica apenas se falhar o parse do JSON
                    console.error('Erro detalhado:', err);
                    showNotification('Erro ao processar solicitação. Por favor, tente novamente.', 'error');
                } finally {
                    btnAgendar.disabled = false;
                    btnAgendar.innerHTML = editing ? '<i class="fas fa-save"></i> Salvar Alterações' : '<i class="fas fa-calendar-check"></i> Agendar';
                }
            });

            servicoSelect?.addEventListener('change', function() {
                atualizarIconeSelect();
                if (dataSelecionada && this.value) buscarHorariosDisponiveis(dataSelecionada, this.value);
                else {
                    const h = document.getElementById('horarios');
                    h.innerHTML = '<option value="">Selecione uma data</option>';
                    h.disabled = true;
                    document.getElementById('hora_final').value = '';
                }
                atualizarPreco();
                verificarFormulario();
            });

            document.getElementById('horarios')?.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                const fim = document.getElementById('hora_final');
                const dur = servicoSelect.options[servicoSelect.selectedIndex]?.dataset.duracao || 30;
                fim.value = opt?.dataset.horaFinal || calcularHoraFinalCliente(this.value, dur);
                verificarFormulario();
            });

            dataInput?.addEventListener('change', function() {
                dataSelecionada = this.value;
                isFeriado = diasIndisponiveis.feriados?.some(f => f.data === dataSelecionada);
                atualizarPreco();
                if (servicoSelect.value) buscarHorariosDisponiveis(dataSelecionada, servicoSelect.value);
                verificarFormulario();
            });

            btnEditar?.addEventListener('click', () => {
                if (eventoSelecionado) {
                    modalDetalhes.classList.remove('active');
                    abrirEdicaoNoFormulario(eventoSelecionado);
                }
            });

            btnCancelarDetalhes?.addEventListener('click', function() {
                if (!eventoSelecionado) return;
                cancelamentoForm.classList.add('active');
                botoesPrincipais.style.display = 'none';
                motivoCancelamento.focus();
            });

            btnCancelarFormCancelamento?.addEventListener('click', function() {
                cancelamentoForm.classList.remove('active');
                botoesPrincipais.style.display = 'flex';
                motivoCancelamento.value = '';
            });

            btnConfirmarCancelamento?.addEventListener('click', async function() {
                if (!eventoSelecionado) return;

                const motivo = motivoCancelamento.value.trim();
                if (!motivo) {
                    showNotification('Por favor, informe o motivo do cancelamento.', 'error');
                    motivoCancelamento.focus();
                    return;
                }

                if (!confirm('Tem certeza que deseja cancelar este agendamento?')) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelando...';

                try {
                    const r = await fetch('Calendario.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            id: eventoSelecionado.id,
                            acao: 'cancelar',
                            motivo_cancelamento: motivo
                        })
                    });
                    const res = await r.json();
                    if (res.success) {
                        showNotification(res.message || 'Agendamento cancelado com sucesso!', 'success');
                        modalDetalhes.classList.remove('active');
                        cancelamentoForm.classList.remove('active');
                        botoesPrincipais.style.display = 'flex';
                        motivoCancelamento.value = '';
                        calendar.refetchEvents();
                    } else {
                        showNotification('Erro: ' + (res.error || 'Erro ao cancelar agendamento'), 'error');
                    }
                } catch (e) {
                    showNotification('Erro: ' + e.message, 'error');
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-check"></i> Confirmar Cancelamento';
                }
            });

            closeModalDetalhes.forEach(b => b.addEventListener('click', () => {
                modalDetalhes.classList.remove('active');
                cancelamentoForm.classList.remove('active');
                botoesPrincipais.style.display = 'flex';
                motivoCancelamento.value = '';
                eventoSelecionado = null;
            }));

            window.addEventListener('click', e => {
                if (e.target === modalDetalhes) {
                    modalDetalhes.classList.remove('active');
                    cancelamentoForm.classList.remove('active');
                    botoesPrincipais.style.display = 'flex';
                    motivoCancelamento.value = '';
                    eventoSelecionado = null;
                }
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    modalDetalhes.classList.remove('active');
                    cancelamentoForm.classList.remove('active');
                    botoesPrincipais.style.display = 'flex';
                    motivoCancelamento.value = '';
                    eventoSelecionado = null;
                }
            });

            document.querySelectorAll('.animal-card').forEach(c => c.addEventListener('click', () => {
                document.getElementById('animal_id').value = c.dataset.id;
                document.querySelectorAll('.animal-card').forEach(x => x.classList.remove('selected'));
                c.classList.add('selected');
                verificarFormulario();
            }));

            const abrirEdicaoNoFormulario = async ev => {
                const p = ev.extendedProps || {};
                const id = ev.id;
                agendamentoEditando = id;

                if (p.animal) document.querySelectorAll('.animal-card').forEach(c => {
                    if (c.querySelector('.animal-name')?.textContent?.trim() === p.animal) c.click();
                });

                if (p.servico) {
                    for (let i = 0; i < servicoSelect.options.length; i++) {
                        if (servicoSelect.options[i].textContent.includes(p.servico)) {
                            servicoSelect.selectedIndex = i;
                            break;
                        }
                    }
                    servicoSelect.dispatchEvent(new Event('change'));
                }

                const ds = formatarDataLocal(ev.start);
                dataSelecionada = ds;
                dataInput.value = ds;

                const inicio = ev.start.toTimeString().split(' ')[0].substring(0, 5);
                if (servicoSelect.value) {
                    await buscarHorariosDisponiveis(ds, servicoSelect.value, inicio);
                    const dur = servicoSelect.options[servicoSelect.selectedIndex]?.dataset.duracao;
                    document.getElementById('hora_final').value = p.hora_final || calcularHoraFinalCliente(inicio, dur);
                }

                const txt = formNovo.querySelector('[name="observacoes"]');
                if (txt) txt.value = p.observacoes || '';

                let inp = formNovo.querySelector('input[name="id"]');
                if (!inp) {
                    inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'id';
                    formNovo.appendChild(inp);
                }
                inp.value = id;

                btnAgendar.innerHTML = '<i class="fas fa-save"></i> Salvar Alterações';
                formNovo.classList.add('editing');
                formNovo.scrollIntoView({behavior: 'smooth'});
                verificarFormulario();
            };

            const limparModoEdicao = () => {
                agendamentoEditando = null;
                const inp = formNovo.querySelector('input[name="id"]');
                if (inp) inp.remove();
                btnAgendar.innerHTML = '<i class="fas fa-calendar-check"></i> Agendar';
                formNovo.classList.remove('editing');
                document.getElementById('hora_final').value = '';
                verificarFormulario();
            };

            carregarDiasIndisponiveis().then(() => {
                inicializarCalendario();
                atualizarIconeSelect();
            });

            const url = new URLSearchParams(window.location.search);
            const msg = url.get('msg'), tipo = url.get('tipo');
            if (msg) {
                showNotification(decodeURIComponent(msg), tipo || 'success');
                window.history.replaceState({}, '', window.location.pathname);
            }
        });
    </script>
</body>
</html>