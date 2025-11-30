<?php
session_start();
include 'conexao.php'; 

/* ===============================
   ATUALIZAR USUÁRIO (POST)
   =============================== */
if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_usuario') {
    if (!isset($_SESSION["id"]) || $_SESSION["tipo_usuario"] !== "Secretaria") {
        header("Location: ../index.php");
        exit();
    }

    $id = $_POST['id'] ?? null;
    $tipo = $_POST['tipo_usuario'] ?? null;
    $ativo = $_POST['ativo'] ?? null;

    if ($id && $tipo && $ativo !== null) {
        $sql = "UPDATE usuarios 
                SET tipo_usuario = :tipo, ativo = :ativo, atualizado_em = NOW() 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipo,
            ':ativo' => $ativo,
            ':id' => $id
        ]);
    }
    header("Location: sec_home.php");
    exit();
}

/* ===============================
   DELETAR USUÁRIO (POST)
   =============================== */
if (isset($_POST['acao']) && $_POST['acao'] === 'deletar_usuario') {
    $usuarioId = $_POST['id'] ?? null;

    if ($usuarioId) {
        try {
            $sqlAnimais = "DELETE FROM animais WHERE usuario_id = ?";
            $stmtAnimais = $pdo->prepare($sqlAnimais);
            $stmtAnimais->execute([$usuarioId]);

            $sqlUsuario = "DELETE FROM usuarios WHERE id = ?";
            $stmtUsuario = $pdo->prepare($sqlUsuario);
            $stmtUsuario->execute([$usuarioId]);

            echo "Usuário e seus animais deletados com sucesso!";
        } catch (PDOException $e) {
            echo "Erro ao deletar: " . $e->getMessage();
        }
    } else {
        echo "ID do usuário não informado.";
    }
    exit();
}

/* ===============================
   LISTAR EQUIPE (PÁGINA HTML)
   =============================== */
try {
    $sql = "SELECT nome, profissao, descricao, foto FROM equipe";
    $stmt = $pdo->query($sql);
    $equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar equipe: " . $e->getMessage();
    $equipe = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../Estilos/styles.css"><!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

</head>
<style>
    .team-container {
        text-align: center;
        padding: 50px 20px;
        background-color: #f8f9fa;
    }

    .team-row {
        display: flex;
        justify-content: center;
        gap: 20px;
    }

    .team-member {
        max-width: 350px;
        flex: 1;
    }

    .team-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        text-align: center;
        transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        width: 100%;
        max-width: 350px;
    }

    .team-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .team-img {
        width: 100%;
        height: 250px;
        object-fit: cover;
    }

    .team-body {
        padding: 20px;
    }

    .team-title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .team-subtitle {
        font-size: 16px;
        color: #6c757d;
        margin-bottom: 10px;
    }

    .team-text {
        font-size: 14px;
        color: #333;
    }

    .team-social-icons {
        margin-top: 15px;
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .team-social-icons a {
        color: #4CAF50;
        font-size: 24px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(76, 175, 80, 0.1);
        transition: all 0.3s;
    }

    .team-social-icons a:hover {
        color: white;
        background-color: #4CAF50;
    }
</style>

<body>
    <br><br>
    <div class="team-container mt-5">
        <h1 class="text-center mb-4">Nossa Equipe</h1>

        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                <?php if (count($equipe) > 0): ?>
                    <?php foreach ($equipe as $row): ?>
                        <div class="swiper-slide">
                            <div class="team-card">
                                <img src="../assets/uploads/equipe/<?= htmlspecialchars($row["foto"]) ?>" class="team-img"
                                    alt="Foto de <?= htmlspecialchars($row["nome"]) ?>">
                                <div class="team-body">
                                    <h5 class="team-title"><?= htmlspecialchars($row["nome"]) ?></h5>
                                    <p class="team-subtitle text-muted"><?= htmlspecialchars($row["profissao"]) ?></p>
                                    <p class="team-text"><?= htmlspecialchars($row["descricao"]) ?></p>
                                    <div class="team-social-icons">
                                        <a href="#"><i class="fab fa-facebook"></i></a>
                                        <a href="#"><i class="fab fa-instagram"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">Nenhum membro cadastrado ainda.</p>
                <?php endif; ?>
            </div>

            <!-- Botões de navegação -->
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>

            <!-- Paginação (opcional) -->
            <div class="swiper-pagination"></div>
        </div>
    </div>

    <?php include 'header.php'; ?>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const swiper = new Swiper(".mySwiper", {
            slidesPerView: 3,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 2500,
                disableOnInteraction: false,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            breakpoints: {
                0: { slidesPerView: 1 },
                768: { slidesPerView: 2 },
                1024: { slidesPerView: 3 },
            },
        });
    </script>

</body>

</html>