<?php

final class AppConfig
{
    private static ?array $values = null;

    public static function all(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        $projectRoot = dirname(__DIR__, 2);
        $baseFiles = [
            '/etc/practica/db-v2.env',
            $projectRoot . '/.env'
        ];

        $config = null;

        foreach ($baseFiles as $file) {
            if (is_readable($file)) {
                $config = parse_ini_file($file, false, INI_SCANNER_RAW);
                break;
            }
        }

        if (!is_array($config)) {
            throw new RuntimeException(
                'No se encontró la configuración de la base de datos para V2.'
            );
        }

        $v2File = $projectRoot . '/.env.v2';

        if (is_readable($v2File)) {
            $v2Config = parse_ini_file($v2File, false, INI_SCANNER_RAW);

            if (is_array($v2Config)) {
                $config = array_merge($config, $v2Config);
            }
        }

        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $key) {
            if (!array_key_exists($key, $config) || $config[$key] === '') {
                throw new RuntimeException("Falta la variable de configuración {$key}.");
            }
        }

        $ttl = filter_var(
            $config['TOKEN_TTL_MINUTES'] ?? 60,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1440]]
        );

        if ($ttl === false) {
            throw new RuntimeException(
                'TOKEN_TTL_MINUTES debe ser un entero entre 1 y 1440.'
            );
        }

        $config['TOKEN_TTL_MINUTES'] = $ttl;
        self::$values = $config;

        return self::$values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::all();

        return $config[$key] ?? $default;
    }
}
