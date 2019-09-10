CREATE TABLE IF NOT EXISTS attribute (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  qnaire_id INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_qnaire_id (qnaire_id ASC),
  UNIQUE INDEX uq_qnaire_id_name (qnaire_id ASC, name ASC),
  CONSTRAINT fk_attribute_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
