<?php

namespace webdna\commerce\filters\variables;

use Craft;
use webdna\commerce\filters\CommerceFilters;

/**
 * Filters variable
 */
class Filters
{
	public function filter($filteredQuery=[], $allQuery=[], $categories=[], $options=[])
	{
		return CommerceFilters::getInstance()->filters->filter($filteredQuery, $allQuery, $categories, $options);
	}
	
	public function queryParams($options=[])
	{
		return CommerceFilters::getInstance()->filters->queryParams($options);
	}
}
