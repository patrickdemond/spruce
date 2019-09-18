SELECT "Creating new page table" AS "";

CREATE TABLE IF NOT EXISTS page (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  update_timestamp TIMESTAMP NOT NULL,
  create_timestamp TIMESTAMP NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  rank INT UNSIGNED NOT NULL,
  name VARCHAR(127) NOT NULL,
  description TEXT NULL,
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
