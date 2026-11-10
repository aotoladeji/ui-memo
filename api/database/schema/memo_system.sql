CREATE TABLE faculties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL, code VARCHAR(20) NOT NULL UNIQUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;
CREATE TABLE departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  faculty_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, code VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_dept (faculty_id, code),
  CONSTRAINT fk_dept_fac FOREIGN KEY (faculty_id) REFERENCES faculties(id)
) ENGINE=InnoDB;
CREATE TABLE units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, code VARCHAR(20) NOT NULL,
  head_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_unit (department_id, code),
  CONSTRAINT fk_unit_dept FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no VARCHAR(30) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','unit_head','staff') NOT NULL DEFAULT 'staff',
  unit_id BIGINT UNSIGNED NULL, title VARCHAR(120) NULL,
  is_executive TINYINT(1) NOT NULL DEFAULT 0,
  can_send_global_broadcast TINYINT(1) NOT NULL DEFAULT 0,
  signature_path VARCHAR(255) NULL, stamp_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  KEY ix_users_unit (unit_id), KEY ix_users_role (role),
  CONSTRAINT fk_user_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB;
ALTER TABLE units ADD CONSTRAINT fk_unit_head FOREIGN KEY (head_user_id) REFERENCES users(id);
CREATE TABLE admin_scopes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, scope_type ENUM('faculty','department') NOT NULL,
  faculty_id BIGINT UNSIGNED NULL, department_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_as_fac FOREIGN KEY (faculty_id) REFERENCES faculties(id),
  CONSTRAINT fk_as_dep FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT ck_scope CHECK ((scope_type='faculty' AND faculty_id IS NOT NULL) OR (scope_type='department' AND department_id IS NOT NULL))
) ENGINE=InnoDB;
CREATE TABLE memos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE, ref_no VARCHAR(40) NOT NULL UNIQUE, subject VARCHAR(255) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL, origin_unit_id BIGINT UNSIGNED NULL,
  routing_type ENUM('internal_unit','peer','global') NOT NULL,
  priority ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('draft','sent','closed') NOT NULL DEFAULT 'draft',
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  KEY ix_memo_creator (created_by), KEY ix_memo_status (status, sent_at),
  CONSTRAINT fk_memo_user FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_memo_unit FOREIGN KEY (origin_unit_id) REFERENCES units(id)
) ENGINE=InnoDB;
CREATE TABLE memo_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NOT NULL, storage_path VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL,
  page_count SMALLINT UNSIGNED NOT NULL DEFAULT 1, page_dims JSON NULL, sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_doc_memo FOREIGN KEY (memo_id) REFERENCES memos(id)
) ENGINE=InnoDB;
CREATE TABLE memo_routes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NOT NULL, parent_route_id BIGINT UNSIGNED NULL,
  depth SMALLINT UNSIGNED NOT NULL DEFAULT 0, path VARCHAR(255) NOT NULL DEFAULT '',
  kind ENUM('origin','forward') NOT NULL, from_user_id BIGINT UNSIGNED NOT NULL,
  annotation_mode ENUM('all','none') NOT NULL DEFAULT 'all',
  note_mode ENUM('all','none') NOT NULL DEFAULT 'all',
  hidden_classifications JSON NULL, message TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY ix_route_memo (memo_id), KEY ix_route_path (memo_id, path),
  CONSTRAINT fk_r_memo FOREIGN KEY (memo_id) REFERENCES memos(id),
  CONSTRAINT fk_r_parent FOREIGN KEY (parent_route_id) REFERENCES memo_routes(id),
  CONSTRAINT fk_r_from FOREIGN KEY (from_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE memo_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NOT NULL, route_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
  recipient_type ENUM('author','to','cc') NOT NULL DEFAULT 'to',
  delivered_at TIMESTAMP NULL, read_at TIMESTAMP NULL,
  action_state ENUM('none','pending','done') NOT NULL DEFAULT 'none',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_route_user (route_id, user_id), KEY ix_inbox (user_id, memo_id),
  CONSTRAINT fk_mr_memo FOREIGN KEY (memo_id) REFERENCES memos(id),
  CONSTRAINT fk_mr_route FOREIGN KEY (route_id) REFERENCES memo_routes(id),
  CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE annotations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NOT NULL, origin_route_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL,
  page_number SMALLINT UNSIGNED NOT NULL,
  tool ENUM('freehand','highlight','text','stamp','signature','shape') NOT NULL,
  geometry JSON NOT NULL, style JSON NULL,
  classification ENUM('standard','executive','confidential') NOT NULL DEFAULT 'standard',
  visibility_scope ENUM('chain','private') NOT NULL DEFAULT 'chain',
  z_index INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  KEY ix_ann_page (memo_id, page_number), KEY ix_ann_origin (origin_route_id),
  CONSTRAINT fk_an_memo FOREIGN KEY (memo_id) REFERENCES memos(id),
  CONSTRAINT fk_an_route FOREIGN KEY (origin_route_id) REFERENCES memo_routes(id),
  CONSTRAINT fk_an_user FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE sticky_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NOT NULL, origin_route_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL,
  page_number SMALLINT UNSIGNED NOT NULL, x DECIMAL(8,6) NOT NULL, y DECIMAL(8,6) NOT NULL,
  body TEXT NOT NULL, color VARCHAR(9) NOT NULL DEFAULT '#FDE68A',
  classification ENUM('standard','executive','confidential') NOT NULL DEFAULT 'standard',
  visibility_scope ENUM('chain','private') NOT NULL DEFAULT 'chain',
  requires_action TINYINT(1) NOT NULL DEFAULT 0, assigned_to BIGINT UNSIGNED NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  resolved_by BIGINT UNSIGNED NULL, resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  KEY ix_note_page (memo_id, page_number), KEY ix_note_action (memo_id, requires_action, status),
  CONSTRAINT fk_sn_memo FOREIGN KEY (memo_id) REFERENCES memos(id),
  CONSTRAINT fk_sn_route FOREIGN KEY (origin_route_id) REFERENCES memo_routes(id),
  CONSTRAINT fk_sn_author FOREIGN KEY (author_id) REFERENCES users(id),
  CONSTRAINT fk_sn_assignee FOREIGN KEY (assigned_to) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE sticky_note_replies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_id BIGINT UNSIGNED NOT NULL, origin_route_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  KEY ix_reply_note (note_id),
  CONSTRAINT fk_rp_note FOREIGN KEY (note_id) REFERENCES sticky_notes(id),
  CONSTRAINT fk_rp_route FOREIGN KEY (origin_route_id) REFERENCES memo_routes(id),
  CONSTRAINT fk_rp_user FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE memo_forward_visibility_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id BIGINT UNSIGNED NOT NULL, target_type ENUM('annotation','sticky_note') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL, effect ENUM('include','exclude') NOT NULL,
  UNIQUE KEY uq_rule (route_id, target_type, target_id),
  CONSTRAINT fk_vr_route FOREIGN KEY (route_id) REFERENCES memo_routes(id)
) ENGINE=InnoDB;
CREATE TABLE permission_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NOT NULL, target_user_id BIGINT UNSIGNED NOT NULL,
  permission VARCHAR(60) NOT NULL, old_value TINYINT(1) NOT NULL, new_value TINYINT(1) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memo_id BIGINT UNSIGNED NULL, actor_id BIGINT UNSIGNED NULL, action VARCHAR(60) NOT NULL,
  meta JSON NULL, ip VARCHAR(45) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY ix_audit_memo (memo_id, created_at)
) ENGINE=InnoDB;
CREATE TABLE personal_access_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tokenable_type VARCHAR(255) NOT NULL, tokenable_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, abilities TEXT NULL,
  last_used_at TIMESTAMP NULL, expires_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY tokenable (tokenable_type, tokenable_id)
) ENGINE=InnoDB;
