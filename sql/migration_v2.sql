-- SkillApp v2: Multi-Role Migration
-- Run: mysql -u root skillapp < sql/migration_v2.sql

-- 1. Roles table
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(50) NOT NULL UNIQUE,
  description TEXT,
  github_path VARCHAR(255) NOT NULL DEFAULT '.copilot/skills',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed default roles
INSERT IGNORE INTO roles (name, slug, description, github_path) VALUES
('Business Analyst', 'ba', 'Business analysis skills — requirements, discovery, documentation', 'ba/.copilot/skills');
-- Add more roles as needed:
-- ('Developer', 'developer', 'Software development skills', 'developer/.copilot/skills'),
-- ('Security Analyst', 'security', 'Security assessment skills', 'security/.copilot/skills');

-- 3. Add columns to users table
ALTER TABLE users ADD COLUMN role_id INT NULL AFTER email,
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER role_id;
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- 4. Exempt existing users (first user becomes admin)
-- If no admin exists, make user id=1 the admin
UPDATE users SET is_admin = 1 WHERE id = 1;

-- 5. Role change audit log
CREATE TABLE IF NOT EXISTS role_change_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  changed_by INT NOT NULL,
  old_role_id INT NULL,
  new_role_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Application-level enforcement only (no CHECK for MySQL compatibility)
-- Roles are validated in PHP before assignment
