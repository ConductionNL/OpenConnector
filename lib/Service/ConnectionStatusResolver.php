<?php

/**
 * Integriq ConnectionStatusResolver.
 *
 * Works out a connection row's `status`, `statusMessage` and `checkedAt` by the
 * rules of the hydra umbrella design D4, first match wins:
 *
 *   1. the declaring app is disabled                 -> unavailable, now
 *   2. the declaration says `available: false`       -> unavailable, sync time
 *   3. `adapter.configKey` is empty in the app config -> simulated, sync time
 *   4. a `lastProbe` or `lastReport` exists          -> the newer one, its time
 *   5. every `requiredConfig` key is filled          -> configured, now
 *   6. otherwise                                     -> unconfigured, empty
 *
 * Rule 6 uses the declared `unconfiguredMessage` when there is one, and rule
 * 5 says "Required settings are filled." rather than "saved": a register
 * import can fill the keys too (umbrella amendment from dossiq#2715).
 *
 * Rule 3 sits above rule 4 on purpose. A mock adapter never calls the source,
 * so a green probe says nothing about what the app actually sends.
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
	 * The five stored status values.
	 *
	 * @var string[]
	 */
	public const STATUSES = ['configured', 'unconfigured', 'simulated', 'unavailable', 'error'];

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
	 * Rule 3: the adapter key is empty, so a mock answers.
	 *
	 * @param array<string,mixed> $row The stored row data.
	 * @param array<string,mixed> $declaration The declaration entry.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleSimulated(array $row, array $declaration, string $now): ?array {
		$adapter = $declaration['adapter'] ?? null;
		if (is_array($adapter) === false) {
			return null;
		}

		$configKey = (string)($adapter['configKey'] ?? '');
		if ($configKey === '' || $this->readConfig(app: (string)($row['app'] ?? ''), key: $configKey) !== '') {
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
	 * Rule 4: the newer of the last probe and the last report.
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
	 * @param array<string,mixed> $row The stored row data.
	 * @param array<string,mixed> $declaration The declaration entry.
	 * @param string $now The current time.
	 *
	 * @return array{status:string,statusMessage:string,checkedAt:?string,rule:int}|null
	 */
	private function ruleSettingsSaved(array $row, array $declaration, string $now): ?array {
		$required = $declaration['requiredConfig'] ?? [];
		if (is_array($required) === false || $required === []) {
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
	 * @param bool $isProbe Whether this is a probe (ok|error) or a report (the five statuses).
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
