DROP PROCEDURE IF EXISTS patch_qnaire_consent_type_trigger;
DELIMITER //
CREATE PROCEDURE patch_qnaire_consent_type_trigger()
  BEGIN

    -- determine the cenozo database name
    SET @cenozo = (
      SELECT unique_constraint_schema
      FROM information_schema.referential_constraints
      WHERE constraint_schema = DATABASE()
      AND constraint_name = "fk_access_site_id"
    );

    SELECT "Modifying unique key in qnaire_consent_type_trigger table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_consent_type_trigger"
    AND constraint_name = "uq_qnaire_id_consent_type_id_question_id_accept";

    IF @test > 0 THEN
      ALTER TABLE qnaire_consent_type_trigger DROP INDEX uq_qnaire_id_consent_type_id_question_id_accept;
      ALTER TABLE qnaire_consent_type_trigger
      ADD UNIQUE INDEX uq_qnaire_id_consent_type_id_question_id_answer_value (
        qnaire_id ASC, consent_type_id ASC, question_id ASC, answer_value ASC
      );
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_consent_type_trigger();
DROP PROCEDURE IF EXISTS patch_qnaire_consent_type_trigger;
