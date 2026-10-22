<?php
/**
 * Tarjeta con título y texto. Ejemplo de componente con props:
 *   <?= $this->component('card', ['title' => 'Rápido', 'body' => '...']) ?>
 *
 * @var \App\Support\Template $this
 * @var string $title
 * @var string $body
 */
?>
<article class="card">
    <h2 class="card__title"><?= $this->e($title) ?></h2>
    <p><?= $this->e($body) ?></p>
</article>
