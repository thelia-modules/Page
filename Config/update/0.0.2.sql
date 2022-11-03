SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- page_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_document`;

CREATE TABLE `page_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_page_document_page_id` (`page_id`),
    CONSTRAINT `fk_page_document_page_id`
        FOREIGN KEY (`page_id`)
            REFERENCES `page` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- page_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_document_i18n`;

CREATE TABLE `page_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `page_document_i18n_fk_05f093`
        FOREIGN KEY (`id`)
            REFERENCES `page_document` (`id`)
            ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
