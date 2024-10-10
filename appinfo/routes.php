<?php

return [
	'resources' => [
		'Sources' => ['url' => 'api/sources'],
		'Mappings' => ['url' => 'api/mappings'],
		'Jobs' => ['url' => 'api/jobs'],
		'Synchronizations' => ['url' => 'api/synchronizations'],
		'Endpoints' => ['url' => 'api/endpoints'],
		'Consumers' => ['url' => 'api/consumers'],
	],
	'routes' => [
		['name' => 'dashboard#page', 'url' => '/', 'verb' => 'GET'],
		['name' => 'sources#test', 'url' => '/api/source-test/{id}', 'verb' => 'POST'],
		['name' => 'sources#logs', 'url' => '/api/sources-logs/{id}', 'verb' => 'GET'],
		['name' => 'jobs#run', 'url' => '/api/jobs-test/{id}', 'verb' => 'POST'],
		['name' => 'jobs#logs', 'url' => '/api/jobs-logs/{id}', 'verb' => 'GET'],
		['name' => 'endpoints#test', 'url' => '/api/endpoints-test/{id}', 'verb' => 'POST'],
		['name' => 'endpoints#logs', 'url' => '/api/endpoints-logs/{id}', 'verb' => 'GET'],
		['name' => 'synchronizations#contracts', 'url' => '/api/synchronizations-contracts/{id}', 'verb' => 'GET'],
		['name' => 'synchronizations#logs', 'url' => '/api/synchronizations-logs/{id}', 'verb' => 'GET'],
		// Running endpoints
		['name' => 'endpoints#run', 'url' => '/api/v1/{endpoint}', 'verb' => 'GET'],
		['name' => 'endpoints#run', 'url' => '/api/v1/{endpoint}', 'verb' => 'PUT'],
		['name' => 'endpoints#run', 'url' => '/api/v1/{endpoint}', 'verb' => 'POST'],
		['name' => 'endpoints#run', 'url' => '/api/v1/{endpoint}', 'verb' => 'DELETE'],
	],
];
