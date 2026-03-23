<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS audit_logs ( id BIGSERIAL PRIMARY KEY, auditable_type VARCHAR(255) NOT NULL, auditable_id BIGINT NOT NULL, action VARCHAR(20) NOT NULL, old_values JSONB DEFAULT \'{}\'::jsonb, new_values JSONB DEFAULT \'{}\'::jsonb, user_id BIGINT, ip_address VARCHAR(45), request_id VARCHAR(32), created_at TIMESTAMP DEFAULT NOW()); CREATE INDEX IF NOT EXISTS idx_audit_logs_auditable ON audit_logs (auditable_type, auditable_id); CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs (user_id); CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at)',
    'down' => 'DROP TABLE IF EXISTS audit_logs',
];
