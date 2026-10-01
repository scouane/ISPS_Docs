<?php
/**
 * Executa uma vez, via linha de comando, para criar dados de teste:
 *   php scripts/seed_users.php
 *
 * Cria: 1 admin, 1 secretaria, 1 director (do curso EET), 1 estudante (EET).
 * Todas as passwords de teste: "Teste123!"
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDB();
$pass = 'Teste123!';

$adminId = createUser($pdo, 'Administrador do Sistema', 'admin@isps.ac.mz', $pass, 'ADMIN');
$secId   = createUser($pdo, 'Secretaria Geral', 'secretaria@isps.ac.mz', $pass, 'SECRETARIA');
$dirId   = createUser($pdo, 'Director do Curso EET', 'director.eet@isps.ac.mz', $pass, 'DIRECTOR');
$estId   = createUser($pdo, 'Estudante Teste', 'estudante@isps.ac.mz', $pass, 'ESTUDANTE');

// Associa o director ao curso EET
$pdo->prepare("UPDATE courses SET director_user_id = :uid WHERE code = 'EET'")
    ->execute(['uid' => $dirId]);

// Cria o destino "Director do Curso EET" se ainda não existir
$stmt = $pdo->prepare("SELECT id FROM courses WHERE code = 'EET'");
$stmt->execute();
$eetId = $stmt->fetchColumn();

$pdo->prepare(
    "INSERT INTO destinations (type, course_id, label)
     SELECT 'DIRECTOR_CURSO', :course_id, 'Director do Curso EET'
     WHERE NOT EXISTS (
         SELECT 1 FROM destinations WHERE type = 'DIRECTOR_CURSO' AND course_id = :course_id2
     )"
)->execute(['course_id' => $eetId, 'course_id2' => $eetId]);

// Cria o destino "Direcção Geral" se ainda não existir
$pdo->prepare(
    "INSERT INTO destinations (type, label)
     SELECT 'DIRECCAO_GERAL', 'Direcção Geral'
     WHERE NOT EXISTS (SELECT 1 FROM destinations WHERE type = 'DIRECCAO_GERAL')"
)->execute();

// Associa o utilizador da Direcção Geral (usamos o admin como responsável de teste)
$pdo->prepare("UPDATE departments SET responsible_user_id = :uid WHERE code = 'DG'")
    ->execute(['uid' => $adminId]);

// Regista o estudante de teste no curso EET
$pdo->prepare(
    'INSERT INTO students (user_id, student_number, course_id, enrollment_year)
     VALUES (:uid, :num, :course_id, :year)'
)->execute([
    'uid' => $estId, 'num' => '2024-EET-001', 'course_id' => $eetId, 'year' => 2024,
]);

echo "Dados de teste criados. Password para todos os utilizadores: $pass\n";
echo "admin@isps.ac.mz / secretaria@isps.ac.mz / director.eet@isps.ac.mz / estudante@isps.ac.mz\n";