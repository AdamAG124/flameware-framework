<?php

declare(strict_types=1);

/**
 * Lee la estructura real de una tabla y la convierte en FieldSpec.
 *
 * Es el modo preferido: los nombres de columna no los escribe nadie, se leen
 * del esquema, así que desaparece la clase entera de errores por typo — que en
 * este patrón no los detecta el linter porque salen como null en runtime.
 */
final class SchemaReader
{
    public function __construct(
        private readonly string $projectPath,
    ) {}

    /**
     * @return list<FieldSpec>
     * @throws RuntimeException si no hay driver, credenciales o tabla
     */
    public function read(string $table, string $token): array
    {
        $env = $this->readEnv();
        $pdo = $this->connect($env);

        $statement = $pdo->query(sprintf('SHOW COLUMNS FROM `%s`', str_replace('`', '', $table)));

        if ($statement === false) {
            throw new RuntimeException("No se pudo leer la estructura de «{$table}».");
        }

        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($columns === []) {
            throw new RuntimeException("La tabla «{$table}» no tiene columnas.");
        }

        $fields = [];

        foreach ($columns as $column) {
            $name    = (string) $column['Field'];
            $phpType = NamingConvention::phpTypeFor($name, (string) $column['Type']);

            $fields[] = new FieldSpec(
                property:     NamingConvention::toProperty($name, $token),
                column:       $name,
                phpType:      $phpType,
                nullable:     strtoupper((string) $column['Null']) === 'YES',
                isPrimaryKey: strtoupper((string) $column['Key']) === 'PRI',
                cast:         $phpType === 'DateTimeImmutable' ? 'datetime' : null,
            );
        }

        return $fields;
    }

    /** @return array<string,string> */
    private function readEnv(): array
    {
        $path = $this->projectPath . '/.env.local';

        if (!is_file($path)) {
            throw new RuntimeException('No se encontró .env.local — ¿estás dentro de un proyecto Flameware?');
        }

        $env = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\"'");
        }

        return $env;
    }

    /** @param array<string,string> $env */
    private function connect(array $env): PDO
    {
        $database = $env['DB_DATABASE'] ?? '';

        if ($database === '') {
            throw new RuntimeException('DB_DATABASE está vacío en .env.local — no hay esquema que leer.');
        }

        $driver = $env['DB_CONNECTION'] ?? 'mysql';

        if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(sprintf(
                "El driver PDO «%s» no está activo. Drivers disponibles: %s.\n"
                . "    En Arch, descomenta «extension=pdo_%s» en /etc/php/php.ini.",
                $driver,
                PDO::getAvailableDrivers() === [] ? 'ninguno' : implode(', ', PDO::getAvailableDrivers()),
                $driver,
            ));
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $driver,
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $database,
        );

        try {
            return new PDO($dsn, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos: ' . $e->getMessage());
        }
    }
}
