<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (is_logged_in()) {
    header('Location: leads.php');
} else {
    header('Location: login.php');
}
exit;
