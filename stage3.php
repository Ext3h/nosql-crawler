<?php
require_once('config.php');
require_once('model.php');

//TODO Track schema evolutions, which fields exist in a certain revision?

$dir = 'repos';
$handle = opendir($dir);

/**
 * @var $projects Project[]
 */
$projects = [];

while (false !== ($entry = readdir($handle))) {
	$filename = $dir . DIRECTORY_SEPARATOR . $entry;
	if (is_file($filename)) {
		echo "$filename\n";
		$content = json_decode(file_get_contents($filename), true);

		$project = new Project();
		$project->name = $content['name'];

		$revisions = [];

		if(isset($content['commits'])) {
			$revisions = $content['commits'];
			$project->revisions = sizeof($content['commits']);
		}

		if(isset($content['files'])) {

			foreach($content['files'] as $name => &$file) {

				$schema = new Schema();

				$schema->filename = $name;

				$schema->revisions = sizeof($file);

				// Get most recent version of the file, as defined by the order of commits
				uksort($file, function($a, $b) use (&$revisions) {
					return strcmp($revisions[$a][2], $revisions[$b][2]);
				});

				$recent = end($file);

				$source = $recent['source'];

				// Strip comments, this is not exactly accurate, but close enough for Java
				$source = preg_replace('!/\*.*?\*/!s', '', $source);
				$source = preg_replace('!//.*[ \t]*[\r\n]!', '', $source);

				preg_match_all('/@([a-zA-Z]+)/', $source, $match);
				$schema->annotations = array_count_values($match[1]);

				$annotations = array_keys($schema->annotations);

				if(sizeof(array_intersect($annotations, [
					'OnLoad',
					'OnSave',
					'PrePersist',
					'PreSave',
					'PostPersist',
					'PreLoad',
					'PostLoad',
				]))) {
					$schema->containsLifecycleEvents = true;
				}

				if(sizeof(array_intersect($annotations, [
						'AlsoLoad',
						'NotSaved',
						'IgnoreLoad',
						'IgnoreSave',
				]))) {
					$schema->containsMigration = true;
				}

				//TODO This is inaccurate - with objectify any non-"native" class acts as embedded
				if(sizeof(array_intersect($annotations, [
						'Embedded',
				]))) {
					$schema->containsEmbedded = true;
				}

				if(sizeof(array_intersect($annotations, [
						'Entity',
				]))) {
					$schema->isEntity = true;
				}

				if(strpos($source, 'objectify') !== false) {
					$schema->isObjectify = true;
					$project->isObjectify = true;
				}
				if(strpos($source, 'morphia') !== false) {
					$schema->isMorphia = true;
					$project->isMorphia = true;
				}

				//TODO Add more predicates, especially regarding actual evolutions

				$project->schemas[] = $schema;

				unset($recent);
				unset($source);
				unset($schema);
				unset($file);
			}
		}
		$projects[] = $project;
		unset($project);
	}
}

file_put_contents('analysis.json', json_encode($projects));

$morphia = array_values(array_filter($projects, function(Project &$p) {
	return $p->isMorphia == true;
}));
file_put_contents('analysis_morphia.json', json_encode($morphia));


$objectify = array_values(array_filter($projects, function(Project &$p) {
	return $p->isObjectify == true;
}));
file_put_contents('analysis_objectify.json', json_encode($objectify));
