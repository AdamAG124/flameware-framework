<?php

declare(strict_types=1);

/**
 * Escribe el slice completo de un recurso a partir de una lista de FieldSpec.
 *
 * Todas las capas se generan recorriendo la misma lista, que es el punto: un
 * atributo aparece seis veces (propiedad, getter, setter, las dos conversiones
 * y el mapper) y ninguna de esas copias se escribe a mano.
 */
final class ResourceGenerator
{
    /**
     * Clases compartidas que los controladores generados dan por existentes.
     * Se copian del skeleton al proyecto si faltan, nunca se sobrescriben.
     */
    private const SHARED = ['Support/Json.php'];

    /** @param list<FieldSpec> $fields */
    public function __construct(
        private readonly string $projectPath,
        private readonly string $resource,
        private readonly string $table,
        private readonly array $fields,
        private readonly string $skeletonPath,
    ) {}

    /**
     * @return list<string> rutas relativas de los archivos escritos
     * @throws RuntimeException si algún archivo ya existe
     */
    public function generate(bool $force): array
    {
        $files = [
            "Models/{$this->resource}.php"                           => $this->model(),
            "EloquentModels/{$this->resource}EloquentModel.php"      => $this->eloquentModel(),
            "Repository/I{$this->resource}Repository.php"            => $this->repositoryInterface(),
            "Repository/{$this->resource}Repository.php"             => $this->repository(),
            "Mappers/{$this->resource}Mapper.php"                    => $this->mapper(),
            "DTOs/Request/{$this->resource}RequestDto.php"           => $this->requestDto(),
            "DTOs/Response/{$this->resource}ResponseDto.php"         => $this->responseDto(),
            "Services/I{$this->resource}Service.php"                 => $this->serviceInterface(),
            "Services/{$this->resource}Service.php"                  => $this->service(),
            "Controllers/{$this->resource}Controller.php"            => $this->controller(),
        ];

        if (!$force) {
            $existing = array_filter(
                array_keys($files),
                fn (string $relative): bool => file_exists($this->projectPath . '/' . $relative),
            );

            if ($existing !== []) {
                throw new RuntimeException(
                    "Estos archivos ya existen (usa --force para sobrescribir):\n      "
                    . implode("\n      ", $existing)
                );
            }
        }

        $written = $this->copySharedClasses();

        foreach ($files as $relative => $contents) {
            $this->write($relative, $contents);
            $written[] = $relative;
        }

        return $written;
    }

    /**
     * Un proyecto creado con una versión anterior del generador no tiene las
     * clases compartidas, y los controladores nuevos las necesitan. Se copian
     * tal cual del skeleton; si ya están, no se tocan —pueden estar editadas.
     *
     * @return list<string>
     * @throws RuntimeException si el skeleton está incompleto
     */
    private function copySharedClasses(): array
    {
        $written = [];

        foreach (self::SHARED as $relative) {
            if (file_exists($this->projectPath . '/' . $relative)) {
                continue;
            }

            $source = $this->skeletonPath . '/' . $relative;

            if (!is_file($source)) {
                throw new RuntimeException("Falta {$relative} en el skeleton: la instalación de Flameware está incompleta.");
            }

            $this->write($relative, (string) file_get_contents($source));
            $written[] = $relative;
        }

        return $written;
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->projectPath . '/' . $relative;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }

    // ------------------------------------------------------------- selectores

