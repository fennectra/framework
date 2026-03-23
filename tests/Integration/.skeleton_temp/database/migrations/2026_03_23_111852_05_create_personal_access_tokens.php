<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS personal_access_tokens ( id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL, token TEXT NOT NULL UNIQUE, abilities TEXT DEFAULT NULL, last_used_at TEXT DEFAULT NULL, expires_at TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)',
    'down' => 'DROP TABLE IF EXISTS personal_access_tokens',
];
