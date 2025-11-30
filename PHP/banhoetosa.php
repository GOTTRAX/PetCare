<?php
session_start();
$base_path = "../"; 
include $base_path . "PHP/conexao.php";
include $base_path . "PHP/header.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Banho e Tosa na PetCare - Serviços profissionais para manter seu pet limpo e saudável">
    <meta name="keywords" content="banho e tosa, petcare, higiene animal, cuidados pet, tosa higiênica">
    <meta name="author" content="PetCare">
    <title>Banho e Tosa - PetCare</title>

    <link rel="icon" type="image/png" href="<?= $base_path ?>assets/img/favicon.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="<?= $base_path ?>Estilos/styles.css">

    <style>
        /* HERO */
        .hero-servico {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.5)), 
                        url('https://i.pinimg.com/1200x/77/df/ed/77dfed4020b5f6e711e757de59ca84da.jpg') center/cover no-repeat;
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 120px 5% 80px;
            margin-top: 80px;
        }

        .hero-servico h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .hero-servico p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
        }

        /* CONTEÚDO */
        .servico-content {
            padding: 80px 5%;
            background: #f8f9fa;
        }

        .container-narrow {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* TÍTULO PRINCIPAL MELHORADO */
        .main-title {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .main-title h2 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2E8B57, #48B973);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
            position: relative;
            padding: 0 1rem;
        }

        .main-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, transparent, #48B973, transparent);
            border-radius: 2px;
        }

        .main-title .icon-sparkle {
            position: absolute;
            top: -20px;
            right: 30%;
            font-size: 1.8rem;
            color: #FFB84D;
            animation: sparkle 2s infinite;
        }

        @keyframes sparkle {
            0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.8; }
            50% { transform: scale(1.3) rotate(10deg); opacity: 1; }
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: #2C3E50;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .section-title h2::before {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50%;
            height: 3px;
            background: #2E8B57;
            border-radius: 2px;
        }

        .section-title p {
            color: #7F8C8D;
            max-width: 700px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        /* TIPOS DE SERVIÇOS */
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .card-icon {
            text-align: center;
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .card-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #2E8B57, #48B973);
            border-radius: 20px 20px 0 0;
        }

        .card-icon:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 20px 40px rgba(46, 139, 87, 0.2);
        }

        .card-icon i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #2E8B57, #48B973);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.2rem;
            display: block;
        }

        .card-icon h3 {
            font-size: 1.6rem;
            color: #2C3E50;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .card-icon p {
            color: #7F8C8D;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* BENEFÍCIOS - ÍCONES MODERNOS E CRIATIVOS */
        .benefits {
            list-style: none;
            max-width: 900px;
            margin: 0 auto 4rem;
        }

        .benefits li {
            display: flex;
            align-items: flex-start;
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 1.2rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .benefits li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background: linear-gradient(180deg, #2E8B57, #48B973);
            border-radius: 16px 0 0 16px;
        }

        .benefits li:hover {
            transform: translateX(12px);
            box-shadow: 0 8px 25px rgba(46, 139, 87, 0.15);
        }

        .benefits li i {
            font-size: 1.8rem;
            margin-right: 1.5rem;
            margin-top: 0.1rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* ÍCONES PERSONALIZADOS */
        .benefits li:nth-child(1) i { background: linear-gradient(135deg, #FF6B6B, #FF8E8E); color: white; }
        .benefits li:nth-child(2) i { background: linear-gradient(135deg, #4ECDC4, #7ED9D3); color: white; }
        .benefits li:nth-child(3) i { background: linear-gradient(135deg, #FFB84D, #FFCD72); color: white; }
        .benefits li:nth-child(4) i { background: linear-gradient(135deg, #A0D468, #B8E986); color: white; }
        .benefits li:nth-child(5) i { background: linear-gradient(135deg, #9B59B6, #BB8FCE); color: white; }

        .benefits li span {
            flex: 1;
            color: #2C3E50;
            font-weight: 500;
            font-size: 1.05rem;
            line-height: 1.6;
        }

        /* PROCESSO */
        .steps {
            display: grid;
            gap: 1.5rem;
            counter-reset: step;
            margin-bottom: 4rem;
        }

        .step {
            background: white;
            padding: 2rem 1.5rem 1.5rem 5.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
            min-height: 80px;
            transition: all 0.3s ease;
        }

        .step:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .step::before {
            content: counter(step);
            counter-increment: step;
            position: absolute;
            left: 1.8rem;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #7d8a83, #48B973);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1;
        }

        .step h3 {
            color: #2C3E50;
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .step p {
            color: #7F8C8D;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* CTA FINAL - BOTÃO CORRIGIDO */
        .cta-final {
            text-align: center;
            padding: 3.5rem 2.5rem;
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(46, 139, 87, 0.15);
            margin: 3rem auto;
            max-width: 850px;
            position: relative;
            overflow: hidden;
        }

        .cta-final::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #2E8B57, #48B973, #FFB84D);
        }

        .cta-final h3 {
            color: #2C3E50;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .cta-final p {
            color: #7F8C8D;
            font-size: 1.15rem;
            margin-bottom: 2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-agendar {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #2E8B57, #48B973);
            color: white;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            font-size: 1.1rem;
            box-shadow: 0 8px 20px rgba(46, 139, 87, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-agendar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .btn-agendar:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(46, 139, 87, 0.4);
        }

        .btn-agendar:hover::before {
            left: 100%;
        }

        .btn-agendar i {
            font-size: 1.3rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* RESPONSIVIDADE */
        @media (max-width: 992px) {
            .hero-servico { min-height: 60vh; padding: 100px 5% 60px; }
            .main-title h2 { font-size: 2.5rem; }
        }

        @media (max-width: 768px) {
            .servico-content { padding: 60px 3%; }
            .grid-3 { grid-template-columns: 1fr; }
            .benefits li { padding: 1.2rem 1.5rem; }
            .benefits li i { width: 45px; height: 45px; font-size: 1.5rem; }
            .step { padding: 1.8rem 1.2rem 1.2rem 5rem; }
            .step::before { left: 1.6rem; width: 44px; height: 44px; font-size: 1.2rem; }
        }

        @media (max-width: 480px) {
            .hero-servico { padding: 80px 1rem 50px; }
            .main-title h2 { font-size: 2.2rem; }
            .section-title h2 { font-size: 2rem; }
            .card-icon { padding: 2rem 1.5rem; }
            .card-icon i { font-size: 3rem; }
            .step { padding: 1.8rem 1.2rem 1.2rem 4.8rem; }
            .step::before { left: 1.5rem; width: 40px; height: 40px; font-size: 1.1rem; }
            .cta-final { padding: 2.5rem 1.5rem; }
            .btn-agendar { padding: 12px 24px; font-size: 1rem; }
        }
    </style>
</head>

<body>
    <main>
        <section class="hero-servico" aria-labelledby="hero-title">
            <div>
                <h1 id="hero-title">Banho e Tosa</h1>
                <p>Serviços profissionais para manter seu pet limpo, saudável e com aparência impecável</p>
            </div>
        </section>

        <section class="servico-content">
            <div class="container-narrow">

                <div class="main-title">
                    <h2>Banho e Tosa na PetCare</h2>
                    <i class="fas fa-sparkles icon-sparkle"></i>
                </div>
                <p style="text-align: center; color: #7F8C8D; max-width: 800px; margin: 0 auto 4rem; font-size: 1.1rem;">
                    Nossas unidades em São Paulo oferecem serviços de banho e tosa realizados por profissionais qualificados, utilizando produtos seguros e técnicas que garantem o conforto e bem-estar do seu pet.
                </p>

                <div class="grid-3">
                    <div class="card-icon">
                        <i class="fas fa-shower"></i>
                        <h3>Banho Simples</h3>
                        <p>Limpeza completa com shampoos hipoalergênicos, ideal para manutenção regular da higiene.</p>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-scissors"></i>
                        <h3>Tosa Higiênica</h3>
                        <p>Remoção de pelos em áreas sensíveis para conforto e prevenção de irritações.</p>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-paw"></i>
                        <h3>Banho e Tosa Completa</h3>
                        <p>Serviço premium com corte estilizado, secagem e finalização para um visual perfeito.</p>
                    </div>
                </div>

                <div class="section-title">
                    <h2>Benefícios para Seu Pet</h2>
                </div>
                <ul class="benefits">
                    <li><i class="fas fa-heartbeat"></i><span>Prevenção de problemas de pele e infecções dermatológicas.</span></li>
                    <li><i class="fas fa-bug-slash"></i><span>Redução de pulgas, carrapatos e odores indesejados.</span></li>
                    <li><i class="fas fa-grin-stars"></i><span>Aumento do conforto e autoestima do animal.</span></li>
                    <li><i class="fas fa-thermometer-half"></i><span>Tosas adaptadas à raça e clima para termorregulação.</span></li>
                    <li><i class="fas fa-medal"></i><span>Manutenção regular promove longevidade e saúde geral.</span></li>
                </ul>

                <div class="section-title">
                    <h2>Nosso Processo de Atendimento</h2>
                </div>
                <div class="steps">
                    <div class="step">
                        <h3>Avaliação Inicial</h3>
                        <p>Recebemos seu pet e avaliamos o tipo de pelo, pele e necessidades específicas.</p>
                    </div>
                    <div class="step">
                        <h3>Banho e Hidratação</h3>
                        <p>Uso de produtos premium para limpeza profunda e hidratação, com massagem relaxante.</p>
                    </div>
                    <div class="step">
                        <h3>Tosa Personalizada</h3>
                        <p>Corte preciso com ferramentas esterilizadas, seguindo o estilo escolhido.</p>
                    </div>
                    <div class="step">
                        <h3>Finalização e Cuidados</h3>
                        <p>Secagem, escovação e aplicação de protetores para um acabamento impecável.</p>
                    </div>
                </div>

                <div class="cta-final">
                    <h3>Cuidados Pós-Serviço</h3>
                    <p>Recomendamos escovação diária em casa e agendamentos regulares a cada 30-45 dias. Nossa equipe fornece orientações personalizadas para manter a higiene entre as visitas.</p>
                    <a href="<?= $base_path ?>PHP/Calendario/Calendario.php" class="btn-agendar">
                        <i class="fas fa-calendar-check"></i>
                        Agendar Banho e Tosa
                    </a>
                </div>

            </div>
        </section>
    </main>

    
   <?php include 'footer.php'; ?>
</body>

</html>