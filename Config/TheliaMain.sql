
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- page
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page`;

CREATE TABLE `page`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `code` VARCHAR(255),
    `type_id` INTEGER,
    `tag` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `tree_left` INTEGER,
    `tree_right` INTEGER,
    `tree_level` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `fi_page_type_page` (`type_id`),
    CONSTRAINT `fk_page_type_page`
        FOREIGN KEY (`type_id`)
        REFERENCES `page_type` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- page_type
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_type`;

CREATE TABLE `page_type`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `page_type_unique` (`type`)
) ENGINE=InnoDB;

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
-- page_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_i18n`;

CREATE TABLE `page_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `page_i18n_fk_bc427d`
        FOREIGN KEY (`id`)
        REFERENCES `page` (`id`)
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

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
