<?php
include("../conexao.php");
$paginaTitulo = "Gerenciamento de Usuários";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    $acao = $_POST["acao"];
    $params = [
        ":nome" => trim($_POST["nome"] ?? ''),
        ":cpf" => trim($_POST["cpf"] ?? ''),
        ":telefone" => trim($_POST["telefone"] ?? ''),
        ":email" => trim($_POST["email"] ?? ''),
        ":tipo" => $_POST["tipo_usuario"] ?? '',
        ":genero" => $_POST["genero"] ?? ''
    ];

    try {
        if ($acao === "adicionar") {
            if (empty($params[":nome"]) || empty($params[":cpf"]) || empty($params[":email"]) || empty($params[":tipo"])) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Campos obrigatórios não preenchidos");
                exit;
            }

            // Validação de CPF
            $cpf = preg_replace('/[^0-9]/', '', $params[":cpf"]);
            if (strlen($cpf) != 11) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=CPF inválido");
                exit;
            }

            // Validação de telefone
            if (!empty($params[":telefone"])) {
                $telefone = preg_replace('/[^0-9]/', '', $params[":telefone"]);
                if (strlen($telefone) < 10 || strlen($telefone) > 11) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?error=Telefone inválido");
                    exit;
                }
            }

            // Validação de senha
            $senha = trim($_POST["senha"] ?? '');
            if (empty($senha)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Senha é obrigatória");
                exit;
            }
            if (strlen($senha) < 6) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Senha deve ter no mínimo 6 caracteres");
                exit;
            }

            $sql = "INSERT INTO Usuarios (nome, cpf, telefone, email, senha_hash, tipo_usuario, genero, ativo) 
                    VALUES (:nome, :cpf, :telefone, :email, :senha, :tipo, :genero, :ativo)";
            $stmt = $pdo->prepare($sql);
            $params[":senha"] = password_hash($senha, PASSWORD_DEFAULT);
            $params[":ativo"] = isset($_POST["ativo"]) ? 1 : 0;
            $stmt->execute($params);
        }

        if ($acao === "editar") {
            $params[":id"] = (int) ($_POST["id"] ?? 0);
            if (empty($params[":id"]) || empty($params[":nome"]) || empty($params[":cpf"]) || empty($params[":email"]) || empty($params[":tipo"])) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Campos obrigatórios não preenchidos");
                exit;
            }

            // Validação de CPF
            $cpf = preg_replace('/[^0-9]/', '', $params[":cpf"]);
            if (strlen($cpf) != 11) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=CPF inválido");
                exit;
            }

            // Validação de telefone
            if (!empty($params[":telefone"])) {
                $telefone = preg_replace('/[^0-9]/', '', $params[":telefone"]);
                if (strlen($telefone) < 10 || strlen($telefone) > 11) {
                    header("Location: " . $_SERVER['PHP_SELF'] . "?error=Telefone inválido");
                    exit;
                }
            }

            $sql = "UPDATE Usuarios 
                    SET nome=:nome, cpf=:cpf, telefone=:telefone, email=:email, 
                        tipo_usuario=:tipo, genero=:genero, atualizado_em=NOW()
                    WHERE id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        if ($acao === "deletar") {
            $id = (int) ($_POST["id"] ?? 0);
            if (empty($id)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=ID inválido");
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM Usuarios WHERE id=:id");
            $stmt->execute([":id" => $id]);
        }

        if ($acao === "toggleAtivo") {
            $id = (int) ($_POST["id"] ?? 0);
            if (empty($id)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=ID inválido");
                exit;
            }
            $stmt = $pdo->prepare("UPDATE Usuarios SET ativo = NOT ativo, bloqueado_ate = NULL, atualizado_em=NOW() WHERE id=:id");
            $stmt->execute([":id" => $id]);
        }

        if ($acao === "bloquear") {
            $id = (int) ($_POST["id"] ?? 0);
            $duracao = $_POST["duracao"] ?? "indefinido";
            if (empty($id)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=ID inválido");
                exit;
            }
            if ($duracao === "indefinido") {
                $sql = "UPDATE Usuarios SET ativo = 0, bloqueado_ate = NULL, atualizado_em=NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([":id" => $id]);
            } else {
                $map = [
                    "1h" => "1 HOUR",
                    "24h" => "24 HOUR",
                    "7d" => "7 DAY",
                ];
                $interval = $map[$duracao] ?? "1 HOUR";
                $sql = "UPDATE Usuarios SET ativo = 0, bloqueado_ate = DATE_ADD(NOW(), INTERVAL $interval), atualizado_em=NOW() WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([":id" => $id]);
            }
        }

        if ($acao === "desbloquear") {
            $id = (int) ($_POST["id"] ?? 0);
            if (empty($id)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=ID inválido");
                exit;
            }
            $stmt = $pdo->prepare("UPDATE Usuarios SET ativo = 1, bloqueado_ate = NULL, atualizado_em=NOW() WHERE id=:id");
            $stmt->execute([":id" => $id]);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    } catch (PDOException $e) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Erro no banco de dados: " . urlencode($e->getMessage()));
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'relatorio' && isset($_GET['id'])) {
    $id = (int) ($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT id, nome, email, tipo_usuario, genero, ativo, bloqueado_ate, ultimo_login, criado, atualizado_em 
                               FROM Usuarios WHERE id = :id");
        $stmt->execute([":id" => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT email_tentado, sucesso, ip_origem, navegador, data_hora 
                               FROM Logs_Acesso WHERE usuario_id = :id 
                               ORDER BY data_hora DESC LIMIT 50");
        $stmt->execute([":id" => $id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo "<p class='error-message'>Usuário não encontrado.</p>";
            exit;
        }
        ?>
        <div class="usuario-details">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">ID</span>
                    <span class="detail-value"><?= (int) $usuario["id"] ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Nome</span>
                    <span class="detail-value"><?= htmlspecialchars($usuario["nome"]) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?= htmlspecialchars($usuario["email"]) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Tipo</span>
                    <span class="detail-value"><?= htmlspecialchars($usuario["tipo_usuario"] ?: 'Não informado') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Gênero</span>
                    <span class="detail-value"><?= htmlspecialchars($usuario["genero"] ?: 'Não informado') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="badge <?= $usuario["ativo"] ? 'success' : 'danger' ?>">
                            <i class="fas fa-circle"></i> <?= $usuario["ativo"] ? "Ativo" : "Inativo" ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fas fa-info-circle"></i> Informações Adicionais</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Bloqueado até</span>
                        <span
                            class="detail-value"><?= $usuario["bloqueado_ate"] ? htmlspecialchars($usuario["bloqueado_ate"]) : "N/A" ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Último login</span>
                        <span
                            class="detail-value"><?= $usuario["ultimo_login"] ? htmlspecialchars($usuario["ultimo_login"]) : "Nunca" ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Criado em</span>
                        <span class="detail-value"><?= htmlspecialchars($usuario["criado"]) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Atualizado em</span>
                        <span class="detail-value"><?= htmlspecialchars($usuario["atualizado_em"] ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($usuario["bloqueado_ate"])): ?>
                <form method="POST" class="form-actions" style="margin-top: 16px;">
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                    <button type="submit" name="acao" value="desbloquear" class="btn btn-success">
                        <i class="fas fa-lock-open"></i> Desbloquear agora
                    </button>
                </form>
            <?php endif; ?>

            <div class="detail-section">
                <h4><i class="fas fa-history"></i> Logs de Acesso (últimos 50)</h4>
                <?php if (!empty($logs)): ?>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email Tentado</th>
                                    <th>Sucesso</th>
                                    <th>IP</th>
                                    <th>Navegador</th>
                                    <th>Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($l["email_tentado"]) ?></td>
                                        <td>
                                            <?php if ($l["sucesso"]): ?>
                                                <span class="badge success"><i class="fas fa-check"></i></span>
                                            <?php else: ?>
                                                <span class="badge danger"><i class="fas fa-times"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($l["ip_origem"]) ?></td>
                                        <td><?= htmlspecialchars($l["navegador"]) ?></td>
                                        <td><?= htmlspecialchars($l["data_hora"]) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Sem logs para este usuário.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    } catch (PDOException $e) {
        echo "<p class='error-message'>Erro ao carregar relatório: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    exit;
}

$where = [];
$params = [];

if (!empty(trim($_GET['nome'] ?? ''))) {
    $where[] = "u.nome LIKE :nome";
    $params[':nome'] = "%" . trim($_GET['nome']) . "%";
}
if (!empty(trim($_GET['cpf'] ?? ''))) {
    $where[] = "u.cpf LIKE :cpf";
    $params[':cpf'] = "%" . trim($_GET['cpf']) . "%";
}
if (!empty(trim($_GET['telefone'] ?? ''))) {
    $where[] = "u.telefone LIKE :telefone";
    $params[':telefone'] = "%" . trim($_GET['telefone']) . "%";
}
if (!empty(trim($_GET['email'] ?? ''))) {
    $where[] = "u.email LIKE :email";
    $params[':email'] = "%" . trim($_GET['email']) . "%";
}
if (!empty($_GET['tipo_usuario'])) {
    $where[] = "u.tipo_usuario = :tipo_usuario";
    $params[':tipo_usuario'] = $_GET['tipo_usuario'];
}
if (!empty($_GET['genero'])) {
    $where[] = "u.genero = :genero";
    $params[':genero'] = $_GET['genero'];
}

$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$sql_count = "SELECT COUNT(*) as total FROM Usuarios u";
if ($where) {
    $sql_count .= " WHERE " . implode(" AND ", $where);
}
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

$sql = "SELECT u.id, u.nome, u.cpf, u.telefone, u.email, u.tipo_usuario, u.genero,
               u.ativo, u.bloqueado_ate, u.ultimo_login, u.criado, u.atualizado_em,
               COUNT(a.id) AS total_animais
        FROM Usuarios u
        LEFT JOIN Animais a ON u.id = a.usuario_id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " GROUP BY u.id ORDER BY u.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int) $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos
    FROM Usuarios");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$sexoData = $pdo->query("SELECT genero, COUNT(*) AS total FROM Usuarios WHERE genero IS NOT NULL GROUP BY genero")->fetchAll(PDO::FETCH_ASSOC);
$tipoData = $pdo->query("SELECT tipo_usuario, COUNT(*) AS total FROM Usuarios WHERE tipo_usuario IS NOT NULL GROUP BY tipo_usuario")->fetchAll(PDO::FETCH_ASSOC);
$animaisData = $pdo->query("SELECT u.nome, COUNT(a.id) AS total 
                            FROM Usuarios u
                            LEFT JOIN Animais a ON u.id = a.usuario_id
                            GROUP BY u.id, u.nome
                            HAVING COUNT(a.id) > 0
                            ORDER BY total DESC
                            LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';

function getInitials($nome)
{
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
    <title><?= $paginaTitulo ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .error-message {
            background: #fee2e2;
            border-left: 4px solid var(--danger);
            color: #991b1b;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-md);
        }

        .error-message i {
            font-size: 20px;
        }

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

        .filters-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 24px 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .filters-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-grid-full {
            grid-column: 1 / -1;
        }

        .form-section {
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group label i {
            color: var(--primary);
            font-size: 13px;
        }

        .form-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
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
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background: var(--gray-50);
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: var(--gray-300);
        }

        .form-group small {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-group small i {
            font-size: 10px;
            color: var(--info);
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 45px;
        }

        .password-toggle-btn {
            position: absolute;
            right: 2px;
            top: 2px;
            bottom: 2px;
            width: 40px;
            background: var(--gray-100);
            border: none;
            border-radius: 6px;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            background: var(--gray-200);
            color: var(--gray-900);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: 8px;
            border: 2px solid var(--gray-200);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .checkbox-group:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray-700);
        }

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

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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

        .btn-warning {
            background: var(--warning);
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

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 32px;
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

        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--gray-50);
        }

        th {
            padding: 16px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
            color: var(--gray-900);
            white-space: nowrap;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
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

        .user-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 13px;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge i {
            font-size: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 8px 12px;
            font-size: 12px;
        }

        .pagination {
            padding: 20px 32px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            background: var(--white);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        .pagination-info {
            font-size: 14px;
            color: var(--gray-600);
        }

        .pagination-buttons {
            display: flex;
            gap: 8px;
        }

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
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
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

        .usuario-details {
            color: var(--gray-900);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-900);
        }

        .detail-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        .detail-section h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section h4 i {
            color: var(--primary);
        }

        .detail-section p {
            font-size: 14px;
            color: var(--gray-700);
            line-height: 1.6;
        }

        .text-muted {
            color: var(--gray-500);
            font-style: italic;
            font-size: 14px;
        }

        .form-group input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }

        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }

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

            .filters-form {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: scroll;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 12px 16px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .modal-content {
                max-width: 95%;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 20px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                padding: 16px;
            }

            .filters-card,
            .table-card,
            .charts-section {
                padding: 20px;
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
        }
    </style>
</head>

<body>
    <div class="main-content">
        <div class="container">
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($_GET['error']) ?></span>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-users"></i> Gerenciamento de Usuários</h1>
                    <span class="badge-count"><?= $total_registros ?> usuários</span>
                </div>
                <button class="btn btn-primary" onclick="abrirModal('formAdd')">
                    <i class="fas fa-plus"></i>
                    Adicionar Usuário
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Total de Usuários</h3>
                            <p><?= $stats['total'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Usuários Ativos</h3>
                            <p><?= $stats['ativos'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Usuários Inativos</h3>
                            <p><?= $stats['inativos'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Páginas</h3>
                            <p><?= $total_paginas ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="charts-section">
                <h2><i class="fas fa-chart-pie"></i> Estatísticas</h2>
                <div class="charts-grid">
                    <div class="chart-card">
                        <canvas id="graficoSexo"></canvas>
                    </div>
                    <div class="chart-card">
                        <canvas id="graficoTipos"></canvas>
                    </div>
                    <div class="chart-card">
                        <canvas id="graficoAnimais"></canvas>
                    </div>
                </div>
            </div>

            <div class="filters-card">
                <div class="filters-header">
                    <h2><i class="fas fa-filter"></i> Filtros de Pesquisa</h2>
                </div>
                <form method="GET" class="filters-form">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nome</label>
                        <input type="text" name="nome" placeholder="Digite o nome"
                            value="<?= htmlspecialchars($_GET['nome'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> CPF</label>
                        <input type="text" name="cpf" placeholder="Digite o CPF"
                            value="<?= htmlspecialchars($_GET['cpf'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Telefone</label>
                        <input type="text" name="telefone" placeholder="Digite o telefone"
                            value="<?= htmlspecialchars($_GET['telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> E-mail</label>
                        <input type="email" name="email" placeholder="Digite o e-mail"
                            value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Tipo</label>
                        <select name="tipo_usuario">
                            <option value="">Todos</option>
                            <?php foreach (["Cliente", "Veterinario", "Secretaria", "Cuidador"] as $t): ?>
                                <option value="<?= $t ?>" <?= (($_GET['tipo_usuario'] ?? '') == $t) ? "selected" : "" ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Gênero</label>
                        <select name="genero">
                            <option value="">Todos</option>
                            <?php foreach (["Masculino", "Feminino", "Outro"] as $g): ?>
                                <option value="<?= $g ?>" <?= (($_GET['genero'] ?? '') == $g) ? "selected" : "" ?>><?= $g ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary"
                            onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">
                            <i class="fas fa-eraser"></i> Limpar
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($usuarios)): ?>
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-table"></i> Lista de Usuários</h2>
                        <span class="pagination-info">
                            Exibindo <?= $offset + 1 ?> a <?= min($offset + $itens_por_pagina, $total_registros) ?> de
                            <?= $total_registros ?>
                        </span>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>CPF</th>
                                    <th>Telefone</th>
                                    <th>Tipo</th>
                                    <th>Gênero</th>
                                    <th>Animais</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar"><?= getInitials($row['nome']) ?></div>
                                                <div class="user-info">
                                                    <span class="user-name"><?= htmlspecialchars($row['nome']) ?></span>
                                                    <span class="user-email"><?= htmlspecialchars($row['email']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['cpf']) ?></td>
                                        <td><?= htmlspecialchars($row['telefone'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['tipo_usuario']) ?></td>
                                        <td><?= htmlspecialchars($row['genero'] ?? '-') ?></td>
                                        <td>
                                            <a href="../Vet/animais.php?usuario_id=<?= (int) $row['id'] ?>"
                                                class="badge warning">
                                                <i class="fas fa-paw"></i> <?= (int) $row['total_animais'] ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?= $row['ativo'] ? 'success' : 'danger' ?>">
                                                <i class="fas fa-circle"></i> <?= $row['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                            <?php if (!empty($row['bloqueado_ate'])): ?>
                                                <br><span class="badge danger" style="margin-top: 4px;">
                                                    <i class="fas fa-lock"></i> Bloqueado
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-primary btn-sm"
                                                    onclick="abrirRelatorio(<?= (int) $row['id'] ?>)" title="Ver detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-success btn-sm"
                                                    onclick="abrirEdicao(<?= (int) $row['id'] ?>, '<?= addslashes($row['nome']) ?>', '<?= addslashes($row['cpf']) ?>', '<?= addslashes($row['telefone'] ?? '') ?>', '<?= addslashes($row['email']) ?>', '<?= addslashes($row['tipo_usuario']) ?>', '<?= addslashes($row['genero'] ?? '') ?>')"
                                                    title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Tem certeza que deseja excluir este usuário?')">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" name="acao" value="deletar"
                                                        class="btn btn-danger btn-sm" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-warning btn-sm"
                                                    onclick="abrirBloqueio(<?= (int) $row['id'] ?>)" title="Bloquear">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" name="acao" value="toggleAtivo"
                                                        class="btn btn-secondary btn-sm"
                                                        title="<?= $row['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                        <i
                                                            class="fas <?= $row['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination-info">
                            Página <?= $pagina_atual ?> de <?= $total_paginas ?>
                        </div>
                        <div class="pagination-buttons">
                            <button class="btn btn-secondary btn-sm"
                                onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>'"
                                <?= $pagina_atual <= 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-chevron-left"></i> Anterior
                            </button>
                            <button class="btn btn-secondary btn-sm"
                                onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>'"
                                <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>>
                                Próximo <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-table"></i> Lista de Usuários</h2>
                    </div>
                    <div style="padding: 60px; text-align: center; color: var(--gray-500);">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="font-size: 16px;">Nenhum usuário encontrado com os filtros aplicados.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar Usuário -->
    <div class="modal" id="formAdd">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Adicionar Novo Usuário</h3>
                <button class="modal-close" onclick="fecharModal('formAdd')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="adicionar">

                    <!-- Seção: Dados Pessoais -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-user"></i>
                            Dados Pessoais
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i>
                                    Nome Completo
                                    <span class="required">*</span>
                                </label>
                                <input type="text" name="nome" placeholder="Digite o nome completo" required>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-id-card"></i>
                                    CPF
                                    <span class="required">*</span>
                                </label>
                                <input type="text" name="cpf" id="cpf_add" placeholder="000.000.000-00" maxlength="14"
                                    required>
                                <small><i class="fas fa-info-circle"></i> Apenas números (11 dígitos)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Contato -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-address-book"></i>
                            Informações de Contato
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone
                                </label>
                                <input type="text" name="telefone" id="telefone_add" placeholder="(00) 00000-0000"
                                    maxlength="15">
                                <small><i class="fas fa-info-circle"></i> Celular ou telefone fixo</small>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-envelope"></i>
                                    E-mail
                                    <span class="required">*</span>
                                </label>
                                <input type="email" name="email" placeholder="email@exemplo.com" required>
                                <small><i class="fas fa-info-circle"></i> Será usado para login</small>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Acesso -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-lock"></i>
                            Dados de Acesso
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-key"></i>
                                    Senha
                                    <span class="required">*</span>
                                </label>
                                <div class="password-toggle">
                                    <input type="password" name="senha" id="senha_add" placeholder="Mínimo 6 caracteres"
                                        required minlength="6">
                                    <button type="button" class="password-toggle-btn"
                                        onclick="togglePassword('senha_add', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small><i class="fas fa-info-circle"></i> Mínimo de 6 caracteres</small>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user-tag"></i>
                                    Tipo de Usuário
                                    <span class="required">*</span>
                                </label>
                                <select name="tipo_usuario" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="Cliente">Cliente</option>
                                    <option value="Veterinario">Veterinário</option>
                                    <option value="Secretaria">Secretaria</option>
                                    <option value="Cuidador">Cuidador</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Informações Adicionais -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Adicionais
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-venus-mars"></i>
                                    Gênero
                                </label>
                                <select name="genero">
                                    <option value="">Selecione</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Feminino">Feminino</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-toggle-on"></i>
                                    Status da Conta
                                </label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="ativo" id="ativo_add" checked>
                                    <label for="ativo_add">Ativar usuário após cadastro</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('formAdd')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal" id="formEdit">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Editar Usuário</h3>
                <button class="modal-close" onclick="fecharModal('formEdit')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" id="edit_id">

                    <!-- Seção: Dados Pessoais -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-user"></i>
                            Dados Pessoais
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i>
                                    Nome Completo
                                    <span class="required">*</span>
                                </label>
                                <input type="text" name="nome" id="edit_nome" required>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-id-card"></i>
                                    CPF
                                    <span class="required">*</span>
                                </label>
                                <input type="text" name="cpf" id="edit_cpf" maxlength="14" required>
                                <small><i class="fas fa-info-circle"></i> Apenas números (11 dígitos)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Contato -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-address-book"></i>
                            Informações de Contato
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-phone"></i>
                                    Telefone
                                </label>
                                <input type="text" name="telefone" id="edit_telefone" maxlength="15">
                                <small><i class="fas fa-info-circle"></i> Celular ou telefone fixo</small>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-envelope"></i>
                                    E-mail
                                    <span class="required">*</span>
                                </label>
                                <input type="email" name="email" id="edit_email" required>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Tipo e Gênero -->
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-info-circle"></i>
                            Informações Adicionais
                        </h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user-tag"></i>
                                    Tipo de Usuário
                                    <span class="required">*</span>
                                </label>
                                <select name="tipo_usuario" id="edit_tipo" required>
                                    <option value="Cliente">Cliente</option>
                                    <option value="Veterinario">Veterinário</option>
                                    <option value="Secretaria">Secretaria</option>
                                    <option value="Cuidador">Cuidador</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-venus-mars"></i>
                                    Gênero
                                </label>
                                <select name="genero" id="edit_genero">
                                    <option value="">Selecione</option>
                                    <option value="Masculino">Masculino</option>
                                    <option value="Feminino">Feminino</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('formEdit')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Relatório -->
    <div class="modal" id="modalRelatorio">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Relatório do Usuário</h3>
                <button class="modal-close" onclick="fecharModal('modalRelatorio')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="relatorioDados">
                <div style="text-align: center; padding: 40px; color: var(--gray-500);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i>
                    <p style="margin-top: 16px;">Carregando...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Bloquear -->
    <div class="modal" id="modalBloqueio">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-lock"></i> Bloquear Usuário</h3>
                <button class="modal-close" onclick="fecharModal('modalBloqueio')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="bloquear">
                    <input type="hidden" name="id" id="bloqueioUserId">

                    <p style="margin-bottom: 20px; color: var(--gray-700);">
                        <i class="fas fa-info-circle" style="color: var(--warning);"></i>
                        Escolha a duração do bloqueio:
                    </p>

                    <div class="filters-form" style="grid-template-columns: 1fr;">
                        <div class="form-group">
                            <label style="font-weight: 500; cursor: pointer;">
                                <input type="radio" name="duracao" value="1h"> 1 hora
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 500; cursor: pointer;">
                                <input type="radio" name="duracao" value="24h"> 24 horas
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 500; cursor: pointer;">
                                <input type="radio" name="duracao" value="7d"> 7 dias
                            </label>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 500; cursor: pointer;">
                                <input type="radio" name="duracao" value="indefinido" checked> Indeterminado (desativar
                                conta)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalBloqueio')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-lock"></i> Confirmar Bloqueio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Máscaras de CPF e Telefone
        function mascaraCPF(input) {
            let valor = input.value.replace(/\D/g, '');
            valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
            valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
            valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            input.value = valor;
        }

        function mascaraTelefone(input) {
            let valor = input.value.replace(/\D/g, '');
            if (valor.length <= 10) {
                valor = valor.replace(/(\d{2})(\d)/, '($1) $2');
                valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                valor = valor.replace(/(\d{2})(\d)/, '($1) $2');
                valor = valor.replace(/(\d{5})(\d)/, '$1-$2');
            }
            input.value = valor;
        }

        // Aplicar máscaras nos campos
        document.getElementById('cpf_add').addEventListener('input', function () {
            mascaraCPF(this);
        });

        document.getElementById('telefone_add').addEventListener('input', function () {
            mascaraTelefone(this);
        });

        document.getElementById('edit_cpf').addEventListener('input', function () {
            mascaraCPF(this);
        });

        document.getElementById('edit_telefone').addEventListener('input', function () {
            mascaraTelefone(this);
        });

        // Toggle de senha
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Dados dos gráficos
        const dadosSexo = <?= json_encode($sexoData) ?>;
        const dadosTipos = <?= json_encode($tipoData) ?>;
        const dadosAnimais = <?= json_encode($animaisData) ?>;

        // Gráfico de Gênero
        new Chart(document.getElementById('graficoSexo'), {
            type: 'doughnut',
            data: {
                labels: dadosSexo.map(item => item.genero || 'Não informado'),
                datasets: [{
                    data: dadosSexo.map(item => parseInt(item.total)),
                    backgroundColor: ['#6366f1', '#ec4899', '#10b981'],
                    borderWidth: 1
                }]
            },
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
                        text: 'Distribuição por Gênero',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            }
        });

        // Gráfico de Tipos de Usuário
        new Chart(document.getElementById('graficoTipos'), {
            type: 'pie',
            data: {
                labels: dadosTipos.map(item => item.tipo_usuario || 'Não informado'),
                datasets: [{
                    data: dadosTipos.map(item => parseInt(item.total)),
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6'],
                    borderWidth: 1
                }]
            },
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
                        text: 'Distribuição por Tipo de Usuário',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            }
        });

        // Gráfico de Animais por Usuário
        new Chart(document.getElementById('graficoAnimais'), {
            type: 'bar',
            data: {
                labels: dadosAnimais.map(item => item.nome || 'Não informado'),
                datasets: [{
                    label: 'Animais por Usuário',
                    data: dadosAnimais.map(item => parseInt(item.total)),
                    backgroundColor: '#6366f1',
                    borderColor: '#4f46e5',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Top 10 Usuários por Quantidade de Animais',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            }
        });

        // Funções de Modal
        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function fecharModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function abrirRelatorio(userId) {
            const modal = document.getElementById('modalRelatorio');
            const alvo = document.getElementById('relatorioDados');
            alvo.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--gray-500);"><i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 16px;">Carregando...</p></div>';
            abrirModal('modalRelatorio');

            fetch(`<?= basename($_SERVER['PHP_SELF']) ?>?ajax=relatorio&id=` + userId, {
                credentials: 'same-origin'
            })
                .then(r => r.text())
                .then(html => alvo.innerHTML = html)
                .catch(() => alvo.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-circle"></i><span>Erro ao carregar relatório.</span></div>');
        }

        function abrirBloqueio(userId) {
            document.getElementById('bloqueioUserId').value = userId;
            abrirModal('modalBloqueio');
        }

        function abrirEdicao(id, nome, cpf, telefone, email, tipo, genero) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_cpf').value = cpf;
            document.getElementById('edit_telefone').value = telefone;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_genero').value = genero;
            abrirModal('formEdit');
        }

        // Fechar modal ao clicar fora
        window.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>

</html>