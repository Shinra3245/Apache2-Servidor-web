<?php

class ApiToken
{
    public function __construct(private PDO $conn)
    {
    }

    public function issue(int $userId, int $ttlMinutes): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$ttlMinutes} minutes")
            ->format('Y-m-d H:i:s');

        $this->conn->beginTransaction();

        try {
            $revoke = $this->conn->prepare(
                'UPDATE api_tokens
                 SET revoked = TRUE
                 WHERE user_id = :user_id
                   AND revoked = FALSE'
            );
            $revoke->execute([':user_id' => $userId]);

            $insert = $this->conn->prepare(
                'INSERT INTO api_tokens (user_id, token, expires_at)
                 VALUES (:user_id, :token, :expires_at)'
            );
            $insert->execute([
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt
            ]);

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt
        ];
    }

    public function findAuthenticatedUser(string $token): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.status,
                u.created_at,
                u.updated_at
             FROM api_tokens t
             INNER JOIN api_users u ON u.id = t.user_id
             WHERE t.token = :token
               AND t.revoked = FALSE
               AND t.expires_at > UTC_TIMESTAMP()
               AND u.status = \'ACTIVE\'
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function revoke(string $token): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE api_tokens
             SET revoked = TRUE
             WHERE token = :token
               AND revoked = FALSE'
        );
        $stmt->execute([':token' => $token]);

        return $stmt->rowCount() === 1;
    }
}
