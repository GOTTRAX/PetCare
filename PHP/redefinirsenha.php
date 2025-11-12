<?php
session_start();
ob_start();
include("conexao.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

$errors = [];
$sucesso = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim(strtolower($_POST["email"] ?? '')); // Converte para minúsculas

    if (empty($email)) {
        $errors['email'] = 'E-mail é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'E-mail inválido.';
    } else {
        try {
            // Query ajustada para ignorar maiúsculas/minúsculas
            $sql = "SELECT id, email, ativo FROM Usuarios WHERE LOWER(email) = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Depuração: logar o e-mail e o resultado da query
            error_log("E-mail inserido: $email");
            error_log("Resultado da query: " . print_r($usuario, true));

            if (!$usuario) {
                $errors['email'] = 'E-mail não encontrado na base de dados.';
            } elseif ($usuario['ativo'] != 1) {
                $errors['email'] = 'Conta inativa. Entre em contato com o suporte.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $sql_update = "INSERT INTO reset_password (usuario_id, token, expiracao) 
                               VALUES (:id, :token, :expiracao) 
                               ON DUPLICATE KEY UPDATE token = :token, expiracao = :expiracao";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    ':id' => $usuario['id'],
                    ':token' => $token,
                    ':expiracao' => $expiracao
                ]);

                // === Enviar e-mail com PHPMailer ===
                $reset_link = "http://localhost/bruno/PetCare/PHP/redefinir_senha.php?token=" . $token;
                $mail = new PHPMailer(true);

                try {
                    // Configurações do servidor SMTP
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'gaa.melo2015@gmail.com';
                    $mail->Password = 'zopcuscsyrffefbr';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Configurações de codificação
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';

                    // Remetente e destinatário
                    $mail->setFrom('gaa.melo2015@gmail.com', 'PetCare');
                    $mail->addAddress($email);

                    // Configuração do e-mail
                    $mail->isHTML(true);
                    $mail->Subject = 'Recuperação de Senha - PetCare';
                    $mail->Body = '
                        <!DOCTYPE html>
                        <html lang="pt-BR">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Recuperação de Senha - PetCare</title>
                            <style>
                                body {
                                    font-family: "Montserrat", Arial, sans-serif;
                                    line-height: 1.6;
                                    color: #2C3E50;
                                    background-color: #F8F9FA;
                                    margin: 0;
                                    padding: 0;
                                }
                                .container {
                                    max-width: 600px;
                                    margin: 20px auto;
                                    background: #FFFFFF;
                                    border-radius: 12px;
                                    overflow: hidden;
                                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                                }
                                .header {
                                    background: linear-gradient(135deg, #2E8B57 0%, #48B973 100%);
                                    padding: 20px;
                                    text-align: center;
                                }
                                .header img {
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    border: 2px solid #FFFFFF;
                                }
                                .header h1 {
                                    color: #FFFFFF;
                                    font-size: 24px;
                                    margin: 10px 0 0;
                                    font-weight: 700;
                                }
                                .content {
                                    padding: 30px;
                                    text-align: center;
                                }
                                .content p {
                                    font-size: 16px;
                                    color: #2C3E50;
                                    margin-bottom: 20px;
                                }
                                .btn {
                                    display: inline-block;
                                    padding: 12px 24px;
                                    background: #2E8B57 !important;
                                    color: #FFFFFF !important;
                                    text-decoration: none !important;
                                    border-radius: 8px;
                                    font-weight: 600;
                                    transition: background 0.3s ease;
                                }
                                .btn:hover {
                                    background: #1F5F3F !important;
                                }
                                .btn:focus {
                                    outline: none;
                                    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2) !important;
                                }
                                .footer {
                                    background: #F8F9FA;
                                    padding: 20px;
                                    text-align: center;
                                    font-size: 14px;
                                    color: #7F8C8D;
                                }
                                .footer p {
                                    margin: 5px 0;
                                }
                                .footer a {
                                    color: #2E8B57;
                                    text-decoration: none;
                                    font-weight: 600;
                                }
                                .footer a:hover {
                                    text-decoration: underline;
                                }
                                .footer a:focus {
                                    outline: none;
                                    box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
                                }
                                @media (max-width: 600px) {
                                    .container { margin: 10px; }
                                    .header h1 { font-size: 20px; }
                                    .content p { font-size: 14px; }
                                    .btn { padding: 10px 20px; font-size: 14px; }
                                    .footer { font-size: 12px; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="header">
                                    <img src="https://st2.depositphotos.com/5056293/9389/v/450/depositphotos_93899252-stock-illustration-vector-sign-veterinary.jpg" alt="PetCare Logo">
                                    <h1>PetCare</h1>
                                </div>
                                <div class="content">
                                    <p>Olá!</p>
                                    <p>Recebemos uma solicitação para redefinir a senha da sua conta PetCare. Clique no botão abaixo para criar uma nova senha:</p>
                                    <a href="' . $reset_link . '" class="btn">Redefinir Senha</a>
                                    <p>Este link é válido por 1 hora. Se você não solicitou esta redefinição, ignore este e-mail.</p>
                                </div>
                                <div class="footer">
                                    <p>PetCare &copy; ' . date('Y') . ' | Todos os direitos reservados</p>
                                    <p><a href="http://localhost/bruno/PetCare/login.php">Visite nosso site</a></p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ';
                    $mail->AltBody = "Olá! Use este link para redefinir sua senha: $reset_link\nEste link expira em 1 hora. Se você não solicitou esta redefinição, ignore este e-mail.";

                    $mail->send();
                    $sucesso = "Um e-mail com instruções foi enviado para " . htmlspecialchars($email) . ".";
                } catch (Exception $e) {
                    $errors['email'] = "Erro ao enviar o e-mail: " . $e->getMessage();
                    error_log("Erro PHPMailer: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            $errors['geral'] = "Erro ao processar a solicitação. Detalhes: " . $e->getMessage();
            error_log("Erro PDO: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recupere sua senha na PetCare para acessar serviços veterinários exclusivos">
    <meta name="keywords" content="recuperar senha, petcare, veterinária">
    <meta name="author" content="PetCare">
    <title>Recuperação de Senha - PetCare</title>
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
            --primary-dark: #1F5F3F;
            --secondary-color: #c6c8c8;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --background-light: #F8F9FA;
            --white: #FFFFFF;
            --accent-color: #FF6B6B;
            --success-color: #34C759;
            --error-color: #FF6B6B;
            --gradient-primary: linear-gradient(135deg, #7d8a83 0%, #48B973 100%);
            --gradient-secondary: linear-gradient(135deg, #F0A500 0%, #FFB84D 100%);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
            --shadow-large: 0 20px 40px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 6px 12px rgba(0, 0, 0, 0.15);
            --shadow-glow: 0 0 30px rgba(46, 139, 87, 0.2);
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
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://img.icons8.com/fluency-systems-regular/48/000000/paw.png') repeat;
            opacity: 0.03;
            z-index: -1;
            animation: backgroundFloat 20s ease-in-out infinite;
        }

        @keyframes backgroundFloat {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(10px, 10px);
            }
        }

        /* Header Styles */
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

        .back-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .reset-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 420px;
            width: 100%;
            margin-top: 80px;
            box-shadow: var(--shadow-light);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .reset-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .reset-header p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 8px;
        }

        .reset-form {
            display: grid;
            gap: 1rem;
        }

        .input-group {
            position: relative;
        }

        .input-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 6px;
            display: block;
        }

        .input-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .input-group.invalid input {
            border-color: var(--error-color);
        }

        .input-group.valid input {
            border-color: var(--success-color);
        }

        .error-message {
            font-size: 0.8rem;
            color: var(--error-color);
            margin-top: 4px;
            display: none;
        }

        .input-group.invalid .error-message {
            display: block;
        }

        .reset-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            color: var(--white);
            background: var(--primary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: var(--shadow-light);
        }

        .reset-btn:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .reset-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .reset-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 1rem;
        }

        .login-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .login-link a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        .login-link a:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 16px;
            border-radius: var(--border-radius);
            color: var(--white);
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 1000;
            box-shadow: var(--shadow-light);
            animation: slideInRight 0.3s ease;
        }

        .toast.success {
            background: var(--success-color);
        }

        .toast.error {
            background: var(--error-color);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }

            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        @media (max-width: 768px) {
            .reset-container {
                padding: 1.5rem;
                margin-top: 70px;
            }

            .reset-header h1 {
                font-size: 1.5rem;
            }

            .reset-btn {
                font-size: 0.95rem;
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .reset-container {
                padding: 1rem;
            }

            .reset-header h1 {
                font-size: 1.3rem;
            }

            .reset-header p {
                font-size: 0.85rem;
            }

            .input-group input {
                font-size: 0.85rem;
            }

            .reset-btn {
                font-size: 0.9rem;
            }

            .toast {
                max-width: calc(100% - 40px);
            }
        }
    </style>
</head>

<body>
    <header class="header" role="banner">
        <a href="login.php" class="logo" aria-label="PetCare Home">
            <img src="https://st2.depositphotos.com/5056293/9389/v/450/depositphotos_93899252-stock-illustration-vector-sign-veterinary.jpg"
                alt="PetCare Logo" class="logo-img" loading="lazy">
            PetCare
        </a>
        <a href="http://localhost/Bruno/PetCare/PHP/login.php" class="back-btn" aria-label="Voltar à página inicial">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </header>

    <div class="reset-container" aria-labelledby="reset-header-title">
        <div class="reset-header">
            <h1 id="reset-header-title"><i class="fas fa-lock"></i> Recuperar Senha</h1>
            <p>Insira seu e-mail para receber um link de redefinição de senha.</p>
            <?php if (isset($errors['geral'])): ?>
                <div class="toast error"><i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['geral']); ?></div>
            <?php endif; ?>
            <?php if (isset($errors['email'])): ?>
                <div class="toast error"><i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errors['email']); ?></div>
            <?php endif; ?>
        </div>

        <form id="resetForm" class="reset-form" method="POST" action="">
            <div class="input-group <?php echo isset($errors['email']) ? 'invalid' : ''; ?>">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seu@email.com"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                <span
                    class="error-message"><?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : 'Por favor, insira um e-mail válido'; ?></span>
            </div>
            <button type="submit" class="reset-btn" aria-label="Enviar link de redefinição">
                <i class="fas fa-paper-plane"></i> Enviar
            </button>
        </form>

        <div class="login-link">
            Lembrou sua senha? <a href="../login.php">Faça login aqui</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('resetForm');
            const emailInput = document.getElementById('email');

            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease forwards';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            }

            <?php if ($sucesso): ?>
                showToast(<?php echo json_encode(htmlspecialchars($sucesso)); ?>, 'success');
            <?php endif; ?>

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            emailInput.addEventListener('input', function () {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.queryor('.error-message');
                inputGroup.classList.remove('invalid', 'valid');
                errorMessage.style.display = 'none';

                if (this.value && !isValidEmail(this.value)) {
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'E-mail inválido.';
                    errorMessage.style.display = 'block';
                } else if (this.value) {
                    inputGroup.classList.add('valid');
                }
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                let isValid = true;

                const inputGroup = emailInput.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                inputGroup.classList.remove('valid', 'invalid');
                errorMessage.style.display = 'none';

                if (!emailInput.value) {
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'E-mail é obrigatório.';
                    errorMessage.style.display = 'block';
                    isValid = false;
                } else if (!isValidEmail(emailInput.value)) {
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'E-mail inválido.';
                    errorMessage.style.display = 'block';
                    isValid = false;
                } else {
                    inputGroup.classList.add('valid');
                }

                if (isValid) {
                    const submitBtn = this.querySelector('.reset-btn');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                    submitBtn.disabled = true;
                    this.submit();
                } else {
                    showToast('Corrija os erros no formulário.', 'error');
                }
            });
        });
    </script>
</body>

</html>
<?php ob_end_flush(); ?>