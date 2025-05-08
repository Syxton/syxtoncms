alreadyregistered||
    SELECT *
    FROM events_registrations
    WHERE regid IN (
                    SELECT regid
                    FROM events_registrations_values
                    WHERE elementname="camper_name"
                    AND value=||name||
                    )
    AND regid IN (
                    SELECT regid
                    FROM events_registrations_values
                    WHERE elementname="camper_birth_date"
                    AND value=||birthdate||
                )
    AND eventid=||eventid||
||alreadyregistered