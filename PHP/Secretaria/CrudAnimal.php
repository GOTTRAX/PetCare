<?php
// =================== CONEXÃO PDO ===================
include "../conexao.php";
$paginaTitulo = "Gerenciamento de Animais";

// Enable PDO error reporting for debugging
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// =================== CONFIGURAÇÕES DE UPLOAD ===================
$uploadDir = '../../assets/uploads/pets/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$allowedTypes = ['image/jpeg', 'image/png'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// =================== AÇÕES (POST) ===================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"])) {
    $acao = $_POST["acao"];
    $foto = null;

    // Processar upload de foto (comum para adicionar e editar)
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto']['tmp_name'];
        $fileName = $_FILES['foto']['name'];
        $fileSize = $_FILES['foto']['size'];
        $fileType = mime_content_type($fileTmpPath);

        // Validar tipo e tamanho
        if (!in_array($fileType, $allowedTypes)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=Formato inválido. Apenas JPEG ou PNG são permitidos.");
            exit;
        }
        if ($fileSize > $maxFileSize) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=Arquivo muito grande. Máximo 5MB.");
            exit;
        }

        // Evitar sobrescrita: Adicionar timestamp ao nome
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = time() . '_' . basename($fileName);
        $destPath = $uploadDir . $newFileName;

        // Salvar diretamente sem redimensionar
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=Erro ao mover o arquivo para o servidor.");
            exit;
        }

        // Nome do arquivo para o DB
        $foto = $newFileName;
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Erro no upload: " . $_FILES['foto']['error']);
        exit;
    }

    try {
        if ($acao === "adicionar") {
            if (empty($_POST["nome"]) || empty($_POST["especie_id"]) || empty($_POST["usuario_id"])) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Campos obrigatórios não preenchidos");
                exit;
            }
            $sql = "INSERT INTO Animais (nome, datanasc, especie_id, raca, porte, sexo, usuario_id, foto) 
                    VALUES (:nome, :datanasc, :especie_id, :raca, :porte, :sexo, :usuario_id, :foto)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":nome" => trim($_POST["nome"]),
                ":datanasc" => !empty($_POST["datanasc"]) ? $_POST["datanasc"] : null,
                ":especie_id" => (int)$_POST["especie_id"],
                ":raca" => !empty($_POST["raca"]) ? trim($_POST["raca"]) : null,
                ":porte" => !empty($_POST["porte"]) && in_array($_POST["porte"], ['Pequeno', 'Medio', 'Grande']) ? $_POST["porte"] : null,
                ":sexo" => !empty($_POST["sexo"]) ? $_POST["sexo"] : null,
                ":usuario_id" => (int)$_POST["usuario_id"],
                ":foto" => $foto
            ]);
        }

        if ($acao === "editar") {
            $id = (int)$_POST["id"];
            if (empty($id) || empty($_POST["nome"]) || empty($_POST["especie_id"]) || empty($_POST["usuario_id"])) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=Campos obrigatórios não preenchidos");
                exit;
            }
            $stmt = $pdo->prepare("SELECT foto FROM Animais WHERE id = :id");
            $stmt->execute([":id" => $id]);
            $currentFoto = $stmt->fetchColumn();

            if ($foto && $currentFoto && file_exists($uploadDir . $currentFoto)) {
                unlink($uploadDir . $currentFoto);
            } else {
                $foto = $currentFoto;
            }

            $sql = "UPDATE Animais 
                    SET nome=:nome, datanasc=:datanasc, especie_id=:especie_id, 
                        raca=:raca, porte=:porte, sexo=:sexo, usuario_id=:usuario_id, foto=:foto
                    WHERE id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":id" => $id,
                ":nome" => trim($_POST["nome"]),
                ":datanasc" => !empty($_POST["datanasc"]) ? $_POST["datanasc"] : null,
                ":especie_id" => (int)$_POST["especie_id"],
                ":raca" => !empty($_POST["raca"]) ? trim($_POST["raca"]) : null,
                ":porte" => !empty($_POST["porte"]) && in_array($_POST["porte"], ['Pequeno', 'Medio', 'Grande']) ? $_POST["porte"] : null,
                ":sexo" => !empty($_POST["sexo"]) ? $_POST["sexo"] : null,
                ":usuario_id" => (int)$_POST["usuario_id"],
                ":foto" => $foto
            ]);
        }

        if ($acao === "deletar") {
            $id = (int)$_POST["id"];
            if (empty($id)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=ID inválido");
                exit;
            }
            $stmt = $pdo->prepare("SELECT foto FROM Animais WHERE id = :id");
            $stmt->execute([":id" => $id]);
            $currentFoto = $stmt->fetchColumn();
            if ($currentFoto && file_exists($uploadDir . $currentFoto)) {
                unlink($uploadDir . $currentFoto);
            }

            $stmt = $pdo->prepare("DELETE FROM Animais WHERE id=:id");
            $stmt->execute([":id" => $id]);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    } catch (PDOException $e) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=Erro no banco de dados: " . urlencode($e->getMessage()));
        exit;
    }
}

