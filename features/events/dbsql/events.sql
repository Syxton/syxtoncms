get_event||
    SELECT *
    FROM events
    WHERE eventid = ||eventid||
||get_event

insert_event||
    INSERT INTO events
	(pageid, template_id, name, category, location, allowinpage, start_reg, stop_reg, max_users, event_begin_date, event_begin_time, event_end_date, event_end_time,
  	confirmed, siteviewable, allday, caleventid, byline, description, fee_min, fee_full, payableto, checksaddress, paypal, sale_fee, sale_end, contact, email, phone, hard_limits, soft_limits, workers)
    VALUES(||pageid||, ||template_id||, ||name||, ||category||, ||location||, ||allowinpage||, ||start_reg||, ||stop_reg||, ||max_users||, ||event_begin_date||,
    ||event_begin_time||, ||event_end_date||, ||event_end_time||, ||confirmed||, ||siteviewable||, ||allday||, ||caleventid||, ||byline||, ||description||,
    ||fee_min||, ||fee_full||, ||payableto||, ||checksaddress||, ||paypal||, ||sale_fee||, ||sale_end||, ||contact||, ||email||, ||phone||, ||hard_limits||, ||soft_limits||, ||workers||)
||insert_event

update_event||
    UPDATE events SET template_id = ||template_id||, name = ||name||, category = ||category||, location = ||location||, allowinpage = ||allowinpage||, start_reg = ||start_reg||,
        stop_reg = ||stop_reg||, max_users = ||max_users||, event_begin_date = ||event_begin_date||, event_begin_time = ||event_begin_time||, event_end_date = ||event_end_date||,
        event_end_time = ||event_end_time||, sale_fee = ||sale_fee||, sale_end = ||sale_end||, contact = ||contact||, email = ||email||, phone = ||phone||, hard_limits = ||hard_limits||,
        soft_limits = ||soft_limits||, siteviewable = ||siteviewable||, allday = ||allday||, byline = ||byline||, description = ||description||, paypal = ||paypal||,
        fee_min = ||fee_min||, fee_full = ||fee_full||, payableto = ||payableto||, checksaddress = ||checksaddress||, confirmed = ||confirmed||, workers = ||workers||
    WHERE eventid = ||eventid||
||update_event

delete_event||
    DELETE
    FROM events
    WHERE eventid = ||eventid||
||delete_event

delete_event_registrations||
    DELETE
    FROM events_registrations
    WHERE eventid = ||eventid||
||delete_event_registrations

get_registration_values||
    SELECT *
    FROM events_registrations_values
    WHERE regid = ||regid||
    ORDER BY entryid
||get_registration_values

delete_events_registrations_values||
    DELETE
    FROM events_registrations_values
    WHERE eventid = ||eventid||
||delete_events_registrations_values

get_events_having_same_template||
    SELECT * 
    FROM events_registrations 
    WHERE eventid IN (
                        SELECT eventid 
                        FROM events 
                        WHERE template_id = ||templateid||
                     )
    ||year{{
    AND date > '||fromdate||' AND date < '||todate||'
    }}year||
    ORDER BY regid DESC
||get_events_having_same_template

delete_events_requests||
    DELETE
    FROM events_requests
    WHERE reqid = ||reqid||;
||delete_events_requests

delete_events_requests_questions||
    DELETE
    FROM events_requests_questions
    WHERE reqid = ||reqid||;
||delete_events_requests_questions

get_events_requests||
    SELECT *
    FROM events_requests
    WHERE reqid = ||reqid||
||get_events_requests

events_requests_new_vote||
    UPDATE events_requests
    SET voted = CONCAT(voted, ||newvote||)
    WHERE reqid = ||reqid||
||events_requests_new_vote

events_requests_calculate||
    UPDATE events_requests
    SET ||approve{{
        votes_for = (votes_for + 1)
        //OR//
        votes_against = (votes_against + 1)
    }}approve||
    WHERE reqid = ||reqid||
||events_requests_calculate

events_requests_change_vote||
    UPDATE events_requests
    SET voted = REPLACE(voted, ||oldvote||, ||newvote||)
    WHERE reqid = ||reqid||
||events_requests_change_vote

events_requests_recalculate||
    UPDATE events_requests
    SET ||approve{{
        votes_for = (votes_for + 1), votes_against = (votes_against - 1)
        //OR//
        votes_for = (votes_for - 1), votes_against = (votes_against + 1)
    }}approve||
    WHERE reqid= ||reqid||
||events_requests_recalculate

get_contacts_list||
    SELECT DISTINCT CONCAT(contact,': ',email,': ',phone) as admin_contact
    FROM events
    WHERE confirmed = 1
    ORDER BY contact, eventid DESC
||get_contacts_list

get_payable_list||
    SELECT DISTINCT CONCAT(payableto,': ',checksaddress,': ',paypal) as admin_contact
    FROM events
    WHERE payableto != ''
    AND confirmed = 1
||get_payable_list