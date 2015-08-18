<?php
define('SLEEP_DURATION', 60);
require_once('config.php');

function load($url) {
	$filename = 'cache/'.sha1($url).'.html';
		
	if(file_exists($filename) && filesize($filename) > 10000) {
		//echo "Cache hit:  $filename\n";
		return file_get_contents($filename);
	} else {
		//echo "Cache miss: $filename\n";
	}
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, config()['github']['useragent']);
	curl_setopt($ch, CURLOPT_USERPWD, config_user());
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'X-PJAX: true',
		'X-PJAX-Container: #container',
		'X-Requested-With: XMLHttpRequest'
	]);
	$text = curl_exec($ch);
	curl_close($ch);
		
	if(strlen($text) < 10000) {
		echo "Error: Rate limit exceeded! Retry in ".SLEEP_DURATION." seconds.\n";
		sleep(SLEEP_DURATION);
		$text = load($url);
	} else {
		file_put_contents($filename, $text);
	}
	
	return $text;
}

function countResults($text) {
	preg_match('/(found|Showing) (\d{1,3}(,\d{3})*)( available)? code results?/', $text, $matches);
	if(isset($matches[2])) {
		return (int)str_replace(',','',$matches[2]);
	} else {
		echo "Unexpected format, could not count results!\n";
		return 0;
	}
}

function countPages($text) {
	$xml = new DOMDocument();

	@$xml->loadHTML($text);
	
	$xpath = new DOMXpath($xml);
	
	$pagelinks = $xpath->query('//div[@class="pagination"]/a');
	
	$max = 0;
	
	for($i = 0; $i < $pagelinks->length; $i++) {
		$content = $pagelinks->item($i)->textContent;
		if((int)$content) {
			$max = (int)$content;
		}
	}
	return $max;
}

function extraxtRepos($text) {
	$xml = new DOMDocument();

	@$xml->loadHTML($text);
	
	$xpath = new DOMXpath($xml);
	
	$repos = $xpath->query('//div[@class="code-list"]//p[@class="title"]/a[1]');
	
	for($i = 0; $i < $repos->length; $i++) {
		$content = $repos->item($i)->textContent;
		saveRepo($content);
	}
}

$repos = [];
$counter = 0;
function saveRepo($name) {
	//TODO Stub which does no real work. Doesn't matter, there's a cache in place
	global $repos, $counter;
	$counter++;
	if(!array_key_exists($name, $repos)) {
		$repos[$name] = $name;
		file_put_contents('repos.txt', implode("\r\n",$repos));
		//echo sizeof($repos)."/$counter\n";
	}
}

function search($keyword, $tautologies) {
	echo "Searching for '$keyword'\n";
	$page = 1;
	$text = load('https://github.com/search?type=Code&p='.$page.'&q='.urlencode($keyword));
	
	$count = countResults($text);
	echo "Found $count results for '$keyword'\n";
	if($count > 1000) {
		echo "Too many results, recursing\n";
		if(sizeof($tautologies)) {
			$word = array_shift($tautologies);
			search($keyword . ' ' . $word, $tautologies);
			search($keyword . ' NOT ' . $word, $tautologies);
		} else {
			echo "Error: Not enough auxilary words left for '$keyword'\n";
			exit(1);
		}
	} elseif($count) {
		$pages = countPages($text);
		
		extraxtRepos($text);
		while(++$page <= $pages) {
			echo "Fetching page $page/$pages\n";
			$text = load('https://github.com/search?type=Code&p='.$page.'&q='.urlencode($keyword));
			extraxtRepos($text);
		}
	} else {
		echo "Error: No results found\n";
		exit(-1);
	}
}

function sortAuxiliary($keyword, $auxiliary) {
	// Benchmark auxiliary terms for frequency
	$results = [];
	foreach($auxiliary as $term) {
		$text = load('https://github.com/search?type=Code&q='.urlencode($keyword.' '.$term));
		$count = countResults($text);
		$results[$term] = $count;
	}
	// Prefer high frequency terms first to intentionally unbalance the search tree
	arsort($results);
	return array_keys($results);
}

$auxiliary = config()['crawler']['auxillary'];

foreach(config()['crawler']['term'] as $term) {
	search($term, sortAuxiliary($term, $auxiliary));
}
