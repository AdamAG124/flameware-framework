<?php

declare(strict_types=1);

/**
 * Un atributo del recurso, resuelto desde el esquema de la BD o desde --fields.
 *
 * Es la única fuente de verdad para la generación: cada archivo del slice se
 * escribe recorriendo la misma lista de FieldSpec, así que el nombre de columna
 * y el de la propiedad no pueden desincronizarse entre capas.
 */
final class FieldSpec
{
    private const SCALARS = ['int', 'float', 'bool', 'string'];

    /**
     * @param ?string $relatedPrimaryKey Propiedad que hace de clave en el Model
     *                                   referenciado. Solo la llevan los campos
     *                                   cuyo tipo es otro Model del proyecto.
     */
    public function __construct(
        public readonly string $property,
        public readonly string $column,
        public readonly string $phpType,
        public readonly bool $nullable = false,
        public readonly bool $isPrimaryKey = false,
        public readonly ?string $cast = null,
        public readonly ?string $relatedPrimaryKey = null,
    ) {}

    /** Tipo tal como se escribe en una firma: «?DateTimeImmutable», «string». */
    public function hint(): string
    {
        return ($this->nullable ? '?' : '') . $this->phpType;
    }

    /** «createdAt» => «CreatedAt», para getCreatedAt()/setCreatedAt(). */
    public function studly(): string
    {
        return ucfirst($this->property);
    }

    /**
     * Valor neutro con el que el constructor sin parámetros inicializa.
     *
     * Un objeto no tiene neutro escalar, así que se instancia vacío: dejarlo
     * en null rompería la propiedad tipada en cuanto se construye el Model.
     */
    public function neutralValue(): string
    {
        if ($this->nullable) {
            return 'null';
        }

        return match ($this->phpType) {
            'int'    => '0',
            'float'  => '0.0',
            'bool'   => 'false',
            'string' => "''",
            default  => "new {$this->phpType}()",
        };
    }

    /**
     * Cast que aplica el EloquentModel al leer la columna cruda.
     * Los objetos se arman aparte: una columna no se castea a una clase.
     */
    public function castExpression(string $accessor): string
    {
        if ($this->isObject()) {
            return $accessor;
        }

        return "({$this->phpType}) {$accessor}";
    }

    public function isObject(): bool
    {
        return !in_array($this->phpType, self::SCALARS, true);
    }

    public function isDate(): bool
    {
        return $this->phpType === 'DateTimeImmutable';
    }

    /** El tipo del campo es otro Model del proyecto, no un escalar ni una fecha. */
    public function isModel(): bool
    {
        return $this->relatedPrimaryKey !== null;
    }

    /** Getter de la clave en el Model referenciado: «getPersonId». */
    public function relatedGetter(): string
    {
        return 'get' . ucfirst((string) $this->relatedPrimaryKey);
    }

    /** Setter de la clave en el Model referenciado: «setPersonId». */
    public function relatedSetter(): string
    {
        return 'set' . ucfirst((string) $this->relatedPrimaryKey);
    }
}
