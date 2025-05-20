delete_calendar_events||
    DELETE FROM calendar_events
    WHERE eventid = ||eventid||
||delete_calendar_events

delete_calendar_event||
    DELETE FROM calendar_events
    WHERE id = ||id||
||delete_calendar_event

get_calendar_event_data||
    SELECT *
    FROM `calendar_events`
    WHERE `date` > ||from||
    AND `date` < ||to||
    AND `day` = ||day||
    ||show_site_events{{
    AND (
        pageid = ||pageid||
        OR
        pageid = ||siteid||
        OR
        site_viewable = 1
    )
    //OR//
    AND pageid = ||pageid||
    }}show_site_events||
    ORDER BY day;
||get_calendar_event_data