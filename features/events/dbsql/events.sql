get_event||
    SELECT *
    FROM events
    WHERE eventid = ||eventid||
||get_event

get_templates_event_registrations_by_email||
    SELECT *
    FROM events_registrations
    WHERE email = ||email||
    AND eventid IN (
        SELECT eventid
        FROM events
        WHERE template_id IN (
            SELECT template_id
            FROM events_templates
            ||*templates||
        )
    )
    ORDER BY regid DESC
||get_templates_event_registrations_by_email

get_verified_event_registrations||
    SELECT *
    FROM events_registrations
    WHERE eventid = ||eventid||
    AND verified = 1
||get_verified_event_registrations

get_pending_event_registrations||
    SELECT *
    FROM events_registrations
    WHERE eventid = ||eventid||
    AND verified = 0
||get_pending_event_registrations

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

get_events_with_same_template||
    SELECT e.eventid, b.orderbyfield, b.folder
    FROM events as e
    JOIN events_templates as b ON b.template_id=e.template_id
    WHERE eventid=||eventid||
||get_events_with_same_template

get_events_templates_by_regid||
    SELECT *
    FROM events_templates
    WHERE template_id IN (
        SELECT template_id
        FROM events
        WHERE eventid IN (
            SELECT eventid
            FROM events_registrations
            WHERE regid=||regid||
        )
    )
||get_events_templates_by_regid

get_events_templates_form_names||
    SELECT *
    FROM events_templates_forms
    WHERE template_id=||template_id||
    AND nameforemail=1
||get_events_templates_form_names

get_current_events_with_same_template||
    SELECT eventid, (CONCAT(FROM_UNIXTIME(e.event_begin_date , '%Y'), ' ', e.name)) AS name
    FROM events e
    WHERE e.confirmed = 1
    AND e.template_id = ||template_id||
    AND e.start_reg > 0
    AND (
        (e.event_begin_date - ||today||) < 31560000
        &&
        (e.event_begin_date - ||today||) > -7776000
    )
||get_current_events_with_same_template

delete_event||
    DELETE
    FROM events
    WHERE eventid = ||eventid||
||delete_event

delete_event_settings||
    DELETE
    FROM settings
    WHERE type = ||type||
    AND extra = ||extra||
||delete_event_settings

delete_event_registrations||
    DELETE
    FROM events_registrations
    WHERE eventid = ||eventid||
||delete_event_registrations

get_registration_value||
    SELECT *
    FROM events_registrations_values
    WHERE regid = ||regid||
    AND LOWER(elementname) = ||elementname||
||get_registration_value

get_registration_value_by_id||
    SELECT *
    FROM events_registrations_values
    WHERE regid = ||regid||
    AND elementid = ||elementid||
||get_registration_value_by_id

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

get_events_template_forms||
    SELECT *
    FROM events_templates_forms
    WHERE template_id = ||template_id||
    ORDER BY sort
||get_events_template_forms

update_template_status||
    UPDATE events_templates
    SET activated = ||activated||
    WHERE template_id = ||template_id||
||update_template_status

insert_events_template||
    INSERT INTO events_templates
    (name, folder, formlist, registrant_name, orderbyfield, settings)
    VALUES (||name||, ||folder||, ||formlist||, ||registrant_name||, ||orderbyfield||, ||settings||)
||insert_events_template

update_events_template||
    UPDATE events_templates
    SET formlist = ||formlist||, registrant_name = ||registrant_name||, orderbyfield = ||orderbyfield||, settings = ||settings||
    WHERE template_id = ||template_id||
||update_events_template

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

insert_events_requests_questions||
    INSERT INTO events_requests_questions
    (reqid, question, answer, question_time, answer_time)
    VALUES(||reqid||, ||question||, "", ||qtime||, 0)
||insert_events_requests_questions

get_events_requests_questions||
    SELECT *
    FROM events_requests_questions
    WHERE reqid = ||reqid||
    ||*mod||
    ORDER BY question_time
||get_events_requests_questions

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
    ORDER BY admin_contact DESC
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

get_event_from_regid||
    SELECT *
    FROM events
    WHERE eventid IN (
        SELECT eventid
        FROM events_registrations
        WHERE regid = ||regid||
    )
||get_event_from_regid

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

search_staff_app||
    SELECT s.*, u.email
    FROM events_staff s
    JOIN users u ON u.userid = s.userid
    WHERE ||searchstring||
    AND s.pageid = ||pageid||
    ORDER BY s.name
||search_staff_app

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

get_all_years_with_staff||
    SELECT year
    FROM events_staff_archive
    WHERE pageid = ||pageid||
    GROUP BY year
    ORDER BY year
||get_all_years_with_staff

events_search||
    SELECT *
    FROM events
    WHERE start_reg != ''
    AND ||searchstring||
    AND (
        pageid = ||pageid||
        ||issite{{
        OR (
            siteviewable = 1
            AND confirmed = 1
        )
        }}issite||
    )
    ORDER BY event_begin_date DESC
||events_search

