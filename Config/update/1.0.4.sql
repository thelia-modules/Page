SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `page_image`;

CREATE TABLE `page_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_page_image_page_id` (`page_id`),
    CONSTRAINT `fk_page_image_page_id`
        FOREIGN KEY (`page_id`)
            REFERENCES `page` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS `page_image_i18n`;

CREATE TABLE `page_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `page_image_i18n_fk_91aec8`
        FOREIGN KEY (`id`)
            REFERENCES `page_image` (`id`)
            ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
