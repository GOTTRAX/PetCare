<?php
session_start();
$base_path = "/Bruno/PetCare/";
include "conexao.php";

// Permitir acesso público - não redirecionar
$usuario_logado = isset($_SESSION['id']);
$usuario_id = $_SESSION['id'] ?? null;
$tipo = $_SESSION['tipo_usuario'] ?? null;

include "header.php";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PetCare - Serviços de Cirurgia Veterinária com Excelência e Precisão">
    <meta name="keywords" content="petcare, cirurgia veterinária, castração, ortopedia, oftalmologia, oncologia, cuidado pet">
    <meta name="author" content="PetCare">
    <title>Cirurgias - PetCare</title>
    
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios/452/cat.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap');

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
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--white);
            overflow-x: hidden;
        }

        /* Header e Navegação */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.98);
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
            border-color: var(--secondary-color);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

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
            background: var(--primary-color);
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .nav-links a:hover::before {
            width: 100%;
        }

        /* User Menu and Dropdown */
        .user-menu {
            position: relative;
        }

        .user-name-container {
            position: relative;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: var(--transition);
            background: rgba(46, 139, 87, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .user-name:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 150px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            z-index: 1000;
        }

        .user-name-container:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: block;
            padding: 0.8rem 1.2rem;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .dropdown-menu a:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        /* Seção Hero */
        .hero {
            display: flex;
            align-items: center;
            min-height: 80vh;
            padding: 120px 5% 80px;
            max-width: 1400px;
            margin: 0 auto;
            gap: 3rem;
        }

        .hero-text {
            flex: 1;
            max-width: 600px;
        }

        .hero h1 {
            font-size: clamp(2.2rem, 4vw, 3.5rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .highlight {
            color: var(--primary-color);
        }

        .hero p {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn.primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn.primary:hover {
            background: var(--primary-dark);
        }

        .btn.secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn.secondary:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .image-container {
            flex: 1;
            max-width: 600px;
        }

        .image-container img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid rgba(46, 139, 87, 0.1);
        }

        /* Seção de Cirurgias */
        .cirurgias {
            padding: 80px 5%;
            background: var(--background-light);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
        }

        .cirurgias h2 {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .cirurgias p {
            font-size: 1.1rem;
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        .procedure-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }

        .procedure-table th, .procedure-table td {
            padding: 1.5rem;
            text-align: left;
            border-bottom: 1px solid rgba(46, 139, 87, 0.2);
        }

        .procedure-table th {
            background: var(--primary-color);
            color: var(--white);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .procedure-table td {
            background: var(--white);
            color: var(--text-dark);
            font-size: 1rem;
        }

        .procedure-table tr:hover td {
            background: rgba(46, 139, 87, 0.05);
        }

        .procedure-table .procedure-name {
            font-weight: 600;
            color: var(--primary-dark);
        }

        /* Seção Benefícios */
        .beneficios {
            padding: 80px 5%;
            background: var(--white);
        }

        .beneficios h2 {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .beneficios p {
            font-size: 1.1rem;
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        .benefit-list {
            list-style: none;
            max-width: 800px;
            margin: 0 auto;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--white);
            border: 1px solid rgba(46, 139, 87, 0.1);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .benefit-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .benefit-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: 1rem;
            margin-top: 0.2rem;
        }

        .benefit-item div h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .benefit-item div p {
            font-size: 1rem;
            color: var(--text-light);
            line-height: 1.6;
        }

        /* Seção FAQ */
        .faq {
            padding: 80px 5%;
            background: var(--background-light);
        }

        .faq .container {
            max-width: 900px;
        }

        .faq h2 {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .faq p {
            font-size: 1.1rem;
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        .faq-item {
            background: var(--white);
            margin-bottom: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid rgba(46, 139, 87, 0.1);
            overflow: hidden;
        }

        .faq-item h3 {
            padding: 1.2rem 1.5rem;
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background var(--transition);
        }

        .faq-item h3:hover {
            background: rgba(46, 139, 87, 0.05);
        }

        .faq-item h3::after {
            content: '+';
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .faq-item.active h3::after {
            content: '−';
            transform: rotate(180deg);
        }

        .faq-item p {
            margin: 0;
            padding: 0 1.5rem;
            color: var(--text-light);
            line-height: 1.7;
            font-size: 1rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }

        .faq-item.active p {
            max-height: 300px; /* Aumentado para garantir espaço suficiente */
            padding: 1rem 1.5rem;
        }

        /* Footer */
        .footer {
            background: var(--text-dark);
            color: var(--white);
            padding: 4rem 5% 2rem;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            max-width: 1400px;
            margin: 0 auto;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--secondary-color);
        }

        .footer-section p {
            color: #BDC3C7;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .social-icons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 50%;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-icons a:hover {
            background: var(--primary-dark);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section ul li a {
            color: #BDC3C7;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-section ul li a:hover {
            color: var(--secondary-color);
        }

        .horario p {
            margin-bottom: 0.5rem;
        }

        .emergencia a {
            color: var(--accent-color);
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--accent-color);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            display: inline-block;
            margin-top: 1rem;
            transition: var(--transition);
        }

        .emergencia a:hover {
            background: var(--accent-color);
            color: var(--white);
        }

        .footer-bottom {
            border-top: 1px solid #34495E;
            padding-top: 2rem;
            text-align: center;
            color: #95A5A6;
        }

        .footer-bottom p {
            margin-bottom: 0.5rem;
        }

        .heart {
            color: var(--accent-color);
            font-size: 1.2rem;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem 3%;
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }
            
            .hero {
                flex-direction: column;
                text-align: center;
                padding: 100px 3% 60px;
                gap: 2rem;
            }
            
            .hero-text {
                order: 2;
            }
            
            .image-container {
                order: 1;
                max-width: 400px;
            }
            
            .buttons {
                justify-content: center;
            }
            
            .btn {
                flex: 1;
                min-width: 140px;
                text-align: center;
            }
            
            .cirurgias, .beneficios, .faq {
                padding: 60px 3%;
            }
            
            .procedure-table {
                display: block;
                overflow-x: auto;
            }
            
            .procedure-table th, .procedure-table td {
                padding: 1rem;
                font-size: 0.9rem;
            }
            
            .footer {
                padding: 3rem 3% 1.5rem;
            }
            
            .footer-container {
                gap: 2rem;
            }

            .user-menu {
                order: 1;
                align-self: flex-end;
            }

            .faq-item h3 {
                font-size: 1.1rem;
                padding: 1rem 1.2rem;
            }

            .faq-item p {
                font-size: 0.95rem;
            }

            .faq-item.active p {
                max-height: 400px; /* Ajustado para telas menores */
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding: 80px 1rem 40px;
            }
            
            .cirurgias, .beneficios, .faq {
                padding: 40px 1rem;
            }
            
            .footer {
                padding: 2rem 1rem 1rem;
            }
            
            .procedure-table th, .procedure-table td {
                padding: 0.8rem;
                font-size: 0.85rem;
            }
            
            .user-name {
                padding: 0.4rem 0.8rem;
                font-size: 1rem;
            }

            .faq-item h3 {
                font-size: 1rem;
                padding: 0.8rem 1rem;
            }

            .faq-item p {
                font-size: 0.9rem;
            }

            .faq-item.active p {
                max-height: 500px; /* Ajustado para telas muito pequenas */
                padding: 0.8rem 1rem;
            }
        }

        /* Scroll suave */
        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-text">
                <h1 id="hero-title">Excelência em <span class="highlight">Cirurgias Veterinárias</span></h1>
                <p>Na PetCare, oferecemos serviços cirúrgicos veterinários de alta qualidade, utilizando tecnologia avançada e protocolos rigorosos para garantir a segurança e o bem-estar do seu pet. Nossa equipe de cirurgiões especializados está preparada para realizar desde procedimentos de rotina até intervenções complexas, sempre com foco na precisão e na recuperação eficiente.</p>
                <div class="buttons">
                    <a href="consultas.php" class="btn primary">Agendar Consulta</a>
                    <a href="#faq" class="btn secondary">Saiba Mais</a>
                </div>
            </div>
            <div class="image-container">
                <img src="https://images.pexels.com/photos/6235124/pexels-photo-6235124.jpeg" alt="Equipe veterinária em cirurgia" loading="lazy">
            </div>
        </section>

        <section id="cirurgias" class="cirurgias" aria-labelledby="cirurgias-title">
            <div class="container">
                <h2 id="cirurgias-title">Nossos Procedimentos Cirúrgicos</h2>
                <p>Nossa clínica dispõe de um centro cirúrgico equipado com tecnologia de ponta, projetado para atender às necessidades de pets em diversas especialidades cirúrgicas. Cada procedimento é precedido por uma avaliação detalhada, garantindo um plano de tratamento personalizado. Abaixo, apresentamos os principais procedimentos realizados:</p>
                <table class="procedure-table">
                    <thead>
                        <tr>
                            <th>Procedimento</th>
                            <th>Descrição</th>
                            <th>Indicações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="procedure-name">Castração</td>
                            <td>Procedimento para esterilização de machos e fêmeas, utilizando técnicas minimamente invasivas para reduzir o tempo de recuperação.</td>
                            <td>Controle populacional, prevenção de doenças reprodutivas e redução de comportamentos indesejados.</td>
                        </tr>
                        <tr>
                            <td class="procedure-name">Cirurgia Ortopédica</td>
                            <td>Intervenções para tratar fraturas, luxações e problemas articulares, com uso de implantes e suporte pós-operatório especializado.</td>
                            <td>Fraturas ósseas, displasia de quadril, ruptura de ligamentos e outras condições musculoesqueléticas.</td>
                        </tr>
                        <tr>
                            <td class="procedure-name">Cirurgia de Tecidos Moles</td>
                            <td>Procedimentos em órgãos internos, como remoção de massas ou correção de anomalias, realizados com equipamentos de alta precisão.</td>
                            <td>Remoção de tumores, obstruções intestinais, correção de hérnias e outras condições internas.</td>
                        </tr>
                        <tr>
                            <td class="procedure-name">Cirurgia Oftalmológica</td>
                            <td>Intervenções para tratar condições oculares, como catarata, glaucoma ou úlceras corneanas, utilizando microcirurgia avançada.</td>
                            <td>Problemas de visão, infecções oculares graves e anomalias congênitas ou adquiridas.</td>
                        </tr>
                        <tr>
                            <td class="procedure-name">Cirurgia Oncológica</td>
                            <td>Remoção de tumores malignos ou benignos, com suporte diagnóstico e acompanhamento pós-operatório para otimizar a qualidade de vida.</td>
                            <td>Diagnóstico de tumores, massas suspeitas ou câncer confirmado.</td>
                        </tr>
                        <tr>
                            <td class="procedure-name">Cirurgia de Emergência</td>
                            <td>Intervenções imediatas para casos críticos, como traumas ou obstruções, com equipe disponível 24 horas.</td>
                            <td>Traumas graves, obstruções intestinais, hemorragias internas e outras emergências.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="beneficios" class="beneficios" aria-labelledby="beneficios-title">
            <div class="container">
                <h2 id="beneficios-title">Por que Escolher a PetCare?</h2>
                <p>Nossa clínica é reconhecida pela excelência em cuidados cirúrgicos veterinários, combinando expertise técnica, infraestrutura moderna e um compromisso inabalável com o bem-estar animal. Veja os diferenciais que nos destacam:</p>
                <ul class="benefit-list">
                    <li class="benefit-item">
                        <i class="fas fa-microscope"></i>
                        <div>
                            <h3>Infraestrutura de Ponta</h3>
                            <p>Nosso centro cirúrgico conta com equipamentos avançados, como monitores de anestesia em tempo real e sistemas de imagem de alta resolução, garantindo procedimentos seguros e precisos.</p>
                        </div>
                    </li>
                    <li class="benefit-item">
                        <i class="fas fa-user-md"></i>
                        <div>
                            <h3>Cirurgiões Especializados</h3>
                            <p>Nossa equipe é formada por veterinários com formação avançada e experiência em diversas especialidades cirúrgicas, assegurando o mais alto padrão de cuidado.</p>
                        </div>
                    </li>
                    <li class="benefit-item">
                        <i class="fas fa-band-aid"></i>
                        <div>
                            <h3>Cuidados Pós-Operatórios</h3>
                            <p>Oferecemos planos personalizados de recuperação, incluindo fisioterapia, medicações e acompanhamento contínuo para garantir uma reabilitação eficaz.</p>
                        </div>
                    </li>
                    <li class="benefit-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h3>Segurança Anestésica</h3>
                            <p>Utilizamos protocolos anestésicos modernos, com monitoramento constante dos sinais vitais, minimizando riscos durante os procedimentos.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <section id="faq" class="faq" aria-labelledby="faq-title">
            <div class="container">
                <h2 id="faq-title">Perguntas Frequentes sobre Cirurgias</h2>
                <p>Entendemos que procedimentos cirúrgicos podem gerar dúvidas e preocupações. Abaixo, respondemos às perguntas mais comuns para esclarecer e tranquilizar os tutores:</p>
                <div class="faq-item">
                    <h3>Como saber se meu pet precisa de cirurgia?</h3>
                    <p>Realizamos uma avaliação completa, incluindo exames clínicos, laboratoriais e de imagem, para determinar a necessidade de intervenção cirúrgica. Um diagnóstico preciso é essencial para um tratamento eficaz.</p>
                </div>
                <div class="faq-item">
                    <h3>Quais são os riscos associados à anestesia?</h3>
                    <p>Utilizamos anestesias seguras e monitoramos continuamente os sinais vitais do seu pet. Avaliações pré-operatórias detalhadas minimizam quaisquer riscos, garantindo maior segurança.</p>
                </div>
                <div class="faq-item">
                    <h3>Qual é o tempo de recuperação de uma cirurgia?</h3>
                    <p>O tempo de recuperação varia conforme o procedimento. Castrações geralmente requerem 7-10 dias, enquanto cirurgias ortopédicas podem levar semanas. Fornecemos orientações detalhadas para cada caso.</p>
                </div>
                <div class="faq-item">
                    <h3>Vocês oferecem suporte para emergências cirúrgicas?</h3>
                    <p>Sim, nossa clínica mantém uma equipe de plantão 24 horas para atender emergências cirúrgicas, garantindo resposta rápida em situações críticas.</p>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar scroll effect
            const header = document.querySelector('header');
            const navbar = document.querySelector('.navbar');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    header.style.background = 'rgba(255, 255, 255, 1)';
                    header.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.1)';
                    navbar.style.padding = '0.7rem 5%';
                } else {
                    header.style.background = 'rgba(255, 255, 255, 0.98)';
                    header.style.boxShadow = 'none';
                    navbar.style.padding = '1rem 5%';
                }
            });

            // Smooth scroll for internal links
            const internalLinks = document.querySelectorAll('a[href^="#"]');
            internalLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        const headerHeight = header.offsetHeight;
                        const targetPosition = targetElement.offsetTop - headerHeight - 20;
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // FAQ interactivity
            const faqItems = document.querySelectorAll('.faq-item');
            faqItems.forEach(item => {
                const question = item.querySelector('h3');
                const answer = item.querySelector('p');
                
                question.addEventListener('click', function() {
                    const isOpen = item.classList.contains('active');
                    
                    // Close all other FAQ items
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('active');
                            const otherAnswer = otherItem.querySelector('p');
                            otherAnswer.style.maxHeight = '0';
                            otherAnswer.style.padding = '0 1.5rem';
                        }
                    });
                    
                    // Toggle current FAQ item
                    if (!isOpen) {
                        item.classList.add('active');
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                        answer.style.padding = '1rem 1.5rem';
                    } else {
                        item.classList.remove('active');
                        answer.style.maxHeight = '0';
                        answer.style.padding = '0 1.5rem';
                    }
                });
            });
        });
    </script>
</body>
</html>