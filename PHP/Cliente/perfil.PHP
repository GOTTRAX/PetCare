<?php
session_start();

// ==================== CONFIGURAÇÕES DE SEGURANÇA ====================
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Headers de segurança
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

include '../conexao.php';

// ==================== CONSTANTES ====================
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', '../../assets/uploads/pets/');
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// ==================== CLASSE DE SEGURANÇA ====================
class SecurityManager
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Gera token CSRF
    public function generateCSRFToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Valida token CSRF
    public function validateCSRFToken($token)
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // Sanitiza entrada
    public function sanitizeInput($data, $type = 'string')
    {
        if (is_array($data)) {
            return array_map(function ($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $data);
        }

        $data = trim($data);

        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'string':
            default:
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
    }

    // Rate limiting
    public function checkRateLimit($action, $maxAttempts = MAX_LOGIN_ATTEMPTS, $timeWindow = LOCKOUT_TIME)
    {
        $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
        }

        $data = $_SESSION[$key];

        // Resetar se passou o tempo
        if (time() - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
            return true;
        }

        // Verificar limite
        if ($data['attempts'] >= $maxAttempts) {
            $waitTime = $timeWindow - (time() - $data['first_attempt']);
            throw new Exception("Muitas tentativas. Aguarde " . ceil($waitTime / 60) . " minutos.");
        }

        $_SESSION[$key]['attempts']++;
        return true;
    }

    // Validar upload de arquivo
    public function validateFileUpload($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo.');
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('Arquivo muito grande. Máximo: 5MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Tipo de arquivo não permitido.');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            throw new Exception('Extensão de arquivo não permitida.');
        }

        // Verificar se é realmente uma imagem
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Arquivo não é uma imagem válida.');
        }

        return true;
    }
}

// ==================== CLASSE DE VALIDAÇÃO ====================
class Validator
{
    public static function validateEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }

        // Validar domínio
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX')) {
            throw new Exception('Domínio de email inválido.');
        }

        return true;
    }

    public static function validatePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) < 10 || strlen($phone) > 11) {
            throw new Exception('Telefone inválido. Use formato: (00) 00000-0000');
        }

        return true;
    }

    public static function validateDate($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new Exception('Data inválida.');
        }

        $today = new DateTime();
        if ($d > $today) {
            throw new Exception('Data não pode ser no futuro.');
        }

        $minDate = new DateTime('1900-01-01');
        if ($d < $minDate) {
            throw new Exception('Data muito antiga.');
        }

        return true;
    }

    public static function validatePassword($password)
    {
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            throw new Exception('Senha deve ter no mínimo ' . MIN_PASSWORD_LENGTH . ' caracteres.');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Senha deve conter pelo menos uma letra maiúscula.');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Senha deve conter pelo menos uma letra minúscula.');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Senha deve conter pelo menos um número.');
        }

        return true;
    }
}

// ==================== CLASSE DE CACHE ====================
class CacheManager
{
    private static $cache = [];

    public static function get($key)
    {
        return self::$cache[$key] ?? null;
    }

    public static function set($key, $value, $ttl = 300)
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }

    public static function has($key)
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        if (self::$cache[$key]['expires'] < time()) {
            unset(self::$cache[$key]);
            return false;
        }

        return true;
    }
}

// Instanciar segurança
$security = SecurityManager::getInstance();

// Gerar token CSRF
$csrfToken = $security->generateCSRFToken();

// Função para converter data do formato input para o formato brasileiro
function converterDataParaBr($data_input)
{
    if (empty($data_input)) return '';
    $partes = explode('-', $data_input);
    if (count($partes) === 3) {
        return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
    }
    return $data_input;
}

// Função para converter data do formato brasileiro para o formato input
function converterDataParaInput($data_br)
{
    if (empty($data_br)) return '';
    $partes = explode('/', $data_br);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data_br;
}

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

// Determinar aba atual
$aba_atual = $_GET['aba'] ?? 'perfil';
$abas_validas = ['perfil', 'pets', 'Agendamentos', 'config'];
if (!in_array($aba_atual, $abas_validas)) {
    $aba_atual = 'perfil';
}

// Variáveis para edição de animais
$editando_animal = false;
$animal_editando = null;
$animal_id_editar = null;

// Verificar se estamos editando um animal
if (isset($_GET['editar_animal'])) {
    $animal_id_editar = (int)$_GET['editar_animal'];
    $editando_animal = true;

    try {
        $stmt = $pdo->prepare("
            SELECT a.*, e.nome as especie_nome 
            FROM Animais a 
            JOIN Especies e ON a.especie_id = e.id 
            WHERE a.id = ? AND a.usuario_id = ?
        ");
        $stmt->execute([$animal_id_editar, $_SESSION["id"]]);
        $animal_editando = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal_editando) {
            $editando_animal = false;
            $_SESSION['mensagem'] = "Animal não encontrado!";
        }
    } catch (PDOException $e) {
        $editando_animal = false;
        $_SESSION['mensagem'] = "Erro ao carregar animal.";
        error_log("Erro ao carregar animal: " . $e->getMessage());
    }
}

// Cancelar edição
if (isset($_GET['cancelar_edicao'])) {
    header("Location: ?aba=pets");
    exit();
}

// ======= PROCESSAMENTO DE FORMULÁRIOS =======
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    try {
        // Validar CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!$security->validateCSRFToken($token)) {
            throw new Exception('Token de segurança inválido. Recarregue a página.');
        }

        // Verificar rate limiting
        $security->checkRateLimit('form_submit', 20, 300);

        if (isset($_POST['atualizar_perfil'])) {
            processarAtualizacaoPerfil($pdo, $security);
        } elseif (isset($_POST['redefinir_senha'])) {
            processarRedefinicaoSenha($pdo, $security);
        } else {
            processarCadastroAnimal($pdo, $security);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// ======= FUNÇÕES DE PROCESSAMENTO =======
function processarAtualizacaoPerfil($pdo, $security)
{
    $nome = $security->sanitizeInput($_POST['nome']);
    $telefone = $security->sanitizeInput($_POST['telefone']);
    $datanasc = $security->sanitizeInput($_POST['datanasc']);
    $genero = $security->sanitizeInput($_POST['genero']);
    $usuario_id = $_SESSION["id"];

    // Validações
    if (strlen($nome) < 3) {
        throw new Exception('Nome deve ter no mínimo 3 caracteres.');
    }

    Validator::validatePhone($telefone);
    Validator::validateDate($datanasc);

    try {
        $sql = "UPDATE Usuarios SET nome = :nome, telefone = :telefone, datanasc = :datanasc, genero = :genero WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':telefone' => $telefone,
            ':datanasc' => $datanasc,
            ':genero' => $genero,
            ':id' => $usuario_id
        ]);

        $_SESSION["nome"] = $nome;
        $_SESSION["telefone"] = $telefone;
        $_SESSION["datanasc"] = converterDataParaBr($datanasc);
        $_SESSION["genero"] = $genero;

        echo json_encode(['status' => 'success', 'message' => 'Perfil atualizado com sucesso!']);
        exit();
    } catch (PDOException $e) {
        error_log("Erro ao atualizar perfil: " . $e->getMessage());
        throw new Exception('Erro ao atualizar perfil. Tente novamente.');
    }
}

