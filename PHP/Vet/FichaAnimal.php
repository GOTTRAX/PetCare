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
    <link rel="stylesheet" href="../../Estilos/vet.css">
</head>
<body>
    <button class="btn btn-primary theme-toggle" id="themeToggle" aria-label="Alternar entre modo claro e escuro">
        <svg class="theme-icon" id="themeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16">
            <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
            <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
        </svg>
        Alternar Tema
    </button>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Sistema Veterinário</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menu de navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list me-1" viewBox="0 0 16 16">
                                <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/>
                                <path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z"/>
                            </svg>
                            Fichas dos Animais
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../animais.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard2-data me-1" viewBox="0 0 16 16">
                                <path d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2a.5.5 0 0 1-.5.5h-5A.5.5 0 0 1 5 2v-.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 .5-.5h3Z"/>
                                <path d="M3 2.5a.5.5 0 0 1 .5-.5H4a.5.5 0 0 0 0-1h-.5A1.5 1.5 0 0 0 2 2.5v12A1.5 1.5 0 0 0 3.5 16h9a1.5 1.5 0 0 0 1.5-1.5v-12A1.5 1.5 0 0 0 12.5 1H12a.5.5 0 0 0 0 1h.5a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-12Z"/>
                                <path d="M10 7a1 1 0 1 1 2 0v5a1 1 0 1 1-2 0V7Zm-6 4a1 1 0 1 1 2 0v1a1 1 0 1 1-2 0v-1Zm4-3a1 1 0 0 0-1 1v3a1 1 0 0 0 2 0V9a1 1 0 0 0-1-1Z"/>
                            </svg>
                            Prontuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendario.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-event me-1" viewBox="0 0 16 16">
                                <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                            </svg>
                            Agendamentos
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        <h1 class="mb-4">Fichas dos Animais</h1>

        <!-- Filtros -->
        <section class="filter-section">
            <div class="row g-3">
                <div class="col-md-3 col-sm-6">
                    <label for="sexoFilter" class="form-label">Filtrar por Sexo</label>
                    <select class="form-select" id="sexoFilter">
                        <option value="todos">Todos</option>
                        <option value="Macho">Machos</option>
                        <option value="Fêmea">Fêmeas</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="especieFilter" class="form-label">Filtrar por Espécie</label>
                    <select class="form-select" id="especieFilter">
                        <option value="todos">Todas</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="donoFilter" class="form-label">Filtrar por Dono</label>
                    <select class="form-select" id="donoFilter">
                        <option value="todos">Todos</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 d-flex align-items-end">
                    <button class="btn btn-success w-100" id="limparFiltros">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise me-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/>
                        </svg>
                        Limpar Filtros
                    </button>
                </div>
            </div>
        </section>

        <!-- Gráficos -->
        <section class="row mt-4 g-3">
            <div class="col-lg-6 col-md-12">
                <div class="chart-container">
                    <h5>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-gender-ambiguous me-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M11.5 1a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0V1.707l-2.91 2.91a.5.5 0 0 1-.686-.687L10.293 1H8.5a.5.5 0 0 1 0-1h3a.5.5 0 0 1 .5.5M11 7.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0V8.707l-3.45 3.45a.5.5 0 0 1-.707-.707L13.293 8H11.5a.5.5 0 0 1-.5-.5"/>
                            <path fill-rule="evenodd" d="M5.5 13.5A3.5 3.5 0 1 1 2 10a3.5 3.5 0 0 1 3.5 3.5m-1-3.5a1 1 0 1 0 2 0 1 1 0 0 0-2 0"/>
                        </svg>
                        Distribuição por Sexo
                    </h5>
                    <canvas id="sexoChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="chart-container">
                    <h5>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-diagram-3 me-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H14a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 2 7h5.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM0 11.5A1.5 1.5 0 0 1 1.5 10h1A1.5 1.5 0 0 1 4 11.5v1A1.5 1.5 0 0 1 2.5 14h-1A1.5 1.5 0 0 1 0 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5A1.5 1.5 0 0 1 7.5 10h1a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 8.5 14h-1A1.5 1.5 0 0 1 6 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/>
                        </svg>
                        Distribuição por Espécie
                    </h5>
                    <canvas id="especieChart"></canvas>
                </div>
            </div>
        </section>

        <!-- Cards dos Animais -->
        <section class="row mt-4 g-3" id="animaisContainer">
            <!-- Os cards serão preenchidos via JavaScript -->
        </section>
    </main>

    <!-- Modal para visualizar prontuário -->
    <div class="modal fade" id="prontuarioModal" tabindex="-1" aria-labelledby="prontuarioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="prontuarioModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-file-medical me-2" viewBox="0 0 16 16">
                            <path d="M8.5 4.5a.5.5 0 0 0-1 0v.634l-.549-.317a.5.5 0 1 0-.5.866L7 6l-.549.317a.5.5 0 1 0 .5.866l.549-.317V7.5a.5.5 0 0 0 1 0v-.634l.549.317a.5.5 0 1 0 .5-.866L9 6l.549-.317a.5.5 0 1 0-.5-.866l-.549.317V4.5zM5.5 9a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 2a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>
                            <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/>
                        </svg>
                        Prontuário do Animal
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
            if (!dataNasc) return 'Idade não informada';
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

        // Renderizar cards dos animais com animação otimizada
        function renderizarAnimais(animaisFiltrados = animais) {
            const container = document.getElementById('animaisContainer');
            const fragment = document.createDocumentFragment();
            
            if (animaisFiltrados.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-search text-muted mb-3" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                        <h4 class="text-muted">Nenhum animal encontrado</h4>
                        <p class="text-muted">Tente ajustar os filtros para ver mais resultados.</p>
                    </div>
                `;
                return;
            }
            animaisFiltrados.forEach((animal, index) => {
                const idade = calcularIdade(animal.datanasc);
                const fotoUrl = animal.foto 
                    ? '../../assets/ploads/pets/' + animal.foto 
                    : 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60';

                const card = document.createElement('div');
                card.className = 'col-lg-4 col-md-6 col-sm-12';
                card.innerHTML = `
                    <div class="card animal-card">
                        <img src="${fotoUrl}" class="animal-img card-img-top img-fluid" alt="Foto de ${animal.nome}, um ${animal.especie_nome}" loading="lazy">
                        <div class="card-body">
                            <h5 class="card-title">${animal.nome}</h5>
                            <p class="card-text">
                                <strong>Espécie:</strong> ${animal.especie_nome}<br>
                                <strong>Raça:</strong> ${animal.raca || 'Não informada'}<br>
                                <strong>Idade:</strong> ${idade} anos<br>
                                <strong>Porte:</strong> ${animal.porte || 'Não informado'}<br>
                                <strong>Sexo:</strong> ${animal.sexo || 'Não informado'}<br>
                                <strong>Dono:</strong> ${animal.dono_nome}
                            </p>
                            <button class="btn btn-primary btn-sm ver-prontuario" data-animal-id="${animal.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-medical me-1" viewBox="0 0 16 16">
                                    <path d="M8.5 4.5a.5.5 0 0 0-1 0v.634l-.549-.317a.5.5 0 1 0-.5.866L7 6l-.549.317a.5.5 0 1 0 .5.866l.549-.317V7.5a.5.5 0 0 0 1 0v-.634l.549.317a.5.5 0 1 0 .5-.866L9 6l.549-.317a.5.5 0 1 0-.5-.866l-.549.317V4.5zM5.5 9a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 2a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>
                                    <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/>
                                </svg>
                                Ver Prontuário
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

        // Mostrar prontuário no modal com caminho de imagem corrigido
        function mostrarProntuario(animalId) {
            const animal = animais.find(a => a.id == animalId);
            const idade = calcularIdade(animal.datanasc);
            
            const fotoUrl = animal.foto && animal.foto.trim() !== ''
                ? '../../assets/uploads/pets/' + animal.foto
                : 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60';
            
            console.log('Caminho da imagem no modal:', fotoUrl);

            const prontuarioContent = document.getElementById('prontuarioContent');
            prontuarioContent.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4">
                        <img src="${fotoUrl}" class="img-fluid rounded" alt="Foto de ${animal.nome}, um ${animal.especie_nome}" loading="lazy" style="max-height: 250px; object-fit: cover; width: 100%;">
                    </div>
                    <div class="col-md-8">
                        <h4>${animal.nome}</h4>
                        <p><strong>Espécie:</strong> ${animal.especie_nome}</p>
                        <p><strong>Raça:</strong> ${animal.raca || 'Não informada'}</p>
                        <p><strong>Data de Nascimento:</strong> ${formatarData(animal.datanasc)} (${idade} anos)</p>
                        <p><strong>Porte:</strong> ${animal.porte || 'Não informado'}</p>
                        <p><strong>Sexo:</strong> ${animal.sexo || 'Não informado'}</p>
                        <p><strong>Dono:</strong> ${animal.dono_nome}</p>
                        <p><strong>Contato:</strong> ${animal.email} | ${animal.telefone}</p>
                    </div>
                </div>
                <hr>
                <h5>Histórico de Consultas</h5>
                ${animal.historico && animal.historico.length > 0 ? 
                    animal.historico.map((consulta, index) => `
                        <div class="card mb-2" style="animation-delay: ${index * 0.05}s;">
                            <div class="card-body">
                                <h6 class="card-title">${formatarData(consulta.data)} - ${consulta.tipo}</h6>
                                <p class="card-text">${consulta.descricao}</p>
                            </div>
                        </div>
                    `).join('') : 
                    '<p>Nenhum histórico de consulta registrado.</p>'
                }
                <div class="mt-3">
                    <h6>Adicionar nova observação</h6>
                    <textarea class="form-control" id="novaObservacao" rows="3" placeholder="Registrar nova observação no prontuário..."></textarea>
                    <button class="btn btn-success mt-2 salvar-observacao" data-animal-id="${animalId}">Salvar Observação</button>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('prontuarioModal'));
            modal.show();

            // Adicionar evento ao botão de salvar observação
            document.querySelector('.salvar-observacao').addEventListener('click', function() {
                const observacao = document.getElementById('novaObservacao').value.trim();
                const button = this;
                const originalText = button.innerHTML;
                if (!observacao) {
                    alert('Por favor, insira uma observação antes de salvar.');
                    return;
                }

                // Mostrar spinner
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...';
                button.disabled = true;

                fetch('salvar_observacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        animal_id: animalId,
                        observacao: observacao,
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    if (data.success) {
                        alert('Observação salva com sucesso!');
                        animal.historico.push({
                            data: new Date().toISOString(),
                            tipo: 'Observação',
                            descricao: observacao,
                        });
                        modal.hide();
                    } else {
                        alert('Erro ao salvar observação: ' + data.message);
                    }
                })
                .catch(error => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    console.error('Erro:', error);
                    alert('Ocorreu um erro ao salvar a observação.');
                });
            });
        }

        // Criar gráficos com animação otimizada
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
                        backgroundColor: ['#82c7ff', '#fcb2ff'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 400, // Reduzido para performance
                        easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
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
                        label: 'Quantidade por Espécie',
                        data: Object.values(especieCount),
                        backgroundColor: ['#08b854', '#fcff52', '#7020b2', '#93e6ff'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    },
                    animation: {
                        duration: 600, // Reduzido para performance
                        easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
                    }
                }
            });
        }

        // Aplicar filtros com debounce
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

        // Inicializar a página
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar preferência de tema do localStorage
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                document.getElementById('themeIcon').outerHTML = `
                    <svg class="theme-icon" id="themeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sun-fill" viewBox="0 0 16 16">
                        <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707z"/>
                    </svg>
                `;
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

            // Toggle dark/light mode with icon change and localStorage
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            themeToggle.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
                if (document.body.classList.contains('dark-mode')) {
                    themeIcon.outerHTML = `
                        <svg class="theme-icon" id="themeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sun-fill" viewBox="0 0 16 16">
                            <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707z"/>
                        </svg>
                    `;
                } else {
                    themeIcon.outerHTML = `
                        <svg class="theme-icon" id="themeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-moon-stars-fill" viewBox="0 0 16 16">
                            <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>
                            <path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/>
                        </svg>
                    `;
                }
            });
        });
    </script>
</body>
</html>