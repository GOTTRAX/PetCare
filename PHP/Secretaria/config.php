<?php
session_start();
$paginaTitulo = "Configurações";
include("../conexao.php"); // $pdo

// ====== CONSTANTES E CONFIGURAÇÕES ======
define('DIAS_SEMANA', ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']);
define('HORARIO_PADRAO_ABERTURA', '08:00');
define('HORARIO_PADRAO_FECHAMENTO', '18:00');
define('HORARIO_PADRAO_ALMOCO_INICIO', '12:00');
define('HORARIO_PADRAO_ALMOCO_FIM', '13:00');

// ====== FUNÇÕES AUXILIARES ======
function set_flash($msg, $tipo = 'success')
{
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}

function get_flash()
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function sanitizar_input($data)
{
    return trim(htmlspecialchars($data ?? ''));
}

function validar_data($data)
{
    return DateTime::createFromFormat('Y-m-d', $data) !== false;
}

// Configurar tratamento de erros
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ====== CARREGAR DADOS DO BANCO ======
function carregarHorariosClinica($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM Dias_Trabalhados ORDER BY 
            CASE dia_semana 
                WHEN 'Segunda' THEN 1
                WHEN 'Terça' THEN 2
                WHEN 'Quarta' THEN 3
                WHEN 'Quinta' THEN 4
                WHEN 'Sexta' THEN 5
                WHEN 'Sábado' THEN 6
                WHEN 'Domingo' THEN 7
            END");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function carregarDadosTabela($pdo, $tabela, $ordenacao = 'nome')
{
    try {
        $stmt = $pdo->query("SELECT * FROM $tabela ORDER BY $ordenacao");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// ====== FUNÇÃO GET HORARIOS DISPONIVEIS ======
function getHorariosDisponiveis($data, $pdo, $horarios_por_dia)
{
    if (!validar_data($data) || $data < date('Y-m-d')) {
        return [];
    }

    $dia_semana = date('N', strtotime($data));
    $nomes_dias = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    $dia_nome = $nomes_dias[$dia_semana];

    if (!isset($horarios_por_dia[$dia_nome])) {
        return [];
    }

    $horario_dia = $horarios_por_dia[$dia_nome];
    $horarios_disponiveis = [];

    try {
        // Verificar agendamentos existentes
        $stmt = $pdo->prepare("SELECT hora_inicio FROM Agendamentos WHERE data_hora = ? AND status != 'cancelado'");
        $stmt->execute([$data]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $horarios_ocupados = array_column($agendamentos, 'hora_inicio');
        $horarios_ocupados = array_flip($horarios_ocupados);

        // Gerar horários disponíveis
        $inicio = new DateTime($horario_dia['horario_abertura']);
        $fim = new DateTime($horario_dia['horario_fechamento']);

        while ($inicio < $fim) {
            $hora_atual = $inicio->format('H:i');

            // Verificar se está no horário de almoço
            $em_almoco = false;
            if (!empty($horario_dia['horario_almoco_inicio']) && !empty($horario_dia['horario_almoco_fim'])) {
                $almoco_inicio = new DateTime($horario_dia['horario_almoco_inicio']);
                $almoco_fim = new DateTime($horario_dia['horario_almoco_fim']);
                $hora_atual_obj = new DateTime($hora_atual);

                if ($hora_atual_obj >= $almoco_inicio && $hora_atual_obj < $almoco_fim) {
                    $em_almoco = true;
                }
            }

            if (!isset($horarios_ocupados[$hora_atual]) && !$em_almoco) {
                $horarios_disponiveis[] = $hora_atual;
            }

            $inicio->modify('+1 hour');
        }

        return $horarios_disponiveis;
    } catch (Exception $e) {
        return [];
    }
}

// ====== CRUD GENÉRICO ======
function executarAcaoCRUD($pdo, $acao, $dados)
{
    $acoes = [
        'salvar_horarios' => function ($pdo, $dados) {
            try {
                $pdo->beginTransaction();
                $pdo->query("DELETE FROM Dias_Trabalhados");

                foreach (DIAS_SEMANA as $dia) {
                    $ativo = isset($dados['ativo'][$dia]) ? 1 : 0;
                    $abertura = sanitizar_input($dados['abertura'][$dia] ?? HORARIO_PADRAO_ABERTURA);
                    $fechamento = sanitizar_input($dados['fechamento'][$dia] ?? HORARIO_PADRAO_FECHAMENTO);
                    $tem_almoco = isset($dados['tem_almoco'][$dia]) ? 1 : 0;
                    $almoco_inicio = $tem_almoco ? sanitizar_input($dados['almoco_inicio'][$dia] ?? HORARIO_PADRAO_ALMOCO_INICIO) : NULL;
                    $almoco_fim = $tem_almoco ? sanitizar_input($dados['almoco_fim'][$dia] ?? HORARIO_PADRAO_ALMOCO_FIM) : NULL;

                    $sql = "INSERT INTO Dias_Trabalhados (dia_semana, horario_abertura, horario_fechamento, horario_almoco_inicio, horario_almoco_fim, ativo) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$dia, $abertura, $fechamento, $almoco_inicio, $almoco_fim, $ativo]);
                }

                $pdo->commit();
                return "Horários salvos com sucesso!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw new Exception("Erro ao salvar horários: " . $e->getMessage());
            }
        },

        'servico_criar' => function ($pdo, $dados) {
            $sql = "INSERT INTO Servicos (nome, descricao, preco_normal, preco_feriado, duracao)
            VALUES (:n, :d, :pn, :pf, :du)";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':n'  => sanitizar_input($dados['nome']),
                ':d'  => sanitizar_input($dados['descricao']),
                ':pn' => floatval($dados['preco_normal'] ?? 0),
                ':pf' => floatval($dados['preco_feriado'] ?? 0),
                ':du' => intval($dados['duracao'] ?? 30)
            ]);
            return "Serviço cadastrado com sucesso!";
        },

        'servico_atualizar' => function ($pdo, $dados) {
            $sql = "UPDATE Servicos 
            SET nome=:n, descricao=:d, preco_normal=:pn, preco_feriado=:pf, duracao=:du 
            WHERE id=:id";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':n'  => sanitizar_input($dados['nome']),
                ':d'  => sanitizar_input($dados['descricao']),
                ':pn' => floatval($dados['preco_normal'] ?? 0),
                ':pf' => floatval($dados['preco_feriado'] ?? 0),
                ':du' => intval($dados['duracao'] ?? 30),
                ':id' => intval($dados['id'] ?? 0)
            ]);
            return "Serviço atualizado!";
        },

        'servico_excluir' => function ($pdo, $dados) {
            $st = $pdo->prepare("DELETE FROM Servicos WHERE id=:id");
            $st->execute([':id' => intval($dados['id'] ?? 0)]);
            return "Serviço excluído!";
        },

        'periodo_criar' => function ($pdo, $dados) {
            if (!validar_data($dados['data_inicio']) || !validar_data($dados['data_fim'])) {
                throw new Exception("Datas inválidas");
            }

            $st = $pdo->prepare("INSERT INTO Periodos_Inativos (data_inicio, data_fim, motivo) VALUES (:i, :f, :m)");
            $st->execute([
                ':i' => $dados['data_inicio'],
                ':f' => $dados['data_fim'],
                ':m' => sanitizar_input($dados['motivo'])
            ]);
            return "Período inativo cadastrado!";
        },

        'periodo_excluir' => function ($pdo, $dados) {
            $st = $pdo->prepare("DELETE FROM Periodos_Inativos WHERE id=:id");
            $st->execute([':id' => intval($dados['id'] ?? 0)]);
            return "Período removido!";
        },

        'feriado_criar' => function ($pdo, $dados) {
            if (!validar_data($dados['data'])) {
                throw new Exception("Data inválida");
            }

            $st = $pdo->prepare("INSERT INTO Feriados (nome, data) VALUES (:n, :d)");
            $st->execute([
                ':n' => sanitizar_input($dados['nome']),
                ':d' => $dados['data']
            ]);
            return "Feriado adicionado!";
        },

        'feriado_excluir' => function ($pdo, $dados) {
            $st = $pdo->prepare("DELETE FROM Feriados WHERE id=:id");
            $st->execute([':id' => intval($dados['id'] ?? 0)]);
            return "Feriado removido!";
        },

        'especie_criar' => function ($pdo, $dados) {
            $especies = explode(',', $dados['especies']);
            $count = 0;

            foreach ($especies as $nome) {
                $nome = sanitizar_input($nome);
                if (!empty($nome)) {
                    $stmt = $pdo->prepare("INSERT INTO Especies (nome) VALUES (?)");
                    $stmt->execute([$nome]);
                    $count++;
                }
            }
            return "$count espécie(s) cadastrada(s) com sucesso!";
        },

        'especie_atualizar' => function ($pdo, $dados) {
            $id = intval($dados['id']);
            $nome = sanitizar_input($dados['nome']);

            if (empty($nome)) {
                throw new Exception("Nome da espécie não pode estar vazio");
            }

            $stmt = $pdo->prepare("UPDATE Especies SET nome=? WHERE id=?");
            $stmt->execute([$nome, $id]);
            return "Espécie atualizada!";
        },

        'especie_excluir' => function ($pdo, $dados) {
            $id = intval($dados['id']);
            $pdo->prepare("DELETE FROM Especies WHERE id=?")->execute([$id]);
            return "Espécie excluída!";
        }
    ];

    if (isset($acoes[$acao])) {
        return $acoes[$acao]($pdo, $dados);
    }

    throw new Exception("Ação não reconhecida: $acao");
}

// ====== PROCESSAMENTO PRINCIPAL ======
$success_message = null;
$error_message = null;

// Processar TODAS as ações via executarAcaoCRUD (incluindo horários)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    try {
        $mensagem = executarAcaoCRUD($pdo, $_POST['acao'], $_POST);
        set_flash($mensagem, 'success');
    } catch (Exception $e) {
        set_flash("Erro: " . $e->getMessage(), "error");
    }

    header("Location: config.php");
    exit;
}

