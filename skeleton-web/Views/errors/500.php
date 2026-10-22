<?php
/**
 * La muestra el router cuando una página lanza una excepción. El detalle
 * queda en el log, nunca en el HTML.
 *
 * @var \App\Support\Template $this
 */
?>
<section class="hero">
    <h1>Algo salió mal</h1>
    <p>Tuvimos un problema al cargar esta página. Inténtalo de nuevo en unos minutos.</p>
    <p><a href="<?= $this->url() ?>">Volver al inicio</a></p>
</section>
