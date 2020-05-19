SELECT language.name, COUNT( participant.id )
FROM participant
JOIN participant_last_hold ON participant.id = participant_last_hold.participant_id
LEFT JOIN hold ON participant_last_hold.hold_id = hold.id
LEFT JOIN hold_type ON hold.hold_type_id = hold_type.id
JOIN language ON participant.language_id = language.id
JOIN collection_has_participant ON participant.id = collection_has_participant.participant_id
JOIN collection ON collection_has_participant.collection_id = collection.id
JOIN patrick_pine.respondent ON participant.id = respondent.participant_id
JOIN patrick_pine.qnaire ON respondent.qnaire_id = qnaire.id
JOIN patrick_pine.response ON respondent.id = response.respondent_id
WHERE participant.email IS NOT NULL
AND qnaire.name = "COVID-19 Baseline Questionnaire"
AND response.submitted = false
AND IFNULL( hold_type.type, "" ) != "final"
AND collection.name = "COVID_Web_Based_Master"
AND participant.id NOT IN (
  SELECT DISTINCT participant_id
  FROM collection_has_participant
  WHERE collection_id IN ( SELECT id FROM collection WHERE name LIKE "COVID%Refusal" )
)
AND participant.uid NOT IN( "T634973","H987789","K320707","V121800","S458449","I878904","H223180","A003850","M169646","K333035","L082387","H638944","I291315","A893115","G928969","A094113","V420469","W311946","E234539","P562855","S723000","P964725","M007547","N337174","J714039","C901925","J466249","D467844","W946307","F891000","J246248","O613288","J740307","Q959226","L830961","O649950","R632012","N422872","O436424","O850050","S880426")
GROUP BY language.name WITH ROLLUP;

