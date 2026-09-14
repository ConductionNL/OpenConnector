<?php

/**
 * Unit tests for ConnectionStatusResolver (connection-registry, umbrella D4).
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-resolver-applies-the-d4-rules-in-order-req-conn-003
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Integriq\Service\ConnectionStatusResolver;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * Every D4 row, in order, plus the two orderings the umbrella explains.
 */
class ConnectionStatusResolverTest extends TestCase {

	/**
	 * The fixed clock value.
	 *
	 * @var string
	 */
	private const NOW = '2026-09-14T12:00:00+00:00';

	/**
	 * Build a resolver over a fixed config map and clock.
	 *
	 * @param array<string,string> $config Key => value in the declaring app's config.
	 *
	 * @return ConnectionStatusResolver
	 */
	private function makeResolver(array $config = []): ConnectionStatusResolver {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '', bool $lazy = false) use ($config): string {
				return $config[$app . '.' . $key] ?? $default;
			}
		);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		return new ConnectionStatusResolver($appConfig, $time);
	}//end makeResolver()

	/**
	 * Rule 1: a disabled app shows unavailable, stamped now, above every other rule.
	 *
	 * @return void
	 */
	public function testRuleOneDisabledAppIsUnavailable(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => ['available' => false, 'adapter' => ['configKey' => 'x']],
			'lastProbe' => ['status' => 'ok', 'message' => 'fine', 'at' => '2026-09-14T10:00:00+00:00'],
		];

		$outcome = $this->makeResolver()->resolve($row, false);

		$this->assertSame('unavailable', $outcome['status']);
		$this->assertSame('The dossiq app is disabled.', $outcome['statusMessage']);
		$this->assertSame(self::NOW, $outcome['checkedAt']);
		$this->assertSame(1, $outcome['rule']);
	}//end testRuleOneDisabledAppIsUnavailable()

	/**
	 * Rule 2: available false uses the declared message, else the default.
	 *
	 * @return void
	 */
	public function testRuleTwoDeclaredUnavailable(): void {
		$resolver = $this->makeResolver();

		$declared = $resolver->resolve(
			['app' => 'dossiq', 'declaration' => ['available' => false, 'unavailableMessage' => 'Not wired yet.']],
			true
		);
		$this->assertSame('unavailable', $declared['status']);
		$this->assertSame('Not wired yet.', $declared['statusMessage']);
		$this->assertSame(self::NOW, $declared['checkedAt']);
		$this->assertSame(2, $declared['rule']);

		$default = $resolver->resolve(['app' => 'dossiq', 'declaration' => ['available' => false]], true);
		$this->assertSame('Declared, not built yet.', $default['statusMessage']);
	}//end testRuleTwoDeclaredUnavailable()

	/**
	 * Rules 2, 3 and 5 keep the stored time while status and message stay the same.
	 *
	 * @return void
	 */
	public function testSyncTimeIsKeptWhileNothingChanged(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => ['available' => false],
			'status' => 'unavailable',
			'statusMessage' => 'Declared, not built yet.',
			'checkedAt' => '2026-09-01T08:00:00+00:00',
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('2026-09-01T08:00:00+00:00', $outcome['checkedAt']);
	}//end testSyncTimeIsKeptWhileNothingChanged()

	/**
	 * Rule 3: an empty adapter key shows simulated with the declared message.
	 *
	 * @return void
	 */
	public function testRuleThreeEmptyAdapterKeyIsSimulated(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => [
				'adapter' => ['configKey' => 'berichtenbox_adapter', 'simulatedMessage' => 'A mock answers.'],
			],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('simulated', $outcome['status']);
		$this->assertSame('A mock answers.', $outcome['statusMessage']);
		$this->assertSame(3, $outcome['rule']);
	}//end testRuleThreeEmptyAdapterKeyIsSimulated()

	/**
	 * Rule 3 default message names the config key.
	 *
	 * @return void
	 */
	public function testRuleThreeDefaultMessageNamesTheKey(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['adapter' => ['configKey' => 'kvk_adapter']]];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('A mock adapter answers here. Set kvk_adapter to a real adapter.', $outcome['statusMessage']);
	}//end testRuleThreeDefaultMessageNamesTheKey()

	/**
	 * Why simulated beats a passing probe: rule 3 sits above rule 4.
	 *
	 * @return void
	 */
	public function testSimulatedOutranksAPassingProbe(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => ['adapter' => ['configKey' => 'berichtenbox_adapter']],
			'source' => 'b0a7c1d2-0000-4000-8000-000000000001',
			'lastProbe' => ['status' => 'ok', 'message' => 'HTTP 200', 'at' => '2026-09-14T11:00:00+00:00'],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('simulated', $outcome['status']);
	}//end testSimulatedOutranksAPassingProbe()

	/**
	 * A filled adapter key falls through to the next rules.
	 *
	 * @return void
	 */
	public function testFilledAdapterKeyIsNotSimulated(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['adapter' => ['configKey' => 'berichtenbox_adapter']]];

		$outcome = $this->makeResolver(['dossiq.berichtenbox_adapter' => 'OCA\\Dossiq\\Real'])->resolve($row, true);

		$this->assertSame('unconfigured', $outcome['status']);
	}//end testFilledAdapterKeyIsNotSimulated()

	/**
	 * Rule 4: a probe's ok reads as configured, with the probe's own time.
	 *
	 * @return void
	 */
	public function testRuleFourProbeOkReadsAsConfigured(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => [],
			'lastProbe' => ['status' => 'ok', 'message' => 'The source answered with HTTP 200.', 'at' => '2026-09-14T11:00:00+00:00'],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('configured', $outcome['status']);
		$this->assertSame('The source answered with HTTP 200.', $outcome['statusMessage']);
		$this->assertSame('2026-09-14T11:00:00+00:00', $outcome['checkedAt']);
		$this->assertSame(4, $outcome['rule']);
	}//end testRuleFourProbeOkReadsAsConfigured()

	/**
	 * Why a report and a probe compare by time: the newer observation wins.
	 *
	 * @return void
	 */
	public function testNewerObservationWins(): void {
		$resolver = $this->makeResolver();
		$report = ['status' => 'configured', 'message' => 'Logged in', 'at' => '2026-09-14T10:00:00+00:00'];
		$probe = ['status' => 'error', 'message' => 'HTTP 503', 'at' => '2026-09-14T11:00:00+00:00'];

		$probeNewer = $resolver->resolve(['app' => 'dossiq', 'lastReport' => $report, 'lastProbe' => $probe], true);
		$this->assertSame('error', $probeNewer['status']);
		$this->assertSame('2026-09-14T11:00:00+00:00', $probeNewer['checkedAt']);

		$report['at'] = '2026-09-14T11:30:00+00:00';
		$reportNewer = $resolver->resolve(['app' => 'dossiq', 'lastReport' => $report, 'lastProbe' => $probe], true);
		$this->assertSame('configured', $reportNewer['status']);
		$this->assertSame('Logged in', $reportNewer['statusMessage']);
	}//end testNewerObservationWins()

	/**
	 * An observation with an invalid status is ignored.
	 *
	 * @return void
	 */
	public function testInvalidObservationIsIgnored(): void {
		$row = [
			'app' => 'dossiq',
			'lastReport' => ['status' => 'green', 'message' => 'nope', 'at' => '2026-09-14T10:00:00+00:00'],
			'lastProbe' => ['status' => 'configured', 'message' => 'probes only say ok or error'],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame(6, $outcome['rule']);
	}//end testInvalidObservationIsIgnored()

	/**
	 * Rule 5: every required key filled shows configured.
	 *
	 * @return void
	 */
	public function testRuleFiveSavedSettingsShowConfigured(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['register', 'case_schema']]];

		$outcome = $this->makeResolver(['dossiq.register' => 'dossiq', 'dossiq.case_schema' => 'case'])->resolve($row, true);

		$this->assertSame('configured', $outcome['status']);
		$this->assertSame('Required settings are filled.', $outcome['statusMessage']);
		$this->assertSame(self::NOW, $outcome['checkedAt']);
		$this->assertSame(5, $outcome['rule']);
	}//end testRuleFiveSavedSettingsShowConfigured()

	/**
	 * Rule 5 does not apply when one required key is empty.
	 *
	 * @return void
	 */
	public function testRuleFiveNeedsEveryKey(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['register', 'case_schema']]];

		$outcome = $this->makeResolver(['dossiq.register' => 'dossiq', 'dossiq.case_schema' => '  '])->resolve($row, true);

		$this->assertSame('unconfigured', $outcome['status']);
	}//end testRuleFiveNeedsEveryKey()

	/**
	 * Rule 6: nothing to go on shows not checked, with no time.
	 *
	 * @return void
	 */
	public function testRuleSixNotCheckedYet(): void {
		$outcome = $this->makeResolver()->resolve(['app' => 'dossiq', 'declaration' => ['key' => 'pdok']], true);

		$this->assertSame('unconfigured', $outcome['status']);
		$this->assertSame('Not checked yet.', $outcome['statusMessage']);
		$this->assertNull($outcome['checkedAt']);
		$this->assertSame(6, $outcome['rule']);
	}//end testRuleSixNotCheckedYet()

	/**
	 * Rule 6 uses the declared unconfiguredMessage when there is one.
	 *
	 * @return void
	 */
	public function testRuleSixUsesDeclaredUnconfiguredMessage(): void {
		$row = [
			'app' => 'dossiq',
			'declaration' => ['key' => 'brp', 'unconfiguredMessage' => 'Set integration.brp.mode to use the BRP.'],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame('unconfigured', $outcome['status']);
		$this->assertSame('Set integration.brp.mode to use the BRP.', $outcome['statusMessage']);
		$this->assertNull($outcome['checkedAt']);
	}//end testRuleSixUsesDeclaredUnconfiguredMessage()

	/**
	 * A value stored under another type counts as filled.
	 *
	 * @return void
	 */
	public function testTypedConfigValueCountsAsFilled(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willThrowException(
			new \OCP\Exceptions\AppConfigTypeConflictException('conflict with value type from database')
		);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		$resolver = new ConnectionStatusResolver($appConfig, $time);
		$outcome = $resolver->resolve(['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['retries']]], true);

		$this->assertSame('configured', $outcome['status']);
	}//end testTypedConfigValueCountsAsFilled()
}//end class
