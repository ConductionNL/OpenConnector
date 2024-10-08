<?php

namespace OCA\OpenConnector\Service;


use GuzzleHttp\Client;
use OAuthException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class AuthenticationService
{
	public const REQUIRED_PARAMETERS_CLIENT_CREDENTIALS = [
		'grant_type',
		'scope',
		'authentication',
		'client_id',
		'client_secret',
	];
	public const REQUIRED_PARAMETERS_PASSWORD = [
		'grant_type',
		'scope',
		'authentication',
		'username',
		'password',
	];

	/**
	 * Create call options for OAuth with Client Credentials
	 *
	 * @param array $configuration Configuration array for authentication.
	 *
	 * @return array|array[] The call options for OAuth with Client Credentials.
	 */
	private function createClientCredentialConfig(array $configuration): array
	{
		if(($diff = array_diff(self::REQUIRED_PARAMETERS_CLIENT_CREDENTIALS, array_keys(array: $configuration))) !== []) {
			throw new BadRequestException(message: 'Some required parameters are not set: ['.implode(separator: ',', array: $diff).']');
		}

		$callConfig = [
			'form_params' => [
				'grant_type' => $configuration['grant_type'],
				'scope'		 => $configuration['scope'],
			]
		];

		if($configuration['authentication'] === 'body') {
			$callConfig['form_params']['client_id']     = $configuration['client_id'];
			$callConfig['form_params']['client_secret'] = $configuration['client_secret'];
		} else if ($configuration['authentication'] === 'basic_auth') {
			$callConfig['auth'] = [
				'username' => $configuration['client_id'],
				'password' => $configuration['client_secret'],
			];
		}
		//@todo: check for off-cases, i.e. camelCase (not according to OAuth standards)

		return $callConfig;
	}

	/**
	 * Create call options for OAuth with Password Credentials
	 *
	 * @param array $configuration Configuration array for authentication.
	 *
	 * @return array|array[] The call options for OAuth with Password Credentials
	 */
	private function createPasswordConfig(array $configuration): array
	{

		if(($diff = array_diff(self::REQUIRED_PARAMETERS_PASSWORD, array_keys(array: $configuration))) !== []) {
			throw new BadRequestException(message: 'Some required parameters are not set: ['.implode(separator: ',', array: $diff).']');
		}

		$callConfig = [
			'form_params' => [
				'grant_type' => $configuration['grant_type'],
				'scope'		 => $configuration['scope'],
			]
		];

		if($configuration['authentication'] === 'body') {
			$callConfig['form_params']['username'] = $configuration['username'];
			$callConfig['form_params']['password'] = $configuration['password'];
		} else if ($configuration['authentication'] === 'basic_auth') {
			$callConfig['auth'] = [
				'username' => $configuration['username'],
				'password' => $configuration['password'],
			];
		}


		return $callConfig;
	}

	/**
	 * Requests an OAuth Access Token with predefined configuration
	 *
	 * @param string $endpoint	   The OAuth token endpoint.
	 * @param array $configuration The configuration for the OAuth call.

	 * @return string The resulting access token
	 *
	 * @throws BadRequestException					 Thrown if the configuration is not compatible with OAuth.
	 * @throws \GuzzleHttp\Exception\GuzzleException Thrown if the token endpoint does not respond with an access token.
	 * @todo Convert GuzzleException to another error.
	 */
    public function fetchOAuthTokens (string $endpoint, array $configuration): string
	{
		if (isset($configuration['grant_type']) === false) {
			throw new BadRequestException(message: 'Grant type not set, cannot request token');
		}

		switch ($configuration['grant_type'])
		{
			case 'client_credentials':
				$callConfig = $this->createClientCredentialConfig(configuration: $configuration);
				break;
			case 'password':
				$callConfig = $this->createPasswordConfig(configuration: $configuration);
				break;
			default:
				throw new BadRequestException(message: 'Grant type not supported');

		}
		//@todo: custom config

		$client = new Client();
		$response = $client->post(uri: $endpoint, options: $callConfig);

		$result = json_decode(json: $response->getBody()->getContents(), associative: true);

		if(isset($configuration['tokenLocation']) === true) {
			return $result[$configuration['tokenLocation']];
		}

		return $result['access_token'];
	}

}
