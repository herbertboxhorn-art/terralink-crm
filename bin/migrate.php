<?php
// Applies sql/*.sql files in order. Safe to re-run (statements use IF NOT EXISTS / INSERT IGNORE).
require __DIR__ . '/../src/bootstrap.php';
for ($i = 0; $i < 30; $i++) { try { db(); break; } catch (Throwable $e) { fwrite(STDERR, "waiting for database…\n"); sleep(2); } }
foreach (glob(APP_ROOT . '/sql/*.sql') as $f) {
    db()->exec(file_get_contents($f));
    echo 'applied ' . basename($f) . PHP_EOL;
}
