<?php
ob_start();
session_start();

header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

$usuario_logado = isset($_SESSION["id"]) && $_SESSION["tipo_usuario"] === "Cliente";
$base_path = "/Bruno/PetCare/"; 

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
    <meta name="description" content="Exames veterinários de alta precisão na PetCare - Diagnósticos com tecnologia de ponta para a saúde do seu pet">
    <title>Exames Veterinários - PetCare Clínica Veterinária</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../styles.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');

        :root {
            --primary: #2E8B57;
            --primary-dark: #1F5F3F;
            --secondary: #c6c8c8;
            --accent: #4A90E2;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --bg-light: #F8F9FA;
            --white: #FFFFFF;
            --success: #E8F5E9;
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-md: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-lg: 0 20px 40px rgba(0,0,0,0.1);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; line-height: 1.6; color: var(--text-dark); background: var(--white); overflow-x: hidden; }

        /* ============= HEADER ============= */
        header {
            position: fixed; top: 0; width: 100%; z-index: 1000;
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(46,139,87,0.1); transition: var(--transition);
        }

        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 5%; max-width: 1400px; margin: 0 auto;
        }

        .logo {
            display: flex; align-items: center; gap: 0.5rem; font-size: 1.8rem;
            font-weight: 700; color: var(--primary); text-decoration: none;
        }

        .logo-img {
            width: 45px; height: 45px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--primary); transition: var(--transition);
        }

        .logo:hover .logo-img { transform: scale(1.1) rotate(5deg); border-color: var(--secondary); }

        .nav-links {
            display: flex; list-style: none; gap: 2rem; align-items: center;
        }

        .nav-links a {
            text-decoration: none; color: var(--text-dark); font-weight: 500;
            position: relative; padding: 0.5rem 0; transition: var(--transition);
        }

        .nav-links a::before {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 0; height: 2px; background: var(--primary); transition: var(--transition);
        }

        .nav-links a:hover { color: var(--primary); }
        .nav-links a:hover::before { width: 100%; }

        main { margin-top: 80px; }

        /* ============= PAGE HEADER ============= */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; padding: 5rem 5% 4rem; text-align: center; position: relative; overflow: hidden;
        }

        .page-header::before {
            content: ''; position: absolute; inset: 0;
            background: url('https://images.pexels.com/photos/6235048/pexels-photo-6235048.jpeg') center/cover;
            opacity: 0.1;
        }

        .page-header-content { position: relative; z-index: 2; }
        .page-header h1 { font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 800; margin-bottom: 1rem; }
        .page-header p { font-size: 1.2rem; opacity: 0.95; max-width: 700px; margin: 0 auto 1.5rem; line-height: 1.7; }

        .tag {
            display: inline-block; background: linear-gradient(135deg, #F0A500, #FFB84D);
            color: white; padding: 0.5rem 1.2rem; border-radius: 50px; font-size: 0.9rem;
            font-weight: 600; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

        /* ============= BOTÕES ============= */
        .btn {
            padding: 1rem 2rem; border-radius: var(--radius-md); font-weight: 600;
            text-decoration: none; transition: var(--transition); border: none; cursor: pointer;
            font-size: 1rem; display: inline-flex; align-items: center; gap: 10px;
            position: relative; overflow: hidden;
        }

        .btn::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before { left: 100%; }

        .btn.primary {
            background: linear-gradient(135deg, var(--primary), #236d45);
            color: white; box-shadow: var(--shadow-md);
        }

        .btn.primary:hover {
            transform: translateY(-2px); box-shadow: var(--shadow-lg);
        }

        .btn.secondary {
            background: white; color: var(--primary); border: 2px solid var(--primary);
        }

        .btn.secondary:hover {
            background: var(--primary); color: white;
        }

        .btn.whatsapp {
            background: #25D366; color: white; padding: 1rem 1.8rem;
            box-shadow: 0 8px 20px rgba(37,211,102,0.3);
        }

        .btn.whatsapp:hover {
            background: #1DA851; transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37,211,102,0.4);
        }

        .btn.whatsapp i { font-size: 1.4rem; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }

        /* ============= ABOUT SECTION ============= */
        .about-section { padding: 100px 5%; background: var(--bg-light); }
        .about-container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
        .about-content h2 { font-size: 2.5rem; color: var(--text-dark); margin-bottom: 2rem; font-weight: 700; }
        .about-content p { font-size: 1.1rem; line-height: 1.8; color: var(--text-light); margin-bottom: 1.5rem; }

        .about-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .stat-item { background: white; padding: 1.5rem; border-radius: var(--radius-md); text-align: center; box-shadow: var(--shadow-sm); transition: var(--transition); opacity: 0; transform: translateY(20px); }
        .stat-item.visible { opacity: 1; transform: translateY(0); transition: all 0.6s ease; }
        .stat-item:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }
        .stat-item i { font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem; }

        .about-image { border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-lg); }
        .about-image img { width: 100%; height: 450px; object-fit: cover; transition: var(--transition); }
        .about-image:hover img { transform: scale(1.05); }

        /* ============= EXAMES GRID ============= */
        .exames-section { padding: 100px 5%; background: white; }
        .exames-container { max-width: 1200px; margin: 0 auto; text-align: center; }
        .exames-container h2 { font-size: 2.5rem; margin-bottom: 1.5rem; color: var(--text-dark); }
        .exames-container p { max-width: 700px; margin: 0 auto 3rem; color: var(--text-light); line-height: 1.7; }

        .exames-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;
        }

        .exam-card {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-sm); transition: var(--transition); border: 1px solid rgba(0,0,0,0.05);
            opacity: 0; transform: translateY(30px);
        }

        .exam-card.visible { opacity: 1; transform: translateY(0); transition: all 0.6s ease; }

        .exam-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); }

        .exam-image {
            height: 220px; overflow: hidden; position: relative;
        }

        .exam-image img {
            width: 100%; height: 100%; object-fit: cover; transition: var(--transition);
            filter: blur(8px); transition: filter 0.4s ease;
        }

        .exam-card.visible .exam-image img { filter: blur(0); }

        .exam-card:hover .exam-image img { transform: scale(1.08); }

        .exam-content { padding: 1.8rem; text-align: left; }
        .exam-title { font-size: 1.4rem; font-weight: 600; color: var(--primary); margin-bottom: 0.8rem; }
        .exam-description { color: var(--text-light); line-height: 1.7; margin-bottom: 1rem; font-size: 0.95rem; }

        .exam-features { list-style: none; margin-top: 1rem; }
        .exam-features li { display: flex; align-items: center; gap: 0.5rem; color: var(--text-dark); margin-bottom: 0.6rem; font-size: 0.9rem; }
        .exam-features li i { color: var(--accent); font-size: 0.8rem; }

        /* ============= TIMELINE DE EXAMES ============= */
        .timeline-section { padding: 100px 5%; background: var(--bg-light); }
        .timeline-container { max-width: 1200px; margin: 0 auto; text-align: center; }
        .timeline-container h2 { font-size: 2.5rem; margin-bottom: 1.5rem; color: var(--text-dark); }
        .timeline-container p { max-width: 700px; margin: 0 auto 3rem; color: var(--text-light); line-height: 1.7; }

        .timeline { position: relative; padding: 2rem 0; }
        .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 50%; width: 4px; background: #ddd; transform: translateX(-50%); }

        .timeline-item {
            display: grid; grid-template-columns: 1fr 60px 1fr; gap: 2rem; margin-bottom: 4rem; align-items: center;
            opacity: 0; transform: translateY(30px); transition: all 0.6s ease;
        }

        .timeline-item.visible { opacity: 1; transform: translateY(0); }

        .timeline-item:nth-child(even) { direction: rtl; }
        .timeline-item:nth-child(even) > * { direction: ltr; }

        .timeline-content, .timeline-image {
            background: white; padding: 1.8rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
        }

        .timeline-content h3 { color: var(--primary); margin-bottom: 0.8rem; font-size: 1.3rem; }
        .timeline-content p { color: var(--text-light); line-height: 1.6; }

        .timeline-image { height: 280px; overflow: hidden; border-radius: var(--radius-lg); }
        .timeline-image img { width: 100%; height: 100%; object-fit: cover; filter: blur(8px); transition: filter 0.4s ease; }
        .timeline-image.visible img { filter: blur(0); }

        .timeline-dot {
            width: 50px; height: 50px; background: var(--primary); border-radius: 50%;
            position: relative; z-index: 2; margin: 0 auto;
            box-shadow: 0 0 0 10px rgba(46,139,87,0.2);
        }

        .timeline-dot::before {
            content: ''; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%); width: 20px; height: 20px;
            background: white; border-radius: 50%;
        }

        /* ============= CTA ============= */
        .cta-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; padding: 80px 5%; text-align: center;
        }

        .cta-content { max-width: 600px; margin: 0 auto; }
        .cta-content h2 { font-size: 2.5rem; margin-bottom: 1.5rem; }
        .cta-content p { font-size: 1.2rem; opacity: 0.95; margin-bottom: 2.5rem; line-height: 1.7; }

        .buttons { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }

        .login-prompt {
            background: rgba(255,255,255,0.15); padding: 1.8rem; border-radius: var(--radius-md);
            margin: 2rem 0; text-align: center; border-left: 5px solid white;
        }

        .login-prompt p { font-weight: 600; margin-bottom: 1rem; font-size: 1.1rem; }

        /* ============= RESPONSIVIDADE ============= */
        @media (max-width: 768px) {
            .navbar { padding: 1rem 3%; flex-direction: column; gap: 1rem; }
            .nav-links { gap: 1rem; flex-wrap: wrap; justify-content: center; width: 100%; }
            .about-container, .exames-grid, .timeline-item { grid-template-columns: 1fr; }
            .timeline::before { left: 30px; }
            .timeline-item { grid-template-columns: 1fr; gap: 1.5rem; }
            .timeline-dot { display: none; }
            .about-section, .exames-section, .timeline-section, .cta-section { padding: 60px 3%; }
            .buttons { flex-direction: column; align-items: center; }
            .btn.whatsapp { width: 240px; justify-content: center; }
            .exam-image, .timeline-image { height: 200px; }
        }

        @media (max-width: 480px) {
            .page-header { padding: 3rem 1rem; }
            .about-image img, .exam-image, .timeline-image { height: 180px; }
        }

        html { scroll-behavior: smooth; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <section class="page-header">
            <div class="page-header-content">
                <span class="tag">Diagnósticos de Excelência</span>
                <h1>Exames Veterinários</h1>
                <p>Diagnósticos precisos com tecnologia avançada para cuidar da saúde do seu pet com excelência e dedicação profissional.</p>
            </div>
        </section>

        <section class="about-section">
            <div class="about-container">
                <div class="about-content">
                    <h2>Tecnologia e Precisão em Diagnósticos</h2>
                    <p>Na PetCare, utilizamos equipamentos de última geração e uma equipe altamente qualificada para oferecer diagnósticos precisos e confiáveis. Nossa estrutura moderna permite realizar uma ampla gama de exames com rapidez e segurança.</p>
                    <p>Contamos com laboratório próprio e parcerias estratégicas para garantir resultados rápidos e precisos, essenciais para o tratamento adequado do seu companheiro.</p>
                    
                    <div class="about-stats">
                        <div class="stat-item">
                            <i class="fas fa-paw"></i>
                            <div><strong>+5.000</strong><br><span>Pets Atendidos</span></div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-clock"></i>
                            <div><strong>24h</strong><br><span>Atendimento</span></div>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-star"></i>
                            <div><strong>10+ Anos</strong><br><span>de Experiência</span></div>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <img src="https://images.pexels.com/photos/7470635/pexels-photo-7470635.jpeg" alt="Laboratório PetCare" loading="lazy">
                </div>
            </div>
        </section>

        <section class="exames-section">
            <div class="exames-container">
                <span class="tag">Nossos Exames</span>
                <h2>Exames Especializados</h2>
                <p>Oferecemos uma ampla gama de exames diagnósticos com equipamentos modernos e profissionais especializados para garantir a saúde do seu pet.</p>

                <div class="exames-grid">
                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://images.pexels.com/photos/1350591/pexels-photo-1350591.jpeg" alt="Hemogasometria" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Hemogasometria</h3>
                            <p class="exam-description">Análise precisa dos gases sanguíneos para monitoramento de funções respiratórias e metabólicas.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Resultados em tempo real</li>
                                <li><i class="fas fa-check-circle"></i> Alta precisão</li>
                                <li><i class="fas fa-check-circle"></i> Essencial para UTI</li>
                            </ul>
                        </div>
                    </div>

                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://images.pexels.com/photos/8450142/pexels-photo-8450142.jpeg" alt="Exames laboratoriais" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Exames Laboratoriais</h3>
                            <p class="exam-description">Hemogramas completos, bioquímica sanguínea e análises clínicas para diagnóstico abrangente.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Laboratório próprio</li>
                                <li><i class="fas fa-check-circle"></i> Resultados rápidos</li>
                                <li><i class="fas fa-check-circle"></i> Análises especializadas</li>
                            </ul>
                        </div>
                    </div>

                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://images.pexels.com/photos/6235048/pexels-photo-6235048.jpeg" alt="Radiologia digital" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Radiologia Digital</h3>
                            <p class="exam-description">Imagens de alta definição para diagnóstico de fraturas, problemas respiratórios e estruturais.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Tecnologia digital</li>
                                <li><i class="fas fa-check-circle"></i> Menor radiação</li>
                                <li><i class="fas fa-check-circle"></i> Imagens de alta qualidade</li>
                            </ul>
                        </div>
                    </div>

                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://momentoequestre.com.br/wp-content/uploads/2018/01/tomografia.gif" alt="Tomografia" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Tomografia Computadorizada</h3>
                            <p class="exam-description">Imagens 3D detalhadas para diagnósticos complexos e planejamento cirúrgico.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Imagens 3D</li>
                                <li><i class="fas fa-check-circle"></i> Diagnóstico preciso</li>
                                <li><i class="fas fa-check-circle"></i> Planejamento cirúrgico</li>
                            </ul>
                        </div>
                    </div>

                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://www.wellpetclinica.com.br/imagens/informacoes/ultrassonografia-veterinaria-no-ceara-09.jpg" alt="Ultrassonografia" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Ultrassonografia</h3>
                            <p class="exam-description">Exame não invasivo para avaliação de órgãos internos e gestação.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Não invasivo</li>
                                <li><i class="fas fa-check-circle"></i> Acompanhamento gestacional</li>
                                <li><i class="fas fa-check-circle"></i> Avaliação em tempo real</li>
                            </ul>
                        </div>
                    </div>

                    <div class="exam-card">
                        <div class="exam-image">
                            <img src="https://images.pexels.com/photos/7469219/pexels-photo-7469219.jpeg" alt="Ecocardiograma" loading="lazy">
                        </div>
                        <div class="exam-content">
                            <h3 class="exam-title">Ecocardiograma</h3>
                            <p class="exam-description">Avaliação especializada da função cardíaca com ultrassom.</p>
                            <ul class="exam-features">
                                <li><i class="fas fa-check-circle"></i> Especialista em cardiologia</li>
                                <li><i class="fas fa-check-circle"></i> Avaliação funcional</li>
                                <li><i class="fas fa-check-circle"></i> Diagnóstico preciso</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="cta-section">
            <div class="cta-content">
                <h2>Precisa Agendar um Exame?</h2>
                <p>Entre em contato agora e garanta o melhor cuidado para o seu pet.</p>

                <?php if ($usuario_logado): ?>
                    <div class="buttons">
                        <a href="<?= $base_path ?>consultas.php" class="btn primary">Agendar Exame</a>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <p>Faça login para agendar seu exame</p>
                        <div class="buttons">
                            <a href="<?= $base_path ?>PHP/login.php" class="btn primary">Fazer Login</a>
                            <a href="<?= $base_path ?>PHP/registro.php" class="btn secondary">Criar Conta Grátis</a>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="https://wa.me/5518991418664?text=Olá!%20Gostaria%20de%20agendar%20um%20exame%20veterinário." 
                   class="btn whatsapp" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Fale pelo WhatsApp
                </a>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.exam-card, .stat-item, .timeline-item, .timeline-content, .timeline-image').forEach(el => {
                observer.observe(el);
            });

            document.querySelectorAll('.exam-image img, .timeline-image img').forEach(img => {
                img.style.filter = 'blur(8px)';
                img.style.transition = 'filter 0.4s ease';
                const loaded = () => img.style.filter = 'blur(0)';
                if (img.complete) loaded();
                else img.addEventListener('load', loaded);
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>