SELECT "Creating new question table" AS "";

CREATE TABLE IF NOT EXISTS question (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  type ENUM('boolean', 'list', 'number', 'string', 'text', 'comment') NOT NULL,
  multiple TINYINT(1) NULL DEFAULT NULL,
  minimum FLOAT NULL DEFAULT NULL,
  maximum FLOAT NULL DEFAULT NULL,
  description TEXT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_page_id (page_id ASC),
  UNIQUE INDEX uq_page_id_rank (page_id ASC, rank ASC),
  UNIQUE INDEX uq_page_id_name (page_id ASC, name ASC),
  CONSTRAINT fk_question_page_id
    FOREIGN KEY (page_id)
    REFERENCES page (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
