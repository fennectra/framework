<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS organization_invitations ( id BIGSERIAL PRIMARY KEY, organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE, email VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT \'member\', token VARCHAR(64) NOT NULL UNIQUE, expires_at TIMESTAMP NOT NULL, created_at TIMESTAMP DEFAULT NOW())',
    'down' => 'DROP TABLE IF EXISTS organization_invitations',
];
