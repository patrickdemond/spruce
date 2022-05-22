DROP PROCEDURE IF EXISTS patch_qnaire;
DELIMITER //
CREATE PROCEDURE patch_qnaire()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Adding allow_in_hold column to qnaire table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire"
    AND column_name = "allow_in_hold";

    IF @test = 0 THEN
      ALTER TABLE qnaire ADD COLUMN allow_in_hold TINYINT(1) NOT NULL DEFAULT 0 AFTER readonly;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire();
DROP PROCEDURE IF EXISTS patch_qnaire;
