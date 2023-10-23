SET FOREIGN_KEY_CHECKS = 0;

alter table page
drop foreign key fk_page_block_group;

alter table page
drop column block_group_id;

alter table page_i18n
drop column slug;

SET FOREIGN_KEY_CHECKS = 1;
