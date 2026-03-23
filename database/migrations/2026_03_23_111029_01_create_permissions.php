<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS permissions ( id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, guard_name VARCHAR(50) NOT NULL DEFAULT \'web\', description VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW())',
    'down' => 'DROP TABLE IF EXISTS permissions',
];
