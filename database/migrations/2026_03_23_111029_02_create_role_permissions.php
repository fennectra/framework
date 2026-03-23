<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS role_permissions ( role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL, PRIMARY KEY (role_id, permission_id), CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE)',
    'down' => 'DROP TABLE IF EXISTS role_permissions',
];
