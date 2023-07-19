DROP PROCEDURE IF EXISTS patch_qnaire_participant_trigger;
DELIMITER //
CREATE PROCEDURE patch_qnaire_participant_trigger()
  BEGIN

    SELECT "Adding new column names to qnaire_participant_trigger table" AS "";

    SELECT LOCATE( "current_sex", column_type ), LOCATE( "sex", column_type )
    INTO @current_sex, @sex
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
    AND table_name = "qnaire_participant_trigger"
    AND column_name = "column_name";

    IF @current_sex = 0 OR @sex = 0 THEN
      ALTER TABLE qnaire_participant_trigger
      MODIFY COLUMN column_name ENUM(
        'current_sex', 'delink', 'low_education', 'mass_email', 'out_of_area',
        'override_stratum', 'sex', 'withdraw_third_party'
      ) NOT NULL;
    END IF;

  END //
DELIMITER ;

CALL patch_qnaire_participant_trigger();
DROP PROCEDURE IF EXISTS patch_qnaire_participant_trigger;
