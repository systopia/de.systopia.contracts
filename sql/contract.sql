-- /*******************************************************
-- *
-- * civicrm_sdd_entity_mandate
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_contract_payment` (
     `id`                    int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'ID',
     `contract_id`           int unsigned NOT NULL                 COMMENT 'FK to civicrm_membership',
     `contribution_recur_id` int unsigned NOT NULL                 COMMENT 'FK to civicrm_contribution_recur',
     `is_active`             tinyint NOT NULL  DEFAULT 1           COMMENT 'Is this link still active?',
     `creation_date`         datetime NOT NULL                     COMMENT 'by default now()',
     `start_date`            datetime                              COMMENT 'optional start_date of the link',
     `end_date`              datetime                              COMMENT 'optional start_date of the link',

     PRIMARY KEY (`id`),
     INDEX `contract_id` (contract_id),
     INDEX `contribution_recur_id` (contribution_recur_id),
     INDEX `is_active` (is_active),
     INDEX `start_date` (start_date),
     INDEX `end_date` (end_date),

     CONSTRAINT FK_civicrm_contract_payment_id FOREIGN KEY (`contract_id`) REFERENCES `civicrm_membership`(`id`) ON DELETE CASCADE

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;

