<?php

namespace App\Support;

/**
 * Metadatos SEO de una página, inyectados desde el controlador:
 *
 *   View::render('pages.home', $data, new Seo(
 *       title:       'Inicio',
 *       description: 'Catering para eventos.',
 *       keywords:    ['catering', 'eventos'],
 *   ));
 *
 * Todo es opcional. Lo que no se indique sale de .env.local (APP_NAME,
 * APP_LOCALE, SEO_DEFAULT_DESCRIPTION, SEO_DEFAULT_IMAGE) o se deriva de la
 * petición, como el canonical. El layout imprime las etiquetas con
 * $this->seo(), ya escapadas.
 */
final class Seo
{
    /**
     * @param string|list<string>  $keywords Lista o cadena separada por comas
     * @param ?string              $canonical Ruta o URL; por defecto la URL actual sin query string
     * @param ?string              $image     Ruta dentro de Public/ o URL absoluta (og:image)
     * @param array<string,mixed>  $jsonLd    Un schema de schema.org, o una lista de ellos
     * @param array<string,string> $meta      Meta tags extra: ['author' => 'Flameware']
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly string|array $keywords = [],
        public readonly ?string $canonical = null,
        public readonly string $robots = 'index, follow',
        public readonly ?string $image = null,
        public readonly string $type = 'website',
        public readonly ?string $locale = null,
        public readonly array $jsonLd = [],
        public readonly array $meta = [],
    ) {}

    /** «Inicio | Mi Sitio»; solo «Mi Sitio» si la página no trae título. */
    public function fullTitle(): string
    {
        $site = self::setting('APP_NAME') ?? '';

        if ($this->title === null || $this->title === '') {
            return $site;
        }

        return $site === '' || $this->title === $site ? $this->title : "{$this->title} | {$site}";
    }

    /** Idioma para <html lang>: es_CR → es-CR. */
    public function lang(): string
    {
        return str_replace('_', '-', $this->resolvedLocale());
    }

    /** Todas las etiquetas del <head> que dependen de la página. */
    public function toHtml(): string
    {
        $description = $this->description ?? self::setting('SEO_DEFAULT_DESCRIPTION');
        $image       = $this->image ?? self::setting('SEO_DEFAULT_IMAGE');
        $image       = match (true) {
            $image === null          => null,
            Url::isAbsolute($image)  => $image,
            default                  => Url::absolute(Url::asset($image, version: false)),
        };
        $keywords    = is_array($this->keywords) ? implode(', ', $this->keywords) : $this->keywords;
        $indexable   = !str_contains(strtolower($this->robots), 'noindex');
        $url         = Url::absolute($this->canonical ?? Url::current());
        $title       = $this->fullTitle();

        $tags = ['<title>' . self::e($title) . '</title>'];

        $tags[] = self::meta('name', 'description', $description);
        $tags[] = self::meta('name', 'keywords', $keywords);
        $tags[] = self::meta('name', 'robots', $this->robots);

        // Una página que no se indexa no debe declararse versión canónica de nada
        if ($indexable) {
            $tags[] = '<link rel="canonical" href="' . self::e($url) . '">';
        }

        $tags[] = self::meta('property', 'og:type', $this->type);
        $tags[] = self::meta('property', 'og:title', $title);
        $tags[] = self::meta('property', 'og:description', $description);
        $tags[] = self::meta('property', 'og:url', $url);
        $tags[] = self::meta('property', 'og:site_name', self::setting('APP_NAME'));
        $tags[] = self::meta('property', 'og:locale', $this->resolvedLocale());
        $tags[] = self::meta('property', 'og:image', $image);

        $tags[] = self::meta('name', 'twitter:card', $image !== null ? 'summary_large_image' : 'summary');
        $tags[] = self::meta('name', 'twitter:title', $title);
        $tags[] = self::meta('name', 'twitter:description', $description);
        $tags[] = self::meta('name', 'twitter:image', $image);

        foreach ($this->meta as $name => $content) {
            $tags[] = self::meta(str_starts_with($name, 'og:') ? 'property' : 'name', $name, $content);
        }

        if ($this->jsonLd !== []) {
            // JSON_HEX_TAG convierte < y > en <: un texto con </script>
            // no puede cerrar la etiqueta antes de tiempo
            $tags[] = '<script type="application/ld+json">'
                . json_encode($this->jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR)
                . '</script>';
        }

        return implode("\n    ", array_filter($tags, static fn (string $tag): bool => $tag !== ''));
    }

    private function resolvedLocale(): string
    {
        return $this->locale ?? self::setting('APP_LOCALE') ?? 'es';
    }

    /** Las etiquetas sin valor se omiten en vez de imprimirse vacías. */
    private static function meta(string $attribute, string $name, ?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        return sprintf('<meta %s="%s" content="%s">', $attribute, self::e($name), self::e($content));
    }

    /** Variable de .env.local, o null si no existe o está vacía. */
    private static function setting(string $key): ?string
    {
        $value = defined($key) ? (string) constant($key) : '';

        return $value !== '' ? $value : null;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
