<?php
require_once('config.php');

function download($url, $dir)
{
	$output = [];
	$return = 0;
	exec('timeout ' . config()['downloader']['timeout'] . 's git clone ' . escapeshellarg($url) . ' ' . escapeshellarg($dir) . ' 2>&1', $output, $return);
	if ($return) return false;
	return true;
}

function download_metadata($repo)
{
	$url = 'https://api.github.com/repos/' . $repo;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, config()['github']['useragent']);
	curl_setopt($ch, CURLOPT_USERPWD, config_user());
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$api_response = curl_exec($ch);
	curl_close($ch);

	$api_response = json_decode($api_response, true);

	return $api_response;
}

function process($dir)
{
	$oldcwd = getcwd();
	chdir($dir);

	$data = [];

	// Get a list of all commits
	$commits = [];
	exec('git log --cherry-pick --topo-order --pretty=format:"%H;%aI;%cI;%P" --cherry-pick ', $commits);

	$data['commits'] = [];
	foreach ($commits as $commit) {
		$commit = explode(';', $commit);
		$data['commits'][$commit[0]] = $commit;
	}

	// Find all files in master branch which match the pattern
	$files = [];
	$includes = implode(' ', array_map(function($include) {
		return '--include '.escapeshellarg($include);
	}, config()['downloader']['pattern']));
	$terms = implode(' ', array_map(function($term) {
		return '-e '.escapeshellarg($term);
	}, config()['downloader']['term']));
	exec('grep -rl '.$includes.' '.$terms, $files);
	$files = array_unique($files);

	foreach ($files as $file) {
		// Find all revisions for each matched file, there is at least the current one
		$file_commits = [];
		exec('git log --cherry-pick --topo-order --pretty=format:%H ' . escapeshellarg($file), $file_commits);

		$data['files'][$file] = [];

		$commits = [];
		foreach ($file_commits as $line) {
			if (preg_match('/^(?<hash>[a-f0-9]{40})/', $line, $results)) {
				$commits[] = $results['hash'];
			}
		}

		foreach ($commits as $commit) {
			// Dump content of each file at each revision
			$content = shell_exec('git show ' . escapeshellarg($commit) . ':' . escapeshellarg($file) . ' 2> /dev/null');

			// $content can be empty - we do expect that if a file has been deleted temporarily!
			$data['files'][$file][$commit] = [
				'commit' => $commit,
				'source' => mb_convert_encoding($content, 'UTF-8'),
				'filename' => $file,
			];
		}
	}

	chdir($oldcwd);

	return $data;
}

function wrapper($repo)
{
	$basedir = config()['downloader']['tmp_dir'];
	$dir = $basedir . DIRECTORY_SEPARATOR . base64_encode($repo);
	$lockfile = $basedir . DIRECTORY_SEPARATOR . base64_encode($repo) . '.lock';
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
		$data = ['name' => $repo];

		$data['github'] = download_metadata($repo);

		$skip = false;
		if (!isset($data['github']['private']) || $data['github']['private'] != false) {
			echo "$repo is private\n";
			$skip = true;
		}
		if (isset($data['github']['size'])) {
			if ($data['github']['size'] * 1024 > config()['downloader']['max_size']) {
				echo "$repo is too large\n";
				$skip = true;
			}
		} else {
			echo "$repo not found\n";
			$skip = true;
		}
		if (!$skip) {
			echo "Downloading $repo\n";
			@mkdir($dir, 0777, true);
			// Credentials are only evaluated for private repositories
			// Specifying them enforces proper bailout with 403
			// Omitting them causes git to hang
			$url = 'https://' . config_user() . '@github.com/' . $repo . '.git';
			if (download($url, $dir)) {

				echo "Processing $repo\n";
				$data = array_merge($data, process($dir));

			} else {
				echo "Download for $repo failed\n";
				$data['failed'] = true;
			}
		} else {
			echo "Skippping $repo\n";
			$data['skipped'] = true;
		}

		file_put_contents($datafile, json_encode($data));

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
