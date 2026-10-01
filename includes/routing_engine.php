<?php
/**
 * Motor de Tramitação
 * ---------------------------------------------------------------
 * Dado um tipo de pedido (request_type_id) e, quando aplicável, o
 * curso do estudante (course_id), determina para qual "destination"
 * o processo deve ser inicialmente encaminhado.
 *
 * Regra de resolução:
 *   1. Procura em routing_rules uma regra específica para
 *      (request_type_id, course_id) — prioridade mais alta.
 *   2. Se não encontrar, procura uma regra genérica para o
 *      request_type_id com course_id NULL (aplica-se a todos os cursos).
 *   3. Se ainda não encontrar, cai para o destination_type por omissão
 *      definido no próprio request_type e resolve automaticamente:
 *        - DIRECCAO_GERAL   -> destino único "Direcção Geral"
 *        - DIRECTOR_CURSO   -> destino do curso do estudante
 *        - DIRECCAO_CENTRAL -> erro (é obrigatório configurar routing_rules
 *                               explícitas, pois há várias direcções centrais)
 */

class RoutingException extends RuntimeException {}

function determineDestination(PDO $pdo, int $requestTypeId, ?int $courseId): int
{
    // 1. Regra específica por curso
    if ($courseId !== null) {
        $stmt = $pdo->prepare(
            'SELECT destination_id FROM routing_rules
             WHERE request_type_id = :rt AND course_id = :course
             ORDER BY priority DESC LIMIT 1'
        );
        $stmt->execute(['rt' => $requestTypeId, 'course' => $courseId]);
        $destId = $stmt->fetchColumn();
        if ($destId) {
            return (int) $destId;
        }
    }

    // 2. Regra genérica (aplica-se a todos os cursos)
    $stmt = $pdo->prepare(
        'SELECT destination_id FROM routing_rules
         WHERE request_type_id = :rt AND course_id IS NULL
         ORDER BY priority DESC LIMIT 1'
    );
    $stmt->execute(['rt' => $requestTypeId]);
    $destId = $stmt->fetchColumn();
    if ($destId) {
        return (int) $destId;
    }

    // 3. Resolução automática pelo destino por omissão do request_type
    $stmt = $pdo->prepare('SELECT default_destination_type FROM request_types WHERE id = :id');
    $stmt->execute(['id' => $requestTypeId]);
    $defaultType = $stmt->fetchColumn();

    if ($defaultType === 'DIRECCAO_GERAL') {
        $stmt = $pdo->prepare("SELECT id FROM destinations WHERE type = 'DIRECCAO_GERAL' LIMIT 1");
        $stmt->execute();
        $destId = $stmt->fetchColumn();
        if ($destId) {
            return (int) $destId;
        }
        throw new RoutingException('Não existe destino configurado para a Direcção Geral.');
    }

    if ($defaultType === 'DIRECTOR_CURSO') {
        if ($courseId === null) {
            throw new RoutingException('Este tipo de pedido requer um curso associado ao estudante.');
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM destinations WHERE type = 'DIRECTOR_CURSO' AND course_id = :course LIMIT 1"
        );
        $stmt->execute(['course' => $courseId]);
        $destId = $stmt->fetchColumn();
        if ($destId) {
            return (int) $destId;
        }
        throw new RoutingException('Não existe destino configurado para o Director deste curso.');
    }

    // DIRECCAO_CENTRAL sem regra explícita: ambíguo, exige configuração manual
    throw new RoutingException(
        'Este tipo de pedido aponta para uma Direcção Central, mas não há uma routing_rule ' .
        'a especificar qual. Configura uma regra em routing_rules para este request_type.'
    );
}

/**
 * Regista uma entrada no histórico do processo (rastreabilidade).
 */
function logHistory(
    PDO $pdo,
    int $requestId,
    string $action,
    int $performedBy,
    ?int $fromDestination,
    ?int $toDestination,
    ?string $notes = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO request_history
            (request_id, action, performed_by, from_destination_id, to_destination_id, notes)
         VALUES (:request_id, :action, :performed_by, :from_dest, :to_dest, :notes)'
    );
    $stmt->execute([
        'request_id'  => $requestId,
        'action'      => $action,
        'performed_by'=> $performedBy,
        'from_dest'   => $fromDestination,
        'to_dest'     => $toDestination,
        'notes'       => $notes,
    ]);
}

/**
 * Cria uma notificação simples para um utilizador.
 */
function notifyUser(PDO $pdo, int $userId, ?int $requestId, string $message): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, request_id, message) VALUES (:uid, :rid, :msg)'
    );
    $stmt->execute(['uid' => $userId, 'rid' => $requestId, 'msg' => $message]);
}
