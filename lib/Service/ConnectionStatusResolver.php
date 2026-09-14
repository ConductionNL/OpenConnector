<?php

/**
 * Integriq ConnectionStatusResolver.
 *
 * Works out a connection row's `status`, `statusMessage` and `checkedAt` by the
 * rules of the hydra umbrella design D4, first match wins:
 *
 *   1.  the declaring app is disabled                  -> unavailable, now
 *   2.  the declaration says `available: false`        -> unavailable, sync time
 *   3.  not `reportedOnly`, and the adapter value is
 *       one of `adapter.simulatedValues`               -> simulated, sync time
 *   4a. `lastReport.status` is `simulated`             -> simulated, its time
 *   4b. a `lastProbe` or `lastReport` exists           -> the newer one, its time
 *   5.  not `reportedOnly`, and every `requiredConfig`
 *       key is filled                                  -> configured, now
 *   6.  otherwise                                      -> unconfigured, empty
 *
 * Rule 6 uses the declared `unconfiguredMessage` when there is one, and rule
 * 5 says "Required settings are filled." rather than "saved": a register
 * import can fill the keys too (umbrella amendment from dossiq#2715).
 *
 * Rule 3 sits above rule 4 on purpose. A mock adapter never calls the source,
 * so a green probe says nothing about what the app actually sends. Rule 4a
 * applies the same reasoning to the app's own report (umbrella D12).
 *
 * The adapter value is the app config value at `adapter.configKey`, or, with
 * `adapter.jsonPath`, the scalar at that dot path inside the JSON object the
 * key holds. `adapter.simulatedValues` defaults to `[""]`, so a declaration
 * without it keeps its old meaning: an empty key means a mock answers.
 * No declaration produces `limited`; only a report can.
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

use DateTimeInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;

/**
 * The D4 status rules.
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
 */
class ConnectionStatusResolver {

