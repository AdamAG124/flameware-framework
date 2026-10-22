<?php

declare(strict_types=1);

/**
 * Escribe un archivo de vista a partir de su nombre en notación de puntos.
 *
 * El primer segmento decide la plantilla, igual que decide la carpeta:
 *
 *   pages.contacto        → Views/pages/contacto.php       página + snippet del controlador
 *   components.forms.input → Views/components/forms/input.php componente con props
 *   layouts.admin         → Views/layouts/admin.php         documento HTML completo
 *   errors.403            → Views/errors/403.php            página sin ruta propia
 *   emails.bienvenida     → Views/emails/bienvenida.php     fragmento para View::fetch()
 */
final class ViewGenerator
{
    /** Mismo patrón que Template::resolve(): nada que pueda salir de Views/. */
    private const NAME = '/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/';

    /** @var list<string> */
    private readonly array $segments;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $view,
    ) {
        if (!self::isValidName($view)) {
            throw new RuntimeException("Nombre de vista inválido: «{$view}». Usa segmentos con letras, números, guion o guion bajo separados por puntos, por ejemplo «pages.contacto».");
        }

        $this->segments = explode('.', $view);
    }

    public static function isValidName(string $view): bool
    {
        return (bool) preg_match(self::NAME, $view);
    }

    /** 'page', 'component', 'layout', 'error' o 'partial' */
    public function kind(): string
    {
        if (count($this->segments) < 2) {
            return 'partial';
        }

        return match ($this->segments[0]) {
            'pages'      => 'page',
            'components' => 'component',
            'layouts'    => 'layout',
            'errors'     => 'error',
            default      => 'partial',
        };
    }

    public function relativePath(): string
    {
        return 'Views/' . implode('/', $this->segments) . '.php';
    }

    /**
     * @return string ruta relativa del archivo escrito
     * @throws RuntimeException si el archivo ya existe y no se pasó --force
     */
    public function generate(bool $force): string
    {
        $relative = $this->relativePath();
        $path     = $this->projectPath . '/' . $relative;

        if (!$force && file_exists($path)) {
            throw new RuntimeException("La vista ya existe (usa --force para sobrescribir): {$relative}");
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, match ($this->kind()) {
            'page'      => $this->page(),
            'component' => $this->component(),
            'layout'    => $this->layout(),
            'error'     => $this->error(),
            default     => $this->partial(),
        });

        return $relative;
    }

    /**
     * El método que hay que pegar en un controlador para servir la página.
     * Solo las páginas tienen ruta; el resto de vistas no devuelve nada.
     */
    public function controllerSnippet(): ?string
    {
        if ($this->kind() !== 'page') {
            return null;
        }

        $route = $this->route();

        return strtr(<<<'PHP'
            #[Page(Http::GET, '{{ROUTE}}')]
            public function {{METHOD}}(): Response
            {
                return View::render('{{VIEW}}', [], new Seo(
                    title: '{{TITLE}}',
                    description: '',
                ));
            }
            PHP, [
            '{{ROUTE}}'  => $route,
            '{{METHOD}}' => $this->methodName(),
            '{{VIEW}}'   => $this->view,
            '{{TITLE}}'  => $this->title(),
        ]);
    }

    // -------------------------------------------------------------- plantillas

    private function page(): string
    {
        return strtr(<<<'PHP'
            <?php
            /**
             * {{TITLE}} — se sirve en /{{ROUTE}}.
             *
             * Cada variable que el controlador pase a View::render() llega
             * aquí con su nombre. Decláralas abajo para que el IDE las conozca.
             *
             * @var \App\Support\Template $this
             */
            ?>
            <section>
                <h1>{{TITLE}}</h1>
            </section>

            PHP, [
            '{{TITLE}}' => $this->title(),
            '{{ROUTE}}' => $this->route(),
        ]);
    }

    private function component(): string
    {
        $name = implode('.', array_slice($this->segments, 1));

        return strtr(<<<'PHP'
            <?php
            /**
             * Componente {{NAME}}. Solo ve las props que recibe:
             *   <?= $this->component('{{NAME}}', ['title' => '...']) ?>
             *
             * @var \App\Support\Template $this
             * @var string $title
             */
            ?>
            <div class="{{CLASS}}">
                <?= $this->e($title) ?>
            </div>

            PHP, [
            '{{NAME}}'  => $name,
            '{{CLASS}}' => str_replace('.', '-', $name),
        ]);
    }

    private function layout(): string
    {
        $name = implode('.', array_slice($this->segments, 1));

        return strtr(<<<'PHP'
            <?php
            /**
             * Layout {{NAME}}. Una vista lo usa con View::render(..., layout: '{{NAME}}')
             * o desde la propia vista con <?php $this->layout('{{NAME}}') ?>.
             *
             * @var \App\Support\Template $this
             */
            ?>
            <!DOCTYPE html>
            <html lang="<?= $this->e($this->seo->lang()) ?>">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?= $this->seo() ?>

                <link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
                <?= $this->section('styles') ?>
            </head>
            <body>
                <main>
                    <?= $this->content() ?>
                </main>

                <?= $this->section('scripts') ?>
            </body>
            </html>

            PHP, ['{{NAME}}' => $name]);
    }

    private function error(): string
    {
        $status = implode('.', array_slice($this->segments, 1));

        return strtr(<<<'PHP'
            <?php
            /**
             * Página de error {{STATUS}}. Se muestra con View::error({{STATUS}}).
             *
             * @var \App\Support\Template $this
             */
            ?>
            <section>
                <h1>Error {{STATUS}}</h1>
                <p><a href="<?= $this->url() ?>">Volver al inicio</a></p>
            </section>

            PHP, ['{{STATUS}}' => $status]);
    }

    private function partial(): string
    {
        return strtr(<<<'PHP'
            <?php
            /**
             * Fragmento {{VIEW}}. Sin layout, para View::fetch('{{VIEW}}', $datos).
             *
             * @var \App\Support\Template $this
             */
            ?>
            <div>
            </div>

            PHP, ['{{VIEW}}' => $this->view]);
    }

    // ------------------------------------------------------------- utilidades

    /** pages.blog.index → 'blog'; pages.sobre-nosotros → 'sobre-nosotros' */
    private function route(): string
    {
        $path = array_slice($this->segments, 1);

        if (end($path) === 'index') {
            array_pop($path);
        }

        return implode('/', $path);
    }

    /** pages.sobre-nosotros → sobreNosotros; pages.index → index */
    private function methodName(): string
    {
        $words = preg_split('/[-_.\/]+/', $this->route() !== '' ? $this->route() : 'index') ?: ['index'];
        $name  = lcfirst(implode('', array_map('ucfirst', $words)));

        // Un método no puede empezar por dígito: pages.404 → page404
        return preg_match('/^\d/', $name) ? 'page' . $name : $name;
    }

    /** pages.sobre-nosotros → «Sobre nosotros» */
    private function title(): string
    {
        $last = $this->route() !== '' ? basename($this->route()) : 'inicio';

        return ucfirst(str_replace(['-', '_'], ' ', $last));
    }
}
