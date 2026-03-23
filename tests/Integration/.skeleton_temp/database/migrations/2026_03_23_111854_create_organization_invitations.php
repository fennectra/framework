<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS organization_invitations ( id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE, email TEXT NOT NULL, role TEXT NOT NULL DEFAULT \'member\', token TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'down' => 'DROP TABLE IF EXISTS organization_invitations',
];
