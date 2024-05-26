custom_theme_styles||
	SELECT *, 1 as ranky
	FROM styles
	WHERE pageid = ||pageid||
	AND themeid = ||themeid||
	UNION
	SELECT *, 2 as ranky
	FROM styles
	WHERE pageid = ||pageid||
	AND themeid = ||themeid||
	AND feature = ||feature||
	AND featureid = 0
	UNION
	SELECT *, 3 as ranky
	FROM styles
	WHERE pageid = ||pageid||
	AND themeid = ||themeid||
	AND feature = ||feature||
	AND featureid = ||featureid||
	UNION
	SELECT *, 4 as ranky
	FROM styles
	WHERE pageid = 0
	AND forced = 1
	AND feature = ||feature||
	AND featureid = 0
	ORDER BY ranky
||custom_theme_styles

saved_theme_styles||
	SELECT *, 1 as ranky
	FROM styles
	WHERE themeid = ||themeid||
	UNION
	SELECT *, 2 as ranky
	FROM styles
	WHERE themeid = ||themeid||
	AND feature = ||feature||
	AND featureid = 0
	UNION
	SELECT *, 3 as ranky
	FROM styles
	WHERE themeid = ||themeid||
	AND feature = ||feature||
	AND featureid = ||featureid||
	UNION
	SELECT *, 4 as ranky
	FROM styles
	WHERE pageid = 0
	AND forced = 1
	AND feature = ||feature||
	AND featureid = 0
	ORDER BY ranky
||saved_theme_styles

parent_theme_styles||
	SELECT *, 1 as ranky
	FROM styles
	WHERE pageid = 0
	AND themeid = ||themeid||
	AND feature <= ''
	UNION
	SELECT *, 2 as ranky
	FROM styles
	WHERE pageid = ||pageid||
	AND themeid = ||themeid||
	AND feature = ||feature||
	AND featureid = 0
	UNION
	SELECT *, 3 as ranky
	FROM styles
	WHERE pageid = ||pageid||
	AND feature = ||feature||
	AND featureid = ||featureid||
	AND themeid = ||themeid||
	UNION
	SELECT *, 4 as ranky
	FROM styles
	WHERE pageid = 0
	AND forced = 1
	AND feature = ||feature||
	AND featureid = 0
	AND themeid = ||themeid||
	ORDER BY ranky DESC
||parent_theme_styles

theme_selector_sql||
	SELECT *, 9999 as sort
	FROM themes
	||notsite{{
	UNION
	SELECT '', 'None', 1
	}}notsite||
	UNION
	SELECT '0', 'Custom', 2
	ORDER BY sort
||theme_selector_sql

delete_page_styles||
	DELETE
	FROM `styles`
	WHERE pageid = ||pageid||;
||delete_page_styles
