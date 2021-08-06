SELECT "Creating new stage table" AS "";

CREATE TABLE IF NOT EXISTS stage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT(10) UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  precondition TEXT NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_rank (qnaire_id ASC, rank ASC),
  UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
  CONSTRAINT fk_stage_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
