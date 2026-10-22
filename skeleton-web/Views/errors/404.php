<?php
/**
 * La muestra el router cuando ninguna ruta coincide fuera del API.
 *
 * @var \App\Support\Template $this
 */
?>
<section class="hero">
    <h1>Página no encontrada</h1>
    <p>La dirección que buscas no existe o cambió de lugar.</p>
    <p><a href="<?= $this->url() ?>">Volver al inicio</a></p>
</section>
