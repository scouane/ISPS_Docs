<?php
/**
 * Camada de aplicação — autenticação, sessão e controlo de permissões.
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Tenta autenticar um utilizador por email + password.
 * Devolve os dados do utilizador em sucesso, ou null em falha.
 */
function attemptLogin(string $email, string $password): ?array
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.email, u.password_hash, u.active,
                r.code AS role_code, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);

    $_SESSION['user'] = $user;

    // Se for estudante, carrega também o student_id e course_id (úteis no motor de tramitação)
    if ($user['role_code'] === 'ESTUDANTE') {
        $stmt = $pdo->prepare('SELECT id AS student_id, course_id FROM students WHERE user_id = :uid');
        $stmt->execute(['uid' => $user['id']]);
        $student = $stmt->fetch();
        if ($student) {
            $_SESSION['user']['student_id'] = $student['student_id'];
            $_SESSION['user']['course_id']  = $student['course_id'];
        }
    }

    return $_SESSION['user'];
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

/**
 * Interrompe a execução e redireciona para o login se o utilizador
 * não estiver autenticado, ou se não tiver um dos papéis permitidos.
 *
 * @param string[] $allowedRoles Ex.: ['ESTUDANTE'], ['SECRETARIA','ADMIN']
 */
function requireRole(array $allowedRoles): array
{
    if (!isLoggedIn()) {
        header('Location: /public/login.php');
        exit;
    }

    $user = currentUser();

    if (!in_array($user['role_code'], $allowedRoles, true)) {
        http_response_code(403);
        die('Acesso negado: não tens permissão para aceder a esta página.');
    }

    return $user;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * Cria um novo utilizador com password em hash. Usado pelo admin
 * ao registar estudantes, secretaria, directores, etc.
 */
function createUser(PDO $pdo, string $fullName, string $email, string $password, string $roleCode): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE code = :code');
    $stmt->execute(['code' => $roleCode]);
    $role = $stmt->fetch();
    if (!$role) {
        throw new InvalidArgumentException("Perfil inválido: $roleCode");
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (role_id, full_name, email, password_hash)
         VALUES (:role_id, :full_name, :email, :password_hash)'
    );
    $stmt->execute([
        'role_id'       => $role['id'],
        'full_name'     => $fullName,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}