// =================== AJAX (GET - Detalhes do Animal) ===================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, e.nome as especie_nome, u.nome as dono_nome, u.email, u.telefone 
            FROM Animais a 
            INNER JOIN Especies e ON a.especie_id = e.id 
            INNER JOIN Usuarios u ON a.usuario_id = u.id 
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT c.data_consulta as data, 'Consulta' as tipo, 
                   COALESCE(c.diagnostico, 'Consulta realizada') as descricao
            FROM Consultas c 
            WHERE c.animal_id = :id
            ORDER BY c.data_consulta DESC 
            LIMIT 10
        ");
        $stmt->execute([':id' => $id]);
        $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$animal) {
            echo "<p class='error-message'>Animal não encontrado.</p>";
            exit;
        }

        $idade = "";
        if ($animal['datanasc']) {
            $nascimento = new DateTime($animal['datanasc']);
            $hoje = new DateTime();
            $idade = $nascimento->diff($hoje)->y . " ano(s)";
        }

        $porteDisplay = str_replace('Medio', 'Médio', $animal["porte"] ?: 'Não informado');
        ?>
        <div class="animal-details">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">ID</span>
                    <span class="detail-value"><?= (int) $animal["id"] ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Nome</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["nome"]) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Espécie</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["especie_nome"]) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Raça</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["raca"] ?: 'Não informada') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Data de Nascimento</span>
                    <span class="detail-value"><?= $animal["datanasc"] ? date('d/m/Y', strtotime($animal["datanasc"])) : 'Não informada' ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Idade</span>
                    <span class="detail-value"><?= $idade ?: 'Não informada' ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Porte</span>
                    <span class="detail-value"><?= htmlspecialchars($porteDisplay) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Sexo</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["sexo"] ?: 'Não informado') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Dono</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["dono_nome"]) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Contato</span>
                    <span class="detail-value"><?= htmlspecialchars($animal["email"]) ?> | <?= htmlspecialchars($animal["telefone"] ?: 'Não informado') ?></span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fas fa-image"></i> Foto</h4>
                <img src="<?= $animal['foto'] ? '../../assets/uploads/pets/' . htmlspecialchars(basename($animal['foto'])) : 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' ?>"
                     class="img-fluid rounded shadow-sm" alt="<?= htmlspecialchars($animal['nome']) ?>" style="max-width: 300px;">
            </div>

            <div class="detail-section">
                <h4><i class="fas fa-stethoscope"></i> Últimas Consultas</h4>
                <?php if (!empty($consultas)): ?>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultas as $consulta): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($consulta["data"])) ?></td>
                                        <td><?= htmlspecialchars($consulta["tipo"]) ?></td>
                                        <td><?= htmlspecialchars($consulta["descricao"]) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nenhuma consulta registrada para este animal.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    } catch (PDOException $e) {
        echo "<p class='error-message'>Erro ao carregar detalhes: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    exit;
}