// ====== CARREGAR DADOS PARA EXIBIÇÃO ======
// Horários configurados
$horarios_config = [];
$resultados = carregarHorariosClinica($pdo);
foreach ($resultados as $row) {
    $horarios_config[$row['dia_semana']] = $row;
}

// Preparar dados dos dias
$dias_config = [];
foreach (DIAS_SEMANA as $dia) {
    if (isset($horarios_config[$dia])) {
        $dias_config[$dia] = $horarios_config[$dia];
        $dias_config[$dia]['tem_almoco'] = !empty($horarios_config[$dia]['horario_almoco_inicio']);
    } else {
        $dias_config[$dia] = [
            'ativo' => ($dia != 'Domingo' && $dia != 'Sábado'),
            'horario_abertura' => HORARIO_PADRAO_ABERTURA,
            'horario_fechamento' => HORARIO_PADRAO_FECHAMENTO,
            'horario_almoco_inicio' => HORARIO_PADRAO_ALMOCO_INICIO,
            'horario_almoco_fim' => HORARIO_PADRAO_ALMOCO_FIM,
            'tem_almoco' => true
        ];
    }
}

// Carregar horários ativos para teste
$horarios_por_dia = [];
$horarios_clinica = carregarDadosTabela($pdo, 'Dias_Trabalhados', 'dia_semana');
foreach ($horarios_clinica as $horario) {
    if ($horario['ativo']) {
        $horarios_por_dia[$horario['dia_semana']] = $horario;
    }
}

// Testar horários disponíveis
$data_teste = date('Y-m-d', strtotime('+1 day'));
$horarios_disponiveis_teste = getHorariosDisponiveis($data_teste, $pdo, $horarios_por_dia);

