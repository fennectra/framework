<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS webhooks ( id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, secret VARCHAR(255) NOT NULL, events JSONB NOT NULL DEFAULT \'[]\'::jsonb, is_active BOOLEAN NOT NULL DEFAULT TRUE, description TEXT, created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW()); CREATE INDEX IF NOT EXISTS idx_webhooks_is_active ON webhooks (is_active); CREATE TABLE IF NOT EXISTS webhook_deliveries ( id BIGSERIAL PRIMARY KEY, webhook_id BIGINT NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE, event VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, payload JSONB, status VARCHAR(50) NOT NULL DEFAULT \'pending\', http_status INTEGER DEFAULT 0, response_body TEXT, attempt INTEGER NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT NOW()); CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_webhook_id ON webhook_deliveries (webhook_id); CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_status ON webhook_deliveries (status)',
    'down' => 'DROP TABLE IF EXISTS webhook_deliveries; DROP TABLE IF EXISTS webhooks',
];
