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