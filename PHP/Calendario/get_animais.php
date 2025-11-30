<?php
// Inclui o arquivo de conexão com o banco de dados
include '../conexao.php'; 

// Define que a resposta será em formato JSON
header('Content-Type: application/json');

// ========== VALIDAÇÃO DO PARÂMETRO ==========
// Verifica se o cliente_id foi enviado via GET e se é numérico
// Se não foi enviado OU não é número, retorna array vazio
if (!isset($_GET['cliente_id']) || !is_numeric($_GET['cliente_id'])) {
    echo json_encode([]); // Retorna [] (array vazio) em JSON
    exit;
}

// ========== CONVERSÃO PARA INTEIRO ==========
// Converte o cliente_id para inteiro (segurança adicional)
// (int) garante que será um número inteiro, mesmo que venha como string "123"
$cliente_id = (int)$_GET['cliente_id'];

try {
    // ========== CONFIGURAÇÃO DA QUERY ==========
    // Define o nome da coluna que relaciona animal com cliente
    $coluna_cliente = 'usuario_id'; // Coluna FK que aponta para o dono do animal
    
    // Define o nome da tabela de animais
    $tabela_animais = 'Animais'; 

    // ========== CONSTRUÇÃO DA QUERY ==========
    // Busca ID e nome dos animais que pertencem ao cliente
    // ORDER BY nome = ordena alfabeticamente
    $sql = "SELECT id, nome FROM $tabela_animais WHERE $coluna_cliente = ? ORDER BY nome";
    
    // ========== EXECUÇÃO DA QUERY ==========
    // Prepara a query (prepared statement para evitar SQL Injection)
    $stmt = $pdo->prepare($sql);
    
    // Executa substituindo o ? pelo cliente_id
    $stmt->execute([$cliente_id]);
    
    // Busca todos os resultados como array associativo
    // Cada animal será um array: ['id' => 1, 'nome' => 'Rex']
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========== RETORNA OS ANIMAIS EM JSON ==========
    // Exemplo de retorno: [{"id": 1, "nome": "Rex"}, {"id": 2, "nome": "Miau"}]
    echo json_encode($animais);

} catch (PDOException $e) {
    // ========== TRATAMENTO DE ERRO DO BANCO ==========
    // Define código HTTP 500 (erro interno do servidor)
    http_response_code(500);
    
    // Retorna mensagem genérica para o usuário (segurança)
    echo json_encode(['error' => 'Erro no banco de dados']);
    
    // Salva o erro detalhado no log do servidor (para debug)
    error_log("Erro em get_animais.php: " . $e->getMessage());
    
} catch (Exception $e) {
    // ========== TRATAMENTO DE ERRO GERAL ==========
    // Captura qualquer outro tipo de erro
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
    error_log("Erro em get_animais.php: " . $e->getMessage());
}

// ========== RESUMO DO FUNCIONAMENTO ==========
// 1. Recebe cliente_id via GET (ex: get_animais.php?cliente_id=5)
// 2. Valida se o parâmetro existe e é numérico
// 3. Busca todos os animais que pertencem a esse cliente (usuario_id = cliente_id)
// 4. Retorna array JSON com id e nome dos animais
// 5. Usado para popular dropdown/select de animais no formulário de agendamento
// 
// Exemplo de uso no frontend:
// fetch('get_animais.php?cliente_id=5')
//   .then(response => response.json())
//   .then(animais => {
//     // animais = [{"id": 1, "nome": "Rex"}, {"id": 2, "nome": "Miau"}]
//     // Popular <select> com os animais
//   });
?>