SELECT "Changing data column from MEDIUMTEXT to LONGTEXT in qnaire_report table" AS "";

alter table qnaire_report modify column data LONGTEXT NOT NULL;
