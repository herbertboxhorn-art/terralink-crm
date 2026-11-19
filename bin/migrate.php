<?php
// Brings the database up to date (base schema + versioned migrations). Safe to re-run.
require __DIR__ . '/../src/bootstrap.php';
for ($i = 0; $i < 30; $i++) { try { db(); break; } catch (Throwable $e) { fwrite(STDERR, "waiting for database…\n"); sleep(2); } }
ensure_schema();
echo 'schema at ' . SCHEMA_VERSION . PHP_EOL;