    /** Campos que el cliente envía: sin PK y sin fechas gestionadas por la BD. */
    private function writableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (FieldSpec $f): bool => !$f->isPrimaryKey && !$f->isDate(),
        ));
    }

    private function primaryKey(): FieldSpec
    {
        foreach ($this->fields as $field) {
            if ($field->isPrimaryKey) {
                return $field;
            }
        }

        return $this->fields[0];
    }

    /** Models referenciados por el tipo de algún campo, sin repetir. */
    private function relatedModels(): array
    {
        $related = [];

        foreach ($this->fields as $field) {
            if ($field->isModel()) {
                $related[$field->phpType] = true;
            }
        }

        return array_keys($related);
    }

    /**
     * Bloque «use App\Models\*» del recurso y de todo lo que referencia, en
     * orden alfabético. Para los archivos que viven fuera de App\Models.
     */
    private function modelImports(): string
    {
        $classes = [$this->resource, ...$this->relatedModels()];

        sort($classes);

        return implode('', array_map(
            static fn (string $class): string => "use App\\Models\\{$class};\n",
            $classes,
        ));
    }

    private function usesDates(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->isDate()) {
                return true;
            }
        }

        return false;
    }

    private function plural(): string
    {
        $word = lcfirst($this->resource);

        return match (true) {
            (bool) preg_match('/(s|x|z|ch|sh)$/i', $word) => $word . 'es',
            (bool) preg_match('/[^aeiou]y$/i', $word)     => substr($word, 0, -1) . 'ies',
            default                                       => $word . 's',
        };
    }

    // -------------------------------------------------------------- plantillas

    private function model(): string
    {
        $properties = [];
        $assigns    = [];
        $accessors  = [];

        foreach ($this->fields as $field) {
            $properties[] = sprintf('    private %s $%s;', $field->hint(), $field->property);
            $assigns[]    = sprintf('        $this->%s = %s;', $field->property, $field->neutralValue());

            $accessors[] = sprintf(
                "    public function get%s(): %s\n    {\n        return \$this->%s;\n    }",
                $field->studly(),
                $field->hint(),
                $field->property,
            );

            $accessors[] = sprintf(
                "    public function set%s(%s \$%s): void\n    {\n        \$this->%s = \$%s;\n    }",
                $field->studly(),
                $field->hint(),
                $field->property,
                $field->property,
                $field->property,
            );
        }

        $use = $this->usesDates() ? "\nuse DateTimeImmutable;\n" : '';

        return sprintf(
            "<?php\n\nnamespace App\\Models;\n%s\n/**\n * Objeto de dominio. Sin herencia, sin ORM, sin anotaciones:\n * solo estado encapsulado y comportamiento.\n */\nclass %s\n{\n%s\n\n    public function __construct()\n    {\n%s\n    }\n\n%s\n}\n",
            $use,
            $this->resource,
            implode("\n", $properties),
            implode("\n", $assigns),
            implode("\n\n", $accessors),
        );
    }

    private function eloquentModel(): string
    {
        $toModel     = [];
        $toEloquent  = [];
        $casts       = [];
        $primaryKey  = $this->primaryKey();

        foreach ($this->fields as $field) {
            $accessor = '$this->' . $field->column;

            if ($field->isModel()) {
                // La columna solo trae la clave, así que el objeto se arma con
                // ella y nada más. Cargarlo entero es trabajo del Service: un
                // EloquentModel no puede depender de un Repository.
                $build = static fn (string $pad): string => sprintf(
                    "%s\$%s = new %s();\n%s\$%s->%s((int) %s);\n%s\$model->set%s(\$%s);",
                    $pad, $field->property, $field->phpType,
                    $pad, $field->property, $field->relatedSetter(), $accessor,
                    $pad, $field->studly(), $field->property,
                );

                $toModel[] = $field->nullable
                    ? sprintf(
                        "        if (%s === null) {\n            \$model->set%s(null);\n        } else {\n%s\n        }",
                        $accessor,
                        $field->studly(),
                        $build('            '),
                    )
                    : $build('        ');
            } elseif ($field->isDate()) {
                $casts[] = sprintf("        '%s' => 'datetime',", $field->column);

                // Eloquent entrega Carbon, de ahí la conversión. El guardia de
                // null solo se escribe si la propiedad lo admite: pasárselo a
                // un setter no nullable sería un TypeError.
                $toModel[] = $field->nullable
                    ? sprintf(
                        "        \$model->set%s(\n            %s !== null\n                ? DateTimeImmutable::createFromInterface(%s)\n                : null,\n        );",
                        $field->studly(),
                        $accessor,
                        $accessor,
                    )
                    : sprintf(
                        '        $model->set%s(DateTimeImmutable::createFromInterface(%s));',
                        $field->studly(),
                        $accessor,
                    );
            } elseif ($field->nullable) {
                $toModel[] = sprintf(
                    '        $model->set%s(%s === null ? null : %s);',
                    $field->studly(),
                    $accessor,
                    $field->castExpression($accessor),
                );
            } else {
                $toModel[] = sprintf(
                    '        $model->set%s(%s);',
                    $field->studly(),
                    $field->castExpression($accessor),
                );
            }

            if ($field->isPrimaryKey) {
                continue;
            }

            // De un Model referenciado solo baja su clave: es lo único que
            // cabe en la columna.
            $toEloquent[] = $field->isModel()
                ? sprintf(
                    '        $this->%s = $model->get%s()%s->%s();',
                    $field->column,
                    $field->studly(),
                    $field->nullable ? '?' : '',
                    $field->relatedGetter(),
                )
                : sprintf(
                    '        $this->%s = $model->get%s();',
                    $field->column,
                    $field->studly(),
                );
        }

        $castsBlock = $casts === []
            ? ''
            : sprintf("\n    protected \$casts = [\n%s\n    ];\n", implode("\n", $casts));

        $use = $this->usesDates() ? "use DateTimeImmutable;\n" : '';

        return sprintf(
            "<?php\n\nnamespace App\\EloquentModels;\n\n%s%suse Illuminate\\Database\\Eloquent\\Model as EloquentModel;\n\n"
            . "/**\n * Única clase que conoce los nombres de las columnas.\n * Nada por encima del Repository debe importar esta clase.\n */\n"
            . "class %sEloquentModel extends EloquentModel\n{\n"
            . "    protected \$table = '%s';\n    protected \$primaryKey = '%s';\n    public \$timestamps = false;\n%s\n"
            . "    public function toModel(): %s\n    {\n        \$model = new %s();\n\n%s\n\n        return \$model;\n    }\n\n"
            . "    public function toEloquentModel(%s \$model): self\n    {\n%s\n\n"
            . "        // La PK solo se asigna en actualizaciones; en un insert la genera la BD.\n"
            . "        if (\$model->get%s() > 0) {\n            \$this->%s = \$model->get%s();\n        }\n\n        return \$this;\n    }\n}\n",
            $this->modelImports(),
            $use,
            $this->resource,
            $this->table,
            $primaryKey->column,
            $castsBlock,
            $this->resource,
            $this->resource,
            implode("\n", $toModel),
            $this->resource,
            implode("\n", $toEloquent),
            $primaryKey->studly(),
            $primaryKey->column,
            $primaryKey->studly(),
        );
    }

    private function repositoryInterface(): string
    {
        return sprintf(
            "<?php\n\nnamespace App\\Repository;\n\nuse App\\Models\\%s;\n\n"
            . "/**\n * Contrato expresado solo en tipos de dominio: ningún EloquentModel\n"
            . " * aparece en estas firmas, que es lo que permite cambiar el ORM sin\n * tocar Services ni Controllers.\n */\n"
            . "interface I%sRepository\n{\n"
            . "    public function findById(int \$id): ?%s;\n\n"
            . "    /** @return list<%s> */\n    public function findAll(): array;\n\n"
            . "    public function save(%s \$model): %s;\n\n"
            . "    public function update(%s \$model): void;\n\n"
            . "    public function deleteById(int \$id): bool;\n}\n",
            $this->resource,
            $this->resource,
            $this->resource,
            $this->resource,
            $this->resource,
            $this->resource,
            $this->resource,
        );
    }

    private function repository(): string
    {
        return sprintf(
            "<?php\n\nnamespace App\\Repository;\n\nuse App\\EloquentModels\\%sEloquentModel;\nuse App\\Models\\%s;\n\n"
            . "class %sRepository implements I%sRepository\n{\n"
            . "    public function findById(int \$id): ?%s\n    {\n        return %sEloquentModel::query()->find(\$id)?->toModel();\n    }\n\n"
            . "    public function findAll(): array\n    {\n        return %sEloquentModel::query()\n            ->get()\n"
            . "            ->map(static fn (%sEloquentModel \$record): %s => \$record->toModel())\n            ->all();\n    }\n\n"
            . "    public function save(%s \$model): %s\n    {\n        \$record = (new %sEloquentModel())->toEloquentModel(\$model);\n        \$record->save();\n\n"
            . "        // Se reconstruye el dominio para devolver el ID generado por la BD.\n        return \$record->toModel();\n    }\n\n"
            . "    public function update(%s \$model): void\n    {\n        %sEloquentModel::query()\n            ->findOrFail(\$model->get%s())\n"
            . "            ->toEloquentModel(\$model)\n            ->save();\n    }\n\n"
            . "    public function deleteById(int \$id): bool\n    {\n        return %sEloquentModel::query()->whereKey(\$id)->delete() > 0;\n    }\n}\n",
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->primaryKey()->studly(),
            $this->resource,
        );
    }

    private function mapper(): string
    {
        $toDto = [];

        foreach ($this->fields as $field) {
            // Hacia fuera, una referencia viaja como la clave que la identifica:
            // el JSON del API es plano, no anida el recurso entero.
            $toDto[] = match (true) {
                $field->isModel() => sprintf(
                    '        $dto->%s = $model->get%s()%s->%s();',
                    $field->property,
                    $field->studly(),
                    $field->nullable ? '?' : '',
                    $field->relatedGetter(),
                ),
                $field->isDate() => sprintf(
                    '        $dto->%s = $model->get%s()?->format(DATE_ATOM);',
                    $field->property,
                    $field->studly(),
                ),
                default => sprintf(
                    '        $dto->%s = $model->get%s();',
                    $field->property,
                    $field->studly(),
                ),
            };
        }

        $toModel = [];

        foreach ($this->writableFields() as $field) {
            if (!$field->isModel()) {
                $toModel[] = sprintf('        $model->set%s($dto->%s);', $field->studly(), $field->property);
                continue;
            }

            // Y al entrar se rehace el objeto con esa clave. Queda con la
            // referencia puesta y nada más; resolverla es cosa del Service.
            $build = static fn (string $pad): string => sprintf(
                "%s\$%s = new %s();\n%s\$%s->%s(\$dto->%s);\n%s\$model->set%s(\$%s);",
                $pad, $field->property, $field->phpType,
                $pad, $field->property, $field->relatedSetter(), $field->property,
                $pad, $field->studly(), $field->property,
            );

            $toModel[] = $field->nullable
                ? sprintf(
                    "        if (\$dto->%s === null) {\n            \$model->set%s(null);\n        } else {\n%s\n        }",
                    $field->property,
                    $field->studly(),
                    $build('            '),
                )
                : $build('        ');
        }

        return sprintf(
            "<?php\n\nnamespace App\\Mappers;\n\nuse App\\DTOs\\Request\\%sRequestDto;\nuse App\\DTOs\\Response\\%sResponseDto;\n%s\n"
            . "/**\n * Traduce entre el contrato del API y el dominio.\n * Sin lógica de negocio ni validación: eso vive en el Service y en los #[Assert].\n */\n"
            . "class %sMapper\n{\n    public function toDto(%s \$model): %sResponseDto\n    {\n        \$dto = new %sResponseDto();\n\n%s\n\n        return \$dto;\n    }\n\n"
            . "    public function toModel(%sRequestDto \$dto): %s\n    {\n        \$model = new %s();\n\n%s\n\n        return \$model;\n    }\n}\n",
            $this->resource, $this->resource, $this->modelImports(),
            $this->resource, $this->resource, $this->resource, $this->resource,
            implode("\n", $toDto),
            $this->resource, $this->resource, $this->resource,
            implode("\n", $toModel),
        );
    }

    private function requestDto(): string
    {
        $properties = [];

        foreach ($this->writableFields() as $field) {
            $type    = $this->dtoType($field);
            $asserts = [];

            if ($type === 'string' && !$field->nullable) {
                $asserts[] = '    #[Assert\NotBlank]';
            } elseif (in_array($type, ['int', 'float'], true) && !$field->nullable) {
                $asserts[] = '    #[Assert\NotNull]';
            }

            $properties[] = sprintf(
                "    #[Serializer\\Type(\"%s\")]\n    #[Serializer\\SerializedName(\"%s\")]\n%s    public %s \$%s;",
                $type,
                $field->property,
                $asserts === [] ? '' : implode("\n", $asserts) . "\n",
                ($field->nullable ? '?' : '') . $type,
                $field->property,
            );
        }

        return sprintf(
            "<?php\n\nnamespace App\\DTOs\\Request;\n\nuse JMS\\Serializer\\Annotation as Serializer;\nuse Symfony\\Component\\Validator\\Constraints as Assert;\n\n"
            . "/**\n * Contrato de entrada. Las reglas #[Assert] son un punto de partida:\n * ajústalas a las del negocio.\n */\n"
            . "class %sRequestDto\n{\n%s\n}\n",
            $this->resource,
            implode("\n\n", $properties),
        );
    }

    private function responseDto(): string
    {
        $properties = [];

        foreach ($this->fields as $field) {
            $type = $this->dtoType($field);
            // Las fechas siempre pueden faltar: se serializan desde un valor
            // que el dominio admite como null.
            $hint = ($field->isDate() || $field->nullable ? '?' : '') . $type;
            $tail = $field->isDate() || $field->nullable ? ' = null' : '';

            $properties[] = sprintf(
                "    #[Serializer\\Type(\"%s\")]\n    #[Serializer\\SerializedName(\"%s\")]\n    public %s \$%s%s;",
                $type,
                $field->property,
                $hint,
                $field->property,
                $tail,
            );
        }

        return sprintf(
            "<?php\n\nnamespace App\\DTOs\\Response;\n\nuse JMS\\Serializer\\Annotation as Serializer;\n\n"
            . "class %sResponseDto\n{\n%s\n}\n",
            $this->resource,
            implode("\n\n", $properties),
        );
    }

    /**
     * Tipo con el que el campo cruza el API.
     *
     * Los DTOs son planos: una fecha viaja como cadena ISO-8601 y una
     * referencia a otro Model como la clave que lo identifica.
     */
    private function dtoType(FieldSpec $field): string
    {
        return match (true) {
            $field->isModel() => 'int',
            $field->isDate()  => 'string',
            default           => $this->serializerType($field),
        };
    }

    private function serializerType(FieldSpec $field): string
    {
        return match ($field->phpType) {
            'int'               => 'int',
            'float'             => 'float',
            'bool'              => 'bool',
            'DateTimeImmutable' => 'string',
            default             => 'string',
        };
    }

    private function serviceInterface(): string
    {
        return sprintf(
            "<?php\n\nnamespace App\\Services;\n\nuse App\\DTOs\\Request\\%sRequestDto;\nuse App\\DTOs\\Response\\%sResponseDto;\n\n"
            . "/**\n * El contrato del recurso, expresado en DTOs por los dos lados.\n *\n"
            . " * Nada del dominio asoma en estas firmas: el Controller entrega lo que\n"
            . " * llegó por HTTP y recibe lo que se va a serializar, sin más.\n */\n"
            . "interface I%sService\n{\n"
            . "    public function findById(int \$id): ?%sResponseDto;\n\n"
            . "    /** @return list<%sResponseDto> */\n    public function findAll(): array;\n\n"
            . "    public function create(%sRequestDto \$dto): %sResponseDto;\n\n"
            . "    public function update(int \$id, %sRequestDto \$dto): %sResponseDto;\n\n"
            . "    public function delete(int \$id): bool;\n}\n",
            $this->resource, $this->resource,
            $this->resource,
            $this->resource,
            $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource,
        );
    }

    private function service(): string
    {
        return sprintf(
            "<?php\n\nnamespace App\\Services;\n\nuse App\\DTOs\\Request\\%sRequestDto;\nuse App\\DTOs\\Response\\%sResponseDto;\n"
            . "use App\\Mappers\\%sMapper;\nuse App\\Models\\%s;\nuse App\\Repository\\I%sRepository;\n\n"
            . "/**\n * Aquí va la lógica de negocio. Recibe la interfaz del repositorio,\n * nunca la implementación concreta.\n *\n"
            . " * Es también la frontera de traducción: por eso el Mapper se inyecta aquí\n"
            . " * y no en el Controller. Hacia arriba se habla en DTOs y hacia abajo en\n"
            . " * Models, y ninguno de los dos extremos ve el lenguaje del otro.\n */\n"
            . "class %sService implements I%sService\n{\n"
            . "    public function __construct(\n        private readonly I%sRepository \$repository,\n        private readonly %sMapper \$mapper,\n    ) {}\n\n"
            . "    public function findById(int \$id): ?%sResponseDto\n    {\n        \$model = \$this->repository->findById(\$id);\n\n"
            . "        return \$model === null ? null : \$this->mapper->toDto(\$model);\n    }\n\n"
            . "    public function findAll(): array\n    {\n        return array_map(\n"
            . "            fn (%s \$model): %sResponseDto => \$this->mapper->toDto(\$model),\n            \$this->repository->findAll(),\n        );\n    }\n\n"
            . "    public function create(%sRequestDto \$dto): %sResponseDto\n    {\n"
            . "        return \$this->mapper->toDto(\n            \$this->repository->save(\$this->mapper->toModel(\$dto)),\n        );\n    }\n\n"
            . "    public function update(int \$id, %sRequestDto \$dto): %sResponseDto\n    {\n"
            . "        \$model = \$this->mapper->toModel(\$dto);\n        \$model->set%s(\$id);\n\n        \$this->repository->update(\$model);\n\n"
            . "        return \$this->mapper->toDto(\$model);\n    }\n\n"
            . "    public function delete(int \$id): bool\n    {\n        return \$this->repository->deleteById(\$id);\n    }\n}\n",
            $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource, $this->primaryKey()->studly(),
        );
    }

    /**
     * El controlador solo enruta: toma lo que llegó por HTTP, se lo pasa al
     * Service y convierte lo que vuelve en una respuesta. No conoce el Mapper
     * ni el dominio —entra un RequestDto y sale un ResponseDto—, y serializar
     * es trabajo de App\Support\Json.
     */
    private function controller(): string
    {
        $plural = $this->plural();

        return sprintf(
            "<?php\n\nnamespace App\\Controllers;\n\nuse App\\DTOs\\Request\\%sRequestDto;\n"
            . "use App\\Routing\\Http;\nuse App\\Routing\\Route;\nuse App\\Services\\I%sService;\nuse App\\Support\\Json;\n"
            . "use Symfony\\Component\\HttpFoundation\\Response;\n\n"
            . "class %sController\n{\n"
            . "    public function __construct(\n        private readonly I%sService \$service,\n    ) {}\n\n"
            . "    #[Route(Http::GET, '%s')]\n    public function index(): Response\n    {\n        return Json::response(\$this->service->findAll());\n    }\n\n"
            . "    #[Route(Http::GET, '%s/{id}')]\n    public function show(array \$params): Response\n    {\n        \$dto = \$this->service->findById((int) \$params['id']);\n\n"
            . "        if (\$dto === null) {\n            return Json::error('No encontrado.', Response::HTTP_NOT_FOUND);\n        }\n\n        return Json::response(\$dto);\n    }\n\n"
            . "    #[Route(Http::POST, '%s')]\n    public function store(): Response\n    {\n"
            . "        return Json::response(\n            \$this->service->create(Json::body(%sRequestDto::class)),\n            Response::HTTP_CREATED,\n        );\n    }\n\n"
            . "    #[Route(Http::PUT, '%s/{id}')]\n    public function update(array \$params): Response\n    {\n"
            . "        return Json::response(\n            \$this->service->update((int) \$params['id'], Json::body(%sRequestDto::class)),\n        );\n    }\n\n"
            . "    #[Route(Http::DELETE, '%s/{id}')]\n    public function destroy(array \$params): Response\n    {\n"
            . "        if (!\$this->service->delete((int) \$params['id'])) {\n            return Json::error('No encontrado.', Response::HTTP_NOT_FOUND);\n        }\n\n"
            . "        return Json::noContent();\n    }\n}\n",
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $plural,
            $plural,
            $plural, $this->resource,
            $plural, $this->resource,
            $plural,
        );
    }
}
