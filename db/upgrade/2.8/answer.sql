DROP PROCEDURE IF EXISTS patch_answer;
DELIMITER //
CREATE PROCEDURE patch_answer()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding alternate_id column to answer table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "answer"
    AND column_name = "alternate_id";

    IF @test = 0 THEN
      -- adding a column to a very large table can be slow, so use the instant algorithm
      SET SESSION alter_algorithm='INSTANT';
      ALTER TABLE answer ADD COLUMN alternate_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER language_id;
      SET SESSION alter_algorithm='DEFAULT';

      ALTER TABLE answer ADD INDEX fk_alternate_id (alternate_id ASC);

      SET @sql = CONCAT(
        "ALTER TABLE answer ",
        "ADD CONSTRAINT fk_answer_alternate_id ",
          "FOREIGN KEY (alternate_id) ",
          "REFERENCES ", @cenozo, ".alternate (id) ",
          "ON DELETE NO ACTION ",
          "ON UPDATE NO ACTION"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;
    END IF;

  END //
DELIMITER ;

CALL patch_answer();
DROP PROCEDURE IF EXISTS patch_answer;
