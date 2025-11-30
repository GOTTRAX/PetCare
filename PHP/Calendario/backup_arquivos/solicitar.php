<?php
// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// ========== COLETA DOS DADOS DO FORMULÁRIO ==========
// ATENÇÃO: Este código está usando cliente_id FIXO (hardcoded)
// TODO: Substituir por sessão quando implementar autenticação
$cliente_id = 1; // ⚠️ TEMPORÁRIO - depois substitui pelo ID do usuário logado ($_SESSION['id'])

// Recebe os dados enviados via POST do formulário
$animal_id = $_POST['animal_id'];      // ID do animal que será atendido
$servico_id = $_POST['servico_id'];    // ID do serviço a ser realizado
$data = $_POST['data'];                // Data do agendamento (YYYY-MM-DD)
$hora_inicio = $_POST['hora_inicio'];  // Hora de início (HH:MM:SS)
$hora_final = $_POST['hora_final'];    // Hora de término (HH:MM:SS)

// Observações: Opcional, se não enviado usa string vazia
$observacoes = $_POST['observacoes'] ?? '';

// ========== INSERÇÃO NO BANCO DE DADOS ==========
// Cria um novo agendamento com status 'pendente' (aguardando aprovação da clínica)
$sql = "INSERT INTO Agendamentos 
        (cliente_id, animal_id, servico_id, data_hora, hora_inicio, hora_final, status, observacoes) 
        VALUES (:cliente_id, :animal_id, :servico_id, :data, :hora_inicio, :hora_final, 'pendente', :observacoes)";

// ========== PREPARAÇÃO E EXECUÇÃO ==========
// Prepara a query (prepared statement para evitar SQL Injection)
$stmt = $pdo->prepare($sql);

// Executa substituindo os placeholders (:cliente_id, :animal_id, etc) pelos valores reais
$stmt->execute([
    ':cliente_id' => $cliente_id,
    ':animal_id' => $animal_id,
    ':servico_id' => $servico_id,
    ':data' => $data,
    ':hora_inicio' => $hora_inicio,
    ':hora_final' => $hora_final,
    ':observacoes' => $observacoes
]);

// ========== RETORNO DE SUCESSO ==========
// Retorna JSON confirmando que o agendamento foi criado
echo json_encode(["sucesso" => true]);

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo CRIA (INSERE) um novo agendamento no sistema
//
// Fluxo:
// 1. Recebe dados do formulário via POST
// 2. Usa cliente_id fixo (1) - PRECISA SER ALTERADO para usar sessão
// 3. Insere novo registro na tabela Agendamentos
// 4. Define status inicial como 'pendente' (aguardando aprovação)
// 5. Retorna JSON: {"sucesso": true}
//
// Status do agendamento:
// - 'pendente': Aguardando aprovação da clínica
// - 'confirmado': Aprovado pela clínica
// - 'cancelado': Cancelado pelo cliente ou clínica
// - 'finalizado': Atendimento concluído
//
// ⚠️ PROBLEMAS ENCONTRADOS:
// 1. Cliente_id FIXO (hardcoded = 1) - não usa sessão
// 2. SEM validação dos dados recebidos
// 3. SEM tratamento de erros (try-catch)
// 4. SEM verificação se os campos foram enviados
// 5. Retorna "sucesso" ao invés de "success" (inconsistente com outros arquivos)
?>