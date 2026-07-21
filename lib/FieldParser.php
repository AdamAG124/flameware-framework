<?php

declare(strict_types=1);

/**
 * Convierte la especificación de --fields en FieldSpec.
 *
 *   --fields="name:string,price:float,stock:int,createdAt:?datetime"
 *
 * Los nombres de columna se derivan por convención y quedan como propuesta:
 * si la tabla real usa otros, se corrigen en el EloquentModel, que es el único
 * archivo donde aparecen.
 */
final class FieldParser
{
    private const ALIASES = [
        'string'   => 'string',
        'text'     => 'string',
        'int'      => 'int',
        'integer'  => 'int',
        'float'    => 'float',
        'decimal'  => 'float',
        'double'   => 'float',
        'bool'     => 'bool',
        'boolean'  => 'bool',
        'datetime' => 'DateTimeImmutable',
        'date'     => 'DateTimeImmutable',
    ];

    /**
     * @return list<FieldSpec>
     * @throws RuntimeException
     */
    public function parse(string $spec, string $token): array
    {
        $fields = [
            // La PK siempre existe y no se declara en --fields.
            new FieldSpec(
                property: 'id',
                column: NamingConvention::toColumn('id', 'int', $token),
                phpType: 'int',
                isPrimaryKey: true,
            ),
        ];

        foreach (array_filter(array_map('trim', explode(',', $spec)), strlen(...)) as $definition) {
            if (!str_contains($definition, ':')) {
                throw new RuntimeException("Campo mal formado: «{$definition}». Usa nombre:tipo.");
            }

            [$property, $type] = array_map('trim', explode(':', $definition, 2));

            $nullable = str_starts_with($type, '?');
            $type     = ltrim($type, '?');

            if (!isset(self::ALIASES[strtolower($type)])) {
                throw new RuntimeException(sprintf(
                    "Tipo desconocido: «%s». Válidos: %s.",
                    $type,
                    implode(', ', array_keys(self::ALIASES)),
                ));
            }

            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $property)) {
                throw new RuntimeException("Nombre de campo inválido: «{$property}». Usa camelCase.");
            }

            $phpType = self::ALIASES[strtolower($type)];

            $fields[] = new FieldSpec(
                property: $property,
                column:   NamingConvention::toColumn($property, $phpType, $token),
                phpType:  $phpType,
                nullable: $nullable,
                cast:     $phpType === 'DateTimeImmutable' ? 'datetime' : null,
            );
        }

        if (count($fields) === 1) {
            throw new RuntimeException('--fields no declaró ningún campo.');
        }

        return $fields;
    }
}
