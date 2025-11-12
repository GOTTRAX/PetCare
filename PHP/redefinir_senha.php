<?php
session_start();
ob_start();
include("conexao.php");

$errors = [];
$sucesso = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Verificar se o token é válido e não expirou
        $sql = "SELECT usuario_id, expiracao FROM reset_password WHERE token = :token AND expiracao > NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            $errors['token'] = 'Token inválido ou expirado.';
        } else {
            if ($_SERVER["REQUEST_METHOD"] === "POST") {
                $nova_senha = trim($_POST['nova_senha'] ?? '');
                $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');

                // Validações
                if (empty($nova_senha)) {
                    $errors['nova_senha'] = 'A nova senha é obrigatória.';
                } elseif (strlen($nova_senha) < 6) {
                    $errors['nova_senha'] = 'A senha deve ter pelo menos 6 caracteres.';
                }

                if ($nova_senha !== $confirmar_senha) {
                    $errors['confirmar_senha'] = 'As senhas não coincidem.';
                }

                if (empty($errors)) {
                    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    // Atualizar a senha usando a coluna correta 'senha_hash'
                    $sql_update = "UPDATE Usuarios SET senha_hash = :senha_hash WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $result = $stmt_update->execute([
                        ':senha_hash' => $nova_senha_hash,
                        ':id' => $reset['usuario_id']
                    ]);

                    // Verificar se a atualização foi bem-sucedida
                    if ($result && $stmt_update->rowCount() > 0) {
                        // Invalidar sessão existente
                        session_unset();
                        session_destroy();
                        session_start();

                        // Remover o token
                        $sql_delete = "DELETE FROM reset_password WHERE token = :token";
                        $stmt_delete = $pdo->prepare($sql_delete);
                        $stmt_delete->execute([':token' => $token]);

                        $sucesso = 'Senha redefinida com sucesso! Redirecionando para a página de login...';
                        header("Location: ../index.php");
                        exit();
                    } else {
                        $errors['geral'] = 'Erro: Nenhuma conta foi atualizada. Verifique se o usuário existe.';
                        // Depuração: descomente para verificar
                        // var_dump($reset);
                        // var_dump($stmt_update->errorInfo());
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors['geral'] = "Erro ao processar a solicitação: " . htmlspecialchars($e->getMessage());
    }
} else {
    $errors['token'] = 'Nenhum token fornecido.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Redefina sua senha na PetCare para acessar serviços veterinários exclusivos">
    <title>Redefinir Senha - PetCare</title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios/452/cat.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2E8B57;
            --primary-dark: #2E8B57;
            --secondary-color: #c6c8c8;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --background-light: #F8F9FA;
            --white: #FFFFFF;
            --accent-color: #FF6B6B;
            --success-color: #27AE60;
            --gradient-primary: linear-gradient(135deg, #7d8a83 0%, #48B973 100%);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-glow: 0 8px 25px rgba(46, 139, 87, 0.3);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--background-light) 0%, rgba(46, 139, 87, 0.05) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(46, 139, 87, 0.1);
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            gap: 0.5rem;
        }

        .logo-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            transition: var(--transition);
        }

        .logo:hover .logo-img {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--secondary-color);
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            transition: var(--transition);
        }

        .back-btn:hover {
            color: var(--primary-color);
            background: rgba(46, 139, 87, 0.1);
            transform: translateX(-3px);
        }

        .reset-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 450px;
            width: 100%;
            margin-top: 70px;
            box-shadow: var(--shadow-light);
            animation: slideInScale 0.5s ease-out;
        }

        @keyframes slideInScale {
            from { opacity: 0; transform: scale(0.95) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .reset-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .reset-header p {
            font-size: 0.9rem;
            color: var(--text-light);
            max-width: 350px;
            margin: 0 auto;
        }

        .reset-form {
            display: grid;
            gap: 1.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .input-wrapper {
            position: relative;
        }

        .left-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.9rem;
            pointer-events: none;
        }

        .right-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .right-icon:hover {
            color: var(--primary-color);
        }

        .input-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            padding-left: 40px;
            padding-right: 40px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--white);
            transition: var(--transition);
            font-family: 'Montserrat', sans-serif;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }

        .input-group.invalid input {
            border-color: var(--accent-color);
            background-color: rgba(255, 107, 107, 0.05);
        }

        .input-group.valid input {
            border-color: var(--success-color);
            background-color: rgba(39, 174, 96, 0.05);
        }

        .error-message, .success-message {
            font-size: 0.75rem;
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 500;
        }

        .error-message { color: var(--accent-color); display: none; }
        .success-message { color: var(--success-color); display: none; }

        .input-group.invalid .error-message { display: flex; animation: fadeIn 0.3s ease; }
        .input-group.valid .success-message { display: flex; animation: fadeIn 0.3s ease; }

        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            height: 4px;
            background: #e1e8ed;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .strength-fill {
            height: 100%;
            background: var(--accent-color);
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0%;
        }

        .strength-fill.weak { background: var(--accent-color); width: 25%; }
        .strength-fill.fair { background: #FFA500; width: 50%; }
        .strength-fill.good { background: #32CD32; width: 75%; }
        .strength-fill.strong { background: var(--success-color); width: 100%; }

        .strength-text {
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .strength-text.weak { color: var(--accent-color); }
        .strength-text.fair { color: #FFA500; }
        .strength-text.good { color: #32CD32; }
        .strength-text.strong { color: var(--success-color); }

        .reset-btn {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            background: var(--gradient-primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            margin-top: 1rem;
        }

        .reset-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .reset-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            text-align: center;
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 1.5rem;
        }

        .login-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: var(--white);
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            box-shadow: var(--shadow-light);
            max-width: 320px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toast.success { background: var(--success-color); }
        .toast.error { background: var(--accent-color); }

        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideOutRight { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(100%); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .reset-container { padding: 1.5rem; margin-top: 60px; margin-left: 10px; margin-right: 10px; }
            .reset-header h1 { font-size: 1.4rem; }
            .reset-header p { font-size: 0.85rem; }
            .input-group input { font-size: 0.85rem; padding: 0.7rem 0.9rem 0.7rem 36px; }
            .reset-btn { font-size: 0.9rem; padding: 0.8rem; }
        }

        @media (max-width: 480px) {
            .reset-container { padding: 1.2rem 1rem; }
            .reset-header h1 { font-size: 1.3rem; }
            .reset-header p { font-size: 0.8rem; }
            .input-group input { font-size: 0.8rem; padding: 0.6rem 0.8rem 0.6rem 32px; }
            .reset-btn { font-size: 0.85rem; padding: 0.7rem; }
            .toast { margin: 10px; max-width: calc(100vw - 20px); }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="#" class="logo">
            <img src="https://st2.depositphotos.com/5056293/9389/v/450/depositphotos_93899252-stock-illustration-vector-sign-veterinary.jpg" alt="PetCare Logo" class="logo-img">
            PetCare
        </a>
        <a href="#" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </header>

    <div class="reset-container">
        <div class="reset-header">
            <h1><i class="fas fa-lock"></i> Redefinir Senha</h1>
            <p>Crie uma nova senha segura para sua conta. Certifique-se de que ela tenha pelo menos 6 caracteres.</p>
            <?php if (!empty($errors['geral'])): ?>
                <div class="toast error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errors['geral']); ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="toast success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors['token'])): ?>
                <div class="toast error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errors['token']); ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($errors['token'])): ?>
        <form id="resetForm" class="reset-form" method="POST" action="">
            <!-- Nova Senha -->
            <div class="input-group" id="nova-senha-group">
                <label for="nova_senha"><i class="fas fa-key"></i> Nova Senha</label>
                <div class="input-wrapper">
                    <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite sua nova senha" required>
                    <i class="fas fa-key left-icon"></i>
                    <i class="fas fa-eye toggle-password right-icon" onclick="togglePassword('nova_senha')"></i>
                </div>
                <div class="password-strength" id="password-strength">
                    <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                    <div class="strength-text" id="strength-text"><i class="fas fa-info-circle"></i> Digite uma senha para verificar sua força</div>
                </div>
                <span class="error-message" id="nova_senha-error"><i class="fas fa-exclamation-triangle"></i> <span><?php echo isset($errors['nova_senha']) ? htmlspecialchars($errors['nova_senha']) : ''; ?></span></span>
                <span class="success-message" id="nova_senha-success"><i class="fas fa-check-circle"></i> <span>Senha válida!</span></span>
            </div>

            <!-- Confirmar Senha -->
            <div class="input-group" id="confirmar-senha-group">
                <label for="confirmar_senha"><i class="fas fa-shield-alt"></i> Confirmar Nova Senha</label>
                <div class="input-wrapper">
                    <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Digite novamente sua nova senha" required>
                    <i class="fas fa-shield-alt left-icon"></i>
                    <i class="fas fa-eye toggle-password right-icon" onclick="togglePassword('confirmar_senha')"></i>
                </div>
                <span class="error-message" id="confirmar_senha-error"><i class="fas fa-exclamation-triangle"></i> <span><?php echo isset($errors['confirmar_senha']) ? htmlspecialchars($errors['confirmar_senha']) : ''; ?></span></span>
                <span class="success-message" id="confirmar_senha-success"><i class="fas fa-check-circle"></i> <span>As senhas coincidem!</span></span>
            </div>

            <button type="submit" class="reset-btn" id="submitBtn"><i class="fas fa-save"></i> Salvar Nova Senha</button>
        </form>
        <?php endif; ?>

        <div class="login-link">Lembrou da sua senha? <a href="login.php" id="loginLink">Faça login aqui</a></div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.nextElementSibling;
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

        function showToast(message, type = 'success') {
            const existingToast = document.querySelector('.toast');
            if (existingToast) existingToast.remove();
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-triangle'}"></i> ${message}`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 4500);
        }

        function checkPasswordStrength(password) {
            const strengthFill = document.getElementById('strength-fill');
            const strengthText = document.getElementById('strength-text');
            
            if (!password) {
                strengthFill.className = 'strength-fill';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> Digite uma senha para verificar sua força';
                strengthText.className = 'strength-text';
                return;
            }
            
            let score = 0;
            let feedback = '';
            
            if (password.length >= 8) score += 1;
            if (/[a-z]/.test(password)) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            
            switch (score) {
                case 0:
                case 1:
                    strengthFill.className = 'strength-fill weak';
                    strengthText.className = 'strength-text weak';
                    feedback = 'Muito fraca';
                    break;
                case 2:
                case 3:
                    strengthFill.className = 'strength-fill fair';
                    strengthText.className = 'strength-text fair';
                    feedback = 'Razoável';
                    break;
                case 4:
                    strengthFill.className = 'strength-fill good';
                    strengthText.className = 'strength-text good';
                    feedback = 'Boa';
                    break;
                case 5:
                    strengthFill.className = 'strength-fill strong';
                    strengthText.className = 'strength-text strong';
                    feedback = 'Muito forte';
                    break;
            }
            
            const icon = score <= 1 ? 'fa-times-circle' : score <= 3 ? 'fa-info-circle' : 'fa-check-circle';
            strengthText.innerHTML = `<i class="fas ${icon}"></i> ${feedback}`;
        }

        function validateField(input, validationFn, errorMsg, successMsg = '') {
            const inputGroup = input.closest('.input-group');
            const errorElement = inputGroup.querySelector('.error-message span');
            const successElement = inputGroup.querySelector('.success-message span');
            
            const isValid = validationFn(input.value);
            
            inputGroup.classList.remove('invalid', 'valid');
            
            if (input.value && !isValid) {
                inputGroup.classList.add('invalid');
                if (errorElement) errorElement.textContent = errorMsg;
                return false;
            } else if (input.value && isValid) {
                inputGroup.classList.add('valid');
                if (successElement && successMsg) successElement.textContent = successMsg;
                return true;
            }
            
            return input.value === '' ? null : true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const novaSenhaInput = document.getElementById('nova_senha');
            const confirmarSenhaInput = document.getElementById('confirmar_senha');
            const submitBtn = document.getElementById('submitBtn');

            novaSenhaInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                validateField(
                    this,
                    value => value.length >= 6,
                    'A senha deve ter pelo menos 6 caracteres',
                    'Senha válida!'
                );
                
                if (confirmarSenhaInput.value) {
                    validatePasswordMatch();
                }
            });

            function validatePasswordMatch() {
                validateField(
                    confirmarSenhaInput,
                    value => value === novaSenhaInput.value,
                    'As senhas não coincidem',
                    'As senhas coincidem!'
                );
            }

            confirmarSenhaInput.addEventListener('input', validatePasswordMatch);

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                
                document.querySelectorAll('.input-group').forEach(group => {
                    group.classList.remove('invalid', 'valid');
                });
                
                let isValid = true;
                
                if (!novaSenhaInput.value) {
                    document.getElementById('nova-senha-group').classList.add('invalid');
                    document.querySelector('#nova_senha-error span').textContent = 'A nova senha é obrigatória';
                    isValid = false;
                } else if (novaSenhaInput.value.length < 6) {
                    document.getElementById('nova-senha-group').classList.add('invalid');
                    document.querySelector('#nova_senha-error span').textContent = 'A senha deve ter pelo menos 6 caracteres';
                    isValid = false;
                } else {
                    document.getElementById('nova-senha-group').classList.add('valid');
                }
                
                if (!confirmarSenhaInput.value) {
                    document.getElementById('confirmar-senha-group').classList.add('invalid');
                    document.querySelector('#confirmar_senha-error span').textContent = 'Confirme sua nova senha';
                    isValid = false;
                } else if (novaSenhaInput.value !== confirmarSenhaInput.value) {
                    document.getElementById('confirmar-senha-group').classList.add('invalid');
                    document.querySelector('#confirmar_senha-error span').textContent = 'As senhas não coincidem';
                    isValid = false;
                } else {
                    document.getElementById('confirmar-senha-group').classList.add('valid');
                }
                
                if (isValid) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                    submitBtn.disabled = true;
                    form.submit();
                } else {
                    showToast('Por favor, corrija os erros no formulário', 'error');
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>