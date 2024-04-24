delete_polls||
  DELETE
  FROM polls
  WHERE pageid = '||pageid||'
  AND pollid = '||featureid||'
||delete_polls

delete_answers||
  DELETE
  FROM polls_answers
  WHERE pollid = '||featureid||'
||delete_answers

delete_responses||
  DELETE
  FROM polls_response
  WHERE pollid = '||featureid||'
||delete_responses