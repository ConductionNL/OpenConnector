<?php

namespace OCA\OpenConnector\Service;

use Adbar\Dot;
use OCA\OpenConnector\Service\AuthenticationService;
use OCA\OpenConnector\Service\MappingService;
use OCA\OpenConnector\Db\Source;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use OCA\OpenConnector\Db\CallLog;
use OCA\OpenConnector\Db\CallLogMapper;

class CallService
{
	private $callLogMapper;

	/**
	 * The constructor sets al needed variables.
	 *
	 * @param AuthenticationService  $authenticationService The authentication service
	 * @param MappingService         $mappingService        The mapping service
	 */
	public function __construct(CallLogMapper $callLogMapper)
	{
		$this->client                = new Client([]);
		$this->callLogMapper = $callLogMapper;
	}

	/**
	 * Calls a source according to given configuration.
	 *
	 * @param Source $source             The source to call.
	 * @param string $endpoint           The endpoint on the source to call.
	 * @param string $method             The method on which to call the source.
	 * @param array  $config             The additional configuration to call the source.
	 * @param bool   $asynchronous       Whether or not to call the source asynchronously.
	 * @param bool   $createCertificates Whether or not to create certificates for this source.
	 *
	 * @throws Exception
	 *
	 * @return Response
	 */
	public function call(
		Source $source,
		string $endpoint = '',
		string $method = 'GET',
		array $config = [],
		bool $asynchronous = false,
		bool $createCertificates = true,
		bool $overruleAuth = false
	): CallLog
	{
		$this->source = $source;

		if ($this->source->getIsEnabled() === null || $this->source->getIsEnabled() === false) {
			// Create and save the CallLog
			$callLog = new CallLog();
			$callLog->setSourceId($this->source->getId());
			$callLog->setStatusCode(409);
			$callLog->setStatusMessage("This source is not enabled");
			$callLog->setCreatedAt(new \DateTime());
			$callLog->setUpdatedAt(new \DateTime());

			$this->callLogMapper->insert($callLog);

			return $callLog;
		}

		if (empty($this->source->getLocation()) === true) {
			// Create and save the CallLog
			$callLog = new CallLog();
			$callLog->setSourceId($this->source->getId());
			$callLog->setStatusCode(409);
			$callLog->setStatusMessage("This source has no location");
			$callLog->setCreatedAt(new \DateTime());
			$callLog->setUpdatedAt(new \DateTime());

			$this->callLogMapper->insert($callLog);

			return $callLog;
		}

		// Check if the source has a configuration and merge it with the given config
		if (empty($this->source->getConfiguration()) === false) {
			$config = array_merge_recursive($config, $this->applyConfigDot($this->source->getConfiguration()));
		}

		// Check if the config has a Content-Type header and overwrite it if it does
		if (isset($config['headers']['Content-Type']) === true) {
			$overwriteContentType = $config['headers']['Content-Type'];
		}

		// decapiitilized fall back for content-type
		if (isset($config['headers']['content-type']) === true) {
			$overwriteContentType = $config['headers']['content-type'];
		}

		// Make sure we do not have an array of accept headers but just one value
		if (isset($config['headers']['accept']) === true && is_array($config['headers']['accept']) === true) {
			$config['headers']['accept'] = $config['headers']['accept'][0];
		}


		// Check if the config has a headers array and create it if it doesn't
		if (isset($config['headers']) === false) {
			$config['headers'] = [];
		}

		// We want to suprres guzzle exceptions and return the response instead
		$config['http_errors'] = false;

		// Set the URL to call and add an endpoint if needed
		$url = $this->source->getLocation().$endpoint;

		// Set authentication if needed. @todo: create  the authentication service
		//$createCertificates && $this->getCertificate($config);

		// Let's log the call.
		$this->source->setLastCall(new \DateTime());
		// @todo: save the source
		// Let's make the call.
		$time_start = microtime(true);
		try {
			if ($asynchronous === false) {
			   $response = $this->client->request($method, $url, $config);
			} else {
				return $this->client->requestAsync($method, $url, $config);
			}
		} catch (GuzzleHttp\Exception\BadResponseException $e) {
			$response = $e->getResponse();
		}

		$time_end = microtime(true);

		// Let create the data array
		$data = [
			'request' => [
				'url' => $url,
				'method' => $method,
				...$config
			],
			'response' => [
				'statusCode' => $response->getStatusCode(),
				'statusMessage' => $response->getReasonPhrase(),
				'responseTime' => ( $time_end - $time_start ) * 1000,
				'size' => $response->getBody()->getSize(),
				'remoteIp' => $response->getHeaderLine('X-Real-IP') ?: $response->getHeaderLine('X-Forwarded-For') ?: null,
				'headers' => $response->getHeaders(),
				'body' => $response->getBody()->getContents(),
			]
		];

		// Create and save the CallLog
		$callLog = new CallLog();
		$callLog->setSourceId($this->source->getId());
		$callLog->setStatusCode($data['response']['statusCode']);
		$callLog->setStatusMessage($data['response']['statusMessage']);
		$callLog->setRequest($data['request']);
		$callLog->setResponse($data['response']);
		$callLog->setCreatedAt(new \DateTime());

		$this->callLogMapper->insert($callLog);

		return $callLog;
	}

	/**
	 * Uses Adbar Dot to place the values of keys with a dot in it in the $config array
	 * to the correct position in the then updated multidimensional $config array.
	 *
	 * @param array $config The config array.
	 *
	 * @return array The updated config array.
	 */
	private function applyConfigDot(array $config): array
	{
		$dotConfig = new Dot($config);
		$unsetKeys = [];

		// Check if there are keys containing a dot we want to map to a different position in the $config array.
		foreach ($config as $key => $value) {
			if (str_contains($key, '.')) {
				$dotConfig->set($key, $value);
				$unsetKeys[] = $key;
			}
		}

		// Remove the old keys containing a dot that we mapped to a different position in the $config array.
		$config = $dotConfig->all();
		foreach ($unsetKeys as $key) {
			unset($config[$key]);
		}

		return $config;
	}
}
