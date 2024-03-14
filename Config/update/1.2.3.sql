SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- page_tag
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_tag`;

CREATE TABLE `page_tag`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tag` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `page_tag_unique` (`tag`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- page_tag_combination
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `page_tag_combination`;

CREATE TABLE `page_tag_combination`
(
    `page_id` INTEGER NOT NULL,
    `page_tag_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`page_id`,`page_tag_id`),
    INDEX `fi_page_tag_combination_page_tag_id` (`page_tag_id`),
    CONSTRAINT `fk_page_tag_combination_page_id`
        FOREIGN KEY (`page_id`)
            REFERENCES `page` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `fk_page_tag_combination_page_tag_id`
        FOREIGN KEY (`page_tag_id`)
            REFERENCES `page_tag` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `page_tag` (`tag`, `created_at`, `updated_at`)
SELECT `tag`, `created_at`, `updated_at`
FROM `page`;

INSERT INTO `page_tag_combination` (`page_id`, `page_tag_id`, `created_at`, `updated_at`)
SELECT p.id, pt.id, p.`created_at`, p.`updated_at`
FROM `page` p
JOIN `page_tag` pt ON p.`tag` = pt.`tag`;

ALTER TABLE `page` DROP COLUMN `tag`;

SET FOREIGN_KEY_CHECKS = 1;