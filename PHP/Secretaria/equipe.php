<?php
// =================== INICIAR SESSÃO PRIMEIRO ===================
session_start();

// Verifica se usuário está logado e é Secretaria
if (!isset($_SESSION['id']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    header("Location: ../index.php");
    exit();
}

// =================== INCLUIR CONEXÃO ===================
include '../conexao.php';

// Enable PDO error reporting for debugging
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ====== CREATE ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $nome = trim($_POST['nome']);
        $profissao = trim($_POST['profissao']);
        $descricao = trim($_POST['descricao']);

        if (empty($nome) || empty($profissao)) {
            header("Location: equipe.php?error=Campos obrigatórios não preenchidos");
            exit;
        }

        $foto = null;
        if (!empty($_FILES['foto']['name'])) {
            $targetDir = "../../assets/uploads/equipe/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $foto = time() . "_" . basename($_FILES["foto"]["name"]);
            $targetFile = $targetDir . $foto;
            
            if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $targetFile)) {
                header("Location: equipe.php?error=Erro ao fazer upload da foto");
                exit;
            }
        }

        // Garantir que o usuario_id existe
        $usuario_id = $_SESSION['id'] ?? null;

        if ($usuario_id) {
            $sql = "INSERT INTO equipe (nome, usuario_id, profissao, descricao, foto) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $usuario_id, $profissao, $descricao, $foto]);
        }

        header("Location: equipe.php?success=Membro adicionado com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: equipe.php?error=Erro ao adicionar membro: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== UPDATE ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = intval($_POST['id']);
        $nome = trim($_POST['nome']);
        $profissao = trim($_POST['profissao']);
        $descricao = trim($_POST['descricao']);

        if (empty($nome) || empty($profissao)) {
            header("Location: equipe.php?error=Campos obrigatórios não preenchidos");
            exit;
        }

        // Buscar foto atual
        $stmt = $pdo->prepare("SELECT foto FROM equipe WHERE id = ?");
        $stmt->execute([$id]);
        $membro = $stmt->fetch(PDO::FETCH_ASSOC);
        $foto = $membro['foto'];

        // Upload nova foto se fornecida
        if (!empty($_FILES['foto']['name'])) {
            $targetDir = "../../assets/uploads/equipe/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Deletar foto antiga
            if ($foto && file_exists($targetDir . $foto)) {
                unlink($targetDir . $foto);
            }

            $foto = time() . "_" . basename($_FILES["foto"]["name"]);
            $targetFile = $targetDir . $foto;
            
            if (!move_uploaded_file($_FILES["foto"]["tmp_name"], $targetFile)) {
                header("Location: equipe.php?error=Erro ao fazer upload da foto");
                exit;
            }
        }

        $sql = "UPDATE equipe SET nome = ?, profissao = ?, descricao = ?, foto = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $profissao, $descricao, $foto, $id]);

        header("Location: equipe.php?success=Membro atualizado com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: equipe.php?error=Erro ao atualizar membro: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== DELETE ======
if (isset($_GET['delete'])) {
    try {
        $id = intval($_GET['delete']);
        
        // Buscar foto para deletar
        $stmt = $pdo->prepare("SELECT foto FROM equipe WHERE id = ?");
        $stmt->execute([$id]);
        $membro = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membro && $membro['foto']) {
            $fotoPath = "../../assets/uploads/equipe/" . $membro['foto'];
            if (file_exists($fotoPath)) {
                unlink($fotoPath);
            }
        }
        
        $pdo->prepare("DELETE FROM equipe WHERE id=?")->execute([$id]);
        header("Location: equipe.php?success=Membro excluído com sucesso");
        exit;
    } catch (PDOException $e) {
        header("Location: equipe.php?error=Erro ao excluir membro: " . urlencode($e->getMessage()));
        exit;
    }
}

// ====== READ ======
$sql = "SELECT id, nome, profissao, descricao, foto FROM equipe ORDER BY id DESC";
$stmt = $pdo->query($sql);
$equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar membros
$total_membros = count($equipe);

// =================== DEFINIR TÍTULO ===================
$paginaTitulo = "Gerenciamento de Equipe";

// =================== INCLUIR HEADER ===================
include "header.php";

// Função para gerar iniciais do nome
function getInitials($nome) {
    $words = explode(' ', trim($nome));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($nome, 0, 2));
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

        .badge-count {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Messages */
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group input,
        .form-group textarea {
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: var(--white);
            color: var(--gray-900);
            font-family: 'Inter', sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group input[type="file"] {
            padding: 8px;
            cursor: pointer;
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
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Team Grid */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .team-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .team-card-header {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .team-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .team-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 48px;
            border: 4px solid white;
        }

        .team-body {
            padding: 24px;
        }

        .team-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .team-subtitle {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .team-text {
            font-size: 14px;
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .team-actions {
            display: flex;
            gap: 8px;
        }

        .empty-state {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 60px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--gray-500);
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
            backdrop-filter: blur(4px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--white);
            z-index: 10;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--gray-100);
            color: var(--gray-600);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 16px;
        }

        .modal-close:hover {
            background: var(--gray-200);
            color: var(--gray-900);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 32px;
        }

        .modal-footer {
            padding: 24px 32px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            position: sticky;
            bottom: 0;
            background: var(--white);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 24px 16px;
            }

            .team-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }

            .team-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                max-width: 95%;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 16px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 13px;
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
                    <h1><i class="fas fa-users"></i> Gerenciamento de Equipe</h1>
                    <span class="badge-count"><?= $total_membros ?> membros</span>
                </div>
                <button class="btn btn-primary" onclick="abrirModal('modalAdd')">
                    <i class="fas fa-plus"></i>
                    Adicionar Membro
                </button>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <!-- Team Grid -->
            <?php if (count($equipe) > 0): ?>
                <div class="team-grid">
                    <?php foreach ($equipe as $row): ?>
                        <div class="team-card">
                            <div class="team-card-header">
                                <?php if ($row['foto'] && file_exists("../../assets/uploads/equipe/" . $row['foto'])): ?>
                                    <img src="../../assets/uploads/equipe/<?= htmlspecialchars($row['foto']) ?>" class="team-img" alt="Foto de <?= htmlspecialchars($row['nome']) ?>">
                                <?php else: ?>
                                    <div class="team-avatar-placeholder">
                                        <?= getInitials($row['nome']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="team-body">
                                <h5 class="team-title"><?= htmlspecialchars($row['nome']) ?></h5>
                                <p class="team-subtitle"><?= htmlspecialchars($row['profissao']) ?></p>
                                <p class="team-text"><?= htmlspecialchars($row['descricao']) ?></p>
                                <div class="team-actions">
                                    <button class="btn btn-success btn-sm" onclick="abrirEdicao(<?= (int) $row['id'] ?>, '<?= addslashes($row['nome']) ?>', '<?= addslashes($row['profissao']) ?>', '<?= addslashes($row['descricao']) ?>', '<?= addslashes($row['foto'] ?? '') ?>')">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <a href="?delete=<?= (int) $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este membro?\n\nEsta ação não pode ser desfeita!')">
                                        <i class="fas fa-trash"></i> Excluir
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>Nenhum membro cadastrado</h3>
                    <p>Adicione o primeiro membro da sua equipe clicando no botão acima</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar -->
    <div class="modal" id="modalAdd">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Adicionar Novo Membro</h3>
                <button class="modal-close" onclick="fecharModal('modalAdd')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nome Completo *</label>
                            <input type="text" name="nome" placeholder="Nome completo" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Profissão *</label>
                            <input type="text" name="profissao" placeholder="Ex: Veterinário" required>
                        </div>
                        <div class="form-group full">
                            <label><i class="fas fa-align-left"></i> Descrição</label>
                            <textarea name="descricao" placeholder="Breve descrição sobre o membro"></textarea>
                        </div>
                        <div class="form-group full">
                            <label><i class="fas fa-image"></i> Foto</label>
                            <input type="file" name="foto" accept="image/*">
                            <small style="color: var(--gray-500); margin-top: 4px;">Formatos aceitos: JPG, PNG, GIF (máx. 5MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAdd')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Membro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal" id="modalEdit">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Editar Membro</h3>
                <button class="modal-close" onclick="fecharModal('modalEdit')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nome Completo *</label>
                            <input type="text" name="nome" id="edit_nome" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Profissão *</label>
                            <input type="text" name="profissao" id="edit_profissao" required>
                        </div>
                        <div class="form-group full">
                            <label><i class="fas fa-align-left"></i> Descrição</label>
                            <textarea name="descricao" id="edit_descricao"></textarea>
                        </div>
                        <div class="form-group full">
                            <label><i class="fas fa-image"></i> Nova Foto (opcional)</label>
                            <input type="file" name="foto" accept="image/*">
                            <small style="color: var(--gray-500); margin-top: 4px;">Deixe em branco para manter a foto atual</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEdit')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funções de Modal
        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function abrirEdicao(id, nome, profissao, descricao, foto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_profissao').value = profissao;
            document.getElementById('edit_descricao').value = descricao;
            abrirModal('modalEdit');
        }

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
                document.body.style.overflow = 'auto';
            }
        });

        // Auto-fechar mensagens de sucesso/erro após 5 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(msg) {
                msg.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    msg.remove();
                }, 300);
            });
        }, 5000);

        // Validação de arquivo
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Verificar tamanho (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('⚠️ O arquivo é muito grande! Tamanho máximo: 5MB');
                        this.value = '';
                        return;
                    }
                    
                    // Verificar tipo
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('⚠️ Formato não permitido! Use: JPG, PNG ou GIF');
                        this.value = '';
                        return;
                    }
                }
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>