	/**
	 * The six stored status values.
	 *
	 * @var string[]
	 */
	public const STATUSES = ['configured', 'limited', 'unconfigured', 'simulated', 'unavailable', 'error'];

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
	 * @param ITimeFactory $timeFactory The clock.
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
	) {
	}//end __construct()

	/**
	 * Resolve one row.
	 *
	 * The returned `checkedAt` for rules 2, 3 and 5 keeps the row's stored
	 * value while its status and message stay the same. That is how "sync time"
	 * is read here: the time of the sync that first gave the row this status.
	 * Re-running a sync then changes nothing.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param bool $appEnabled Whether the declaring app is enabled.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
	 */
	public function resolve(array $row, bool $appEnabled): array {
		$declaration = $row['declaration'] ?? [];
		if (is_array($declaration) === false) {
			$declaration = [];
		}

		$now = $this->now();

		return $this->ruleAppDisabled(row: $row, appEnabled: $appEnabled, now: $now)
			?? $this->ruleDeclaredUnavailable(row: $row, declaration: $declaration, now: $now)
			?? $this->ruleSimulated(row: $row, declaration: $declaration, now: $now)
			?? $this->ruleReportedSimulated(row: $row)
			?? $this->ruleObserved(row: $row)
			?? $this->ruleSettingsSaved(row: $row, declaration: $declaration, now: $now)
			?? $this->outcome(
				status: 'unconfigured',
				message: $this->nonEmptyString(value: $declaration['unconfiguredMessage'] ?? null, fallback: 'Not checked yet.'),
				checkedAt: null,
				rule: 6
			);
	}//end resolve()

	/**
	 * Rule 1: the declaring app is disabled.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param bool $appEnabled Whether the declaring app is enabled.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleAppDisabled(array $row, bool $appEnabled, string $now): ?array {
		if ($appEnabled === true) {
			return null;
		}

		return $this->outcome(
			status: 'unavailable',
			message: 'The ' . (string)($row['app'] ?? '') . ' app is disabled.',
			checkedAt: $now,
			rule: 1
		);
	}//end ruleAppDisabled()

	/**
	 * Rule 2: declared, not usable yet.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param array<string,mixed> $declaration The declaration entry.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleDeclaredUnavailable(array $row, array $declaration, string $now): ?array {
		if (($declaration['available'] ?? true) !== false) {
			return null;
		}

		$message = $this->nonEmptyString(value: $declaration['unavailableMessage'] ?? null, fallback: 'Declared, not built yet.');

		return $this->outcome(
			status: 'unavailable',
			message: $message,
			checkedAt: $this->keepOrNow(row: $row, status: 'unavailable', message: $message, now: $now),
			rule: 2
		);
	}//end ruleDeclaredUnavailable()

	/**
	 * Rule 3: the adapter value is one of the simulated values, so a mock answers.
	 *
	 * Skipped for a `reportedOnly` row: only the app can tell what answers there.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param array<string,mixed> $declaration The declaration entry.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleSimulated(array $row, array $declaration, string $now): ?array {
		$adapter = $declaration['adapter'] ?? null;
		if (is_array($adapter) === false || $this->isReportedOnly(declaration: $declaration) === true) {
			return null;
		}

		$configKey = (string)($adapter['configKey'] ?? '');
		if ($configKey === '') {
			return null;
		}

		$value = $this->adapterValue(app: (string)($row['app'] ?? ''), configKey: $configKey, jsonPath: $adapter['jsonPath'] ?? null);
		if ($this->isSimulatedValue(value: $value, simulatedValues: $adapter['simulatedValues'] ?? null) === false) {
			return null;
		}

		$message = $this->nonEmptyString(
			value: $adapter['simulatedMessage'] ?? null,
			fallback: 'A mock adapter answers here. Set ' . $configKey . ' to a real adapter.'
		);

		return $this->outcome(
			status: 'simulated',
			message: $message,
			checkedAt: $this->keepOrNow(row: $row, status: 'simulated', message: $message, now: $now),
			rule: 3
		);
	}//end ruleSimulated()

	/**
	 * Rule 4a: the app reported that a mock answers.
	 *
	 * The report stands against any probe, newer ones included: a green test
	 * on the source says nothing about what a mock adapter sends. The outcome
	 * carries rule number 4, like rule 4b.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleReportedSimulated(array $row): ?array {
		$report = $this->readObservation(value: $row['lastReport'] ?? null, isProbe: false);
		if ($report === null || $report['status'] !== 'simulated') {
			return null;
		}

		return $this->outcome(
			status: 'simulated',
			message: $report['message'],
			checkedAt: $report['at'],
			rule: 4
		);
	}//end ruleReportedSimulated()

	/**
	 * Rule 4b: the newer of the last probe and the last report.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleObserved(array $row): ?array {
		$observation = $this->newestObservation(row: $row);
		if ($observation === null) {
			return null;
		}

		return $this->outcome(
			status: $observation['status'],
			message: $observation['message'],
			checkedAt: $observation['at'],
			rule: 4
		);
	}//end ruleObserved()

	/**
	 * Rule 5: every required settings key holds a value.
	 *
	 * Skipped for a `reportedOnly` row: filled settings say nothing about a
	 * platform chosen elsewhere.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param array<string,mixed> $declaration The declaration entry.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleSettingsSaved(array $row, array $declaration, string $now): ?array {
		$required = $declaration['requiredConfig'] ?? [];
		if (is_array($required) === false || $required === [] || $this->isReportedOnly(declaration: $declaration) === true) {
			return null;
		}

		if ($this->allFilled(app: (string)($row['app'] ?? ''), keys: $required) === false) {
			return null;
		}

		$message = 'Required settings are filled.';

		return $this->outcome(
			status: 'configured',
			message: $message,
			checkedAt: $this->keepOrNow(row: $row, status: 'configured', message: $message, now: $now),
			rule: 5
		);
	}//end ruleSettingsSaved()

	/**
	 * Pick the newer of `lastProbe` and `lastReport`.
	 *
	 * A probe's `ok` reads as `configured`. On an equal time the probe wins,
	 * because it is integriq's own observation.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 *
	 * @return array{status:string,message:string,at:?string}|null The observation, or null when there is none.
	 */
	private function newestObservation(array $row): ?array {
		$probe = $this->readObservation(value: $row['lastProbe'] ?? null, isProbe: true);
		$report = $this->readObservation(value: $row['lastReport'] ?? null, isProbe: false);

		if ($probe === null) {
			return $report;
		}

		if ($report === null) {
			return $probe;
		}

		if ($report['time'] > $probe['time']) {
			return $report;
		}

		return $probe;
	}//end newestObservation()

	/**
	 * Read one stored observation.
	 *
	 * @param mixed $value The stored `{status, message, at}` object.
	 * @param bool $isProbe Whether this is a probe (ok|error) or a report (the six statuses).
	 *
	 * @return array{status:string,message:string,at:?string,time:int}|null
	 */
	private function readObservation(mixed $value, bool $isProbe): ?array {
		if (is_array($value) === false) {
			return null;
		}

		$status = $this->observationStatus(status: (string)($value['status'] ?? ''), isProbe: $isProbe);
		if ($status === null) {
			return null;
		}

		$observedAt = $value['at'] ?? null;
		if (is_string($observedAt) === false || $observedAt === '') {
			return ['status' => $status, 'message' => (string)($value['message'] ?? ''), 'at' => null, 'time' => 0];
		}

		return [
			'status' => $status,
			'message' => (string)($value['message'] ?? ''),
			'at' => $observedAt,
			'time' => (int)strtotime($observedAt),
		];
	}//end readObservation()

	/**
	 * Map a stored observation status onto a row status.
	 *
	 * @param string $status The stored status.
	 * @param bool $isProbe Whether the observation is a probe.
	 *
	 * @return string|null The row status, or null when the value is not valid for its kind.
	 */
	private function observationStatus(string $status, bool $isProbe): ?string {
		if ($isProbe === true) {
			return match ($status) {
				'ok' => 'configured',
				'error' => 'error',
				default => null,
			};
		}

		if (in_array($status, self::STATUSES, true) === true) {
			return $status;
		}

		return null;
	}//end observationStatus()

	/**
	 * Whether every key holds a non-empty value in the app's config.
	 *
	 * @param string $app The declaring app id.
	 * @param array<int|string,mixed> $keys The required keys.
	 *
	 * @return bool
	 */
	private function allFilled(string $app, array $keys): bool {
		foreach ($keys as $key) {
			if (is_string($key) === false || $this->readConfig(app: $app, key: $key) === '') {
				return false;
			}
		}

		return true;
	}//end allFilled()

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
	private function readConfig(string $app, string $key): string {
		if ($app === '' || $key === '') {
			return '';
		}

		try {
			return trim($this->appConfig->getValueString($app, $key, '', true));
		} catch (\OCP\Exceptions\AppConfigTypeConflictException $e) {
			return 'typed';
		}
	}//end readConfig()

	/**
	 * Whether the declaration says only the app can judge this connection.
	 *
	 * @param array<string,mixed> $declaration The declaration entry.
	 *
	 * @return bool
	 */
	private function isReportedOnly(array $declaration): bool {
		return ($declaration['reportedOnly'] ?? false) === true;
	}//end isReportedOnly()

	/**
	 * The value that selects the adapter.
	 *
	 * Without a JSON path it is the trimmed config value. With one, it is the
	 * scalar at that dot path inside the JSON object the key holds. A missing
	 * path, invalid JSON or a non-scalar value reads as the empty string.
	 *
	 * @param string $app The declaring app id.
	 * @param string $configKey The config key.
	 * @param mixed $jsonPath The declared dot path, if any.
	 *
	 * @return string
	 */
	private function adapterValue(string $app, string $configKey, mixed $jsonPath): string {
		if (is_string($jsonPath) === false || $jsonPath === '') {
			return $this->readConfig(app: $app, key: $configKey);
		}

		return $this->scalarAtPath(tree: $this->readConfigObject(app: $app, key: $configKey), path: $jsonPath);
	}//end adapterValue()

	/**
	 * Read a config value holding a JSON object, decoded.
	 *
	 * A value Nextcloud stores as an array type makes getValueString() throw;
	 * getValueArray() reads that one.
	 *
	 * @param string $app The declaring app id.
	 * @param string $key The config key.
	 *
	 * @return mixed The decoded value, or null when it is absent or not JSON.
	 */
	private function readConfigObject(string $app, string $key): mixed {
		if ($app === '') {
			return null;
		}

		try {
			return json_decode($this->appConfig->getValueString($app, $key, '', true), true);
		} catch (\OCP\Exceptions\AppConfigTypeConflictException $e) {
			return $this->readConfigArray(app: $app, key: $key);
		}
	}//end readConfigObject()

	/**
	 * Read a config value stored under the array type.
	 *
	 * @param string $app The declaring app id.
	 * @param string $key The config key.
	 *
	 * @return array<mixed>|null The value, or null when it is stored under another type.
	 */
	private function readConfigArray(string $app, string $key): ?array {
		try {
			return $this->appConfig->getValueArray($app, $key, [], true);
		} catch (\OCP\Exceptions\AppConfigTypeConflictException $e) {
			return null;
		}
	}//end readConfigArray()

	/**
	 * The scalar at a dot path, as a trimmed string.
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

		if ($tree === true) {
			return 'true';
		}

		if ($tree === false) {
			return 'false';
		}

		if (is_string($tree) === true || is_int($tree) === true || is_float($tree) === true) {
			return trim((string)$tree);
		}

		return '';
	}//end scalarAtPath()

	/**
	 * Whether an adapter value is one of the values that mean a mock answers.
	 *
	 * Compared case-insensitively after trimming. A declaration without a
	 * valid list uses the default `[""]`.
	 *
	 * @param string $value The adapter value.
	 * @param mixed $simulatedValues The declared list, if any.
	 *
	 * @return bool
	 */
	private function isSimulatedValue(string $value, mixed $simulatedValues): bool {
		if (is_array($simulatedValues) === false) {
			$simulatedValues = self::DEFAULT_SIMULATED_VALUES;
		}

		$needle = mb_strtolower(trim($value));
		foreach ($simulatedValues as $candidate) {
			if (is_string($candidate) === true && mb_strtolower(trim($candidate)) === $needle) {
				return true;
			}
		}

		return false;
	}//end isSimulatedValue()

	/**
	 * Keep the stored `checkedAt` when status and message did not change.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param string $status The resolved status.
	 * @param string $message The resolved message.
	 * @param string $now The current time.
	 *
	 * @return string
	 */
	private function keepOrNow(array $row, string $status, string $message, string $now): string {
		$stored = $row['checkedAt'] ?? null;
		if (($row['status'] ?? null) === $status
			&& ($row['statusMessage'] ?? null) === $message
			&& is_string($stored) === true
			&& $stored !== ''
		) {
			return $stored;
		}

		return $now;
	}//end keepOrNow()

	/**
	 * A string value, or the fallback when it is empty or not a string.
	 *
	 * @param mixed $value The value.
	 * @param string $fallback The fallback.
	 *
	 * @return string
	 */
	private function nonEmptyString(mixed $value, string $fallback): string {
		if (is_string($value) === true && trim($value) !== '') {
			return $value;
		}

		return $fallback;
	}//end nonEmptyString()

	/**
	 * Build the outcome array.
	 *
	 * @param string $status The status.
	 * @param string $message The status message.
	 * @param string|null $checkedAt The observation time.
	 * @param int $rule The D4 rule number that matched.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}
	 */
	private function outcome(string $status, string $message, ?string $checkedAt, int $rule): array {
		return [
			'status' => $status,
			'statusMessage' => $message,
			'checkedAt' => $checkedAt,
			'rule' => $rule,
		];
	}//end outcome()

	/**
	 * The current time as ISO 8601.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
	 */
	public function now(): string {
		return $this->timeFactory->now()->format(DateTimeInterface::ATOM);
	}//end now()
}//end class
