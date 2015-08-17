<?php
function download($url, $dir)
{
	$output = [];
	$return = 0;
	exec('git clone ' . escapeshellarg($url) . ' ' . escapeshellarg($dir) . ' 2>&1', $output, $return);
	if($return) return false;
	return true;
}

function process($dir)
{
	$oldcwd = getcwd();
	chdir($dir);

	$data = [];

	// Get a list of all commits
	$commits = [];
	exec('git log --pretty=format:"%H;%aI;%cI" --cherry-pick ', $commits);

	$data['commits'] = [];
	foreach ($commits as $commit) {
		$commit = explode(';', $commit);
		$data['commits'][$commit[0]] = $commit;
	}

	// Find all files in master branch which match the pattern
	$files = [];
	exec('grep -rIl --include \*.java "@Entity"', $files);
	exec('grep -rIl --include \*.java "@Embedded"', $files);
	exec('grep -rIl --include \*.java "com.googlecode.objectify"', $files);
	exec('grep -rIl --include \*.java "org.mongodb.morphia"', $files);
	$files = array_unique($files);

	foreach ($files as $file) {
		// Find all revisions for each matched file, there is at least the current one
		$file_commits = [];
		exec('git log --follow --name-status --pretty=format:%H ' . escapeshellarg($file), $file_commits);

		$data['files'][$file] = [];

		$current = [];
		$commits = [];
		foreach ($file_commits as $line) {
			if(!empty($line)) {
				if(preg_match('/^(?<hash>[a-f0-9]{40})$/', $line, $results)) {
					if(!empty($current)) {
						$commits[] = $current;
					}
					$current = ['id' => $results['hash']];
				}
				if(preg_match('/^((?<action>A|C|D|M|R|T|U|X|B)(?<probability>\d{3})?)(\s+(?<source>[^\s]+))?\s+(?<target>[^\s]+)$/', $line, $results)) {
					$current['action'] = $results['action'];
					$current['target'] = $results['target'];
					$current['probability'] = ($results['probability'] ?: '100') / 100.0;
					$current['source'] = $results['source'] ?: $results['target'];
				}
			}
		}
		if(!empty($current)) {
			$commits[] = $current;
		}

		foreach ($commits as $commit) {
			if(isset($commit['action']) && in_array($commit['action'], ['A','R','M'])) {
				// Dump content of each file at each revision
				$content = shell_exec('git show ' . escapeshellarg($commit['id']) . ':' . escapeshellarg($commit['target']));
				$data['files'][$file][$commit['id']] = [
					'source' => mb_convert_encoding($content, 'UTF-8'),
					'action' => $commit['action'],
					'probability' => $commit['probability'],
					'filename' => $commit['target']
				];
			}
		}
	}

	chdir($oldcwd);

	return $data;
}

function wrapper($repo)
{
	$dir = '/tmp/repos/' . base64_encode($repo);
	$lockfile = '/tmp/repos/' . base64_encode($repo) . '.lock';
	$datafile = 'repos/' . base64_encode($repo) . '.json';

	// Create lock dir if non existent yet
	if (!file_exists(dirname($lockfile))) {
		mkdir(dirname($lockfile), 0777, true);
	}

	// Create data dir if non existent yet
	if (!file_exists(dirname($datafile))) {
		mkdir(dirname($datafile), 0777, true);
	}

	// Attempt to lock
	$lock = fopen($lockfile, 'w');
	if (flock($lock, LOCK_EX | LOCK_NB, $blocked) && !file_exists($datafile)) {


		echo "Downloading $repo\n";
		@mkdir($dir, 0777, true);
		// Invalid credentials are only evaluated for private repositories
		// Specifying them enforces proper bailout with 403
		$url = 'https://foo:bar@github.com/' . $repo . '.git';
		if(download($url, $dir)) {

			echo "Processing $repo\n";
			$data = process($dir);
			$data['name'] = $repo;
			file_put_contents($datafile, json_encode($data));

		} else {
			echo "Download for $repo failed\n";
		}

		// Clean up
		exec('rm -rf ' . escapeshellarg($dir));

		// Release lock
		flock($lock, LOCK_UN);
		fclose($lock);
		unlink($lockfile);
	}
}

$repos = file('repos.txt');
foreach ($repos as $repo) {
	$repo = trim($repo);
	wrapper($repo);
}
