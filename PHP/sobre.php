<?php
session_start();
// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'conexao.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sobre a PetCare - Conheça nossa história, valores e compromisso com o bem-estar animal">
    <meta name="keywords" content="veterinária, petcare, sobre nós, cuidados pet">
    <meta name="author" content="PetCare">
    <title>Sobre Nós - PetCare Clínica Veterinária</title>
    
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios/452/cat.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    
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
            --accent-color: #FF6B6B;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --background-light: #F8F9FA;
            --white: #FFFFFF;
            --gradient-primary: linear-gradient(135deg, #7d8a83 0%, #48B973 100%);
            --gradient-secondary: linear-gradient(135deg, #F0A500 0%, #FFB84D 100%);
            --shadow-light: 0 4px 6px rgba(0, 0, 0, 0.07);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
            --shadow-large: 0 20px 40px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--white);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .site-wrapper {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
        }

        main { flex: 1 0 auto; }
        .footer { flex-shrink: 0; width: 100%; }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(46, 139, 87, 0.1);
            transition: var(--transition);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            gap: 0.5rem;
        }

        .logo-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            transition: var(--transition);
        }

        .logo:hover .logo-img {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--secondary-color);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .login-btn {
            background: var(--gradient-primary);
            color: var(--white) !important;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before { left: 100%; }
        .login-btn:hover { transform: translateY(-2px) scale(1.05); }

        .login-btn i { font-size: 1rem; transition: var(--transition); }
        .login-btn:hover i { transform: scale(1.1) rotate(5deg); }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            position: relative;
            padding: 0.5rem 0;
            transition: var(--transition);
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: var(--transition);
        }

        .nav-links a:hover { color: var(--primary-color); }
        .nav-links a:hover::before { width: 100%; }

        /* Tag */
        .tag {
            display: inline-block;
            background: var(--gradient-secondary);
            color: var(--white);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-light);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        /* Botões */
        .btn {
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before { left: 100%; }

        .btn.primary {
            background: var(--gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow-medium);
        }

        .btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
        }

        /* BOTÃO WHATSAPP - MESMA COR DO INDEX.PHP */
        .btn.whatsapp {
            background: var(--gradient-primary); /* EXATAMENTE IGUAL AO INDEX */
            color: white;
            border-radius: 16px; /* Bordas quadradas */
            padding: 1rem 1.8rem;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(46, 139, 87, 0.3);
            transition: all 0.3s ease;
        }

        .btn.whatsapp:hover {
            background: linear-gradient(135deg, #1F5F3F, #2E8B57);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(46, 139, 87, 0.4);
        }

        .btn.whatsapp i {
            font-size: 1.4rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Hero */
        .hero-sobre {
            background: url('https://images.pexels.com/photos/14824170/pexels-photo-14824170.jpeg') center/cover no-repeat;
            min-height: 60vh;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            margin-top: 80px;
        }

        .hero-sobre::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.3);
        }

        .hero-sobre-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1s ease-out forwards;
        }

        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .hero-sobre h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            margin-bottom: 1rem;
            font-weight: 800;
        }

        .hero-sobre p {
            font-size: 1.3rem;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* História */
        .nossa-historia {
            padding: 100px 5% 80px;
            background: var(--white);
        }

        .historia-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .historia-texto h2 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }

        .historia-texto p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }

        .historia-imagem img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: var(--shadow-large);
        }

        /* VALORES - ÍCONES E CORES MODERNAS */
        .valores-section {
            padding: 100 only 5%;
            background: var(--white);
        }

        .valores-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .valores-content h2 {
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--text-dark);
        }

        .valores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
            margin-top: 3rem;
        }

        .valor-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: var(--shadow-light);
        }

        .valor-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-large);
        }

        .valor-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        /* CORES E ÍCONES PERSONALIZADOS */
        .valor-card:nth-child(1) .valor-icon { background: linear-gradient(135deg, #FF6B6B, #FF8E8E); }
        .valor-card:nth-child(2) .valor-icon { background: linear-gradient(135deg, #4ECDC4, #7ED9D3); }
        .valor-card:nth-child(3) .valor-icon { background: linear-gradient(135deg, #FFB84D, #FFCD72); }
        .valor-card:nth-child(4) .valor-icon { background: linear-gradient(135deg, #9B59B6, #BB8FCE); }
        .valor-card:nth-child(5) .valor-icon { background: linear-gradient(135deg, #A0D468, #B8E986); }
        .valor-card:nth-child(6) .valor-icon { background: linear-gradient(135deg, #3498DB, #5DADE2); }

        .valor-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .valor-card p {
            color: var(--text-light);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Compromisso */
        .compromisso-section {
            background: var(--gradient-primary);
            color: white;
            padding: 80px 5%;
            text-align: center;
            position: relative;
        }

        .compromisso-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://images.pexels.com/photos/4269985/pexels-photo-4269985.jpeg') center/cover;
            opacity: 0.1;
        }

        .compromisso-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }

        .compromisso-content h2 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            font-weight: 700;
        }

        .compromisso-content p {
            font-size: 1.2rem;
            line-height: 1.8;
            opacity: 0.95;
        }

        /* CTA */
        .cta-section {
            background: var(--white);
            padding: 80px 5%;
            text-align: center;
        }

        .cta-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .cta-content h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .cta-content p {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
            line-height: 1.7;
        }

        .buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .navbar { padding: 1rem 3%; flex-direction: column; gap: 1rem; }
            .nav-links { gap: 1rem; flex-wrap: wrap; justify-content: center; width: 100%; order: 2; }
            .login-btn { order: -1; margin-bottom: 0.5rem; }
            .hero-sobre { margin-top: 100px; }
            .historia-content { grid-template-columns: 1fr; gap: 2rem; text-align: center; }
            .historia-texto h2 { font-size: 2rem; }
            .valores-grid { grid-template-columns: 1fr; gap: 2rem; }
            .nossa-historia, .valores-section, .compromisso-section, .cta-section { padding: 60px 3%; }
            .buttons { flex-direction: column; align-items: center; }
            .btn.whatsapp { width: 240px; justify-content: center; }
        }

        @media (max-width: 480px) {
            .hero-sobre { min-height: 50vh; padding: 2rem 1rem; }
            .nossa-historia, .valores-section, .compromisso-section, .cta-section { padding: 40px 1rem; }
            .historia-imagem img { height: 300px; }
        }

        html { scroll-behavior: smooth; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const heroSobreContent = document.querySelector('.hero-sobre-content');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 1s ease-out forwards';
                    }
                });
            }, { threshold: 0.2 });
            observer.observe(heroSobreContent);
        });
    </script>
</head>
<body>
    <div class="site-wrapper">
        <?php include '../PHP/header.php';?>

        <main>
            <section class="hero-sobre" aria-labelledby="hero-sobre-title">
                <div class="hero-sobre-content">
                    <h1 id="hero-sobre-title">Sobre a PetCare</h1>
                    <p>Mais de uma década dedicada ao cuidado e bem-estar dos animais, oferecendo tratamento veterinário de excelência com amor e responsabilidade.</p>
                </div>
            </section>

            <section class="nossa-historia" aria-labelledby="historia-title">
                <div class="historia-content">
                    <div class="historia-texto">
                        <span class="tag">Nossa História</span>
                        <h2 id="historia-title">Uma jornada de amor pelos animais</h2>
                        <p>A PetCare nasceu com o sonho de criar um espaço onde os animais pudessem receber cuidados veterinários de qualidade em um ambiente acolhedor e humanizado. Fundada pela Dra. Maria Silva, nossa clínica começou como um pequeno consultório e cresceu até se tornar uma das referências em medicina veterinária da região.</p>
                        <p>Nossa jornada é impulsionada pela inovação e pelo amor aos animais. Investimos constantemente em tecnologia de ponta e em uma equipe de profissionais dedicados, porque acreditamos que cada pet é único. Nosso compromisso é oferecer um atendimento excepcional, todos os dias.</p>
                        <p>Nossa missão vai além do tratamento médico: acreditamos na importância da educação dos tutores, na prevenção de doenças e no fortalecimento do vínculo entre humanos e animais de estimação.</p>
                    </div>
                    <div class="historia-imagem">
                        <img src="https://images.pexels.com/photos/6816861/pexels-photo-6816861.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1" alt="Nossa história" loading="lazy">
                    </div>
                </div>
            </section>

            <!-- VALORES COM ÍCONES COLORIDOS -->
            <section class="valores-section" aria-labelledby="valores-title">
                <div class="valores-content">
                    <span class="tag">Nossos Valores</span>
                    <h2 id="valores-title">O que nos move todos os dias</h2>
                    <div class="valores-grid">
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <h3>Amor pelos Animais</h3>
                            <p>Cada pet é tratado com carinho, respeito e dedicação, como se fosse da nossa própria família.</p>
                        </div>
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-flask"></i>
                            </div>
                            <h3>Excelência Técnica</h3>
                            <p>Investimos em tecnologia de ponta e capacitação para diagnósticos precisos e tratamentos eficazes.</p>
                        </div>
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3>Transparência</h3>
                            <p>Comunicação clara e honesta com os tutores sobre diagnósticos, tratamentos e custos.</p>
                        </div>
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-hands-helping"></i>
                            </div>
                            <h3>Humanização</h3>
                            <p>Empatia e apoio emocional para famílias nos momentos mais difíceis.</p>
                        </div>
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-seedling"></i>
                            </div>
                            <h3>Sustentabilidade</h3>
                            <p>Práticas eco-friendly e conscientização sobre bem-estar animal e meio ambiente.</p>
                        </div>
                        <div class="valor-card">
                            <div class="valor-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <h3>Educação</h3>
                            <p>Orientação preventiva para uma vida mais saudável e longa para os pets.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="compromisso-section" aria-labelledby="compromisso-title">
                <div class="compromisso-content">
                    <h2 id="compromisso-title">Nosso Compromisso</h2>
                    <p>Na PetCare, renovamos diariamente nosso compromisso de oferecer cuidados veterinários de excelência. Cada animal que passa por nossas portas recebe não apenas tratamento médico de qualidade, mas também todo o amor e respeito que merece.</p>
                </div>
            </section>

            <!-- CTA COM WHATSAPP NA MESMA COR DO INDEX -->
            <section class="cta-section" aria-labelledby="cta-title">
                <div class="cta-content">
                    <h2 id="cta-title">Faça parte da nossa família</h2>
                    <p>Venha conhecer nossa clínica e descobrir por que somos a escolha de milhares de tutores.</p>
                    <div class="buttons">
                        <a href="consultas.php" class="btn primary">Agendar Consulta</a>
                        <a href="https://wa.me/5518991418664?text=Olá!%20Gostaria%20de%20mais%20informações%20sobre%20a%20PetCare." 
                           class="btn whatsapp" 
                           target="_blank" 
                           rel="noopener">
                            <i class="fab fa-whatsapp"></i>
                            Fale pelo WhatsApp
                        </a>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <?php include 'footer.php';?>
</body>
</html>