// =================== FILTROS E PAGINAÇÃO (GET) ===================
$where = [];
$params = [];

if (!empty(trim($_GET['nome'] ?? ''))) {
    $where[] = "a.nome LIKE :nome";
    $params[':nome'] = "%" . trim($_GET['nome']) . "%";
}
if (!empty(trim($_GET['raca'] ?? ''))) {
    $where[] = "a.raca LIKE :raca";
    $params[':raca'] = "%" . trim($_GET['raca']) . "%";
}
if (!empty($_GET['especie_id'])) {
    $where[] = "a.especie_id = :especie_id";
    $params[':especie_id'] = (int)$_GET['especie_id'];
}
if (!empty($_GET['porte'])) {
    $where[] = "a.porte = :porte";
    $params[':porte'] = $_GET['porte'];
}
if (!empty($_GET['sexo'])) {
    $where[] = "a.sexo = :sexo";
    $params[':sexo'] = $_GET['sexo'];
}
if (!empty($_GET['usuario_id'])) {
    $where[] = "a.usuario_id = :usuario_id";
    $params[':usuario_id'] = (int)$_GET['usuario_id'];
}

// Paginação
$itens_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$sql_count = "SELECT COUNT(*) as total 
              FROM Animais a
              INNER JOIN Especies e ON a.especie_id = e.id
              INNER JOIN Usuarios u ON a.usuario_id = u.id";
if ($where) {
    $sql_count .= " WHERE " . implode(" AND ", $where);
}
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

$sql = "SELECT a.*, e.nome as especie_nome, u.nome as dono_nome 
        FROM Animais a
        INNER JOIN Especies e ON a.especie_id = e.id
        INNER JOIN Usuarios u ON a.usuario_id = u.id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$animais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =================== DADOS PARA GRÁFICOS ===================
