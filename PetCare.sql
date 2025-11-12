-- ==========================================================
-- CRIAR BANCO DO ZERO 
-- ==========================================================
DROP DATABASE IF EXISTS PetCare;
CREATE DATABASE PetCare;
USE PetCare;

-- ==========================================================
-- TABELA USUÁRIOS
-- ==========================================================
select * from agendamentos;
alter table usuarios add column foto VARCHAR(255) NULL after nome ;
CREATE TABLE Usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    telefone VARCHAR(15),
    email VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('Cliente', 'Veterinario', 'Secretaria', 'Cuidador') DEFAULT 'Cliente',
    genero ENUM('Masculino', 'Feminino', 'Outro') DEFAULT 'Outro',
    tentativas INT DEFAULT 0,
    datanasc DATE,
    bloqueado_ate DATETIME NULL DEFAULT NULL,
    ultimo_login DATETIME NULL DEFAULT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    criado DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    descricao TEXT(244) NULL
);

-- ==========================================================
-- TABELA ESPÉCIES
-- ==========================================================
CREATE TABLE Especies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL
);

-- ==========================================================
-- TABELA ANIMAIS
-- ==========================================================
CREATE TABLE Animais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    datanasc DATE,
    especie_id INT NOT NULL,
    raca VARCHAR(80),
    porte ENUM('Pequeno', 'Medio', 'Grande'),
    sexo ENUM('Macho', 'Fêmea') DEFAULT NULL,
    usuario_id INT NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    foto VARCHAR(255) DEFAULT NULL,

    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id),
    FOREIGN KEY (especie_id) REFERENCES Especies(id)
);

-- ==========================================================
-- TABELA SERVIÇOS
-- ==========================================================
CREATE TABLE Servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    preco_normal DECIMAL(10,2) NOT NULL,
    preco_feriado DECIMAL(10,2) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================================
-- TABELA AGENDAMENTOS
-- ==========================================================
CREATE TABLE Agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    animal_id INT NOT NULL,
    veterinario_id INT,
    data_hora DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_final TIME NOT NULL,
    status ENUM('pendente', 'confirmado', 'cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    servico_id INT,

    FOREIGN KEY (cliente_id) REFERENCES Usuarios(id),
    FOREIGN KEY (animal_id) REFERENCES Animais(id),
    FOREIGN KEY (veterinario_id) REFERENCES Usuarios(id),
    FOREIGN KEY (servico_id) REFERENCES Servicos(id)
);

-- ==========================================================
-- TABELA EQUIPE
-- ==========================================================
CREATE TABLE Equipe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    usuario_id INT NOT NULL,
    profissao VARCHAR(100),
    descricao TEXT,
    foto VARCHAR(255),

    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id)
);

-- ==========================================================
-- TABELA CONSULTAS
-- ==========================================================
CREATE TABLE Consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    agendamento_id INT NOT NULL UNIQUE,
    veterinario_id INT NOT NULL,
    secretario_id INT NULL,
    data_consulta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diagnostico TEXT,
    tratamento TEXT,
    receita TEXT,
    mensagem TEXT NULL,

    FOREIGN KEY (animal_id) REFERENCES Animais(id),
    FOREIGN KEY (agendamento_id) REFERENCES Agendamentos(id),
    FOREIGN KEY (veterinario_id) REFERENCES Usuarios(id),
    FOREIGN KEY (secretario_id) REFERENCES Usuarios(id)
);

-- ==========================================================
-- TABELA PRONTUÁRIOS
-- ==========================================================
CREATE TABLE Prontuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consulta_id INT NOT NULL,
    observacoes TEXT NOT NULL,
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (consulta_id) REFERENCES Consultas(id)
);

-- ==========================================================
-- TABELAS DE CONFIGURAÇÕES
-- ==========================================================
CREATE TABLE Redef_Senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME DEFAULT NULL,

    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id)
);

CREATE TABLE Logs_Acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    email_tentado VARCHAR(100),
    sucesso BOOLEAN NOT NULL,
    ip_origem VARCHAR(45),
    navegador TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id) REFERENCES Usuarios(id)
);
UPDATE Dias_Trabalhados
SET dia_semana = CASE dia_semana
    WHEN 'Segunda' THEN 'Segunda'
    WHEN 'Terça' THEN 'Terça'
    WHEN 'Quarta' THEN 'Quarta'
    WHEN 'Quinta' THEN 'Quinta'
    WHEN 'Sexta' THEN 'Sexta'
    WHEN 'Sábado' THEN 'Sábado'
    WHEN 'Domingo' THEN 'Domingo'
END;

describe Dias_Trabalhados;
CREATE TABLE Dias_Trabalhados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana ENUM('Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado') NOT NULL,
    horario_abertura TIME NOT NULL,
    horario_fechamento TIME NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Periodos_Inativos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE Feriados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data DATE NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================================
-- DADOS DE TESTE (OPCIONAL)
-- ==========================================================
