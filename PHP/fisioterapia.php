<?php
session_start();
$base_path = "/Bruno/PetCare/";
include "conexao.php";

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
    <link rel="stylesheet" href="../Estilos/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Fisioterapia - PetCare</title>
    <style>
        .service-detail {
            padding: 80px 5%;
            background: #F8F9FA;
        }
        .service-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
            max-width: 1200px;
            margin: 0 auto;
        }
        .service-image {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .service-info {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .full-width-section {
            max-width: 1200px;
            margin: 3rem auto;
            background: white;
            padding: 3rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .treatments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .treatment-card {
            background: #F8F9FA;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .treatment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        .treatment-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .treatment-card-content {
            padding: 1.5rem;
        }
        .treatment-card h4 {
            color: #2E8B57;
            margin-bottom: 0.8rem;
            font-size: 1.3rem;
        }
        .treatment-card p {
            color: #7F8C8D;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .benefit-card {
            text-align: center;
            padding: 2rem 1.5rem;
            background: #E8F5E9;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .benefit-card:hover {
            background: #2E8B57;
            transform: translateY(-5px);
        }
        .benefit-card:hover .benefit-icon {
            color: white;
        }
        .benefit-card:hover h4,
        .benefit-card:hover p {
            color: white;
        }
        .benefit-icon {
            font-size: 3rem;
            color: #2E8B57;
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }
        .benefit-card h4 {
            color: #2C3E50;
            margin-bottom: 0.8rem;
            font-size: 1.2rem;
        }
        .benefit-card p {
            color: #7F8C8D;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 2rem 0;
        }
        .gallery-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .gallery-image:hover {
            transform: scale(1.05);
        }
        .service-info h1 {
            color: #2C3E50;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        .service-info h2 {
            color: #2E8B57;
            margin: 2rem 0 1rem;
            font-size: 1.8rem;
        }
        .service-info h3 {
            color: #2C3E50;
            margin: 1.5rem 0 0.8rem;
            font-size: 1.3rem;
        }
        .service-info p {
            color: #7F8C8D;
            line-height: 1.8;
            margin-bottom: 1rem;
        }
        .service-info ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }
        .service-info li {
            margin: 0.8rem 0;
            color: #7F8C8D;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px 10px 10px 0;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn.primary {
            background: #2E8B57;
            color: white;
        }
        .btn.primary:hover {
            background: #1F5F3F;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn.secondary {
            background: white;
            color: #2E8B57;
            border: 2px solid #2E8B57;
        }
        .btn.secondary:hover {
            background: #2E8B57;
            color: white;
        }
        .login-prompt {
            background: #E8F5E9;
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: center;
            border-left: 4px solid #2E8B57;
        }
        .login-prompt p {
            color: #2C3E50;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .contact-info {
            background: #F8F9FA;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            text-align: center;
        }
        .btn-whatsapp {
            background: #25D366;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-whatsapp:hover {
            background: #1DA851;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-whatsapp i {
            font-size: 1.2rem;
        }
        @media (max-width: 768px) {
            .service-content {
                grid-template-columns: 1fr;
            }
            .service-info h1 {
                font-size: 2rem;
            }
            .service-info h2 {
                font-size: 1.5rem;
            }
            .treatments-grid,
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            .image-gallery {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main>
        <section class="service-detail">
            <div class="service-content">
                <div>
                    <img src="https://i.pinimg.com/1200x/0a/02/60/0a0260047850371bcf5c37cedb97e4c1.jpg" 
                         alt="Fisioterapia veterinária" 
                         class="service-image">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem;">
                        <div style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                            <div style="font-size: 2.5rem; color: #2E8B57; margin-bottom: 0.5rem;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 style="color: #2C3E50; margin-bottom: 0.5rem; font-size: 1.1rem;">Horário</h4>
                            <p style="color: #7F8C8D; font-size: 0.9rem; margin: 0;">Seg - Sex: 8h - 18h<br>Sábados: 8h - 12h</p>
                        </div>
                        
                        <div style="background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                            <div style="font-size: 2.5rem; color: #2E8B57; margin-bottom: 0.5rem;">
                                <i class="fas fa-award"></i>
                            </div>
                            <h4 style="color: #2C3E50; margin-bottom: 0.5rem; font-size: 1.1rem;">Experiência</h4>
                            <p style="color: #7F8C8D; font-size: 0.9rem; margin: 0;">+10 anos cuidando<br>dos seus pets</p>
                        </div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #2E8B57 0%, #1F5F3F 100%); padding: 1.5rem; border-radius: 8px; margin-top: 1rem; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 2rem; color: white; margin-bottom: 0.5rem;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 style="color: white; margin-bottom: 0.5rem; font-size: 1.2rem;">Primeira Sessão</h4>
                        <p style="color: white; font-size: 1rem; margin: 0; font-weight: 500;">Avaliação Gratuita!</p>
                        <p style="color: rgba(255,255,255,0.9); font-size: 0.85rem; margin-top: 0.5rem;">Conheça nossos tratamentos sem compromisso</p>
                    </div>
                </div>
                
                <div class="service-info">
                    <h1>🦴 Fisioterapia Veterinária</h1>
                    
                    <p>Nossa fisioterapia veterinária oferece tratamentos especializados para reabilitação 
                       e melhoria da mobilidade do seu pet, proporcionando qualidade de vida e recuperação 
                       mais rápida após cirurgias ou lesões.</p>
                    
                    <h2>Benefícios da Fisioterapia</h2>
                    <ul>
                        <li>✅ Recuperação pós-cirúrgica acelerada</li>
                        <li>✅ Alívio de dores articulares e musculares</li>
                        <li>✅ Melhora significativa da mobilidade</li>
                        <li>✅ Fortalecimento muscular progressivo</li>
                        <li>✅ Reabilitação neurológica especializada</li>
                        <li>✅ Aumento da qualidade de vida do pet</li>
                    </ul>
                    
                    <h3>Tratamentos Oferecidos</h3>
                    <ul>
                        <li>🏊 <strong>Hidroterapia:</strong> Exercícios aquáticos de baixo impacto</li>
                        <li>💡 <strong>Laserterapia:</strong> Tratamento com laser terapêutico</li>
                        <li>⚡ <strong>Eletroterapia:</strong> Estimulação elétrica muscular</li>
                        <li>💆 <strong>Massagem Terapêutica:</strong> Relaxamento e recuperação</li>
                        <li>🏃 <strong>Exercícios de Mobilidade:</strong> Programa personalizado</li>
                    </ul>
                    
                    <?php if ($usuario_logado): ?>
                        <div style="margin-top: 2rem;">
                            <a href="<?php echo $base_path; ?>consultas.php" class="btn primary">
                                📅 Agendar Sessão de Fisioterapia
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p><strong>💡 Faça login para agendar uma sessão de fisioterapia</strong></p>
                            <a href="<?php echo $base_path; ?>PHP/login.php" class="btn primary">
                                🔑 Fazer Login
                            </a>
                            <a href="<?php echo $base_path; ?>PHP/registro.php" class="btn secondary">
                                ✨ Criar Conta Grátis
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="contact-info">
                        <h3>Tem dúvidas sobre a fisioterapia?</h3>
                        <p>Fale conosco pelo WhatsApp!</p>
                        <a href="https://wa.me/5518996931805?text=Ol%C3%A1%2C%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es%20sobre%20fisioterapia%20veterinária."
                           class="btn-whatsapp" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp"></i> Falar no WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="full-width-section" style="background: #F8F9FA;">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Por que escolher nossa fisioterapia?
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Oferecemos tratamentos modernos e personalizados para garantir o melhor cuidado ao seu pet
            </p>
            
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h4>Profissionais Especializados</h4>
                    <p>Equipe certificada em fisioterapia veterinária com anos de experiência</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h4>Tratamento Humanizado</h4>
                    <p>Cuidado individualizado respeitando as necessidades de cada pet</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h4>Equipamentos Modernos</h4>
                    <p>Tecnologia de ponta para tratamentos mais eficazes</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Resultados Comprovados</h4>
                    <p>Protocolos testados com alta taxa de sucesso na recuperação</p>
                </div>
            </div>
        </section>
        
        <section class="full-width-section">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Nossos Tratamentos
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Conheça as técnicas que utilizamos para cuidar do seu pet
            </p>
            
            <div class="treatments-grid">
                <div class="treatment-card">
                    <img src="https://i.pinimg.com/736x/6a/be/c2/6abec2096acdffa68f340d4c9542457c.jpg" alt="Hidroterapia">
                    <div class="treatment-card-content">
                        <h4>🏊 Hidroterapia</h4>
                        <p>Exercícios aquáticos de baixo impacto, perfeitos para recuperação pós-cirúrgica 
                           e fortalecimento muscular sem sobrecarregar as articulações.</p>
                    </div>
                </div>
                
                <div class="treatment-card">
                    <img src="https://i.pinimg.com/1200x/d1/b9/d7/d1b9d767409fa4de85199a99a4e6e936.jpg" alt="Laserterapia">
                    <div class="treatment-card-content">
                        <h4>💡 Laserterapia</h4>
                        <p>Tratamento com laser de baixa potência para redução de dor e inflamação, 
                           acelerando o processo de cicatrização de tecidos.</p>
                    </div>
                </div>
                
                <div class="treatment-card">
                    <img src="https://i.pinimg.com/736x/35/40/ac/3540accb32d83a1230e45127f65f5d61.jpg" alt="Massagem Terapêutica">
                    <div class="treatment-card-content">
                        <h4>💆 Massagem Terapêutica</h4>
                        <p>Técnicas de massagem especializadas para aliviar tensões musculares, 
                           melhorar circulação e promover relaxamento.</p>
                    </div>
                </div>
                
                <div class="treatment-card">
                    <img src="https://i.pinimg.com/1200x/bc/d0/0a/bcd00ae81c01191faf1cad4498c78182.jpg" alt="Eletroterapia">
                    <div class="treatment-card-content">
                        <h4>⚡ Eletroterapia</h4>
                        <p>Estimulação elétrica funcional para fortalecimento muscular e recuperação 
                           de movimentos em casos neurológicos.</p>
                    </div>
                </div>
                
                <div class="treatment-card">
                    <img src="https://images.pexels.com/photos/7210754/pexels-photo-7210754.jpeg" alt="Exercícios Terapêuticos">
                    <div class="treatment-card-content">
                        <h4>🏃 Exercícios Terapêuticos</h4>
                        <p>Programa personalizado de exercícios para melhorar amplitude de movimento, 
                           equilíbrio e coordenação motora.</p>
                    </div>
                </div>
                
                <div class="treatment-card">
                    <img src="https://i.pinimg.com/736x/3b/77/41/3b77419d843886b1713db18243b66c97.jpg" alt="Crioterapia">
                    <div class="treatment-card-content">
                        <h4>❄️ Crioterapia</h4>
                        <p>Aplicação controlada de frio para reduzir inflamações agudas e controlar 
                           dor em lesões recentes.</p>
                    </div>
                </div>
            </div>
        </section>
        
      
    <?php include 'footer.php'; ?>
</body>
</html>