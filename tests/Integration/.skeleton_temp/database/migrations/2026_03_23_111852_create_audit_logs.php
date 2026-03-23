<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS audit_logs ( id INTEGER PRIMARY KEY AUTOINCREMENT, auditable_type TEXT NOT NULL, auditable_id INTEGER NOT NULL, action TEXT NOT NULL, old_values TEXT DEFAULT \'{}\', new_values TEXT DEFAULT \'{}\', user_id INTEGER, ip_address TEXT, request_id TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP); CREATE INDEX IF NOT EXISTS idx_audit_logs_auditable ON audit_logs (auditable_type, auditable_id); CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs (user_id); CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at)',
    'down' => 'DROP TABLE IF EXISTS audit_logs',
];
