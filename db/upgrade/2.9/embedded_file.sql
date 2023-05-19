SELECT "Changing data column from MEDIUMTEXT to LONGTEXT in embedded_file table" AS "";

alter table embedded_file modify column data LONGTEXT NOT NULL;