$sexoData = $pdo->query("SELECT sexo, COUNT(*) AS total FROM Animais WHERE sexo IS NOT NULL GROUP BY sexo")->fetchAll(PDO::FETCH_ASSOC);
$especieData = $pdo->query("SELECT e.nome, COUNT(a.id) AS total 
                            FROM Animais a
                            INNER JOIN Especies e ON a.especie_id = e.id
                            GROUP BY e.id, e.nome")->fetchAll(PDO::FETCH_ASSOC);
$porteData = $pdo->query("SELECT porte, COUNT(*) AS total FROM Animais WHERE porte IS NOT NULL GROUP BY porte")->fetchAll(PDO::FETCH_ASSOC);

// =================== DADOS PARA FILTROS ===================
$especies = $pdo->query("SELECT id, nome FROM Especies")->fetchAll(PDO::FETCH_ASSOC);
$donos = $pdo->query("SELECT id, nome FROM Usuarios WHERE tipo_usuario = 'Cliente'")->fetchAll(PDO::FETCH_ASSOC);

// =================== ESTATÍSTICAS ===================
$stmt_stats = $pdo->query("SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT especie_id) as especies,
    SUM(CASE WHEN sexo = 'Macho' THEN 1 ELSE 0 END) as machos,
    SUM(CASE WHEN sexo = 'Fêmea' THEN 1 ELSE 0 END) as femeas
    FROM Animais");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// =================== INCLUIR HEADER COM SIDEBAR ===================
include 'header.php';

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
    <title><?= $paginaTitulo ?></title>
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

        /* Error Message */
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

        /* Filters Card */
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

        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: var(--white);
            color: var(--gray-900);
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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

        /* Table Card */
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
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        .animal-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .animal-avatar {
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

        .animal-img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
        }

        .animal-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .animal-name {
            font-weight: 600;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .animal-especie {
            font-size: 13px;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        /* Pagination */
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

        /* Animal Details in Modal */
        .animal-details {
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

            .filters-form {
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

            th, td {
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

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-paw"></i> Gerenciamento de Animais</h1>
                    <span class="badge-count"><?= $total_registros ?> animais</span>
                </div>
                <button class="btn btn-primary" onclick="abrirModal('formAdd')">
                    <i class="fas fa-plus"></i>
                    Adicionar Animal
                </button>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Total de Animais</h3>
                            <p><?= $stats['total'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Espécies Únicas</h3>
                            <p><?= $stats['especies'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Machos</h3>
                            <p><?= $stats['machos'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-mars"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Fêmeas</h3>
                            <p><?= $stats['femeas'] ?></p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-venus"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <h2><i class="fas fa-chart-pie"></i> Estatísticas</h2>
                <div class="charts-grid">
                    <div class="chart-card">
                        <canvas id="graficoSexo"></canvas>
                    </div>
                    <div class="chart-card">
                        <canvas id="graficoEspecies"></canvas>
                    </div>
                    <div class="chart-card">
                        <canvas id="graficoPortes"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <div class="filters-header">
                    <h2><i class="fas fa-filter"></i> Filtros de Pesquisa</h2>
                </div>
                <form method="GET" class="filters-form">
                    <div class="form-group">
                        <label><i class="fas fa-paw"></i> Nome</label>
                        <input type="text" name="nome" placeholder="Digite o nome" value="<?= htmlspecialchars($_GET['nome'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-dog"></i> Raça</label>
                        <input type="text" name="raca" placeholder="Digite a raça" value="<?= htmlspecialchars($_GET['raca'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-leaf"></i> Espécie</label>
                        <select name="especie_id">
                            <option value="">Todos</option>
                            <?php foreach ($especies as $especie): ?>
                                <option value="<?= $especie['id'] ?>" <?= (($_GET['especie_id'] ?? '') == $especie['id']) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($especie['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-ruler"></i> Porte</label>
                        <select name="porte">
                            <option value="">Todos</option>
                            <?php foreach (["Pequeno" => "Pequeno", "Medio" => "Médio", "Grande" => "Grande"] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= (($_GET['porte'] ?? '') == $value) ? "selected" : "" ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Sexo</label>
                        <select name="sexo">
                            <option value="">Todos</option>
                            <?php foreach (["Macho", "Fêmea"] as $sexo): ?>
                                <option value="<?= $sexo ?>" <?= (($_GET['sexo'] ?? '') == $sexo) ? "selected" : "" ?>>
                                    <?= $sexo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Dono</label>
                        <select name="usuario_id">
                            <option value="">Todos</option>
                            <?php foreach ($donos as $dono): ?>
                                <option value="<?= $dono['id'] ?>" <?= (($_GET['usuario_id'] ?? '') == $dono['id']) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($dono['nome']) ?>
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
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'">
                            <i class="fas fa-eraser"></i> Limpar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <?php if (!empty($animais)): ?>
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-table"></i> Lista de Animais</h2>
                        <span class="pagination-info">
                            Exibindo <?= $offset + 1 ?> a <?= min($offset + $itens_por_pagina, $total_registros) ?> de <?= $total_registros ?>
                        </span>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>Espécie</th>
                                    <th>Raça</th>
                                    <th>Porte</th>
                                    <th>Sexo</th>
                                    <th>Dono</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($animais as $animal): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($animal['foto'])): ?>
                                                <img src="../../assets/uploads/pets/<?= htmlspecialchars(basename($animal['foto'])) ?>" class="animal-img"
                                                     alt="<?= htmlspecialchars($animal['nome']) ?>">
                                            <?php else: ?>
                                                <div class="animal-avatar"><?= getInitials($animal['nome']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="animal-cell">
                                                <div class="animal-info">
                                                    <span class="animal-name"><?= htmlspecialchars($animal['nome']) ?></span>
                                                    <span class="animal-especie"><?= htmlspecialchars($animal['especie_nome']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($animal['especie_nome']) ?></td>
                                        <td><?= htmlspecialchars($animal['raca'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars(str_replace('Medio', 'Médio', $animal['porte'] ?: '-')) ?></td>
                                        <td><?= htmlspecialchars($animal['sexo'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($animal['dono_nome']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="abrirDetalhes(<?= (int) $animal['id'] ?>)" title="Ver detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-success btn-sm" onclick="abrirEdicao(<?= (int) $animal['id'] ?>, '<?= addslashes($animal['nome']) ?>', '<?= addslashes($animal['datanasc'] ?? '') ?>', '<?= (int) $animal['especie_id'] ?>', '<?= addslashes($animal['raca'] ?? '') ?>', '<?= addslashes($animal['porte'] ?? '') ?>', '<?= addslashes($animal['sexo'] ?? '') ?>', '<?= (int) $animal['usuario_id'] ?>', '<?= addslashes($animal['foto'] ?? '') ?>')" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este animal?')">
                                                    <input type="hidden" name="id" value="<?= (int) $animal['id'] ?>">
                                                    <button type="submit" name="acao" value="deletar" class="btn btn-danger btn-sm" title="Excluir">
                                                        <i class="fas fa-trash"></i>
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
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>'" <?= $pagina_atual <= 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-chevron-left"></i> Anterior
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>'" <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>>
                                Próximo <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-table"></i> Lista de Animais</h2>
                    </div>
                    <div style="padding: 60px; text-align: center; color: var(--gray-500);">
                        <i class="fas fa-paw" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p style="font-size: 16px;">Nenhum animal encontrado com os filtros aplicados.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar Animal -->
    <div class="modal" id="formAdd">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-paw"></i> Adicionar Novo Animal</h3>
                <button class="modal-close" onclick="fecharModal('formAdd')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="adicionar">
                    <div class="filters-form">
                        <div class="form-group">
                            <label><i class="fas fa-paw"></i> Nome *</label>
                            <input type="text" name="nome" placeholder="Nome do animal" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Data de Nascimento</label>
                            <input type="date" name="datanasc">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-leaf"></i> Espécie *</label>
                            <select name="especie_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($especies as $especie): ?>
                                    <option value="<?= $especie['id'] ?>"><?= htmlspecialchars($especie['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-dog"></i> Raça</label>
                            <input type="text" name="raca" placeholder="Raça do animal">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-ruler"></i> Porte</label>
                            <select name="porte">
                                <option value="">Selecione</option>
                                <?php foreach (["Pequeno" => "Pequeno", "Medio" => "Médio", "Grande" => "Grande"] as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Sexo</label>
                            <select name="sexo">
                                <option value="">Selecione</option>
                                <?php foreach (["Macho", "Fêmea"] as $sexo): ?>
                                    <option value="<?= $sexo ?>"><?= $sexo ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Dono *</label>
                            <select name="usuario_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($donos as $dono): ?>
                                    <option value="<?= $dono['id'] ?>"><?= htmlspecialchars($dono['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Foto</label>
                            <input type="file" name="foto" accept="image/jpeg, image/png">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('formAdd')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Animal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Animal -->
    <div class="modal" id="formEdit">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Animal</h3>
                <button class="modal-close" onclick="fecharModal('formEdit')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="filters-form">
                        <div class="form-group">
                            <label><i class="fas fa-paw"></i> Nome *</label>
                            <input type="text" name="nome" id="edit_nome" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Data de Nascimento</label>
                            <input type="date" name="datanasc" id="edit_datanasc">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-leaf"></i> Espécie *</label>
                            <select name="especie_id" id="edit_especie" required>
                                <?php foreach ($especies as $especie): ?>
                                    <option value="<?= $especie['id'] ?>"><?= htmlspecialchars($especie['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-dog"></i> Raça</label>
                            <input type="text" name="raca" id="edit_raca">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-ruler"></i> Porte</label>
                            <select name="porte" id="edit_porte">
                                <option value="">Selecione</option>
                                <?php foreach (["Pequeno" => "Pequeno", "Medio" => "Médio", "Grande" => "Grande"] as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Sexo</label>
                            <select name="sexo" id="edit_sexo">
                                <option value="">Selecione</option>
                                <?php foreach (["Macho", "Fêmea"] as $sexo): ?>
                                    <option value="<?= $sexo ?>"><?= $sexo ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Dono *</label>
                            <select name="usuario_id" id="edit_usuario" required>
                                <?php foreach ($donos as $dono): ?>
                                    <option value="<?= $dono['id'] ?>"><?= htmlspecialchars($dono['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Foto</label>
                            <input type="file" name="foto" id="edit_foto" accept="image/jpeg, image/png">
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

    <!-- Modal Detalhes -->
    <div class="modal" id="modalDetalhes">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Detalhes do Animal</h3>
                <button class="modal-close" onclick="fecharModal('modalDetalhes')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="detalhesDados">
                <div style="text-align: center; padding: 40px; color: var(--gray-500);">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i>
                    <p style="margin-top: 16px;">Carregando...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dados dos gráficos
        const dadosSexo = <?= json_encode($sexoData) ?>;
        const dadosEspecies = <?= json_encode($especieData) ?>;
        const dadosPortes = <?= json_encode($porteData) ?>;

        // Gráfico de Sexo
        new Chart(document.getElementById('graficoSexo'), {
            type: 'pie',
            data: {
                labels: dadosSexo.map(d => d.sexo || 'Não informado'),
                datasets: [{
                    data: dadosSexo.map(d => Number(d.total)),
                    backgroundColor: ['#6366f1', '#ec4899'],
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
                        text: 'Distribuição por Sexo',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            }
        });

        // Gráfico de Espécies
        new Chart(document.getElementById('graficoEspecies'), {
            type: 'pie',
            data: {
                labels: dadosEspecies.map(d => d.nome || 'Não informado'),
                datasets: [{
                    data: dadosEspecies.map(d => Number(d.total)),
                    backgroundColor: ['#6366f1', '#f59e0b', '#10b981', '#8b5cf6', '#3b82f6'],
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
                        text: 'Distribuição por Espécie',
                        font: {
                            size: 16,
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            }
        });

        // Gráfico de Portes
        new Chart(document.getElementById('graficoPortes'), {
            type: 'bar',
            data: {
                labels: dadosPortes.map(d => d.porte ? d.porte.replace('Medio', 'Médio') : 'Não informado'),
                datasets: [{
                    label: 'Quantidade por Porte',
                    data: dadosPortes.map(d => Number(d.total)),
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
                        text: 'Distribuição por Porte',
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

        function abrirDetalhes(animalId) {
            const modal = document.getElementById('modalDetalhes');
            const alvo = document.getElementById('detalhesDados');
            alvo.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--gray-500);"><i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 16px;">Carregando...</p></div>';
            abrirModal('modalDetalhes');
            
            fetch(`<?= basename($_SERVER['PHP_SELF']) ?>?ajax=detalhes&id=` + animalId, { 
                credentials: 'same-origin' 
            })
            .then(r => r.text())
            .then(html => alvo.innerHTML = html)
            .catch(() => alvo.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-circle"></i><span>Erro ao carregar detalhes.</span></div>');
        }

        function abrirEdicao(id, nome, datanasc, especie_id, raca, porte, sexo, usuario_id, foto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nome').value = nome;
            document.getElementById('edit_datanasc').value = datanasc;
            document.getElementById('edit_especie').value = especie_id;
            document.getElementById('edit_raca').value = raca;
            document.getElementById('edit_porte').value = porte;
            document.getElementById('edit_sexo').value = sexo;
            document.getElementById('edit_usuario').value = usuario_id;
            abrirModal('formEdit');
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
    </script>
</body>
</html>