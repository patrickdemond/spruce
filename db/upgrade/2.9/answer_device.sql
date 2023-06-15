DROP PROCEDURE IF EXISTS patch_answer_device;
DELIMITER //
CREATE PROCEDURE patch_answer_device()
  BEGIN

    SELECT "Making participant_id in answer_device table nullable" AS "";

    SELECT COUNT(*) INTO @test
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
    AND table_name = "answer_device";

    IF @test = 0 THEN
      CREATE TABLE IF NOT EXISTS answer_device (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        update_timestamp TIMESTAMP NOT NULL,
        create_timestamp TIMESTAMP NOT NULL,
        answer_id INT(10) UNSIGNED NOT NULL,
        uuid VARCHAR(45) NULL DEFAULT NULL,
        status ENUM('cancelled', 'in progress', 'completed') NULL DEFAULT NULL,
        start_datetime DATETIME NULL DEFAULT NULL,
        end_datetime DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE INDEX uq_uuid (uuid ASC),
        INDEX fk_answer_id (answer_id ASC),
        UNIQUE INDEX uq_answer_id (answer_id ASC),
        CONSTRAINT fk_answer_device_answer_id
          FOREIGN KEY (answer_id)
          REFERENCES answer (id)
          ON DELETE CASCADE
          ON UPDATE NO ACTION)
      ENGINE = InnoDB;

      -- move old response_device records into the new table
      INSERT INTO answer_device(
        update_timestamp, create_timestamp, answer_id, uuid, status, start_datetime, end_datetime
      )
      SELECT response_device.update_timestamp,
             response_device.create_timestamp,
             answer.id,
             response_device.uuid,
             response_device.status,
             response_device.start_datetime,
             response_device.end_datetime
      FROM response_device
      JOIN question USING( device_id )
      JOIN answer ON question.id = answer.question_id AND response_device.response_id = answer.response_id;

      -- make sure all answers to device questions have an answer_device record
      INSERT IGNORE INTO answer_device( answer_id )
      SELECT answer.id
      FROM answer
      JOIN question ON answer.question_id = question.id
      WHERE question.device_id IS NOT NULL;

    END IF;

  END //
DELIMITER ;

CALL patch_answer_device();
DROP PROCEDURE IF EXISTS patch_answer_device;
