<?php
/**
 * Termina apenas a sessão independente da gestão.
 */

require __DIR__ . '/inicio.php';
exigir_post_csrf();
logout();
redirecionar_gestao('index.php');
