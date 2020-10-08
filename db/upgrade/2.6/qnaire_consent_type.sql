DROP PROCEDURE IF EXISTS patch_qnaire_consent_type;
DELIMITER //
CREATE PROCEDURE patch_qnaire_consent_type()
  BEGIN

    SELECT "Adding new unique key to qnaire_consent_type table" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_consent_type"
    AND constraint_name = "uq_qnaire_id_consent_type_id_question_id_accept";

    IF @test = 0 THEN
      ALTER TABLE qnaire_consent_type
      ADD UNIQUE INDEX uq_qnaire_id_consent_type_id_question_id_accept (
        qnaire_id ASC, consent_type_id ASC, question_id ASC, accept ASC
      );
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_consent_type();
DROP PROCEDURE IF EXISTS patch_qnaire_consent_type;