function processarRedefinicaoSenha($pdo, $security)
{
    $senha_atual = trim($_POST['senha_atual'] ?? '');
    $nova_senha = trim($_POST['nova_senha'] ?? '');
    $confirmar_senha = trim($_POST['confirmar_senha'] ?? '');
    $usuario_id = $_SESSION["id"];

    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        throw new Exception('Todos os campos são obrigatórios!');
    }

    if ($nova_senha !== $confirmar_senha) {
        throw new Exception('As novas senhas não coincidem!');
    }

    // Validar senha forte
    Validator::validatePassword($nova_senha);

    try {
        $sql = "SELECT senha FROM Usuarios WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new Exception('Usuário não encontrado!');
        }

        if (password_verify($senha_atual, $usuario['senha'])) {
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_ARGON2ID);

            $sql_update = "UPDATE Usuarios SET senha = :senha WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':senha' => $nova_senha_hash,
                ':id' => $usuario_id
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Senha redefinida com sucesso!']);
            exit();
        } else {
            throw new Exception('Senha atual incorreta!');
        }
    } catch (PDOException $e) {
        error_log("Erro ao redefinir senha: " . $e->getMessage());
        throw new Exception('Erro ao redefinir senha. Tente novamente.');
    }
}

function processarCadastroAnimal($pdo, $security)
{
    global $editando_animal, $animal_id_editar;

    $nome = $security->sanitizeInput($_POST['nome']);
    $datanasc = !empty($_POST['datanasc']) ? $security->sanitizeInput($_POST['datanasc']) : null;
    $especie_nome = $security->sanitizeInput($_POST['especie']);
    $raca = !empty($_POST['raca']) ? $security->sanitizeInput($_POST['raca']) : null;
    $sexo = !empty($_POST['sexo']) ? $security->sanitizeInput($_POST['sexo']) : null;
    $porte = !empty($_POST['porte']) ? $security->sanitizeInput($_POST['porte']) : null;
    $usuario_id = $_SESSION["id"];

    // Validar data se fornecida
    if ($datanasc) {
        Validator::validateDate($datanasc);
    }

    // Processar upload da foto
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        try {
            $security->validateFileUpload($_FILES['foto']);

            $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $nome_arquivo = uniqid('pet_', true) . '.' . $extensao;

            if (!file_exists(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0755, true);
            }

            $caminho_completo = UPLOAD_PATH . $nome_arquivo;

            // Redimensionar imagem para economizar espaço
            $imagem = null;
            switch ($extensao) {
                case 'jpg':
                case 'jpeg':
                    $imagem = imagecreatefromjpeg($_FILES['foto']['tmp_name']);
                    break;
                case 'png':
                    $imagem = imagecreatefrompng($_FILES['foto']['tmp_name']);
                    break;
                case 'gif':
                    $imagem = imagecreatefromgif($_FILES['foto']['tmp_name']);
                    break;
                case 'webp':
                    $imagem = imagecreatefromwebp($_FILES['foto']['tmp_name']);
                    break;
            }

            if ($imagem) {
                $largura_original = imagesx($imagem);
                $altura_original = imagesy($imagem);
                $max_width = 800;

                if ($largura_original > $max_width) {
                    $ratio = $max_width / $largura_original;
                    $nova_largura = $max_width;
                    $nova_altura = $altura_original * $ratio;

                    $imagem_redimensionada = imagecreatetruecolor($nova_largura, $nova_altura);
                    imagecopyresampled($imagem_redimensionada, $imagem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura_original, $altura_original);

                    imagejpeg($imagem_redimensionada, $caminho_completo, 85);
                    imagedestroy($imagem_redimensionada);
                } else {
                    move_uploaded_file($_FILES['foto']['tmp_name'], $caminho_completo);
                }

                imagedestroy($imagem);
                $foto = $nome_arquivo;
            }
        } catch (Exception $e) {
            throw new Exception('Erro no upload: ' . $e->getMessage());
        }
    }

    try {
        $stmt_especie = $pdo->prepare("SELECT id FROM Especies WHERE nome = :nome");
        $stmt_especie->execute([':nome' => $especie_nome]);
        $especie = $stmt_especie->fetch(PDO::FETCH_ASSOC);

        if (!$especie) {
            throw new Exception('Espécie não encontrada!');
        }

        $especie_id = $especie['id'];

        if ($editando_animal && $animal_id_editar) {
            if ($foto) {
                $sql = "UPDATE Animais SET nome=:nome, datanasc=:datanasc, especie_id=:especie_id, 
                        raca=:raca, porte=:porte, sexo=:sexo, foto=:foto 
                        WHERE id=:id AND usuario_id=:usuario_id";
                $params = [
                    ':nome' => $nome,
                    ':datanasc' => $datanasc,
                    ':especie_id' => $especie_id,
                    ':raca' => $raca,
                    ':porte' => $porte,
                    ':sexo' => $sexo,
                    ':foto' => $foto,
                    ':id' => $animal_id_editar,
                    ':usuario_id' => $usuario_id
                ];
            } else {
                $sql = "UPDATE Animais SET nome=:nome, datanasc=:datanasc, especie_id=:especie_id, 
                        raca=:raca, porte=:porte, sexo=:sexo 
                        WHERE id=:id AND usuario_id=:usuario_id";
                $params = [
                    ':nome' => $nome,
                    ':datanasc' => $datanasc,
                    ':especie_id' => $especie_id,
                    ':raca' => $raca,
                    ':porte' => $porte,
                    ':sexo' => $sexo,
                    ':id' => $animal_id_editar,
                    ':usuario_id' => $usuario_id
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $mensagem = 'Animal atualizado com sucesso!';
        } else {
            $sql = "INSERT INTO Animais (nome, datanasc, especie_id, raca, porte, sexo, usuario_id, foto)
                    VALUES (:nome, :datanasc, :especie_id, :raca, :porte, :sexo, :usuario_id, :foto)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':datanasc' => $datanasc,
                ':especie_id' => $especie_id,
                ':raca' => $raca,
                ':porte' => $porte,
                ':sexo' => $sexo,
                ':usuario_id' => $usuario_id,
                ':foto' => $foto
            ]);

            $mensagem = 'Animal cadastrado com sucesso!';
        }

        echo json_encode(['status' => 'success', 'message' => $mensagem]);
        exit();
    } catch (PDOException $e) {
        error_log("Erro ao salvar animal: " . $e->getMessage());
        throw new Exception('Erro ao salvar animal. Tente novamente.');
    }
}

// Processar exclusão de animal
if (isset($_GET['excluir_animal'])) {
    $animal_id_excluir = (int)$_GET['excluir_animal'];

    try {
        $stmt_verificar = $pdo->prepare("SELECT id, foto FROM Animais WHERE id = ? AND usuario_id = ?");
        $stmt_verificar->execute([$animal_id_excluir, $_SESSION["id"]]);
        $animal = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

        if ($animal) {
            if ($animal['foto']) {
                $caminho_foto = UPLOAD_PATH . $animal['foto'];
                if (file_exists($caminho_foto)) {
                    unlink($caminho_foto);
                }
            }

            $stmt_excluir = $pdo->prepare("DELETE FROM Animais WHERE id = ?");
            $stmt_excluir->execute([$animal_id_excluir]);

            $_SESSION['mensagem'] = "Animal excluído com sucesso!";
        } else {
            $_SESSION['mensagem'] = "Animal não encontrado!";
        }

        header("Location: ?aba=pets");
        exit();
    } catch (PDOException $e) {
        $_SESSION['mensagem'] = "Erro ao excluir animal.";
        error_log("Erro ao excluir animal: " . $e->getMessage());
        header("Location: ?aba=pets");
        exit();
    }
}

