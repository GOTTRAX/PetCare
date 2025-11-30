<?php
// Inicia a sessão para acessar dados do usuário logado
session_start();

// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php';

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// ========== VALIDAÇÃO DO MÉTODO E DADOS MÍNIMOS ==========
// Verifica se é POST e se pelo menos animal_id foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['animal_id'])) {
    echo json_encode(['success' => false, 'message' => 'Método inválido ou dados incompletos.']);
    exit;
}

// ========== COLETA DOS DADOS DO FORMULÁRIO ==========
// Cliente ID: Se for Secretaria, pode escolher o cliente; se for Cliente, usa o próprio ID
$cliente_id   = $_POST['cliente_id'] ?? $_SESSION['id'];

// Dados obrigatórios do agendamento
$animal_id    = $_POST['animal_id'];     // ID do animal que será atendido
$servico_id   = $_POST['servico_id'];    // ID do serviço a ser realizado
$data         = $_POST['data'];          // Data do agendamento (YYYY-MM-DD)
$hora_inicio  = $_POST['hora_inicio'];   // Hora de início (HH:MM:SS)

// Hora final: Se não enviada, calcula 1 hora após o início (fallback)
$hora_final   = $_POST['hora_final'] ?? date("H:i:s", strtotime($hora_inicio . " +1 hour"));

// Observações: Opcional, se vazio usa string vazia
$observacoes  = $_POST['observacoes'] ?? '';

// ========== VALIDAÇÃO DE CAMPOS OBRIGATÓRIOS ==========
// Verifica se todos os campos essenciais foram preenchidos
if (empty($cliente_id) || empty($animal_id) || empty($servico_id) || empty($data) || empty($hora_inicio)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// ========== MONTAGEM DA DATA/HORA COMPLETA ==========
// Combina data e hora de início no formato aceito pelo banco
// Exemplo: "2024-12-25" + " " + "14:30:00" = "2024-12-25 14:30:00"
$data_hora = $data . ' ' . $hora_inicio;

try {
    // ========== INSERÇÃO NO BANCO DE DADOS ==========
    // Cria um novo agendamento com status 'pendente' (aguardando aprovação)
    $sql = "INSERT INTO Agendamentos 
            (cliente_id, animal_id, servico_id, data_hora, hora_inicio, hora_final, status, observacoes) 
            VALUES (:cliente_id, :animal_id, :servico_id, :data_hora, :hora_inicio, :hora_final, 'pendente', :observacoes)";

    // ========== PREPARAÇÃO E EXECUÇÃO ==========
    // Prepara a query (prepared statement para segurança)
    $stmt = $pdo->prepare($sql);
    
    // Executa substituindo os placeholders pelos valores
    $stmt->execute([
        ':cliente_id'   => $cliente_id,
        ':animal_id'    => $animal_id,
        ':servico_id'   => $servico_id,
        ':data_hora'    => $data_hora,      // Data + hora combinadas
        ':hora_inicio'  => $hora_inicio,
        ':hora_final'   => $hora_final,
        ':observacoes'  => $observacoes
    ]);

    // ========== RETORNO DE SUCESSO ==========
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // ========== TRATAMENTO DE ERRO ==========
    // Salva o erro detalhado no log do servidor (para debug)
    error_log("Erro ao salvar agendamento: " . $e->getMessage());
    
    // Retorna mensagem genérica para o usuário (segurança)
    echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar. Tente novamente.']);
}

// Garante que nada mais será executado
exit;

// ========== RESUMO DO FUNCIONAMENTO ==========
// Este arquivo CRIA um novo agendamento no sistema
//
// Fluxo completo:
// 1. Valida se é POST e se animal_id foi enviado
// 2. Coleta dados do formulário:
//    - cliente_id: Secretaria escolhe, Cliente usa próprio ID
//    - animal_id, servico_id, data, hora_inicio (obrigatórios)
//    - hora_final (opcional, padrão +1 hora)
//    - observacoes (opcional)
// 3. Valida se campos obrigatórios foram preenchidos
// 4. Combina data + hora_inicio em data_hora
// 5. Insere no banco com status 'pendente'
// 6. Retorna JSON com sucesso ou erro
//
// Diferenças por tipo de usuário:
// - Cliente: Só pode agendar para seus próprios animais
// - Secretaria: Pode escolher o cliente e agendar para qualquer animal
// - Veterinário: Usa seu próprio ID (caso precise agendar algo)
//
// Status inicial: 'pendente' (aguardando aprovação da clínica)
//
// Uso típico:
// - Formulário de novo agendamento
// - Cliente seleciona: animal, serviço, data, hora
// - Secretaria seleciona: cliente, animal, serviço, data, hora
// - Ao submeter, este arquivo cria o registro
?>