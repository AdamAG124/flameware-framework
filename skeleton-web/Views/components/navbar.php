<?php
/**
 * Barra de navegación. Un componente solo ve las props que recibe; este no
 * necesita ninguna.
 *
 * @var \App\Support\Template $this
 */
$brand = defined('APP_NAME') && APP_NAME !== '' ? APP_NAME : 'Flameware';
?>
<header class="navbar">
    <a class="navbar__brand" href="<?= $this->url() ?>"><?= $this->e($brand) ?></a>
</header>