// ======= CARREGAMENTO DE DADOS =======
try {
    $cacheKey = 'usuario_' . $_SESSION["id"];

    if (CacheManager::has($cacheKey)) {
        $usuario = CacheManager::get($cacheKey)['value'];
    } else {
        $sql = "SELECT nome, telefone, email, datanasc, genero FROM vw_usuarios_formatados WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $_SESSION["id"]]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        CacheManager::set($cacheKey, $usuario);
    }
} catch (PDOException $e) {
    $usuario = [];
    error_log("Erro ao carregar usuário: " . $e->getMessage());
}

// Listagem de animais
$animais = [];
try {
    $sql = "SELECT 
                a.id,
                a.nome AS animal_nome, 
                e.nome AS especie, 
                a.raca, 
                a.sexo, 
                a.porte,
                a.foto,
                a.datanasc
            FROM Animais a
            JOIN Especies e ON a.especie_id = e.id
            WHERE a.usuario_id = :usuario_id
            ORDER BY a.nome";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['usuario_id' => $_SESSION["id"]]);
    $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $animais = [];
    error_log("Erro ao carregar animais: " . $e->getMessage());
}

// ======= AGENDAMENTOS COM PAGINAÇÃO =======
$itens_por_pagina = 10;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;
$usuario_id = $_SESSION['id'];

