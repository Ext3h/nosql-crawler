<?php
require_once('config.php');
require_once('model.php');

/**
 * @param string $source
 * @return Attribute[]
 */
function extract_attributes($source)
{
	// Strip block comments
	$source = preg_replace('#/\*.*?\*/#s', '', $source);
	// Strip inline comments
	$source = preg_replace('#//.*[ \t]*[\r\n]#', '', $source);
	// Collapse strings
	$source = preg_replace('#"([^"]|[\\\]")*?(?![\\\])"#s', '""', $source);

	// Isolate class content, assuming only top level class
	if(!preg_match('#class[^{]*{(.*)}#s', $source, $results)) {
		// No class to be found, probably because there is no legit source code
		return [];
	}
	$source = $results[1];

	// Eliminate method heads
	$source = preg_replace('#(?<=;|})[^;{}]*(?={)#', '', $source);

	// Eliminate method bodies
	do {
		$source = preg_replace('#{[^{}]*}#s', '', $source, -1, $count);
	} while ($count);

	// Eliminate method calls
	do {
		$source = preg_replace('#\([^()]*\)#s', '', $source, -1, $count);
	} while ($count);

	// Eliminate templates
	do {
		$source = preg_replace('#<[^<>]*>#s', '', $source, -1, $count);
	} while ($count);

	// Parse attributes
	preg_match_all('#(?<annotation>(?:@[[:alnum:]]+\s*?)+)?\s*' // Match all annotations
		. '(?<visibility>public|private|protected)?\s*' // Visbility modifiers
		. '(?<type>[[:alnum:]]*)\s*' // Type without templates
		. '(?<name>[[:alnum:]]*)\s*' // Variable name
		. '(?:=\s*(?<default>[^;]+?))?\s*' // Defaults
		. ';#', $source, $match, PREG_SET_ORDER);

	$output = [];

	foreach ($match as &$var) {
		$attribute = new Attribute();

		$attribute->name = $var['name'];
		$attribute->type = $var['type'];

		if(isset($var['visibility'])) {
			$attribute->visibility = $var['visibility'];
		} else {
			$attribute->visibility = 'default';
		}

		// Expand annotations
		if (isset($var['annotation'])) {
			preg_match_all('#@[[:alnum:]]+#', $var['annotation'], $match2);
			$var['annotation'] = $match2[0];
			sort($var['annotation']);

			$attribute->annnotations = $var['annotation'];
		}

		if(isset($var['default'])) {
			$attribute->default = $var['default'];
		}

		$output[$attribute->name] = $attribute;
	}

	return $output;
}

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
				$schema->commit = key($file);

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
						'Embed',
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


				// Trace back the main path in schema history.
				// We only trace back the main line of the graph.
				// All evolutions are therefor still counted, as part of the merge commits.
				// Actual development activity could have been hidden by this.
				$trace = [];
				$currentCommit = $recent['commit'];
				while($currentCommit) {
					// The revision is know, so mark it for analysis
					if(isset($file[$currentCommit])) {
						$trace[] = $currentCommit;
					}
					// Parent commit
					if(isset($revisions[$currentCommit][3]) && !empty($revisions[$currentCommit][3])) {
						$currentCommit = $revisions[$currentCommit][3];
					} else {
						$currentCommit = false;
					}
				}
				// Follow it in reverse order, from the start to the end
				$trace = array_reverse($trace);

				/**
				 * @var Attribute[] $attributes
				 */
				$attributes = [];
				/**
				 * @var AttributeDiff[] $history
				 */
				$history = [];
				foreach($trace as $revision) {
					$diff = new AttributeDiff();
					$diff->commit = $revision;

					$source = $file[$revision]['source'];
					$currentAttributes = extract_attributes($source);

					foreach($currentAttributes as $attribute) {
						if(!array_key_exists($attribute->name, $attributes)) {
							$attributes[$attribute->name] = $attribute;
							$diff->added[] = $attribute;
						} else if($attributes[$attribute->name] <> $attribute) {
							$attributes[$attribute->name] = $attribute;
							$diff->modified[] = $attribute;
						}
					}

					foreach($attributes as $attribute) {
						if(!array_key_exists($attribute->name, $currentAttributes)) {
							unset($attributes[$attribute->name]);
							$diff->removed[] = $attribute;
						}
					}

					$history[] = $diff;
				}

				$schema->attributeHistory = $history;


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
