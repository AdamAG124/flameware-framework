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
    public function __construct(
        public readonly string $property,
        public readonly string $column,
        public readonly string $phpType,
        public readonly bool $nullable = false,
        public readonly bool $isPrimaryKey = false,
        public readonly ?string $cast = null,
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

    /** Valor neutro con el que el constructor sin parámetros inicializa. */
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
            default  => 'null',
        };
    }

    /**
     * Cast que aplica el EloquentModel al leer la columna cruda.
     * Las fechas se convierten aparte porque Eloquent entrega Carbon.
     */
    public function castExpression(string $accessor): string
    {
        if ($this->phpType === 'DateTimeImmutable') {
            return $accessor;
        }

        return "({$this->phpType}) {$accessor}";
    }

    public function isDate(): bool
    {
        return $this->phpType === 'DateTimeImmutable';
    }
}
