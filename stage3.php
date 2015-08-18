<?php
require_once('config.php');

class Project {
	/**
	 * @var string
	 */
	var $name = '';
	/**
	 * @var Schema[]
	 */
	var $schemas = [];
	/**
	 * @var int
	 */
	var $revisions = 0;
	/**
	 * @var bool
	 */
	var $isObjectify = false;
	/**
	 * @var bool
	 */
	var $isMorphia = false;
}

class Schema {
	/**
	 * @var string
	 */
	var $filename = '';
	/**
	 * @var int
	 */
	var $revisions = 0;
	/**
	 * @var bool
	 */
	var $isObjectify = false;
	/**
	 * @var bool
	 */
	var $isMorphia = false;
	/**
	 * @var bool
	 */
	var $isEntity = false;
	/**
	 * @var bool
	 */
	var $containsLifecycleEvents = false;
	/**
	 * @var bool
	 */
	var $containsEmbedded = false;
}

//TODO Track schema evolutions, which fields exist in a certain revision?

$dir = 'repos';
$handle = opendir($dir);

/**
 * @var Project[]
 */
$projects = [];

while (false !== ($entry = readdir($handle))) {
	$filename = $dir . DIRECTORY_SEPARATOR . $entry;
	if (is_file($filename)) {
		echo "$filename\n";
		$content = json_decode(file_get_contents($filename), true);

		$project = new Project();
		$project->name = $content['name'];

		if(isset($content['commits'])) {
			$project->revisions = sizeof($content['commits']);
		}

		if(isset($content['files'])) {

			foreach($content['files'] as $name => &$file) {

				$schema = new Schema();

				$schema->filename = $name;

				$schema->revisions = sizeof($file);

				$first = current($file);

				if(preg_match('/@(?:AlsoLoad|OnLoad|OnSave|NotSaved)/', $first['source'])) {
					$schema->containsLifecycleEvents = true;
				}

				//TODO This is inaccurate - with objectify any non-"native" class acts as embedded
				if(strpos($first['source'], '@Embedded') !== false) {
					$schema->containsEmbedded = true;
				}

				if(strpos($first['source'], '@Entity') !== false) {
					$schema->isEntity = true;
				}

				if(strpos($first['source'], 'objectify') !== false) {
					$schema->isObjectify = true;
					$project->isObjectify = true;
				}
				if(strpos($first['source'], 'morphia') !== false) {
					$schema->isMorphia = true;
					$project->isMorphia = true;
				}

				//TODO Add more predicates, especially regarding actual evolutions

				$project->schemas[] = $schema;

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
