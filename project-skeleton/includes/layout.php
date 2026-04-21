<?php

declare(strict_types=1);

function render_page_start(string $title): void
{
    echo '<!doctype html>';
    echo '<html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/css/styles.css">';
    echo '</head><body>';
    echo '<header class="topbar">';
    echo '<h1>' . h(app_title()) . '</h1>';
    echo '<nav>';
    echo '<a href="index.php">Inicio</a>';
    echo '<a href="clientes.php">Clientes</a>';
    echo '<a href="insumos.php">Insumos</a>';
    echo '<a href="presupuesto_nuevo.php">Presupuestos</a>';
    echo '<a href="agenda.php">Agenda</a>';
    echo '</nav>';
    echo '</header>';
    echo '<main class="container">';
    echo '<h2>' . h($title) . '</h2>';

    $message = $_GET['msg'] ?? null;
    if (is_string($message) && $message !== '') {
        echo '<p class="flash">' . h($message) . '</p>';
    }
}

function render_page_end(): void
{
    echo '</main></body></html>';
function render_header(string $title): void
    
{
    echo "<h1>{$title}</h1>";
}
}
