-- Update value for TaxEUTaxValidation setting
UPDATE tblconfiguration SET value = CASE WHEN value = 1 THEN 'server' ELSE 'disabled' END WHERE setting = 'TaxEUTaxValidation';

-- Add VAT Tax Rules Auto Configuration task
set @query = if ((select count(*) from `tbltask` where `name` = 'VAT Rules Automatic Update') = 0, "INSERT INTO `tbltask` VALUES (0,1700,'WHMCS\\\\Cron\\\\Task\\\\TaxVatAutoConfiguration',1,1,1440,'VAT Rules Automatic Update','Automatically update VAT rules.',now(),now());",'DO 0;');
PREPARE statement FROM @query;
EXECUTE statement;
DEALLOCATE PREPARE statement;

-- Add VAT Tax Rules Auto Configuration task tracker
set @task_id = (select id from `tbltask` where `name` = 'VAT Rules Automatic Update' LIMIT 1);
set @query = if ((select @task_id) and (select count(*) from `tbltask_status` where task_id = @task_id) = 0, "INSERT INTO `tbltask_status` VALUES (0, @task_id,0,'0000-00-00 00:00:00','0000-00-00 00:00:00',now(),now());",'DO 0;');
PREPARE statement FROM @query;
EXECUTE statement;
DEALLOCATE PREPARE statement;

-- Billing Notes
CREATE TABLE IF NOT EXISTS `tblbillingnotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `note_type` enum('credit','debit') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'credit',
  `custom_number` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `client_id` int unsigned NOT NULL,
  `date_issued` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `subtotal` decimal(16,2) DEFAULT '0.00',
  `tax` decimal(16,2) NOT NULL DEFAULT '0.00',
  `tax2` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total` decimal(16,2) NOT NULL DEFAULT '0.00',
  `taxrate` decimal(10,3) NOT NULL DEFAULT '0.000',
  `taxrate2` decimal(10,3) NOT NULL DEFAULT '0.000',
  `status` enum('draft','issued','closed') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblbillingnoteitems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `billingnote_id` int unsigned NOT NULL DEFAULT '0',
  `note_type` enum('credit','debit') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'credit',
  `client_id` int unsigned NOT NULL,
  `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'other',
  `relid` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `taxed` tinyint NOT NULL DEFAULT '0',
  `tax` decimal(16,2) DEFAULT NULL,
  `tax2` decimal(16,2) DEFAULT NULL,
  `taxrate` decimal(10,3) DEFAULT NULL,
  `taxrate2` decimal(10,3) DEFAULT NULL,
  `status` enum('draft','issued','closed') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `billingnote_id` (`billingnote_id`),
  KEY `status` (`status`),
  KEY `client_type_rel` (`client_id`,`type`,`relid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblaccounts' and column_name='billingnoteid') = 0, 'ALTER TABLE `tblaccounts` ADD `billingnoteid` int unsigned NOT NULL DEFAULT "0" AFTER `refundid`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblaccounts' and column_name='type') = 0, 'ALTER TABLE `tblaccounts` ADD `type` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `billingnoteid`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblaccounts' and column_name='relid') = 0, 'ALTER TABLE `tblaccounts` ADD `relid` int unsigned NOT NULL DEFAULT "0" AFTER `type`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((SELECT count(INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = database() AND TABLE_NAME = 'tblaccounts' AND INDEX_NAME = 'billingnoteid') = 0, 'CREATE INDEX `billingnoteid` ON `tblaccounts` (`billingnoteid`)', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((SELECT count(INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = database() AND TABLE_NAME = 'tblaccounts' AND INDEX_NAME = 'type_billingnoteid_relid') = 0, 'CREATE INDEX `type_billingnoteid_relid` ON `tblaccounts` (`type`, `billingnoteid`, `relid`)', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'BillingNoteIncrement') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('BillingNoteIncrement', 1, now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxCustomBillingNotes') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxCustomBillingNotes', 'on', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxCustomBillingNoteNumbering') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxCustomBillingNoteNumbering', 0, now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxCustomBillingNoteNumberFormat') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxCustomBillingNoteNumberFormat', '{NUMBER}', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxAutoResetNumberingBillingNote') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxAutoResetNumberingBillingNote', 'never', now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxNextCustomBillingNoteNumber') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxNextCustomBillingNoteNumber', 1, now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from `tblconfiguration` where `setting` = 'TaxLastCustomBillingNoteNumberResetDate') = 0, "INSERT INTO `tblconfiguration` (`setting`, `value`, `created_at`, `updated_at`) VALUES ('TaxLastCustomBillingNoteNumberResetDate', now(), now(), now());",'DO 0;');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new column (billing_note_id) to tblcredit
set @query = if ((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = database() AND TABLE_NAME = 'tblcredit' AND COLUMN_NAME  = 'billing_note_id') = 0, 'ALTER TABLE `tblcredit` ADD `billing_note_id` int unsigned NOT NULL DEFAULT "0" AFTER `relid`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

DELETE FROM tblconfiguration WHERE setting = 'DisplayAllowSmartyPhpSetting';
DELETE FROM tblconfiguration WHERE setting = 'AllowSmartyPhpTags';
DELETE FROM tblconfiguration WHERE setting = 'DomainExpirationFeeHandling';

SET @query = IF ((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name  = 'tblproductgroups' AND column_name = 'icon') = 0, "ALTER TABLE `tblproductgroups` ADD COLUMN `icon` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `order`;", 'DO 0;');
PREPARE statement FROM @query;
EXECUTE statement;
DEALLOCATE PREPARE statement;

-- Add new field to tblticketreplies for trace_id tracking
set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblticketreplies' and column_name='trace_id') = 0, 'ALTER table `tblticketreplies` ADD `trace_id` VARCHAR(36) NULL DEFAULT NULL AFTER `message`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new field to tbltickets for ai_token_charged_at tracking
set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tbltickets' and column_name='ai_token_charged_at') = 0, 'ALTER table `tbltickets` ADD `ai_token_charged_at` TIMESTAMP NULL DEFAULT NULL AFTER `lastreply`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

-- Add new agent_score column to tblticketreplies
SET @query = IF ((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = database() AND TABLE_NAME = 'tblticketreplies' AND COLUMN_NAME = 'agent_score') = 0, 'ALTER TABLE `tblticketreplies` ADD COLUMN `agent_score` TINYINT(1) DEFAULT NULL AFTER `editor`', 'DO 0;');
PREPARE statement FROM @query;
EXECUTE statement;
DEALLOCATE PREPARE statement;
