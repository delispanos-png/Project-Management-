-- WHMCS-26207 Add per-department mail-import cap columns to tblticketdepartments (sizes in whole MB)
set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblticketdepartments' and column_name='mail_import_message_cap') = 0, 'ALTER TABLE `tblticketdepartments` ADD `mail_import_message_cap` smallint(5) unsigned NOT NULL DEFAULT "500" AFTER `prevent_client_closure`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblticketdepartments' and column_name='mail_import_total_attachment_mb') = 0, 'ALTER TABLE `tblticketdepartments` ADD `mail_import_total_attachment_mb` smallint(5) unsigned NOT NULL DEFAULT "500" AFTER `mail_import_message_cap`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;

set @query = if ((select count(*) from information_schema.columns where table_schema=database() and table_name='tblticketdepartments' and column_name='mail_import_max_attachment_mb') = 0, 'ALTER TABLE `tblticketdepartments` ADD `mail_import_max_attachment_mb` smallint(5) unsigned NOT NULL DEFAULT "25" AFTER `mail_import_total_attachment_mb`', 'DO 0');
prepare statement from @query;
execute statement;
deallocate prepare statement;
