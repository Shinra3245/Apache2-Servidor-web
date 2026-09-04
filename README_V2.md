# Práctica API REST V2 — autenticación con tokens

Esta rama agrega autenticación Bearer al API REST sin modificar la versión V1
publicada desde la rama `main`.

## Requisitos

- PHP 8.1 o superior con `pdo_mysql`.
- MariaDB o MySQL.
- Apache con `mod_rewrite`, o el servidor integrado de PHP para desarrollo.

## Configuración

1. Conservar las credenciales de base de datos en `.env`.
2. Copiar `.env.v2.example` como `.env.v2` para personalizar
   `TOKEN_TTL_MINUTES` y, si se necesita, `DB_PORT`.
3. Ejecutar la migración:

   ```bash
   php api/scripts/migrate_v2.php
   ```

4. Crear el usuario académico de prueba:

   ```bash
   php api/scripts/create_api_user.php USUARIO CORREO CONTRASEÑA
   ```

5. Iniciar el servidor local:

   ```bash
   php -S 127.0.0.1:8082 -t api/public api/public/index.php
   ```

## Endpoints V2

- `POST /api/v2/login`
- `POST /api/v2/logout`
- `GET /api/v2/me`
- CRUD protegido en `/api/v2/users`
- CRUD protegido en `/api/v2/products`

Todas las rutas, excepto `login`, requieren:

```text
Authorization: Bearer TOKEN
```

## Pruebas

```bash
API_TEST_PASSWORD='CONTRASEÑA_ACADÉMICA' tests/integration_v2.sh
```

La colección importable se encuentra en `postman/` y no almacena la
contraseña ni el token en Git.
