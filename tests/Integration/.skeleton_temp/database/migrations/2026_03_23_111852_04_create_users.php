<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS users ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, activation_token TEXT DEFAULT NULL, activated_at TEXT DEFAULT NULL, reset_token TEXT DEFAULT NULL, reset_token_expires_at TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, deleted_at TEXT DEFAULT NULL)',
    'down' => 'DROP TABLE IF EXISTS users',
];
