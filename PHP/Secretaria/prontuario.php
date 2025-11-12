<?php
// =================== INICIAR SESSÃO PRIMEIRO ===================
session_start();

// Verifica se usuário está logado e é Secretaria ou Veterinário
if (!isset($_SESSION['id']) || !in_array($_SESSION['tipo_usuario'], ['Secretaria', 'Veterinario'])) {
    header("Location: ../index.php");
    exit();
}

// =================== INCLUIR CONEXÃO ===================
include '../conexao.php';

// Enable PDO error reporting for debugging
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ====== AJAX - DETALHES ======
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.consulta_id, p.observacoes, p.data_registro,
                   c.data_consulta,
                   a.nome AS animal_nome,
                   u.nome AS cliente_nome,
                   v.nome AS veterinario_nome,
                   s.nome AS servico_nome
            FROM Prontuarios p
            INNER JOIN Consultas c ON p.consulta_id = c.id
            INNER JOIN Agendamentos ag ON c.agendamento_id = ag.id
            INNER JOIN Servicos s ON ag.servico_id = s.id
            INNER JOIN Animais a ON c.animal_id = a.id
            INNER JOIN Usuarios u ON a.usuario_id = u.id
            LEFT JOIN Usuarios v ON c.veterinario_id = v.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);          // linha 199
        $prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prontuario) {
            $prontuario['data_consulta_formatada'] = date('d/m/Y H:i', strtotime($prontuario['data_consulta']));
            $prontuario['data_registro_formatada'] = date('d/m/Y H:i', strtotime($prontuario['data_registro']));
            $prontuario['observacoes']   = htmlspecialchars($prontuario['observacoes']);
            $prontuario['animal_nome']   = htmlspecialchars($prontuario['animal_nome']);
            $prontuario['cliente_nome']  = htmlspecialchars($prontuario['cliente_nome']);
            $prontuario['veterinario_nome'] = htmlspecialchars($prontuario['veterinario_nome'] ?? 'N/A');
            $prontuario['servico_nome']  = htmlspecialchars($prontuario['servico_nome'] ?? 'N/A');

            echo json_encode(['success' => true, 'prontuario' => $prontuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Prontuário não encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ====== CREATE ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $consulta_id = intval($_POST['consulta_id']);
        $observacoes = trim($_POST['observacoes']);

        if (empty($consulta_id) || empty($observacoes)) {
            header("Location: prontuario.php?error=Campos obrigatórios não preenchidos");
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM Consultas WHERE id = ?");
        $stmt->execute([$consulta_id]);
        if (!$stmt->fetch()) {
            header("Location: prontuario.php?error=Consulta não encontrada");
            exit;
        }

        $sql = "INSERT INTO Prontuarios (consulta_id, observacoes, data_registro) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$consulta_id, $observacoes]);

        header("Location: prontuario.php?success=Prontuário criado com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: prontuario.php?error=Erro ao criar prontuário: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== UPDATE ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = intval($_POST['id']);
        $observacoes = trim($_POST['observacoes']);

        if (empty($id) || empty($observacoes)) {
            header("Location: prontuario.php?error=Campos obrigatórios não preenchidos");
            exit;
        }

        $sql = "UPDATE Prontuarios SET observacoes = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$observacoes, $id]);

        header("Location: prontuario.php?success=Prontuário atualizado com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: prontuario.php?error=Erro ao atualizar prontuário: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== DELETE ======
if (isset($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);
        $pdo->prepare("DELETE FROM Prontuarios WHERE id=?")->execute([$id]);
        header("Location: prontuario.php?success=Prontuário excluído com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: prontuario.php?error=Erro ao excluir prontuário: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== FILTROS ======
$where = [];
$params = [];

if (!empty(trim($_GET['animal'] ?? ''))) {
    $where[] = "a.nome LIKE :animal";
    $params[':animal'] = '%' . trim($_GET['animal']) . '%';
}
if (!empty(trim($_GET['cliente'] ?? ''))) {
    $where[] = "u.nome LIKE :cliente";
    $params[':cliente'] = '%' . trim($_GET['cliente']) . '%';
}
if (!empty(trim($_GET['veterinario'] ?? ''))) {
    $where[] = "v.nome LIKE :veterinario";
    $params[':veterinario'] = '%' . trim($_GET['veterinario']) . '%';
}
if (!empty($_GET['data_inicio'])) {
    $where[] = "DATE(p.data_registro) >= :data_inicio";
    $params[':data_inicio'] = $_GET['data_inicio'];
}
if (!empty($_GET['data_fim'])) {
    $where[] = "DATE(p.data_registro) <= :data_fim";
    $params[':data_fim'] = $_GET['data_fim'];
}

// ====== PAGINAÇÃO ======
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$sql_count = "SELECT COUNT(*) AS total FROM Prontuarios p
              INNER JOIN Consultas c ON p.consulta_id = c.id
              INNER JOIN Agendamentos ag ON c.agendamento_id = ag.id
              INNER JOIN Animais a ON c.animal_id = a.id
              INNER JOIN Usuarios u ON a.usuario_id = u.id
              LEFT JOIN Usuarios v ON c.veterinario_id = v.id";
if ($where) $sql_count .= " WHERE " . implode(' AND ', $where);
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

// ====== READ (LISTAGEM) ======
$sql = "SELECT p.id, p.consulta_id, p.observacoes, p.data_registro,
               c.data_consulta,
               a.id AS animal_id, a.nome AS animal_nome,
               u.nome AS cliente_nome,
               v.nome AS veterinario_nome,
               s.nome AS servico_nome
        FROM Prontuarios p
        INNER JOIN Consultas c ON p.consulta_id = c.id
        INNER JOIN Agendamentos ag ON c.agendamento_id = ag.id
        INNER JOIN Servicos s ON ag.servico_id = s.id
        INNER JOIN Animais a ON c.animal_id = a.id
        INNER JOIN Usuarios u ON a.usuario_id = u.id
        LEFT JOIN Usuarios v ON c.veterinario_id = v.id";

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY p.data_registro DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$prontuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== CONSULTAS DISPONÍVEIS PARA CRIAR PRONTUÁRIO ======
$consultas_disponiveis = $pdo->query("
    SELECT c.id, c.data_consulta,
           a.nome AS animal_nome,
           u.nome AS cliente_nome,
           s.nome AS servico_nome
    FROM Consultas c
    INNER JOIN Agendamentos ag ON c.agendamento_id = ag.id
    INNER JOIN Servicos s ON ag.servico_id = s.id
    INNER JOIN Animais a ON c.animal_id = a.id
    INNER JOIN Usuarios u ON a.usuario_id = u.id
    LEFT JOIN Prontuarios p ON c.id = p.consulta_id
    WHERE p.id IS NULL AND ag.status = 'confirmado'
    ORDER BY c.data_consulta DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ====== ESTATÍSTICAS ======
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN DATE(data_registro) = CURDATE() THEN 1 END) AS hoje,
        COUNT(CASE WHEN WEEK(data_registro) = WEEK(CURDATE()) THEN 1 END) AS semana,
        COUNT(CASE WHEN MONTH(data_registro) = MONTH(CURDATE()) THEN 1 END) AS mes
    FROM Prontuarios
")->fetch(PDO::FETCH_ASSOC);

// =================== DEFINIR TÍTULO ===================
$paginaTitulo = "Gerenciamento de Prontuários";

// =================== INCLUIR HEADER ===================
include "header.php";

function getInitials($nome) {
    $words = explode(' ', trim($nome));
    if (count($words) >= 2) return strtoupper(substr($words[0],0,1).substr($words[1],0,1));
    return strtoupper(substr($nome,0,2));
}
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
        :root{--primary:#6366f1;--primary-dark:#4f46e5;--primary-light:#818cf8;--secondary:#8b5cf6;--success:#10b981;--danger:#ef4444;--warning:#f59e0b;--info:#3b82f6;--dark:#0f172a;--dark-light:#1e293b;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-300:#cbd5e1;--gray-400:#94a3b8;--gray-500:#64748b;--gray-600:#475569;--gray-700:#334155;--gray-800:#1e293b;--gray-900:#0f172a;--white:#fff;--border-radius:12px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,.05);--shadow:0 1px 3px 0 rgba(0,0,0,.1),0 1px 2px -1px rgba(0,0,0,.1);--shadow-md:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -2px rgba(0,0,0,.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);--shadow-xl:0 20px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1);}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:#f8f9fa;color:var(--gray-900);min-height:100vh;}
        .main-content{margin-left:100px;padding:32px 16px 32px 32px;min-height:100vh;}
        .container{max-width:1200px;margin:0 auto;}
        .page-header{background:var(--white);border-radius:var(--border-radius);padding:24px 32px;margin-bottom:32px;box-shadow:var(--shadow-lg);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
        .page-title h1{font-size:28px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:12px;}
        .page-title h1 i{color:var(--primary);}
        .badge-count{background:linear-gradient(135deg,var(--primary),var(--secondary));color:var(--white);padding:6px 14px;border-radius:20px;font-size:14px;font-weight:600;}
        .message{padding:12px 16px;border-radius:8px;font-size:14px;font-weight:500;margin-bottom:24px;display:flex;align-items:center;gap:8px;}
        .message.success{background:rgba(16,185,129,.1);color:var(--success);border:1px solid rgba(16,185,129,.2);}
        .message.error{background:rgba(239,68,68,.1);color:var(--danger);border:1px solid rgba(239,68,68,.2);}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:24px;margin-bottom:32px;}
        .stat-card{background:var(--white);border-radius:var(--border-radius);padding:24px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;transition:all .3s;}
        .stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-xl);}
        .stat-card::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;}
        .stat-card.primary::before{background:linear-gradient(90deg,var(--primary),var(--secondary));}
        .stat-card.success::before{background:var(--success);}
        .stat-card.warning::before{background:var(--warning);}
        .stat-card.info::before{background:var(--info);}
        .stat-content{display:flex;justify-content:space-between;align-items:center;}
        .stat-info h3{font-size:13px;font-weight:600;color:var(--gray-600);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;}
        .stat-info p{font-size:32px;font-weight:700;color:var(--gray-900);}
        .stat-icon{width:56px;height:56px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;}
        .stat-card.primary .stat-icon{background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(139,92,246,.1));color:var(--primary);}
        .stat-card.success .stat-icon{background:rgba(16,185,129,.1);color:var(--success);}
        .stat-card.warning .stat-icon{background:rgba(245,158,11,.1);color:var(--warning);}
        .stat-card.info .stat-icon{background:rgba(59,130,246,.1);color:var(--info);}
        .filters-card{background:var(--white);border-radius:var(--border-radius);padding:24px 32px;margin-bottom:32px;box-shadow:var(--shadow-lg);}
        .filters-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
        .filters-header h2{font-size:18px;font-weight:600;color:var(--gray-900);display:flex;align-items:center;gap:8px;}
        .filters-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;align-items:end;}
        .form-group{display:flex;flex-direction:column;}
        .form-group label{font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
        .form-group input,.form-group select,.form-group textarea{padding:10px 14px;border:2px solid var(--gray-200);border-radius:8px;font-size:14px;transition:all .2s;background:var(--white);color:var(--gray-900);}
        .form-group textarea{resize:vertical;min-height:120px;}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(99,102,241,.1);}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;justify-content:center;gap:8px;}
        .btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--secondary));color:var(--white);}
        .btn-success{background:var(--success);color:var(--white);}
        .btn-danger{background:var(--danger);color:var(--white);}
        .btn-secondary{background:var(--gray-200);color:var(--gray-700);}
        .btn-sm{padding:6px 12px;font-size:13px;}
        .table-card{background:var(--white);border-radius:var(--border-radius);box-shadow:var(--shadow-lg);overflow:hidden;margin-bottom:32px;}
        .table-header{padding:20px 32px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
        .table-header h2{font-size:18px;font-weight:600;color:var(--gray-900);display:flex;align-items:center;gap:8px;}
        .table-container{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;}
        thead{background:var(--gray-50);}
        th{padding:16px 20px;text-align:left;font-size:12px;font-weight:700;color:var(--gray-700);text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
        td{padding:16px 20px;border-bottom:1px solid var(--gray-200);font-size:14px;color:var(--gray-900);}
        tbody tr{transition:all .2s;}
        tbody tr:hover{background:var(--gray-50);}
        .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
        .badge.info{background:rgba(59,130,246,.1);color:var(--info);}
        .action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
        .pagination{padding:20px 32px;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;background:var(--white);border-radius:0 0 var(--border-radius) var(--border-radius);}
        .pagination-info{font-size:14px;color:var(--gray-600);}
        .pagination-buttons{display:flex;gap:8px;}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:2000;justify-content:center;align-items:center;padding:20px;}
        .modal.active{display:flex;}
        .modal-content{background:var(--white);border-radius:var(--border-radius);box-shadow:var(--shadow-xl);max-width:700px;width:100%;max-height:90vh;overflow-y:auto;}
        .modal-header{padding:24px 32px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--white);z-index:10;}
        .modal-header h3{font-size:20px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:10px;}
        .modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--gray-100);color:var(--gray-600);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;font-size:16px;}
        .modal-close:hover{background:var(--gray-200);color:var(--gray-900);}
        .modal-body{padding:32px;}
        .modal-footer{padding:24px 32px;border-top:1px solid var(--gray-200);display:flex;justify-content:flex-end;gap:12px;position:sticky;bottom:0;background:var(--white);}
        .empty-state{padding:60px;text-align:center;color:var(--gray-500);}
        .empty-state i{font-size:48px;margin-bottom:16px;opacity:.3;}
        .empty-state p{font-size:16px;}
        .info-card{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:16px;}
        .info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
        .info-item{display:flex;flex-direction:column;gap:4px;}
        .info-label{font-size:12px;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:.5px;}
        .info-value{font-size:14px;font-weight:500;color:var(--gray-900);}
        @media(max-width:1024px){.main-content{margin-left:0;padding:24px 16px;}}
        @media(max-width:768px){.main-content{padding:16px 12px;}.page-header{padding:20px;}.page-title h1{font-size:22px;}.filters-form{grid-template-columns:1fr;}.stats-grid{grid-template-columns:1fr;}.info-grid{grid-template-columns:1fr;}.table-container{overflow-x:scroll;}.modal-content{max-width:95%;}}
        @media(max-width:480px){.page-header{padding:16px;}.btn{padding:8px 16px;font-size:13px;}}
    </style>
