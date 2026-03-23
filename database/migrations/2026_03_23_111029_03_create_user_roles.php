<?php

return [
    'up' => 'CREATE TABLE IF NOT EXISTS user_roles ( user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (user_id, role_id), CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE)',
    'down' => 'DROP TABLE IF EXISTS user_roles',
];
