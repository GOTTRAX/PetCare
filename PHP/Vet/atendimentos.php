<?php
session_start();
include '../conexao.php';

// Verificar se sessão existe e se é veterinário
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'Veterinario') {
    header('Location: login.php');
    exit;
}

// Filtros
$filtro_periodo = $_GET['filtro_periodo'] ?? 'todos';
$filtro_especie = $_GET['filtro_especie'] ?? 'todos';
$filtro_servico = $_GET['filtro_servico'] ?? 'todos';
$filtro_status = $_GET['filtro_status'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

// Construir WHERE dinamicamente
$where = "WHERE a.status = 'finalizado'";
$params = [];

// Filtro de período
switch ($filtro_periodo) {
    case 'hoje':
        $where .= " AND DATE(a.data_hora) = CURDATE()";
        break;
    case 'semana':
        $where .= " AND YEARWEEK(a.data_hora, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'mes':
        $where .= " AND YEAR(a.data_hora) = YEAR(CURDATE()) AND MONTH(a.data_hora) = MONTH(CURDATE())";
        break;
    case 'ano':
        $where .= " AND YEAR(a.data_hora) = YEAR(CURDATE())";
        break;
}

// Filtro por espécie
if ($filtro_especie !== 'todos') {
    $where .= " AND esp.nome = ?";
    $params[] = $filtro_especie;
}

// Filtro por serviço
if ($filtro_servico !== 'todos') {
    $where .= " AND s.id = ?";
    $params[] = $filtro_servico;
}

// Filtro por status das informações
if ($filtro_status !== 'todos') {
    if ($filtro_status === 'com_info') {
        $where .= " AND c.id IS NOT NULL";
    } elseif ($filtro_status === 'sem_info') {
        $where .= " AND c.id IS NULL";
    }
}

// Filtro por busca
if (!empty($busca)) {
    $where .= " AND (cli.nome LIKE ? OR ani.nome LIKE ? OR ani.raca LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Buscar agendamentos finalizados
$sql = "
    SELECT 
        a.id as agendamento_id,
        a.data_hora,
        a.hora_inicio,
        a.hora_final,
        a.observacoes as obs_agendamento,
        cli.id as cliente_id,
        cli.nome as cliente_nome,
        cli.telefone,
        cli.email,
        ani.foto as animal_foto,
        ani.id as animal_id,
        ani.nome as animal_nome,
        ani.datanasc as animal_nascimento,
        ani.raca,
        ani.porte,
        ani.sexo,
        esp.nome as especie,
        vet.id as veterinario_id,
        vet.nome as veterinario_nome,
        s.id as servico_id,
        s.nome as servico_nome,
        s.descricao as descricao_servico,
        c.id as consulta_id,
        c.diagnostico,
        c.tratamento,
        c.receita,
        c.observacoes as obs_consulta,
        c.data_consulta,
        c.veterinario_id as veterinario_consulta_id,
        vet_consulta.nome as veterinario_consulta_nome,
        DATE(a.data_hora) as data_agendamento
    FROM Agendamentos a
    INNER JOIN Usuarios cli ON a.cliente_id = cli.id
    INNER JOIN Animais ani ON a.animal_id = ani.id
    INNER JOIN Especies esp ON ani.especie_id = esp.id
    LEFT JOIN Usuarios vet ON a.veterinario_id = vet.id
    LEFT JOIN Servicos s ON a.servico_id = s.id
    LEFT JOIN Consultas c ON a.id = c.agendamento_id
    LEFT JOIN Usuarios vet_consulta ON c.veterinario_id = vet_consulta.id
    $where
    ORDER BY a.data_hora DESC, a.hora_inicio DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar POST para salvar/atualizar consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agendamento_id = $_POST['agendamento_id'] ?? null;
    $animal_id = $_POST['animal_id'] ?? null;
    $veterinario_id = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
    $diagnostico = $_POST['diagnostico'] ?? null;
    $tratamento = $_POST['tratamento'] ?? null;
    $receita = $_POST['receita'] ?? null;
    $observacoes = $_POST['observacoes'] ?? null;

    $success = false;
    $msg = "";

    if ($agendamento_id && $animal_id && $veterinario_id) {
        try {
            $check_sql = "SELECT id FROM Consultas WHERE agendamento_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$agendamento_id]);
            $consulta_existente = $check_stmt->fetch();

            if ($consulta_existente) {
                $update_sql = "
                    UPDATE Consultas SET 
                        diagnostico = ?, 
                        tratamento = ?, 
                        receita = ?, 
                        observacoes = ?,
                        data_consulta = NOW()
                    WHERE agendamento_id = ?
                ";
                $stmt = $pdo->prepare($update_sql);
                $stmt->execute([$diagnostico, $tratamento, $receita, $observacoes, $agendamento_id]);
                $success = true;
                $msg = "Consulta atualizada com sucesso!";
            } else {
                $insert_sql = "
                    INSERT INTO Consultas (
                        agendamento_id, animal_id, veterinario_id, 
                        diagnostico, tratamento, receita, observacoes, data_consulta
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ";
                $stmt = $pdo->prepare($insert_sql);
                $stmt->execute([
                    $agendamento_id,
                    $animal_id,
                    $veterinario_id,
                    $diagnostico,
                    $tratamento,
                    $receita,
                    $observacoes
                ]);
                $success = true;
                $msg = "Consulta criada com sucesso!";
            }
        } catch (Exception $e) {
            $msg = "Erro ao salvar consulta: " . $e->getMessage();
        }
    } else {
        $msg = "Dados incompletos para salvar consulta.";
    }

    header('Content-Type: application/json');
    echo json_encode(["success" => $success, "msg" => $msg]);
    exit;
}

// Templates de diagnósticos
$templates_diagnosticos = [
    'checkup' => 'Animal apresentou-se em bom estado geral. Exame físico dentro dos parâmetros normais. Recomendado retorno em 12 meses.',
    'vacinação' => 'Aplicação de vacina realizada com sucesso. Animal reagiu bem ao procedimento. Próxima dose agendada.',
    'cirurgia' => 'Procedimento cirúrgico realizado com sucesso. Animal em recuperação. Prescritos medicamentos para dor e inflamação.',
    'dermatite' => 'Diagnóstico de dermatite alérgica. Prescrito shampoo medicamentoso e antialérgicos. Recomendada dieta hipoalergênica.'
];

// Agrupar por data para a timeline
$agendamentos_por_data = [];
foreach ($agendamentos as $agendamento) {
    $data = date('d/m/Y', strtotime($agendamento['data_agendamento']));
    $agendamentos_por_data[$data][] = $agendamento;
}

// Buscar informações do veterinário logado
$veterinario_id = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
$veterinario_nome = "Dr. Veterinário";

if ($veterinario_id) {
    $sql_vet = "SELECT nome FROM Usuarios WHERE id = ? AND tipo_usuario = 'Veterinario'";
    $stmt_vet = $pdo->prepare($sql_vet);
    $stmt_vet->execute([$veterinario_id]);
    $veterinario = $stmt_vet->fetch();

    if ($veterinario) {
        $veterinario_nome = $veterinario['nome'];
    }
}

// Extrair as iniciais para o avatar
$iniciais = "";
$nomes = explode(' ', $veterinario_nome);
if (count($nomes) >= 2) {
    $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr($nomes[count($nomes) - 1], 0, 1));
} else {
    $iniciais = strtoupper(substr($veterinario_nome, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultas Finalizadas - Sistema Veterinário</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../Estilos/vet.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-paw logo-icon"></i>
                    <h1>VetCare System</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?= $iniciais ?></div>
                    <span>Dr. <?= htmlspecialchars($veterinario_nome) ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="page-title">
            <div>
                <i class="fas fa-clipboard-check"></i>
                Consultas Finalizadas
            </div>
            <div class="export-buttons">
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
        </div>

        <!-- Dashboard de Estatísticas -->
        <div class="dashboard-stats">
            <div class="stat-card stat-total">
                <div class="stat-number"><?= count($agendamentos) ?></div>
                <div class="stat-label">Total de Consultas</div>
            </div>
            <div class="stat-card stat-pendentes">
                <div class="stat-number">
                    <?= count(array_filter($agendamentos, function ($a) { 
                        return empty($a['consulta_id']); 
                    })) ?>
                </div>
                <div class="stat-label">Informações Pendentes</div>
            </div>
            <div class="stat-card stat-completas">
                <div class="stat-number">
                    <?= count(array_filter($agendamentos, function ($a) { 
                        return !empty($a['consulta_id']); 
                    })) ?>
                </div>
                <div class="stat-label">Completas</div>
            </div>
        </div>

        <!-- Filtros Avançados -->
        <div class="filters-container">
            <form id="filterForm" method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="busca">Buscar</label>
                        <input type="text" id="busca" name="busca" class="filter-input"
                            placeholder="Dono, animal ou raça..." value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="filtro_periodo">Período</label>
                        <select id="filtro_periodo" name="filtro_periodo" class="filter-select">
                            <option value="todos" <?= $filtro_periodo === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="hoje" <?= $filtro_periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                            <option value="semana" <?= $filtro_periodo === 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                            <option value="mes" <?= $filtro_periodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                            <option value="ano" <?= $filtro_periodo === 'ano' ? 'selected' : '' ?>>Este Ano</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filtro_especie">Espécie</label>
                        <select id="filtro_especie" name="filtro_especie" class="filter-select">
                            <option value="todos" <?= $filtro_especie === 'todos' ? 'selected' : '' ?>>Todas</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT DISTINCT nome FROM Especies ORDER BY nome ASC");
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = $filtro_especie === $row['nome'] ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($row['nome']) . "' $selected>" . htmlspecialchars($row['nome']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option disabled>Erro ao carregar espécies</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filtro_servico">Serviço</label>
                        <select id="filtro_servico" name="filtro_servico" class="filter-select">
                            <option value="todos" <?= $filtro_servico === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT id, nome FROM Servicos ORDER BY nome ASC");
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = $filtro_servico == $row['id'] ? 'selected' : '';
                                    echo "<option value='" . $row['id'] . "' $selected>" . htmlspecialchars($row['nome']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option disabled>Erro ao carregar serviços</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filtro_status">Status Info</label>
                        <select id="filtro_status" name="filtro_status" class="filter-select">
                            <option value="todos" <?= $filtro_status === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="com_info" <?= $filtro_status === 'com_info' ? 'selected' : '' ?>>Com Informações</option>
                            <option value="sem_info" <?= $filtro_status === 'sem_info' ? 'selected' : '' ?>>Sem Informações</option>
                        </select>
                    </div>
                    <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-redo"></i> Limpar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Informações da consulta salvas com sucesso!
            </div>
        <?php endif; ?>

        <!-- Timeline de Consultas -->
        <div class="timeline">
            <?php if (empty($agendamentos_por_data)): ?>
                <div class="no-consultas">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Nenhuma consulta finalizada encontrada</h3>
                    <p>Não há consultas finalizadas para os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <?php foreach ($agendamentos_por_data as $data => $agendamentos_do_dia): ?>
                    <div class="timeline-date" onclick="toggleDate('<?= $data ?>')">
                        <span><?= $data ?></span>
                        <span class="date-badge"><?= count($agendamentos_do_dia) ?> consultas</span>
                    </div>

                    <div id="date-<?= str_replace('/', '-', $data) ?>">
                        <?php foreach ($agendamentos_do_dia as $agendamento): ?>
                            <div class="appointment-card <?= $agendamento['consulta_id'] ? 'com-info' : 'sem-info' ?>"
                                onclick="openModal(<?= htmlspecialchars(json_encode($agendamento), ENT_QUOTES, 'UTF-8') ?>)">
                                <div class="appointment-header">
                                    <div class="appointment-time">
                                        <i class="far fa-clock"></i>
                                        <?= date('H:i', strtotime($agendamento['hora_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($agendamento['hora_final'])) ?>
                                    </div>
                                    <div class="appointment-status status-finalizado">
                                        Finalizado
                                    </div>
                                </div>

                                <div class="status-badges">
                                    <span class="badge <?= $agendamento['consulta_id'] ? 'badge-completo' : 'badge-pendente' ?>">
                                        <?= $agendamento['consulta_id'] ? 'Completo' : 'Pendente' ?>
                                    </span>
                                    <span class="badge badge-servico">
                                        <?= htmlspecialchars($agendamento['servico_nome']) ?>
                                    </span>
                                </div>

                                <div class="appointment-content">
                                    <!-- FOTO DO ANIMAL -->
                                    <div class="animal-photo-small">
                                        <?php if (!empty($agendamento['animal_foto'])): ?>
                                            <img src="../../assets/uploads/pets/<?= htmlspecialchars($agendamento['animal_foto']) ?>" 
                                                 alt="<?= htmlspecialchars($agendamento['animal_nome']) ?>" 
                                                 class="animal-img-small">
                                        <?php else: ?>
                                            <div class="no-photo-small">
                                                <i class="fas fa-paw"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="appointment-details-compact">
                                        <div class="detail-item-compact">
                                            <span class="detail-label-compact">Animal</span>
                                            <span class="detail-value-compact"><?= htmlspecialchars($agendamento['animal_nome']) ?></span>
                                        </div>
                                        <div class="detail-item-compact">
                                            <span class="detail-label-compact">Dono</span>
                                            <span class="detail-value-compact"><?= htmlspecialchars($agendamento['cliente_nome']) ?></span>
                                        </div>
                                        <div class="detail-item-compact">
                                            <span class="detail-label-compact">Raça</span>
                                            <span class="detail-value-compact"><?= htmlspecialchars($agendamento['raca']) ?></span>
                                        </div>
                                        <div class="detail-item-compact">
                                            <span class="detail-label-compact">Espécie</span>
                                            <span class="detail-value-compact"><?= htmlspecialchars($agendamento['especie']) ?></span>
                                        </div>

                                        <?php if ($agendamento['veterinario_consulta_nome']): ?>
                                            <div class="detail-item-compact" style="grid-column: 1 / -1;">
                                                <span class="detail-label-compact">Prontuário por</span>
                                                <span class="detail-value-compact">Dr. <?= htmlspecialchars($agendamento['veterinario_consulta_nome']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($agendamento['diagnostico'] || $agendamento['tratamento']): ?>
                                            <div class="info-preview-compact">
                                                <?php if ($agendamento['diagnostico']): ?>
                                                    <p><strong>Diagnóstico:</strong> <?= substr($agendamento['diagnostico'], 0, 60) ?>...</p>
                                                <?php endif; ?>
                                                <?php if ($agendamento['tratamento']): ?>
                                                    <p><strong>Tratamento:</strong> <?= substr($agendamento['tratamento'], 0, 60) ?>...</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="appointment-actions">
                                    <button class="btn btn-primary"
                                        onclick="event.stopPropagation(); openModal(<?= htmlspecialchars(json_encode($agendamento), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fas fa-edit"></i>
                                        <?= $agendamento['consulta_id'] ? 'Editar' : 'Adicionar' ?> Informações
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para adicionar/editar informações da consulta -->
    <div id="consultaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Informações da Consulta</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="consultaForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="agendamento_id" id="modal_agendamento_id">
                    <input type="hidden" name="animal_id" id="modal_animal_id">
                    <input type="hidden" name="veterinario_id" value="<?= $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? '' ?>">

                    <div class="appointment-info">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <!-- FOTO DO ANIMAL NO MODAL -->
                            <div class="animal-photo-large" id="modal_animal_photo">
                                <!-- A foto será carregada via JavaScript -->
                            </div>
                            <div>
                                <strong>Animal:</strong> <span id="modal_animal_nome"></span><br>
                                <strong>Dono:</strong> <span id="modal_cliente_nome"></span><br>
                                <strong>Data:</strong> <span id="modal_data_consulta"></span><br>
                                <strong>Veterinário:</strong> <span id="modal_veterinario_nome"></span>
                            </div>
                        </div>
                        <div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-radius: 4px; text-align: center;">
                            <strong>Você está editando como:</strong>
                            <span style="color: var(--primary-color); font-weight: bold;"><?= htmlspecialchars($veterinario_nome) ?></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Templates de Diagnóstico</label>
                        <div class="template-buttons">
                            <?php foreach ($templates_diagnosticos as $key => $template): ?>
                                <button type="button" class="template-btn" onclick="applyTemplate('<?= $key ?>')">
                                    <?= ucfirst($key) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="diagnostico">Diagnóstico:</label>
                        <textarea name="diagnostico" id="diagnostico" class="form-control" placeholder="Descreva o diagnóstico..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tratamento">Tratamento Prescrito:</label>
                        <textarea name="tratamento" id="tratamento" class="form-control" placeholder="Descreva o tratamento prescrito..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="receita">Receita/Medicamentos:</label>
                        <textarea name="receita" id="receita" class="form-control" placeholder="Liste os medicamentos prescritos..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="observacoes">Observações Adicionais:</label>
                        <textarea name="observacoes" id="observacoes" class="form-control" placeholder="Adicione quaisquer observações relevantes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar Informações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Templates de diagnóstico
        const templates = <?= json_encode($templates_diagnosticos) ?>;

        function openModal(agendamento) {
            console.log('Abrindo modal para:', agendamento);

            // Preencher os campos hidden
            document.getElementById('modal_agendamento_id').value = agendamento.agendamento_id;
            document.getElementById('modal_animal_id').value = agendamento.animal_id;

            // Atualizar a foto do animal no modal
            const photoContainer = document.getElementById('modal_animal_photo');
            if (agendamento.animal_foto) {
                photoContainer.innerHTML = `
                    <img src="../../assets/uploads/pets/${agendamento.animal_foto}" 
                         alt="${agendamento.animal_nome}" 
                         class="animal-img-large">
                `;
            } else {
                photoContainer.innerHTML = `
                    <div class="no-photo-large">
                        <i class="fas fa-paw"></i>
                    </div>
                `;
            }

            // Preencher campos de visualização
            document.getElementById('modal_animal_nome').textContent = agendamento.animal_nome + ' (' + agendamento.especie + ')';
            document.getElementById('modal_cliente_nome').textContent = agendamento.cliente_nome;
            document.getElementById('modal_veterinario_nome').textContent = "<?= htmlspecialchars($veterinario_nome) ?>";

            // Formatar data
            const dataHora = agendamento.data_hora + ' ' + agendamento.hora_inicio;
            const data = new Date(dataHora.replace(' ', 'T'));
            document.getElementById('modal_data_consulta').textContent = data.toLocaleString('pt-BR');

            // Preencher textareas
            document.getElementById('diagnostico').value = agendamento.diagnostico || '';
            document.getElementById('tratamento').value = agendamento.tratamento || '';
            document.getElementById('receita').value = agendamento.receita || '';
            document.getElementById('observacoes').value = agendamento.obs_consulta || '';

            // Mostrar modal
            document.getElementById('consultaModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('consultaModal').style.display = 'none';
        }

        // Aplicar template de diagnóstico
        function applyTemplate(templateKey) {
            if (templates[templateKey]) {
                document.getElementById('diagnostico').value = templates[templateKey];
            }
        }

        // Alternar visibilidade de consultas por data
        function toggleDate(date) {
            const dateId = 'date-' + date.replace(/\//g, '-');
            const element = document.getElementById(dateId);
            if (element.style.display === 'none') {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        }

        // Limpar filtros
        function clearFilters() {
            document.getElementById('filterForm').reset();
            document.getElementById('filterForm').submit();
        }

        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('consultaModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Manipular envio do formulário com AJAX
        document.getElementById('consultaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.msg);
                    closeModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Erro: ' + data.msg);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar consulta.');
            });
        });

        // Busca em tempo real
        let searchTimeout;
        document.getElementById('busca').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>
</body>
</html>