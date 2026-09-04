<?php

require_once __DIR__ . '/JsonResponse.php';
require_once __DIR__ . '/../models/ApiToken.php';

class AuthMiddleware
{
    private ApiToken $tokens;
    private ?array $authenticatedUser = null;
    private ?string $currentToken = null;

    public function __construct(PDO $db)
    {
        $this->tokens = new ApiToken($db);
    }

    public function handle(): void
    {
        $header = $this->authorizationHeader();

        if (
            $header === null ||
            preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) !== 1
        ) {
            $this->reject();
        }

        $token = $matches[1];
        $user = $this->tokens->findAuthenticatedUser($token);

        if ($user === null) {
            $this->reject();
        }

        $this->currentToken = $token;
        $this->authenticatedUser = $user;
    }

    public function user(): array
    {
        if ($this->authenticatedUser === null) {
            throw new LogicException('No hay un usuario autenticado en el contexto.');
        }

        return $this->authenticatedUser;
    }

    public function token(): string
    {
        if ($this->currentToken === null) {
            throw new LogicException('No hay un token autenticado en el contexto.');
        }

        return $this->currentToken;
    }

    private function authorizationHeader(): ?string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            if (!empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function reject(): never
    {
        header('WWW-Authenticate: Bearer');
        JsonResponse::send(401, [
            'error' => 'unauthorized',
            'message' => 'Token inválido, expirado o no proporcionado'
        ]);
        exit;
    }
}
