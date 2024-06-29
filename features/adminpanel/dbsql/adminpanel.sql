useractions||
    SELECT ||fields||
    FROM logfile
    WHERE userid = ||userid||
    AND YEAR(FROM_UNIXTIME(timeline)) = ||year||
    AND MONTH(FROM_UNIXTIME(timeline)) = ||month||
    ||*order||
    ||*limit||
||useractions