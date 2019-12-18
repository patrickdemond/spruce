SELECT "Creating new page table" AS "";

CREATE TABLE IF NOT EXISTS page (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  max_time INT UNSIGNED NOT NULL,
  precondition TEXT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_module_id (module_id ASC),
  UNIQUE INDEX uq_module_id_rank (module_id ASC, rank ASC),
  UNIQUE INDEX uq_module_id_name (module_id ASC, name ASC),
  CONSTRAINT fk_page_module_id
    FOREIGN KEY (module_id)
    REFERENCES module (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


DELIMITER $$

DROP TRIGGER IF EXISTS page_AFTER_INSERT $$
CREATE DEFINER = CURRENT_USER TRIGGER page_AFTER_INSERT AFTER INSERT ON page FOR EACH ROW
BEGIN
  INSERT INTO page_description( page_id, language_id )
  SELECT NEW.id, language_id
  FROM qnaire_has_language
  JOIN module ON qnaire_has_language.qnaire_id = module.qnaire_id
  WHERE module.id = NEW.module_id;
END$$

DELIMITER ;