try {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM Agendamentos WHERE cliente_id = :cliente_id");
    $stmt_total->execute([':cliente_id' => $usuario_id]);
    $total_agendamentos = $stmt_total->fetchColumn();
    $total_paginas = max(1, ceil($total_agendamentos / $itens_por_pagina));

    $sql = "SELECT a.id, s.nome AS servico, an.nome AS animal,
                   a.data_hora, a.hora_inicio, a.hora_final, a.status, a.observacoes
            FROM Agendamentos a
            JOIN Servicos s ON a.servico_id = s.id
            JOIN Animais an ON a.animal_id = an.id
            WHERE a.cliente_id = :cliente_id
            ORDER BY a.data_hora DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':cliente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $itens_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($agendamentos) {
        $html_agendamentos = '<div class="agendamentos-wrapper">
            <table class="tabela-agendamentos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Serviço</th>
                    <th>Animal</th>
                    <th>Data</th>
                    <th>Hora Início</th>
                    <th>Hora Final</th>
                    <th>Status</th>
                    <th>Observações</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($agendamentos as $ag) {
            $status_class = match (strtolower($ag['status'])) {
                'confirmado' => 'status-confirmado',
                'pendente' => 'status-pendente',
                'cancelado' => 'status-cancelado',
                default => ''
            };

            $html_agendamentos .= "<tr>
                <td>{$ag['id']}</td>
                <td>" . htmlspecialchars($ag['servico']) . "</td>
                <td>" . htmlspecialchars($ag['animal']) . "</td>
                <td>" . date('d/m/Y', strtotime($ag['data_hora'])) . "</td>
                <td>{$ag['hora_inicio']}</td>
                <td>{$ag['hora_final']}</td>
                <td><span class='status $status_class'>" . htmlspecialchars($ag['status']) . "</span></td>
                <td>" . htmlspecialchars($ag['observacoes'] ?? '') . "</td>
            </tr>";
        }

        $html_agendamentos .= '</tbody></table>';

        $html_agendamentos .= '<div class="paginacao">';

        if ($pagina_atual > 1) {
            $prev = $pagina_atual - 1;
            $html_agendamentos .= "<a href='?aba=Agendamentos&pagina=$prev' class='pagina-btn anterior'>
                <i class='fas fa-chevron-left'></i>
            </a>";
        } else {
            $html_agendamentos .= "<span class='pagina-btn anterior disabled'>
                <i class='fas fa-chevron-left'></i>
            </span>";
        }

        $inicio = max(1, $pagina_atual - 2);
        $fim = min($total_paginas, $inicio + 4);

        if ($inicio > 1) {
            $html_agendamentos .= "<a href='?aba=Agendamentos&pagina=1' class='pagina-btn'>1</a>";
            if ($inicio > 2) $html_agendamentos .= "<span class='pagina-ellipsis'>...</span>";
        }

        for ($i = $inicio; $i <= $fim; $i++) {
            $active = $i == $pagina_atual ? 'active' : '';
            $html_agendamentos .= "<a href='?aba=Agendamentos&pagina=$i' class='pagina-btn $active'>$i</a>";
        }

        if ($fim < $total_paginas) {
            if ($fim < $total_paginas - 1) $html_agendamentos .= "<span class='pagina-ellipsis'>...</span>";
            $html_agendamentos .= "<a href='?aba=Agendamentos&pagina=$total_paginas' class='pagina-btn'>$total_paginas</a>";
        }

        if ($pagina_atual < $total_paginas) {
            $next = $pagina_atual + 1;
            $html_agendamentos .= "<a href='?aba=Agendamentos&pagina=$next' class='pagina-btn proximo'>
                <i class='fas fa-chevron-right'></i>
            </a>";
        } else {
            $html_agendamentos .= "<span class='pagina-btn proximo disabled'>
                <i class='fas fa-chevron-right'></i>
            </span>";
        }

        $html_agendamentos .= '</div></div>';
    } else {
        $html_agendamentos = '<div class="agendamentos-wrapper">
            <div class="no-agendamentos">
                <i class="fas fa-calendar-times"></i>
                <p>Nenhum agendamento encontrado.</p>
            </div>
        </div>';
    }
} catch (PDOException $e) {
    $html_agendamentos = '<div class="agendamentos-wrapper">
        <p style="color:#dc3545; text-align:center;">Erro ao carregar agendamentos.</p>
    </div>';
    error_log("Erro ao carregar agendamentos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - PetShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        :root {
            --primary: #4e9f3d;
            --primary-dark: #1e5128;
            --primary-light: #d8e9a8;
            --secondary: #191a19;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --dark-gray: #64748b;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            overflow-x: hidden;
            padding-top: 80px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        /* ========== LOADING OVERLAY ========== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            text-align: center;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 1.125rem;
        }

        /* ========== HEADER ========== */
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 32px;
            text-align: center;
            animation: fadeInDown 0.6s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
            box-shadow: var(--shadow-lg);
            border: 4px solid var(--white);
            transition: var(--transition);
        }

        .avatar:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: var(--shadow-xl);
        }

        .profile-header h1 {
            color: var(--primary-dark);
            margin-bottom: 8px;
            font-size: 2rem;
            font-weight: 700;
        }

        .profile-header p {
            color: var(--gray-600);
            font-size: 1.125rem;
        }

        /* ========== NAVIGATION TABS ========== */
        .profile-nav {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
            flex-wrap: wrap;
            animation: fadeIn 0.6s ease 0.2s both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-nav button {
            padding: 12px 24px;
            background-color: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: 50px;
            color: var(--gray-700);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }

        .profile-nav button:hover {
            background: linear-gradient(135deg, var(--primary-light) 0%, #c8e6c9 100%);
            border-color: var(--primary);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .profile-nav button.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary-dark);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .profile-nav button i {
            font-size: 1.125rem;
        }

        /* ========== CONTENT SECTIONS ========== */
        .content-section {
            display: none;
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 32px;
            animation: fadeInUp 0.5s ease;
            border-top: 4px solid var(--primary);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-section.active {
            display: block;
        }

        .content-section h2 {
            color: var(--primary-dark);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gray-200);
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content-section h2 i {
            color: var(--primary);
        }

        /* ========== PROFILE INFO GRID ========== */
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-left-color: var(--primary-dark);
        }

        .info-item strong {
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item strong i {
            color: var(--primary);
        }

        /* ========== FORMS ========== */
        .flex-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .form-container,
        .pets-container,
        .config-form-container {
            min-width: 300px;
        }

        .form-animal {
            background: var(--white);
            padding: 28px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            height: 100%;
            border-top: 3px solid var(--primary);
        }

        .form-animal h3 {
            color: var(--primary-dark);
            margin-bottom: 16px;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-animal h3 i {
            color: var(--primary);
        }

        .form-animal p {
            color: var(--gray-600);
            margin-bottom: 20px;
            font-size: 0.9375rem;
        }

        .form-animal label,
        .form-group label {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-animal input,
        .form-animal select,
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            background-color: var(--white);
            transition: var(--transition);
            font-size: 0.9375rem;
            font-family: inherit;
        }

        .form-animal input:focus,
        .form-animal select:focus,
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(78, 159, 61, 0.1);
            background-color: var(--white);
        }

        .form-animal input.error,
        .form-group input.error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-animal input:disabled {
            background-color: var(--gray-100);
            cursor: not-allowed;
        }

        .form-animal button,
        .form-group button {
            margin-top: 24px;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
        }

        .form-animal button:hover,
        .form-group button:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #0d3d1a 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .form-animal button:active {
            transform: translateY(0);
        }

        .form-animal button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .form-animal button i {
            font-size: 1.125rem;
        }

        /* ========== PETS LIST ========== */
        .pets-list {
            margin-top: 20px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .pets-list::-webkit-scrollbar {
            width: 8px;
        }

        .pets-list::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }

        .pets-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .pets-list::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .pet-card {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
            border: 2px solid var(--gray-100);
            box-shadow: var(--shadow-sm);
        }

        .pet-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-light);
        }

        .pet-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light) 0%, #c8e6c9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 24px;
            font-weight: 700;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .pet-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pet-info {
            flex: 1;
        }

        .pet-name {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 8px;
            font-size: 1.125rem;
        }

        .pet-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .pet-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--white);
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
        }

        .pet-detail i {
            color: var(--primary);
        }

        .pet-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-edit,
        .btn-delete {
            padding: 10px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--primary-light) 0%, #c8e6c9 100%);
            color: var(--primary-dark);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            transform: rotate(10deg) scale(1.1);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            color: #f44336;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
            transform: rotate(-10deg) scale(1.1);
        }

        .no-pets {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
            font-style: italic;
        }

        .no-pets i {
            font-size: 60px;
            margin-bottom: 16px;
            opacity: 0.3;
            display: block;
        }

        /* ========== AGENDAMENTOS ========== */
        .agendamentos-wrapper {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }

        .tabela-agendamentos {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .tabela-agendamentos thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .tabela-agendamentos th {
            color: white;
            padding: 18px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .tabela-agendamentos tbody tr {
            transition: var(--transition);
            background: white;
        }

        .tabela-agendamentos tbody tr:nth-child(even) {
            background: var(--gray-50);
        }

        .tabela-agendamentos tbody tr:hover {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(78, 159, 61, 0.15);
        }

        .tabela-agendamentos td {
            padding: 18px 16px;
            border: none;
            font-size: 0.9375rem;
            color: var(--gray-700);
            vertical-align: middle;
        }

        .tabela-agendamentos .status {
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--shadow-sm);
        }

        .tabela-agendamentos .status::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .tabela-agendamentos .status-confirmado {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .tabela-agendamentos .status-confirmado::before {
            background: #28a745;
            box-shadow: 0 0 8px #28a745;
        }

        .tabela-agendamentos .status-pendente {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }

        .tabela-agendamentos .status-pendente::before {
            background: #ffc107;
            box-shadow: 0 0 8px #ffc107;
        }

        .tabela-agendamentos .status-cancelado {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .tabela-agendamentos .status-cancelado::before {
            background: #dc3545;
            box-shadow: 0 0 8px #dc3545;
        }

        .no-agendamentos {
            text-align: center;
            padding: 60px 30px;
            color: var(--gray-500);
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-radius: 16px;
            border: 2px dashed var(--gray-200);
        }

        .no-agendamentos i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
            display: block;
        }

        /* ========== PAGINAÇÃO ========== */
        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
            padding-top: 24px;
            flex-wrap: wrap;
        }

        .pagina-btn {
            min-width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: var(--primary-dark);
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .pagina-btn:hover:not(.disabled):not(.active) {
            background: linear-gradient(135deg, var(--primary-light) 0%, #c8e6c9 100%);
            border-color: var(--primary);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .pagina-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-color: var(--primary-dark);
            font-weight: 700;
            cursor: default;
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .pagina-btn.disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            border-color: var(--gray-200);
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.6;
        }

        .pagina-ellipsis {
            color: var(--gray-500);
            font-weight: 600;
            padding: 0 8px;
            font-size: 1.25rem;
        }

        /* ========== CONFIG SECTION ========== */
        .config-flex-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 42px;
            cursor: pointer;
            color: var(--gray-500);
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn-logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
            margin-top: 32px;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%) !important;
        }

        /* ========== PASSWORD STRENGTH ========== */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
            display: none;
        }

        .password-strength.active {
            display: block;
        }

        .password-strength-bar {
            height: 100%;
            transition: var(--transition);
            border-radius: 2px;
        }

        .password-strength-text {
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 600;
        }

        /* ========== IMAGE PREVIEW ========== */
        .image-preview {
            margin-top: 12px;
            text-align: center;
            animation: fadeIn 0.3s ease;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            border: 3px solid var(--primary-light);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .tabela-agendamentos {
                font-size: 0.875rem;
            }

            .tabela-agendamentos th,
            .tabela-agendamentos td {
                padding: 14px 12px;
            }
        }

        @media (max-width: 992px) {

            .flex-container,
            .config-flex-container {
                grid-template-columns: 1fr;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .agendamentos-wrapper {
                padding: 16px;
                overflow-x: auto;
            }

            .tabela-agendamentos {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .profile-header h1 {
                font-size: 1.5rem;
            }

            .avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .profile-nav {
                flex-direction: column;
                align-items: stretch;
            }

            .profile-nav button {
                width: 100%;
                justify-content: center;
            }

            .content-section {
                padding: 20px;
            }

            .content-section h2 {
                font-size: 1.25rem;
            }

            .pet-card {
                flex-direction: column;
                text-align: center;
            }

            .pet-actions {
                justify-content: center;
                margin-top: 12px;
            }

            .tabela-agendamentos th,
            .tabela-agendamentos td {
                padding: 12px 8px;
                font-size: 0.8125rem;
            }

            .tabela-agendamentos .status {
                padding: 6px 12px;
                font-size: 0.6875rem;
            }

            .paginacao {
                gap: 6px;
            }

            .pagina-btn {
                min-width: 40px;
                height: 40px;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 480px) {
            .profile-header h1 {
                font-size: 1.25rem;
            }

            .profile-header p {
                font-size: 1rem;
            }

            .pet-detail {
                font-size: 0.8125rem;
            }

            .form-animal,
            .content-section {
                padding: 16px;
            }

            .agendamentos-wrapper {
                padding: 12px;
                border-radius: 12px;
            }

            .pagina-btn.anterior,
            .pagina-btn.proximo {
                width: 44px;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processando...</div>
        </div>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar">
                <?php
                $nome_completo = $_SESSION["nome"];
                $partes = explode(' ', trim($nome_completo));
                $inicial_nome = strtoupper(mb_substr($partes[0], 0, 1));
                $inicial_sobrenome = isset($partes[1]) ? strtoupper(mb_substr($partes[1], 0, 1)) : '';
                echo $inicial_nome . $inicial_sobrenome;
                ?>
            </div>
            <h1>Olá, <?= explode(' ', $_SESSION["nome"])[0] ?>!</h1>
            <p>Bem-vindo ao seu perfil PetShop</p>
        </div>

        <!-- Navigation -->
        <div class="profile-nav">
            <button class="<?= $aba_atual === 'perfil' ? 'active' : '' ?>" onclick="mostrarSecao('perfil')">
                <i class="fas fa-user"></i> Perfil
            </button>
            <button class="<?= $aba_atual === 'pets' ? 'active' : '' ?>" onclick="mostrarSecao('pets')">
                <i class="fas fa-paw"></i> Meus Pets
            </button>
            <button class="<?= $aba_atual === 'Agendamentos' ? 'active' : '' ?>" onclick="mostrarSecao('Agendamentos')">
                <i class="fas fa-calendar"></i> Agendamentos
            </button>
            <button class="<?= $aba_atual === 'config' ? 'active' : '' ?>" onclick="mostrarSecao('config')">
                <i class="fas fa-cog"></i> Configurações
            </button>
        </div>

        <!-- Profile Section -->
        <div id="perfil" class="content-section <?= $aba_atual === 'perfil' ? 'active' : '' ?>">
            <h2><i class="fas fa-id-card"></i> Informações do Perfil</h2>
            <div class="profile-info">
                <div class="info-item">
                    <strong><i class="fas fa-signature"></i> Nome Completo</strong>
                    <?= htmlspecialchars($usuario['nome'] ?? $_SESSION["nome"]) ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-phone"></i> Telefone</strong>
                    <?= htmlspecialchars($usuario['telefone'] ?? $_SESSION["telefone"]) ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-envelope"></i> E-mail</strong>
                    <?= htmlspecialchars($usuario['email'] ?? $_SESSION["email"]) ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-birthday-cake"></i> Data de Nascimento</strong>
                    <?= htmlspecialchars($usuario['datanasc'] ?? $_SESSION["datanasc"]) ?>
                </div>
                <div class="info-item">
                    <strong><i class="fas fa-venus-mars"></i> Gênero</strong>
                    <?= htmlspecialchars($usuario['genero'] ?? $_SESSION["genero"]) ?>
                </div>
            </div>
        </div>

        <!-- Pets Section -->
        <div id="pets" class="content-section <?= $aba_atual === 'pets' ? 'active' : '' ?>">
            <h2><i class="fas fa-paw"></i> Meus Pets</h2>
            <div class="flex-container">
                <!-- Add Pet Form -->
                <div class="form-container">
                    <div class="form-animal">
                        <h3>
                            <i class="fas <?= $editando_animal ? 'fa-edit' : 'fa-plus-circle' ?>"></i>
                            <?= $editando_animal ? 'Editar Pet' : 'Cadastrar Novo Pet' ?>
                        </h3>
                        <p><?= $editando_animal ? 'Edite os dados do seu animal de estimação.' : 'Preencha os dados do seu animal de estimação.' ?></p>

                        <?php if ($editando_animal): ?>
                            <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                                <span>Editando: <strong><?= htmlspecialchars($animal_editando['nome']) ?></strong></span>
                                <a href="?aba=pets&cancelar_edicao=1" style="color: #1976d2; text-decoration: none;">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        <?php endif; ?>

                        <form id="formPet" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <?php if ($editando_animal): ?>
                                <input type="hidden" name="animal_id" value="<?= $animal_editando['id'] ?>">
                            <?php endif; ?>

                            <label for="pet_nome">Nome do Pet: <span style="color: red;">*</span></label>
                            <input type="text" name="nome" id="pet_nome" required placeholder="Ex: Thor" minlength="2" maxlength="50"
                                value="<?= $editando_animal ? htmlspecialchars($animal_editando['nome']) : '' ?>">

                            <label for="pet_especie">Espécie: <span style="color: red;">*</span></label>
                            <select name="especie" id="pet_especie" required>
                                <option value="">Selecione</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT DISTINCT nome FROM Especies ORDER BY nome ASC");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = ($editando_animal && $animal_editando['especie_nome'] == $row['nome']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($row['nome']) . "' $selected>" . htmlspecialchars($row['nome']) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option disabled>Erro ao carregar espécies</option>";
                                }
                                ?>
                            </select>

                            <label for="pet_raca">Raça:</label>
                            <input type="text" name="raca" id="pet_raca" placeholder="Ex: Golden Retriever" maxlength="50"
                                value="<?= $editando_animal ? htmlspecialchars($animal_editando['raca']) : '' ?>">

                            <label for="pet_datanasc">Data Estimada de Nascimento:</label>
                            <input type="date" name="datanasc" id="pet_datanasc"
                                min="1990-01-01" max="<?= date('Y-m-d') ?>"
                                value="<?= $editando_animal && $animal_editando['datanasc'] ? $animal_editando['datanasc'] : '' ?>">

                            <label for="pet_foto">Foto do Pet (máx. 5MB):</label>
                            <input type="file" name="foto" id="pet_foto" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small style="color: var(--gray-600); display: block; margin-top: 4px;">
                                Formatos: JPG, PNG, GIF, WEBP
                            </small>
                            <?php if ($editando_animal && $animal_editando['foto']): ?>
                                <div style="margin-top: 8px;">
                                    <small>Foto atual: <?= htmlspecialchars($animal_editando['foto']) ?></small>
                                </div>
                            <?php endif; ?>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                                <div>
                                    <label for="pet_sexo">Sexo:</label>
                                    <select name="sexo" id="pet_sexo">
                                        <option value="">Não definido</option>
                                        <option value="Macho" <?= ($editando_animal && $animal_editando['sexo'] == 'Macho') ? 'selected' : '' ?>>Macho</option>
                                        <option value="Fêmea" <?= ($editando_animal && $animal_editando['sexo'] == 'Fêmea') ? 'selected' : '' ?>>Fêmea</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="pet_porte">Porte:</label>
                                    <select name="porte" id="pet_porte">
                                        <option value="">Não definido</option>
                                        <option value="Pequeno" <?= ($editando_animal && $animal_editando['porte'] == 'Pequeno') ? 'selected' : '' ?>>Pequeno</option>
                                        <option value="Medio" <?= ($editando_animal && $animal_editando['porte'] == 'Medio') ? 'selected' : '' ?>>Médio</option>
                                        <option value="Grande" <?= ($editando_animal && $animal_editando['porte'] == 'Grande') ? 'selected' : '' ?>>Grande</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit">
                                <i class="fas fa-save"></i>
                                <?= $editando_animal ? 'Atualizar Pet' : 'Cadastrar Pet' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Pets List -->
                <div class="pets-container">
                    <div class="form-animal">
                        <h3><i class="fas fa-list"></i> Lista de Pets</h3>
                        <div class="pets-list" id="petsList">
                            <?php if (!empty($animais)): ?>
                                <?php foreach ($animais as $animal): ?>
                                    <div class="pet-card">
                                        <div class="pet-icon">
                                            <?php if (!empty($animal['foto'])): ?>
                                                <img src="../../assets/uploads/pets/<?= htmlspecialchars($animal['foto']) ?>"
                                                    alt="<?= htmlspecialchars($animal['animal_nome']) ?>">
                                            <?php else: ?>
                                                <?= $animal['especie'] == 'Cachorro' ? '🐕' : ($animal['especie'] == 'Gato' ? '🐈' : ($animal['especie'] == 'Hamster' ? '🐹' : ($animal['especie'] == 'Peixe' ? '🐠' : '🐾'))) ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="pet-info">
                                            <div class="pet-name"><?= htmlspecialchars($animal['animal_nome']) ?></div>
                                            <div class="pet-details">
                                                <span class="pet-detail">
                                                    <i class="fas fa-dog"></i>
                                                    <?= htmlspecialchars($animal['especie']) ?>
                                                </span>
                                                <?php if ($animal['raca']): ?>
                                                    <span class="pet-detail">
                                                        <i class="fas fa-dna"></i>
                                                        <?= htmlspecialchars($animal['raca']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($animal['sexo']): ?>
                                                    <span class="pet-detail">
                                                        <i class="fas fa-venus-mars"></i>
                                                        <?= htmlspecialchars($animal['sexo']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($animal['porte']): ?>
                                                    <span class="pet-detail">
                                                        <i class="fas fa-weight-hanging"></i>
                                                        <?= htmlspecialchars($animal['porte']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($animal['datanasc']): ?>
                                                    <span class="pet-detail">
                                                        <i class="fas fa-birthday-cake"></i>
                                                        <?= date('d/m/Y', strtotime($animal['datanasc'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="pet-actions">
                                            <a href="?editar_animal=<?= $animal['id'] ?>&aba=pets" class="btn-edit" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?excluir_animal=<?= $animal['id'] ?>&aba=pets" class="btn-delete" title="Excluir"
                                                onclick="return confirm('Tem certeza que deseja excluir <?= htmlspecialchars($animal['animal_nome']) ?>?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-pets">
                                    <i class="fas fa-paw"></i>
                                    <p>Você ainda não cadastrou nenhum pet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agendamentos Section -->
        <div id="Agendamentos" class="content-section <?= $aba_atual === 'Agendamentos' ? 'active' : '' ?>">
            <h2><i class="fas fa-calendar"></i> Meus Agendamentos</h2>
            <div id="listaAgendamentos">
                <?= $html_agendamentos ?>
            </div>
        </div>

        <!-- Config Section -->
        <div id="config" class="content-section <?= $aba_atual === 'config' ? 'active' : '' ?>">
            <h2><i class="fas fa-cog"></i> Configurações da Conta</h2>

            <div class="config-flex-container">
                <!-- Editar Perfil -->
                <div class="config-form-container">
                    <div class="form-animal">
                        <h3><i class="fas fa-user-edit"></i> Editar Perfil</h3>
                        <form id="formPerfil" class="form-config">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                            <div class="form-group">
                                <label for="perfil_nome">Nome Completo <span style="color: red;">*</span></label>
                                <input type="text" id="perfil_nome" name="nome"
                                    value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                                    required minlength="3" maxlength="100">
                            </div>

                            <div class="form-group">
                                <label for="perfil_email">E-mail</label>
                                <input type="email" id="perfil_email" name="email"
                                    value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                                    disabled title="O e-mail não pode ser alterado">
                            </div>

                            <div class="form-group">
                                <label for="perfil_telefone">Telefone <span style="color: red;">*</span></label>
                                <input type="tel" id="perfil_telefone" name="telefone"
                                    value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>"
                                    required placeholder="(00) 00000-0000">
                            </div>

                            <div class="form-group">
                                <label for="perfil_datanasc">Data de Nascimento <span style="color: red;">*</span></label>
                                <input type="date" id="perfil_datanasc" name="datanasc"
                                    value="<?= converterDataParaInput($usuario['datanasc'] ?? '') ?>"
                                    required min="1900-01-01" max="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label for="perfil_genero">Gênero <span style="color: red;">*</span></label>
                                <select id="perfil_genero" name="genero" required>
                                    <option value="">Selecione...</option>
                                    <option value="Masculino" <?= ($usuario['genero'] ?? '') == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="Feminino" <?= ($usuario['genero'] ?? '') == 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                                    <option value="Outro" <?= ($usuario['genero'] ?? '') == 'Outro' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>

                            <button type="submit" name="atualizar_perfil" class="btn-salvar">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="config-form-container">
                    <div class="form-animal">
                        <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px;">
                            <strong>Requisitos:</strong> Mínimo 8 caracteres, 1 maiúscula, 1 minúscula e 1 número
                        </p>
                        <form id="formSenha">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                            <div class="form-group">
                                <label for="senha_atual">Senha Atual: <span style="color: red;">*</span></label>
                                <input type="password" name="senha_atual" id="senha_atual" required minlength="6">
                                <span class="password-toggle" onclick="togglePassword('senha_atual')">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="nova_senha">Nova Senha: <span style="color: red;">*</span></label>
                                <input type="password" name="nova_senha" id="nova_senha" required minlength="8">
                                <span class="password-toggle" onclick="togglePassword('nova_senha')">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                                <div class="password-strength" id="passwordStrength">
                                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                </div>
                                <div class="password-strength-text" id="passwordStrengthText"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirmar_senha">Confirmar Nova Senha: <span style="color: red;">*</span></label>
                                <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="8">
                                <span class="password-toggle" onclick="togglePassword('confirmar_senha')">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                            </div>

                            <button type="submit"><i class="fas fa-key"></i> Alterar Senha</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <div style="text-align: center; margin-top: 20px;">
                <form action="../logout.php" method="post">
                    <button type="submit" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair da Conta</button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../header.php'; ?>

    <script>
        // ====== CONFIGURAÇÕES ======
        const CONFIG = {
            alertDuration: 5000,
            animationDelay: 300,
            scrollBehavior: 'smooth'
        };

        // ====== GERENCIAMENTO DE NOTIFICAÇÕES ======
        const NotificationManager = {
            container: null,

            init() {
                this.createContainer();
            },

            createContainer() {
                if (!document.getElementById('notification-container')) {
                    this.container = document.createElement('div');
                    this.container.id = 'notification-container';
                    this.container.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 10000;
                        display: flex;
                        flex-direction: column;
                        gap: 12px;
                        max-width: 400px;
                    `;
                    document.body.appendChild(this.container);
                } else {
                    this.container = document.getElementById('notification-container');
                }
            },

            show(mensagem, tipo = 'success', duracao = CONFIG.alertDuration) {
                if (!this.container) this.createContainer();

                const notification = document.createElement('div');
                notification.className = `notification ${tipo}`;
                notification.style.cssText = `
                    background: ${tipo === 'success' ? 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)' : 
                                'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)'};
                    color: ${tipo === 'success' ? '#155724' : '#721c24'};
                    padding: 16px 20px;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    animation: slideInRight 0.4s ease;
                    border-left: 4px solid ${tipo === 'success' ? '#10b981' : '#ef4444'};
                    cursor: pointer;
                    font-weight: 500;
                `;

                const icon = tipo === 'success' ? 'check-circle' : 'exclamation-circle';
                notification.innerHTML = `
                    <i class="fas fa-${icon}" style="font-size: 1.25rem;"></i>
                    <span style="flex: 1;">${this.sanitize(mensagem)}</span>
                    <i class="fas fa-times" style="opacity: 0.7; cursor: pointer;"></i>
                `;

                if (!document.getElementById('notification-styles')) {
                    const style = document.createElement('style');
                    style.id = 'notification-styles';
                    style.textContent = `
                        @keyframes slideInRight {
                            from { transform: translateX(100%); opacity: 0; }
                            to { transform: translateX(0); opacity: 1; }
                        }
                        @keyframes slideOutRight {
                            from { transform: translateX(0); opacity: 1; }
                            to { transform: translateX(100%); opacity: 0; }
                        }
                    `;
                    document.head.appendChild(style);
                }

                this.container.appendChild(notification);

                const closeNotification = () => {
                    notification.style.animation = 'slideOutRight 0.4s ease';
                    setTimeout(() => notification.remove(), 400);
                };

                notification.querySelector('.fa-times').addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeNotification();
                });

                notification.addEventListener('click', closeNotification);
                setTimeout(closeNotification, duracao);
            },

            sanitize(str) {
                const temp = document.createElement('div');
                temp.textContent = str;
                return temp.innerHTML;
            }
        };

        // ====== LOADING OVERLAY ======
        const LoadingManager = {
            overlay: null,

            init() {
                this.overlay = document.getElementById('loadingOverlay');
            },

            show(text = 'Processando...') {
                if (this.overlay) {
                    this.overlay.querySelector('.loading-text').textContent = text;
                    this.overlay.classList.add('active');
                }
            },

            hide() {
                if (this.overlay) {
                    this.overlay.classList.remove('active');
                }
            }
        };

        // ====== VALIDAÇÃO DE SENHA FORTE ======
        const PasswordStrengthChecker = {
            init() {
                const novaSenhaInput = document.getElementById('nova_senha');
                if (novaSenhaInput) {
                    novaSenhaInput.addEventListener('input', (e) => {
                        this.checkStrength(e.target.value);
                    });
                }
            },

            checkStrength(password) {
                const strengthBar = document.getElementById('passwordStrengthBar');
                const strengthText = document.getElementById('passwordStrengthText');
                const strengthContainer = document.getElementById('passwordStrength');

                if (!password) {
                    strengthContainer.classList.remove('active');
                    return;
                }

                strengthContainer.classList.add('active');

                let strength = 0;
                const checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };

                strength = Object.values(checks).filter(Boolean).length;

                const colors = ['#ef4444', '#f59e0b', '#f59e0b', '#10b981', '#10b981'];
                const texts = ['Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];

                strengthBar.style.width = widths[strength - 1] || '0%';
                strengthBar.style.background = colors[strength - 1] || '#ef4444';
                strengthText.textContent = texts[strength - 1] || '';
                strengthText.style.color = colors[strength - 1] || '#ef4444';
            }
        };

        // ====== MÁSCARA DE TELEFONE ======
        const PhoneMask = {
            init() {
                const phoneInput = document.getElementById('perfil_telefone');
                if (phoneInput) {
                    phoneInput.addEventListener('input', (e) => {
                        this.applyMask(e.target);
                    });
                }
            },

            applyMask(input) {
                let value = input.value.replace(/\D/g, '');

                if (value.length <= 11) {
                    value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                    value = value.replace(/(\d)(\d{4})$/, '$1-$2');
                }

                input.value = value;
            }
        };

        // ====== PREVIEW DE IMAGEM ======
        const ImagePreview = {
            init() {
                const fotoInput = document.getElementById('pet_foto');
                if (fotoInput) {
                    fotoInput.addEventListener('change', (e) => {
                        this.previewImage(e.target);
                    });
                }
            },

            previewImage(input) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];

                    // Validar tamanho
                    if (file.size > 5 * 1024 * 1024) {
                        NotificationManager.show('Arquivo muito grande! Máximo: 5MB', 'error');
                        input.value = '';
                        return;
                    }

                    // Validar tipo
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        NotificationManager.show('Tipo de arquivo inválido!', 'error');
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();

                    reader.onload = (e) => {
                        let preview = input.parentElement.querySelector('.image-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.className = 'image-preview';
                            input.parentElement.appendChild(preview);
                        }

                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <p style="margin-top: 8px; font-size: 0.875rem; color: var(--gray-600);">
                                Preview da imagem
                            </p>
                        `;
                    };

                    reader.readAsDataURL(file);
                }
            }
        };

        // ====== ALERTA DE DADOS NÃO SALVOS ======
        const UnsavedChangesAlert = {
            formChanged: false,

            init() {
                const forms = document.querySelectorAll('form:not([action])');

                forms.forEach(form => {
                    const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');

                    inputs.forEach(input => {
                        input.addEventListener('input', () => {
                            this.formChanged = true;
                        });
                    });

                    form.addEventListener('submit', () => {
                        this.formChanged = false;
                    });
                });

                window.addEventListener('beforeunload', (e) => {
                    if (this.formChanged) {
                        e.preventDefault();
                        e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair?';
                        return e.returnValue;
                    }
                });
            }
        };

        // ====== GERENCIAMENTO DE ABAS ======
        const TabManager = {
            currentTab: null,

            init() {
                this.bindEvents();
                this.loadActiveTab();
            },

            bindEvents() {
                document.querySelectorAll('.profile-nav button').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const section = e.currentTarget.textContent.trim().toLowerCase();
                        this.switchTab(this.getSectionId(section));
                    });
                });
            },

            getSectionId(text) {
                const map = {
                    'perfil': 'perfil',
                    'meus pets': 'pets',
                    'agendamentos': 'Agendamentos',
                    'configurações': 'config'
                };
                return map[text] || 'perfil';
            },

            loadActiveTab() {
                const urlParams = new URLSearchParams(window.location.search);
                const aba = urlParams.get('aba') || 'perfil';
                this.switchTab(aba, false);
            },

            switchTab(sectionId, updateUrl = true) {
                document.querySelectorAll('.profile-nav button').forEach(btn =>
                    btn.classList.remove('active')
                );
                document.querySelectorAll('.content-section').forEach(section =>
                    section.classList.remove('active')
                );

                const section = document.getElementById(sectionId);
                if (section) {
                    section.classList.add('active');
                    this.currentTab = sectionId;

                    document.querySelectorAll('.profile-nav button').forEach(btn => {
                        const btnText = btn.textContent.trim().toLowerCase();
                        if (this.getSectionId(btnText) === sectionId) {
                            btn.classList.add('active');
                        }
                    });

                    if (updateUrl) {
                        const url = new URL(window.location);
                        url.searchParams.set('aba', sectionId);
                        window.history.pushState({}, '', url);
                    }

                    window.scrollTo({
                        top: 0,
                        behavior: CONFIG.scrollBehavior
                    });
                }
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
                const formPerfil = document.getElementById('formPerfil');
                if (formPerfil) {
                    formPerfil.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const formData = new FormData(formPerfil);
                        formData.append('atualizar_perfil', '1');
                        this.submitFormData(formPerfil, formData, 'Perfil atualizado com sucesso!');
                    });
                }

                const formSenha = document.getElementById('formSenha');
                if (formSenha) {
                    formSenha.addEventListener('submit', (e) => {
                        e.preventDefault();

                        const novaSenha = formSenha.querySelector('#nova_senha').value;
                        const confirmarSenha = formSenha.querySelector('#confirmar_senha').value;

                        if (novaSenha !== confirmarSenha) {
                            NotificationManager.show('As senhas não coincidem!', 'error');
                            return;
                        }

                        if (novaSenha.length < 8) {
                            NotificationManager.show('A nova senha deve ter no mínimo 8 caracteres!', 'error');
                            return;
                        }

                        const formData = new FormData(formSenha);
                        formData.append('redefinir_senha', '1');
                        this.submitFormData(formSenha, formData, 'Senha alterada com sucesso!', true);
                    });
                }

                const formPet = document.getElementById('formPet');
                if (formPet) {
                    formPet.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const isEditing = formPet.querySelector('input[name="animal_id"]');
                        const message = isEditing ?
                            'Pet atualizado com sucesso!' :
                            'Pet cadastrado com sucesso!';
                        this.submitForm(formPet, message);
                    });
                }
            },

            setupValidation() {
                document.addEventListener('blur', (e) => {
                    if (e.target.matches('input[required], select[required], textarea[required]')) {
                        this.validateField(e.target);
                    }
                }, true);
            },

            validateField(field) {
                if (field.validity.valid) {
                    field.style.borderColor = '';
                    field.classList.remove('error');
                    return true;
                } else {
                    field.style.borderColor = '#ef4444';
                    field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                    field.classList.add('error');
                    return false;
                }
            },

            async submitFormData(form, formData, successMessage, resetOnSuccess = false) {
                if (this.submittingForms.has(form)) {
                    NotificationManager.show('Aguarde o processamento anterior', 'error', 2000);
                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                this.submittingForms.add(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalHTML = submitBtn?.innerHTML;

                try {
                    LoadingManager.show('Salvando alterações...');

                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                        submitBtn.disabled = true;
                    }

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        NotificationManager.show(successMessage, 'success');

                        if (resetOnSuccess) {
                            form.reset();
                        }

                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        NotificationManager.show(data.message || 'Erro ao processar', 'error');
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    NotificationManager.show('Erro de conexão. Tente novamente.', 'error');
                } finally {
                    LoadingManager.hide();
                    if (submitBtn) {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                    this.submittingForms.delete(form);
                }
            },

            async submitForm(form, successMessage, resetOnSuccess = false) {
                if (this.submittingForms.has(form)) {
                    NotificationManager.show('Aguarde o processamento anterior', 'error', 2000);
                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                this.submittingForms.add(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalHTML = submitBtn?.innerHTML;

                try {
                    LoadingManager.show('Salvando...');

                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                        submitBtn.disabled = true;
                    }

                    const formData = new FormData(form);
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        NotificationManager.show(successMessage, 'success');

                        if (resetOnSuccess) {
                            form.reset();
                        }

                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        NotificationManager.show(data.message || 'Erro ao processar', 'error');
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    NotificationManager.show('Erro de conexão. Tente novamente.', 'error');
                } finally {
                    LoadingManager.hide();
                    if (submitBtn) {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                    this.submittingForms.delete(form);
                }
            }
        };

        // ====== GERENCIAMENTO DE SENHA ======
        const PasswordManager = {
            init() {
                this.bindEvents();
            },

            bindEvents() {
                document.querySelectorAll('.password-toggle').forEach(toggle => {
                    toggle.addEventListener('click', (e) => {
                        const input = e.currentTarget.previousElementSibling;
                        this.toggleVisibility(input, e.currentTarget);
                    });
                });
            },

            toggleVisibility(input, toggle) {
                const icon = toggle.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                }
            }
        };

        // ====== CONFIRMAÇÃO DE EXCLUSÃO ======
        const ConfirmManager = {
            init() {
                document.querySelectorAll('.btn-delete').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        if (!confirm('Tem certeza que deseja excluir este pet?')) {
                            e.preventDefault();
                        }
                    });
                });
            }
        };

        // ====== FUNÇÃO GLOBAL PARA MOSTRAR SEÇÃO ======
        function mostrarSecao(sectionId) {
            TabManager.switchTab(sectionId);
        }

        // ====== FUNÇÃO GLOBAL PARA TOGGLE DE SENHA ======
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                const toggle = input.nextElementSibling;
                PasswordManager.toggleVisibility(input, toggle);
            }
        }

        // ====== FUNÇÃO GLOBAL PARA MOSTRAR ALERTA ======
        function mostrarAlerta(mensagem, tipo) {
            NotificationManager.show(mensagem, tipo);
        }

        // ====== INICIALIZAÇÃO ======
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Inicializando sistema de perfil melhorado...');

            // Inicializar módulos
            NotificationManager.init();
            LoadingManager.init();
            TabManager.init();
            FormManager.init();
            PasswordManager.init();
            PasswordStrengthChecker.init();
            PhoneMask.init();
            ImagePreview.init();
            UnsavedChangesAlert.init();
            ConfirmManager.init();

            // Animação dos cards de pets
            const petCards = document.querySelectorAll('.pet-card');
            petCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Mostrar mensagens de sessão
            <?php if (isset($_SESSION['mensagem'])): ?>
                NotificationManager.show('<?= addslashes($_SESSION['mensagem']) ?>', 'success');
                <?php unset($_SESSION['mensagem']); ?>
            <?php endif; ?>

            console.log('✅ Sistema inicializado com sucesso!');
        });

        // ====== TRATAMENTO DE ERROS GLOBAL ======
        window.addEventListener('error', (e) => {
            console.error('Erro capturado:', e.error);
        });

        window.addEventListener('unhandledrejection', (e) => {
            console.error('Promise rejeitada:', e.reason);
        });
    </script>
</body>

</html>