<?php
// Inicia a sessão
session_start();
require_once '../conexao.php';

// Verifica login e tipo de usuário
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    header("Location: ../../index.php");
    exit;
}

$usuario_id = $_SESSION['id'];

// Carrega dados do usuário
try {
    $stmt = $pdo->prepare("SELECT nome, email, telefone, foto FROM Usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $_SESSION['nome'] = $usuario['nome'] ?? $_SESSION['nome'] ?? 'Secretaria';
    $_SESSION['email'] = $usuario['email'] ?? $_SESSION['email'] ?? 'sec@sec.com';
    $_SESSION['telefone'] = $usuario['telefone'] ?? $_SESSION['telefone'] ?? '';
    $_SESSION['foto'] = $usuario['foto'] ?? $_SESSION['foto'] ?? 'default_profile.jpg';
} catch (Exception $e) {
    error_log("Erro ao carregar perfil: " . $e->getMessage());
}

// Variáveis locais
$nome_usuario = $_SESSION['nome'];
$email_usuario = $_SESSION['email'];
$telefone_usuario = $_SESSION['telefone'];
$profile_picture = $_SESSION['foto'];

// Calcula iniciais
$nomes = explode(' ', trim($nome_usuario));
$iniciais = count($nomes) > 0 ? strtoupper(substr($nomes[0], 0, 1) . (count($nomes) > 1 ? substr(end($nomes), 0, 1) : '')) : 'SC';

// Função: Atualizar perfil
function updateProfile($pdo, $user_id, $new_name, $new_email, $new_telefone, $file, &$profile_picture, &$nome_usuario, &$email_usuario, &$telefone_usuario)
{
    try {
        if (empty($new_name) || strlen($new_name) < 2 || strlen($new_name) > 100) {
            throw new Exception("O nome deve ter entre 2 e 100 caracteres.");
        }
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido.");
        }
        if (!empty($new_telefone) && !preg_match('/^\(\d{2}\)\s?\d{4,5}-\d{4}$/', $new_telefone)) {
            throw new Exception("Telefone inválido. Use (XX) XXXX-XXXX.");
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Este email já está em uso.");
        }

        $old_picture = $profile_picture;
        $new_filename = $old_picture;
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/uploads/perfil/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024;
            $type = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : $file['type'];

            if (!in_array($type, $allowed)) throw new Exception("Apenas JPEG, PNG ou GIF.");
            if ($file['size'] > $max_size) throw new Exception("Imagem muito grande (máx. 2MB).");

            $ext = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif'][$type] ?? '';
            $new_filename = "profile_{$user_id}_" . time() . ".{$ext}";
            $dest = $upload_dir . $new_filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new Exception("Erro ao salvar imagem.");
            }

            if ($old_picture !== 'default_profile.jpg' && file_exists($upload_dir . $old_picture)) {
                unlink($upload_dir . $old_picture);
            }
        }

        $stmt = $pdo->prepare("UPDATE Usuarios SET nome = ?, email = ?, telefone = ?, foto = ? WHERE id = ?");
        $stmt->execute([$new_name, $new_email, $new_telefone, $new_filename, $user_id]);

        $_SESSION['nome'] = $new_name;
        $_SESSION['email'] = $new_email;
        $_SESSION['telefone'] = $new_telefone;
        $_SESSION['foto'] = $new_filename;

        $nome_usuario = $new_name;
        $email_usuario = $new_email;
        $telefone_usuario = $new_telefone;
        $profile_picture = $new_filename;

        return ['success' => true, 'message' => 'Perfil atualizado com sucesso!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Função: Atualizar senha
function updatePassword($pdo, $user_id, $current, $new)
{
    try {
        if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[!@#$%^&*]/', $new)) {
            throw new Exception("Senha fraca. Use 8+ caracteres com maiúscula, número e símbolo.");
        }

        $stmt = $pdo->prepare("SELECT senha_hash FROM Usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            throw new Exception("Senha atual incorreta.");
        }

        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Usuarios SET senha_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);

        return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Processa formulários
$success_message = $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $result = updateProfile(
        $pdo, $_SESSION['id'],
        $_POST['nome'] ?? '',
        $_POST['email'] ?? '',
        $_POST['telefone'] ?? '',
        $_FILES['profile_picture'] ?? null,
        $profile_picture, $nome_usuario, $email_usuario, $telefone_usuario
    );
    $success_message = $result['success'] ? $result['message'] : '';
    $error_message = !$result['success'] ? $result['message'] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $result = updatePassword($pdo, $_SESSION['id'], $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
    $success_message = $result['success'] ? $result['message'] : '';
    $error_message = !$result['success'] ? $result['message'] : '';
}

$paginaTitulo = "Perfil - Secretaria";
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
            padding: 32px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
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

        .profile-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 32px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .profile-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .profile-header h2 i {
            color: var(--primary);
        }

        .profile-header p {
            color: var(--gray-500);
            font-size: 14px;
            margin-top: 8px;
        }

        .profile-picture-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            position: relative;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .profile-picture-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 36px;
            border: 4px solid var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
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
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group input[type="file"] {
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            background: var(--white);
            color: var(--gray-900);
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group input[type="file"] {
            padding: 8px;
            cursor: pointer;
        }

        .btn {
            padding: 12px 24px;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            opacity: 0.7;
            transform: none;
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .password-strength {
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            background: var(--danger);
        }

        .form-progress {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--gray-600);
        }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s ease;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin: 32px 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: span 1;
            }

            .page-header {
                padding: 16px;
            }

            .page-title h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-user-cog"></i> Meu Perfil</h1>
                </div>
            </div>

            <div class="profile-card">
                <div class="profile-header">
                    <h2><i class="fas fa-user"></i> Editar Perfil</h2>
                    <p>Atualize suas informações pessoais e foto</p>
                </div>

                <?php if ($success_message): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-picture-container">
                    <?php if ($profile_picture && $profile_picture !== 'default_profile.jpg' && file_exists("../../assets/uploads/perfil/$profile_picture")): ?>
                        <img src="../../assets/uploads/perfil/<?= htmlspecialchars($profile_picture) ?>?t=<?= time() ?>"
                             alt="Foto de Perfil" class="profile-picture" id="preview">
                    <?php else: ?>
                        <div class="profile-picture-placeholder" id="placeholder">
                            <?= htmlspecialchars($iniciais) ?>
                        </div>
                    <?php endif; ?>
                </div>

         

                <form method="POST" enctype="multipart/form-data" class="form-grid" id="profile-form">
                    <div class="form-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($nome_usuario) ?>" required minlength="2" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email_usuario) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($telefone_usuario) ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Foto de Perfil</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                        <small style="color: var(--gray-500);">JPEG, PNG, GIF — máx. 2MB</small>
                    </div>
                    <div class="form-group full">
                        <button type="submit" name="update_profile" class="btn btn-primary" id="save-profile">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>

                <h3 class="section-title"><i class="fas fa-key"></i> Alterar Senha</h3>
                <form method="POST" class="form-grid" id="password-form">
                    <div class="form-group">
                        <label for="current_password">Senha Atual</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nova Senha</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-strength"><div class="password-strength-bar"></div></div>
                        <small style="color: var(--gray-500);">Mín. 8 caracteres com maiúscula, número e símbolo</small>
                    </div>
                    <div class="form-group full">
                        <button type="submit" name="update_password" class="btn btn-primary" id="save-password">
                            <i class="fas fa-lock"></i> Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Elementos
            const profileForm = document.getElementById('profile-form');
            const passwordForm = document.getElementById('password-form');
            const fileInput = document.getElementById('profile_picture');
            const preview = document.getElementById('preview');
            const placeholder = document.getElementById('placeholder');
            const savePasswordBtn = document.getElementById('save-password');
            const currentPass = document.getElementById('current_password');
            const newPass = document.getElementById('new_password');
            const strengthBar = document.querySelector('.password-strength-bar');

            // Máscara de telefone
            document.getElementById('telefone').addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                else if (v.length > 6) v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,4})/, '($1) $2');
                e.target.value = v;
            });

            // Preview da imagem
            fileInput.addEventListener('change', () => {
                const file = fileInput.files[0];
                if (!file) return;

                const maxSize = 2 * 1024 * 1024;
                const allowed = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowed.includes(file.type)) {
                    alert('Apenas JPEG, PNG ou GIF.');
                    fileInput.value = '';
                    return;
                }
                if (file.size > maxSize) {
                    alert('Imagem muito grande (máx. 2MB).');
                    fileInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });

         

            profileForm.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', updateProfileProgress);
            });
            updateProfileProgress();

            // Validação de senha
            const validatePassword = () => {
                const current = currentPass.value.trim();
                const nova = newPass.value;

                const hasMinLength = nova.length >= 8;
                const hasUpper = /[A-Z]/.test(nova);
                const hasNumber = /[0-9]/.test(nova);
                const hasSymbol = /[!@#$%^&*]/.test(nova);

                const strength = (hasMinLength + hasUpper + hasNumber + hasSymbol) / 4 * 100;
                strengthBar.style.width = `${strength}%`;
                strengthBar.style.backgroundColor = 
                    strength < 50 ? '#ef4444' : 
                    strength < 75 ? '#f59e0b' : 
                    '#10b981';

                // Habilita botão apenas se ambos campos preenchidos e senha forte
                const isValid = current !== '' && nova !== '' && hasMinLength && hasUpper && hasNumber && hasSymbol;
                savePasswordBtn.disabled = !isValid;
            };

            // Eventos de input
            currentPass.addEventListener('input', validatePassword);
            newPass.addEventListener('input', validatePassword);

            // Validação inicial
            validatePassword();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>