<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

include '../conexao.php';

// ========== FUNÇÕES AUXILIARES ==========
function getIniciais($nomeCompleto)
{
    $partes = explode(' ', trim($nomeCompleto));
    $inicial1 = strtoupper(mb_substr($partes[0], 0, 1));
    $inicial2 = isset($partes[1]) ? strtoupper(mb_substr($partes[1], 0, 1)) : '';
    return $inicial1 . $inicial2;
}

function getEmojiPorEspecie($especie)
{
    $emojis = [
        'Cachorro' => '🐕',
        'Gato' => '🐈',
        'Hamster' => '🐹',
        'Peixe' => '🐠',
        'Pássaro' => '🐦',
        'Coelho' => '🐰',
        'Tartaruga' => '🐢',
        'Porquinho da Índia' => '🐹'
    ];
    return $emojis[$especie] ?? '🐾';
}

// ========== VARIÁVEIS DO USUÁRIO ==========
$usuario_id = $_SESSION["id"];
$usuario_nome = $_SESSION["nome"];
$usuario_email = $_SESSION["email"];
$usuario_telefone = $_SESSION["telefone"];
$usuario_datanasc = $_SESSION["datanasc"];
$usuario_genero = $_SESSION["genero"];

// Buscar dados completos do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario_completo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuario_completo = [];
}

// Buscar quantidade de pets
$qtd_pets = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Animais WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $qtd_pets = $stmt->fetchColumn();
} catch (PDOException $e) {
    $qtd_pets = 0;
}

