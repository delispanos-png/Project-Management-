CREATE TABLE IF NOT EXISTS `tblautoupdate` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `current_version` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `next_version` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `last_check` timestamp NULL DEFAULT NULL,
  `status` enum('new', 'scheduled', 'in_progress', 'completed', 'failed') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'new',
  `failure_count` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Add new configuration value for AutoUpdatePatchEnabled
set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'AutoUpdatePatchEnabled') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('AutoUpdatePatchEnabled', '', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new configuration value for AutoUpdatePatchSchedule
set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'AutoUpdatePatchSchedule') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('AutoUpdatePatchSchedule', '{\"frequency\":\"\",\"day\":\"\",\"time\":\"\",\"timezone\":\"\"}', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new permissions for Unattended Automatic Updates
set @query = if ((select count(*) from `tbladminperms` where `roleid` = '1' and `permid` = '169') = 0, "INSERT INTO `tbladminperms` (`roleid` ,`permid` ) VALUES ('1', '169');",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add index (affaccid) to tblaffiliatespending to speed up affiliate pending commission lookups
set @query = if ((SELECT count(INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = database() AND TABLE_NAME = 'tblaffiliatespending' AND INDEX_NAME = 'affaccid') = 0, 'CREATE INDEX `affaccid` ON `tblaffiliatespending` (`affaccid`)', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Drop deprecated password_reset_data column from tbladmins WHMCS-26383
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tbladmins' and COLUMN_NAME='password_reset_data' and TABLE_SCHEMA=database()) > 0, 'ALTER TABLE `tbladmins` DROP COLUMN `password_reset_data`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add lastvisit column to tbladmins so staff presence no longer aggregates tbladminlog WHMCS-27003
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tbladmins' and COLUMN_NAME='lastvisit' and TABLE_SCHEMA=database()) = 0, 'ALTER TABLE `tbladmins` ADD COLUMN `lastvisit` datetime DEFAULT NULL AFTER `mixpanel_tracking_enabled`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add `is_cancelled` column to tblinvoiceitems WHMCS-25474
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tblinvoiceitems' and COLUMN_NAME='is_cancelled' and TABLE_SCHEMA=database()) = 0, "ALTER TABLE `tblinvoiceitems` ADD COLUMN `is_cancelled` tinyint(1) NOT NULL DEFAULT '0' AFTER `notes`", 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Password history storage for reuse prevention (PCI DSS 8.3.7) WHMCS-27446
CREATE TABLE IF NOT EXISTS `tblpassword_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('user','admin') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
  `algorithm` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'bcrypt',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Add new configuration value for PasswordMinimumLength WHMCS-27450
set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'PasswordMinimumLength') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('PasswordMinimumLength', '12', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Record the order and invoice a cart checked out to, so a repeat checkout replays that
-- result instead of placing a second order WHMCS-27578
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tblcarts' and COLUMN_NAME='checkout_order_id' and TABLE_SCHEMA=database()) = 0, 'ALTER TABLE `tblcarts` ADD COLUMN `checkout_order_id` int DEFAULT NULL AFTER `data`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new configuration value for PasswordHistoryDepth WHMCS-27450
set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'PasswordHistoryDepth') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('PasswordHistoryDepth', '4', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tblcarts' and COLUMN_NAME='checkout_invoice_id' and TABLE_SCHEMA=database()) = 0, 'ALTER TABLE `tblcarts` ADD COLUMN `checkout_invoice_id` int unsigned DEFAULT NULL AFTER `checkout_order_id`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- checkout_item_ids lets a repeat checkout replay the item lists the first one returned
-- rather than re-deriving them WHMCS-27882
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tblcarts' and COLUMN_NAME='checkout_item_ids' and TABLE_SCHEMA=database()) = 0, 'ALTER TABLE `tblcarts` ADD COLUMN `checkout_item_ids` text COLLATE utf8_unicode_ci NULL AFTER `checkout_invoice_id`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Store admin "remember me" tokens one row per device instead of one column per admin,
-- so a Remember Me login on a new device no longer invalidates the cookie already issued
-- to another device WHMCS-28009
CREATE TABLE IF NOT EXISTS `tbladminremembertokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `context_hash` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- The dropped column combined the token and its request-context binding into one hash,
-- which cannot be split into the new token_hash/context_hash pair without the original
-- raw token, never stored. Any admin with a live "remember me" cookie at upgrade time
-- signs in again once, the same as a normal cookie expiry; this is not carried forward.
-- Drop the single-token column now that tokens live one row per device WHMCS-28009
set @query = if ((select count(*) from information_schema.columns where TABLE_NAME='tbladmins' and COLUMN_NAME='remember_token' and TABLE_SCHEMA=database()) > 0, 'ALTER TABLE `tbladmins` DROP COLUMN `remember_token`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;
