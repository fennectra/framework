<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS personal_access_tokens ( id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, abilities TEXT DEFAULT NULL, last_used_at TIMESTAMP DEFAULT NULL, expires_at TIMESTAMP DEFAULT NULL, created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW(), CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)',
    'down' => 'DROP TABLE IF EXISTS personal_access_tokens',
];
