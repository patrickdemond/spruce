SELECT "Creating new deviation_type table" AS "";

CREATE TABLE IF NOT EXISTS deviation_type (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT(10) UNSIGNED NOT NULL,
  type ENUM('skip', 'order') NOT NULL,
  name VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_type_name (qnaire_id ASC, type ASC, name ASC),
  CONSTRAINT fk_deviation_type_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
