SELECT "Creating new response_stage table" AS "";

CREATE TABLE IF NOT EXISTS response_stage (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  response_id INT(10) UNSIGNED NOT NULL,
  stage_id INT UNSIGNED NOT NULL,
  page_id INT(10) UNSIGNED NULL DEFAULT NULL,
  status ENUM('not ready', 'ready', 'active', 'paused', 'skipped', 'completed') NOT NULL DEFAULT 'not ready',
  skip_deviation_type_id INT UNSIGNED NULL DEFAULT NULL,
  order_deviation_type_id INT UNSIGNED NULL DEFAULT NULL,
  comments TEXT NULL,
  PRIMARY KEY (id),
  INDEX fk_response_id (response_id ASC),
  INDEX fk_stage_id (stage_id ASC),
  INDEX fk_skip_deviation_type_id (skip_deviation_type_id ASC),
  INDEX fk_order_deviation_type_id (order_deviation_type_id ASC),
  INDEX fk_page_id (page_id ASC),
  UNIQUE INDEX uq_response_id_stage_id (response_id ASC, stage_id ASC),
  CONSTRAINT fk_response_stage_response_id
    FOREIGN KEY (response_id)
    REFERENCES response (id)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT fk_response_stage_stage_id
    FOREIGN KEY (stage_id)
    REFERENCES stage (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_response_stage_skip_deviation_type_id
    FOREIGN KEY (skip_deviation_type_id)
    REFERENCES deviation_type (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_response_stage_order_deviation_type_id
    FOREIGN KEY (order_deviation_type_id)
    REFERENCES deviation_type (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT fk_response_stage_page_id
    FOREIGN KEY (page_id)
    REFERENCES page (id)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;
