
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- paygreen_climate_order_footprint
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `paygreen_climate_order_footprint`;

CREATE TABLE `paygreen_climate_order_footprint`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `footprint_id` VARCHAR(64) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `paygreen_climate_order_footprint_fi_75704f` (`order_id`),
    CONSTRAINT `paygreen_climate_order_footprint_fk_75704f`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
