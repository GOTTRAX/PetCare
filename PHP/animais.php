<?php
include 'conexao.php';

/******************************************
 * PARÂMETROS: usuario_id ou animal_id
 ******************************************/
$usuario_id = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
$animal_id = isset($_GET['animal_id']) ? (int) $_GET['animal_id'] : 0;

/******************************************
 * FUNÇÃO AUXILIAR PARA CALCULAR IDADE
 ******************************************/
function calcularIdade($dataNasc)
{
    if (!$dataNasc || $dataNasc == '0000-00-00')
        return 'Sem data';
    $nasc = new DateTime($dataNasc);
    $hoje = new DateTime();
    $idade = $nasc->diff($hoje);
    return $idade->y . " ano(s)";
}

/******************************************
 * INICIALIZAR VARIÁVEIS
 ******************************************/
$titulo = "Lista de Animais";
$animais = [];
$animal = null;
$agendamentos = [];

/******************************************
 * SEÇÃO 1: DETALHES DO ANIMAL
 ******************************************/
if ($animal_id > 0) {
    $sql = "SELECT a.*, u.nome AS dono, e.nome AS especie
            FROM Animais a
            INNER JOIN Usuarios u ON u.id = a.usuario_id
            INNER JOIN Especies e ON e.id = a.especie_id
            WHERE a.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch();

    if (!$animal) {
        die("<h2>Animal não encontrado!</h2>");
    }

    $titulo = "Detalhes do Animal - " . htmlspecialchars($animal['nome']);

    // Buscar agendamentos desse animal
    $sql_ag = "SELECT ag.*, s.nome AS servico, v.nome AS veterinario
           FROM Agendamentos ag
           LEFT JOIN Servicos s ON s.id = ag.servico_id
           LEFT JOIN Usuarios v ON v.id = ag.veterinario_id
           WHERE ag.animal_id = ?
           ORDER BY ag.data_hora DESC, ag.hora_inicio DESC";
    $stmt_ag = $pdo->prepare($sql_ag);
    $stmt_ag->execute([$animal_id]);
    $agendamentos = $stmt_ag->fetchAll();

} else {
    /******************************************
     * SEÇÃO 2: LISTAGEM DE ANIMAIS
     ******************************************/
    if ($usuario_id > 0) {
        $sql = "SELECT a.*, u.nome AS dono, e.nome AS especie
                FROM Animais a
                INNER JOIN Usuarios u ON u.id = a.usuario_id
                INNER JOIN Especies e ON e.id = a.especie_id
                WHERE a.usuario_id = ?
                ORDER BY a.nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);
        $titulo = "Animais do Usuário";
    } else {
        $sql = "SELECT a.*, u.nome AS dono, e.nome AS especie
                FROM Animais a
                INNER JOIN Usuarios u ON u.id = a.usuario_id
                INNER JOIN Especies e ON e.id = a.especie_id
                ORDER BY u.nome, a.nome";
        $stmt = $pdo->query($sql);
        $titulo = "Todos os Animais";
    }
    $animais = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title><?= $titulo ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ====== RESET E BASE ====== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fdff 0%, #e8f4f8 100%);
            color: #2c3e50;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }

        /* ====== CONTAINER PRINCIPAL ====== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 82, 155, 0.1);
            overflow: hidden;
        }

        /* ====== HEADER ====== */
        .header {
            background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
            color: white;
            padding: 30px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="rgba(255,255,255,0.1)"><path d="M30,30 Q50,10 70,30 T90,30 T70,50 T90,70 T70,90 T50,70 T30,90 T10,70 T30,50 T10,30 T30,30 Z"/></svg>');
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header h1 i {
            color: #4caf50;
            margin-right: 15px;
        }

        .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
            position: relative;
        }

        /* ====== CARDS DE ANIMAIS ====== */
        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            padding: 40px;
        }

        .animal-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 82, 155, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            overflow: hidden;
            position: relative;
        }

        .animal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 82, 155, 0.2);
            border-color: #4caf50;
        }

        .animal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4caf50, #1e88e5);
        }

        .animal-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #e3f2fd, #f1f8e9);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .animal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .animal-card:hover .animal-image img {
            transform: scale(1.05);
        }

        .animal-image .placeholder {
            font-size: 4em;
            color: #1e88e5;
            opacity: 0.7;
        }

        .animal-info {
            padding: 20px;
        }

        .animal-name {
            font-size: 1.4em;
            font-weight: 600;
            color: #1565c0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .animal-name i {
            color: #4caf50;
        }

        .animal-details {
            display: grid;
            gap: 8px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #546e7a;
            font-size: 0.95em;
        }

        .detail-item i {
            width: 16px;
            color: #1e88e5;
        }

        .animal-owner {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            border-left: 4px solid #4caf50;
        }

        .owner-label {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ====== BOTÕES ====== */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.95em;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e88e5, #1565c0);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1565c0, #0d47a1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(21, 101, 192, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: #1e88e5;
            border: 2px solid #1e88e5;
        }

        .btn-outline:hover {
            background: #1e88e5;
            color: white;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* ====== ESTILOS ESPECÍFICOS PARA DETALHES DO ANIMAL ====== */
        .animal-detail {
            padding: 40px;
        }

        .detail-header {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            align-items: flex-start;
        }

        .detail-photo {
            flex-shrink: 0;
            width: 250px;
            height: 250px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 4px solid white;
        }

        .detail-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-info {
            flex: 1;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #4caf50;
        }

        .info-card h3 {
            color: #1565c0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ====== AGENDAMENTOS ====== */
        .appointments-section {
            margin-top: 40px;
        }

        .appointment-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #1e88e5;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .appointment-date {
            font-weight: 600;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .appointment-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-confirmado {
            background: #e8f5e8;
            color: #4caf50;
        }

        .status-pendente {
            background: #fff3cd;
            color: #ff9800;
        }

        .status-cancelado {
            background: #f8d7da;
            color: #dc3545;
        }

        /* ====== BOTÃO VOLTAR ====== */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #f8f9fa;
            color: #546e7a;
            text-decoration: none;
            border-radius: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #e9ecef;
            color: #2c3e50;
        }

        /* ====== MENSAGEM DE LISTA VAZIA ====== */
        .empty-message {
            text-align: center;
            padding: 60px 40px;
            color: #546e7a;
        }

        .empty-message i {
            font-size: 4em;
            color: #b0bec5;
            margin-bottom: 20px;
        }

        /* ====== RESPONSIVIDADE ====== */
        @media (max-width: 768px) {
            .animals-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .detail-header {
                flex-direction: column;
                text-align: center;
            }

            .detail-photo {
                width: 200px;
                height: 200px;
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-paw"></i><?= htmlspecialchars($titulo) ?></h1>
            <p class="subtitle">Cuidando com amor e dedicação</p>
        </div>

        <?php if ($animal_id > 0 && $animal): ?>
            <!-- DETALHES DO ANIMAL -->
            <div class="animal-detail">
                <div class="detail-header">
                    <div class="detail-photo">
                        <?php if ($animal['foto']): ?>
                            <img src="../assets/uploads/pets/<?= htmlspecialchars($animal['foto']) ?>" alt="Foto do animal">
                        <?php else: ?>
                            <div
                                style="width: 100%; height: 100%; background: linear-gradient(135deg, #e3f2fd, #f1f8e9); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-paw" style="font-size: 4em; color: #1e88e5; opacity: 0.7;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="detail-info">
                        <h1 class="animal-name">
                            <i class="fas fa-paw"></i><?= htmlspecialchars($animal['nome']) ?>
                        </h1>
                        <div class="info-grid">
                            <div class="info-card">
                                <h3><i class="fas fa-dna"></i>Informações Básicas</h3>
                                <p><strong>Espécie:</strong> <?= htmlspecialchars($animal['especie']) ?></p>
                                <p><strong>Raça:</strong> <?= htmlspecialchars($animal['raca']) ?></p>
                                <p><strong>Porte:</strong> <?= htmlspecialchars($animal['porte']) ?></p>
                            </div>
                            <div class="info-card">
                                <h3><i class="fas fa-info-circle"></i>Detalhes</h3>
                                <p><strong>Sexo:</strong> <?= htmlspecialchars($animal['sexo']) ?></p>
                                <p><strong>Idade:</strong> <?= calcularIdade($animal['datanasc']) ?></p>
                                <p><strong>Dono:</strong> <?= htmlspecialchars($animal['dono']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="appointments-section">
                    <h2><i class="fas fa-calendar-alt"></i> Agendamentos</h2>
                    <?php if ($agendamentos && count($agendamentos) > 0): ?>
                        <?php foreach ($agendamentos as $ag): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <span class="appointment-date">
                                        <i class="far fa-calendar"></i>
                                        <?php
                                        // Verifica se existe data_hora ou data separado
                                        if (isset($ag['data_hora']) && !empty($ag['data_hora'])) {
                                            echo date('d/m/Y', strtotime($ag['data_hora']));
                                        } elseif (isset($ag['data']) && !empty($ag['data'])) {
                                            echo date('d/m/Y', strtotime($ag['data']));
                                        } else {
                                            echo 'Data não informada';
                                        }
                                        ?>
                                        às <?= htmlspecialchars($ag['hora_inicio'] ?? 'Horário não informado') ?>
                                    </span>
                                    <span class="appointment-status status-<?= htmlspecialchars($ag['status']) ?>">
                                        <?= htmlspecialchars($ag['status']) ?>
                                    </span>
                                </div>
                                <p><strong>Serviço:</strong> <?= htmlspecialchars($ag['servico'] ?? '-') ?></p>
                                <p><strong>Veterinário:</strong> <?= htmlspecialchars($ag['veterinario'] ?? '-') ?></p>
                                <?php if ($ag['observacoes']): ?>
                                    <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($ag['observacoes'])) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="info-card">
                            <p><i class="fas fa-info-circle"></i> Nenhum agendamento para este animal.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="animais.php<?= $usuario_id ? '?usuario_id=' . $usuario_id : '' ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Voltar à lista
                </a>
            </div>

        <?php else: ?>
            <!-- LISTA DE ANIMAIS -->
            <div class="animals-grid">
                <?php if ($animais && count($animais) > 0): ?>
                    <?php foreach ($animais as $a): ?>
                        <div class="animal-card">
                            <div class="animal-image">
                                <?php if ($a['foto']): ?>
                                    <img src="../assets/uploads/pets/<?= htmlspecialchars($a['foto']) ?>"
                                        alt="<?= htmlspecialchars($a['nome']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-paw placeholder"></i>
                                <?php endif; ?>
                            </div>
                            <div class="animal-info">
                                <h3 class="animal-name">
                                    <i class="fas fa-paw"></i><?= htmlspecialchars($a['nome']) ?>
                                </h3>
                                <div class="animal-details">
                                    <div class="detail-item">
                                        <i class="fas fa-dna"></i>
                                        <span><?= htmlspecialchars($a['especie']) ?> • <?= htmlspecialchars($a['raca']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-weight"></i>
                                        <span>Porte <?= htmlspecialchars($a['porte']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <span><?= calcularIdade($a['datanasc']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-venus-mars"></i>
                                        <span><?= htmlspecialchars($a['sexo']) ?></span>
                                    </div>
                                </div>
                                <div class="animal-owner">
                                    <span class="owner-label">
                                        <i class="fas fa-user"></i>
                                        Dono: <?= htmlspecialchars($a['dono']) ?>
                                    </span>
                                </div>
                                <div class="actions">
                                    <a href="animais.php?animal_id=<?= $a['id'] ?><?= $usuario_id ? '&usuario_id=' . $usuario_id : '' ?>"
                                        class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fas fa-paw"></i>
                        <h3>Nenhum animal encontrado</h3>
                        <p>Não há animais cadastrados no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>