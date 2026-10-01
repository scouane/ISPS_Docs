-- =====================================================================
-- ISPS FLOW — Schema da Base de Dados (MySQL / MariaDB)
-- Sistema Digital de Submissão, Tramitação, Despacho e Gestão de
-- Documentação Administrativa do Instituto Superior Politécnico de Songo
-- =====================================================================
-- Ambiente alvo: MySQL 8+ ou MariaDB 10.5+ (XAMPP)
-- Charset: utf8mb4 (suporte a acentuação portuguesa)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS isps_flow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE isps_flow;

-- =====================================================================
-- 1. PERFIS E UTILIZADORES
-- =====================================================================

DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(30) NOT NULL UNIQUE,   -- ESTUDANTE, SECRETARIA, DIRECTOR, ADMIN
  name          VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

INSERT INTO roles (code, name) VALUES
  ('ESTUDANTE', 'Estudante'),
  ('SECRETARIA', 'Secretaria'),
  ('DIRECTOR', 'Director'),
  ('ADMIN', 'Administrador');

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  role_id       INT NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  phone         VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE INDEX idx_users_role ON users(role_id);

-- =====================================================================
-- 2. CURSOS E DIRECÇÕES (destinatários institucionais)
-- =====================================================================

DROP TABLE IF EXISTS courses;
CREATE TABLE courses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(15) NOT NULL UNIQUE,   -- EE, EET, EER, ET, EH, EC, ECM
  name          VARCHAR(150) NOT NULL,
  director_user_id INT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (director_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO courses (code, name) VALUES
  ('EE',  'Engenharia Electrotécnica'),
  ('EET', 'Engenharia Electrónica e Telecomunicações'),
  ('EER', 'Engenharia de Energias Renováveis'),
  ('ET',  'Engenharia de Transportes'),
  ('EH',  'Engenharia Hidráulica'),
  ('EC',  'Engenharia Civil'),
  ('ECM', 'Engenharia e Ciências dos Materiais');

DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,   -- DG, DICOSSER, DICOSAFA, ...
  name          VARCHAR(150) NOT NULL,
  category      ENUM('DIRECCAO_GERAL','DIRECCAO_CENTRAL') NOT NULL,
  responsible_user_id INT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (responsible_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO departments (code, name, category) VALUES
  ('DG',        'Direcção Geral',                          'DIRECCAO_GERAL'),
  ('DICOSSER',  'Direcção de Coordenação de Serviços',      'DIRECCAO_CENTRAL'),
  ('DICOSAFA',  'Direcção de Coordenação Sócio-Afectiva',   'DIRECCAO_CENTRAL');

-- =====================================================================
-- 3. ESTUDANTES
-- =====================================================================

DROP TABLE IF EXISTS students;
CREATE TABLE students (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL UNIQUE,
  student_number  VARCHAR(30) NOT NULL UNIQUE,
  course_id       INT NOT NULL,
  enrollment_year YEAR NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 4. DESTINOS (alvo genérico de encaminhamento — unifica DG / Curso / Dept)
-- =====================================================================
-- Uma "destination" representa um ponto de decisão concreto para onde
-- um processo pode ser encaminhado. Referencia OU um curso OU um
-- departamento, nunca ambos.

DROP TABLE IF EXISTS destinations;
CREATE TABLE destinations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  type          ENUM('DIRECCAO_GERAL','DIRECTOR_CURSO','DIRECCAO_CENTRAL') NOT NULL,
  course_id     INT NULL,
  department_id INT NULL,
  label         VARCHAR(150) NOT NULL,   -- rótulo amigável, ex: "Director do Curso EET"
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT chk_destination_target CHECK (
    (type = 'DIRECTOR_CURSO' AND course_id IS NOT NULL AND department_id IS NULL) OR
    (type = 'DIRECCAO_CENTRAL' AND department_id IS NOT NULL AND course_id IS NULL) OR
    (type = 'DIRECCAO_GERAL' AND course_id IS NULL)
  )
) ENGINE=InnoDB;

-- =====================================================================
-- 5. TIPOS DE PEDIDO E REGRAS DE ENCAMINHAMENTO (Motor de Tramitação)
-- =====================================================================

DROP TABLE IF EXISTS request_types;
CREATE TABLE request_types (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(150) NOT NULL,
  description         TEXT,
  requires_course     TINYINT(1) NOT NULL DEFAULT 1,  -- se depende do curso do estudante
  required_documents  TEXT,        -- lista textual/JSON dos documentos obrigatórios
  default_destination_type ENUM('DIRECCAO_GERAL','DIRECTOR_CURSO','DIRECCAO_CENTRAL') NOT NULL,
  active              TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Regras específicas: permitem sobrepor o destino padrão consoante o curso
-- (ex: um mesmo tipo de pedido pode ir para destinos diferentes por curso)
DROP TABLE IF EXISTS routing_rules;
CREATE TABLE routing_rules (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  request_type_id INT NOT NULL,
  course_id       INT NULL,       -- NULL = regra aplica-se a todos os cursos
  destination_id  INT NOT NULL,
  priority        INT NOT NULL DEFAULT 0,  -- regras mais específicas com prioridade maior
  FOREIGN KEY (request_type_id) REFERENCES request_types(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (destination_id) REFERENCES destinations(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. REQUERIMENTOS (PROCESSOS)
-- =====================================================================

DROP TABLE IF EXISTS requests;
CREATE TABLE requests (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  protocol_number     VARCHAR(30) NOT NULL UNIQUE,   -- ex: ISPS-2026-000123
  student_id          INT NOT NULL,
  request_type_id     INT NOT NULL,
  subject             VARCHAR(200) NOT NULL,
  description         TEXT,
  current_destination_id INT NULL,
  current_status      ENUM(
                         'SUBMETIDO',
                         'EM_TRIAGEM',
                         'ENCAMINHADO',
                         'EM_ANALISE',
                         'DESPACHADO',
                         'COMUNICADO',
                         'ARQUIVADO',
                         'REJEITADO'
                       ) NOT NULL DEFAULT 'SUBMETIDO',
  submitted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (request_type_id) REFERENCES request_types(id),
  FOREIGN KEY (current_destination_id) REFERENCES destinations(id)
) ENGINE=InnoDB;

CREATE INDEX idx_requests_status ON requests(current_status);
CREATE INDEX idx_requests_student ON requests(student_id);
CREATE INDEX idx_requests_destination ON requests(current_destination_id);

-- Sequência auxiliar para gerar números de protocolo de forma atómica
DROP TABLE IF EXISTS protocol_sequence;
CREATE TABLE protocol_sequence (
  year_ref  YEAR PRIMARY KEY,
  last_seq  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- =====================================================================
-- 7. DOCUMENTOS
-- =====================================================================

DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  request_id    INT NOT NULL,
  doc_type      ENUM('REQUERIMENTO','ANEXO','DESPACHO','COMUNICACAO','OUTRO') NOT NULL,
  file_path     VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(100),
  file_size     INT,               -- bytes
  uploaded_by   INT NOT NULL,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_documents_request ON documents(request_id);

-- =====================================================================
-- 8. DESPACHOS
-- =====================================================================

DROP TABLE IF EXISTS dispatches;
CREATE TABLE dispatches (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  request_id      INT NOT NULL,
  dispatched_by   INT NOT NULL,     -- utilizador (director/responsável) que decidiu
  decision        ENUM('DEFERIDO','INDEFERIDO','PENDENTE_INFO','ENCAMINHADO') NOT NULL,
  decision_text   TEXT NOT NULL,
  dispatched_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (dispatched_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. COMUNICAÇÕES (documento gerado a partir do despacho)
-- =====================================================================

DROP TABLE IF EXISTS communications;
CREATE TABLE communications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  request_id    INT NOT NULL,
  dispatch_id   INT NOT NULL,
  document_id   INT NULL,          -- PDF gerado (referência à tabela documents)
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at  DATETIME NULL,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (dispatch_id) REFERENCES dispatches(id) ON DELETE CASCADE,
  FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. HISTÓRICO DO PROCESSO (rastreabilidade / auditoria)
-- =====================================================================

DROP TABLE IF EXISTS request_history;
CREATE TABLE request_history (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  request_id        INT NOT NULL,
  action            VARCHAR(100) NOT NULL,   -- ex: SUBMETIDO, ENCAMINHADO, DESPACHADO
  performed_by      INT NOT NULL,
  from_destination_id INT NULL,
  to_destination_id   INT NULL,
  notes             TEXT,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (performed_by) REFERENCES users(id),
  FOREIGN KEY (from_destination_id) REFERENCES destinations(id),
  FOREIGN KEY (to_destination_id) REFERENCES destinations(id)
) ENGINE=InnoDB;

CREATE INDEX idx_history_request ON request_history(request_id);

-- =====================================================================
-- 11. NOTIFICAÇÕES
-- =====================================================================

DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  request_id    INT NULL,
  message       VARCHAR(500) NOT NULL,
  read_at       DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notifications_user ON notifications(user_id, read_at);

-- =====================================================================
-- 12. DADOS DE EXEMPLO (opcional — remover em produção)
-- =====================================================================

INSERT INTO request_types (name, description, requires_course, default_destination_type) VALUES
  ('Requerimento Académico Geral', 'Pedido genérico dirigido ao Director do Curso', 1, 'DIRECTOR_CURSO'),
  ('Pedido de Declaração', 'Emissão de declarações diversas', 0, 'DIRECCAO_GERAL'),
  ('Pedido Institucional', 'Pedidos dirigidos directamente à Direcção Geral', 0, 'DIRECCAO_GERAL');

SET FOREIGN_KEY_CHECKS = 1;
