SELECT "Fixing bug in question_option trigger" AS "";

DROP TRIGGER IF EXISTS question_option_BEFORE_UPDATE;

DELIMITER $$

CREATE DEFINER=CURRENT_USER TRIGGER question_option_BEFORE_UPDATE BEFORE UPDATE ON question_option FOR EACH ROW
BEGIN
  SELECT NEW.name RLIKE "^[a-z0-9_]+$" INTO @test;
  IF( @test = 0 ) THEN 
    SIGNAL SQLSTATE 'HY000'
    SET MESSAGE_TEXT = "Invalid name character string: must RLIKE ^[a-z0-9_]+$",
    MYSQL_ERRNO = 1300;
  END IF;

  IF( NOT( OLD.extra <=> NEW.extra ) ) THEN 
    IF( NEW.extra IS NULL ) THEN 
      SET NEW.multiple_answers = false;
    END IF;

    IF( "date" != NEW.extra AND "number" != NEW.extra ) THEN
      SET NEW.minimum = NULL;
      SET NEW.maximum = NULL;
    END IF;
  END IF;
END$$

DELIMITER ;
