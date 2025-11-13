<?php 
session_start(); 
include("../conexao.php");

// Buscar dados do banco
$especies = [];
$usuarios = [];
$animais = [];

try {
    // Buscar espécies
    $stmt = $pdo->query("SELECT id, nome FROM Especies");
    $especies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar usuários (donos)
    $stmt = $pdo->query("SELECT id, nome, email, telefone FROM Usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar animais com informações completas
    $stmt = $pdo->query("
        SELECT a.*, e.nome as especie_nome, u.nome as dono_nome, u.email, u.telefone 
        FROM Animais a 
        INNER JOIN Especies e ON a.especie_id = e.id 
        INNER JOIN Usuarios u ON a.usuario_id = u.id
    ");
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada animal, buscar seu histórico de consultas
    foreach ($animais as &$animal) {
        $stmt = $pdo->prepare("
            SELECT c.data_consulta as data, 'Consulta' as tipo, 
                   COALESCE(c.diagnostico, 'Consulta realizada') as descricao
            FROM Consultas c 
            WHERE c.animal_id = :animal_id
            UNION
            SELECT a.data_hora as data, 'Agendamento' as tipo, 
                   CONCAT('Agendamento - ', a.status) as descricao
            FROM Agendamentos a 
            WHERE a.animal_id = :animal_id
            ORDER BY data DESC
        ");
        $stmt->execute(['animal_id' => $animal['id']]);
        $animal['historico'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Sistema Veterinário - Fichas dos Animais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../Estilos/vet.css">
</head>
<body>
    <button class="btn btn-primary theme-toggle" id="themeToggle" aria-label="Alternar entre modo claro e escuro">
        <svg class="theme-icon" id="themeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16">
            <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
            <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
        </svg>
    </button>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-paw me-2"></i>Sistema Veterinário
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menu de navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-list me-1"></i>Fichas dos Animais
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../animais.php">
                            <i class="fas fa-chart-bar me-1"></i>Prontuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendario.php">
                            <i class="fas fa-calendar me-1"></i>Agendamentos
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        <!-- Título -->
        <div class="page-title mt-4">
            <div>
                <i class="fas fa-paw"></i>Fichas dos Animais
            </div>
        </div>

        <!-- Container Principal com Layout em 2 Colunas -->
        <div class="ficha-animal-container">
            
            <!-- Coluna Esquerda - Conteúdo Principal -->
            <div class="ficha-animais-content">
                
                <!-- Filtros -->
                <section class="ficha-filtros-section">
                    <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros</h6>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label for="sexoFilter" class="form-label">Sexo</label>
                            <select class="form-select form-select-sm" id="sexoFilter">
                                <option value="todos">Todos</option>
                                <option value="Macho">Machos</option>
                                <option value="Fêmea">Fêmeas</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label for="especieFilter" class="form-label">Espécie</label>
                            <select class="form-select form-select-sm" id="especieFilter">
                                <option value="todos">Todas</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label for="donoFilter" class="form-label">Dono</label>
                            <select class="form-select form-select-sm" id="donoFilter">
                                <option value="todos">Todos</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-secondary mt-3 w-100" id="limparFiltros">
                        <i class="fas fa-redo me-1"></i>Limpar Filtros
                    </button>
                </section>

                <!-- Grid de Animais -->
                <section>
                    <div class="ficha-animais-grid" id="animaisContainer">
                        <!-- Os cards serão preenchidos via JavaScript -->
                    </div>
                </section>
            </div>

            <!-- Coluna Direita - Gráficos Lateral -->
            <aside class="ficha-graficos-sidebar">
                <div class="chart-container">
                    <h5>
                        <i class="fas fa-venus-mars"></i>
                        <span>Por Sexo</span>
                    </h5>
                    <canvas id="sexoChart"></canvas>
                </div>
                <div class="chart-container">
                    <h5>
                        <i class="fas fa-dog"></i>
                        <span>Por Espécie</span>
                    </h5>
                    <canvas id="especieChart"></canvas>
                </div>
            </aside>
        </div>
    </main>

    <!-- Modal para visualizar prontuário -->
    <div class="modal fade" id="prontuarioModal" tabindex="-1" aria-labelledby="prontuarioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="prontuarioModalLabel">
                        <i class="fas fa-file-medical me-2"></i>Prontuário do Animal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="prontuarioContent">
                    <!-- Conteúdo do prontuário será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Converter dados PHP para JavaScript
        const especies = <?php echo json_encode($especies); ?>;
        const usuarios = <?php echo json_encode($usuarios); ?>;
        const animais = <?php echo json_encode($animais); ?>;

        // Função de debounce para otimizar eventos
        function debounce(func, delay) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func(...args), delay);
            };
        }

        // Preencher filtros
        function preencherFiltros() {
            const especieFilter = document.getElementById('especieFilter');
            especies.forEach(especie => {
                const option = document.createElement('option');
                option.value = especie.id;
                option.textContent = especie.nome;
                especieFilter.appendChild(option);
            });

            const donoFilter = document.getElementById('donoFilter');
            usuarios.forEach(usuario => {
                const option = document.createElement('option');
                option.value = usuario.id;
                option.textContent = usuario.nome;
                donoFilter.appendChild(option);
            });
        }

        // Calcular idade a partir da data de nascimento
        function calcularIdade(dataNasc) {
            if (!dataNasc) return 'N/A';
            const hoje = new Date();
            const nascimento = new Date(dataNasc);
            let idade = hoje.getFullYear() - nascimento.getFullYear();
            const mes = hoje.getMonth() - nascimento.getMonth();
            
            if (mes < 0 || (mes === 0 && hoje.getDate() < nascimento.getDate())) {
                idade--;
            }
            
            return idade;
        }

        // Formatar data para exibição
        function formatarData(data) {
            if (!data) return 'N/A';
            return new Date(data).toLocaleDateString('pt-BR');
        }

        // Renderizar cards dos animais
        function renderizarAnimais(animaisFiltrados = animais) {
            const container = document.getElementById('animaisContainer');
            const fragment = document.createDocumentFragment();
            
            if (animaisFiltrados.length === 0) {
                container.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem 1rem; color: var(--text-light);">
                        <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem; display: block;"></i>
                        <h5>Nenhum animal encontrado</h5>
                        <p>Tente ajustar os filtros para ver mais resultados.</p>
                    </div>
                `;
                return;
            }

            animaisFiltrados.forEach((animal) => {
                const idade = calcularIdade(animal.datanasc);
                const fotoUrl = animal.foto 
                    ? '../../assets/uploads/pets/' + animal.foto 
                    : 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60';

                const card = document.createElement('div');
                card.innerHTML = `
                    <div class="card animal-card">
                        <img src="${fotoUrl}" class="animal-img" alt="${animal.nome}" loading="lazy">
                        <div class="card-body">
                            <h6 class="card-title">${animal.nome}</h6>
                            <p class="card-text">
                                <small>
                                    <strong>Raça:</strong> ${animal.raca || 'N/A'}<br>
                                    <strong>Idade:</strong> ${idade}a<br>
                                    <strong>Dono:</strong> ${animal.dono_nome}
                                </small>
                            </p>
                            <button class="btn btn-primary btn-sm w-100 ver-prontuario" data-animal-id="${animal.id}">
                                <i class="fas fa-file-medical me-1"></i>Ver
                            </button>
                        </div>
                    </div>
                `;
                fragment.appendChild(card);
            });
            
            container.innerHTML = '';
            container.appendChild(fragment);
            
            document.querySelectorAll('.ver-prontuario').forEach(btn => {
                btn.addEventListener('click', function() {
                    const animalId = this.getAttribute('data-animal-id');
                    mostrarProntuario(animalId);
                });
            });
        }

        // Mostrar prontuário no modal
        function mostrarProntuario(animalId) {
            const animal = animais.find(a => a.id == animalId);
            const idade = calcularIdade(animal.datanasc);
            
            const fotoUrl = animal.foto && animal.foto.trim() !== ''
                ? '../../assets/uploads/pets/' + animal.foto
                : 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60';

            const prontuarioContent = document.getElementById('prontuarioContent');
            prontuarioContent.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <img src="${fotoUrl}" class="img-fluid rounded" alt="${animal.nome}" loading="lazy" style="max-height: 250px; object-fit: cover; width: 100%;">
                    </div>
                    <div class="col-md-8">
                        <h5>${animal.nome}</h5>
                        <p><strong>Espécie:</strong> ${animal.especie_nome}</p>
                        <p><strong>Raça:</strong> ${animal.raca || 'N/A'}</p>
                        <p><strong>Data de Nascimento:</strong> ${formatarData(animal.datanasc)} (${idade} anos)</p>
                        <p><strong>Porte:</strong> ${animal.porte || 'N/A'}</p>
                        <p><strong>Sexo:</strong> ${animal.sexo || 'N/A'}</p>
                        <p><strong>Dono:</strong> ${animal.dono_nome}</p>
                        <p><strong>Contato:</strong> ${animal.email} | ${animal.telefone}</p>
                    </div>
                </div>
                <hr>
                <h6>Histórico de Consultas</h6>
                ${animal.historico && animal.historico.length > 0 ? 
                    animal.historico.map((consulta) => `
                        <div class="card mb-2">
                            <div class="card-body py-2">
                                <p class="mb-1"><small><strong>${formatarData(consulta.data)} - ${consulta.tipo}</strong></small></p>
                                <p class="mb-0"><small>${consulta.descricao}</small></p>
                            </div>
                        </div>
                    `).join('') : 
                    '<p><small>Nenhum histórico registrado.</small></p>'
                }
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('prontuarioModal'));
            modal.show();
        }

        // Criar gráficos
        function criarGraficos() {
            const sexoCount = {
                'Macho': animais.filter(a => a.sexo === 'Macho').length,
                'Fêmea': animais.filter(a => a.sexo === 'Fêmea').length
            };
            
            const sexoCtx = document.getElementById('sexoChart').getContext('2d');
            new Chart(sexoCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Machos', 'Fêmeas'],
                    datasets: [{
                        data: [sexoCount['Macho'], sexoCount['Fêmea']],
                        backgroundColor: ['#3498db', '#eb71aeff'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } }
                    }
                }
            });
            
            const especieCount = {};
            especies.forEach(especie => {
                especieCount[especie.nome] = animais.filter(a => a.especie_id == especie.id).length;
            });
            
            const especieCtx = document.getElementById('especieChart').getContext('2d');
            new Chart(especieCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(especieCount),
                    datasets: [{
                        label: 'Qtd',
                        data: Object.values(especieCount),
                        backgroundColor: '#1e7e34',
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 } }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        // Aplicar filtros
        const aplicarFiltros = debounce(function() {
            const sexoVal = document.getElementById('sexoFilter').value;
            const especieVal = document.getElementById('especieFilter').value;
            const donoVal = document.getElementById('donoFilter').value;
            
            let animaisFiltrados = animais;
            
            if (sexoVal !== 'todos') {
                animaisFiltrados = animaisFiltrados.filter(a => a.sexo === sexoVal);
            }
            
            if (especieVal !== 'todos') {
                animaisFiltrados = animaisFiltrados.filter(a => a.especie_id == especieVal);
            }
            
            if (donoVal !== 'todos') {
                animaisFiltrados = animaisFiltrados.filter(a => a.usuario_id == donoVal);
            }
            
            renderizarAnimais(animaisFiltrados);
        }, 300);

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Dark mode
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
            }

            preencherFiltros();
            renderizarAnimais();
            criarGraficos();
            
            document.getElementById('sexoFilter').addEventListener('change', aplicarFiltros);
            document.getElementById('especieFilter').addEventListener('change', aplicarFiltros);
            document.getElementById('donoFilter').addEventListener('change', aplicarFiltros);
            
            document.getElementById('limparFiltros').addEventListener('click', function() {
                document.getElementById('sexoFilter').value = 'todos';
                document.getElementById('especieFilter').value = 'todos';
                document.getElementById('donoFilter').value = 'todos';
                renderizarAnimais();
            });

            // Toggle dark/light mode
            const themeToggle = document.getElementById('themeToggle');
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
            });
        });
    </script>
</body>
</html>