</head>
<body>
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>Gerenciamento de Prontuários</h1>
                <span class="badge-count"><?= $total_registros ?> prontuários</span>
            </div>
            <button class="btn btn-primary" onclick="abrirModal('modalAdd')">
                Novo Prontuário
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success"> <?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="message error"> <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-content">
                    <div class="stat-info"><h3>Total de Prontuários</h3><p><?= $stats['total'] ?></p></div>
                    <div class="stat-icon"></div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-content">
                    <div class="stat-info"><h3>Hoje</h3><p><?= $stats['hoje'] ?></p></div>
                    <div class="stat-icon"></div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-content">
                    <div class="stat-info"><h3>Esta Semana</h3><p><?= $stats['semana'] ?></p></div>
                    <div class="stat-icon"></div>
                </div>
            </div>
            <div class="stat-card info">
                <div class="stat-content">
                    <div class="stat-info"><h3>Este Mês</h3><p><?= $stats['mes'] ?></p></div>
                    <div class="stat-icon"></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-header">
                <h2>Filtros de Pesquisa</h2>
            </div>
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label>Animal</label>
                    <input type="text" name="animal" placeholder="Nome do animal" value="<?= htmlspecialchars($_GET['animal'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Cliente</label>
                    <input type="text" name="cliente" placeholder="Nome do cliente" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Veterinário</label>
                    <input type="text" name="veterinario" placeholder="Nome do veterinário" value="<?= htmlspecialchars($_GET['veterinario'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='prontuario.php'">Limpar</button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <?php if (!empty($prontuarios)): ?>
            <div class="table-card">
                <div class="table-header">
                    <h2>Lista de Prontuários</h2>
                    <span class="pagination-info">
                        Exibindo <?= $offset + 1 ?> a <?= min($offset + $itens_por_pagina, $total_registros) ?> de <?= $total_registros ?>
                    </span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Animal</th>
                                <th>Cliente</th>
                                <th>Veterinário</th>
                                <th>Data Consulta</th>
                                <th>Data Registro</th>
                                <th>Serviço</th>
                                <th style="text-align:center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prontuarios as $row): ?>
                                <tr>
                                    <td><strong>#<?= (int)$row['id'] ?></strong></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span><?= htmlspecialchars($row['animal_nome']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['cliente_nome']) ?></td>
                                    <td><?= htmlspecialchars($row['veterinario_nome'] ?? 'N/A') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['data_consulta'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['data_registro'])) ?></td>
                                    <td>
                                        <span class="badge info">
                                            <?= htmlspecialchars($row['servico_nome']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="abrirDetalhes(<?= (int)$row['id'] ?>)" title="Ver detalhes">
                                                Ver
                                            </button>
                                            <button type="button" class="btn btn-success btn-sm" onclick="abrirEdicao(<?= (int)$row['id'] ?>, <?= (int)$row['consulta_id'] ?>, '<?= addslashes($row['observacoes']) ?>')" title="Editar">
                                                Editar
                                            </button>
                                            <a href="?delete=<?= (int)$row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este prontuário?')" title="Excluir">
                                                Excluir
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <div class="pagination-info">Página <?= $pagina_atual ?> de <?= $total_paginas ?></div>
                    <div class="pagination-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>'" <?= $pagina_atual <= 1 ? 'disabled' : '' ?>>
                            Anterior
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>'" <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>>
                            Próximo
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="empty-state">
                    <p>Nenhum prontuário encontrado com os filtros aplicados.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Adicionar -->
<div class="modal" id="modalAdd">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Novo Prontuário</h3>
            <button class="modal-close" onclick="fecharModal('modalAdd')">X</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <?php if (!empty($consultas_disponiveis)): ?>
                    <div class="form-group">
                        <label>Selecionar Consulta *</label>
                        <select name="consulta_id" id="consulta_select" required onchange="atualizarInfoConsulta()">
                            <option value="">Selecione uma consulta</option>
                            <?php foreach ($consultas_disponiveis as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                        data-animal="<?= htmlspecialchars($c['animal_nome']) ?>"
                                        data-cliente="<?= htmlspecialchars($c['cliente_nome']) ?>"
                                        data-servico="<?= htmlspecialchars($c['servico_nome']) ?>"
                                        data-data="<?= date('d/m/Y H:i', strtotime($c['data_consulta'])) ?>">
                                    #<?= (int)$c['id'] ?> - <?= htmlspecialchars($c['animal_nome']) ?> (<?= htmlspecialchars($c['cliente_nome']) ?>) - <?= date('d/m/Y H:i', strtotime($c['data_consulta'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="info_consulta" style="display:none;">
                        <div class="info-card">
                            <h4 style="font-size:14px;font-weight:600;color:var(--gray-700);margin-bottom:12px;">
                                Informações da Consulta
                            </h4>
                            <div class="info-grid">
                                <div class="info-item"><span class="info-label">Animal</span><span class="info-value" id="info_animal">-</span></div>
                                <div class="info-item"><span class="info-label">Cliente</span><span class="info-value" id="info_cliente">-</span></div>
                                <div class="info-item"><span class="info-label">Serviço</span><span class="info-value" id="info_servico">-</span></div>
                                <div class="info-item"><span class="info-label">Data/Hora</span><span class="info-value" id="info_data">-</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observações *</label>
                        <textarea name="observacoes" placeholder="Descreva os detalhes da consulta, diagnóstico, prescrições, etc." required></textarea>
                    </div>
                <?php else: ?>
                    <div class="message error">
                        Não há consultas confirmadas sem prontuário. Crie uma consulta primeiro.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAdd')">Cancelar</button>
                <?php if (!empty($consultas_disponiveis)): ?>
                    <button type="submit" class="btn btn-success">Salvar Prontuário</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal" id="modalEdit">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Editar Prontuário</h3>
            <button class="modal-close" onclick="fecharModal('modalEdit')">X</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="consulta_id" id="edit_consulta_id">
                <div class="form-group">
                    <label>Observações *</label>
                    <textarea name="observacoes" id="edit_observacoes" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEdit')">Cancelar</button>
                <button type="submit" class="btn btn-success">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalhes -->
<div class="modal" id="modalDetalhes">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Detalhes do Prontuário</h3>
            <button class="modal-close" onclick="fecharModal('modalDetalhes')">X</button>
        </div>
        <div class="modal-body" id="detalhes_content">
            <div style="text-align:center;padding:40px;color:var(--gray-500);">
                <p>Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script>
    function abrirModal(id){document.getElementById(id).classList.add('active');document.body.style.overflow='hidden';}
    function fecharModal(id){document.getElementById(id).classList.remove('active');document.body.style.overflow='auto';}
    function abrirEdicao(id,consulta_id,observacoes){
        document.getElementById('edit_id').value=id;
        document.getElementById('edit_consulta_id').value=consulta_id;
        document.getElementById('edit_observacoes').value=observacoes;
        abrirModal('modalEdit');
    }
    function atualizarInfoConsulta(){
        const select=document.getElementById('consulta_select');
        const option=select.options[select.selectedIndex];
        const infoDiv=document.getElementById('info_consulta');
        if(option.value){
            document.getElementById('info_animal').textContent=option.dataset.animal;
            document.getElementById('info_cliente').textContent=option.dataset.cliente;
            document.getElementById('info_servico').textContent=option.dataset.servico;
            document.getElementById('info_data').textContent=option.dataset.data;
            infoDiv.style.display='block';
        }else infoDiv.style.display='none';
    }
    function abrirDetalhes(id){
        abrirModal('modalDetalhes');
        fetch('?ajax=detalhes&id='+id)
            .then(r=>r.json())
            .then(data=>{
                if(data.success){
                    const p=data.prontuario;
                    document.getElementById('detalhes_content').innerHTML=`
                        <div class="info-card">
                            <h4 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:16px;">
                                Informações Gerais
                            </h4>
                            <div class="info-grid">
                                <div class="info-item"><span class="info-label">ID Prontuário</span><span class="info-value">#${p.id}</span></div>
                                <div class="info-item"><span class="info-label">ID Consulta</span><span class="info-value">#${p.consulta_id}</span></div>
                                <div class="info-item"><span class="info-label">Animal</span><span class="info-value">${p.animal_nome}</span></div>
                                <div class="info-item"><span class="info-label">Cliente</span><span class="info-value">${p.cliente_nome}</span></div>
                                <div class="info-item"><span class="info-label">Veterinário</span><span class="info-value">${p.veterinario_nome}</span></div>
                                <div class="info-item"><span class="info-label">Serviço</span><span class="info-value">${p.servico_nome}</span></div>
                                <div class="info-item"><span class="info-label">Data Consulta</span><span class="info-value">${p.data_consulta_formatada}</span></div>
                                <div class="info-item"><span class="info-label">Data Registro</span><span class="info-value">${p.data_registro_formatada}</span></div>
                            </div>
                        </div>
                        <div style="margin-top:24px;">
                            <h4 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:12px;">
                                Observações
                            </h4>
                            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:16px;line-height:1.6;white-space:pre-wrap;">
                                ${p.observacoes}
                            </div>
                        </div>`;
                }
            })
            .catch(()=>{document.getElementById('detalhes_content').innerHTML='<div class="message error">Erro ao carregar detalhes</div>';});
    }
    window.addEventListener('click',e=>{if(e.target.classList.contains('modal')){e.target.classList.remove('active');document.body.style.overflow='auto';}});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.modal').forEach(m=>m.classList.remove('active'));document.body.style.overflow='auto';}});
    setTimeout(()=>{document.querySelectorAll('.message').forEach(m=>{m.style.transition='opacity .3s';m.style.opacity='0';setTimeout(()=>m.remove(),300);});},5000);
</script>

<?php include 'footer.php'; ?>
</body>
</html>