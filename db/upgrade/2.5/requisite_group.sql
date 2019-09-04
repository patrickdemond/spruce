DROP PROCEDURE IF EXISTS patch_requisite_group;
DELIMITER //
CREATE PROCEDURE patch_requisite_group()
  BEGIN

    SELECT "Creating new requisite_group table" AS "";

    CREATE TABLE IF NOT EXISTS requisite_group (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      update_timestamp TIMESTAMP NOT NULL,
      create_timestamp TIMESTAMP NOT NULL,
      module_id INT UNSIGNED NULL DEFAULT NULL,
      page_id INT UNSIGNED NULL DEFAULT NULL,
      question_id INT UNSIGNED NULL DEFAULT NULL,
      requisite_group_id INT UNSIGNED NULL DEFAULT NULL,
      rank INT UNSIGNED NOT NULL,
      logic ENUM('AND', 'OR') NOT NULL DEFAULT 'AND',
      negative TINYINT(1) NOT NULL DEFAULT 0,
      note TEXT NULL,
      PRIMARY KEY (id),
      INDEX fk_module_id (module_id ASC),
      INDEX fk_page_id (page_id ASC),
      INDEX fk_question_id (question_id ASC),
      INDEX fk_requisite_group_id (requisite_group_id ASC),
      UNIQUE INDEX uq_module_id_rank (module_id ASC, rank ASC),
      UNIQUE INDEX uq_page_id_rank (page_id ASC, rank ASC),
      UNIQUE INDEX uq_question_id_rank (question_id ASC, rank ASC),
      UNIQUE INDEX uq_requisite_group_id_rank (requisite_group_id ASC, rank ASC),
      CONSTRAINT fk_requisite_group_module_id
        FOREIGN KEY (module_id)
        REFERENCES module (id)
        ON DELETE CASCADE
        ON UPDATE NO ACTION,
      CONSTRAINT fk_requisite_group_page_id
        FOREIGN KEY (page_id)
        REFERENCES page (id)
        ON DELETE CASCADE
        ON UPDATE NO ACTION,
      CONSTRAINT fk_requisite_group_question_id
        FOREIGN KEY (question_id)
        REFERENCES question (id)
        ON DELETE CASCADE
        ON UPDATE NO ACTION)
    ENGINE = InnoDB;

    SELECT COUNT(*) INTO @test
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = "fk_requisite_group_requisite_group_id";

    IF @test = 0 THEN
      SET @sql = CONCAT(
        "ALTER TABLE requisite_group ",
        "ADD CONSTRAINT fk_requisite_group_requisite_group_id ",
          "FOREIGN KEY (requisite_group_id) ",
          "REFERENCES requisite_group (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_requisite_group();
