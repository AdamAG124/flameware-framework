<?php

declare(strict_types=1);

/**
 * Traduce entre nombres de columna y nombres de propiedad.
 *
 * Nomenclatura de la base de datos:
 *
 *   ID      Primary keys / foreign keys   ID_EMPLOYEE
 *   DSC     Descriptions                  DSC_ADDRESS
 *   TYPE    Type indicators               TYPE_DOCUMENT
 *   STATUS  Status fields                 STATUS
 *   AMOUNT  Monetary amounts              TOTAL_AMOUNT
 *   NUM     Numeric values                NUM_QUANTITY
 *   FEC     Date fields                   FEC_CREATION
 *
 * Al nombre se le quita el marcador de tipo y el token de la entidad, y lo que
 * queda pasa a camelCase:
 *
 *   ID_PRODUCT              -> productId
 *   ID_CLIENT               -> clientId
 *   DSC_PRODUCT_NAME        -> name
 *   NUM_QUANTITY            -> quantity
 *   FEC_CREATION            -> creation
 *   STATUS                  -> status
 *   TOTAL_AMOUNT            -> totalAmount
 */
final class NamingConvention
{
    private const PREFIXES = ['ID', 'DSC', 'TYPE', 'STATUS', 'AMOUNT', 'NUM', 'FEC'];

    /** «ClientBusiness» => «CLIENT_BUSINESS» */
    public static function entityToken(string $resource): string
    {
        return strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $resource));
    }

    public static function toProperty(string $column, string $token): string
    {
        $column = strtoupper($column);

        // Columna que es exactamente el marcador, sin sufijo: STATUS -> status.
        if (in_array($column, self::PREFIXES, true)) {
            return strtolower($column);
        }

        // AMOUNT aparece pospuesto (TOTAL_AMOUNT), no antepuesto. Ahí no es un
        // marcador que se descarte: forma parte del nombre y se conserva entero.
        if (str_ends_with($column, '_AMOUNT')) {
            return self::camel($column);
        }

        $rest = (string) preg_replace('/^(' . implode('|', self::PREFIXES) . ')_/', '', $column);

        // Toda columna con marcador ID se nombra igual, sea la PK de la tabla o
        // una foreign key: lo que acompaña al marcador, en camelCase, con Id
        // al final. Cuál es la PK lo dice el esquema, no el nombre.
        if (str_starts_with($column, 'ID_')) {
            if (str_starts_with($rest, $token . '_')) {
                $rest = substr($rest, strlen($token) + 1);
            }

            return $rest === '' ? 'id' : self::camel($rest) . 'Id';
        }

        if ($rest === $token) {
            return self::camel($column);
        }

        if (str_starts_with($rest, $token . '_')) {
            $rest = substr($rest, strlen($token) + 1);
        }

        return $rest === '' ? self::camel($column) : self::camel($rest);
    }

    /**
     * Dirección inversa, para el modo --fields donde no hay esquema que leer.
     * El marcador se elige por tipo y por cómo termina el nombre; es una
     * propuesta editable, no un dogma.
     */
    public static function toColumn(string $property, string $phpType, string $token): string
    {
        $snake = static fn (string $value): string
            => strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));

        if ($property === 'id') {
            return 'ID_' . $token;
        }

        // El marcador ID también puede venir antepuesto en la propiedad, no
        // solo como sufijo — por ejemplo idCategory frente a categoryId. La
        // columna es la misma en ambos casos.
        if (preg_match('/^id[A-Z]/', $property)) {
            return 'ID_' . $snake(lcfirst(substr($property, 2)));
        }

        // Los montos llevan AMOUNT pospuesto y sin token: totalAmount -> TOTAL_AMOUNT.
        if (str_ends_with($property, 'Amount')) {
            return $snake($property);
        }

        // Cuando el nombre ya termina en el marcador, se quita para no repetirlo,
        // y se omite el token: clientId -> ID_CLIENT, no ID_INVOICE_CLIENT_ID.
        foreach (['Id' => 'ID', 'Type' => 'TYPE'] as $suffix => $marker) {
            if (str_ends_with($property, $suffix) && $property !== lcfirst($suffix)) {
                return $marker . '_' . $snake(substr($property, 0, -strlen($suffix)));
            }
        }

        $prefix = match (true) {
            $phpType === 'bool'              => 'STATUS',
            $phpType === 'DateTimeImmutable' => 'FEC',
            $phpType === 'int', $phpType === 'float' => 'NUM',
            default                          => 'DSC',
        };

        return "{$prefix}_{$token}_" . $snake($property);
    }

    /** «CLIENT_BUSINESS» => «clientBusiness». Inversa de entityToken(). */
    public static function tokenToEntity(string $token): string
    {
        return self::camel($token);
    }

    /**
     * ¿La propiedad nombra la clave del propio recurso, o la de otro?
     *
     * Un atributo con marcador ID solo es la PK si lo que acompaña al marcador
     * es la entidad misma; cualquier otra cosa apunta fuera de la tabla y es
     * una foreign key. En Person: personId sí, categoryId no.
     */
    public static function isPrimaryKeyName(string $property, string $token): bool
    {
        $property = strtolower($property);
        $entity   = strtolower(str_replace('_', '', $token));

        return $property === 'id'
            || $property === $entity . 'id'
            || $property === 'id' . $entity;
    }

    /**
     * Tipo PHP a partir del tipo SQL, con el marcador como desempate.
     *
     * El esquema manda: STATUS suele ser booleano, pero si la columna es un
     * varchar el estado es una cadena y así se genera.
     */
    public static function phpTypeFor(string $column, string $sqlType): string
    {
        $sqlType = strtolower($sqlType);
        $marker  = self::markerOf($column);

        if (str_starts_with($sqlType, 'tinyint(1)')) {
            return $marker === 'STATUS' ? 'bool' : 'int';
        }

        return match (true) {
            (bool) preg_match('/^(date|datetime|timestamp)/', $sqlType) => 'DateTimeImmutable',
            (bool) preg_match('/^(decimal|numeric|float|double|real)/', $sqlType) => 'float',
            (bool) preg_match('/^(int|bigint|smallint|mediumint|tinyint|bit)/', $sqlType) => 'int',
            default => 'string',
        };
    }

    /** Marcador de la columna, esté antepuesto o pospuesto. */
    private static function markerOf(string $column): string
    {
        $column = strtoupper($column);

        if (in_array($column, self::PREFIXES, true)) {
            return $column;
        }

        if (str_ends_with($column, '_AMOUNT')) {
            return 'AMOUNT';
        }

        $head = strtok($column, '_');

        return in_array($head, self::PREFIXES, true) ? $head : '';
    }

    private static function camel(string $upperSnake): string
    {
        return lcfirst(str_replace('_', '', ucwords(strtolower($upperSnake), '_')));
    }
}
