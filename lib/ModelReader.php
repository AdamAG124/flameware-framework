<?php

declare(strict_types=1);

/**
 * Lee los Models ya presentes en el proyecto.
 *
 * Cuando un campo se declara con el tipo de otro recurso —«personId:Person»—
 * el generador necesita dos cosas de esa clase: que exista, y cómo se llama su
 * clave, porque es la que viaja en la columna y en el DTO.
 *
 * Se lee el archivo como texto en lugar de reflexionar la clase: el binario
 * corre fuera del proyecto y no tiene su autoloader cargado.
 */
final class ModelReader
{
    /** Captura «private ?Tipo $propiedad;» de una propiedad declarada. */
    private const PROPERTY = '/^\s*private\s+\??([A-Za-z_][A-Za-z0-9_]*)\s+\$([A-Za-z_][A-Za-z0-9_]*)\s*;/m';

    public function __construct(
        private readonly string $projectPath,
    ) {}

    public function exists(string $class): bool
    {
        return is_file($this->pathOf($class));
    }

    /**
     * Propiedad que hace de clave primaria en el Model indicado.
     *
     * @throws RuntimeException si la clase no está o no se le reconoce la clave
     */
    public function primaryKeyOf(string $class): string
    {
        $token = NamingConvention::entityToken($class);

        foreach ($this->propertiesOf($class) as $property => $type) {
            if (NamingConvention::isPrimaryKeyName($property, $token)) {
                return $property;
            }
        }

        throw new RuntimeException(sprintf(
            "El Model «%s» no tiene una propiedad que se reconozca como su clave primaria.\n"
            . "      Se buscó «id», «%sId» o «id%s» en Models/%s.php.",
            $class,
            lcfirst($class),
            $class,
            $class,
        ));
    }

    /**
     * Models a los que apunta este Model por el tipo de sus propiedades.
     *
     * @return list<string>
     */
    public function referencesOf(string $class): array
    {
        $references = [];

        foreach ($this->propertiesOf($class) as $type) {
            if ($this->exists($type)) {
                $references[] = $type;
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @return array<string,string> propiedad => tipo declarado
     * @throws RuntimeException si la clase no existe en el proyecto
     */
    private function propertiesOf(string $class): array
    {
        $path = $this->pathOf($class);

        if (!is_file($path)) {
            throw new RuntimeException(sprintf(
                "No existe el Model «%s»: se esperaba en Models/%s.php.\n"
                . "      Genera ese recurso antes de referenciarlo:\n"
                . "        flameware make:resource %s --fields=\"...\"",
                $class,
                $class,
                $class,
            ));
        }

        preg_match_all(self::PROPERTY, (string) file_get_contents($path), $matches, PREG_SET_ORDER);

        $properties = [];

        foreach ($matches as [, $type, $property]) {
            $properties[$property] = $type;
        }

        return $properties;
    }

    private function pathOf(string $class): string
    {
        return $this->projectPath . '/Models/' . $class . '.php';
    }
}
