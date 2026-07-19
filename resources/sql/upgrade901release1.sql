ALTER TABLE `tblaccounts` MODIFY COLUMN `type` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;

UPDATE `tblaccounts` SET `type`='invoice_billing_adjustment_credit' WHERE `type`='invoice_billing_adjustment_credi';
