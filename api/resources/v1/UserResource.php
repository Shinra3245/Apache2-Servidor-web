<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/JsonResponse.php';
require_once __DIR__ . '/../../models/User.php';

class UserResource
{
    private User $user;

    public function __construct(?PDO $db = null)
    {
        $db ??= (new Database())->getConnection();
        $this->user = new User($db);
    }

    public function index(): void
    {
        $stmt = $this->user->read();

        JsonResponse::send(200, [
            'records' => $stmt->fetchAll()
        ]);
    }

    public function show(string $id): void
    {
        if (!$this->validId($id)) {
            JsonResponse::send(400, ['message' => 'ID de usuario no válido']);
            return;
        }

        $this->user->id = (int) $id;
        $user = $this->user->readOne();

        if ($user === null) {
            JsonResponse::send(404, ['message' => 'Usuario no encontrado']);
            return;
        }

        JsonResponse::send(200, $user);
    }

    public function store(): void
    {
        $data = $this->jsonBody();
        $errors = $this->validate($data, true);

        if ($errors !== []) {
            JsonResponse::send(400, [
                'message' => 'Datos inválidos',
                'errors' => $errors
            ]);
            return;
        }

        $this->user->username = trim($data['username']);
        $this->user->email = trim($data['email']);
        $this->user->passwordHash = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );
        $this->user->status = $data['status'] ?? 'ACTIVE';

        try {
            $this->user->create();

            JsonResponse::send(201, [
                'message' => 'Usuario creado exitosamente',
                'id' => $this->user->id
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                JsonResponse::send(409, [
                    'message' => 'El username o email ya está registrado'
                ]);
                return;
            }

            throw $e;
        }
    }

    public function update(string $id): void
    {
        if (!$this->validId($id)) {
            JsonResponse::send(400, ['message' => 'ID de usuario no válido']);
            return;
        }

        $this->user->id = (int) $id;

        if ($this->user->readOne() === null) {
            JsonResponse::send(404, ['message' => 'Usuario no encontrado']);
            return;
        }

        $data = $this->jsonBody();
        $replacePassword = isset($data['password']) && $data['password'] !== '';
        $errors = $this->validate($data, $replacePassword);

        if ($errors !== []) {
            JsonResponse::send(400, [
                'message' => 'Datos inválidos',
                'errors' => $errors
            ]);
            return;
        }

        $this->user->username = trim($data['username']);
        $this->user->email = trim($data['email']);
        $this->user->status = $data['status'] ?? 'ACTIVE';

        if ($replacePassword) {
            $this->user->passwordHash = password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            );
        }

        try {
            $this->user->update($replacePassword);
            JsonResponse::send(200, [
                'message' => 'Usuario actualizado exitosamente'
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                JsonResponse::send(409, [
                    'message' => 'El username o email ya está registrado'
                ]);
                return;
            }

            throw $e;
        }
    }

    public function destroy(string $id): void
    {
        if (!$this->validId($id)) {
            JsonResponse::send(400, ['message' => 'ID de usuario no válido']);
            return;
        }

        $this->user->id = (int) $id;

        if (!$this->user->delete()) {
            JsonResponse::send(404, ['message' => 'Usuario no encontrado']);
            return;
        }

        JsonResponse::send(200, [
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    private function jsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            JsonResponse::send(400, [
                'message' => 'El cuerpo debe contener JSON válido'
            ]);
            exit;
        }

        return $data;
    }

    private function validate(array $data, bool $passwordRequired): array
    {
        $errors = [];
        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $status = $data['status'] ?? 'ACTIVE';

        if ($username === '' || mb_strlen($username) > 100) {
            $errors[] = 'Username obligatorio con máximo 100 caracteres';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errors[] = 'Email no válido';
        }

        if ($passwordRequired && mb_strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres';
        }

        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $errors[] = 'Status debe ser ACTIVE o INACTIVE';
        }

        return $errors;
    }

    private function validId(string $id): bool
    {
        return ctype_digit($id) && (int) $id > 0;
    }
}
