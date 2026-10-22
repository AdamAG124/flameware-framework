<?php

namespace App\Support;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * El $this dentro de las vistas. Una instancia por render: guarda las
 * secciones, el layout elegido y el SEO de la página.
 *
 * Las vistas son PHP plano. Lo que imprimen los helpers ya es seguro en HTML
 * —content(), section(), component() y seo() devuelven marcado, y url() y
 * asset() vienen escapados para un atributo—; cualquier dato propio se
 * imprime con $this->e().
 *
 *   <h1><?= $this->e($product->name) ?></h1>
 *   <?= $this->component('card', ['title' => 'Hola']) ?>
 */
final class Template
{
    /** @var array<string,string> */
    private array $sections = [];

    /** @var list<string> */
    private array $open = [];

    private string $content = '';

    private ?string $layout = null;

    public function __construct(
        private readonly string $root,
        public readonly Seo $seo,
    ) {}

    /**
     * Renderiza la vista y después su layout, que la recibe en content().
     *
     * @param array<string,mixed> $data Variables de la vista; el layout también las recibe
     */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $this->layout  = $layout;
        $this->content = $this->capture($view, $data);

        if ($this->layout === null) {
            return $this->content;
        }

        return $this->capture('layouts.' . $this->layout, $data);
    }

    /** Escapa un valor para imprimirlo en HTML, dentro o fuera de un atributo. */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Componente de Views/components/ con su propio ámbito: solo ve las props
     * que recibe, nunca las variables de la vista que lo llama.
     *
     * @param array<string,mixed> $props
     */
    public function component(string $name, array $props = []): string
    {
        return $this->capture('components.' . $name, $props);
    }

    /** Cambia el layout desde la propia vista: <?php $this->layout('admin') ?> */
    public function layout(?string $name): void
    {
        $this->layout = $name;
    }

    /**
     * Abre una sección; todo lo que se imprima hasta end() va a ella.
     * Si varias vistas o componentes abren la misma, su contenido se acumula.
     */
    public function start(string $name): void
    {
        $this->open[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->open) ?? throw new LogicException('end() sin un start() abierto.');

        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    /** Imprime una sección en el layout: <?= $this->section('scripts') ?> */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** El HTML de la vista, para imprimirlo dentro del layout. */
    public function content(): string
    {
        return $this->content;
    }

    /** Las etiquetas SEO del <head>. */
    public function seo(): string
    {
        return $this->seo->toHtml();
    }

    /** Ruta de una página, escapada: <a href="<?= $this->url('nosotros') ?>"> */
    public function url(string $path = ''): string
    {
        return $this->e(Url::to($path));
    }

    /** Archivo de Public/, escapado y versionado: <?= $this->asset('css/app.css') ?> */
    public function asset(string $path): string
    {
        return $this->e(Url::asset($path));
    }

    /**
     * Ejecuta un archivo de vista y devuelve lo que imprime. Si la vista
     * lanza una excepción se descartan sus buffers, incluidas las secciones
     * que dejó abiertas, para que no se escape HTML a medias.
     *
     * @param array<string,mixed> $data
     */
    private function capture(string $view, array $data): string
    {
        $file  = $this->resolve($view);
        $level = ob_get_level();
        $open  = count($this->open);

        ob_start();

        try {
            // func_get_arg en vez de parámetros: así ninguna variable local
            // choca con las del controlador al hacer extract()
            (function (): void {
                extract(func_get_arg(1), EXTR_SKIP);
                include func_get_arg(0);
            })->call($this, $file, $data);

            if (count($this->open) > $open) {
                throw new LogicException(sprintf('La sección «%s» de «%s» nunca se cerró con end().', end($this->open), $view));
            }

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            $this->open = array_slice($this->open, 0, $open);

            throw $e;
        }
    }

    /** 'pages.home' → Views/pages/home.php */
    private function resolve(string $view): string
    {
        // Solo letras, números, guion y guion bajo por segmento: un nombre
        // de vista nunca puede salir de Views/ con ../
        if (!preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $view)) {
            throw new InvalidArgumentException("Nombre de vista inválido: «{$view}».");
        }

        $file = $this->root . '/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Vista no encontrada: «{$view}» (Views/" . str_replace('.', '/', $view) . '.php).');
        }

        return $file;
    }
}
