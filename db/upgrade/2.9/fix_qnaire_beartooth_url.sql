DROP PROCEDURE IF EXISTS fix_language;
DELIMITER //
CREATE PROCEDURE fix_language()
  BEGIN
    SELECT "Adding Beartooth URL to qnaire.parent_beartooth_url column in qnaire table" AS "";

    UPDATE qnaire
    SET parent_beartooth_url = CONCAT(
      "https://beartooth.clsa-elcv.ca/",
      SUBSTRING( USER(), 1, LOCATE( "@", USER() )-1 ),
      "/f3"
    )
    WHERE name IN ("Follow-up 3 Home", "Follow-up 3 Site")
    AND parent_username IS NOT NULL;

  END //
DELIMITER ;

CALL fix_language();
DROP PROCEDURE IF EXISTS fix_language;
