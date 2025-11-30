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
    <title>Castração Veterinária - PetCare</title>
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

        .process-timeline {
            margin-top: 2rem;
            position: relative;
        }

        .timeline-item {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .timeline-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2E8B57 0%, #1F5F3F 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(46, 139, 87, 0.3);
        }

        .timeline-content {
            flex: 1;
            background: #F8F9FA;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #2E8B57;
        }

        .timeline-content h4 {
            color: #2C3E50;
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .timeline-content p {
            color: #7F8C8D;
            line-height: 1.6;
            margin: 0;
        }

        .age-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .age-card {
            background: linear-gradient(135deg, #F8F9FA 0%, #E8F5E9 100%);
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #2E8B57;
            transition: all 0.3s ease;
        }

        .age-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .age-card-icon {
            font-size: 3rem;
            color: #2E8B57;
            margin-bottom: 1rem;
        }

        .age-card h4 {
            color: #2C3E50;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .age-card p {
            color: #7F8C8D;
            line-height: 1.6;
            margin: 0;
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

        .warning-box {
            background: #FFF3CD;
            border-left: 4px solid #FFC107;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }

        .warning-box h4 {
            color: #856404;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box p {
            color: #856404;
            margin: 0;
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

            .benefits-grid,
            .age-cards {
                grid-template-columns: 1fr;
            }

            .timeline-item {
                flex-direction: column;
                gap: 1rem;
            }
        }

        .faq-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .faq-item {
            background: white;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .faq-question {
            background: #F8F9FA;
            padding: 1.5rem 2rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #2E8B57;
            transition: all 0.3s ease;
            user-select: none;
        }

        .faq-question:hover {
            background: #E8F5E9;
        }

        .faq-question.active {
            background: #E8F5E9;
            border-left-color: #1F5F3F;
        }

        .faq-question h4 {
            color: #2C3E50;
            font-size: 1.2rem;
            margin: 0;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .faq-question h4 i {
            color: #2E8B57;
            font-size: 1.3rem;
        }

        .faq-toggle {
            width: 35px;
            height: 35px;
            background: #2E8B57;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .faq-question.active .faq-toggle {
            background: #1F5F3F;
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
            padding: 0 2rem;
        }

        .faq-answer.active {
            max-height: 500px;
            padding: 1.5rem 2rem;
        }

        .faq-answer p {
            color: #7F8C8D;
            line-height: 1.8;
            margin: 0;
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .faq-question {
                padding: 1.2rem 1.5rem;
            }

            .faq-question h4 {
                font-size: 1rem;
            }

            .faq-question h4 i {
                font-size: 1.1rem;
            }

            .faq-toggle {
                width: 30px;
                height: 30px;
                font-size: 1rem;
            }

            .faq-answer {
                padding: 0 1.5rem;
            }

            .faq-answer.active {
                padding: 1.2rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <main>
        <section class="service-detail">
            <div class="service-content">
                <div>
                    <img src="https://i.pinimg.com/736x/58/47/7c/58477c70daa3b4b19411b5f4f06cb719.jpg"
                        alt="Castração veterinária"
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
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h4 style="color: #2C3E50; margin-bottom: 0.5rem; font-size: 1.1rem;">Cirurgiões</h4>
                            <p style="color: #7F8C8D; font-size: 0.9rem; margin: 0;">Equipe especializada<br>e certificada</p>
                        </div>
                    </div>

                    <div style="background: linear-gradient(135deg, #2E8B57 0%, #1F5F3F 100%); padding: 1.5rem; border-radius: 8px; margin-top: 1rem; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
                        <div style="font-size: 2rem; color: white; margin-bottom: 0.5rem;">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h4 style="color: white; margin-bottom: 0.5rem; font-size: 1.2rem;">Procedimento Seguro</h4>
                        <p style="color: white; font-size: 1rem; margin: 0; font-weight: 500;">Anestesia Moderna</p>
                        <p style="color: rgba(255,255,255,0.9); font-size: 0.85rem; margin-top: 0.5rem;">Recuperação rápida e acompanhamento completo</p>
                    </div>
                </div>

                <div class="service-info">
                    <h1>🏥 Castração Veterinária</h1>

                    <p>Procedimento cirúrgico seguro e humanizado para promover a saúde, prevenir doenças
                        e contribuir para o controle populacional responsável de cães e gatos.</p>

                    <h2>Por que Castrar seu Pet?</h2>
                    <ul>
                        <li>✅ Previne câncer de mama, ovário e próstata</li>
                        <li>✅ Evita infecções uterinas graves (piometra)</li>
                        <li>✅ Reduz comportamentos de fuga e marcação</li>
                        <li>✅ Diminui agressividade territorial</li>
                        <li>✅ Aumenta a expectativa de vida</li>
                        <li>✅ Controla ninhadas indesejadas</li>
                    </ul>

                    <div class="warning-box">
                        <h4><i class="fas fa-exclamation-triangle"></i> Importante</h4>
                        <p>A castração é um procedimento definitivo. Converse com nossos veterinários
                            para entender todos os aspectos e tomar a melhor decisão para seu pet.</p>
                    </div>

                    <h3>Idade Recomendada</h3>
                    <p>Geralmente a partir dos <strong>6 meses de idade</strong>, mas pode variar conforme
                        a raça, porte e condição de saúde. Consulte nossos especialistas para uma avaliação personalizada.</p>

                    <?php if ($usuario_logado): ?>
                        <div style="margin-top: 2rem;">
                            <a href="<?php echo $base_path; ?>consultas.php" class="btn primary">
                                📅 Agendar Consulta Pré-Cirúrgica
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p><strong>💡 Faça login para agendar uma consulta de avaliação</strong></p>
                            <a href="<?php echo $base_path; ?>PHP/login.php" class="btn primary">
                                🔑 Fazer Login
                            </a>
                            <a href="<?php echo $base_path; ?>PHP/registro.php" class="btn secondary">
                                ✨ Criar Conta Grátis
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="contact-info">
                        <h3>Dúvidas sobre o procedimento?</h3>
                        <p>Converse com nossos veterinários!</p>
                        <a href="https://wa.me/5518996931805?text=Ol%C3%A1%2C%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es%20sobre%20castra%C3%A7%C3%A3o."
                            class="btn-whatsapp" target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp"></i> Falar no WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="full-width-section" style="background: #F8F9FA;">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Benefícios para a Saúde do seu Pet
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                A castração traz inúmeras vantagens para a saúde e qualidade de vida do seu animal
            </p>

            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Prevenção de Doenças</h4>
                    <p>Reduz drasticamente o risco de tumores mamários, câncer de próstata e infecções uterinas</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart-pulse"></i>
                    </div>
                    <h4>Maior Longevidade</h4>
                    <p>Pets castrados vivem em média 20-30% mais tempo devido à prevenção de doenças graves</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h4>Melhor Comportamento</h4>
                    <p>Redução de agressividade, marcação territorial e comportamentos de fuga</p>
                </div>

                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h4>Controle Populacional</h4>
                    <p>Evita ninhadas indesejadas e contribui para reduzir o abandono de animais</p>
                </div>
            </div>
        </section>

        <section class="full-width-section">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Como Funciona o Procedimento
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Passo a passo do processo cirúrgico seguro e humanizado
            </p>

            <div class="process-timeline">
                <div class="timeline-item">
                    <div class="timeline-number">1</div>
                    <div class="timeline-content">
                        <h4>Consulta Pré-Cirúrgica</h4>
                        <p>Avaliação completa do estado de saúde do pet, exames laboratoriais e orientações sobre jejum e preparação para a cirurgia.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-number">2</div>
                    <div class="timeline-content">
                        <h4>Anestesia e Monitoramento</h4>
                        <p>Aplicação de anestesia geral moderna e segura, com monitoramento contínuo dos sinais vitais durante todo o procedimento.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-number">3</div>
                    <div class="timeline-content">
                        <h4>Cirurgia</h4>
                        <p>Procedimento rápido (30-60 minutos) realizado por cirurgiões experientes, com técnicas minimamente invasivas e sutura interna.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-number">4</div>
                    <div class="timeline-content">
                        <h4>Recuperação Pós-Operatória</h4>
                        <p>Período de observação em nossa clínica até a recuperação da anestesia, com liberação no mesmo dia e orientações detalhadas.</p>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-number">5</div>
                    <div class="timeline-content">
                        <h4>Cuidados em Casa</h4>
                        <p>Repouso por 7-10 dias, uso de colar elizabetano, medicação prescrita e retorno para remoção de pontos (se necessário).</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="full-width-section" style="background: #F8F9FA;">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Idade Ideal para Castração
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Conheça o momento ideal para cada tipo de pet
            </p>

            <div class="age-cards">
                <div class="age-card">
                    <div class="age-card-icon">
                        <i class="fas fa-dog"></i>
                    </div>
                    <h4>Cães</h4>
                    <p><strong>6 a 12 meses:</strong> Raças pequenas e médias</p>
                    <p style="margin-top: 0.5rem;"><strong>12 a 18 meses:</strong> Raças grandes e gigantes</p>
                    <p style="margin-top: 1rem; font-size: 0.85rem; color: #2E8B57;">
                        <i class="fas fa-info-circle"></i> Consulte sobre a raça específica
                    </p>
                </div>

                <div class="age-card">
                    <div class="age-card-icon">
                        <i class="fas fa-cat"></i>
                    </div>
                    <h4>Gatos</h4>
                    <p><strong>6 a 8 meses:</strong> Idade ideal para machos e fêmeas</p>
                    <p style="margin-top: 0.5rem;"><strong>Antes do 1º cio:</strong> Maior proteção contra tumores</p>
                    <p style="margin-top: 1rem; font-size: 0.85rem; color: #2E8B57;">
                        <i class="fas fa-info-circle"></i> Pode ser feita até na idade adulta
                    </p>
                </div>

                <div class="age-card">
                    <div class="age-card-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <h4>Casos Especiais</h4>
                    <p><strong>Animais adultos:</strong> Podem ser castrados após avaliação</p>
                    <p style="margin-top: 0.5rem;"><strong>Condições de saúde:</strong> Requerem análise individualizada</p>
                    <p style="margin-top: 1rem; font-size: 0.85rem; color: #2E8B57;">
                        <i class="fas fa-info-circle"></i> Agende uma consulta de avaliação
                    </p>
                </div>
            </div>
        </section>

        <section class="full-width-section">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Cuidados Pós-Castração
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Orientações essenciais para uma recuperação tranquila
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div style="background: #F8F9FA; padding: 2rem; border-radius: 8px; border-left: 4px solid #2E8B57;">
                    <h4 style="color: #2C3E50; margin-bottom: 1rem; font-size: 1.2rem;">
                        <i class="fas fa-bed" style="color: #2E8B57; margin-right: 0.5rem;"></i>
                        Repouso Adequado
                    </h4>
                    <ul style="color: #7F8C8D; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                        <li>Mantenha o pet em local calmo e tranquilo</li>
                        <li>Evite escadas e móveis altos por 7-10 dias</li>
                        <li>Limite exercícios físicos e brincadeiras</li>
                        <li>Não deixe lamber a ferida</li>
                    </ul>
                </div>

                <div style="background: #F8F9FA; padding: 2rem; border-radius: 8px; border-left: 4px solid #2E8B57;">
                    <h4 style="color: #2C3E50; margin-bottom: 1rem; font-size: 1.2rem;">
                        <i class="fas fa-pills" style="color: #2E8B57; margin-right: 0.5rem;"></i>
                        Medicação
                    </h4>
                    <ul style="color: #7F8C8D; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                        <li>Administre antibióticos conforme prescrição</li>
                        <li>Use analgésicos nas doses recomendadas</li>
                        <li>Não interrompa o tratamento antes do prazo</li>
                        <li>Retorne se houver efeitos adversos</li>
                    </ul>
                </div>

                <div style="background: #F8F9FA; padding: 2rem; border-radius: 8px; border-left: 4px solid #2E8B57;">
                    <h4 style="color: #2C3E50; margin-bottom: 1rem; font-size: 1.2rem;">
                        <i class="fas fa-eye" style="color: #2E8B57; margin-right: 0.5rem;"></i>
                        Monitoramento
                    </h4>
                    <ul style="color: #7F8C8D; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                        <li>Observe a ferida diariamente</li>
                        <li>Atenção para vermelhidão ou inchaço excessivo</li>
                        <li>Verifique a temperatura corporal</li>
                        <li>Entre em contato se notar algo anormal</li>
                    </ul>
                </div>

                <div style="background: #F8F9FA; padding: 2rem; border-radius: 8px; border-left: 4px solid #2E8B57;">
                    <h4 style="color: #2C3E50; margin-bottom: 1rem; font-size: 1.2rem;">
                        <i class="fas fa-utensils" style="color: #2E8B57; margin-right: 0.5rem;"></i>
                        Alimentação
                    </h4>
                    <ul style="color: #7F8C8D; line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                        <li>Ofereça água fresca à vontade</li>
                        <li>Reintroduza alimentação gradualmente</li>
                        <li>Porções menores e mais frequentes nos primeiros dias</li>
                        <li>Evite alimentos pesados nas primeiras 24h</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="full-width-section">
            <h2 style="text-align: center; color: #2C3E50; font-size: 2.5rem; margin-bottom: 1rem;">
                Perguntas Frequentes
            </h2>
            <p style="text-align: center; color: #7F8C8D; font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
                Tire suas dúvidas sobre a castração
            </p>

            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-syringe"></i>
                            A castração dói?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            Não. O procedimento é realizado sob anestesia geral, então o pet não sente dor durante a cirurgia.
                            Após o procedimento, são prescritos analgésicos para controlar qualquer desconforto durante a recuperação.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-weight-scale"></i>
                            Meu pet vai engordar após a castração?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            A castração pode reduzir o metabolismo, mas com dieta adequada e exercícios regulares, é perfeitamente
                            possível manter o peso ideal. Nossos veterinários podem orientar sobre alimentação pós-castração.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-clock"></i>
                            Quanto tempo dura a recuperação?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            A recuperação completa leva cerca de 7 a 10 dias. O pet pode retornar às atividades normais
                            gradualmente após esse período, sempre respeitando as orientações veterinárias.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-heart"></i>
                            A personalidade do meu pet vai mudar?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            A personalidade básica não muda. O que pode ocorrer é a redução de comportamentos relacionados
                            a hormônios, como agressividade territorial, marcação e tentativas de fuga. Seu pet continuará
                            sendo carinhoso e brincalhão.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-flask"></i>
                            É necessário fazer exames antes da cirurgia?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            Sim, são recomendados exames pré-operatórios como hemograma e avaliação cardíaca, especialmente
                            para pets idosos ou com histórico de doenças. Isso garante maior segurança durante a anestesia.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>
                            <i class="fas fa-calendar-check"></i>
                            Qual a melhor idade para castrar?
                        </h4>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>
                            Para a maioria dos pets, a idade ideal é entre 6 e 12 meses. Cães de raças grandes podem
                            beneficiar-se de esperar até 12-18 meses. Gatos podem ser castrados a partir de 6 meses.
                            Consulte nossos veterinários para uma recomendação personalizada.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', function() {
                    const faqItem = this.parentElement;
                    const answer = this.nextElementSibling;
                    
                    this.classList.toggle('active');
                    answer.classList.toggle('active');
                    
                    faqQuestions.forEach(otherQuestion => {
                        if (otherQuestion !== this) {
                            otherQuestion.classList.remove('active');
                            otherQuestion.nextElementSibling.classList.remove('active');
                        }
                    });
                });
            });
        });
    </script>


    <?php include 'footer.php'; ?>
</body>

</html>