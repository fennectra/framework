<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS permissions ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, guard_name TEXT NOT NULL DEFAULT \'web\', description TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'down' => 'DROP TABLE IF EXISTS permissions',
];
