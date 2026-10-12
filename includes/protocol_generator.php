<?php
/**
 * Geração do número de protocolo, no formato ISPS-AAAA-NNNNNN.
 * Usa a tabela protocol_sequence com um UPDATE atómico para evitar
 * colisões em caso de submissões concorrentes.
 *
 * IMPORTANTE: esta função NÃO abre a sua própria transação — deve ser
 * chamada a partir de dentro de uma transacção já iniciada pelo chamador
 * (ex.: novo_requerimento.php). PDO não suporta transações aninhadas;
 * chamar beginTransaction() aqui dentro de uma transacção já activa
 * lançaria "There is already an active transaction".
 */

function generateProtocolNumber(PDO $pdo): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException(
            'generateProtocolNumber() deve ser chamada dentro de uma transacção já iniciada pelo chamador.'
        );
    }

    $year = (int) date('Y');

    // Garante que existe uma linha para o ano corrente
    $pdo->prepare('INSERT IGNORE INTO protocol_sequence (year_ref, last_seq) VALUES (:year, 0)')
        ->execute(['year' => $year]);

    // Incrementa de forma atómica (o UPDATE já bloqueia a linha dentro da transacção corrente)
    $pdo->prepare('UPDATE protocol_sequence SET last_seq = last_seq + 1 WHERE year_ref = :year')
        ->execute(['year' => $year]);

    $stmt = $pdo->prepare('SELECT last_seq FROM protocol_sequence WHERE year_ref = :year');
    $stmt->execute(['year' => $year]);
    $seq = (int) $stmt->fetchColumn();

    return sprintf('ISPS-%d-%06d', $year, $seq);
}
