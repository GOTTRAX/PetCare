<?php
session_start();
$base_path = "/Bruno/PetCare/"; // Caminho absoluto relativo à raiz do servidor
include "PHP/conexao.php";

$usuario_id = $_SESSION['id'] ?? null;
$tipo = $_SESSION['tipo_usuario'] ?? null;

include "PHP/header.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Estilos/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>PetCare - Home</title>
    <style>
        /* Variáveis para consistência com o tema */
        :root {
            --primary-color: #2E8B57;
            --primary-dark: #1F5F3F;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --background-light: #F8F9FA;
            --white: #FFFFFF;
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        /* Estilo para o botão do WhatsApp */
        .btn-whatsapp {
            background: #25D366;
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        .btn-whatsapp:hover {
            background: #1DA851;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .btn-whatsapp i {
            font-size: 1.2rem;
        }

        /* Seção Nossa Equipe */
        .team-container {
            padding: 80px 5%;
            background: var(--background-light);
            text-align: center;
        }
        .team-container h2 {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2rem;
        }
        .carousel-container {
            position: relative;
            max-width: 1400px;
            margin: 0 auto;
            overflow: hidden;
        }
        .carousel-track {
            display: flex;
            gap: 20px;
            transition: transform 0.5s ease;
            padding: 0 10px;
            overflow-x: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .carousel-track::-webkit-scrollbar {
            display: none; /* Esconder scrollbar para estética */
        }
        .team-card {
            flex: 0 0 350px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
        }
        .team-img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-bottom: 2px solid var(--primary-color);
        }
        .team-img-error {
            background: var(--text-light);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            height: 300px;
            margin: 0;
            padding: 1rem;
            text-align: center;
        }
        .team-body {
            padding: 1.5rem;
        }
        .team-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .team-subtitle {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }
        .team-text {
            font-size: 0.9rem;
            color: var(--text-light);
            line-height: 1.6;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: var(--white);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            z-index: 100; /* Aumentado para evitar sobreposição */
            pointer-events: auto;
        }
        .carousel-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.05);
        }
        .carousel-btn.prev {
            left: 20px;
        }
        .carousel-btn.next {
            right: 20px;
        }
        .carousel-btn:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            opacity: 0.5;
            pointer-events: none;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .team-container {
                padding: 60px 3%;
            }
            .team-card {
                flex: 0 0 300px;
            }
            .carousel-btn {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            .carousel-btn.prev {
                left: 10px;
            }
            .carousel-btn.next {
                right: 10px;
            }
            .btn-whatsapp {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 480px) {
            .team-container {
                padding: 40px 1rem;
            }
            .team-card {
                flex: 0 0 280px;
            }
            .carousel-btn {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <main>
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-text">
                <h1>Cuidado <span class="highlight">especializado</span> para seu melhor amigo</h1>
                <p>Oferecemos atendimento veterinário de qualidade com uma equipe de profissionais dedicados ao
                    bem-estar do seu pet.</p>
                <div class="buttons">
                    <a href="consultas.php" class="btn primary">Agendar Consulta</a>
                    <a href="https://wa.me/5518996931805?text=Ol%C3%A1%2C%20gostaria%20de%20mais%20informa%C3%A7%C3%B5es%20sobre%20os%20servi%C3%A7os."
                       class="btn btn-whatsapp" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp"></i> Fale no WhatsApp
                    </a>
                </div>
            </div>
            <div class="image-container">
                <img src="https://images.pexels.com/photos/6235225/pexels-photo-6235225.jpeg"
                    alt="Veterinário cuidando de um pet" loading="lazy">
            </div>
        </section>

        <section id="servicos" class="servicos" aria-labelledby="servicos-title">
            <div class="container">
                <span class="tag">Nossos Serviços</span>
                <h2 id="servicos-title">Serviços especializados para seu pet</h2>
                <p>Oferecemos uma ampla gama de serviços veterinários de alta qualidade para garantir a saúde e o
                    bem-estar do seu animal de estimação.</p>
                <div class="grid">
                    <a href="PHP/fisioterapia.php" class="card" aria-label="Fisioterapia veterinária">
                        <div class="icon">🦴</div>
                        <h3>Fisioterapia</h3>
                        <p>Tratamentos especializados para reabilitação e melhoria da mobilidade do seu pet.</p>
                    </a>
                    <a href="PHP/castracao.php" class="card" aria-label="Castração">
                        <div class="icon">✂️</div>
                        <h3>Castração</h3>
                        <p>Procedimento seguro para controle populacional e saúde do seu animal.</p>
                    </a>
                    <a href="PHP/banhoetosa.php" class="card" aria-label="Banho e tosa">
                        <div class="icon">🛁</div>
                        <h3>Banho & Tosa</h3>
                        <p>Serviços de estética completos realizados por profissionais capacitados.</p>
                    </a>
                    <a href="PHP/vacinas.php" class="card" aria-label="Vacinação">
                        <div class="icon">💉</div>
                        <h3>Vacinação</h3>
                        <p>Programa de imunização completo para prevenir doenças e proteger seu pet.</p>
                    </a>
                    <a href="PHP/exames.php" class="card" aria-label="Exames laboratoriais">
                        <div class="icon">🔬</div>
                        <h3>Exames</h3>
                        <p>Laboratório completo para diagnósticos precisos e rápidos.</p>
                    </a>
                    <a href="PHP/cirurgias.php" class="card" aria-label="Cirurgias veterinárias">
                        <div class="icon">📋</div>
                        <h3>Cirurgias</h3>
                        <p>Centro cirúrgico equipado para procedimentos simples e complexos.</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="team-container" aria-labelledby="team-title">
            <div class="container">
                <h2 id="team-title">Nossa Equipe</h2>
                <div class="carousel-container">
                    <button class="carousel-btn prev" aria-label="Previous team member">&#10094;</button>
                    <div class="carousel-track">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM equipe");
                            if ($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    // Validar a coluna 'foto'
                                    $foto = !empty($row['foto']) ? htmlspecialchars($row['foto']) : '';
                                    // Usar caminho absoluto relativo à raiz do servidor
                                    $caminhoImagem = $foto ? "/Bruno/PetCare/assets/uploads/equipe/" . $foto : '';
                                    $caminhoDefault = "/Bruno/PetCare/assets/uploads/equipe/default.jpg";
                                    $caminhoFallback = "https://via.placeholder.com/350x300?text=Sem+Imagem";

                                    // Verificar existência da imagem no sistema de arquivos
                                    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
                                    if ($foto && file_exists($documentRoot . $caminhoImagem)) {
                                        $imagemFinal = $caminhoImagem;
                                    } elseif (file_exists($documentRoot . $caminhoDefault)) {
                                        $imagemFinal = $caminhoDefault;
                                        error_log("Imagem não encontrada para {$row['nome']}: $foto, usando default.jpg");
                                    } else {
                                        $imagemFinal = $caminhoFallback;
                                        error_log("Imagem default não encontrada: $caminhoDefault, usando fallback externo");
                                    }

                                    echo "
                                    <div class='team-card'>
                                        <img src='$imagemFinal' class='team-img' alt='Foto de {$row['nome']}'>
                                        <div class='team-body'>
                                            <h3 class='team-title'>{$row['nome']}</h3>
                                            <p class='team-subtitle'>{$row['profissao']}</p>
                                            <p class='team-text'>{$row['descricao']}</p>
                                        </div>
                                    </div>";
                                }
                            } else {
                                echo '<p class="team-img-error">Nenhum membro da equipe cadastrado.</p>';
                            }
                        } catch (PDOException $e) {
                            echo "<p class='team-img-error'>Erro ao carregar equipe: " . htmlspecialchars($e->getMessage()) . "</p>";
                        }
                        ?>
                    </div>
                    <button class="carousel-btn next" aria-label="Next team member">&#10095;</button>
                </div>
            </div>
        </section>

        <section class="testemunhos">
            <div class="container">
                <h2>Testemunhos dos nossos pacientes</h2>
                <p>Veja o que nossos clientes dizem sobre nossos serviços!</p>
                <div class="testemunhos-grid">
                    <div class="testemunho-card">
                        <img src="https://i.pinimg.com/736x/23/d8/29/23d8291e32a3daf58e0d525ce27c405f.jpg"
                            alt="Cliente 1" class="testemunho-img">
                        <div class="testemunho-content">
                            <p>"A equipe foi incrivelmente atenciosa com meu cachorro! A castração foi rápida e ele se
                                recuperou super bem."</p>
                            <h4>Ana Silva</h4>
                        </div>
                    </div>
                    <div class="testemunho-card">
                        <img src="https://i.pinimg.com/1200x/7b/78/11/7b78114280764cc32c93fb696c6a44ab.jpg"
                            alt="Cliente 2" class="testemunho-img">
                        <div class="testemunho-content">
                            <p>"O banho e tosa deixaram minha gatinha linda e cheirosa! Super recomendo!"</p>
                            <h4>Maria Oliveira</h4>
                        </div>
                    </div>
                    <div class="testemunho-card">
                        <img src="https://i.pinimg.com/1200x/4e/9c/81/4e9c8198b2ff727b23b43961962ba3b9.jpg"
                            alt="Cliente 3" class="testemunho-img">
                        <div class="testemunho-content">
                            <p>"A fisioterapia ajudou muito meu cão a voltar a andar após a cirurgia. Equipe nota 10!"
                            </p>
                            <h4>João Santos</h4>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Seções comentadas mantidas -->
        <!--
        <section id="depoimentos" class="depoimentos" aria-labelledby="depoimentos-title">
            <div class="container">
                <span class="tag">Depoimentos</span>
                <h2 id="depoimentos-title">O que nossos clientes dizem</h2>
                <div class="grid">
                    <div class="card">
                        <p>"A PetCare salvou a vida do meu cachorro! Atendimento excelente e muito carinho com os animais."</p>
                        <h4>- Ana Paula</h4>
                    </div>
                    <div class="card">
                        <p>"Equipe extremamente profissional e dedicada. Meu gato foi tratado como um rei."</p>
                        <h4>- Marcos Vinícius</h4>
                    </div>
                    <div class="card">
                        <p>"Serviços de qualidade e preços justos. Recomendo para todos os meus amigos."</p>
                        <h4>- Fernanda Rocha</h4>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="faq" aria-labelledby="faq-title">
            <div class="container">
                <span class="tag">Perguntas Frequentes</span>
                <h2 id="faq-title">Tire suas dúvidas</h2>
                <div class="faq-item">
                    <h3>Vocês atendem emergências?</h3>
                    <p>Sim! Temos plantão 24h para emergências.</p>
                </div>
                <div class="faq-item">
                    <h3>Quais formas de pagamento aceitam?</h3>
                    <p>Aceitamos cartões de crédito, débito, PIX e dinheiro.</p>
                </div>
                <div class="faq-item">
                    <h3>Preciso marcar consulta com antecedência?</h3>
                    <p>Recomendamos agendar para evitar espera, mas também atendemos por ordem de chegada.</p>
                </div>
            </div>
        </section>

        <section id="contato" class="contato" aria-labelledby="contato-title">
            <div class="container">
                <span class="tag">Fale Conosco</span>
                <h2 id="contato-title">Entre em contato</h2>
                <form action="process_contact.php" method="POST" id="contactForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="text" name="name" placeholder="Seu nome" required aria-label="Nome">
                    <input type="email" name="email" placeholder="Seu e-mail" required aria-label="E-mail">
                    <textarea name="message" placeholder="Sua mensagem" required aria-label="Mensagem"></textarea>
                    <button type="submit" class="btn primary">Enviar</button>
                </form>
            </div>
        </section>
        -->

        <?php include 'PHP/footer.php'; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const track = document.querySelector('.carousel-track');
            const prevBtn = document.querySelector('.carousel-btn.prev');
            const nextBtn = document.querySelector('.carousel-btn.next');
            const cardWidth = 370; // largura do card + gap

            // Verificar se os elementos existem
            if (!track || !prevBtn || !nextBtn) {
                console.error('Erro: Elementos do carrossel não encontrados.');
                return;
            }

            let position = 0;

            function updateButtons() {
                const maxScroll = track.scrollWidth - track.clientWidth;
                console.log('maxScroll:', maxScroll, 'position:', position); // Depuração
                prevBtn.disabled = position <= 0;
                nextBtn.disabled = position >= maxScroll || maxScroll <= 0;
            }

            nextBtn.addEventListener('click', () => {
                console.log('Next clicked'); // Depuração
                const maxScroll = track.scrollWidth - track.clientWidth;
                position = Math.min(position + cardWidth, maxScroll);
                track.scrollTo({ left: position, behavior: 'smooth' });
                updateButtons();
            });

            prevBtn.addEventListener('click', () => {
                console.log('Prev clicked'); // Depuração
                position = Math.max(position - cardWidth, 0);
                track.scrollTo({ left: position, behavior: 'smooth' });
                updateButtons();
            });

            // Inicializar botões
            updateButtons();

            // Atualizar botões ao redimensionar a janela
            window.addEventListener('resize', () => {
                console.log('Window resized'); // Depuração
                position = Math.min(position, track.scrollWidth - track.clientWidth);
                track.scrollTo({ left: position, behavior: 'smooth' });
                updateButtons();
            });
        });
    </script>
</body>
</html>