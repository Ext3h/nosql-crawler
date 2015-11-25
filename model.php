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

class Schema
{
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
	var $containsMigration = false;
	/**
	 * @var bool
	 */
	var $containsEmbedded = false;
	/**
	 * @var string[]
	 */
	var $annotations = [];
}
