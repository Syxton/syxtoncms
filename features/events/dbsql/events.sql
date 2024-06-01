get_event||
	SELECT *
	FROM events
	WHERE eventid = ||eventid||
||get_event

update_reg_event||
	UPDATE events_registrations_values
	SET eventid = ||eventid||
	WHERE regid = ||regid||
||update_reg_event

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
	AND date > ||fromdate|| AND date < ||todate||
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
	SELECT DISTINCT CONCAT(contact, ': ', email, ': ', phone) as admin_contact
	FROM events
	WHERE confirmed = 1
	ORDER BY contact, eventid DESC
||get_contacts_list

get_payable_list||
	SELECT DISTINCT CONCAT(payableto, ': ', checksaddress, ': ', paypal) as admin_contact
	FROM events
	WHERE payableto <> ''
	AND confirmed = 1
||get_payable_list

find_paypal_transfer||
	SELECT *
	FROM logfile
	WHERE feature = "events"
	AND description = "Paypal"
	AND info = ||info||
||find_paypal_transfer

update_reg_value||
	UPDATE events_registrations_values
	SET value = ||value||
	WHERE elementname = ||elementname||
	AND regid = ||regid||
||update_reg_value

update_reg_status||
	UPDATE events_registrations
	SET verified = ||verified||
	WHERE regid = ||regid||
||update_reg_status

update_reg_event_info||
	UPDATE events_registrations
	SET eventid = ||eventid||, email = ||email||, code = ||code||
	WHERE regid = ||regid||
||update_reg_event_info

reg_copy_create_temptable||
	CREATE TEMPORARY TABLE temp_updates (
		id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		entryid INT(11) UNSIGNED NOT NULL,
		newvalue LONGTEXT COLLATE 'utf8_general_ci'
	)
||reg_copy_create_temptable

reg_update_from_temptable||
	UPDATE events_registrations_values e, temp_updates t
	SET e.value = t.newvalue WHERE e.entryid = t.entryid AND e.regid = ||regid||
||reg_update_from_temptable

insert_registration||
	INSERT INTO events_registrations (eventid, date, email, code, verified)
	VALUES(||eventid||, ||date||, ||email||, ||code||, ||verified||)
||insert_registration

insert_registration_values||
	INSERT INTO events_registrations_values (regid, value, eventid, elementname)
	VALUES(||regid||, ||value||, ||eventid||, ||elementname||)
||insert_registration_values

delete_registration||
	DELETE FROM events_registrations
	WHERE regid = ||regid||
||delete_registration

delete_registration_values||
	DELETE FROM events_registrations_values
	WHERE regid = ||regid||
||delete_registration_values

update_staff_app||
	UPDATE events_staff
	SET userid = ||userid||, pageid = ||pageid||, name = ||name||, phone = ||phone||,
		dateofbirth = ||dateofbirth||, address = ||address||, agerange = ||agerange||,
		cocmember = ||cocmember||, congregation = ||congregation||, priorwork = ||priorwork||,
		q1_1 = ||q1_1||, q1_2 = ||q1_2||, q1_3 = ||q1_3||, q2_1 = ||q2_1||, q2_2 = ||q2_2||, q2_3 = ||q2_3||,
		parentalconsent = ||parentalconsent||, parentalconsentsig = ||parentalconsentsig||,
		workerconsent = ||workerconsent||, workerconsentsig = ||workerconsentsig||, workerconsentdate = ||workerconsentdate||,
		ref1name = ||ref1name||, ref1relationship = ||ref1relationship||, ref1phone = ||ref1phone||,
		ref2name = ||ref2name||, ref2relationship = ||ref2relationship||, ref2phone = ||ref2phone||,
		ref3name = ||ref3name||, ref3relationship = ||ref3relationship||, ref3phone = ||ref3phone||
	WHERE staffid = ||staffid||
||update_staff_app

