<?php

require_once __DIR__ . '/../../config/AppConfig.php';
require_once __DIR__ . '/../../core/AuthMiddleware.php';
require_once __DIR__ . '/../../core/JsonResponse.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/ApiToken.php';

class AuthResource
{
    private User $users;
    private ApiToken $tokens;

    public function __construct(
        PDO $db,
        private AuthMiddleware $auth
    ) {
        $this->users = new User($db);
        $this->tokens = new ApiToken($db);
    }

    public function login(): void
    {
        $data = $this->jsonBody();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            JsonResponse::send(400, [
                'error' => 'invalid_request',
                'message' => 'Username y password son obligatorios'
            ]);
            return;
        }

        $user = $this->users->findActiveByUsername($username);

        if (
            $user === null ||
            !password_verify($password, $user['password_hash'])
        ) {
            JsonResponse::send(401, [
                'error' => 'invalid_credentials',
                'message' => 'Usuario o contraseña incorrectos'
            ]);
            return;
        }

        $issuedToken = $this->tokens->issue(
            (int) $user['id'],
            (int) AppConfig::get('TOKEN_TTL_MINUTES', 60)
        );

        JsonResponse::send(200, $issuedToken);
    }

    public function logout(): void
    {
        $this->tokens->revoke($this->auth->token());

        JsonResponse::send(200, [
            'message' => 'Token revocado exitosamente'
        ]);
    }

    public function me(): void
    {
        JsonResponse::send(200, $this->auth->user());
    }

    private function jsonBody(): array
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!is_array($data)) {
            JsonResponse::send(400, [
                'error' => 'invalid_json',
                'message' => 'El cuerpo debe contener JSON válido'
            ]);
            exit;
        }

        return $data;
    }
}
