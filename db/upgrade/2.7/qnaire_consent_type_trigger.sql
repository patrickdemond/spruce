DROP PROCEDURE IF EXISTS patch_qnarie_consent_type_trigger;
DELIMITER //
CREATE PROCEDURE patch_qnarie_consent_type_trigger()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Renaming qnaire_consent_type table to qnaire_consent_type_trigger" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_consent_type";

    IF @test = 1 THEN
      RENAME TABLE qnaire_consent_type TO qnaire_consent_type_trigger;

      ALTER TABLE qnaire_consent_type_trigger
        DROP CONSTRAINT fk_qnaire_consent_type_consent_type_id,
        DROP CONSTRAINT fk_qnaire_consent_type_qnaire_id,
        DROP CONSTRAINT fk_qnaire_consent_type_question_id;

      SET @sql = CONCAT(
        "ALTER TABLE qnaire_consent_type_trigger ",
          "ADD CONSTRAINT fk_qnaire_consent_type_trigger_consent_type_id ",
          "FOREIGN KEY (consent_type_id) ",
          "REFERENCES ", @cenozo, ".consent_type (id) ",
          "ON DELETE CASCADE ",
          "ON UPDATE NO ACTION"
      );
      PREPARE statement FROM @sql;
      EXECUTE statement;
      DEALLOCATE PREPARE statement;

      ALTER TABLE qnaire_consent_type_trigger
        ADD CONSTRAINT fk_qnaire_consent_type_trigger_qnaire_id
        FOREIGN KEY (qnaire_id)
        REFERENCES qnaire (id)
        ON DELETE CASCADE
        ON UPDATE NO ACTION;

      ALTER TABLE qnaire_consent_type_trigger
        ADD CONSTRAINT fk_qnaire_consent_type_trigger_question_id
        FOREIGN KEY (question_id)
        REFERENCES question (id)
        ON DELETE CASCADE
        ON UPDATE NO ACTION;

    END IF;

  END //
DELIMITER ;

CALL patch_qnarie_consent_type_trigger();
DROP PROCEDURE IF EXISTS patch_qnarie_consent_type_trigger;
