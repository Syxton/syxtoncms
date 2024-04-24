delete_donate_instance||
  DELETE
  FROM donate_instance
  WHERE id = '||featureid||'
||delete_donate_instance

get_campaign||
  SELECT *
  FROM donate_campaign
  WHERE campaign_id IN (
                        SELECT campaign_id 
                        FROM donate_instance
                        WHERE donate_id='||featureid||'
                       )
||get_campaign

get_donate_instance||
  SELECT *
  FROM donate_instance
  WHERE donate_id='||featureid||'
||get_donate_instance

get_campaign_donations_total||
  SELECT SUM(amount) as total 
  FROM donate_donations
  WHERE campaign_id = '||campaignid||'
||get_campaign_donations_total