<?php

/**
 * Integriq ConnectionConfigReader.
 *
 * Reads the declaring app's settings for the connection status resolver
 * (hydra umbrella design D4 rules 3 and 5, amended by D12): whether every
 * required key is filled, and the value that selects an adapter.
 *
 * The adapter value is the config value at `adapter.configKey`, or, with
 * `adapter.jsonPath`, the scalar at that dot path inside the JSON object the
 * key holds. A missing path, invalid JSON or a non-scalar value reads as the
 * empty string. `adapter.simulatedValues` defaults to `[""]`, so a declaration
 * without it keeps its old meaning: an empty key means a mock answers.
 *
 * @category Service
 * @package  OCA\Integriq\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @version GIT: <git_id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
 */

declare(strict_types=1);

namespace OCA\Integriq\Service;

use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;

/**
 * Reads another app's config for the D4 rules.
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
 */
class ConnectionConfigReader {

	/**
	 * The adapter values that mean a mock answers, when a declaration names none.
	 *
	 * @var string[]
	 */
	public const DEFAULT_SIMULATED_VALUES = [''];

	/**
	 * Constructor.
	 *
	 * @param IAppConfig $appConfig Reads the declaring app's settings.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Whether the adapter value is one of the values that mean a mock answers.
	 *
	 * @param string $app The declaring app id.
	 * @param array<string|int,mixed> $adapter The declared adapter object, with a non-empty `configKey`.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-provider-name-selects-simulated
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-json-path-reads-inside-a-settings-blob
	 */
	public function isSimulated(string $app, array $adapter): bool {
		$value = $this->adapterValue(app: $app, configKey: (string)($adapter['configKey'] ?? ''), jsonPath: $adapter['jsonPath'] ?? null);

		$simulatedValues = $adapter['simulatedValues'] ?? null;
		if (is_array($simulatedValues) === false) {
			$simulatedValues = self::DEFAULT_SIMULATED_VALUES;
		}

		$needle = mb_strtolower($value);
		foreach ($simulatedValues as $candidate) {
			if (is_string($candidate) === true && mb_strtolower(trim($candidate)) === $needle) {
				return true;
			}
		}

		return false;
	}//end isSimulated()

	/**
	 * Whether every key holds a non-empty value in the app's config.
	 *
	 * @param string $app The declaring app id.
	 * @param array<int|string,mixed> $keys The required keys.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-saved-settings-show-configured
	 */
	public function allFilled(string $app, array $keys): bool {
		foreach ($keys as $key) {
			if (is_string($key) === false || $this->read(app: $app, key: $key) === '') {
				return false;
			}
		}

		return true;
	}//end allFilled()

	/**
	 * The value that selects the adapter, trimmed.
	 *
	 * @param string $app The declaring app id.
	 * @param string $configKey The config key.
	 * @param mixed $jsonPath The declared dot path, if any.
	 *
	 * @return string
	 */
	private function adapterValue(string $app, string $configKey, mixed $jsonPath): string {
		if (is_string($jsonPath) === false || $jsonPath === '') {
			return $this->read(app: $app, key: $configKey);
		}

		return $this->scalarAtPath(tree: $this->readObject(app: $app, key: $configKey), path: $jsonPath);
	}//end adapterValue()

	/**
	 * Read one value from another app's config.
	 *
	 * `lazy: true` makes Nextcloud return lazy and non-lazy values alike, so a
	 * key the app stored as lazy is not mistaken for an empty one. A value
	 * stored under another type makes getValueString() throw; such a key does
	 * hold a value, so it counts as filled.
	 *
	 * @param string $app The declaring app id.
	 * @param string $key The config key.
	 *
	 * @return string The trimmed value, or '' when absent.
	 */
	private function read(string $app, string $key): string {
		if ($app === '' || $key === '') {
			return '';
		}

		try {
			return trim($this->appConfig->getValueString($app, $key, '', true));
		} catch (AppConfigTypeConflictException $e) {
			return 'typed';
		}
	}//end read()

	/**
	 * Read a config value holding a JSON object, decoded.
	 *
	 * A value Nextcloud stores under the array type makes getValueString()
	 * throw; getValueArray() reads that one.
	 *
	 * @param string $app The declaring app id.
	 * @param string $key The config key.
	 *
	 * @return mixed The decoded value, or null when it is absent or not JSON.
	 */
	private function readObject(string $app, string $key): mixed {
		if ($app === '' || $key === '') {
			return null;
		}

		try {
			return json_decode($this->appConfig->getValueString($app, $key, '', true), true);
		} catch (AppConfigTypeConflictException $e) {
			return $this->readArray(app: $app, key: $key);
		}
	}//end readObject()

	/**
	 * Read a config value stored under the array type.
	 *
	 * @param string $app The declaring app id.
	 * @param string $key The config key.
	 *
	 * @return array<mixed>|null The value, or null when it is stored under another type.
	 */
	private function readArray(string $app, string $key): ?array {
		try {
			return $this->appConfig->getValueArray($app, $key, [], true);
		} catch (AppConfigTypeConflictException $e) {
			return null;
		}
	}//end readArray()

	/**
	 * The scalar at a dot path, as a trimmed string.
	 *
	 * Booleans read as `true` or `false`, so a declaration can list them.
	 *
	 * @param mixed $tree The decoded JSON.
	 * @param string $path The dot path, such as `chat.provider`.
	 *
	 * @return string The value, or '' when the path is missing or the value is not a scalar.
	 */
	private function scalarAtPath(mixed $tree, string $path): string {
		foreach (explode('.', $path) as $segment) {
			if (is_array($tree) === false || array_key_exists($segment, $tree) === false) {
				return '';
			}

			$tree = $tree[$segment];
		}

		return match (true) {
			$tree === true => 'true',
			$tree === false => 'false',
			is_string($tree), is_int($tree), is_float($tree) => trim((string)$tree),
			default => '',
		};
	}//end scalarAtPath()
}//end class
