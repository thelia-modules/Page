SET FOREIGN_KEY_CHECKS = 0;

alter table page
    add code varchar(255) null after id;

SET FOREIGN_KEY_CHECKS = 1;