// Carregar outros dados
$servicos = carregarDadosTabela($pdo, 'Servicos', 'nome');
$periodos = carregarDadosTabela($pdo, 'Periodos_Inativos', 'data_inicio DESC');
$feriados = carregarDadosTabela($pdo, 'Feriados', 'data');
$especies = carregarDadosTabela($pdo, 'Especies', 'nome ASC');

$flash = get_flash();

include("header.php");
?>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --primary-light: #818cf8;
        --secondary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --dark: #0f172a;
        --gray-50: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e1;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1e293b;
        --gray-900: #0f172a;
        --white: #ffffff;
        --border-radius: 10px;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    body {
        background: var(--gray-50);
        color: var(--gray-900);
        line-height: 1.6;
        min-height: 100vh;
        padding: 16px;
        margin-left: 90px;
    }

    .config-container {
        max-width: 1200px;
        margin: 0 auto;
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    .header {
        background: var(--white);
        color: var(--primary-dark);
        padding: 20px 24px;
        text-align: center;
        border-bottom: 2px solid var(--gray-100);
    }

    .header h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-weight: 700;
        color: var(--gray-900);
    }

    .header p {
        font-size: 0.875rem;
        color: var(--gray-600);
        margin-top: 4px;
    }

    .floating-tabs {
        position: sticky;
        top: 0;
        background: var(--white);
        z-index: 100;
        padding: 12px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        gap: 8px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--gray-400) var(--gray-100);
    }

    .floating-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .floating-tabs::-webkit-scrollbar-track {
        background: var(--gray-100);
    }

    .floating-tabs::-webkit-scrollbar-thumb {
        background: var(--gray-400);
        border-radius: 4px;
    }

    .tab-btn {
        background: transparent;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        color: var(--gray-700);
    }

    .tab-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--gray-50);
    }

    .tab-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--white);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .tab-content {
        display: none;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .tab-content.active {
        display: block;
    }

    .card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-md);
        border-left: 3px solid var(--primary);
    }

    .card h2 {
        font-size: 1.125rem;
        margin-bottom: 16px;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
    }

    .form-grid {
        display: grid;
        gap: 14px;
    }

    .form-grid label i {
        color: var(--primary);
        margin-right: 4px;
        font-size: 0.875rem;
    }

    /* Ícones nos headers da tabela */
    .table th i {
        opacity: 0.7;
        margin-right: 6px;
    }

    /* Estado vazio melhorado */
    .card>div[style*="text-align: center"] {
        animation: fadeIn 0.6s ease;
    }

    /* Animação para a tabela */
    .table tbody tr {
        animation: slideInTable 0.4s ease backwards;
    }

    .table tbody tr:nth-child(1) {
        animation-delay: 0.05s;
    }

    .table tbody tr:nth-child(2) {
        animation-delay: 0.1s;
    }

    .table tbody tr:nth-child(3) {
        animation-delay: 0.15s;
    }

    .table tbody tr:nth-child(4) {
        animation-delay: 0.2s;
    }

    .table tbody tr:nth-child(5) {
        animation-delay: 0.25s;
    }

    @keyframes slideInTable {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Badge de duração */
    .table td span[style*="background: var(--primary-light)"] {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .table td span[style*="background: var(--primary-light)"]::before {
        content: '⏱️';
        font-size: 0.75rem;
    }

    /* Tooltip customizado */
    [title] {
        position: relative;
    }

    /* Botão de cancelar com hover especial */
    .btn-light:hover {
        background: var(--gray-300);
        transform: translateX(-2px);
    }

    .form-grid-2 {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .form-grid-3 {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    label {
        display: block;
        margin-bottom: 5px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    input,
    textarea,
    select {
        width: 100%;
        padding: 9px 12px;
        border: 2px solid var(--gray-200);
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: var(--white);
        color: var(--gray-900);
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 16px;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-primary {
        background: var(--primary);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline {
        background: transparent;
        color: var(--primary);
        border: 1px solid var(--primary);
    }

    .btn-outline:hover {
        background: var(--primary-light);
        color: var(--white);
    }

    .btn-light {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    .btn-light:hover {
        background: var(--gray-300);
    }

    .btn-danger {
        background: var(--danger);
        color: var(--white);
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        font-size: 0.875rem;
    }

    .table th {
        background: var(--gray-50);
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        color: var(--gray-700);
        border-bottom: 2px solid var(--gray-200);
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-800);
    }

    .table tr:last-child td {
        border-bottom: none;
    }

    .table tr:hover {
        background: var(--gray-50);
    }

    .flash {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.875rem;
        font-weight: 500;
        animation: slideIn 0.4s ease;
        border-left: 4px solid;
    }

    @keyframes slideIn {
        from {
            transform: translateX(-100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .flash.success {
        background: #d1fae5;
        color: #065f46;
        border-left-color: var(--success);
    }

    .flash.error {
        background: #fee2e2;
        color: #991b1b;
        border-left-color: var(--danger);
    }

    .flash.info {
        background: #dbeafe;
        color: #1e40af;
        border-left-color: var(--info);
    }

    .dias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .dia-card {
        background: var(--white);
        border-radius: 8px;
        padding: 14px;
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        transition: all 0.2s ease;
    }

    .dia-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .dia-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--gray-200);
    }

    .dia-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--gray-900);
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 22px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--gray-300);
        transition: .3s;
        border-radius: 22px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: var(--shadow-sm);
    }

    input:checked+.slider {
        background-color: var(--success);
    }

    input:checked+.slider:before {
        transform: translateX(20px);
    }

    .horarios-container {
        display: grid;
        gap: 10px;
    }

    .time-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .time-input {
        padding: 7px 10px;
        font-size: 0.8125rem;
    }

    .almoco-section {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--gray-300);
    }

    .almoco-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--gray-700);
    }

    .almoco-toggle input[type="checkbox"] {
        width: auto;
    }

    .almoco-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .hidden {
        display: none !important;
    }

    .btn-salvar {
        background: var(--success);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.9375rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        justify-content: center;
        margin-top: 8px;
    }

    .btn-salvar:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .test-section {
        background: #fef3c7;
        padding: 16px;
        border-radius: 8px;
        margin-top: 18px;
        border-left: 4px solid var(--warning);
    }

    .test-section h3 {
        margin-bottom: 10px;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9375rem;
        font-weight: 700;
    }

    .test-section p {
        font-size: 0.875rem;
        color: var(--gray-700);
        margin-bottom: 10px;
    }

    .horarios-lista {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .horario-item {
        background: var(--primary);
        color: white;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .sem-horarios {
        color: var(--gray-600);
        font-style: italic;
        font-size: 0.875rem;
    }

    .especie-form {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .especie-input {
        flex: 1;
        margin-bottom: 0;
    }

    details {
        margin: 8px 0;
    }

    details summary {
        cursor: pointer;
        padding: 8px 12px;
        background-color: var(--gray-50);
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-700);
        list-style: none;
    }

    details summary::-webkit-details-marker {
        display: none;
    }

    details summary::before {
        content: '▶';
        display: inline-block;
        margin-right: 6px;
        transition: transform 0.2s;
        font-size: 0.75rem;
    }

    details[open] summary::before {
        transform: rotate(90deg);
    }

    details summary:hover {
        background-color: var(--gray-100);
    }

    details[open] summary {
        margin-bottom: 12px;
        background-color: var(--primary-light);
        color: var(--primary-dark);
    }

    button[type="submit"]:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    @media (max-width: 992px) {
        body {
            margin-left: 0;
            padding: 12px;
        }

        .config-container {
            border-radius: 8px;
        }

        .floating-tabs {
            padding: 10px 14px;
        }

        .tab-content {
            padding: 16px;
        }

        .dias-grid {
            grid-template-columns: 1fr;
        }

        .time-group,
        .almoco-content {
            grid-template-columns: 1fr;
        }

        .form-grid-2,
        .form-grid-3 {
            grid-template-columns: 1fr;
        }

        .header h1 {
            font-size: 1.25rem;
        }
    }

    @media (max-width: 768px) {
        .card {
            padding: 16px;
        }

        .table {
            font-size: 0.8125rem;
        }

        .table th,
        .table td {
            padding: 8px 10px;
        }

        .floating-tabs {
            gap: 6px;
        }

        .tab-btn {
            padding: 7px 12px;
            font-size: 0.8125rem;
        }
    }

    @media (max-width: 480px) {
        .header h1 {
            font-size: 1.1rem;
        }

        .btn {
            padding: 8px 12px;
            font-size: 0.8125rem;
        }
    }
</style>


<div class="config-container">
    <div class="header">
        <h1><i class="fas fa-cog"></i> Configurações do Sistema</h1>
        <p>Gerencie as configurações do sistema de agendamento</p>
    </div>

    <div class="floating-tabs">
        <button class="tab-btn active" data-tab="tab-servicos">
            <i class="fas fa-stethoscope"></i> Serviços
        </button>
        <button class="tab-btn" data-tab="tab-dias">
            <i class="fas fa-calendar-day"></i> Dias Trabalhados
        </button>
        <button class="tab-btn" data-tab="tab-periodos">
            <i class="fas fa-plane-slash"></i> Períodos Inativos
        </button>
        <button class="tab-btn" data-tab="tab-feriados">
            <i class="fas fa-flag"></i> Feriados
        </button>
        <button class="tab-btn" data-tab="tab-especies">
            <i class="fas fa-paw"></i> Espécies
        </button>
    </div>

    <!-- SERVIÇOS -->
    <!-- SERVIÇOS - VERSÃO MELHORADA -->
    <div id="tab-servicos" class="tab-content active">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['tipo']) ?>">
                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa fa-plus"></i> Novo serviço</h2>
            <form method="post" class="form-grid form-grid-3">
                <input type="hidden" name="acao" value="servico_criar">
                <div>
                    <label><i class="fas fa-tag"></i> Nome</label>
                    <input type="text" name="nome" required placeholder="Ex: Consulta geral">
                </div>
                <div>
                    <label><i class="fas fa-dollar-sign"></i> Preço normal (R$)</label>
                    <input type="number" name="preco_normal" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div>
                    <label><i class="fas fa-calendar-alt"></i> Preço em feriado (R$)</label>
                    <input type="number" name="preco_feriado" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div>
                    <label><i class="fas fa-clock"></i> Duração</label>
                    <select name="duracao" required>
                        <option value="15">⏱️ 15 minutos</option>
                        <option value="30" selected>⏱️ 30 minutos</option>
                        <option value="45">⏱️ 45 minutos</option>
                        <option value="60">⏱️ 1 hora</option>
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label><i class="fas fa-align-left"></i> Descrição</label>
                    <textarea name="descricao" rows="3" placeholder="Detalhes do serviço..."></textarea>
                </div>
                <div style="grid-column:1/-1; display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">
                        <i class="fa fa-save"></i> Salvar serviço
                    </button>
                    <button class="btn btn-light" type="reset">
                        <i class="fa fa-eraser"></i> Limpar
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa fa-list"></i> Serviços cadastrados (<?= count($servicos) ?>)</h2>

            <?php if (!$servicos): ?>
                <div style="text-align: center; padding: 40px; color: var(--gray-500);">
                    <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: 16px;"></i>
                    <p style="font-size: 1.125rem; font-weight: 600;">Nenhum serviço cadastrado</p>
                    <p style="font-size: 0.875rem;">Comece adicionando um serviço usando o formulário acima</p>
                </div>
            <?php else: ?>
                <div style="overflow:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> ID</th>
                                <th><i class="fas fa-tag"></i> Nome</th>
                                <th><i class="fas fa-dollar-sign"></i> Preço normal</th>
                                <th><i class="fas fa-calendar-alt"></i> Preço feriado</th>
                                <th><i class="fas fa-clock"></i> Duração</th>
                                <th><i class="fas fa-align-left"></i> Descrição</th>
                                <th style="width:200px; text-align: center;"><i class="fas fa-cog"></i> Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--gray-600);">#<?= $s['id'] ?></td>
                                    <td style="font-weight: 600; color: var(--gray-900);">
                                        <?= htmlspecialchars($s['nome']) ?>
                                    </td>
                                    <td style="color: var(--success); font-weight: 600;">
                                        R$ <?= number_format($s['preco_normal'], 2, ',', '.') ?>
                                    </td>
                                    <td style="color: var(--warning); font-weight: 600;">
                                        R$ <?= number_format($s['preco_feriado'], 2, ',', '.') ?>
                                    </td>
                                    <td>
                                        <span style="background: var(--primary-light); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                            <?= $s['duracao'] ?> min
                                        </span>
                                    </td>
                                    <td style="color: var(--gray-600); font-size: 0.8125rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= $s['descricao'] ? htmlspecialchars($s['descricao']) : '<em style="opacity: 0.5;">Sem descrição</em>' ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                            <!-- Formulário de edição em details -->
                                            <details style="flex: 1;">
                                                <summary>Editar</summary>
                                                <form method="post" class="form-grid form-grid-3">
                                                    <input type="hidden" name="acao" value="servico_atualizar">
                                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">

                                                    <div>
                                                        <label title="Nome do serviço">Nome</label>
                                                        <input type="text" name="nome" value="<?= htmlspecialchars($s['nome']) ?>" required>
                                                    </div>

                                                    <div>
                                                        <label title="Preço em dias normais">Preço normal</label>
                                                        <input type="number" step="0.01" name="preco_normal"
                                                            value="<?= number_format($s['preco_normal'], 2, '.', '') ?>" required>
                                                    </div>

                                                    <div>
                                                        <label title="Preço em feriados">Preço feriado</label>
                                                        <input type="number" step="0.01" name="preco_feriado"
                                                            value="<?= number_format($s['preco_feriado'], 2, '.', '') ?>" required>
                                                    </div>

                                                    <div>
                                                        <label title="Duração do serviço">Duração</label>
                                                        <select name="duracao" required>
                                                            <option value="15" <?= $s['duracao'] == 15 ? 'selected' : '' ?>>15 minutos</option>
                                                            <option value="30" <?= $s['duracao'] == 30 ? 'selected' : '' ?>>30 minutos</option>
                                                            <option value="45" <?= $s['duracao'] == 45 ? 'selected' : '' ?>>45 minutos</option>
                                                            <option value="60" <?= $s['duracao'] == 60 ? 'selected' : '' ?>>1 hora</option>
                                                        </select>
                                                    </div>

                                                    <div style="grid-column:1/-1">
                                                        <label title="Descrição detalhada do serviço">Descrição</label>
                                                        <textarea name="descricao" rows="3"><?= htmlspecialchars($s['descricao']) ?></textarea>
                                                    </div>

                                                    <div style="grid-column:1/-1; display:flex; gap:8px;">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="fa fa-save"></i> Salvar alterações
                                                        </button>
                                                        <button class="btn btn-light" type="button"
                                                            onclick="this.closest('details').removeAttribute('open')">
                                                            <i class="fa fa-times"></i> Cancelar
                                                        </button>
                                                    </div>
                                                </form>
                                            </details>

                                            <!-- Botão de excluir -->
                                            <form method="post" style="margin: 0;"
                                                onsubmit="return confirm('⚠️ Tem certeza que deseja excluir o serviço \'<?= htmlspecialchars($s['nome']) ?>\'?\n\nEsta ação não pode ser desfeita!');">
                                                <input type="hidden" name="acao" value="servico_excluir">
                                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                                <button class="btn btn-danger" type="submit" title="Excluir serviço">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DIAS TRABALHADOS -->
    <div id="tab-dias" class="tab-content">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['tipo']) ?>">
                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>



        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Configuração de Horários</h2>
            <p>Defina os dias e horários de funcionamento da clínica</p>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>

            <!-- ADICIONAR: Formulário com ID específico e campo ação -->
            <form id="form-horarios" method="post" action="">
                <input type="hidden" name="acao" value="salvar_horarios">

                <div class="dias-grid">
                    <?php foreach ($dias_config as $dia => $config): ?>
                        <div class="dia-card">
                            <div class="dia-header">
                                <span class="dia-title"><?= $dia ?></span>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="ativo[<?= $dia ?>]" <?= $config['ativo'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="horarios-container">
                                <div class="time-group">
                                    <div>
                                        <label>Abertura</label>
                                        <input type="time" name="abertura[<?= $dia ?>]"
                                            value="<?= $config['horario_abertura'] ?>"
                                            class="time-input" required>
                                    </div>
                                    <div>
                                        <label>Fechamento</label>
                                        <input type="time" name="fechamento[<?= $dia ?>]"
                                            value="<?= $config['horario_fechamento'] ?>"
                                            class="time-input" required>
                                    </div>
                                </div>

                                <div class="almoco-section">
                                    <label class="almoco-toggle">
                                        <input type="checkbox" name="tem_almoco[<?= $dia ?>]"
                                            <?= !empty($config['horario_almoco_inicio']) ? 'checked' : '' ?>
                                            onchange="toggleAlmoco(this, '<?= $dia ?>')">
                                        <span>Horário de almoço</span>
                                    </label>

                                    <div id="almoco-<?= $dia ?>" class="almoco-content <?= empty($config['horario_almoco_inicio']) ? 'hidden' : '' ?>">
                                        <div>
                                            <label>Início</label>
                                            <input type="time" name="almoco_inicio[<?= $dia ?>]"
                                                value="<?= !empty($config['horario_almoco_inicio']) ? $config['horario_almoco_inicio'] : '12:00' ?>"
                                                class="time-input">
                                        </div>
                                        <div>
                                            <label>Fim</label>
                                            <input type="time" name="almoco_fim[<?= $dia ?>]"
                                                value="<?= !empty($config['horario_almoco_fim']) ? $config['horario_almoco_fim'] : '13:00' ?>"
                                                class="time-input">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-salvar">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </form>

            <!-- Seção de teste dos horários disponíveis -->
            <div class="test-section">
                <h3><i class="fas fa-test-tube"></i> Teste dos Horários Disponíveis</h3>
                <p>Horários disponíveis para amanhã (<?= date('d/m/Y', strtotime('+1 day')) ?>):</p>

                <div class="horarios-lista">
                    <?php if (!empty($horarios_disponiveis_teste)): ?>
                        <?php foreach ($horarios_disponiveis_teste as $horario): ?>
                            <span class="horario-item"><?= $horario ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="sem-horarios">Nenhum horário disponível ou clínica fechada</span>
                    <?php endif; ?>
                </div>

                <p style="margin-top: 15px; font-size: 14px; color: var(--dark-gray);">
                    <i class="fas fa-info-circle"></i> Esta é uma demonstração dos horários que estarão disponíveis para agendamento.
                </p>
            </div>
        </div>
    </div>

    <!-- PERÍODOS INATIVOS -->
    <div id="tab-periodos" class="tab-content">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['tipo']) ?>">
                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa fa-plane-slash"></i> Cadastrar período inativo</h2>
            <form method="post" class="form-grid form-grid-3">
                <input type="hidden" name="acao" value="periodo_criar">
                <div>
                    <label>Data início</label>
                    <input type="date" name="data_inicio" required>
                </div>
                <div>
                    <label>Data fim</label>
                    <input type="date" name="data_fim" required>
                </div>
                <div style="grid-column:1/-1">
                    <label>Motivo</label>
                    <input type="text" name="motivo" required placeholder="Ex: Reforma, viagem, inventário...">
                </div>
                <div style="grid-column:1/-1">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Cadastrar</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa fa-list-check"></i> Períodos cadastrados</h2>
            <div style="overflow:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Motivo</th>
                            <th style="width:120px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$periodos): ?>
                            <tr>
                                <td colspan="4">Nenhum período inativo.</td>
                            </tr>
                            <?php else: foreach ($periodos as $p): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($p['data_inicio'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['data_fim'])) ?></td>
                                    <td><?= htmlspecialchars($p['motivo']) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Remover este período?');">
                                            <input type="hidden" name="acao" value="periodo_excluir">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button class="btn btn-danger" type="submit"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FERIADOS -->
    <div id="tab-feriados" class="tab-content">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['tipo']) ?>">
                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa fa-flag"></i> Adicionar feriado</h2>
            <form method="post" class="form-grid form-grid-2">
                <input type="hidden" name="acao" value="feriado_criar">
                <div>
                    <label>Nome</label>
                    <input type="text" name="nome" required placeholder="Ex: Natal">
                </div>
                <div>
                    <label>Data</label>
                    <input type="date" name="data" required>
                </div>
                <div style="grid-column:1/-1">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Adicionar</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa fa-list"></i> Feriados</h2>
            <div style="overflow:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nome</th>
                            <th style="width:120px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$feriados): ?>
                            <tr>
                                <td colspan="3">Nenhum feriado cadastrado.</td>
                            </tr>
                            <?php else: foreach ($feriados as $f): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($f['data'])) ?></td>
                                    <td><?= htmlspecialchars($f['nome']) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Remover feriado?');">
                                            <input type="hidden" name="acao" value="feriado_excluir">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <button class="btn btn-danger" type="submit"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ESPÉCIES -->
    <div id="tab-especies" class="tab-content">
        <?php if ($flash): ?>
            <div class="flash <?= htmlspecialchars($flash['tipo']) ?>">
                <i class="fa fa-info-circle"></i> <?= htmlspecialchars($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa fa-plus"></i> Cadastrar Espécies</h2>
            <form method="post">
                <input type="hidden" name="acao" value="especie_criar">
                <div>
                    <label>Digite os nomes dos animais (separados por vírgula):</label>
                    <input type="text" name="especies" placeholder="Ex: Cachorro, Gato, Coelho" required>
                </div>
                <div style="margin-top:10px">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Cadastrar Espécies</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa fa-list"></i> Espécies Cadastradas</h2>
            <div style="overflow:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th style="width:180px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$especies): ?>
                            <tr>
                                <td colspan="3">Nenhuma espécie cadastrada.</td>
                            </tr>
                            <?php else: foreach ($especies as $e): ?>
                                <tr>
                                    <td><?= $e['id'] ?></td>
                                    <td>
                                        <form method="post" class="especie-form">
                                            <input type="hidden" name="acao" value="especie_atualizar">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <input type="text" name="nome" value="<?= htmlspecialchars($e['nome']) ?>" class="especie-input" required>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i></button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Excluir esta espécie?');">
                                            <input type="hidden" name="acao" value="especie_excluir">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button class="btn btn-danger" type="submit"><i class="fa fa-trash"></i> Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // ====== CONFIGURAÇÕES E CONSTANTES ======
    const CONFIG = {
        flashMessageDuration: 5000,
        autoSaveDelay: 300,
        selectors: {
            tabBtn: '.tab-btn',
            tabContent: '.tab-content',
            form: 'form',
            almocoToggle: 'input[name*="tem_almoco"]',
            ativoToggle: 'input[name*="ativo"]'
        },
        classes: {
            active: 'active',
            hidden: 'hidden',
            loading: 'loading'
        }
    };

    // ====== UTILITÁRIOS ======
    const Utils = {
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        sanitizeHTML(str) {
            const temp = document.createElement('div');
            temp.textContent = str;
            return temp.innerHTML;
        },

        serializeForm(form) {
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (data[key]) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            }
            return data;
        }
    };

    // ====== GERENCIAMENTO DE NOTIFICAÇÕES ======
    const NotificationManager = {
        container: null,

        init() {
            this.createContainer();
        },

        createContainer() {
            if (!document.getElementById('notificacoes-container')) {
                this.container = document.createElement('div');
                this.container.id = 'notificacoes-container';
                this.container.style.cssText = `
                position: fixed;
                top: 16px;
                right: 16px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 8px;
                pointer-events: none;
            `;
                document.body.appendChild(this.container);
            } else {
                this.container = document.getElementById('notificacoes-container');
            }
        },

        show(mensagem, tipo = 'info', duracao = CONFIG.flashMessageDuration) {
            if (!this.container) this.createContainer();

            const id = `notif-${Date.now()}`;
            const div = document.createElement('div');
            div.className = `notificacao ${tipo}`;
            div.id = id;
            div.style.cssText = `
            background: var(--white);
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.4s ease-out;
            border-left: 4px solid;
            font-size: 0.875rem;
            pointer-events: auto;
            cursor: pointer;
        `;

            const iconMap = {
                sucesso: 'check-circle',
                success: 'check-circle',
                erro: 'exclamation-circle',
                error: 'exclamation-circle',
                info: 'info-circle',
                warning: 'exclamation-triangle'
            };

            const colorMap = {
                sucesso: 'var(--success)',
                success: 'var(--success)',
                erro: 'var(--danger)',
                error: 'var(--danger)',
                info: 'var(--info)',
                warning: 'var(--warning)'
            };

            div.style.borderLeftColor = colorMap[tipo] || colorMap.info;

            div.innerHTML = `
            <i class="fas fa-${iconMap[tipo] || 'info-circle'}" style="color: ${colorMap[tipo]}; font-size: 1.25rem;"></i>
            <span style="flex: 1; color: var(--gray-900);">${Utils.sanitizeHTML(mensagem)}</span>
            <i class="fas fa-times" style="color: var(--gray-500); cursor: pointer; font-size: 1rem;"></i>
        `;

            this.container.appendChild(div);

            // Click para fechar
            const closeBtn = div.querySelector('.fa-times');
            const closeNotification = () => {
                div.style.animation = 'slideIn 0.4s reverse';
                setTimeout(() => div.remove(), 400);
            };

            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeNotification();
            });

            div.addEventListener('click', closeNotification);

            // Auto-remover
            setTimeout(() => {
                if (div.parentNode) {
                    div.style.animation = 'slideIn 0.4s reverse';
                    setTimeout(() => div.remove(), 400);
                }
            }, duracao);

            return div;
        }
    };

    // ====== GERENCIAMENTO DE ABAS ======
    const TabManager = {
        currentTab: null,

        init() {
            this.bindEvents();
            this.setInitialTab();
        },

        bindEvents() {
            document.addEventListener('click', (e) => {
                const btn = e.target.closest(CONFIG.selectors.tabBtn);
                if (btn) {
                    e.preventDefault();
                    this.switchTab(btn);
                }
            });

            // Salvar aba ativa no localStorage
            window.addEventListener('beforeunload', () => {
                if (this.currentTab) {
                    localStorage.setItem('activeTab', this.currentTab);
                }
            });
        },

        setInitialTab() {
            const savedTab = localStorage.getItem('activeTab');
            const initialBtn = savedTab ?
                document.querySelector(`[data-tab="${savedTab}"]`) :
                document.querySelector(CONFIG.selectors.tabBtn);

            if (initialBtn) {
                this.switchTab(initialBtn);
            }
        },

        switchTab(activeBtn) {
            // Remove active de todos
            document.querySelectorAll(CONFIG.selectors.tabBtn).forEach(b =>
                b.classList.remove(CONFIG.classes.active)
            );
            document.querySelectorAll(CONFIG.selectors.tabContent).forEach(c =>
                c.classList.remove(CONFIG.classes.active)
            );

            // Adiciona active ao selecionado
            activeBtn.classList.add(CONFIG.classes.active);

            const tabId = activeBtn.getAttribute('data-tab');
            const tabContent = document.getElementById(tabId);

            if (tabContent) {
                tabContent.classList.add(CONFIG.classes.active);
                this.currentTab = tabId;

                // Disparar evento customizado
                window.dispatchEvent(new CustomEvent('tabChanged', {
                    detail: {
                        tabId,
                        tabContent
                    }
                }));
            }
        },

        getActiveTab() {
            return document.querySelector(`${CONFIG.selectors.tabContent}.${CONFIG.classes.active}`);
        }
    };

    // ====== GERENCIAMENTO DE FORMULÁRIOS ======
    const FormManager = {
        submittingForms: new Set(),

        init() {
            this.bindEvents();
            this.setupValidation();
        },

        bindEvents() {
            // Submit de formulários
            document.addEventListener('submit', async (e) => {
                if (e.target.matches(CONFIG.selectors.form)) {
                    e.preventDefault();
                    await this.handleFormSubmit(e.target);
                }
            });

            // Toggle de almoço
            document.addEventListener('change', (e) => {
                if (e.target.matches(CONFIG.selectors.almocoToggle)) {
                    const match = e.target.name.match(/\[(.*?)\]/);
                    if (match) {
                        this.toggleAlmoco(e.target, match[1]);
                    }
                }
            });

            // Toggle de dias ativos
            document.addEventListener('change', (e) => {
                if (e.target.matches(CONFIG.selectors.ativoToggle)) {
                    const card = e.target.closest('.dia-card');
                    if (card) {
                        card.style.opacity = e.target.checked ? '1' : '0.6';
                    }
                }
            });

            // Reset de formulários
            document.addEventListener('reset', (e) => {
                if (e.target.matches(CONFIG.selectors.form)) {
                    NotificationManager.show('Formulário limpo', 'info', 2000);
                }
            });
        },

        setupValidation() {
            // Validação em tempo real
            document.addEventListener('blur', (e) => {
                if (e.target.matches('input[required], select[required], textarea[required]')) {
                    this.validateField(e.target);
                }
            }, true);
        },

        validateField(field) {
            if (field.validity.valid) {
                field.style.borderColor = 'var(--gray-200)';
                return true;
            } else {
                field.style.borderColor = 'var(--danger)';
                field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                return false;
            }
        },

        toggleAlmoco(checkbox, dia) {
            const almocoContent = document.getElementById(`almoco-${dia}`);
            if (almocoContent) {
                if (checkbox.checked) {
                    almocoContent.classList.remove(CONFIG.classes.hidden);
                    almocoContent.style.animation = 'fadeIn 0.3s ease';
                } else {
                    almocoContent.classList.add(CONFIG.classes.hidden);
                }
            }
        },

        async handleFormSubmit(form) {
            // Prevenir múltiplos submits
            if (this.submittingForms.has(form)) {
                NotificationManager.show('Aguarde o processamento anterior', 'warning', 2000);
                return;
            }

            // Validar formulário
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            this.submittingForms.add(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn?.innerHTML;

            try {
                // Mostrar loading
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                    submitBtn.disabled = true;
                }

                const formData = new FormData(form);

                console.log('📤 Enviando formulário:', form.id || 'sem-id');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}:`, value);
                }

                const response = await fetch('config.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                const html = await response.text();
                await this.handleFormResponse(html, form);

            } catch (error) {
                console.error('❌ Erro ao enviar formulário:', error);
                NotificationManager.show(
                    'Erro ao processar solicitação: ' + error.message,
                    'erro'
                );
            } finally {
                // Restaurar botão
                if (submitBtn) {
                    submitBtn.innerHTML = originalHTML;
                    submitBtn.disabled = false;
                }
                this.submittingForms.delete(form);
            }
        },

        async handleFormResponse(html, originalForm) {
            const currentTab = TabManager.getActiveTab();
            if (!currentTab) return;

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector(`#${currentTab.id}`);

            if (newContent) {
                // Preservar scroll
                const scrollPos = window.scrollY;

                // Atualizar conteúdo
                currentTab.innerHTML = newContent.innerHTML;

                // Restaurar scroll
                window.scrollTo(0, scrollPos);

                // Verificar mensagens flash
                const flashElement = newContent.querySelector('.flash');
                if (flashElement) {
                    const message = flashElement.textContent.trim();
                    const type = flashElement.classList.contains('success') ? 'sucesso' :
                        flashElement.classList.contains('error') ? 'erro' : 'info';
                    NotificationManager.show(message, type);
                } else {
                    NotificationManager.show('Operação realizada com sucesso!', 'sucesso');
                }

                // Resetar formulário se não for de edição
                if (!originalForm.querySelector('input[type="hidden"][name="id"]')) {
                    originalForm.reset();
                }

                // Disparar evento
                window.dispatchEvent(new CustomEvent('formSuccess', {
                    detail: {
                        form: originalForm,
                        tab: currentTab
                    }
                }));
            }
        }
    };

    // ====== GERENCIAMENTO DE CONFIRMAÇÕES ======
    const ConfirmManager = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form.hasAttribute('onsubmit') && form.getAttribute('onsubmit').includes('confirm')) {
                    e.preventDefault();

                    const confirmText = form.getAttribute('onsubmit').match(/confirm\(['"](.+?)['"]\)/)?.[1] ||
                        'Tem certeza que deseja realizar esta ação?';

                    this.showConfirm(confirmText, () => {
                        form.removeAttribute('onsubmit');
                        form.requestSubmit();
                    });
                }
            });
        },

        showConfirm(message, onConfirm) {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            animation: fadeIn 0.2s ease;
        `;

            overlay.innerHTML = `
            <div style="
                background: var(--white);
                padding: 24px;
                border-radius: 12px;
                max-width: 400px;
                box-shadow: var(--shadow-xl);
                animation: slideIn 0.3s ease;
            ">
                <h3 style="margin: 0 0 16px 0; color: var(--gray-900); font-size: 1.125rem;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    Confirmação
                </h3>
                <p style="margin: 0 0 20px 0; color: var(--gray-700); font-size: 0.9375rem;">
                    ${Utils.sanitizeHTML(message)}
                </p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="btn-cancel" style="
                        padding: 8px 16px;
                        border: 1px solid var(--gray-300);
                        border-radius: 6px;
                        background: var(--white);
                        color: var(--gray-700);
                        cursor: pointer;
                        font-weight: 600;
                        font-size: 0.875rem;
                    ">Cancelar</button>
                    <button class="btn-confirm" style="
                        padding: 8px 16px;
                        border: none;
                        border-radius: 6px;
                        background: var(--danger);
                        color: var(--white);
                        cursor: pointer;
                        font-weight: 600;
                        font-size: 0.875rem;
                    ">Confirmar</button>
                </div>
            </div>
        `;

            document.body.appendChild(overlay);

            const closeOverlay = () => overlay.remove();

            overlay.querySelector('.btn-cancel').addEventListener('click', closeOverlay);
            overlay.querySelector('.btn-confirm').addEventListener('click', () => {
                onConfirm();
                closeOverlay();
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeOverlay();
            });
        }
    };

    // ====== INICIALIZAÇÃO ======
    document.addEventListener('DOMContentLoaded', () => {
        console.log('🚀 Inicializando sistema de configurações...');

        // Inicializar módulos
        NotificationManager.init();
        TabManager.init();
        FormManager.init();
        ConfirmManager.init();

        // Adicionar estilos de animação
        if (!document.getElementById('config-animations')) {
            const style = document.createElement('style');
            style.id = 'config-animations';
            style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            button[type="submit"]:disabled {
                opacity: 0.6 !important;
                cursor: not-allowed !important;
                transform: none !important;
            }
        `;
            document.head.appendChild(style);
        }

        // Event listeners globais
        window.addEventListener('tabChanged', (e) => {
            console.log('📑 Aba alterada:', e.detail.tabId);
        });

        window.addEventListener('formSuccess', (e) => {
            console.log('✅ Formulário processado:', e.detail.form);
        });

        // Verificar mensagens na URL
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        const tipo = urlParams.get('tipo');
        if (msg) {
            NotificationManager.show(decodeURIComponent(msg), tipo || 'info');
            window.history.replaceState({}, '', window.location.pathname);
        }

        console.log('✅ Sistema inicializado com sucesso!');
    });

    // ====== EXPORTAÇÕES GLOBAIS ======
    window.toggleAlmoco = (checkbox, dia) => FormManager.toggleAlmoco(checkbox, dia);
    window.NotificationManager = NotificationManager;
</script>
</body>

</html>