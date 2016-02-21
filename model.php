<?php

class Project
{
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
	 * Total number of revisions.
	 */
	var $revisions = 0;
	/**
	 * @var bool
	 * Contains at least a single objectify related Java source.
	 */
	var $isObjectify = false;
	/**
	 * @var bool
	 * Contains at least a single Morphia related Java source.
	 */
	var $isMorphia = false;
}

class Schema
{
	/**
	 * @var string
	 */
	var $filename = '';
	/**
	 * @var string
	 * Topmost revision for this schema.
	 */
	var $commit = '';
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
	var $containsMigration = false;
	/**
	 * @var bool
	 */
	var $containsEmbedded = false;
	/**
	 * @var string[]
	 */
	var $annotations = [];
	/**
	 * @var AttributeDiff[]
	 */
	var $attributeHistory = [];
}

class AttributeDiff
{
	/**
	 * @var string
	 */
	var $commit = '';
	/**
	 * @var Attribute[]
	 */
	var $added = [];
	/**
	 * @var Attribute[]
	 */
	var $removed = [];
	/**
	 * @var Attribute[]
	 */
	var $modified = [];
}

class Attribute
{
	/**
	 * @var string
	 */
	var $name = '';
	/**
	 * @var string
	 */
	var $type = '';
	/**
	 * @var string[]
	 */
	var $annnotations = [];
	/**
	 * @var string
	 */
	var $visibility = '';
	/**
	 * @var null|string
	 */
	var $default = null;
}