insert_staff_app||
	INSERT INTO events_staff
	(userid, pageid, name, phone, dateofbirth, address, agerange, cocmember, congregation, priorwork, q1_1, q1_2, q1_3, q2_1, q2_2, q2_3, parentalconsent, parentalconsentsig, workerconsent, workerconsentsig, workerconsentdate, ref1name, ref1relationship, ref1phone, ref2name, ref2relationship, ref2phone, ref3name, ref3relationship, ref3phone, bgcheckpass, bgcheckpassdate)
	VALUES(||userid||, ||pageid||, ||name||, ||phone||, ||dateofbirth||, ||address||, ||agerange||, ||cocmember||, ||congregation||, ||priorwork||, ||q1_1||, ||q1_2||, ||q1_3||, ||q2_1||, ||q2_2||, ||q2_3||, ||parentalconsent||, ||parentalconsentsig||, ||workerconsent||, ||workerconsentsig||, ||workerconsentdate||, ||ref1name||,	||ref1relationship||, ||ref1phone||, ||ref2name||, ||ref2relationship||, ||ref2phone||, ||ref3name||, ||ref3relationship||, ||ref3phone||, '', 0)
||insert_staff_app

get_staff_app||
	SELECT *
	FROM events_staff
	WHERE staffid = ||staffid||
||get_staff_app

update_staff_app_archive||
	UPDATE events_staff_archive
	SET userid = ||userid||, pageid = ||pageid||, name = ||name||, phone = ||phone||,
		dateofbirth = ||dateofbirth||, address = ||address||, agerange = ||agerange||,
		cocmember = ||cocmember||, congregation = ||congregation||, priorwork = ||priorwork||,
		q1_1 = ||q1_1||, q1_2 = ||q1_2||, q1_3 = ||q1_3||, q2_1 = ||q2_1||, q2_2 = ||q2_2||, q2_3 = ||q2_3||,
		parentalconsent = ||parentalconsent||, parentalconsentsig = ||parentalconsentsig||,
		workerconsent = ||workerconsent||, workerconsentsig = ||workerconsentsig||, workerconsentdate = ||workerconsentdate||,
		ref1name = ||ref1name||, ref1relationship = ||ref1relationship||, ref1phone = ||ref1phone||,
		ref2name = ||ref2name||, ref2relationship = ||ref2relationship||, ref2phone = ||ref2phone||,
		ref3name = ||ref3name||, ref3relationship = ||ref3relationship||, ref3phone = ||ref3phone||,
		bgcheckpass = ||bgcheckpass||, bgcheckpassdate = ||bgcheckpassdate||
	WHERE staffid = ||staffid|| AND year = ||year|| AND pageid = ||pageid||
||update_staff_app_archive

insert_staff_app_archive||
	INSERT INTO events_staff_archive
	(userid, pageid, name, phone, dateofbirth, address, agerange, cocmember, congregation, priorwork, q1_1, q1_2, q1_3, q2_1, q2_2, q2_3, parentalconsent, parentalconsentsig, workerconsent, workerconsentsig, workerconsentdate, ref1name, ref1relationship, ref1phone, ref2name, ref2relationship, ref2phone, ref3name, ref3relationship, ref3phone, bgcheckpass, bgcheckpassdate, year)
	VALUES(||userid||, ||pageid||, ||name||, ||phone||, ||dateofbirth||, ||address||, ||agerange||, ||cocmember||, ||congregation||, ||priorwork||, ||q1_1||, ||q1_2||, ||q1_3||, ||q2_1||, ||q2_2||, ||q2_3||, ||parentalconsent||, ||parentalconsentsig||, ||workerconsent||, ||workerconsentsig||, ||workerconsentdate||, ||ref1name||,	||ref1relationship||, ||ref1phone||, ||ref2name||, ||ref2relationship||, ||ref2phone||, ||ref3name||, ||ref3relationship||, ||ref3phone||, '', 0, ||year||)
||insert_staff_app_archive

get_staff_by_year||
	SELECT *
	FROM events_staff_archive
	WHERE staffid = ||staffid||
	AND pageid = ||pageid||
	AND year = ||year||
||get_staff_by_year

get_all_staff_by_year||
	SELECT *
	FROM events_staff_archive
	WHERE pageid = ||pageid||
	AND year = ||year||
	ORDER BY name
||get_all_staff_by_year


get_all_staff_by_page||
	SELECT *
	FROM events_staff_archive
	WHERE pageid = ||pageid||
	GROUP BY year
	ORDER BY year
||get_all_staff_by_page