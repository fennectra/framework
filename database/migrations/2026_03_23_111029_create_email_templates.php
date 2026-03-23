<?php

return [
    'up' => 'CREATE TABLE email_templates ( id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, locale VARCHAR(5) NOT NULL DEFAULT \'fr\', subject VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(name, locale))',
    'down' => 'DROP TABLE IF EXISTS email_templates',
];
