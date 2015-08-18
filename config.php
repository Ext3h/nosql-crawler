<?php
function config()
{
	static $conf = null;
	if (!$conf) {
		$conf = parse_ini_file('config.ini', true);
	}
	return $conf;
}

function config_user()
{
	$conf = config();
	return urlencode($conf['github']['username']) . ':' . urlencode($conf['github']['password']);
}
