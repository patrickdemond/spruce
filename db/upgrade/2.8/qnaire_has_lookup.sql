SELECT "Creating new qnaire_has_lookup table" AS "";

CREATE TABLE IF NOT EXISTS qnaire_has_lookup (
  qnaire_id INT(10) UNSIGNED NOT NULL,
  lookup_id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  PRIMARY KEY (qnaire_id, lookup_id),
  INDEX fk_lookup_id (lookup_id ASC),
  INDEX fk_qnaire_id (qnaire_id ASC),
  CONSTRAINT fk_qnaire_has_lookup_qnaire_id
    FOREIGN KEY (qnaire_id)
    REFERENCES qnaire (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_qnaire_has_lookup_lookup_id
    FOREIGN KEY (lookup_id)
    REFERENCES lookup (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
