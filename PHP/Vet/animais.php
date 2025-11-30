<?php
session_start();
include '../conexao.php';

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
 * BUSCAR DADOS DO BANCO
 ******************************************/
$especies = [];
$usuarios = [];
$animais = [];
$animal_selecionado = null;
$agendamentos = [];

try {
    // Endpoint AJAX para buscar agendamentos com consultas
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'agendamentos' && isset($_GET['animal_id'])) {
        $animal_id = (int) $_GET['animal_id'];

        $stmt = $pdo->prepare("
            SELECT 
                ag.id,
                ag.data_hora,
                ag.hora_inicio,
                ag.hora_final,
                ag.status,
                ag.observacoes as obs_agendamento,
                s.nome AS servico,
                v.nome AS veterinario_agendamento,
                c.id as consulta_id,
                c.data_consulta,
                c.diagnostico,
                c.tratamento,
                c.receita,
                c.observacoes as obs_consulta,
                c.mensagem,
                sec.nome as secretario,
                vet.nome as veterinario_prescricao
            FROM Agendamentos ag
            LEFT JOIN Servicos s ON s.id = ag.servico_id
            LEFT JOIN Usuarios v ON v.id = ag.veterinario_id
            LEFT JOIN Consultas c ON c.agendamento_id = ag.id
            LEFT JOIN Usuarios sec ON sec.id = c.secretario_id
            LEFT JOIN Usuarios vet ON vet.id = c.veterinario_id
            WHERE ag.animal_id = ? 
              AND ag.status = 'finalizado'
              AND c.id IS NOT NULL
            ORDER BY ag.data_hora DESC, ag.hora_inicio DESC
        ");
        $stmt->execute([$animal_id]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($agendamentos);
        exit;
    }

    // Buscar espécies
    $stmt = $pdo->query("SELECT id, nome FROM Especies ORDER BY nome");
    $especies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar usuários (clientes) com contagem de pets
    $stmt = $pdo->query("
        SELECT u.id, u.nome, u.email, u.telefone,
               COUNT(a.id) as total_pets,
               GROUP_CONCAT(a.foto) as pet_fotos
        FROM Usuarios u
        LEFT JOIN Animais a ON u.id = a.usuario_id
        WHERE u.tipo_usuario = 'Cliente'
        GROUP BY u.id
        ORDER BY u.nome
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar todos os animais com informações completas
    $stmt = $pdo->query("
        SELECT a.*, 
               e.nome as especie_nome, 
               u.nome as dono_nome, 
               u.email as dono_email, 
               u.telefone as dono_telefone,
               u.id as dono_id
        FROM Animais a 
        INNER JOIN Especies e ON a.especie_id = e.id 
        INNER JOIN Usuarios u ON a.usuario_id = u.id
        ORDER BY a.nome
    ");
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Verificar se veio filtro de usuário
    if (isset($_GET['usuario_id']) && !empty($_GET['usuario_id'])) {
        $stmt = $pdo->prepare("SELECT nome, email FROM Usuarios WHERE id = ?");
        $stmt->execute([(int) $_GET['usuario_id']]);
        $usuario_filtrado = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichas dos Animais - PetCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../Estilos/vet.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<style>
    /* Modal Principal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        animation: fadeIn 0.3s ease;
    }

    .modal.show {
        display: block;
    }

    .modal-content {
        position: relative;
        background-color: white;
        margin: 2% auto;
        width: 90%;
        max-width: 1000px;
        max-height: 90vh;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideUp 0.3s ease;
    }

    .modal-prontuario-unificado {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    /* Header do Modal */
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        color: white;
        border-bottom: 1px solid #e0e0e0;
    }

    .modal-header h2 {
        color: white;
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .btn-close-modal {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-close-modal:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    /* Corpo do Modal */
    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 0;
    }

    .prontuario-wrapper {
        display: flex;
        flex-direction: column;
        min-height: 500px;
    }

    /* Seção Superior - Foto à esquerda, info à direita, tutor abaixo */
    .prontuario-superior {
        padding: 30px;
        background: white;
        border-bottom: 2px solid #e2e8f0;
    }

    /* Container principal com foto à esquerda e informações à direita */
    .animal-info-container {
        display: flex;
        gap: 30px;
        align-items: flex-start;
        margin-bottom: 25px;
    }

    /* Foto do animal à esquerda */
    .animal-foto-principal {
        flex-shrink: 0;
        width: 180px;
        height: 180px;
    }

    .animal-foto-principal img {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        object-fit: cover;
        border: 4px solid #2563eb;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
    }

    .foto-placeholder-principal {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        border: 4px solid #2563eb;
    }

    /* Informações pessoais à direita */
    .animal-dados-principais {
        flex: 1;
    }

    .animal-dados-principais h2 {
        margin: 0 0 15px 0;
        color: #1e293b;
        font-size: 2.2rem;
        font-weight: 700;
        border-bottom: 3px solid #2563eb;
        padding-bottom: 10px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        border-left: 4px solid #2563eb;
    }

    .info-item i {
        color: #2563eb;
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }

    .info-item span {
        color: #1e293b;
        font-size: 1rem;
        font-weight: 500;
    }

    /* Informações do tutor - abaixo, compacto */
    .tutor-info-compact {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 100%);
        border: 2px solid #2563eb;
        border-radius: 10px;
        padding: 20px;
        margin-top: 15px;
    }

    .tutor-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
        color: #1e3a8a;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .tutor-header i {
        color: #2563eb;
        font-size: 1.2rem;
    }

    .tutor-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .tutor-detail {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #475569;
    }

    .tutor-detail i {
        color: #2563eb;
        width: 16px;
    }

    .tutor-detail strong {
        color: #1e293b;
    }

    /* Timeline de Consultas - Fica embaixo */
    .consultas-timeline {
        padding: 30px;
        background: #fafafa;
    }

    .timeline-titulo {
        display: flex;
        align-items: center;
        gap: 15px;
        color: #1e293b;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e2e8f0;
    }

    .timeline-titulo i {
        color: #2563eb;
    }

    .contador {
        background: #2563eb;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .consulta-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .consulta-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }

    .consulta-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .consulta-data {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .consulta-data i {
        font-size: 1.5rem;
        color: #2563eb;
    }

    .consulta-data-info h4 {
        margin: 0;
        color: #1e293b;
        font-size: 1.2rem;
    }

    .consulta-data-info span {
        color: #64748b;
        font-size: 0.9rem;
    }

    .btn-download-consulta {
        background: #2563eb;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-download-consulta:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .consulta-profissionais {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .prof-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #475569;
    }

    .prof-info i {
        color: #2563eb;
        width: 16px;
    }

    .consulta-campos {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .campo-info {
        background: white;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .campo-label {
        background: #f8fafc;
        padding: 12px 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #e2e8f0;
    }

    .campo-label i {
        color: #2563eb;
    }

    .campo-label strong {
        color: #374151;
    }

    .campo-conteudo {
        padding: 15px;
        color: #4b5563;
        line-height: 1.6;
        white-space: pre-line;
    }

    /* Estados vazios */
    .empty-state-consultas {
        text-align: center;
        padding: 60px 30px;
        color: #64748b;
    }

    .empty-state-consultas i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 20px;
    }

    .empty-state-consultas h3 {
        margin: 0 0 10px 0;
        color: #475569;
    }

    /* Footer do Modal */
    .modal-footer-actions {
        padding: 20px 30px;
        background: #f8f9fa;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: center;
    }

    .btn-download-completo {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-download-completo:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
    }

    .btn-download-completo:active {
        transform: translateY(0);
    }

    .btn-download-completo:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Animações */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Scrollbar personalizada */
    .modal-body::-webkit-scrollbar {
        width: 8px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 5% auto;
            max-height: 95vh;
        }

        .animal-info-container {
            flex-direction: column;
            text-align: center;
        }

        .animal-foto-principal {
            align-self: center;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .tutor-details {
            grid-template-columns: 1fr;
        }

        .consulta-header {
            flex-direction: column;
            gap: 15px;
        }

        .consulta-profissionais {
            grid-template-columns: 1fr;
        }

        .modal-header {
            padding: 15px 20px;
        }

        .prontuario-superior,
        .consultas-timeline {
            padding: 20px;
        }
    }
</style>

<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-paw"></i>
                <span>PetCare</span>
            </div>
            <h1>Fichas dos Animais</h1>
            <div class="header-actions">
                <button class="btn-icon" id="darkModeToggle" title="Alternar tema">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="javascript:history.back()" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <!-- Sidebar - Lista de Clientes -->
        <aside class="clients-sidebar" id="clientsSidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-users"></i> Clientes</h3>
                <button class="btn-collapse" id="collapseSidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>

            <div class="clients-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchClients" placeholder="Buscar cliente...">
            </div>

            <div class="clients-list" id="clientsList">
                <button class="client-item active" data-client-id="all">
                    <div class="client-avatar">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="client-info">
                        <span class="client-name">Todos os Clientes</span>
                        <span class="client-pets"><?= count($animais) ?> pets</span>
                    </div>
                </button>

                <?php foreach ($usuarios as $usuario):
                    $primeiraFoto = null;
                    if (!empty($usuario['pet_fotos'])) {
                        $fotos = explode(',', $usuario['pet_fotos']);
                        $primeiraFoto = $fotos[0];
                    }
                    ?>
                    <button class="client-item" data-client-id="<?= $usuario['id'] ?>">
                        <div class="client-avatar">
                            <?php
                            $partes = explode(' ', $usuario['nome']);
                            $iniciais = strtoupper($partes[0][0]);
                            if (isset($partes[1]))
                                $iniciais .= strtoupper($partes[1][0]);
                            echo $iniciais;
                            ?>
                        </div>
                        <div class="client-info">
                            <span class="client-name"><?= htmlspecialchars($usuario['nome']) ?></span>
                            <span class="client-pets"><?= $usuario['total_pets'] ?> pet(s)</span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Conteúdo Principal -->
        <main class="main-content" id="mainContent">
            <!-- Barra de Filtros e Busca -->
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchAnimals" placeholder="Buscar por nome do animal...">
                </div>

                <div class="filters-group">
                    <select id="especieFilter" class="filter-select">
                        <option value="all">Todas as espécies</option>
                        <?php foreach ($especies as $especie): ?>
                            <option value="<?= $especie['id'] ?>"><?= htmlspecialchars($especie['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="sexoFilter" class="filter-select">
                        <option value="all">Todos os sexos</option>
                        <option value="Macho">Macho</option>
                        <option value="Fêmea">Fêmea</option>
                    </select>

                    <select id="porteFilter" class="filter-select">
                        <option value="all">Todos os portes</option>
                        <option value="Pequeno">Pequeno</option>
                        <option value="Medio">Médio</option>
                        <option value="Grande">Grande</option>
                    </select>

                    <button class="btn-clear-filters" id="clearFilters">
                        <i class="fas fa-times"></i>
                        Limpar Filtros
                    </button>
                </div>
            </div>

            <!-- Grid de Animais -->
            <div class="animals-grid" id="animalsGrid">
                <?php if (count($animais) > 0): ?>
                    <?php foreach ($animais as $animal): ?>
                        <div class="animal-card" data-animal-id="<?= $animal['id'] ?>"
                            data-especie="<?= $animal['especie_id'] ?>" data-sexo="<?= $animal['sexo'] ?>"
                            data-porte="<?= $animal['porte'] ?>" data-dono="<?= $animal['dono_id'] ?>"
                            data-nome="<?= strtolower($animal['nome']) ?>">

                            <div class="card-image">
                                <?php if ($animal['foto']): ?>
                                    <img src="../../assets/uploads/pets/<?= htmlspecialchars($animal['foto']) ?>"
                                        alt="<?= htmlspecialchars($animal['nome']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-image">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-badge <?= strtolower($animal['sexo']) ?>">
                                    <i class="fas fa-<?= $animal['sexo'] == 'Macho' ? 'mars' : 'venus' ?>"></i>
                                </div>
                            </div>

                            <div class="card-content">
                                <h3 class="animal-name"><?= htmlspecialchars($animal['nome']) ?></h3>

                                <div class="animal-details">
                                    <span class="detail-item">
                                        <i class="fas fa-dna"></i>
                                        <?= htmlspecialchars($animal['especie_nome']) ?>
                                    </span>
                                    <span class="detail-item">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($animal['raca'] ?: 'SRD') ?>
                                    </span>
                                    <span class="detail-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <?= calcularIdade($animal['datanasc']) ?>
                                    </span>
                                    <span class="detail-item">
                                        <i class="fas fa-weight"></i>
                                        Porte <?= htmlspecialchars($animal['porte']) ?>
                                    </span>
                                </div>

                                <div class="animal-owner">
                                    <i class="fas fa-user"></i>
                                    <span><?= htmlspecialchars($animal['dono_nome']) ?></span>
                                </div>

                                <button class="btn-view-details" onclick="viewAnimalDetails(<?= $animal['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                    Ver Detalhes
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-paw"></i>
                        <h3>Nenhum animal cadastrado</h3>
                        <p>Não há animais cadastrados no sistema.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Unificado de Prontuário -->
    <div class="modal" id="prontuarioModal">
        <div class="modal-content modal-prontuario-unificado">
            <div class="modal-header">
                <h2>Prontuário Completo</h2>
                <button class="btn-close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="prontuarioContent" class="prontuario-wrapper">
                    <!-- Conteúdo será preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer-actions">
                <button class="btn-download-completo" onclick="baixarProntuarioCompleto()">
                    <i class="fas fa-download"></i>
                    Baixar Prontuário Completo
                </button>
            </div>
        </div>
    </div>

    <script>
        // Dados dos animais
        const animaisData = <?= json_encode($animais) ?>;
        const clientesData = <?= json_encode($usuarios) ?>;

        // Variáveis de controle
        const urlParams = new URLSearchParams(window.location.search);
        const usuarioIdDaUrl = urlParams.get('usuario_id');
        let agendamentosAtuais = [];

        // Inicializar com o ID da URL ou 'all'
        let filtroClienteAtivo = usuarioIdDaUrl || 'all';

        // Se veio usuario_id, destacar o cliente
        if (usuarioIdDaUrl) {
            document.querySelectorAll('.client-item').forEach(i => i.classList.remove('active'));

            const clienteItem = document.querySelector(`.client-item[data-client-id="${usuarioIdDaUrl}"]`);
            if (clienteItem) {
                clienteItem.classList.add('active');
                clienteItem.classList.add('highlight');
                clienteItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

                setTimeout(() => clienteItem.classList.remove('highlight'), 1500);
            }

            // Aplicar filtro automaticamente
            setTimeout(() => aplicarFiltros(), 100);
        }

        // Dark Mode
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = darkModeToggle.querySelector('i');

        // Verificar preferência salva
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            darkModeIcon.classList.replace('fa-moon', 'fa-sun');
        }

        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');

            if (document.body.classList.contains('dark-mode')) {
                darkModeIcon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('darkMode', 'enabled');
            } else {
                darkModeIcon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('darkMode', 'disabled');
            }
        });

        // Collapse Sidebar
        const collapseSidebar = document.getElementById('collapseSidebar');
        const sidebar = document.getElementById('clientsSidebar');
        const mainContent = document.getElementById('mainContent');

        collapseSidebar.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');

            const icon = collapseSidebar.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
                sidebar.style.width = '60px';
                sidebar.style.borderRight = '1px solid var(--border-color)';
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
                sidebar.style.width = '';
                sidebar.style.borderRight = '';
            }
        });

        // Busca de Clientes
        document.getElementById('searchClients').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const clientItems = document.querySelectorAll('.client-item');

            clientItems.forEach(item => {
                if (item.dataset.clientId === 'all') return;

                const clientName = item.querySelector('.client-name').textContent.toLowerCase();
                if (clientName.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Filtro por Cliente
        document.querySelectorAll('.client-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.client-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');

                filtroClienteAtivo = item.dataset.clientId;
                aplicarFiltros();
            });
        });

        // Busca de Animais
        document.getElementById('searchAnimals').addEventListener('input', aplicarFiltros);
        document.getElementById('especieFilter').addEventListener('change', aplicarFiltros);
        document.getElementById('sexoFilter').addEventListener('change', aplicarFiltros);
        document.getElementById('porteFilter').addEventListener('change', aplicarFiltros);

        // Limpar Filtros
        document.getElementById('clearFilters').addEventListener('click', () => {
            document.getElementById('searchAnimals').value = '';
            document.getElementById('especieFilter').value = 'all';
            document.getElementById('sexoFilter').value = 'all';
            document.getElementById('porteFilter').value = 'all';
            aplicarFiltros();
        });

        // Função para aplicar todos os filtros
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchAnimals').value.toLowerCase();
            const especieFilter = document.getElementById('especieFilter').value;
            const sexoFilter = document.getElementById('sexoFilter').value;
            const porteFilter = document.getElementById('porteFilter').value;

            const cards = document.querySelectorAll('.animal-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const nome = card.dataset.nome;
                const especie = card.dataset.especie;
                const sexo = card.dataset.sexo;
                const porte = card.dataset.porte;
                const dono = card.dataset.dono;

                let show = true;

                // Filtro de cliente
                if (filtroClienteAtivo !== 'all' && dono !== filtroClienteAtivo) {
                    show = false;
                }

                // Filtro de busca por nome
                if (searchTerm && !nome.includes(searchTerm)) {
                    show = false;
                }

                // Filtro de espécie
                if (especieFilter !== 'all' && especie !== especieFilter) {
                    show = false;
                }

                // Filtro de sexo
                if (sexoFilter !== 'all' && sexo !== sexoFilter) {
                    show = false;
                }

                // Filtro de porte
                if (porteFilter !== 'all' && porte !== porteFilter) {
                    show = false;
                }

                card.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });

            // Mostrar mensagem se não houver resultados
            const grid = document.getElementById('animalsGrid');
            let emptyState = grid.querySelector('.empty-state');

            if (visibleCount === 0) {
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.className = 'empty-state';
                    emptyState.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>Nenhum animal encontrado</h3>
                        <p>Tente ajustar os filtros para ver mais resultados.</p>
                    `;
                    grid.appendChild(emptyState);
                }
            } else {
                if (emptyState) {
                    emptyState.remove();
                }
            }
        }

        // Função para visualizar detalhes do animal com agendamentos
        async function viewAnimalDetails(animalId) {
            const animal = animaisData.find(a => a.id == animalId);
            if (!animal) return;

            const modal = document.getElementById('prontuarioModal');
            const prontuarioContent = document.getElementById('prontuarioContent');

            // Mostrar loading
            prontuarioContent.innerHTML = `
                <div style="text-align: center; padding: 60px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #6366f1;"></i>
                    <p style="margin-top: 20px; color: #64748b;">Carregando prontuário...</p>
                </div>
            `;

            modal.classList.add('show');

            try {
                // Buscar agendamentos
                const response = await fetch(`animais.php?ajax=agendamentos&animal_id=${animalId}`);
                agendamentosAtuais = await response.json();

                const fotoUrl = animal.foto ? `../../assets/uploads/pets/${animal.foto}` : '';

                prontuarioContent.innerHTML = `
                    <!-- Seção Superior Compacta -->
                    <div class="prontuario-superior">
                        <!-- Container com foto à esquerda e informações à direita -->
                        <div class="animal-info-container">
                            <div class="animal-foto-principal">
                                ${animal.foto ?
                        `<img src="${fotoUrl}" alt="${animal.nome}">` :
                        `<div class="foto-placeholder-principal"><i class="fas fa-paw"></i></div>`
                    }
                            </div>
                            <div class="animal-dados-principais">
                                <h2>${animal.nome}</h2>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <i class="fas fa-dna"></i>
                                        <span>${animal.especie_nome}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-tag"></i>
                                        <span>${animal.raca || 'SRD'}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-${animal.sexo == 'Macho' ? 'mars' : 'venus'}"></i>
                                        <span>${animal.sexo}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-weight"></i>
                                        <span>Porte ${animal.porte}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-birthday-cake"></i>
                                        <span>${calcularIdadeJS(animal.datanasc)}</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>${formatarData(animal.datanasc)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informações do Tutor - Compacto -->
                        <div class="tutor-info-compact">
                            <div class="tutor-header">
                                <i class="fas fa-user"></i>
                                <span>Informações do Tutor</span>
                            </div>
                            <div class="tutor-details">
                                <div class="tutor-detail">
                                    <i class="fas fa-id-card"></i>
                                    <strong>Nome:</strong> ${animal.dono_nome}
                                </div>
                                <div class="tutor-detail">
                                    <i class="fas fa-envelope"></i>
                                    <strong>Email:</strong> ${animal.dono_email}
                                </div>
                                <div class="tutor-detail">
                                    <i class="fas fa-phone"></i>
                                    <strong>Telefone:</strong> ${animal.dono_telefone || 'Não informado'}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline de Consultas -->
                    <div class="consultas-timeline">
                        <h3 class="timeline-titulo">
                            <i class="fas fa-history"></i>
                            Histórico de Consultas
                            <span class="contador">${agendamentosAtuais.length}</span>
                        </h3>

                        ${agendamentosAtuais.length > 0 ? agendamentosAtuais.map((ag, index) => `
                            <div class="consulta-card" data-consulta-index="${index}">
                                <div class="consulta-header">
                                    <div class="consulta-data">
                                        <i class="fas fa-calendar-check"></i>
                                        <div class="consulta-data-info">
                                            <h4>${formatarData(ag.data_hora)}</h4>
                                            <span>${ag.hora_inicio} - ${ag.hora_final}</span>
                                        </div>
                                    </div>
                                    <button class="btn-download-consulta" onclick="baixarConsultaIndividual(${index}, '${animal.nome}', '${formatarData(ag.data_hora)}')">
                                        <i class="fas fa-download"></i>
                                        Baixar
                                    </button>
                                </div>

                                <div class="consulta-profissionais">
                                    <div class="prof-info">
                                        <i class="fas fa-stethoscope"></i>
                                        <span><strong>Serviço:</strong> ${ag.servico || 'Consulta Geral'}</span>
                                    </div>
                                    <div class="prof-info">
                                        <i class="fas fa-edit"></i>
                                        <span><strong>Veterinário (Prescrição):</strong> Dr(a). ${ag.veterinario_prescricao || 'Não informado'}</span>
                                    </div>
                                    ${ag.secretario ? `
                                        <div class="prof-info">
                                            <i class="fas fa-user-nurse"></i>
                                            <span><strong>Atendente:</strong> ${ag.secretario}</span>
                                        </div>
                                    ` : ''}
                                </div>

                                <div class="consulta-campos">
                                    ${ag.diagnostico ? `
                                        <div class="campo-info diagnostico">
                                            <div class="campo-label">
                                                <i class="fas fa-notes-medical"></i>
                                                <strong>Diagnóstico</strong>
                                            </div>
                                            <div class="campo-conteudo">${ag.diagnostico}</div>
                                        </div>
                                    ` : ''}

                                    ${ag.tratamento ? `
                                        <div class="campo-info tratamento">
                                            <div class="campo-label">
                                                <i class="fas fa-pills"></i>
                                                <strong>Tratamento Prescrito</strong>
                                            </div>
                                            <div class="campo-conteudo">${ag.tratamento}</div>
                                        </div>
                                    ` : ''}

                                    ${ag.receita ? `
                                        <div class="campo-info receita">
                                            <div class="campo-label">
                                                <i class="fas fa-prescription"></i>
                                                <strong>Receita Médica</strong>
                                            </div>
                                            <div class="campo-conteudo">${ag.receita}</div>
                                        </div>
                                    ` : ''}

                                    ${ag.obs_consulta ? `
                                        <div class="campo-info observacoes">
                                            <div class="campo-label">
                                                <i class="fas fa-comment-medical"></i>
                                                <strong>Observações</strong>
                                            </div>
                                            <div class="campo-conteudo">${ag.obs_consulta}</div>
                                        </div>
                                    ` : ''}

                                    ${ag.mensagem ? `
                                        <div class="campo-info mensagem">
                                            <div class="campo-label">
                                                <i class="fas fa-envelope"></i>
                                                <strong>Mensagem do Veterinário</strong>
                                            </div>
                                            <div class="campo-conteudo">${ag.mensagem}</div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `).join('') : `
                            <div class="empty-state-consultas">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>Nenhuma consulta finalizada</h3>
                                <p>Este animal ainda não possui consultas finalizadas no sistema.</p>
                            </div>
                        `}
                    </div>
                `;

            } catch (error) {
                console.error('Erro ao buscar agendamentos:', error);
                prontuarioContent.innerHTML = `
                    <div style="text-align: center; padding: 60px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                        <h3 style="margin-top: 20px; color: #1e293b;">Erro ao carregar prontuário</h3>
                        <p style="color: #64748b;">Tente novamente mais tarde.</p>
                    </div>
                `;
            }
        }

        // Função para fechar modal
        function closeModal() {
            document.getElementById('prontuarioModal').classList.remove('show');
        }

        // Fechar modal ao clicar fora
        document.getElementById('prontuarioModal').addEventListener('click', (e) => {
            if (e.target.id === 'prontuarioModal') {
                closeModal();
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Função auxiliar para calcular idade em JS
        function calcularIdadeJS(dataNasc) {
            if (!dataNasc || dataNasc === '0000-00-00') return 'N/A';
            const hoje = new Date();
            const nascimento = new Date(dataNasc + 'T00:00:00');
            let idade = hoje.getFullYear() - nascimento.getFullYear();
            const mes = hoje.getMonth() - nascimento.getMonth();

            if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                idade--;
            }

            return `${idade} ano(s)`;
        }

        // Função auxiliar para formatar data
        function formatarData(data) {
            if (!data) return 'N/A';
            const d = new Date(data + 'T00:00:00');
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        // Função para baixar consulta individual - CORRIGIDA
        // Função para baixar consulta individual - CORRIGIDA E SIMPLIFICADA
        async function baixarConsultaIndividual(index, nomeAnimal, dataConsulta) {
            const btn = event.target;
            const textoOriginal = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
            btn.disabled = true;

            try {
                // Buscar o animal atual baseado no modal aberto
                const prontuarioContent = document.getElementById('prontuarioContent');
                const nomeAnimalElement = prontuarioContent.querySelector('.animal-dados-principais h2');
                const nomeAnimalAtual = nomeAnimalElement ? nomeAnimalElement.textContent : nomeAnimal;

                // Encontrar o animal nos dados
                const animal = animaisData.find(a => a.nome === nomeAnimalAtual);

                if (!animal) {
                    throw new Error('Animal não encontrado nos dados');
                }

                // Buscar os agendamentos deste animal
                const response = await fetch(`animais.php?ajax=agendamentos&animal_id=${animal.id}`);
                const agendamentos = await response.json();

                if (!agendamentos[index]) {
                    throw new Error('Consulta não encontrada');
                }

                const ag = agendamentos[index];
                const fotoUrl = animal.foto ? `../../assets/uploads/pets/${animal.foto}` : '';

                // Criar container temporário
                const wrapperTemp = document.createElement('div');
                wrapperTemp.style.cssText = `
            position: fixed;
            top: -10000px;
            left: -10000px;
            width: 800px;
            background: white;
            padding: 30px;
            font-family: Arial, sans-serif;
            z-index: 10000;
        `;

                // Conteúdo para download com estilos inline
                wrapperTemp.innerHTML = `
            <div style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 120px; height: 120px; border-radius: 12px; overflow: hidden; border: 4px solid white; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                        ${animal.foto ?
                        `<img src="${fotoUrl}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'">` :
                        `<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 48px; color: white;"><i class="fas fa-paw"></i></div>`
                    }
                    </div>
                    <div style="flex: 1;">
                        <h2 style="margin: 0 0 15px 0; font-size: 28px; font-weight: bold;">${animal.nome}</h2>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                            <div><strong>Espécie:</strong> ${animal.especie_nome}</div>
                            <div><strong>Raça:</strong> ${animal.raca || 'SRD'}</div>
                            <div><strong>Sexo:</strong> ${animal.sexo}</div>
                            <div><strong>Porte:</strong> ${animal.porte}</div>
                            <div><strong>Idade:</strong> ${calcularIdadeJS(animal.datanasc)}</div>
                            <div><strong>Tutor:</strong> ${animal.dono_nome}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Consulta -->
            <div style="background: white; border-radius: 12px; padding: 25px; border: 2px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <!-- Cabeçalho da consulta -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 24px; color: #2563eb;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: #1e293b; font-size: 20px;">${formatarData(ag.data_hora)}</h3>
                            <p style="margin: 5px 0 0 0; color: #64748b; font-size: 14px;">${ag.hora_inicio} - ${ag.hora_final}</p>
                        </div>
                    </div>
                </div>

                <!-- Profissionais -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; color: #475569;">
                        <i class="fas fa-stethoscope" style="color: #2563eb; width: 16px;"></i>
                        <span><strong>Serviço:</strong> ${ag.servico || 'Consulta Geral'}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; color: #475569;">
                        <i class="fas fa-user-md" style="color: #2563eb; width: 16px;"></i>
                        <span><strong>Veterinário:</strong> Dr(a). ${ag.veterinario_prescricao || 'Não informado'}</span>
                    </div>
                    ${ag.secretario ? `
                        <div style="display: flex; align-items: center; gap: 10px; color: #475569;">
                            <i class="fas fa-user-nurse" style="color: #2563eb; width: 16px;"></i>
                            <span><strong>Atendente:</strong> ${ag.secretario}</span>
                        </div>
                    ` : ''}
                </div>

                <!-- Campos da consulta -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    ${ag.diagnostico ? `
                        <div style="background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fas fa-notes-medical" style="color: #2563eb;"></i>
                                <strong style="color: #374151; font-size: 16px;">Diagnóstico</strong>
                            </div>
                            <div style="padding: 20px; color: #4b5563; line-height: 1.6; white-space: pre-line; font-size: 14px;">${ag.diagnostico}</div>
                        </div>
                    ` : ''}

                    ${ag.tratamento ? `
                        <div style="background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fas fa-pills" style="color: #2563eb;"></i>
                                <strong style="color: #374151; font-size: 16px;">Tratamento Prescrito</strong>
                            </div>
                            <div style="padding: 20px; color: #4b5563; line-height: 1.6; white-space: pre-line; font-size: 14px;">${ag.tratamento}</div>
                        </div>
                    ` : ''}

                    ${ag.receita ? `
                        <div style="background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fas fa-prescription" style="color: #2563eb;"></i>
                                <strong style="color: #374151; font-size: 16px;">Receita Médica</strong>
                            </div>
                            <div style="padding: 20px; color: #4b5563; line-height: 1.6; white-space: pre-line; font-size: 14px;">${ag.receita}</div>
                        </div>
                    ` : ''}

                    ${ag.obs_consulta ? `
                        <div style="background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fas fa-comment-medical" style="color: #2563eb;"></i>
                                <strong style="color: #374151; font-size: 16px;">Observações</strong>
                            </div>
                            <div style="padding: 20px; color: #4b5563; line-height: 1.6; white-space: pre-line; font-size: 14px;">${ag.obs_consulta}</div>
                        </div>
                    ` : ''}

                    ${ag.mensagem ? `
                        <div style="background: white; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #e2e8f0;">
                                <i class="fas fa-envelope" style="color: #2563eb;"></i>
                                <strong style="color: #374151; font-size: 16px;">Mensagem do Veterinário</strong>
                            </div>
                            <div style="padding: 20px; color: #4b5563; line-height: 1.6; white-space: pre-line; font-size: 14px;">${ag.mensagem}</div>
                        </div>
                    ` : ''}
                </div>
            </div>

            <!-- Rodapé -->
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                <p>Documento gerado em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR')}</p>
                <p>PetCare Sistema Veterinário</p>
            </div>
        `;

                // Adicionar ao DOM
                document.body.appendChild(wrapperTemp);

                // Aguardar um pouco para garantir que tudo carregou
                await new Promise(resolve => setTimeout(resolve, 500));

                // Configurações do html2canvas
                const canvas = await html2canvas(wrapperTemp, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    logging: false,
                    useCORS: true,
                    allowTaint: true,
                    width: wrapperTemp.scrollWidth,
                    height: wrapperTemp.scrollHeight
                });

                // Remover do DOM
                document.body.removeChild(wrapperTemp);

                // Criar e disparar o download
                const link = document.createElement('a');
                const nomeArquivo = `consulta_${animal.nome.replace(/\s+/g, '_')}_${dataConsulta.replace(/\//g, '-')}.png`;
                link.download = nomeArquivo;
                link.href = canvas.toDataURL('image/png');

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Restaurar botão
                btn.innerHTML = '<i class="fas fa-check"></i> Baixado!';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-download"></i> Baixar';
                    btn.disabled = false;
                }, 2000);

            } catch (error) {
                console.error('Erro ao gerar download:', error);
                btn.innerHTML = '<i class="fas fa-times"></i> Erro';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-download"></i> Baixar';
                    btn.disabled = false;
                }, 2000);

                // Mostrar alerta com o erro
                alert('Erro ao gerar download: ' + error.message);
            }
        }
        // Função para baixar prontuário completo
        async function baixarProntuarioCompleto() {
            const content = document.getElementById('prontuarioContent');
            const btn = document.querySelector('.btn-download-completo');

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando prontuário completo...';
            btn.disabled = true;

            try {
                // Esconder todos os botões de download individual
                const botoesIndividuais = content.querySelectorAll('.btn-download-consulta');
                botoesIndividuais.forEach(b => b.style.display = 'none');

                const canvas = await html2canvas(content, {
                    scale: 2,
                    backgroundColor: '#ffffff',
                    logging: false,
                    useCORS: true,
                    height: content.scrollHeight,
                    windowHeight: content.scrollHeight
                });

                const link = document.createElement('a');
                link.download = `prontuario_completo_${Date.now()}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();

                // Mostrar os botões novamente
                botoesIndividuais.forEach(b => b.style.display = '');

                btn.innerHTML = '<i class="fas fa-check"></i> Prontuário baixado!';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-download"></i> Baixar Prontuário Completo';
                    btn.disabled = false;
                }, 2000);

            } catch (error) {
                console.error('Erro ao gerar imagem:', error);
                const botoesIndividuais = content.querySelectorAll('.btn-download-consulta');
                botoesIndividuais.forEach(b => b.style.display = '');

                btn.innerHTML = '<i class="fas fa-times"></i> Erro ao gerar';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-download"></i> Baixar Prontuário Completo';
                    btn.disabled = false;
                }, 2000);
            }
        }
    </script>
</body>

</html>