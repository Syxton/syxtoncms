get_event||
    SELECT *
    FROM events
    WHERE eventid = '||eventid||'
||get_event

get_registration_values||
    SELECT *
    FROM events_registrations_values
    WHERE regid = '||regid||'
    ORDER BY entryid
||get_registration_values

get_events_having_same_template||
    SELECT * 
    FROM events_registrations 
    WHERE eventid IN (  SELECT eventid 
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
    WHERE reqid='||reqid||';
||delete_events_requests

delete_events_requests_questions||
    DELETE
    FROM events_requests_questions
    WHERE reqid='||reqid||';
||delete_events_requests_questions

get_events_requests||
    SELECT *
    FROM events_requests
    WHERE reqid='||reqid||'
||get_events_requests

events_requests_new_vote||
    UPDATE events_requests
    SET voted = CONCAT(voted,':||voteid||;||newvote||:')
    WHERE reqid='||reqid||'
||events_requests_new_vote

events_requests_calculate||
    UPDATE events_requests
    SET ||approve{{
        votes_for = (votes_for + 1)
        //OR//
        votes_against = (votes_against + 1)
    }}approve||
    WHERE reqid='||reqid||'
||events_requests_calculate

events_requests_change_vote||
    UPDATE events_requests
    SET voted = REPLACE(voted, ':||voteid||;||oldvote||:', ':||voteid||;||newvote||:')
    WHERE reqid='||reqid||'
||events_requests_change_vote

events_requests_recalculate||
    UPDATE events_requests
    SET ||approve{{
        votes_for = (votes_for + 1), votes_against = (votes_against - 1)
        //OR//
        votes_for = (votes_for - 1), votes_against = (votes_against + 1)
    }}approve||
    WHERE reqid='||reqid||'
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