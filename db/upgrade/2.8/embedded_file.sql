DROP PROCEDURE IF EXISTS patch_embedded_file;
DELIMITER //
CREATE PROCEDURE patch_embedded_file()
  BEGIN

    SELECT "Creating new embedded_file table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "embedded_file";

    IF @test = 0 THEN

      CREATE TABLE IF NOT EXISTS embedded_file (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        update_timestamp TIMESTAMP NOT NULL,
        create_timestamp TIMESTAMP NOT NULL,
        qnaire_id INT(10) UNSIGNED NOT NULL,
        name VARCHAR(45) NOT NULL,
        mime_type VARCHAR(45) NOT NULL,
        size INT UNSIGNED NOT NULL,
        data MEDIUMTEXT NOT NULL,
        PRIMARY KEY (id),
        INDEX fk_qnaire_id (qnaire_id ASC),
        UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
        CONSTRAINT fk_embedded_file_qnaire_id
          FOREIGN KEY (qnaire_id)
          REFERENCES qnaire (id)
          ON DELETE CASCADE
          ON UPDATE NO ACTION)
      ENGINE = InnoDB;

      SELECT "Transfering image records to the new embedded_file table" AS "";

      INSERT INTO embedded_file ( id, update_timestamp, create_timestamp, qnaire_id, name, mime_type, size, data )
      SELECT id, update_timestamp, create_timestamp, qnaire_id, name, mime_type, size, data
      FROM image;
    END IF;

  END //
DELIMITER ;

CALL patch_embedded_file();
DROP PROCEDURE IF EXISTS patch_embedded_file;
