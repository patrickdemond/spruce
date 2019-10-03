DROP PROCEDURE IF EXISTS patch_setting;
DELIMITER //
CREATE PROCEDURE patch_setting()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Creating new setting table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS setting ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "site_id INT UNSIGNED NOT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_site_id (site_id ASC), ",
        "UNIQUE INDEX uq_site_id (site_id ASC), ",
        "CONSTRAINT fk_setting_site_id ",
          "FOREIGN KEY (site_id) ",
          "REFERENCES ", @cenozo, ".site (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_setting();
DROP PROCEDURE IF EXISTS patch_setting;
