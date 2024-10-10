<?php

namespace OCA\OpenConnector\Service;

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
use Symfony\Component\Uid\Uuid;

class CallService
{
	private CallLogMapper $callLogMapper;

	/**
	 * The constructor sets al needed variables.
	 *
	 * @param CallLogMapper $callLogMapper
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
			$callLog->setUuid(Uuid::v4());
			$callLog->setSourceId($this->source->getId());
			$callLog->setStatusCode(409);
			$callLog->setStatusMessage("This source is not enabled");
			$callLog->setCreated(new \DateTime());
			$callLog->setUpdated(new \DateTime());

			$this->callLogMapper->insert($callLog);

			return $callLog;
		}

		if (empty($this->source->getLocation()) === true) {
			// Create and save the CallLog
			$callLog = new CallLog();
			$callLog->setUuid(Uuid::v4());
			$callLog->setSourceId($this->source->getId());
			$callLog->setStatusCode(409);
			$callLog->setStatusMessage("This source has no location");
			$callLog->setCreated(new \DateTime());
			$callLog->setUpdated(new \DateTime());

			$this->callLogMapper->insert($callLog);

			return $callLog;
		}

		// Check if the source has a configuration and merge it with the given config
		if (empty($this->source->getConfiguration()) === false) {
//			echo json_encode($this->source->getConfiguration());
			$config = array_merge_recursive($config, $this->source->getConfiguration());
//			echo json_encode($config);
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
		$callLog->setUuid(Uuid::v4());
		$callLog->setSourceId($this->source->getId());
		$callLog->setStatusCode($data['response']['statusCode']);
		$callLog->setStatusMessage($data['response']['statusMessage']);
		$callLog->setRequest($data['request']);
		$callLog->setResponse($data['response']);
		$callLog->setCreated(new \DateTime());

		$this->callLogMapper->insert($callLog);

		return $callLog;
	}
}
