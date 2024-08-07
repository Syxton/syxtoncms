delete_donate_instance||
	DELETE
	FROM donate_instance
	WHERE id = ||featureid||
||delete_donate_instance

get_campaign||
	SELECT *
	FROM donate_campaign
	WHERE campaign_id = ||campaign_id||
||get_campaign

get_donation_campaign||
	SELECT *
	FROM donate_campaign
	WHERE campaign_id IN (
								SELECT campaign_id
								FROM donate_instance
								WHERE donate_id = ||donate_id||
								)
||get_donation_campaign

get_donation_instance_if_owner_of_campaign||
	SELECT *
	FROM donate_instance
	WHERE donate_id = ||donate_id||
	AND campaign_id IN (
								SELECT campaign_id
								FROM donate_campaign
								WHERE origin_page = ||origin_page||
							)
||get_donation_instance_if_owner_of_campaign

get_donation_instance_if_joined_to_campaign||
	SELECT *
	FROM donate_instance
	WHERE donate_id = ||donate_id||
	AND campaign_id <> 0
||get_donation_instance_if_joined_to_campaign

get_donate_instance||
	SELECT *
	FROM donate_instance
	WHERE donate_id = ||donate_id||
||get_donate_instance

get_campaign_donations||
	SELECT *
	FROM donate_donations
	WHERE campaign_id = ||campaign_id||
	ORDER BY timestamp DESC
||get_campaign_donations

get_campaign_donations_total||
	SELECT SUM(amount) as total
	FROM donate_donations
	WHERE campaign_id = ||campaignid||
||get_campaign_donations_total

get_shared_campaigns||
	SELECT *
	FROM donate_campaign
	WHERE origin_page = ||pageid||
	AND campaign_id NOT IN (
								SELECT campaign_id
								FROM donate_instance
								WHERE donate_id IN (
														SELECT featureid
														FROM pages_features
														WHERE pageid = ||pageid||
														AND feature = 'donate'
													)
							)
	OR shared='1'
||get_shared_campaigns

insert_donate_instance||
	INSERT INTO donate_instance
		(campaign_id)
	VALUES
		(||campaign_id||)
||insert_donate_instance

insert_campaign||
	INSERT INTO donate_campaign (origin_page, title, goal_amount, goal_description, paypal_email, token, shared, datestarted, metgoal)
	VALUES(||pageid||, ||title||, ||goal||, ||description||, ||email||, ||token||, ||shared||, ||datestarted||, ||metgoal||)
||insert_campaign

update_campaign||
	UPDATE donate_campaign
	SET title = ||title||, goal_amount = ||goal||, goal_description = ||description||, paypal_email = ||email||, token = ||token||, shared = ||shared||, datestarted = ||datestarted||, metgoal = ||metgoal||
	WHERE campaign_id = ||campaign_id||
||update_campaign

save_campaignid||
	UPDATE donate_instance
	SET campaign_id = ||campaign_id||
	WHERE donate_id = ||donate_id||
||save_campaignid

delete_donation||
	DELETE FROM donate_donations
	WHERE donationid = ||donationid||
||delete_donation

update_donation||
	UPDATE donate_donations
	SET amount = ||amount||, name = ||name||, paypal_TX = ||paypal_TX||, campaign_id = ||campaign_id||
	WHERE donationid = ||donationid||
||update_donation

insert_donation||
	INSERT INTO donate_donations (campaign_id, name, paypal_TX, amount, timestamp)
	VALUES(||campaign_id||, ||name||, ||paypal_TX||, ||amount||, ||timestamp||)
||insert_donation

get_donation||
	SELECT *
	FROM donate_donations
	WHERE donationid = ||donationid||
||get_donation

get_donation_campaigns||
	SELECT *
	FROM donate_campaign
	WHERE shared = 1
	OR campaign_id = ||campaign_id||
||get_donation_campaigns