// ========== PROCESSAMENTO AJAX ==========
if (
    $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) {

    header('Content-Type: application/json');

    try {
        // ===== CADASTRAR/EDITAR PET =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'salvar_pet') {
            $nome_pet = trim($_POST['nome_pet']);
            $especie_nome = trim($_POST['especie']);
            $raca = !empty($_POST['raca']) ? trim($_POST['raca']) : null;
            $sexo = !empty($_POST['sexo']) ? $_POST['sexo'] : null;
            $porte = !empty($_POST['porte']) ? $_POST['porte'] : null;
            $datanasc = !empty($_POST['datanasc_pet']) ? $_POST['datanasc_pet'] : null;
            $pet_id = !empty($_POST['pet_id']) ? (int) $_POST['pet_id'] : null;

            // Buscar ID da espécie
            $stmt = $pdo->prepare("SELECT id FROM Especies WHERE nome = ?");
            $stmt->execute([$especie_nome]);
            $especie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$especie) {
                throw new Exception("Espécie não encontrada!");
            }

            $especie_id = $especie['id'];

            // Upload de foto
            $foto_nome = null;
            if (isset($_FILES['foto_pet']) && $_FILES['foto_pet']['error'] == 0) {
                $extensao = strtolower(pathinfo($_FILES['foto_pet']['name'], PATHINFO_EXTENSION));
                $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($extensao, $extensoes_permitidas)) {
                    $foto_nome = uniqid() . '.' . $extensao;
                    $caminho_upload = '../../assets/uploads/pets/';

                    if (!is_dir($caminho_upload)) {
                        mkdir($caminho_upload, 0755, true);
                    }

                    move_uploaded_file($_FILES['foto_pet']['tmp_name'], $caminho_upload . $foto_nome);
                }
            }

            if ($pet_id) {
                // EDITAR PET
                if ($foto_nome) {
                    // Deletar foto antiga
                    $stmt = $pdo->prepare("SELECT foto FROM Animais WHERE id = ? AND usuario_id = ?");
                    $stmt->execute([$pet_id, $usuario_id]);
                    $foto_antiga = $stmt->fetchColumn();
                    if ($foto_antiga && file_exists('../../assets/uploads/pets/' . $foto_antiga)) {
                        unlink('../../assets/uploads/pets/' . $foto_antiga);
                    }

                    $sql = "UPDATE Animais SET nome=?, especie_id=?, raca=?, sexo=?, porte=?, datanasc=?, foto=? 
                            WHERE id=? AND usuario_id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome_pet, $especie_id, $raca, $sexo, $porte, $datanasc, $foto_nome, $pet_id, $usuario_id]);
                } else {
                    $sql = "UPDATE Animais SET nome=?, especie_id=?, raca=?, sexo=?, porte=?, datanasc=? 
                            WHERE id=? AND usuario_id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome_pet, $especie_id, $raca, $sexo, $porte, $datanasc, $pet_id, $usuario_id]);
                }

                echo json_encode(['success' => true, 'message' => 'Pet atualizado com sucesso!']);
            } else {
                // CADASTRAR PET
                $sql = "INSERT INTO Animais (nome, especie_id, raca, sexo, porte, datanasc, usuario_id, foto) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome_pet, $especie_id, $raca, $sexo, $porte, $datanasc, $usuario_id, $foto_nome]);

                echo json_encode(['success' => true, 'message' => 'Pet cadastrado com sucesso!']);
            }
            exit();
        }

        // ===== EXCLUIR PET =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'excluir_pet') {
            $pet_id = (int) $_POST['pet_id'];

            // Buscar e deletar foto
            $stmt = $pdo->prepare("SELECT foto FROM Animais WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$pet_id, $usuario_id]);
            $foto = $stmt->fetchColumn();

            if ($foto && file_exists('../../assets/uploads/pets/' . $foto)) {
                unlink('../../assets/uploads/pets/' . $foto);
            }

            $stmt = $pdo->prepare("DELETE FROM Animais WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$pet_id, $usuario_id]);

            echo json_encode(['success' => true, 'message' => 'Pet excluído com sucesso!']);
            exit();
        }

        // ===== ATUALIZAR PERFIL =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'atualizar_perfil') {
            $novo_nome = trim($_POST['nome']);
            $novo_telefone = trim($_POST['telefone']);
            $nova_datanasc = $_POST['datanasc'];
            $novo_genero = $_POST['genero'];

            $sql = "UPDATE Usuarios SET nome=?, telefone=?, datanasc=?, genero=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_nome, $novo_telefone, $nova_datanasc, $novo_genero, $usuario_id]);

            // Atualizar sessão
            $_SESSION["nome"] = $novo_nome;
            $_SESSION["telefone"] = $novo_telefone;
            $_SESSION["datanasc"] = date('d/m/Y', strtotime($nova_datanasc));
            $_SESSION["genero"] = $novo_genero;

            echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!']);
            exit();
        }

        // ===== ALTERAR SENHA =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'alterar_senha') {
            $senha_atual = $_POST['senha_atual'];
            $nova_senha = $_POST['nova_senha'];
            $confirmar_senha = $_POST['confirmar_senha'];

            if ($nova_senha !== $confirmar_senha) {
                throw new Exception("As senhas não coincidem!");
            }

            // Verificar senha atual
            $stmt = $pdo->prepare("SELECT senha_hash FROM Usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($senha_atual, $user['senha_hash'])) {
                throw new Exception("Senha atual incorreta!");
            }

            // Atualizar senha
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE Usuarios SET senha_hash=? WHERE id=?");
            $stmt->execute([$nova_senha_hash, $usuario_id]);

            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
            exit();
        }

        // ===== BUSCAR PETS (para reload) =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'buscar_pets') {
            $sql = "SELECT a.*, e.nome as especie_nome 
                    FROM Animais a 
                    JOIN Especies e ON a.especie_id = e.id 
                    WHERE a.usuario_id = ? 
                    ORDER BY a.nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id]);
            $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'pets' => $pets]);
            exit();
        }

        // ===== BUSCAR DETALHES DO PRONTUÁRIO =====
        if (isset($_POST['acao']) && $_POST['acao'] == 'buscar_prontuario') {
            $agendamento_id = (int) $_POST['agendamento_id'];

            $sql = "SELECT c.*, a.*, an.nome as animal_nome, an.foto as animal_foto,
                           e.nome as especie, s.nome as servico_nome, vet.nome as veterinario_nome
                    FROM Consultas c
                    INNER JOIN Agendamentos a ON c.agendamento_id = a.id
                    INNER JOIN Animais an ON a.animal_id = an.id
                    INNER JOIN Especies e ON an.especie_id = e.id
                    INNER JOIN Servicos s ON a.servico_id = s.id
                    LEFT JOIN Usuarios vet ON c.veterinario_id = vet.id
                    WHERE c.agendamento_id = ? AND a.cliente_id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$agendamento_id, $usuario_id]);
            $prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($prontuario) {
                echo json_encode(['success' => true, 'prontuario' => $prontuario]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Prontuário não encontrado']);
            }
            exit();
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// ========== BUSCAR PETS DO USUÁRIO ==========
$pets = [];
try {
    $sql = "SELECT a.*, e.nome as especie_nome 
            FROM Animais a 
            JOIN Especies e ON a.especie_id = e.id 
            WHERE a.usuario_id = ? 
            ORDER BY a.nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pets = [];
}

// ========== BUSCAR AGENDAMENTOS (TODOS) ==========
$agendamentos = [];

try {
    $sql = "SELECT a.*, s.nome as servico_nome, an.nome as animal_nome, an.foto as animal_foto,
                   e.nome as especie, c.id as consulta_id
            FROM Agendamentos a
            JOIN Servicos s ON a.servico_id = s.id
            JOIN Animais an ON a.animal_id = an.id
            JOIN Especies e ON an.especie_id = e.id
            LEFT JOIN Consultas c ON a.id = c.agendamento_id
            WHERE a.cliente_id = ?
            ORDER BY a.data_hora DESC, a.hora_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $agendamentos = [];
}

// Determinar aba ativa
$aba_atual = isset($_GET['aba']) ? $_GET['aba'] : 'informacoes';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - PetCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bibliotecas para Download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --verde-principal: #2E8B57;
            --verde-escuro: #1e5f3f;
            --branco: #FFFFFF;
            --cinza-claro: #F5F5F5;
            --cinza-medio: #E0E0E0;
            --cinza-escuro: #757575;
            --vermelho: #E74C3C;
            --amarelo: #F39C12;
            --verde-sucesso: #10b981;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--branco);
            min-height: 100vh;
            padding-top: 80px;
        }

        .container-perfil {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* ========== CABEÇALHO CENTRALIZADO ========== */
        .perfil-header {
            background: var(--branco);
            border: 2px solid var(--verde-principal);
            border-radius: 12px;
            padding: 30px;
            color: var(--cinza-escuro);
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--verde-principal);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            color: var(--branco);
            border: 3px solid var(--verde-escuro);
            box-shadow: 0 4px 12px rgba(46, 139, 87, 0.2);
            transition: transform 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
        }

        .perfil-info h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--verde-escuro);
        }

        .perfil-info p {
            font-size: 14px;
            color: var(--cinza-escuro);
            margin: 2px 0;
        }

        /* ========== NAVEGAÇÃO DE ABAS CENTRALIZADA ========== */
        .tabs-navigation {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: var(--branco);
            border: 2px solid var(--cinza-medio);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--cinza-escuro);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            border-color: var(--verde-principal);
            color: var(--verde-principal);
        }

        .tab-btn.active {
            background: var(--verde-principal);
            color: var(--branco);
            border-color: var(--verde-principal);
        }

        .tab-btn i {
            font-size: 16px;
        }

        /* ========== CONTEÚDO DAS ABAS ========== */
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
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

        /* ========== CARD PADRÃO ========== */
        .card {
            background: var(--branco);
            border: 1px solid var(--cinza-medio);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
        }

        .card h2 {
            color: var(--verde-escuro);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid var(--verde-principal);
            padding-bottom: 8px;
        }

        /* ========== GRID DE INFORMAÇÕES ========== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: var(--cinza-claro);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid var(--verde-principal);
            transition: all 0.3s ease;
        }

        .info-item:hover {
            transform: translateX(3px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .info-item strong {
            display: block;
            color: var(--verde-escuro);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span {
            color: var(--cinza-escuro);
            font-size: 16px;
            font-weight: 500;
        }

        /* ========== PETS GRID ========== */
        .pets-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .form-pet {
            background: var(--branco);
            border: 1px solid var(--cinza-medio);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .form-pet h3 {
            color: var(--verde-escuro);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alerta-edicao {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        .alerta-edicao.active {
            display: flex;
        }

        .alerta-edicao i {
            color: #856404;
            font-size: 20px;
        }

        .alerta-edicao-texto {
            flex: 1;
            color: #856404;
            font-weight: 600;
            font-size: 14px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .preview-foto-container {
            margin-bottom: 15px;
            display: none;
            text-align: center;
        }

        .preview-foto-container.active {
            display: block;
        }

        .preview-foto {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--verde-principal);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            color: var(--cinza-escuro);
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--cinza-medio);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--verde-principal);
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--verde-principal);
            color: var(--branco);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--verde-escuro);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ========== LISTA DE PETS ========== */
        .pets-lista {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .pets-lista::-webkit-scrollbar {
            width: 6px;
        }

        .pets-lista::-webkit-scrollbar-track {
            background: var(--cinza-claro);
            border-radius: 10px;
        }

        .pets-lista::-webkit-scrollbar-thumb {
            background: var(--verde-principal);
            border-radius: 10px;
        }

        .pet-card {
            background: var(--branco);
            border: 1px solid var(--cinza-medio);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .pet-card:hover {
            border-color: var(--verde-principal);
            transform: translateX(3px);
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .pet-foto,
        .pet-icon-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .pet-foto {
            object-fit: cover;
            border: 2px solid var(--verde-principal);
        }

        .pet-icon-placeholder {
            background: var(--cinza-claro);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            border: 2px solid var(--cinza-medio);
        }

        .pet-info {
            flex: 1;
        }

        .pet-nome {
            font-size: 17px;
            font-weight: 700;
            color: var(--verde-escuro);
            margin-bottom: 6px;
        }

        .pet-detalhes {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12px;
            color: var(--cinza-escuro);
        }

        .pet-detalhe-item {
            background: var(--cinza-claro);
            padding: 4px 10px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pet-acoes {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-editar {
            background: var(--verde-principal);
            color: var(--branco);
        }

        .btn-editar:hover {
            background: var(--verde-escuro);
            transform: scale(1.1);
        }

        .btn-excluir {
            background: var(--vermelho);
            color: var(--branco);
        }

        .btn-excluir:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        /* ========== TABELA DE AGENDAMENTOS ========== */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table-agendamentos {
            width: 100%;
            border-collapse: collapse;
            background: var(--branco);
            font-size: 13px;
        }

        .table-agendamentos thead {
            background: var(--verde-principal);
        }

        .table-agendamentos th {
            color: var(--branco);
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .table-agendamentos tbody tr {
            border-bottom: 1px solid var(--cinza-medio);
            transition: all 0.3s ease;
        }

        .table-agendamentos tbody tr:hover {
            background: #f8f9fa;
        }

        .table-agendamentos td {
            padding: 12px 10px;
            font-size: 13px;
            color: var(--cinza-escuro);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmado {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-finalizado {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelado {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-table {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 2px;
        }

        .btn-primary {
            background: var(--verde-principal);
            color: var(--branco);
        }

        .btn-primary:hover {
            background: var(--verde-escuro);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(46, 139, 87, 0.3);
        }

        /* ========== MODAL ========== */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--branco);
            border-radius: 12px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: var(--verde-principal);
            color: var(--branco);
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: var(--branco);
            border: none;
            padding: 0;
        }

        .modal-close {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--branco);
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 20px;
        }

        .prontuario-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--verde-principal);
        }

        .prontuario-section h3 {
            color: var(--verde-escuro);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prontuario-text {
            color: var(--cinza-escuro);
            line-height: 1.6;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--cinza-medio);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-download {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
        }

        .btn-excel {
            background: #28a745;
            color: white;
        }

        .btn-imagem {
            background: #17a2b8;
            color: white;
        }

        .btn-download:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* ========== NOTIFICAÇÕES ========== */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--branco);
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 280px;
            animation: slideInRight 0.4s ease;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification.success {
            border-left: 5px solid var(--verde-sucesso);
        }

        .notification.error {
            border-left: 5px solid var(--vermelho);
        }

        .notification i {
            font-size: 20px;
        }

        .notification.success i {
            color: var(--verde-sucesso);
        }

        .notification.error i {
            color: var(--vermelho);
        }

        .notification-text {
            flex: 1;
            font-weight: 600;
            font-size: 14px;
            color: var(--cinza-escuro);
        }

        /* ========== LOADING ========== */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }

        .loading.active {
            display: flex;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid var(--cinza-medio);
            border-top-color: var(--verde-principal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--cinza-escuro);
        }

        .empty-state i {
            font-size: 60px;
            color: var(--cinza-medio);
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 500;
        }

        /* ========== RESPONSIVO ========== */
        @media (max-width: 1024px) {
            .pets-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .perfil-header {
                padding: 20px;
            }

            .tabs-navigation {
                flex-direction: column;
                align-items: stretch;
            }

            .tab-btn {
                width: 100%;
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .pet-card {
                flex-direction: column;
                text-align: center;
            }

            .pet-acoes {
                justify-content: center;
            }

            .table-container {
                overflow-x: scroll;
            }

            .modal-footer {
                justify-content: center;
            }
        }

        /* ========== MODAL PRONTUÁRIO MELHORADO ========== */
        #prontuarioParaDownload {
            background: white;
        }

        .prontuario-header-download {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, var(--verde-principal) 0%, var(--verde-escuro) 100%);
            color: white;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
        }

        .prontuario-header-download h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .prontuario-header-download p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .prontuario-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .prontuario-info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid var(--verde-principal);
        }

        .prontuario-info-item strong {
            display: block;
            color: var(--verde-escuro);
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .prontuario-info-item span {
            color: var(--cinza-escuro);
            font-size: 14px;
        }

        .prontuario-rodape {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--cinza-medio);
            text-align: center;
            color: var(--cinza-escuro);
            font-size: 12px;
        }

        .prontuario-assinatura {
            margin-top: 40px;
            text-align: center;
        }

        .prontuario-linha-assinatura {
            width: 300px;
            border-top: 2px solid var(--cinza-escuro);
            margin: 0 auto 10px;
        }

        .prontuario-assinatura p {
            font-size: 13px;
            color: var(--cinza-escuro);
            font-weight: 600;
        }
    </style>
</head>

<body>

    <?php include '../header.php'; ?>

    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="container-perfil">

        <!-- CABEÇALHO CENTRALIZADO -->
        <div class="perfil-header">
            <div class="avatar">
                <?php echo getIniciais($usuario_nome); ?>
            </div>
            <div class="perfil-info">
                <h1><?php echo htmlspecialchars($usuario_nome); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario_email); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($usuario_telefone); ?></p>
                <p><i class="fas fa-paw"></i> <?php echo $qtd_pets; ?> pet(s) cadastrado(s)</p>
            </div>
        </div>

        <!-- NAVEGAÇÃO DE ABAS CENTRALIZADA -->
        <div class="tabs-navigation">
            <button class="tab-btn <?php echo $aba_atual == 'informacoes' ? 'active' : ''; ?>" data-tab="informacoes">
                <i class="fas fa-user"></i>
                Minhas Informações
            </button>
            <button class="tab-btn <?php echo $aba_atual == 'pets' ? 'active' : ''; ?>" data-tab="pets">
                <i class="fas fa-paw"></i>
                Meus Pets
            </button>
            <button class="tab-btn <?php echo $aba_atual == 'agendamentos' ? 'active' : ''; ?>" data-tab="agendamentos">
                <i class="fas fa-calendar-alt"></i>
                Meus Agendamentos
            </button>
            <button class="tab-btn <?php echo $aba_atual == 'configuracoes' ? 'active' : ''; ?>"
                data-tab="configuracoes">
                <i class="fas fa-cog"></i>
                Configurações
            </button>
        </div>

        <!-- ========== ABA: MINHAS INFORMAÇÕES ========== -->
        <div class="tab-content <?php echo $aba_atual == 'informacoes' ? 'active' : ''; ?>" data-content="informacoes">
            <div class="card">
                <h2><i class="fas fa-id-card"></i> Minhas Informações</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong><i class="fas fa-user"></i> Nome Completo</strong>
                        <span><?php echo htmlspecialchars($usuario_nome); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><i class="fas fa-envelope"></i> E-mail</strong>
                        <span><?php echo htmlspecialchars($usuario_email); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><i class="fas fa-phone"></i> Telefone</strong>
                        <span><?php echo htmlspecialchars($usuario_telefone); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><i class="fas fa-birthday-cake"></i> Data de Nascimento</strong>
                        <span><?php echo htmlspecialchars($usuario_datanasc); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><i class="fas fa-venus-mars"></i> Gênero</strong>
                        <span><?php echo htmlspecialchars($usuario_genero); ?></span>
                    </div>
                    <div class="info-item">
                        <strong><i class="fas fa-paw"></i> Quantidade de Pets</strong>
                        <span><?php echo $qtd_pets; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== ABA: MEUS PETS ========== -->
        <div class="tab-content <?php echo $aba_atual == 'pets' ? 'active' : ''; ?>" data-content="pets">
            <div class="pets-grid">

                <!-- FORMULÁRIO -->
                <div class="form-pet">
                    <h3 id="form-title">
                        <i class="fas fa-plus-circle"></i>
                        Cadastrar Novo Pet
                    </h3>

                    <!-- ALERTA DE EDIÇÃO -->
                    <div class="alerta-edicao" id="alerta-edicao">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="alerta-edicao-texto" id="alerta-edicao-texto">
                            Você está editando: <strong id="nome-pet-editando"></strong>
                        </div>
                    </div>

                    <!-- PREVIEW DA FOTO -->
                    <div class="preview-foto-container" id="preview-foto-container">
                        <img src="" alt="Preview" class="preview-foto" id="preview-foto">
                    </div>

                    <form id="formPet" enctype="multipart/form-data">
                        <input type="hidden" id="pet_id" name="pet_id">

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nome do Pet *</label>
                            <input type="text" name="nome_pet" id="nome_pet" required placeholder="Ex: Rex">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-dog"></i> Espécie *</label>
                            <select name="especie" id="especie" required>
                                <option value="">Selecione...</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT DISTINCT nome FROM Especies ORDER BY nome");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . htmlspecialchars($row['nome']) . "'>" .
                                            htmlspecialchars($row['nome']) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option disabled>Erro ao carregar</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-dna"></i> Raça</label>
                            <input type="text" name="raca" id="raca" placeholder="Ex: Golden Retriever">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Sexo</label>
                            <select name="sexo" id="sexo">
                                <option value="">Não informado</option>
                                <option value="Macho">Macho</option>
                                <option value="Fêmea">Fêmea</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-weight"></i> Porte</label>
                            <select name="porte" id="porte">
                                <option value="">Não informado</option>
                                <option value="Pequeno">Pequeno</option>
                                <option value="Medio">Médio</option>
                                <option value="Grande">Grande</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Data de Nascimento</label>
                            <input type="date" name="datanasc_pet" id="datanasc_pet" max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-camera"></i> Foto do Pet</label>
                            <input type="file" name="foto_pet" id="foto_pet" accept="image/*">
                        </div>

                        <button type="submit" class="btn-submit" id="btn-submit-pet">
                            <i class="fas fa-save"></i>
                            <span id="btn-text">Cadastrar Pet</span>
                        </button>

                        <button type="button" class="btn-submit" id="btn-cancelar"
                            style="background: var(--cinza-escuro); margin-top: 10px; display: none;"
                            onclick="cancelarEdicao()">
                            <i class="fas fa-times"></i>
                            Cancelar Edição
                        </button>
                    </form>
                </div>

                <!-- LISTA DE PETS -->
                <div class="form-pet">
                    <h3><i class="fas fa-list"></i> Meus Pets Cadastrados</h3>

                    <div class="pets-lista" id="pets-lista">
                        <?php if (count($pets) > 0): ?>
                            <?php foreach ($pets as $pet): ?>
                                <div class="pet-card">
                                    <?php if ($pet['foto']): ?>
                                        <img src="../../assets/uploads/pets/<?php echo htmlspecialchars($pet['foto']); ?>"
                                            alt="<?php echo htmlspecialchars($pet['nome']); ?>" class="pet-foto">
                                    <?php else: ?>
                                        <div class="pet-icon-placeholder">
                                            <?php echo getEmojiPorEspecie($pet['especie_nome']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pet-info">
                                        <div class="pet-nome"><?php echo htmlspecialchars($pet['nome']); ?></div>
                                        <div class="pet-detalhes">
                                            <span class="pet-detalhe-item">
                                                <i class="fas fa-dog"></i>
                                                <?php echo htmlspecialchars($pet['especie_nome']); ?>
                                            </span>
                                            <?php if ($pet['raca']): ?>
                                                <span class="pet-detalhe-item">
                                                    <i class="fas fa-dna"></i>
                                                    <?php echo htmlspecialchars($pet['raca']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($pet['sexo']): ?>
                                                <span class="pet-detalhe-item">
                                                    <i class="fas fa-venus-mars"></i>
                                                    <?php echo htmlspecialchars($pet['sexo']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($pet['porte']): ?>
                                                <span class="pet-detalhe-item">
                                                    <i class="fas fa-weight"></i>
                                                    <?php echo htmlspecialchars($pet['porte']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="pet-acoes">
                                        <button class="btn-icon btn-editar"
                                            onclick='editarPet(<?php echo json_encode($pet); ?>)' title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon btn-excluir"
                                            onclick="excluirPet(<?php echo $pet['id']; ?>, '<?php echo htmlspecialchars($pet['nome']); ?>')"
                                            title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-paw"></i>
                                <p>Você ainda não cadastrou nenhum pet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========== ABA: MEUS AGENDAMENTOS ========== -->
        <div class="tab-content <?php echo $aba_atual == 'agendamentos' ? 'active' : ''; ?>"
            data-content="agendamentos">
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i> Meus Agendamentos</h2>

                <?php if (count($agendamentos) > 0): ?>
                    <div class="table-container">
                        <table class="table-agendamentos">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Animal</th>
                                    <th>Data</th>
                                    <th>Hora Início</th>
                                    <th>Hora Final</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agendamentos as $ag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ag['servico_nome']); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php if ($ag['animal_foto']): ?>
                                                    <img src="../../assets/uploads/pets/<?php echo htmlspecialchars($ag['animal_foto']); ?>"
                                                        style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <span
                                                        style="font-size: 20px;"><?php echo getEmojiPorEspecie($ag['especie']); ?></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($ag['animal_nome']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($ag['data_hora'])); ?></td>
                                        <td><?php echo $ag['hora_inicio']; ?></td>
                                        <td><?php echo $ag['hora_final']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($ag['status']); ?>">
                                                <?php echo htmlspecialchars($ag['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ag['observacoes']): ?>
                                                <button class="btn-table btn-primary"
                                                    onclick="alert('<?php echo htmlspecialchars(addslashes($ag['observacoes'])); ?>')">
                                                    <i class="fas fa-comment"></i>
                                                    Observações
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($ag['status'] == 'finalizado' && $ag['consulta_id']): ?>
                                                <button class="btn-table btn-primary"
                                                    onclick="verProntuario(<?php echo $ag['id']; ?>)">
                                                    <i class="fas fa-file-medical"></i>
                                                    Ver Prontuário
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>Você ainda não possui agendamentos.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== ABA: CONFIGURAÇÕES ========== -->
        <div class="tab-content <?php echo $aba_atual == 'configuracoes' ? 'active' : ''; ?>"
            data-content="configuracoes">
            <div class="pets-grid">

                <!-- EDITAR PERFIL -->
                <div class="form-pet">
                    <h3><i class="fas fa-user-edit"></i> Editar Perfil</h3>

                    <form id="formPerfil">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nome Completo *</label>
                            <input type="text" name="nome" id="perfil_nome" required
                                value="<?php echo htmlspecialchars($usuario_nome); ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> E-mail</label>
                            <input type="email" disabled value="<?php echo htmlspecialchars($usuario_email); ?>"
                                style="background: var(--cinza-claro); cursor: not-allowed;">
                            <small style="color: var(--cinza-escuro); display: block; margin-top: 5px;">
                                O e-mail não pode ser alterado
                            </small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Telefone *</label>
                            <input type="tel" name="telefone" id="perfil_telefone" required
                                value="<?php echo htmlspecialchars($usuario_telefone); ?>"
                                placeholder="(00) 00000-0000">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-birthday-cake"></i> Data de Nascimento *</label>
                            <input type="date" name="datanasc" id="perfil_datanasc" required
                                max="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo isset($usuario_completo['datanasc']) ? $usuario_completo['datanasc'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Gênero *</label>
                            <select name="genero" id="perfil_genero" required>
                                <option value="">Selecione...</option>
                                <option value="Masculino" <?php echo $usuario_genero == 'Masculino' ? 'selected' : ''; ?>>
                                    Masculino</option>
                                <option value="Feminino" <?php echo $usuario_genero == 'Feminino' ? 'selected' : ''; ?>>
                                    Feminino</option>
                                <option value="Outro" <?php echo $usuario_genero == 'Outro' ? 'selected' : ''; ?>>Outro
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                    </form>
                </div>

                <!-- ALTERAR SENHA -->
                <div class="form-pet">
                    <h3><i class="fas fa-key"></i> Alterar Senha</h3>

                    <form id="formSenha">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Senha Atual *</label>
                            <input type="password" name="senha_atual" id="senha_atual" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Nova Senha *</label>
                            <input type="password" name="nova_senha" id="nova_senha" required minlength="8">
                            <small style="color: var(--cinza-escuro); display: block; margin-top: 5px;">
                                Mínimo 8 caracteres
                            </small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirmar Nova Senha *</label>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="8">
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </button>
                    </form>
                </div>

            </div>

            <!-- BOTÃO SAIR -->
            <div style="text-align: center; margin-top: 30px;">
                <form action="../logout.php" method="POST">
                    <button type="submit" class="btn-submit"
                        style="max-width: 300px; margin: 0 auto; background: var(--vermelho);">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair da Conta
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- MODAL DE PRONTUÁRIO -->
    <div class="modal" id="modalProntuario">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-medical"></i> Prontuário Médico</h2>
                <button class="modal-close" onclick="fecharModal()">&times;</button>
            </div>

            <!-- CONTEÚDO PARA DOWNLOAD -->
            <div class="modal-body" id="prontuarioParaDownload">
                <div id="prontuarioConteudo">
                    <!-- Conteúdo será carregado via AJAX -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-download btn-pdf" onclick="baixarPDF()">
                    <i class="fas fa-file-pdf"></i>
                    Baixar PDF
                </button>
                <button class="btn-download btn-excel" onclick="baixarExcel()">
                    <i class="fas fa-file-excel"></i>
                    Baixar Excel
                </button>
                <button class="btn-download btn-imagem" onclick="baixarImagem()">
                    <i class="fas fa-image"></i>
                    Baixar Imagem
                </button>
            </div>
        </div>
    </div>

    <script>
        // ========== GERENCIAMENTO DE ABAS ==========
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const tab = this.dataset.tab;

                // Remover active de todos
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Adicionar active no clicado
                this.classList.add('active');
                document.querySelector(`[data-content="${tab}"]`).classList.add('active');

                // Atualizar URL
                const url = new URL(window.location);
                url.searchParams.set('aba', tab);
                window.history.pushState({}, '', url);
            });
        });

        // ========== NOTIFICAÇÕES ==========
        function mostrarNotificacao(mensagem, tipo = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${tipo}`;
            notification.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <div class="notification-text">${mensagem}</div>
    `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideInRight 0.4s ease reverse';
                setTimeout(() => notification.remove(), 400);
            }, 4000);
        }

        // ========== LOADING ==========
        function mostrarLoading() {
            document.getElementById('loading').classList.add('active');
        }

        function esconderLoading() {
            document.getElementById('loading').classList.remove('active');
        }

        // ========== FORMULÁRIO DE PET (AJAX) ==========
        document.getElementById('formPet').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('acao', 'salvar_pet');

            const btnSubmit = document.getElementById('btn-submit-pet');
            const btnText = document.getElementById('btn-text');
            const textOriginal = btnText.textContent;

            btnSubmit.disabled = true;
            btnText.textContent = 'Salvando...';
            mostrarLoading();

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarNotificacao(data.message, 'success');
                    this.reset();
                    document.getElementById('pet_id').value = '';
                    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Cadastrar Novo Pet';
                    document.getElementById('btn-cancelar').style.display = 'none';
                    btnText.textContent = 'Cadastrar Pet';

                    // Esconder alerta de edição e preview
                    document.getElementById('alerta-edicao').classList.remove('active');
                    document.getElementById('preview-foto-container').classList.remove('active');

                    // Recarregar lista de pets
                    await recarregarPets();
                } else {
                    mostrarNotificacao(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao salvar pet. Tente novamente.', 'error');
            } finally {
                btnSubmit.disabled = false;
                if (textOriginal !== 'Salvando...') {
                    btnText.textContent = textOriginal;
                }
                esconderLoading();
            }
        });

        // ========== EDITAR PET ==========
        function editarPet(pet) {
            document.getElementById('pet_id').value = pet.id;
            document.getElementById('nome_pet').value = pet.nome;
            document.getElementById('especie').value = pet.especie_nome;
            document.getElementById('raca').value = pet.raca || '';
            document.getElementById('sexo').value = pet.sexo || '';
            document.getElementById('porte').value = pet.porte || '';
            document.getElementById('datanasc_pet').value = pet.datanasc || '';

            // Atualizar título e botão
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Editar Pet';
            document.getElementById('btn-text').textContent = 'Atualizar Pet';
            document.getElementById('btn-cancelar').style.display = 'block';

            // Mostrar alerta de edição
            document.getElementById('alerta-edicao').classList.add('active');
            document.getElementById('nome-pet-editando').textContent = pet.nome;

            // Mostrar preview da foto se existir
            if (pet.foto) {
                const previewContainer = document.getElementById('preview-foto-container');
                const previewImg = document.getElementById('preview-foto');
                previewImg.src = '../../assets/uploads/pets/' + pet.foto;
                previewContainer.classList.add('active');
            } else {
                document.getElementById('preview-foto-container').classList.remove('active');
            }

            // Scroll para o formulário
            document.querySelector('.form-pet').scrollIntoView({ behavior: 'smooth' });
        }

        // ========== CANCELAR EDIÇÃO ==========
        function cancelarEdicao() {
            document.getElementById('formPet').reset();
            document.getElementById('pet_id').value = '';
            document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> Cadastrar Novo Pet';
            document.getElementById('btn-text').textContent = 'Cadastrar Pet';
            document.getElementById('btn-cancelar').style.display = 'none';

            // Esconder alerta de edição e preview
            document.getElementById('alerta-edicao').classList.remove('active');
            document.getElementById('preview-foto-container').classList.remove('active');
        }

        // ========== EXCLUIR PET (AJAX) ==========
        async function excluirPet(petId, petNome) {
            if (!confirm(`Tem certeza que deseja excluir ${petNome}?`)) {
                return;
            }

            mostrarLoading();

            try {
                const formData = new FormData();
                formData.append('acao', 'excluir_pet');
                formData.append('pet_id', petId);

                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarNotificacao(data.message, 'success');
                    await recarregarPets();
                } else {
                    mostrarNotificacao(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao excluir pet. Tente novamente.', 'error');
            } finally {
                esconderLoading();
            }
        }

        // ========== RECARREGAR LISTA DE PETS ==========
        async function recarregarPets() {
            try {
                const formData = new FormData();
                formData.append('acao', 'buscar_pets');

                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const lista = document.getElementById('pets-lista');

                    if (data.pets.length === 0) {
                        lista.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-paw"></i>
                        <p>Você ainda não cadastrou nenhum pet.</p>
                    </div>
                `;
                    } else {
                        lista.innerHTML = data.pets.map(pet => {
                            const emojis = {
                                'Cachorro': '🐕',
                                'Gato': '🐈',
                                'Hamster': '🐹',
                                'Peixe': '🐠',
                                'Pássaro': '🐦',
                                'Coelho': '🐰',
                                'Tartaruga': '🐢'
                            };
                            const emoji = emojis[pet.especie_nome] || '🐾';

                            return `
                        <div class="pet-card">
                            ${pet.foto ?
                                    `<img src="../../assets/uploads/pets/${pet.foto}" alt="${pet.nome}" class="pet-foto">` :
                                    `<div class="pet-icon-placeholder">${emoji}</div>`
                                }
                            <div class="pet-info">
                                <div class="pet-nome">${pet.nome}</div>
                                <div class="pet-detalhes">
                                    <span class="pet-detalhe-item">
                                        <i class="fas fa-dog"></i> ${pet.especie_nome}
                                    </span>
                                    ${pet.raca ? `<span class="pet-detalhe-item"><i class="fas fa-dna"></i> ${pet.raca}</span>` : ''}
                                    ${pet.sexo ? `<span class="pet-detalhe-item"><i class="fas fa-venus-mars"></i> ${pet.sexo}</span>` : ''}
                                    ${pet.porte ? `<span class="pet-detalhe-item"><i class="fas fa-weight"></i> ${pet.porte}</span>` : ''}
                                </div>
                            </div>
                            <div class="pet-acoes">
                                <button class="btn-icon btn-editar" onclick='editarPet(${JSON.stringify(pet)})' title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-excluir" onclick="excluirPet(${pet.id}, '${pet.nome.replace(/'/g, "\\'")}')" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                        }).join('');
                    }
                }
            } catch (error) {
                console.error('Erro ao recarregar pets:', error);
            }
        }

        // ========== ATUALIZAR PERFIL (AJAX) ==========
        document.getElementById('formPerfil').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('acao', 'atualizar_perfil');

            mostrarLoading();

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarNotificacao(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarNotificacao(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao atualizar perfil. Tente novamente.', 'error');
            } finally {
                esconderLoading();
            }
        });

        // ========== ALTERAR SENHA (AJAX) ==========
        document.getElementById('formSenha').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('acao', 'alterar_senha');

            mostrarLoading();

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    mostrarNotificacao(data.message, 'success');
                    this.reset();
                } else {
                    mostrarNotificacao(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao alterar senha. Tente novamente.', 'error');
            } finally {
                esconderLoading();
            }
        });

        // ========== VER PRONTUÁRIO (AJAX) - VERSÃO MELHORADA ==========
        let prontuarioAtual = null; // Guardar dados do prontuário

        async function verProntuario(agendamentoId) {
            mostrarLoading();

            try {
                const formData = new FormData();
                formData.append('acao', 'buscar_prontuario');
                formData.append('agendamento_id', agendamentoId);

                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    const p = data.prontuario;
                    prontuarioAtual = p; // Salvar para downloads

                    const emojis = {
                        'Cachorro': '🐕',
                        'Gato': '🐈',
                        'Hamster': '🐹',
                        'Peixe': '🐠',
                        'Pássaro': '🐦',
                        'Coelho': '🐰',
                        'Tartaruga': '🐢'
                    };
                    const emoji = emojis[p.especie] || '🐾';

                    const dataFormatada = new Date(p.data_consulta).toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    document.getElementById('prontuarioConteudo').innerHTML = `
                <!-- CABEÇALHO -->
                <div class="prontuario-header-download">
                    <h1><i class="fas fa-paw"></i> PetCare - Clínica Veterinária</h1>
                    <p>Prontuário Médico Veterinário</p>
                </div>

                <!-- INFORMAÇÕES DO PACIENTE -->
                <div class="prontuario-section">
                    <h3><i class="fas fa-paw"></i> Dados do Paciente</h3>
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        ${p.animal_foto ?
                            `<img src="../../assets/uploads/pets/${p.animal_foto}" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--verde-principal);">` :
                            `<div style="width: 80px; height: 80px; border-radius: 50%; background: var(--cinza-claro); display: flex; align-items: center; justify-content: center; font-size: 40px; border: 3px solid var(--cinza-medio);">${emoji}</div>`
                        }
                        <div style="flex: 1;">
                            <div class="prontuario-info-grid">
                                <div class="prontuario-info-item">
                                    <strong>Nome do Animal</strong>
                                    <span>${p.animal_nome}</span>
                                </div>
                                <div class="prontuario-info-item">
                                    <strong>Espécie</strong>
                                    <span>${p.especie}</span>
                                </div>
                                <div class="prontuario-info-item">
                                    <strong>Serviço Realizado</strong>
                                    <span>${p.servico_nome}</span>
                                </div>
                                <div class="prontuario-info-item">
                                    <strong>Data do Atendimento</strong>
                                    <span>${dataFormatada}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- INFORMAÇÕES DO PROFISSIONAL -->
                <div class="prontuario-section">
                    <h3><i class="fas fa-user-md"></i> Profissional Responsável</h3>
                    <div class="prontuario-info-grid">
                        <div class="prontuario-info-item">
                            <strong>Veterinário(a)</strong>
                            <span>Dr(a). ${p.veterinario_nome || 'Não informado'}</span>
                        </div>
                        <div class="prontuario-info-item">
                            <strong>Data da Consulta</strong>
                            <span>${dataFormatada}</span>
                        </div>
                    </div>
                </div>

                <!-- DIAGNÓSTICO -->
                ${p.diagnostico ? `
                    <div class="prontuario-section">
                        <h3><i class="fas fa-stethoscope"></i> Diagnóstico</h3>
                        <div class="prontuario-text">${p.diagnostico}</div>
                    </div>
                ` : ''}

                <!-- TRATAMENTO -->
                ${p.tratamento ? `
                    <div class="prontuario-section">
                        <h3><i class="fas fa-pills"></i> Tratamento Prescrito</h3>
                        <div class="prontuario-text">${p.tratamento}</div>
                    </div>
                ` : ''}

                <!-- RECEITA -->
                ${p.receita ? `
                    <div class="prontuario-section">
                        <h3><i class="fas fa-prescription"></i> Receita/Medicamentos</h3>
                        <div class="prontuario-text">${p.receita}</div>
                    </div>
                ` : ''}

                <!-- OBSERVAÇÕES -->
                ${p.observacoes ? `
                    <div class="prontuario-section">
                        <h3><i class="fas fa-clipboard"></i> Observações Adicionais</h3>
                        <div class="prontuario-text">${p.observacoes}</div>
                    </div>
                ` : ''}

                <!-- ASSINATURA -->
                <div class="prontuario-assinatura">
                    <div class="prontuario-linha-assinatura"></div>
                    <p>Dr(a). ${p.veterinario_nome || 'Veterinário Responsável'}</p>
                    <p style="font-size: 11px; color: var(--cinza-escuro); margin-top: 5px;">CRMV: __________</p>
                </div>

                <!-- RODAPÉ -->
                <div class="prontuario-rodape">
                    <p><strong>PetCare - Clínica Veterinária</strong></p>
                    <p>Documento gerado em: ${new Date().toLocaleString('pt-BR')}</p>
                    <p>Este documento é confidencial e destinado exclusivamente ao tutor do animal.</p>
                </div>
            `;

                    document.getElementById('modalProntuario').classList.add('active');
                } else {
                    mostrarNotificacao(data.message, 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarNotificacao('Erro ao carregar prontuário. Tente novamente.', 'error');
            } finally {
                esconderLoading();
            }
        }

        // ========== FECHAR MODAL ==========
        function fecharModal() {
            document.getElementById('modalProntuario').classList.remove('active');
        }

        // Fechar modal clicando fora
        window.onclick = function (event) {
            const modal = document.getElementById('modalProntuario');
            if (event.target === modal) {
                fecharModal();
            }
        }

        // ========== BAIXAR PDF ==========
        function baixarPDF() {
            if (!prontuarioAtual) {
                alert('Erro: Prontuário não carregado');
                return;
            }

            mostrarLoading();

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Configurações
            const margemEsq = 15;
            const margemDir = 195;
            let y = 20;

            // CABEÇALHO
            doc.setFillColor(46, 139, 87);
            doc.rect(0, 0, 210, 35, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(20);
            doc.setFont(undefined, 'bold');
            doc.text('PetCare - Clínica Veterinária', 105, 15, { align: 'center' });
            doc.setFontSize(12);
            doc.text('Prontuário Médico Veterinário', 105, 25, { align: 'center' });

            y = 45;
            doc.setTextColor(0, 0, 0);

            // DADOS DO PACIENTE
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.text('Dados do Paciente', margemEsq, y);
            y += 10;

            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text(`Animal: ${prontuarioAtual.animal_nome}`, margemEsq, y);
            y += 7;
            doc.text(`Espécie: ${prontuarioAtual.especie}`, margemEsq, y);
            y += 7;
            doc.text(`Serviço: ${prontuarioAtual.servico_nome}`, margemEsq, y);
            y += 7;
            doc.text(`Data: ${new Date(prontuarioAtual.data_consulta).toLocaleString('pt-BR')}`, margemEsq, y);
            y += 12;

            // PROFISSIONAL
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.text('Profissional Responsável', margemEsq, y);
            y += 10;

            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text(`Veterinário(a): Dr(a). ${prontuarioAtual.veterinario_nome || 'Não informado'}`, margemEsq, y);
            y += 12;

            // DIAGNÓSTICO
            if (prontuarioAtual.diagnostico) {
                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Diagnóstico', margemEsq, y);
                y += 10;

                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                const diagnosticoLinhas = doc.splitTextToSize(prontuarioAtual.diagnostico, margemDir - margemEsq);
                doc.text(diagnosticoLinhas, margemEsq, y);
                y += (diagnosticoLinhas.length * 7) + 5;
            }

            // TRATAMENTO
            if (prontuarioAtual.tratamento) {
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Tratamento Prescrito', margemEsq, y);
                y += 10;

                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                const tratamentoLinhas = doc.splitTextToSize(prontuarioAtual.tratamento, margemDir - margemEsq);
                doc.text(tratamentoLinhas, margemEsq, y);
                y += (tratamentoLinhas.length * 7) + 5;
            }

            // RECEITA
            if (prontuarioAtual.receita) {
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Receita/Medicamentos', margemEsq, y);
                y += 10;

                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                const receitaLinhas = doc.splitTextToSize(prontuarioAtual.receita, margemDir - margemEsq);
                doc.text(receitaLinhas, margemEsq, y);
                y += (receitaLinhas.length * 7) + 5;
            }

            // OBSERVAÇÕES
            if (prontuarioAtual.observacoes) {
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(14);
                doc.setFont(undefined, 'bold');
                doc.text('Observações', margemEsq, y);
                y += 10;

                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                const obsLinhas = doc.splitTextToSize(prontuarioAtual.observacoes, margemDir - margemEsq);
                doc.text(obsLinhas, margemEsq, y);
                y += (obsLinhas.length * 7) + 15;
            }

            // ASSINATURA
            if (y > 250) {
                doc.addPage();
                y = 220;
            } else {
                y = 250;
            }

            doc.line(70, y, 140, y);
            y += 5;
            doc.setFontSize(11);
            doc.text(`Dr(a). ${prontuarioAtual.veterinario_nome || 'Veterinário Responsável'}`, 105, y, { align: 'center' });
            y += 5;
            doc.setFontSize(9);
            doc.text('CRMV: __________', 105, y, { align: 'center' });

            // RODAPÉ
            doc.setFontSize(9);
            doc.setTextColor(100, 100, 100);
            doc.text('PetCare - Clínica Veterinária', 105, 285, { align: 'center' });
            doc.text(`Documento gerado em: ${new Date().toLocaleString('pt-BR')}`, 105, 290, { align: 'center' });

            // SALVAR
            const nomeArquivo = `Prontuario_${prontuarioAtual.animal_nome.replace(/\s+/g, '_')}_${Date.now()}.pdf`;
            doc.save(nomeArquivo);

            esconderLoading();
            mostrarNotificacao('PDF baixado com sucesso!', 'success');
        }

        // ========== BAIXAR EXCEL (CSV) ==========
        function baixarExcel() {
            if (!prontuarioAtual) {
                alert('Erro: Prontuário não carregado');
                return;
            }

            const p = prontuarioAtual;

            // Criar CSV
            let csv = '\uFEFF'; // BOM para UTF-8
            csv += 'PRONTUÁRIO MÉDICO VETERINÁRIO - PETCARE\n\n';
            csv += 'DADOS DO PACIENTE\n';
            csv += `Animal,${p.animal_nome}\n`;
            csv += `Espécie,${p.especie}\n`;
            csv += `Serviço,${p.servico_nome}\n`;
            csv += `Data do Atendimento,${new Date(p.data_consulta).toLocaleString('pt-BR')}\n\n`;

            csv += 'PROFISSIONAL RESPONSÁVEL\n';
            csv += `Veterinário(a),Dr(a). ${p.veterinario_nome || 'Não informado'}\n\n`;

            if (p.diagnostico) {
                csv += 'DIAGNÓSTICO\n';
                csv += `"${p.diagnostico.replace(/"/g, '""')}"\n\n`;
            }

            if (p.tratamento) {
                csv += 'TRATAMENTO PRESCRITO\n';
                csv += `"${p.tratamento.replace(/"/g, '""')}"\n\n`;
            }

            if (p.receita) {
                csv += 'RECEITA/MEDICAMENTOS\n';
                csv += `"${p.receita.replace(/"/g, '""')}"\n\n`;
            }

            if (p.observacoes) {
                csv += 'OBSERVAÇÕES\n';
                csv += `"${p.observacoes.replace(/"/g, '""')}"\n\n`;
            }

            csv += `Documento gerado em,${new Date().toLocaleString('pt-BR')}\n`;

            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            const nomeArquivo = `Prontuario_${p.animal_nome.replace(/\s+/g, '_')}_${Date.now()}.csv`;

            link.setAttribute('href', url);
            link.setAttribute('download', nomeArquivo);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            mostrarNotificacao('Excel/CSV baixado com sucesso!', 'success');
        }

        // ========== BAIXAR IMAGEM ==========
        function baixarImagem() {
            if (!prontuarioAtual) {
                alert('Erro: Prontuário não carregado');
                return;
            }

            mostrarLoading();

            const elemento = document.getElementById('prontuarioParaDownload');

            html2canvas(elemento, {
                scale: 2,
                backgroundColor: '#ffffff',
                logging: false,
                useCORS: true
            }).then(canvas => {
                const nomeArquivo = `Prontuario_${prontuarioAtual.animal_nome.replace(/\s+/g, '_')}_${Date.now()}.png`;

                canvas.toBlob(blob => {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = nomeArquivo;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);

                    esconderLoading();
                    mostrarNotificacao('Imagem baixada com sucesso!', 'success');
                });
            }).catch(error => {
                console.error('Erro ao gerar imagem:', error);
                esconderLoading();
                mostrarNotificacao('Erro ao gerar imagem', 'error');
            });
        }
    </script>

</body>

</html>