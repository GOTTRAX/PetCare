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
    // Buscar espécies
    $stmt = $pdo->query("SELECT id, nome FROM Especies ORDER BY nome");
    $especies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar usuários (clientes) com contagem de pets e foto do primeiro pet
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
    
    // Se um animal específico foi selecionado, buscar seus agendamentos
    if (isset($_GET['animal_id'])) {
        $animal_id = (int)$_GET['animal_id'];
        
        // Buscar dados do animal
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   e.nome as especie_nome, 
                   u.nome as dono_nome, 
                   u.email as dono_email, 
                   u.telefone as dono_telefone
            FROM Animais a
            INNER JOIN Especies e ON a.especie_id = e.id
            INNER JOIN Usuarios u ON a.usuario_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$animal_id]);
        $animal_selecionado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar agendamentos do animal
        $stmt = $pdo->prepare("
            SELECT ag.*, 
                   s.nome AS servico, 
                   v.nome AS veterinario
            FROM Agendamentos ag
            LEFT JOIN Servicos s ON s.id = ag.servico_id
            LEFT JOIN Usuarios v ON v.id = ag.veterinario_id
            WHERE ag.animal_id = ?
            ORDER BY ag.data_hora DESC, ag.hora_inicio DESC
        ");
        $stmt->execute([$animal_id]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
</head>
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
                    // Pegar a primeira foto do pet (se houver)
                    $primeiraFoto = null;
                    if (!empty($usuario['pet_fotos'])) {
                        $fotos = explode(',', $usuario['pet_fotos']);
                        $primeiraFoto = $fotos[0]; // Primeira foto
                    }
                ?>
                <button class="client-item" data-client-id="<?= $usuario['id'] ?>">
                    <div class="client-avatar">
                        
                            <?php
                                $partes = explode(' ', $usuario['nome']);
                                $iniciais = strtoupper($partes[0][0]);
                                if (isset($partes[1])) $iniciais .= strtoupper($partes[1][0]);
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
                        <div class="animal-card" 
                             data-animal-id="<?= $animal['id'] ?>"
                             data-especie="<?= $animal['especie_id'] ?>"
                             data-sexo="<?= $animal['sexo'] ?>"
                             data-porte="<?= $animal['porte'] ?>"
                             data-dono="<?= $animal['dono_id'] ?>"
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

    <!-- Modal de Detalhes do Animal -->
    <div class="modal" id="animalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Detalhes do Animal</h2>
                <button class="btn-close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Dados dos animais
        const animaisData = <?= json_encode($animais) ?>;
        const clientesData = <?= json_encode($usuarios) ?>;
        
        // Variáveis de controle
        let filtroClienteAtivo = 'all';
        
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
            e.stopPropagation(); // Prevenir propagação do evento
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
            
            const icon = collapseSidebar.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
                // Adicionar botão para reabrir quando colapsado
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
                // Remover active de todos
                document.querySelectorAll('.client-item').forEach(i => i.classList.remove('active'));
                // Adicionar active no clicado
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
        
        // Função para visualizar detalhes do animal
        function viewAnimalDetails(animalId) {
            const animal = animaisData.find(a => a.id == animalId);
            if (!animal) return;
            
            const modal = document.getElementById('animalModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.textContent = `Prontuário - ${animal.nome}`;
            
            const fotoUrl = animal.foto 
                ? `../../assets/uploads/pets/${animal.foto}`
                : '';
            
            modalBody.innerHTML = `
                <div class="animal-details-grid">
                    <div class="details-photo">
                        ${animal.foto ? 
                            `<img src="${fotoUrl}" alt="${animal.nome}">` :
                            `<div class="placeholder-photo"><i class="fas fa-paw"></i></div>`
                        }
                    </div>
                    <div class="details-info">
                        <h3>${animal.nome}</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="fas fa-dna"></i>
                                <div>
                                    <label>Espécie</label>
                                    <span>${animal.especie_nome}</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-tag"></i>
                                <div>
                                    <label>Raça</label>
                                    <span>${animal.raca || 'SRD'}</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-${animal.sexo == 'Macho' ? 'mars' : 'venus'}"></i>
                                <div>
                                    <label>Sexo</label>
                                    <span>${animal.sexo}</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-weight"></i>
                                <div>
                                    <label>Porte</label>
                                    <span>${animal.porte}</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-birthday-cake"></i>
                                <div>
                                    <label>Idade</label>
                                    <span>${calcularIdadeJS(animal.datanasc)}</span>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <label>Tutor</label>
                                    <span>${animal.dono_nome}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-info">
                            <h4><i class="fas fa-address-book"></i> Contato do Tutor</h4>
                            <p><i class="fas fa-envelope"></i> ${animal.dono_email}</p>
                            <p><i class="fas fa-phone"></i> ${animal.dono_telefone || 'Não informado'}</p>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="animais.php?animal_id=${animal.id}" class="btn-primary">
                                <i class="fas fa-file-medical"></i>
                                Ver Prontuário Completo
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.add('show');
        }
        
        // Função para fechar modal
        function closeModal() {
            document.getElementById('animalModal').classList.remove('show');
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('animalModal').addEventListener('click', (e) => {
            if (e.target.id === 'animalModal') {
                closeModal();
            }
        });
        
        // Função auxiliar para calcular idade em JS
        function calcularIdadeJS(dataNasc) {
            if (!dataNasc) return 'N/A';
            const hoje = new Date();
            const nascimento = new Date(dataNasc);
            let idade = hoje.getFullYear() - nascimento.getFullYear();
            const mes = hoje.getMonth() - nascimento.getMonth();
            
            if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                idade--;
            }
            
            return `${idade} ano(s)`;
        }
        
        // Fechar modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>