
<?php
// Inicia a sessão
session_start();
require_once '../conexao.php';

// Verifica se o usuário está logado e é Secretaria
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'Secretaria') {
    header("Location: ../../index.php");
    exit;
}

// Obtém o termo de pesquisa
$search_term = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING) ?? '';
$search_term = trim($search_term);
$results = [
    'clientes' => [],
    'animais' => [],
    'agendamentos' => []
];

if ($search_term !== '') {
    try {
        // Pesquisa por clientes
        $stmt_clientes = $pdo->prepare("
            SELECT id, nome, email, cpf 
            FROM Usuarios 
            WHERE nome LIKE ? OR email LIKE ? OR cpf LIKE ? 
            AND tipo_usuario = 'Cliente' AND ativo = TRUE
        ");
        $search_like = '%' . $search_term . '%';
        $stmt_clientes->execute([$search_like, $search_like, $search_like]);
        $results['clientes'] = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

        // Pesquisa por animais
        $stmt_animais = $pdo->prepare("
            SELECT a.id, a.nome, a.raca, e.nome as especie 
            FROM Animais a 
            INNER JOIN Especies e ON a.especie_id = e.id 
            WHERE a.nome LIKE ? OR a.raca LIKE ?
        ");
        $stmt_animais->execute([$search_like, $search_like]);
        $results['animais'] = $stmt_animais->fetchAll(PDO::FETCH_ASSOC);

        // Pesquisa por agendamentos
        $stmt_agendamentos = $pdo->prepare("
            SELECT a.id, a.data_hora, a.hora_inicio, u.nome as cliente, an.nome as animal 
            FROM Agendamentos a 
            INNER JOIN Usuarios u ON a.cliente_id = u.id 
            INNER JOIN Animais an ON a.animal_id = an.id 
            WHERE u.nome LIKE ? OR an.nome LIKE ? OR a.status LIKE ?
        ");
        $stmt_agendamentos->execute([$search_like, $search_like, $search_like]);
        $results['agendamentos'] = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "Erro ao realizar a pesquisa: " . $e->getMessage();
    }
}

// Definir título da página
$paginaTitulo = "Resultados da Pesquisa";

// Incluir header
include 'header.php';
?>

<link rel="stylesheet" href="../../Estilos/secretaria.css">
<style>
    .search-results {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 1rem;
    }

    .results-section {
        margin-bottom: 2rem;
    }

    .results-section h2 {
        font-size: 1.5rem;
        color: #2563eb;
        margin-bottom: 1rem;
    }

    .results-list {
        list-style: none;
        padding: 0;
    }

    .results-item {
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .results-item p {
        margin: 0;
        font-size: 0.875rem;
        color: #111827;
    }

    .error-message {
        background: #fee2e2;
        color: #dc2626;
        padding: 0.75rem;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 1rem;
    }

    .no-results {
        font-size: 0.875rem;
        color: #6b7280;
        text-align: center;
    }
</style>

<div class="search-results">
    <h1>Resultados da Pesquisa para "<?= htmlspecialchars($search_term) ?>"</h1>

    <?php if (isset($error_message)): ?>
        <div class="error-message" role="alert"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Resultados de Clientes -->
    <div class="results-section">
        <h2>Clientes</h2>
        <?php if (empty($results['clientes'])): ?>
            <p class="no-results">Nenhum cliente encontrado.</p>
        <?php else: ?>
            <ul class="results-list">
                <?php foreach ($results['clientes'] as $cliente): ?>
                    <li class="results-item">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['nome']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
                        <p><strong>CPF:</strong> <?= htmlspecialchars($cliente['cpf']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Resultados de Animais -->
    <div class="results-section">
        <h2>Animais</h2>
        <?php if (empty($results['animais'])): ?>
            <p class="no-results">Nenhum animal encontrado.</p>
        <?php else: ?>
            <ul class="results-list">
                <?php foreach ($results['animais'] as $animal): ?>
                    <li class="results-item">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($animal['nome']) ?></p>
                        <p><strong>Raça:</strong> <?= htmlspecialchars($animal['raca'] ?? 'N/A') ?></p>
                        <p><strong>Espécie:</strong> <?= htmlspecialchars($animal['especie']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Resultados de Agendamentos -->
    <div class="results-section">
        <h2>Agendamentos</h2>
        <?php if (empty($results['agendamentos'])): ?>
            <p class="no-results">Nenhum agendamento encontrado.</p>
        <?php else: ?>
            <ul class="results-list">
                <?php foreach ($results['agendamentos'] as $agendamento): ?>
                    <li class="results-item">
                        <p><strong>Cliente:</strong> <?= htmlspecialchars($agendamento['cliente']) ?></p>
                        <p><strong>Animal:</strong> <?= htmlspecialchars($agendamento['animal']) ?></p>
                        <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($agendamento['data_hora'])) ?> • <?= substr($agendamento['hora_inicio'], 0, 5) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir footer
include 'footer.php';
?>