INSERT INTO mail( participant_id, from_name, from_address, to_name, to_address, schedule_datetime, subject, body, note )
SELECT participant.id,
       "CLSA-ÉLCV",
       "info@clsa-elcv.ca",
       CONCAT_WS( " ", honorific, first_name, last_name ),
       participant.email,
       CASE
         WHEN FLOOR(6*RAND()) = 0 THEN "2020-05-19 18:05:11" + INTERVAL FLOOR(4*RAND()) HOUR
         WHEN FLOOR(2*RAND()) = 0 THEN "2020-05-20 12:05:11" + INTERVAL FLOOR(10*RAND()) HOUR
         ELSE "2020-05-21 12:05:11" + INTERVAL FLOOR(10*RAND()) HOUR
       END,
       IF( "en" = language.code, "CLSA Reminder for COVID-19 Survey", "Rappel de l’ÉLCV pour l’étude sur la COVID-19" ),
       IF( "en" = language.code,
CONCAT(
"<p>Dear ", honorific, " ", first_name, " ", last_name, ",</p>

<p>Thank you for participating in the Canadian Longitudinal Study on Aging (CLSA) COVID-19 Study. We sent you an email to complete a short web-based questionnaire and a reminder a week later.</p>

<p>If you have already emailed us about an issue, please disregard this email. We will respond to you as soon as we are able. Please be aware that we are experiencing a large number of emails and are doing our best to reply within a week.</p>

<p>Our system shows us that you have started the questionnaire, but not yet completed it. Please complete it at your earliest convenience. You will return to where you left off when you click the link below.</p>

<p>A reminder that supported web browsers include Firefox, Chrome, Safari and Edge.  Please do not use Internet Explorer as certain parts of the questionnaire may not display correctly.  If using a smart phone or tablet, only devices less than 5 years old are supported.  These devices must be up to date before starting the questionnaire.</p>

<p>Click here to do the survey: <a href=\"https://survey.clsa-elcv.ca/live/pine/respondent/run/", token, "\">https://survey.clsa-elcv.ca/live/pine/respondent/run/", token, "</a></p>

<p>If you have any technical issues with the survey, please contact us at <a href=\"mailto:info@clsa-elcv.ca\">info@clsa-elcv.ca</a> and we will get in touch with you as soon as possible.</p>

<p>Regards</p>" )
,
CONCAT(
"<p>Bonjour ", honorific, " ", first_name, " ", last_name, ",</p>

<p>Merci de participer à l’étude sur la COVID-19 de l’Étude longitudinale canadienne sur le vieillissement (ÉLCV). Nous vous avons envoyé un courriel vous invitant à répondre à un court questionnaire en ligne, puis un rappel une semaine plus tard.</p>

<p>Si vous nous avez déjà envoyé un courriel à propos d’un problème, veuillez ignorer le présent courriel. Nous vous répondrons dès que possible. Veuillez noter que nous recevons un grand nombre de courriels et faisons de notre mieux pour répondre dans un délai d’une semaine.</p>

<p>Notre système nous indique que vous avez commencé le questionnaire, mais ne l’avez pas encore terminé. Veuillez le compléter dans les meilleurs délais. Le lien ci-dessous vous dirigera là où vous vous étiez arrêté(e).</p>

<p>Nous vous rappelons que les navigateurs pris en charge sont Firefox, Chrome, Safari et Edge. Veuillez éviter d’utiliser Internet Explorer, car certaines parties du questionnaire pourraient ne pas s’afficher correctement. Si vous utilisez un téléphone intelligent ou une tablette, seuls les appareils de moins de cinq ans sont pris en charge. Ces appareils doivent être à jour avant de commencer le questionnaire.</p>

<p>Cliquez ici pour répondre au sondage : <a href=\"https://survey.clsa-elcv.ca/live/pine/respondent/run/", token, "\">https://survey.clsa-elcv.ca/live/pine/respondent/run/", token, "</a></p>

<p>Si vous avez des problèmes techniques en lien avec le questionnaire, veuillez nous écrire à <a href=\"mailto:info@clsa-elcv.ca\">info@clsa-elcv.ca</a> et nous vous contacterons dès que possible.</p>

<p>Cordialement,</p>"
)
),
       "Manually added to participants who have not completed the COVID-19 Baseline Questionnaire."
FROM participant
JOIN participant_last_hold ON participant.id = participant_last_hold.participant_id
LEFT JOIN hold ON participant_last_hold.hold_id = hold.id
LEFT JOIN hold_type ON hold.hold_type_id = hold_type.id
JOIN language ON participant.language_id = language.id
JOIN collection_has_participant ON participant.id = collection_has_participant.participant_id
JOIN collection ON collection_has_participant.collection_id = collection.id
JOIN patrick_pine.respondent ON participant.id = respondent.participant_id
JOIN patrick_pine.qnaire ON respondent.qnaire_id = qnaire.id
JOIN patrick_pine.response ON respondent.id = response.respondent_id
WHERE participant.email IS NOT NULL
AND qnaire.name = "COVID-19 Baseline Questionnaire"
AND response.submitted = false
AND IFNULL( hold_type.type, "" ) != "final"
AND collection.name = "COVID_Web_Based_Master"
AND participant.id NOT IN (
  SELECT DISTINCT participant_id
  FROM collection_has_participant
  WHERE collection_id IN ( SELECT id FROM collection WHERE name LIKE "COVID%Refusal" )
)
AND participant.uid NOT IN( "T634973","H987789","K320707","V121800","S458449","I878904","H223180","A003850","M169646","K333035","L082387","H638944","I291315","A893115","G928969","A094113","V420469","W311946","E234539","P562855","S723000","P964725","M007547","N337174","J714039","C901925","J466249","D467844","W946307","F891000","J246248" );

select DATE( schedule_datetime ), HOUR( schedule_datetime ), count(*)
from mail
where note = "Manually added to participants who have not completed the COVID-19 Baseline Questionnaire."
GROUP BY DATE( schedule_datetime ), HOUR( schedule_datetime ) WITH ROLLUP;
