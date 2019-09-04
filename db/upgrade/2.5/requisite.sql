CREATE TABLE IF NOT EXISTS requisite (
  id INT UNSIGNED NOT NULL,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  requisite_group_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  logic ENUM('AND', 'OR') NOT NULL DEFAULT 'AND',
  negative TINYINT(1) NOT NULL DEFAULT 0,
  question_id INT UNSIGNED NULL DEFAULT NULL,
  attribute_id INT UNSIGNED NULL DEFAULT NULL,
  operator ENUM('IS NULL', '=', '!=', '<', '<=', '>', '>=') NOT NULL,
  value VARCHAR(45) NULL DEFAULT NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_requisite_group_id (requisite_group_id ASC),
  INDEX fk_question_id (question_id ASC),
  INDEX fk_attribute_id (attribute_id ASC),
  UNIQUE INDEX uq_requisite_group_id_rank (requisite_group_id ASC, rank ASC),
  CONSTRAINT fk_requisite_requisite_group_id
    FOREIGN KEY (requisite_group_id)
    REFERENCES requisite_group (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_requisite_question_id
    FOREIGN KEY (question_id)
    REFERENCES question (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_requisite_attribute_id
    FOREIGN KEY (attribute_id)
    REFERENCES attribute (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
