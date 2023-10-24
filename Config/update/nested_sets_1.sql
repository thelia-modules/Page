SET FOREIGN_KEY_CHECKS = 0;

alter table page
    add tree_left int null;

alter table page
    add tree_right int null;

alter table page
    add tree_level int null;

SET FOREIGN_KEY_CHECKS = 1;
