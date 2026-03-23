<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS organization_members ( id BIGSERIAL PRIMARY KEY, organization_id BIGINT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE, user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE, role VARCHAR(20) NOT NULL DEFAULT \'member\', invited_at TIMESTAMP DEFAULT NULL, joined_at TIMESTAMP DEFAULT NULL, UNIQUE(organization_id, user_id))',
    'down' => 'DROP TABLE IF EXISTS organization_members',
];
