DROP PROCEDURE IF EXISTS patch_page_description;
DELIMITER //
CREATE PROCEDURE patch_page_description()
  BEGIN

    -- determine the @cenozo database name
    SET @cenozo = ( SELECT REPLACE( DATABASE(), "pine", "cenozo" ) );

    SELECT "Creating new page_description table" AS "";

    SET @sql = CONCAT(
      "CREATE TABLE IF NOT EXISTS page_description ( ",
        "id INT UNSIGNED NOT NULL AUTO_INCREMENT, ",
        "update_timestamp TIMESTAMP NOT NULL, ",
        "create_timestamp TIMESTAMP NOT NULL, ",
        "page_id INT UNSIGNED NOT NULL, ",
        "language_id INT UNSIGNED NOT NULL, ",
        "type ENUM('prompt', 'popup') NOT NULL, ",
        "value TEXT NULL, ",
        "PRIMARY KEY (id), ",
        "INDEX fk_page_id (page_id ASC), ",
        "INDEX fk_language_id (language_id ASC), ",
        "UNIQUE INDEX uq_page_id_language_id_type (page_id ASC, language_id ASC, type ASC), ",
        "CONSTRAINT fk_page_description_page_id ",
          "FOREIGN KEY (page_id) ",
          "REFERENCES page (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION, ",
        "CONSTRAINT fk_page_description_language_id ",
          "FOREIGN KEY (language_id) ",
          "REFERENCES ", @cenozo, ".language (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION) ",
      "ENGINE = InnoDB"
    );
    PREPARE statement FROM @sql;
    EXECUTE statement;
    DEALLOCATE PREPARE statement;

  END //
DELIMITER ;

CALL patch_page_description();
DROP PROCEDURE IF EXISTS patch_page_description;
