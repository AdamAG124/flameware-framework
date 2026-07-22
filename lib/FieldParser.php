<?php

declare(strict_types=1);

/**
 * Convierte la especificación de --fields en FieldSpec.
 *
 *   --fields="productId:int,name:string,stock:int,createdAt:?datetime"
 *
 * Se generan exactamente los campos declarados, ninguno más. El primero es la
 * clave primaria y por eso tiene que nombrar al recurso; los demás salen tal
 * cual, así que un marcador ID en cualquier otra posición se trata como FK.
 *
 * El tipo puede ser un escalar o el nombre de un Model ya generado, y entonces
 * la propiedad es una referencia a ese objeto en lugar de un entero suelto:
 *
 *   --fields="clientId:int,name:string,personId:Person"
 *
 * Los nombres de columna se derivan por convención y quedan como propuesta:
 * si la tabla real usa otros, se corrigen en el EloquentModel, que es el único
 * archivo donde aparecen.
 */
final class FieldParser
{
    public function __construct(
        private readonly ModelReader $models,
    ) {}

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
        $fields = [];

        foreach (array_filter(array_map('trim', explode(',', $spec)), strlen(...)) as $definition) {
            if (!str_contains($definition, ':')) {
                throw new RuntimeException("Campo mal formado: «{$definition}». Usa nombre:tipo.");
            }

            [$property, $type] = array_map('trim', explode(':', $definition, 2));

            $nullable = str_starts_with($type, '?');
            $type     = ltrim($type, '?');

            if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $property)) {
                throw new RuntimeException("Nombre de campo inválido: «{$property}». Usa camelCase.");
            }

            // Lo que no es un alias escalar se interpreta como el nombre de un
            // Model del proyecto. Tiene que existir ya: no se puede tipar una
            // propiedad con una clase que todavía nadie ha generado.
            $isModel = !isset(self::ALIASES[strtolower($type)]);

            if ($isModel && !preg_match('/^[A-Z][A-Za-z0-9]*$/', $type)) {
                throw new RuntimeException(sprintf(
                    "Tipo desconocido: «%s».\n"
                    . "      Escalares: %s.\n"
                    . "      También vale el nombre de un Model ya generado, en StudlyCase.",
                    $type,
                    implode(', ', array_keys(self::ALIASES)),
                ));
            }

            $phpType = $isModel ? $type : self::ALIASES[strtolower($type)];

            $fields[] = new FieldSpec(
                property: $property,
                column:   NamingConvention::toColumn($property, $isModel ? 'int' : $phpType, $token),
                phpType:  $phpType,
                nullable: $nullable,
                // Solo el primer campo opta a PK: la clave de la entidad
                // encabeza la tabla, y un marcador ID más abajo ya es una FK
                // aunque nombre a la entidad misma.
                isPrimaryKey: $fields === [] && NamingConvention::isPrimaryKeyName($property, $token),
                cast:     $phpType === 'DateTimeImmutable' ? 'datetime' : null,
                // Resolverlo aquí valida de paso que el Model exista.
                relatedPrimaryKey: $isModel ? $this->models->primaryKeyOf($type) : null,
            );
        }

        if ($fields === []) {
            throw new RuntimeException('--fields no declaró ningún campo.');
        }

        if (!$fields[0]->isPrimaryKey) {
            $entity = NamingConvention::tokenToEntity($token);

            throw new RuntimeException(sprintf(
                "El primer campo de --fields tiene que ser la clave primaria, y «%s» no la nombra.\n"
                . "      Válidos aquí: «id», «%sId» o «id%s».",
                $fields[0]->property,
                $entity,
                ucfirst($entity),
            ));
        }

        if ($fields[0]->isObject()) {
            throw new RuntimeException(sprintf(
                "La clave primaria «%s» no puede ser de tipo «%s»: es el valor de una columna, no una referencia.",
                $fields[0]->property,
                $fields[0]->phpType,
            ));
        }

        return $fields;
    }
}
