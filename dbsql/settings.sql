get_settings_by_featureid||
    SELECT *
    FROM settings
    WHERE type = ||type||
    AND featureid = ||featureid||
||get_settings_by_featureid

update_setting_by_featureid||
    UPDATE settings
    SET setting = ||setting||
    WHERE type = ||type||
    AND featureid = ||featureid||
    AND setting_name = ||setting_name||
||update_setting_by_featureid

update_setting_by_extra||
    UPDATE settings
    SET setting = ||setting||
    WHERE type = ||type||
    AND extra = ||extra||
    AND setting_name = ||setting_name||
||update_setting_by_extra

insert_setting||
    INSERT INTO settings
    (type, pageid, featureid, setting_name, setting, extra)
    VALUES (||type||, ||pageid||, ||featureid||, ||setting_name||, ||setting||, ||extra||)
||insert_setting