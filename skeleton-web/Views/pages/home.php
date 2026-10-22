<?php
/**
 * Página de ejemplo — muestra variables, componentes y secciones.
 * Bórrala junto con HomeController cuando ya no la necesites.
 *
 * @var \App\Support\Template $this
 * @var string $headline
 * @var list<array{title: string, body: string}> $features
 */
?>
<section class="hero">
    <h1><?= $this->e($headline) ?></h1>
    <p>Esta página se renderizó en el servidor: los buscadores reciben el HTML completo.</p>
</section>

<section class="grid">
    <?php foreach ($features as $feature): ?>
        <?= $this->component('card', $feature) ?>
    <?php endforeach ?>
</section>

<?php $this->start('scripts') ?>
<script>
    console.info('Script propio de la página de inicio, inyectado en la sección «scripts».');
</script>
<?php $this->end() ?>