registration_search||
    SELECT e.name, e.eventid, e.pageid, v.value, r.regid, r.email, r.date, r.code
    FROM events_registrations_values v
    JOIN events e ON e.eventid = v.eventid
    JOIN events_registrations r ON r.regid = v.regid
    WHERE ||searchstring||
    AND v.elementname IN (SELECT registrant_name FROM events_templates)
    AND (
        e.pageid = ||pageid||
        ||issite{{
        OR (
            e.siteviewable = 1
            AND e.confirmed = 1
        )
        }}issite||
    )
    ORDER BY r.date DESC
||registration_search

confirmable_events||
    SELECT *
    FROM events e
    WHERE confirmed = 3
    AND ||time|| < e.event_end_date
    AND (   (e.pageid != ||pageid|| AND siteviewable = 1)
            OR
            (e.pageid = ||pageid||)
    )
    ORDER BY e.event_begin_date, e.event_begin_time
||confirmable_events

editable_events||
    SELECT *
    FROM events e
    WHERE ((||time|| - 86400) < e.event_end_date)
    AND (e.pageid = ||pageid|| ||siteviewable||)
    ORDER BY e.event_begin_date, e.event_begin_time
||editable_events

open_enrollable_events||
    SELECT *
    FROM events e
    WHERE (e.pageid = ||pageid|| ||siteviewable||)
    AND e.start_reg < ||time||
    AND e.stop_reg > (||time|| - 86400)
    AND (   e.max_users = 0
            OR (    e.max_users != 0
                    AND e.max_users > (
                                        SELECT COUNT(*)
                                        FROM events_registrations er
                                        WHERE er.eventid = e.eventid
                                        AND verified = 1
                    )
            )
    )
    ORDER BY e.event_begin_date, e.event_begin_time
||open_enrollable_events

upcoming_events||
    SELECT *
    FROM events e
    WHERE (
        e.pageid = ||pageid||
        ||issite{{
            OR (
                siteviewable = 1
                AND confirmed = 1
            )
        }}issite||
    )
    AND e.event_begin_date < ||totime||
    AND e.event_begin_date > ||fromtime||
    ORDER BY e.event_begin_date, e.event_begin_time
||upcoming_events

events_between_dates||
    SELECT *
    FROM events e
    WHERE (
        e.pageid = ||pageid||
        ||issite{{
            OR
            (
                e.pageid != ||pageid||
                AND siteviewable = 1
            )
        }}issite||
    )
    AND e.event_begin_date > ||fromtime||
    AND e.event_begin_date < ||totime||
    ||showonlyconfirmed{{
    AND e.confirmed = 1
    }}showonlyconfirmed||
    ORDER BY e.event_begin_date, e.event_begin_time
||events_between_dates

current_events||
    SELECT *
    FROM events e
    WHERE (e.pageid = ||pageid|| ||siteviewable||)
    AND (
            (
                ((e.event_begin_date + 86400) - ||time||) < 86400
                AND ((e.event_begin_date + 86400) - ||time||) > 0
            )
            OR
            (
                ||time|| > e.event_begin_date
                AND ||time|| < e.event_end_date
            )
    )
    ORDER BY e.event_begin_date, e.event_begin_time
||current_events

recent_events||
    SELECT *
    FROM events e
    WHERE (e.pageid = ||pageid|| ||siteviewable||)
    AND (e.event_end_date + ||to_day||) > ||time||
    AND e.event_end_date < ||time||
    ORDER BY e.event_begin_date DESC, e.event_begin_time DESC
||recent_events

templates_search||
    SELECT *
    FROM events_templates
    WHERE (||searchstring||)
    ORDER BY name
||templates_search

get_promocode_sets||
    SELECT *
    FROM events_promo_set
    WHERE pageid = ||pageid||
    ORDER BY created
||get_promocode_sets

get_promocode_set||
    SELECT *
    FROM events_promo_set
    WHERE setid = ||setid||
    ORDER BY created
||get_promocode_set

update_promocode_set||
    UPDATE events_promo_set
    SET setname = ||setname||
    WHERE setid = ||setid||
||update_promocode_set

insert_promocode_set||
    INSERT INTO events_promo_set
    (pageid, setname, created)
    VALUES
    (||pageid||, ||setname||, ||created||)
||insert_promocode_set

delete_promocode_set||
    DELETE FROM events_promo_set
    WHERE setid = ||setid||
||delete_promocode_set

delete_promocode_set_codes||
    DELETE FROM events_promo_set_codes
    WHERE setid = ||setid||
||delete_promocode_set_codes

get_promocode_set_codes||
    SELECT *
    FROM events_promo_set_codes
    WHERE setid = ||setid||
    ORDER BY created
||get_promocode_set_codes

update_promocode||
    UPDATE events_promo_set_codes
    SET code = ||code||, codename = ||codename||, reduction = ||reduction||
    WHERE codeid = ||codeid||
||update_promocode

insert_promocode||
    INSERT INTO events_promo_set_codes
    (setid, code, codename, reduction, created)
    VALUES
    (||setid||,||code||, ||codename||, ||reduction||, ||created||)
||insert_promocode

delete_promocode||
    DELETE FROM events_promo_set_codes
    WHERE codeid = ||codeid||
||delete_promocode