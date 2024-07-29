delete_polls||
    DELETE
    FROM polls
    WHERE pageid = ||pageid||
    AND pollid = ||pollid||
||delete_polls

delete_answers||
    DELETE
    FROM polls_answers
    WHERE pollid = ||pollid||
||delete_answers

delete_answer||
    DELETE
    FROM polls_answers
    WHERE answerid = ||answerid||
||delete_answer

check_existing_answer||
    SELECT *
    FROM polls_answers
    WHERE pollid = ||pollid||
    AND answer = ||answer||
||check_existing_answer

insert_answer||
    INSERT INTO polls_answers (pollid, answer, sort)
    VALUES(||pollid||, ||answer||, ||sort||)
||insert_answer

update_answer_sort||
    UPDATE polls_answers
    SET sort = ||sort||
    WHERE answerid = ||answerid||
||update_answer_sort

delete_responses||
    DELETE
    FROM polls_response
    WHERE pollid = ||pollid||
||delete_responses

delete_deprecated_responses||
    DELETE FROM polls_response
    WHERE pollid = ||pollid||
    AND answer NOT IN (
                                SELECT answerid
                                FROM polls_answers
                                WHERE pollid = ||pollid||
                            )
||delete_deprecated_responses

insert_poll||
    INSERT INTO polls (pageid)
    VALUES(||pageid||)
||insert_poll

update_poll||
    UPDATE polls
    SET status = ||status||, question = ||question||, startdate = ||startdate||, stopdate = ||stopdate||
    WHERE pollid = ||pollid||
||update_poll

update_poll_status||
    UPDATE polls
    SET status = ||status||
    WHERE pollid = ||pollid||
||update_poll_status

get_poll||
    SELECT *
    FROM polls
    WHERE pollid = ||pollid||
||get_poll

get_answers||
    SELECT *
    FROM polls_answers
    WHERE pollid = ||pollid||
||get_answers

get_responses||
    SELECT *
    FROM polls_response
    WHERE pollid = ||pollid||
||get_responses

poll_data||
    SELECT a.*, (
                        SELECT COUNT(*) as count
                        FROM polls_response r
                        WHERE r.answer = a.answerid
                    ) as stat
    FROM polls_answers AS a
    WHERE a.pollid = ||pollid||
||poll_data

poll_user_responses||
    SELECT *
    FROM polls_response
    WHERE pollid = ||pollid||
    AND (
            userid = ||userid||
            OR ip = ||ip||
    )
||poll_user_responses