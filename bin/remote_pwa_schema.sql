-- Remote PWA: server-side sessions and per-user push tokens.
--
-- Applied once, by hand, against `homeautomation`. It is not run by the app.
-- Everything here is additive: no row is dropped and no existing column changes
-- type, so it is safe to apply to a live database.
--
-- `config.php` is deployed but `bin/` is not, which is why this lives here.

-- ---------------------------------------------------------------------- users
-- A Firebase identity is matched to a local user by email. The column is
-- nullable and uniquely indexed: MySQL permits several NULLs in a unique index,
-- so existing rows need no email until someone is actually given a login.
ALTER TABLE `ha_mi_remote_users`
	ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`,
	ADD UNIQUE KEY `uq_remote_users_email` (`email`);

-- ------------------------------------------------------------------- sessions
-- Authorization is this table, not the PHP session file. The cookie carries an
-- opaque random token; only its SHA-256 is stored, so a dump of this table cannot
-- be replayed. Revoking access is `revoked = 1`, which takes effect on the next
-- request.
CREATE TABLE IF NOT EXISTS `ha_sessions` (
	`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`session_hash`  CHAR(64) BINARY NOT NULL,
	`user_id`       INT NOT NULL,
	`username`      VARCHAR(255) NOT NULL,
	`firebase_uid`  VARCHAR(128) NOT NULL,
	`created_at`    DATETIME NOT NULL,
	`last_seen`     DATETIME NOT NULL,
	`expires_at`    DATETIME NOT NULL,
	`revoked`       TINYINT(1) NOT NULL DEFAULT 0,
	`ip`            VARCHAR(45) NULL,
	`user_agent`    VARCHAR(255) NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sessions_hash` (`session_hash`),
	KEY `ix_sessions_user` (`user_id`),
	KEY `ix_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------- push tokens
-- Push is addressed to a person, who may have several devices, so tokens are
-- keyed by user as well as by the browser's own machine id.
ALTER TABLE `fcm_tokens`
	ADD COLUMN `user_id` INT NULL AFTER `id`,
	ADD KEY `ix_fcm_user` (`user_id`);
