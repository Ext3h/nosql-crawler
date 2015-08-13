<?php

function parse_objectify_entity($source) {
	return ['properties' => [], 'migrations' => []];
}

function parse_morphia_entity($source) {
	return ['properties' => [], 'migrations' => []];
}

function parse($source) {
	if(true) {
		return parse_objectify_entity($source);
	}
	if(true) {
		return parse_morphia_entity($source);
	}
	return false;
}

$dir = 'repos';
$handle = opendir($dir);

while (false !== ($entry = readdir($handle))) {
	$filename = $dir.DIRECTORY_SEPARATOR.$entry;
	if(is_file($filename)) {
		echo "$filename\n";
		$content = json_decode(file_get_contents($filename));
		print_r($content);
		exit();
	}
}
