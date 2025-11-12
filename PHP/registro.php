<?php
session_start();
include("conexao.php"); 
function validateCPF($cpf)
{
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\d)\1+$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

function validateNome($nome) {
    // Remove espaços extras
    $nome = trim($nome);
    
    // Verifica se está vazio
    if (empty($nome)) {
        return ['valid' => false, 'message' => 'Nome é obrigatório.'];
    }
    
    // Verifica se tem pelo menos 3 caracteres
    if (strlen($nome) < 3) {
        return ['valid' => false, 'message' => 'Nome deve ter pelo menos 3 caracteres.'];
    }
    
    // Verifica se contém apenas letras, espaços, hífens e apóstrofos
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s\-']+$/u", $nome)) {
        return ['valid' => false, 'message' => 'Nome deve conter apenas letras.'];
    }
    
    // Verifica se tem pelo menos um espaço (nome e sobrenome)
    if (strpos($nome, ' ') === false) {
        return ['valid' => false, 'message' => 'Por favor, insira nome e sobrenome.'];
    }
    
    // Divide o nome em partes
    $partes = explode(' ', $nome);
    $partes = array_filter($partes); // Remove espaços vazios
    
    // Verifica se tem pelo menos 2 partes (nome e sobrenome)
    if (count($partes) < 2) {
        return ['valid' => false, 'message' => 'Por favor, insira nome e sobrenome.'];
    }
    
    // Verifica se cada parte tem pelo menos 2 caracteres
    foreach ($partes as $parte) {
        if (strlen($parte) < 2) {
            return ['valid' => false, 'message' => 'Nome e sobrenome devem ter pelo menos 2 caracteres cada.'];
        }
    }
    
    // Verifica se não contém números
    if (preg_match('/\d/', $nome)) {
        return ['valid' => false, 'message' => 'Nome não pode conter números.'];
    }
    
    // Verifica se não contém caracteres especiais (exceto - e ')
    if (preg_match('/[^a-zA-ZÀ-ÿ\s\-\']/u', $nome)) {
        return ['valid' => false, 'message' => 'Nome contém caracteres inválidos.'];
    }
    
    return ['valid' => true, 'message' => ''];
}

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome       = trim($_POST["nome"] ?? '');
    $email      = trim($_POST["email"] ?? '');
    $senha      = $_POST["password"] ?? '';
    $confSenha  = $_POST["confirmPassword"] ?? '';
    $telefone   = trim($_POST["telefone"] ?? '');
    $datanasc   = $_POST["datanasc"] ?? '';
    $cpf        = trim($_POST["cpf"] ?? '');
    $genero     = $_POST["genero"] ?? '';

    // Validação do Nome com função melhorada
    $nomeValidation = validateNome($nome);
    if (!$nomeValidation['valid']) {
        $errors['nome'] = $nomeValidation['message'];
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'E-mail inválido.';
    } else {
        $verificaEmail = $pdo->prepare("SELECT id FROM Usuarios WHERE email = ?");
        $verificaEmail->execute([$email]);
        if ($verificaEmail->rowCount() > 0) {
            $errors['email'] = 'Este e-mail já está cadastrado.';
        }
    }

    if (empty($senha)) {
        $errors['password'] = 'Senha é obrigatória.';
    } elseif (strlen($senha) < 8 || !preg_match('/[A-Z]/', $senha) || !preg_match('/[0-9]/', $senha) || !preg_match('/[^A-Za-z0-9]/', $senha)) {
        $errors['password'] = 'Senha deve ter pelo menos 8 caracteres, incluindo letras maiúsculas, números e símbolos.';
    }

    if ($senha !== $confSenha) {
        $errors['confirmPassword'] = 'As senhas não coincidem.';
    }

    $telLimpo = preg_replace('/\D/', '', $telefone);
    if (empty($telefone) || strlen($telLimpo) != 11) {
        $errors['telefone'] = 'Telefone inválido. Use o formato (xx) xxxxx-xxxx.';
    }

    if (empty($datanasc)) {
        $errors['datanasc'] = 'Data de nascimento é obrigatória.';
    } else {
        $data = DateTime::createFromFormat('Y-m-d', $datanasc);
        if (!$data || $data->format('Y-m-d') !== $datanasc || (int)$data->format('Y') < 1900 || $data > new DateTime()) {
            $errors['datanasc'] = 'Data de nascimento inválida.';
        }
    }

    if (!validateCPF($cpf)) {
        $errors['cpf'] = 'CPF inválido.';
    } else {
        $verificaCPF = $pdo->prepare("SELECT id FROM Usuarios WHERE cpf = ?");
        $verificaCPF->execute([$cpf]);
        if ($verificaCPF->rowCount() > 0) {
            $errors['cpf'] = 'Este CPF já está cadastrado.';
        }
    }

    if (empty($genero) || !in_array($genero, ['Masculino', 'Feminino', 'Outro'])) {
        $errors['genero'] = 'Gênero inválido.';
    }

    if (empty($errors)) {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO Usuarios (nome, cpf, email, senha_hash, telefone, datanasc, genero)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$nome, $cpf, $email, $senhaHash, $telefone, $datanasc, $genero])) {
            $usuario_id = $pdo->lastInsertId();
            $_SESSION["usuario_id"] = $usuario_id;
            $_SESSION["usuario_email"] = $email;
            $_SESSION["cadastro_sucesso"] = true;

            header("Location: login.php");
            exit();
        } else {
            $errors['geral'] = 'Erro ao registrar. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cadastre-se na PetCare para acessar serviços veterinários exclusivos para seu pet">
    <meta name="keywords" content="cadastro, petcare, veterinária, cuidado pet">
    <meta name="author" content="PetCare">
    <title>Cadastro - PetCare</title>

    <link rel="icon" type="image/png" href="https://img.icons8.com/ios/452/cat.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous">

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
            --gradient-primary: linear-gradient(135deg, #7d8a83 0%, #48B973 100%);
            --gradient-secondary: linear-gradient(135deg, #F0A500 0%, #FFB84D 100%);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
            --shadow-large: 0 20px 40px rgba(0, 0, 0, 0.1);
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
            0%, 100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(10px, 10px);
            }
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

        .back-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .signup-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            max-width: 600px;
            width: 100%;
            margin-top: 70px;
            box-shadow: var(--shadow-light);
            animation: slideInScale 0.5s ease-out;
        }

        @keyframes slideInScale {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .signup-header {
            text-align: center;
            margin-bottom: 1.2rem;
        }

        .signup-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .signup-header p {
            font-size: 0.85rem;
            color: var(--text-light);
            max-width: 320px;
            margin: 0.4rem auto 0;
        }

        .signup-form {
            display: grid;
            gap: 0.8rem;
        }

        .form-section {
            display: grid;
            gap: 0.6rem;
        }

        .field-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }

        .input-group {
            position: relative;
        }

        .input-group label {
            position: absolute;
            left: 10px;
            top: -7px;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-light);
            background: var(--white);
            padding: 0 5px;
            transition: var(--transition);
        }

        .input-group i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 0.6rem 0.6rem 0.6rem 32px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--white);
            transition: var(--transition);
            font-family: 'Montserrat', sans-serif;
        }

        .input-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%237F8C8D'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px;
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .input-group input:focus+i,
        .input-group select:focus+i {
            color: var(--primary-color);
        }

        .password-group .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .password-group .toggle-password:hover {
            color: var(--primary-color);
        }

        .password-group .toggle-password:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .password-strength {
            display: none;
            margin-top: 0.2rem;
            height: 4px;
            background: rgba(52, 199, 89, 0.15);
            border-radius: 3px;
            overflow: hidden;
        }

        .password-strength-fill {
            height: 100%;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-weak {
            width: 25%;
            background: var(--accent-color);
        }

        .strength-fair {
            width: 50%;
            background: #FBBF24;
        }

        .strength-good {
            width: 75%;
            background: #10B981;
        }

        .strength-strong {
            width: 100%;
            background: var(--primary-dark);
        }

        .password-strength-text {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.2rem;
            text-align: center;
        }

        .signup-btn {
            width: 100%;
            padding: 0.7rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
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
            position: relative;
            overflow: hidden;
        }

        .signup-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .signup-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.4s ease;
        }

        .signup-btn:hover::before {
            left: 100%;
        }

        .signup-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .divider {
            text-align: center;
            margin: 0.8rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(52, 199, 89, 0.15);
        }

        .divider span {
            background: var(--white);
            padding: 0 0.8rem;
            color: var(--text-light);
            font-size: 0.8rem;
            position: relative;
            z-index: 2;
        }

        .login-link {
            text-align: center;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 0.8rem;
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

        .login-link a:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.2);
        }

        .input-group.valid input,
        .input-group.valid select {
            border-color: var(--primary-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2334C759'%3e%3cpath d='M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
        }

        .input-group.invalid input,
        .input-group.invalid select {
            border-color: var(--accent-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23FF6B6B'%3e%3cpath d='M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z'/%3e%3cpath d='M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
        }

        .error-message {
            color: var(--accent-color);
            font-size: 0.75rem;
            margin-top: 0.2rem;
            display: none;
            font-weight: 500;
        }

        .input-group.invalid .error-message {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .toast {
            position: fixed;
            top: 15px;
            right: 15px;
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            color: var(--white);
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            box-shadow: var(--shadow-light);
            max-width: 280px;
            font-size: 0.85rem;
        }

        .toast.success {
            background: var(--primary-color);
        }

        .toast.error {
            background: var(--accent-color);
        }

        .toast.info {
            background: #3B82F6;
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

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 0.6rem 1rem;
            }

            .signup-container {
                padding: 1.2rem 0.8rem;
                margin-top: 60px;
                max-width: 100%;
            }

            .signup-header h1 {
                font-size: 1.3rem;
            }

            .signup-header p {
                font-size: 0.8rem;
            }

            .field-pair {
                grid-template-columns: 1fr;
                gap: 0.6rem;
            }

            .input-group input,
            .input-group select {
                font-size: 0.8rem;
                padding: 0.5rem 0.5rem 0.5rem 28px;
            }

            .input-group i {
                font-size: 0.8rem;
                left: 8px;
            }

            .input-group select {
                background-position: right 8px center;
                background-size: 9px;
            }
        }

        @media (max-width: 480px) {
            .signup-container {
                padding: 1rem 0.6rem;
            }

            .signup-header h1 {
                font-size: 1.2rem;
            }

            .signup-header p {
                font-size: 0.75rem;
            }

            .input-group input,
            .input-group select {
                font-size: 0.75rem;
                padding: 0.4rem 0.4rem 0.4rem 24px;
            }

            .input-group i {
                font-size: 0.75rem;
            }

            .signup-btn {
                font-size: 0.8rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <header class="header" role="banner">
        <a href="../index.php" class="logo" aria-label="PetCare Home">
            <img src="https://st2.depositphotos.com/5056293/9389/v/450/depositphotos_93899252-stock-illustration-vector-sign-veterinary.jpg" alt="PetCare Logo" class="logo-img" loading="lazy">
            PetCare
        </a>
        <a href="login.php" class="back-btn" aria-label="Voltar à página de login">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
    </header>

    <div class="signup-container" aria-labelledby="signup-header-title">
        <div class="signup-header">
            <h1 id="signup-header-title">
                <i class="fas fa-paw"></i>
                Criar Conta
            </h1>
            <p>Junte-se à PetCare e cuide do seu pet com os melhores serviços!</p>
            <?php if (isset($errors['geral'])): ?>
                <div class="toast error"><?php echo htmlspecialchars($errors['geral']); ?></div>
            <?php endif; ?>
        </div>

        <form id="signupForm" class="signup-form" method="POST" action="">
            <div class="form-section">
                <div class="field-pair">
                    <div class="input-group <?php echo isset($errors['nome']) ? 'invalid' : ''; ?>">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="Nome e Sobrenome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                        <i class="fas fa-user"></i>
                        <span class="error-message" id="nome-error"><?php echo isset($errors['nome']) ? htmlspecialchars($errors['nome']) : 'Por favor, insira nome e sobrenome'; ?></span>
                    </div>
                    <div class="input-group <?php echo isset($errors['email']) ? 'invalid' : ''; ?>">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="seu@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <i class="fas fa-envelope"></i>
                        <span class="error-message" id="email-error"><?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : 'Por favor, insira um e-mail válido'; ?></span>
                    </div>
                </div>
                <div class="field-pair">
                    <div class="input-group <?php echo isset($errors['telefone']) ? 'invalid' : ''; ?>">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" placeholder="(xx) xxxxx-xxxx" value="<?php echo isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : ''; ?>" required maxlength="15">
                        <i class="fas fa-phone"></i>
                        <span class="error-message" id="telefone-error"><?php echo isset($errors['telefone']) ? htmlspecialchars($errors['telefone']) : 'Por favor, insira um telefone válido'; ?></span>
                    </div>
                    <div class="input-group <?php echo isset($errors['cpf']) ? 'invalid' : ''; ?>">
                        <label for="cpf">CPF</label>
                        <input type="text" id="cpf" name="cpf" placeholder="xxx.xxx.xxx-xx" value="<?php echo isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : ''; ?>" required maxlength="14">
                        <i class="fas fa-id-card"></i>
                        <span class="error-message" id="cpf-error"><?php echo isset($errors['cpf']) ? htmlspecialchars($errors['cpf']) : 'Por favor, insira um CPF válido'; ?></span>
                    </div>
                </div>
                <div class="field-pair">
                    <div class="input-group <?php echo isset($errors['datanasc']) ? 'invalid' : ''; ?>">
                        <label for="datanasc">Data de Nascimento</label>
                        <input type="date" id="datanasc" name="datanasc" value="<?php echo isset($_POST['datanasc']) ? htmlspecialchars($_POST['datanasc']) : ''; ?>" required>
                        <i class="fas fa-calendar-alt"></i>
                        <span class="error-message" id="datanasc-error"><?php echo isset($errors['datanasc']) ? htmlspecialchars($errors['datanasc']) : 'Por favor, insira uma data válida'; ?></span>
                    </div>
                    <div class="input-group <?php echo isset($errors['genero']) ? 'invalid' : ''; ?>">
                        <label for="genero">Gênero</label>
                        <select id="genero" name="genero" required>
                            <option value="" disabled <?php echo !isset($_POST['genero']) ? 'selected' : ''; ?>>Selecione</option>
                            <option value="Masculino" <?php echo isset($_POST['genero']) && $_POST['genero'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Feminino" <?php echo isset($_POST['genero']) && $_POST['genero'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo isset($_POST['genero']) && $_POST['genero'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                        <i class="fas fa-venus-mars"></i>
                        <span class="error-message" id="genero-error"><?php echo isset($errors['genero']) ? htmlspecialchars($errors['genero']) : 'Por favor, selecione um gênero válido'; ?></span>
                    </div>
                </div>
                <div class="input-group password-group <?php echo isset($errors['password']) ? 'invalid' : ''; ?>">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Crie uma senha" required>
                    <i class="fas fa-lock"></i>
                    <span class="toggle-password" onclick="toggleSenha('password')"><i class="fas fa-eye"></i></span>
                    <span class="error-message" id="password-error"><?php echo isset($errors['password']) ? htmlspecialchars($errors['password']) : 'Por favor, insira uma senha válida'; ?></span>
                    <div class="password-strength">
                        <div class="password-strength-fill"></div>
                    </div>
                    <div class="password-strength-text"></div>
                </div>
                <div class="input-group password-group <?php echo isset($errors['confirmPassword']) ? 'invalid' : ''; ?>">
                    <label for="confirmPassword">Confirmar Senha</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirme sua senha" required>
                    <i class="fas fa-lock"></i>
                    <span class="toggle-password" onclick="toggleSenha('confirmPassword')"><i class="fas fa-eye"></i></span>
                    <span class="error-message" id="confirmPassword-error"><?php echo isset($errors['confirmPassword']) ? htmlspecialchars($errors['confirmPassword']) : 'As senhas não coincidem'; ?></span>
                </div>
            </div>

            <button type="submit" class="signup-btn" aria-label="Cadastrar">
                <i class="fas fa-user-plus"></i>
                Cadastrar
            </button>
        </form>

        <div class="login-link">
            Já tem uma conta? <a href="login.php" id="loginLink">Faça login aqui</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signupForm');
            const inputs = form.querySelectorAll('input, select');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const cpfInput = document.getElementById('cpf');
            const telefoneInput = document.getElementById('telefone');
            const emailInput = document.getElementById('email');
            const datanascInput = document.getElementById('datanasc');
            const generoInput = document.getElementById('genero');
            const nomeInput = document.getElementById('nome');

            // Exibir erros do servidor como toasts e marcar campos inválidos
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $field => $message): ?>
                    <?php if ($field !== 'geral'): ?>
                        const fieldEl = document.getElementById('<?php echo $field; ?>');
                        if (fieldEl) {
                            fieldEl.parentElement.classList.add('invalid');
                            fieldEl.parentElement.querySelector('.error-message').textContent = <?php echo json_encode($message); ?>;
                        }
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            function showToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease forwards';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            }

            function formatCPF(value) {
                value = value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                return value
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }

            function formatTelefone(value) {
                value = value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                if (value.length <= 2) {
                    return value;
                } else if (value.length <= 7) {
                    return `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else {
                    return `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                }
            }

            function isValidCPF(cpf) {
                cpf = cpf.replace(/\D/g, '');
                if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
                let sum = 0;
                let remainder;
                for (let i = 1; i <= 9; i++) {
                    sum += parseInt(cpf[i - 1]) * (11 - i);
                }
                remainder = (sum * 10) % 11;
                if (remainder === 10 || remainder === 11) remainder = 0;
                if (remainder !== parseInt(cpf[9])) return false;
                sum = 0;
                for (let i = 1; i <= 10; i++) {
                    sum += parseInt(cpf[i - 1]) * (12 - i);
                }
                remainder = (sum * 10) % 11;
                if (remainder === 10 || remainder === 11) remainder = 0;
                if (remainder !== parseInt(cpf[10])) return false;
                return true;
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function isValidTelefone(telefone) {
                return /^\(\d{2}\)\s\d{5}-\d{4}$/.test(telefone);
            }

            function isValidDate(date) {
                const today = new Date();
                const inputDate = new Date(date);
                return inputDate <= today && inputDate.getFullYear() >= 1900;
            }

            function isValidGenero(genero) {
                return ['Masculino', 'Feminino', 'Outro'].includes(genero);
            }

            // NOVA FUNÇÃO: Validação melhorada do nome
            function isValidNome(nome) {
                nome = nome.trim();
                
                // Verifica se está vazio
                if (!nome) {
                    return { valid: false, message: 'Nome é obrigatório.' };
                }
                
                // Verifica se tem pelo menos 3 caracteres
                if (nome.length < 3) {
                    return { valid: false, message: 'Nome deve ter pelo menos 3 caracteres.' };
                }
                
                // Verifica se contém apenas letras, espaços, hífens e apóstrofos
                if (!/^[a-zA-ZÀ-ÿ\s\-']+$/.test(nome)) {
                    return { valid: false, message: 'Nome deve conter apenas letras.' };
                }
                
                // Verifica se contém números
                if (/\d/.test(nome)) {
                    return { valid: false, message: 'Nome não pode conter números.' };
                }
                
                // Verifica se tem pelo menos um espaço (nome e sobrenome)
                if (!nome.includes(' ')) {
                    return { valid: false, message: 'Por favor, insira nome e sobrenome.' };
                }
                
                // Divide o nome em partes
                const partes = nome.split(' ').filter(p => p.length > 0);
                
                // Verifica se tem pelo menos 2 partes
                if (partes.length < 2) {
                    return { valid: false, message: 'Por favor, insira nome e sobrenome.' };
                }
                
                // Verifica se cada parte tem pelo menos 2 caracteres
                for (let parte of partes) {
                    if (parte.length < 2) {
                        return { valid: false, message: 'Nome e sobrenome devem ter pelo menos 2 caracteres cada.' };
                    }
                }
                
                return { valid: true, message: '' };
            }

            function isValidPassword(password) {
                return password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password);
            }

            // Validação em tempo real do nome
            nomeInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                const validation = isValidNome(this.value);
                
                if (this.value.trim().length === 0) {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                } else if (validation.valid) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = validation.message;
                }
            });

            cpfInput.addEventListener('input', function() {
                this.value = formatCPF(this.value);
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                if (this.value.length === 14) {
                    if (isValidCPF(this.value)) {
                        inputGroup.classList.remove('invalid');
                        inputGroup.classList.add('valid');
                        errorMessage.textContent = '';
                    } else {
                        inputGroup.classList.remove('valid');
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'CPF inválido.';
                    }
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
            });

            telefoneInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                const cursorPosition = this.selectionStart;
                const oldValue = this.value;
                const oldLength = oldValue.length;
                this.value = formatTelefone(this.value);

                let newCursorPosition = cursorPosition;
                const newValue = this.value;
                const nonDigitCountBefore = (oldValue.slice(0, cursorPosition).match(/\D/g) || []).length;
                const nonDigitCountAfter = (newValue.slice(0, cursorPosition).match(/\D/g) || []).length;
                newCursorPosition += nonDigitCountAfter - nonDigitCountBefore;
                if (newValue.length > oldLength && /[(-)]/.test(newValue[cursorPosition - 1])) {
                    newCursorPosition++;
                }
                if (newCursorPosition >= 0 && newCursorPosition <= newValue.length) {
                    this.setSelectionRange(newCursorPosition, newCursorPosition);
                }

                if (this.value.length === 15) {
                    if (isValidTelefone(this.value)) {
                        inputGroup.classList.remove('invalid');
                        inputGroup.classList.add('valid');
                        errorMessage.textContent = '';
                    } else {
                        inputGroup.classList.remove('valid');
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'Telefone inválido. Use o formato (xx) xxxxx-xxxx.';
                    }
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
            });

            generoInput.addEventListener('change', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                if (isValidGenero(this.value)) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'Por favor, selecione um gênero válido.';
                }
            });

            function checkPasswordStrength(password) {
                const strengthBar = document.querySelector('.password-strength-fill');
                const strengthText = document.querySelector('.password-strength-text');
                const strengthContainer = document.querySelector('.password-strength');

                if (password.length > 0) {
                    strengthContainer.style.display = 'block';
                } else {
                    strengthContainer.style.display = 'none';
                    return;
                }

                let strength = 0;
                let feedback = '';
                if (password.length >= 8) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;

                strengthBar.className = 'password-strength-fill';
                switch (strength) {
                    case 0:
                    case 1:
                        strengthBar.classList.add('strength-weak');
                        feedback = 'Senha muito fraca';
                        break;
                    case 2:
                        strengthBar.classList.add('strength-fair');
                        feedback = 'Senha fraca';
                        break;
                    case 3:
                        strengthBar.classList.add('strength-good');
                        feedback = 'Senha boa';
                        break;
                    case 4:
                    case 5:
                        strengthBar.classList.add('strength-strong');
                        feedback = 'Senha forte';
                        break;
                }
                strengthText.textContent = feedback;
            }

            window.toggleSenha = function(id) {
                const input = document.getElementById(id);
                const icon = input.nextElementSibling.nextElementSibling.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            };

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                let isValid = true;

                // Limpar erros anteriores
                inputs.forEach(input => {
                    const inputGroup = input.parentElement;
                    const errorMessage = inputGroup.querySelector('.error-message');
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                });

                // Validações no lado do cliente
                inputs.forEach(input => {
                    const inputGroup = input.parentElement;
                    const errorMessage = inputGroup.querySelector('.error-message');
                    
                    if (!input.value && input.required) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = `Por favor, preencha este campo.`;
                        isValid = false;
                    } else if (input.id === 'nome') {
                        const validation = isValidNome(input.value);
                        if (!validation.valid) {
                            inputGroup.classList.add('invalid');
                            errorMessage.textContent = validation.message;
                            isValid = false;
                        } else {
                            inputGroup.classList.add('valid');
                        }
                    } else if (input.id === 'cpf' && !isValidCPF(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'CPF inválido.';
                        isValid = false;
                    } else if (input.id === 'email' && !isValidEmail(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'E-mail inválido.';
                        isValid = false;
                    } else if (input.id === 'telefone' && !isValidTelefone(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'Telefone inválido. Use o formato (xx) xxxxx-xxxx.';
                        isValid = false;
                    } else if (input.id === 'datanasc' && !isValidDate(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'Data de nascimento inválida.';
                        isValid = false;
                    } else if (input.id === 'genero' && !isValidGenero(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'Por favor, selecione um gênero válido.';
                        isValid = false;
                    } else if (input.id === 'password' && !isValidPassword(input.value)) {
                        inputGroup.classList.add('invalid');
                        errorMessage.textContent = 'Senha deve ter pelo menos 8 caracteres, incluindo letras maiúsculas, números e símbolos.';
                        isValid = false;
                    } else if (input.value && input.id !== 'confirmPassword') {
                        inputGroup.classList.add('valid');
                    }
                });

                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.parentElement.classList.add('invalid');
                    confirmPasswordInput.parentElement.querySelector('.error-message').textContent = 'As senhas não coincidem.';
                    isValid = false;
                } else if (passwordInput.value) {
                    confirmPasswordInput.parentElement.classList.add('valid');
                }

                if (isValid) {
                    const submitBtn = this.querySelector('.signup-btn');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cadastrando...';
                    submitBtn.disabled = true;
                    this.submit();
                } else {
                    showToast('Por favor, corrija os erros no formulário', 'error');
                }
            });

            passwordInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                checkPasswordStrength(this.value);
                if (isValidPassword(this.value)) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else if (this.value) {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'Senha deve ter pelo menos 8 caracteres, incluindo letras maiúsculas, números e símbolos.';
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
                if (this.value === confirmPasswordInput.value && this.value) {
                    confirmPasswordInput.parentElement.classList.remove('invalid');
                    confirmPasswordInput.parentElement.classList.add('valid');
                    confirmPasswordInput.parentElement.querySelector('.error-message').textContent = '';
                } else if (confirmPasswordInput.value) {
                    confirmPasswordInput.parentElement.classList.add('invalid');
                    confirmPasswordInput.parentElement.querySelector('.error-message').textContent = 'As senhas não coincidem.';
                }
            });

            confirmPasswordInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                if (this.value === passwordInput.value && this.value) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else if (this.value) {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'As senhas não coincidem.';
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
            });

            emailInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                if (isValidEmail(this.value)) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else if (this.value) {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'E-mail inválido.';
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
            });

            datanascInput.addEventListener('input', function() {
                const inputGroup = this.parentElement;
                const errorMessage = inputGroup.querySelector('.error-message');
                if (isValidDate(this.value)) {
                    inputGroup.classList.remove('invalid');
                    inputGroup.classList.add('valid');
                    errorMessage.textContent = '';
                } else if (this.value) {
                    inputGroup.classList.remove('valid');
                    inputGroup.classList.add('invalid');
                    errorMessage.textContent = 'Data de nascimento inválida.';
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                    errorMessage.textContent = '';
                }
            });
        });
    </script>
</body>

</html>