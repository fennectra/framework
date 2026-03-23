<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS organization_members ( id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, role TEXT NOT NULL DEFAULT \'member\', invited_at TEXT DEFAULT NULL, joined_at TEXT DEFAULT NULL, UNIQUE(organization_id, user_id))',
    'down' => 'DROP TABLE IF EXISTS organization_members',
];
