<?php
session_start();
$base_path = "/Bruno/PetCare/";
include "conexao.php";

// Permitir acesso público - não redirecionar
$usuario_logado = isset($_SESSION['id']);
$usuario_id = $_SESSION['id'] ?? null;
$tipo = $_SESSION['tipo_usuario'] ?? null;


?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../Estilos/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>Vacinação - PetCare</title>
  <?php include '../PHP/header.php'; ?>
  <style>
    :root {
      --primary: #2E8B57;
      --secondary: #1976D2;
      --dark: #2C3E50;
      --gray: #7F8C8D;
      --light: #F8F9FA;
      --success: #E8F5E9;
    }

    .service-detail {
      padding: 80px 5%;
      background: var(--light);
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
      height: 400px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.4s ease, box-shadow 0.4s ease;
    }

    .service-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: filter 0.4s ease;
    }

    .service-image:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .service-image:hover img {
      filter: brightness(1.08) contrast(1.05);
    }

    .service-info {
      background: white;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .full-width-section {
      max-width: 1200px;
      margin: 3rem auto;
      background: white;
      padding: 3rem 2rem;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
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
      background: var(--success);
      border-radius: 12px;
      transition: all 0.4s ease;
      opacity: 0;
      transform: translateY(30px);
    }

    .benefit-card.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .benefit-card:hover {
      background: var(--primary);
      transform: translateY(-8px);
      box-shadow: 0 10px 25px rgba(46, 139, 87, 0.25);
    }

    .benefit-card:hover .benefit-icon,
    .benefit-card:hover h4,
    .benefit-card:hover p,
    .benefit-card:hover .highlight {
      color: white !important;
    }

    .benefit-icon {
      font-size: 3rem;
      color: var(--primary);
      margin-bottom: 1rem;
      transition: color 0.3s ease;
    }

    .benefit-card h4 {
      color: var(--dark);
      margin-bottom: 0.8rem;
      font-size: 1.2rem;
    }

    .benefit-card p {
      color: var(--gray);
      font-size: 0.9rem;
      line-height: 1.5;
    }

    .benefit-card .highlight {
      display: block;
      margin-top: 0.8rem;
      font-weight: 600;
      color: var(--primary);
      font-size: 0.85rem;
    }

    .vacina-timeline {
      margin-top: 3rem;
      position: relative;
      padding: 2rem 0;
    }

    .timeline-item {
      display: grid;
      grid-template-columns: 1fr 60px 1fr;
      gap: 2rem;
      margin-bottom: 4rem;
      align-items: center;
    }

    .timeline-item:nth-child(even) {
      direction: rtl;
    }

    .timeline-item:nth-child(even)>* {
      direction: ltr;
    }

    .timeline-content,
    .timeline-image {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.6s ease;
    }

    .timeline-content.visible,
    .timeline-image.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .timeline-content {
      background: var(--light);
      padding: 1.8rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .timeline-content:hover {
      background: #E3F2FD;
      transform: translateX(5px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
    }

    .timeline-content h3 {
      color: var(--primary);
      margin-bottom: 0.8rem;
      font-size: 1.3rem;
    }

    .timeline-content p {
      color: var(--gray);
      line-height: 1.6;
    }

    .timeline-dot {
      width: 50px;
      height: 50px;
      background: var(--primary);
      border-radius: 50%;
      position: relative;
      z-index: 2;
      box-shadow: 0 0 0 10px rgba(46, 139, 87, 0.2);
      margin: 0 auto;
    }

    .timeline-dot::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
    }

    .timeline-image {
      width: 100%;
      height: 280px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.4s ease, box-shadow 0.4s ease;
    }

    .timeline-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      filter: blur(8px);
      transition: filter 0.4s ease;
    }

    .timeline-image.visible img {
      filter: blur(0);
    }

    .timeline-image:hover {
      transform: scale(1.03);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .img-caption {
      background: var(--dark);
      color: white;
      padding: 0.6rem;
      text-align: center;
      font-size: 0.85rem;
      margin: 0;
      border-radius: 0 0 12px 12px;
    }

    .service-info h1 {
      color: var(--dark);
      margin-bottom: 1rem;
      font-size: 2.5rem;
    }

    .service-info h2 {
      color: var(--primary);
      margin: 2rem 0 1rem;
      font-size: 1.8rem;
    }

    .service-info h3 {
      color: var(--dark);
      margin: 1.5rem 0 0.8rem;
      font-size: 1.3rem;
    }

    .service-info p {
      color: var(--gray);
      line-height: 1.8;
      margin-bottom: 1rem;
    }

    .service-info ul {
      margin: 1rem 0;
      padding-left: 1.5rem;
    }

    .service-info li {
      margin: 0.8rem 0;
      color: var(--gray);
      line-height: 1.6;
    }

    .btn {
      display: inline-block;
      padding: 12px 30px;
      margin: 10px 10px 10px 0;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn.primary {
      background: var(--primary);
      color: white;
    }

    .btn.primary:hover {
      background: var(--secondary);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(25, 118, 210, 0.3);
    }

    .btn.secondary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn.secondary:hover {
      background: var(--primary);
      color: white;
    }

    .login-prompt {
      background: #E3F2FD;
      padding: 2rem;
      border-radius: 12px;
      margin: 2rem 0;
      text-align: center;
      border-left: 5px solid var(--primary);
    }

    .login-prompt p {
      color: var(--dark);
      font-size: 1.1rem;
      margin-bottom: 1.5rem;
    }

    .contact-info {
      background: var(--light);
      padding: 1.5rem;
      border-radius: 12px;
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
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .btn-whatsapp:hover {
      background: #1DA851;
      transform: scale(1.03);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .btn-whatsapp i {
      font-size: 1.2rem;
    }

    .info-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .info-card {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s ease;
    }

    .info-card:hover {
      transform: translateY(-5px);
    }

    .info-card i {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .info-card h4 {
      color: var(--dark);
      margin-bottom: 0.5rem;
      font-size: 1.1rem;
    }

    .info-card p {
      color: var(--gray);
      font-size: 0.9rem;
      margin: 0;
    }

    .highlight-card {
      background: linear-gradient(135deg, var(--primary), #236d45);
      color: white;
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      margin-top: 1rem;
      box-shadow: 0 6px 16px rgba(46, 139, 87, 0.3);
    }

    .highlight-card i {
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }

    .highlight-card h4 {
      margin-bottom: 0.5rem;
      font-size: 1.2rem;
    }

    .highlight-card p {
      font-size: 1rem;
      margin: 0;
      font-weight: 500;
    }

    .highlight-card p:last-child {
      font-size: 0.85rem;
      margin-top: 0.5rem;
      opacity: 0.9;
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

      .benefits-grid {
        grid-template-columns: 1fr;
      }

      .timeline-item,
      .timeline-item:nth-child(even) {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .timeline-dot {
        display: none;
      }

      .timeline-item:nth-child(even) .timeline-content,
      .timeline-item:nth-child(even) .timeline-image {
        order: 0;
      }

      .service-image,
      .timeline-image {
        height: 280px;
      }

      .info-cards {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {

      .service-image,
      .timeline-image {
        height: 220px;
      }
    }
  </style>
</head>

<body>
  <main>
    <section class="service-detail">
      <div class="service-content">
        <div>
          <div class="service-image">
            <img src="https://i.pinimg.com/736x/d4/87/60/d487603c9eb2b66db2e4271255a7d403.jpg"
              alt="Vacinação veterinária" loading="lazy">
          </div>

          <!-- Cards informativos abaixo da imagem -->
          <div class="info-cards">
            <div class="info-card">
              <i class="fas fa-clock"></i>
              <h4>Horário</h4>
              <p>Seg - Sex: 8h - 19h<br>Sábados: 8h - 16h</p>
            </div>

            <div class="info-card">
              <i class="fas fa-shield-alt"></i>
              <h4>Proteção</h4>
              <p>Vacinas certificadas<br>e aprovadas</p>
            </div>
          </div>

          <!-- Card de destaque -->
          <div class="highlight-card">
            <i class="fas fa-gift"></i>
            <h4>Pacote Completo</h4>
            <p>Desconto Especial!</p>
            <p>Protocolo completo de vacinação com preço promocional</p>
          </div>
        </div>

        <div class="service-info">
          <h1>💉 Vacinação Veterinária</h1>

          <p>Protocolos personalizados para cães e gatos, com atendimento clínico especializado
            para garantir saúde, proteção e bem-estar ao seu melhor amigo em todas as fases da vida.</p>

          <h2>Protocolo de Vacinação</h2>

          <h3>Para Cães</h3>
          <ul>
            <li>🐕 <strong>V8/V10:</strong> Cinomose, parvovirose, hepatite, parainfluenza, leptospirose</li>
            <li>💉 <strong>Antirrábica:</strong> Proteção obrigatória contra raiva (a partir de 12 semanas)</li>
            <li>🌬️ <strong>Gripe Canina:</strong> Recomendada para pets que frequentam creches e parques</li>
            <li>🦠 <strong>Giardíase:</strong> Proteção adicional contra parasitas intestinais</li>
          </ul>

          <h3>Para Gatos</h3>
          <ul>
            <li>🐱 <strong>V3/V4/V5:</strong> Rinotraqueíte, calicivirose, panleucopenia, clamidiose</li>
            <li>💉 <strong>Antirrábica:</strong> Proteção obrigatória contra raiva</li>
            <li>🔬 <strong>FeLV:</strong> Leucemia felina (recomendada para gatos com acesso à rua)</li>
          </ul>

          <h3>Calendário de Vacinação</h3>
          <ul>
            <li>📅 <strong>1ª Dose:</strong> 45-60 dias de vida</li>
            <li>📅 <strong>2ª Dose:</strong> 21-30 dias após a primeira</li>
            <li>📅 <strong>3ª Dose:</strong> 21-30 dias após a segunda</li>
            <li>📅 <strong>Reforços:</strong> Anuais para manter a proteção</li>
          </ul>

          <?php if ($usuario_logado): ?>
            <div style="margin-top: 2rem;">
              <a href="<?php echo $base_path; ?>consultas.php" class="btn primary">
                📅 Agendar Vacinação
              </a>
            </div>
          <?php else: ?>
            <div class="login-prompt">
              <p><strong>💡 Faça login para agendar a vacinação do seu pet</strong></p>
              <a href="<?php echo $base_path; ?>PHP/login.php" class="btn primary">
                🔑 Fazer Login
              </a>
              <a href="<?php echo $base_path; ?>PHP/registro.php" class="btn secondary">
                ✨ Criar Conta Grátis
              </a>
            </div>
          <?php endif; ?>

          <div class="contact-info">
            <h3>Dúvidas sobre vacinação?</h3>
            <p>Fale conosco pelo WhatsApp!</p>
            <a href="https://wa.me/5518996931805?text=Ol%C3%A1%2C%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es%20sobre%20vacina%C3%A7%C3%A3o%20veterin%C3%A1ria."
              class="btn-whatsapp" target="_blank" rel="noopener noreferrer">
              <i class="fab fa-whatsapp"></i> Falar no WhatsApp
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Seção de Benefícios -->
    <section class="full-width-section" style="background: var(--light);">
      <h2 style="text-align: center; color: var(--dark); font-size: 2.5rem; margin-bottom: 1rem;">
        Por que escolher a PetCare?
      </h2>
      <p style="text-align: center; color: var(--gray); font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
        Combinamos expertise veterinária com paixão por animais, oferecendo cuidados que vão além da vacinação
      </p>

      <div class="benefits-grid">
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-user-md"></i>
          </div>
          <h4>Avaliação Veterinária de Excelência</h4>
          <p>Antes de qualquer vacina, realizamos exames completos para garantir que seu pet está pronto para a
            imunização</p>
          <span class="highlight">Check-up detalhado em cada visita</span>
        </div>

        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-syringe"></i>
          </div>
          <h4>Vacinas Premium</h4>
          <p>Usamos vacinas de marcas globais, armazenadas em condições ideais para máxima eficácia</p>
          <span class="highlight">Qualidade comprovada, sempre</span>
        </div>

        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <h4>Imunização Contínua</h4>
          <p>Protocolos seguem diretrizes internacionais, com reforços anuais para manter seu pet protegido</p>
          <span class="highlight">Lembretes automáticos para reforços</span>
        </div>

        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-home"></i>
          </div>
          <h4>Ambiente Acolhedor</h4>
          <p>Clínica projetada para o conforto do seu pet, com equipe experiente e atendimento humanizado</p>
          <span class="highlight">Seu pet se sente em casa</span>
        </div>
      </div>
    </section>

    <!-- Jornada de Vacinação -->
    <section class="full-width-section">
      <h2 style="text-align: center; color: var(--dark); font-size: 2.5rem; margin-bottom: 1rem;">
        Jornada de Proteção do Seu Pet
      </h2>
      <p style="text-align: center; color: var(--gray); font-size: 1.1rem; max-width: 700px; margin: 0 auto 3rem;">
        Acompanhe a jornada de vacinação do seu pet, desde os primeiros dias até a proteção contínua na vida adulta
      </p>

      <div class="vacina-timeline">
        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Avaliação Inicial (30–45 dias)</h3>
            <p>Uma consulta inicial detalhada avalia a saúde do seu pet, preparando-o para o início do protocolo de
              vacinação.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/736x/d4/87/60/d487603c9eb2b66db2e4271255a7d403.jpg" alt="Consulta inicial"
              loading="lazy">
            <p class="img-caption">Consulta inicial com um filhote</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Primeiros Dias (45–60 dias)</h3>
            <p>Iniciamos com vacinas V8/V10 (cães), contra cinomose e parvovirose, e V3–V5 (gatos), contra rinotraqueíte
              e calicivirose.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/736x/85/d5/44/85d5440fa384d38db4aa23158cdeae63.jpg" alt="Primeira vacina"
              loading="lazy">
            <p class="img-caption">Primeira vacina para filhotes</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Reforços Iniciais (3–4 semanas)</h3>
            <p>Reforços regulares a cada 3–4 semanas até 16 semanas garantem imunidade robusta e proteção duradoura.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/736x/ec/9d/d5/ec9dd55a1f5676757da765d9b2d01679.jpg" alt="Reforços"
              loading="lazy">
            <p class="img-caption">Consulta para reforços iniciais</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Antirrábica (12 semanas)</h3>
            <p>A vacina antirrábica é aplicada a partir de 12 semanas, essencial para a segurança do pet e da
              comunidade.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/1200x/f6/fc/2d/f6fc2dec181e6b4c183952d15b1620ec.jpg" alt="Antirrábica"
              loading="lazy">
            <p class="img-caption">Proteção contra raiva</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Manutenção Anual</h3>
            <p>Reforços anuais e vacinas opcionais, como gripe canina para cães ou FeLV para gatos, ajustados ao estilo
              de vida do seu pet.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/736x/29/60/22/29602249f0f4e15fa1b692a917b19128.jpg" alt="Manutenção"
              loading="lazy">
            <p class="img-caption">Manutenção para pets adultos</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Monitoramento Contínuo</h3>
            <p>Consultas regulares garantem que o plano de vacinação permanece atualizado, adaptado às necessidades do
              seu pet.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/1200x/6e/e2/34/6ee23404e8415eac4ba4a24a8ae0b468.jpg" alt="Monitoramento"
              loading="lazy">
            <p class="img-caption">Acompanhamento contínuo</p>
          </div>
        </div>

        <div class="timeline-item">
          <div class="timeline-content">
            <h3>Cuidados Personalizados</h3>
            <p>Adaptamos o plano ao estilo de vida do seu pet, como vacinas para gripe canina para quem frequenta
              creches ou FeLV para gatos.</p>
          </div>
          <div class="timeline-dot"></div>
          <div class="timeline-image">
            <img src="https://i.pinimg.com/736x/ad/20/26/ad2026bf8333049f6167a3767bcc8293.jpg" alt="Personalização"
              loading="lazy">
            <p class="img-caption">Saúde e felicidade garantidas</p>
          </div>
        </div>
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

      // Animações de entrada
      document.querySelectorAll('.benefit-card, .timeline-content, .timeline-image').forEach(el => {
        observer.observe(el);
      });

      // Lazy load com blur
      document.querySelectorAll('.timeline-image img, .service-image img').forEach(img => {
        img.style.filter = 'blur(8px)';
        img.style.transition = 'filter 0.4s ease';

        const loaded = () => {
          img.style.filter = 'blur(0)';
        };

        if (img.complete) {
          loaded();
        } else {
          img.addEventListener('load', loaded);
        }
      });

      // Smooth scroll
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
          e.preventDefault();
          const target = document.querySelector(anchor.getAttribute('href'));
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });
      });
    });
  </script>

  <?php include 'footer.php'; ?>
</body>

</html>