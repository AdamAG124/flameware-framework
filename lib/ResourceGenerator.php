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
    /** @param list<FieldSpec> $fields */
    public function __construct(
        private readonly string $projectPath,
        private readonly string $resource,
        private readonly string $table,
        private readonly array $fields,
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

        foreach ($files as $relative => $contents) {
            $path = $this->projectPath . '/' . $relative;

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $contents);
        }

        return array_keys($files);
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

            if ($field->isDate()) {
                $casts[] = sprintf("        '%s' => 'datetime',", $field->column);

                $toModel[] = sprintf(
                    "        \$model->set%s(\n            %s !== null\n                ? DateTimeImmutable::createFromInterface(%s)\n                : null,\n        );",
                    $field->studly(),
                    $accessor,
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

            $toEloquent[] = sprintf(
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
            "<?php\n\nnamespace App\\EloquentModels;\n\nuse App\\Models\\%s;\n%suse Illuminate\\Database\\Eloquent\\Model as EloquentModel;\n\n"
            . "/**\n * Única clase que conoce los nombres de las columnas.\n * Nada por encima del Repository debe importar esta clase.\n */\n"
            . "class %sEloquentModel extends EloquentModel\n{\n"
            . "    protected \$table = '%s';\n    protected \$primaryKey = '%s';\n    public \$timestamps = false;\n%s\n"
            . "    public function toModel(): %s\n    {\n        \$model = new %s();\n\n%s\n\n        return \$model;\n    }\n\n"
            . "    public function toEloquentModel(%s \$model): self\n    {\n%s\n\n"
            . "        // La PK solo se asigna en actualizaciones; en un insert la genera la BD.\n"
            . "        if (\$model->get%s() > 0) {\n            \$this->%s = \$model->get%s();\n        }\n\n        return \$this;\n    }\n}\n",
            $this->resource,
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
            . "    public function update(%s \$model): void\n    {\n        %sEloquentModel::query()\n            ->findOrFail(\$model->getId())\n"
            . "            ->toEloquentModel(\$model)\n            ->save();\n    }\n\n"
            . "    public function deleteById(int \$id): bool\n    {\n        return %sEloquentModel::query()->whereKey(\$id)->delete() > 0;\n    }\n}\n",
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource,
            $this->resource,
        );
    }

    private function mapper(): string
    {
        $toDto = [];

        foreach ($this->fields as $field) {
            $toDto[] = $field->isDate()
                ? sprintf('        $dto->%s = $model->get%s()?->format(DATE_ATOM);', $field->property, $field->studly())
                : sprintf('        $dto->%s = $model->get%s();', $field->property, $field->studly());
        }

        $toModel = [];

        foreach ($this->writableFields() as $field) {
            $toModel[] = sprintf('        $model->set%s($dto->%s);', $field->studly(), $field->property);
        }

        return sprintf(
            "<?php\n\nnamespace App\\Mappers;\n\nuse App\\DTOs\\Request\\%sRequestDto;\nuse App\\DTOs\\Response\\%sResponseDto;\nuse App\\Models\\%s;\n\n"
            . "/**\n * Traduce entre el contrato del API y el dominio.\n * Sin lógica de negocio ni validación: eso vive en el Service y en los #[Assert].\n */\n"
            . "class %sMapper\n{\n    public function toDto(%s \$model): %sResponseDto\n    {\n        \$dto = new %sResponseDto();\n\n%s\n\n        return \$dto;\n    }\n\n"
            . "    public function toModel(%sRequestDto \$dto): %s\n    {\n        \$model = new %s();\n\n%s\n\n        return \$model;\n    }\n}\n",
            $this->resource, $this->resource, $this->resource,
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
            $asserts = [];

            if ($field->phpType === 'string' && !$field->nullable) {
                $asserts[] = '    #[Assert\NotBlank]';
            } elseif (in_array($field->phpType, ['int', 'float'], true) && !$field->nullable) {
                $asserts[] = '    #[Assert\NotNull]';
            }

            $properties[] = sprintf(
                "    #[Serializer\\Type(\"%s\")]\n    #[Serializer\\SerializedName(\"%s\")]\n%s    public %s \$%s;",
                $this->serializerType($field),
                $field->property,
                $asserts === [] ? '' : implode("\n", $asserts) . "\n",
                $field->hint(),
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
            // Las fechas viajan como cadena ISO-8601 en el JSON.
            $type = $field->isDate() ? 'string' : $this->serializerType($field);
            $hint = $field->isDate() ? '?string' : $field->hint();
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
            "<?php\n\nnamespace App\\Services;\n\nuse App\\Models\\%s;\n\n"
            . "interface I%sService\n{\n"
            . "    public function findById(int \$id): ?%s;\n\n"
            . "    /** @return list<%s> */\n    public function findAll(): array;\n\n"
            . "    public function create(%s \$model): %s;\n\n"
            . "    public function update(%s \$model): void;\n\n"
            . "    public function delete(int \$id): bool;\n}\n",
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource,
        );
    }

    private function service(): string
    {
        return sprintf(
            "<?php\n\nnamespace App\\Services;\n\nuse App\\Models\\%s;\nuse App\\Repository\\I%sRepository;\n\n"
            . "/**\n * Aquí va la lógica de negocio. Recibe la interfaz del repositorio,\n * nunca la implementación concreta.\n */\n"
            . "class %sService implements I%sService\n{\n"
            . "    public function __construct(\n        private readonly I%sRepository \$repository,\n    ) {}\n\n"
            . "    public function findById(int \$id): ?%s\n    {\n        return \$this->repository->findById(\$id);\n    }\n\n"
            . "    public function findAll(): array\n    {\n        return \$this->repository->findAll();\n    }\n\n"
            . "    public function create(%s \$model): %s\n    {\n        return \$this->repository->save(\$model);\n    }\n\n"
            . "    public function update(%s \$model): void\n    {\n        \$this->repository->update(\$model);\n    }\n\n"
            . "    public function delete(int \$id): bool\n    {\n        return \$this->repository->deleteById(\$id);\n    }\n}\n",
            $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource, $this->resource,
        );
    }

    private function controller(): string
    {
        $plural = $this->plural();

        return sprintf(
            "<?php\n\nnamespace App\\Controllers;\n\nuse App\\DTOs\\Request\\%sRequestDto;\nuse App\\Mappers\\%sMapper;\n"
            . "use App\\Routing\\Http;\nuse App\\Routing\\Route;\nuse App\\Services\\I%sService;\n"
            . "use JMS\\Serializer\\SerializerBuilder;\nuse JMS\\Serializer\\SerializerInterface;\n"
            . "use Symfony\\Component\\HttpFoundation\\JsonResponse;\nuse Symfony\\Component\\HttpFoundation\\Request;\nuse Symfony\\Component\\HttpFoundation\\Response;\n\n"
            . "class %sController\n{\n    private SerializerInterface \$serializer;\n\n"
            . "    public function __construct(\n        private readonly I%sService \$service,\n        private readonly %sMapper \$mapper,\n    ) {\n        \$this->serializer = SerializerBuilder::create()->build();\n    }\n\n"
            . "    #[Route(Http::GET, '%s')]\n    public function index(): Response\n    {\n        \$dtos = array_map(\n            fn (\$model) => \$this->mapper->toDto(\$model),\n            \$this->service->findAll(),\n        );\n\n        return \$this->json(\$dtos);\n    }\n\n"
            . "    #[Route(Http::GET, '%s/{id}')]\n    public function show(array \$params): Response\n    {\n        \$model = \$this->service->findById((int) \$params['id']);\n\n"
            . "        if (\$model === null) {\n            return \$this->error('No encontrado.', Response::HTTP_NOT_FOUND);\n        }\n\n        return \$this->json(\$this->mapper->toDto(\$model));\n    }\n\n"
            . "    #[Route(Http::POST, '%s')]\n    public function store(): Response\n    {\n        \$dto = \$this->deserialize();\n        \$model = \$this->service->create(\$this->mapper->toModel(\$dto));\n\n"
            . "        return \$this->json(\$this->mapper->toDto(\$model), Response::HTTP_CREATED);\n    }\n\n"
            . "    #[Route(Http::PUT, '%s/{id}')]\n    public function update(array \$params): Response\n    {\n        \$dto = \$this->deserialize();\n        \$model = \$this->mapper->toModel(\$dto);\n        \$model->setId((int) \$params['id']);\n\n"
            . "        \$this->service->update(\$model);\n\n        return \$this->json(\$this->mapper->toDto(\$model));\n    }\n\n"
            . "    #[Route(Http::DELETE, '%s/{id}')]\n    public function destroy(array \$params): Response\n    {\n"
            . "        if (!\$this->service->delete((int) \$params['id'])) {\n            return \$this->error('No encontrado.', Response::HTTP_NOT_FOUND);\n        }\n\n"
            . "        return new JsonResponse(null, Response::HTTP_NO_CONTENT)->send();\n    }\n\n"
            . "    private function deserialize(): %sRequestDto\n    {\n        return \$this->serializer->deserialize(\n            Request::createFromGlobals()->getContent(),\n            %sRequestDto::class,\n            'json',\n        );\n    }\n\n"
            . "    private function json(mixed \$payload, int \$status = Response::HTTP_OK): Response\n    {\n"
            . "        return new JsonResponse(\$this->serializer->serialize(\$payload, 'json'), \$status, [], true)->send();\n    }\n\n"
            . "    private function error(string \$message, int \$status): Response\n    {\n"
            . "        return new JsonResponse(['success' => false, 'message' => \$message], \$status)->send();\n    }\n}\n",
            $this->resource, $this->resource, $this->resource,
            $this->resource, $this->resource, $this->resource,
            $plural, $plural, $plural, $plural, $plural,
            $this->resource, $this->resource,
        );
    }
}
