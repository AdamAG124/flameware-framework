<?php
/**
 * Layout principal. Recibe la vista en content() y las mismas variables que
 * ella. Las secciones 'styles' y 'scripts' las llenan las páginas que
 * necesitan CSS o JS propio con $this->start('scripts') ... $this->end().
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
    <?= $this->component('navbar') ?>

    <main class="container">
        <?= $this->content() ?>
    </main>

    <?= $this->section('scripts') ?>
</body>
</html>
