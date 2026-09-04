<?php

class User
{
    private string $tableName = 'api_users';

    public int $id;
    public string $username;
    public string $email;
    public string $passwordHash;
    public string $status = 'ACTIVE';
    public string $createdAt;
    public string $updatedAt;

    public function __construct(private PDO $conn)
    {
    }

    public function findActiveByUsername(string $username): ?array
    {
        $query = "SELECT id, username, email, password_hash, status, created_at, updated_at
                  FROM {$this->tableName}
                  WHERE username = :username
                    AND status = 'ACTIVE'
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':username' => trim($username)]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function read(): PDOStatement
    {
        $query = "SELECT id, username, email, status, created_at, updated_at
                  FROM {$this->tableName}
                  ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    public function readOne(): ?array
    {
        $query = "SELECT id, username, email, status, created_at, updated_at
                  FROM {$this->tableName}
                  WHERE id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $this->id]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function create(): bool
    {
        $query = "INSERT INTO {$this->tableName}
                    (username, email, password_hash, status)
                  VALUES
                    (:username, :email, :password_hash, :status)";

        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            ':username' => trim($this->username),
            ':email' => trim($this->email),
            ':password_hash' => $this->passwordHash,
            ':status' => $this->status
        ]);

        if ($result) {
            $this->id = (int) $this->conn->lastInsertId();
        }

        return $result;
    }

    public function update(bool $replacePassword): bool
    {
        $passwordClause = $replacePassword
            ? ', password_hash = :password_hash'
            : '';

        $query = "UPDATE {$this->tableName}
                  SET username = :username,
                      email = :email,
                      status = :status
                      {$passwordClause}
                  WHERE id = :id";

        $parameters = [
            ':username' => trim($this->username),
            ':email' => trim($this->email),
            ':status' => $this->status,
            ':id' => $this->id
        ];

        if ($replacePassword) {
            $parameters[':password_hash'] = $this->passwordHash;
        }

        $stmt = $this->conn->prepare($query);

        return $stmt->execute($parameters);
    }

    public function delete(): bool
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->tableName} WHERE id = :id"
        );
        $stmt->execute([':id' => $this->id]);

        return $stmt->rowCount() === 1;
    }
}
