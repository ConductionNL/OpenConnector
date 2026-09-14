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
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '', bool $lazy = false) use ($config): string {
				return $config[$app . '.' . $key] ?? $default;
			}
		);

		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		return new ConnectionStatusResolver(appConfig: $appConfig, timeFactory: $time);
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

		$this->assertSame(expected: 'unavailable', actual: $outcome['status']);
		$this->assertSame(expected: 'The dossiq app is disabled.', actual: $outcome['statusMessage']);
		$this->assertSame(expected: self::NOW, actual: $outcome['checkedAt']);
		$this->assertSame(expected: 1, actual: $outcome['rule']);
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
		$this->assertSame(expected: 'unavailable', actual: $declared['status']);
		$this->assertSame(expected: 'Not wired yet.', actual: $declared['statusMessage']);
		$this->assertSame(expected: self::NOW, actual: $declared['checkedAt']);
		$this->assertSame(expected: 2, actual: $declared['rule']);

		$default = $resolver->resolve(['app' => 'dossiq', 'declaration' => ['available' => false]], true);
		$this->assertSame(expected: 'Declared, not built yet.', actual: $default['statusMessage']);
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

		$this->assertSame(expected: '2026-09-01T08:00:00+00:00', actual: $outcome['checkedAt']);
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

		$this->assertSame(expected: 'simulated', actual: $outcome['status']);
		$this->assertSame(expected: 'A mock answers.', actual: $outcome['statusMessage']);
		$this->assertSame(expected: 3, actual: $outcome['rule']);
	}//end testRuleThreeEmptyAdapterKeyIsSimulated()

	/**
	 * Rule 3 default message names the config key.
	 *
	 * @return void
	 */
	public function testRuleThreeDefaultMessageNamesTheKey(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['adapter' => ['configKey' => 'kvk_adapter']]];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame(expected: 'A mock adapter answers here. Set kvk_adapter to a real adapter.', actual: $outcome['statusMessage']);
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

		$this->assertSame(expected: 'simulated', actual: $outcome['status']);
	}//end testSimulatedOutranksAPassingProbe()

	/**
	 * A filled adapter key falls through to the next rules.
	 *
	 * @return void
	 */
	public function testFilledAdapterKeyIsNotSimulated(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['adapter' => ['configKey' => 'berichtenbox_adapter']]];

		$outcome = $this->makeResolver(config: ['dossiq.berichtenbox_adapter' => 'OCA\\Dossiq\\Real'])->resolve($row, true);

		$this->assertSame(expected: 'unconfigured', actual: $outcome['status']);
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

		$this->assertSame(expected: 'configured', actual: $outcome['status']);
		$this->assertSame(expected: 'The source answered with HTTP 200.', actual: $outcome['statusMessage']);
		$this->assertSame(expected: '2026-09-14T11:00:00+00:00', actual: $outcome['checkedAt']);
		$this->assertSame(expected: 4, actual: $outcome['rule']);
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
		$this->assertSame(expected: 'error', actual: $probeNewer['status']);
		$this->assertSame(expected: '2026-09-14T11:00:00+00:00', actual: $probeNewer['checkedAt']);

		$report['at'] = '2026-09-14T11:30:00+00:00';
		$reportNewer = $resolver->resolve(['app' => 'dossiq', 'lastReport' => $report, 'lastProbe' => $probe], true);
		$this->assertSame(expected: 'configured', actual: $reportNewer['status']);
		$this->assertSame(expected: 'Logged in', actual: $reportNewer['statusMessage']);
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

		$this->assertSame(expected: 6, actual: $outcome['rule']);
	}//end testInvalidObservationIsIgnored()

	/**
	 * Rule 5: every required key filled shows configured.
	 *
	 * @return void
	 */
	public function testRuleFiveSavedSettingsShowConfigured(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['register', 'case_schema']]];

		$outcome = $this->makeResolver(config: ['dossiq.register' => 'dossiq', 'dossiq.case_schema' => 'case'])->resolve($row, true);

		$this->assertSame(expected: 'configured', actual: $outcome['status']);
		$this->assertSame(expected: 'Required settings are filled.', actual: $outcome['statusMessage']);
		$this->assertSame(expected: self::NOW, actual: $outcome['checkedAt']);
		$this->assertSame(expected: 5, actual: $outcome['rule']);
	}//end testRuleFiveSavedSettingsShowConfigured()

	/**
	 * Rule 5 does not apply when one required key is empty.
	 *
	 * @return void
	 */
	public function testRuleFiveNeedsEveryKey(): void {
		$row = ['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['register', 'case_schema']]];

		$outcome = $this->makeResolver(config: ['dossiq.register' => 'dossiq', 'dossiq.case_schema' => '  '])->resolve($row, true);

		$this->assertSame(expected: 'unconfigured', actual: $outcome['status']);
	}//end testRuleFiveNeedsEveryKey()

	/**
	 * Rule 6: nothing to go on shows not checked, with no time.
	 *
	 * @return void
	 */
	public function testRuleSixNotCheckedYet(): void {
		$outcome = $this->makeResolver()->resolve(['app' => 'dossiq', 'declaration' => ['key' => 'pdok']], true);

		$this->assertSame(expected: 'unconfigured', actual: $outcome['status']);
		$this->assertSame(expected: 'Not checked yet.', actual: $outcome['statusMessage']);
		$this->assertNull(actual: $outcome['checkedAt']);
		$this->assertSame(expected: 6, actual: $outcome['rule']);
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

		$this->assertSame(expected: 'unconfigured', actual: $outcome['status']);
		$this->assertSame(expected: 'Set integration.brp.mode to use the BRP.', actual: $outcome['statusMessage']);
		$this->assertNull(actual: $outcome['checkedAt']);
	}//end testRuleSixUsesDeclaredUnconfiguredMessage()

	/**
	 * A value stored under another type counts as filled.
	 *
	 * @return void
	 */
	public function testTypedConfigValueCountsAsFilled(): void {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willThrowException(
			new \OCP\Exceptions\AppConfigTypeConflictException('conflict with value type from database')
		);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		$resolver = new ConnectionStatusResolver(appConfig: $appConfig, timeFactory: $time);
		$outcome = $resolver->resolve(['app' => 'dossiq', 'declaration' => ['requiredConfig' => ['retries']]], true);

		$this->assertSame(expected: 'configured', actual: $outcome['status']);
	}//end testTypedConfigValueCountsAsFilled()

	/**
	 * A provider name in `simulatedValues` selects simulated, case-insensitively after trimming.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-provider-name-selects-simulated
	 */
	public function testProviderNameSelectsSimulated(): void {
		$row = [
			'app' => 'pipelinq',
			'declaration' => ['adapter' => ['configKey' => 'email_transport_type', 'simulatedValues' => ['', 'null']]],
		];

		foreach (['null', '  NULL ', ''] as $value) {
			$outcome = $this->makeResolver(config: ['pipelinq.email_transport_type' => $value])->resolve($row, true);
			$this->assertSame(expected: 'simulated', actual: $outcome['status'], message: 'value "' . $value . '"');
			$this->assertSame(expected: 3, actual: $outcome['rule']);
		}

		$real = $this->makeResolver(config: ['pipelinq.email_transport_type' => 'smtp'])->resolve($row, true);
		$this->assertSame(expected: 'unconfigured', actual: $real['status']);
	}//end testProviderNameSelectsSimulated()

	/**
	 * A JSON path reads inside a settings blob, so a real provider there keeps rule 3 off.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-json-path-reads-inside-a-settings-blob
	 */
	public function testJsonPathReadsInsideASettingsBlob(): void {
		$row = [
			'app' => 'pipelinq',
			'declaration' => ['adapter' => ['configKey' => 'llm', 'jsonPath' => 'provider', 'simulatedValues' => ['', 'none']]],
		];

		$real = $this->makeResolver(config: ['pipelinq.llm' => '{"provider": "openai"}'])->resolve($row, true);
		$this->assertNotSame(expected: 3, actual: $real['rule']);
		$this->assertSame(expected: 'unconfigured', actual: $real['status']);

		$mock = $this->makeResolver(config: ['pipelinq.llm' => '{"provider": "None"}'])->resolve($row, true);
		$this->assertSame(expected: 'simulated', actual: $mock['status']);
	}//end testJsonPathReadsInsideASettingsBlob()

	/**
	 * JSON path edge cases: [stored value, path, expected adapter value is simulated under the default list].
	 *
	 * @return array<string,array{0:string,1:string,2:bool}>
	 */
	public static function jsonPathCases(): array {
		return [
			'nested path with a value' => ['{"chat": {"provider": "openai"}}', 'chat.provider', false],
			'missing leaf' => ['{"chat": {}}', 'chat.provider', true],
			'missing branch' => ['{"other": 1}', 'chat.provider', true],
			'path through a scalar' => ['{"chat": "openai"}', 'chat.provider', true],
			'invalid JSON' => ['{provider: openai', 'provider', true],
			'empty value' => ['', 'provider', true],
			'a plain string, not JSON' => ['openai', 'provider', true],
			'object at the path' => ['{"provider": {"name": "openai"}}', 'provider', true],
			'list at the path' => ['{"provider": ["openai"]}', 'provider', true],
			'null at the path' => ['{"provider": null}', 'provider', true],
			'whitespace string at the path' => ['{"provider": "   "}', 'provider', true],
			'number at the path' => ['{"provider": 0}', 'provider', false],
			'false at the path' => ['{"provider": false}', 'provider', false],
		];
	}//end jsonPathCases()

	/**
	 * A missing path, invalid JSON or a non-scalar value reads as the empty string.
	 *
	 * @param string $stored The stored config value.
	 * @param string $path The declared JSON path.
	 * @param bool $simulated Whether rule 3 applies under the default `[""]`.
	 *
	 * @return void
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('jsonPathCases')]
	public function testJsonPathEdgeCases(string $stored, string $path, bool $simulated): void {
		$row = ['app' => 'pipelinq', 'declaration' => ['adapter' => ['configKey' => 'llm', 'jsonPath' => $path]]];

		$outcome = $this->makeResolver(config: ['pipelinq.llm' => $stored])->resolve($row, true);

		$this->assertSame(expected: $simulated, actual: $outcome['rule'] === 3);
	}//end testJsonPathEdgeCases()

	/**
	 * Booleans and numbers at the path read as their JSON text, so a list can name them.
	 *
	 * @return void
	 */
	public function testJsonPathScalarsReadAsText(): void {
		$declaration = ['adapter' => ['configKey' => 'geo', 'jsonPath' => 'enabled', 'simulatedValues' => ['false', '0']]];

		$false = $this->makeResolver(config: ['traffic.geo' => '{"enabled": false}'])->resolve(['app' => 'traffic', 'declaration' => $declaration], true);
		$this->assertSame(expected: 'simulated', actual: $false['status']);

		$zero = $this->makeResolver(config: ['traffic.geo' => '{"enabled": 0}'])->resolve(['app' => 'traffic', 'declaration' => $declaration], true);
		$this->assertSame(expected: 'simulated', actual: $zero['status']);

		$true = $this->makeResolver(config: ['traffic.geo' => '{"enabled": true}'])->resolve(['app' => 'traffic', 'declaration' => $declaration], true);
		$this->assertSame(expected: 'unconfigured', actual: $true['status']);
	}//end testJsonPathScalarsReadAsText()

	/**
	 * A JSON path also reads a value Nextcloud stores under the array type.
	 *
	 * @return void
	 */
	public function testJsonPathReadsAnArrayTypedValue(): void {
		$appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$appConfig->method('getValueString')->willThrowException(
			new \OCP\Exceptions\AppConfigTypeConflictException('conflict with value type from database')
		);
		$appConfig->method('getValueArray')->willReturn(['provider' => 'none']);
		$time = $this->createMock(originalClassName: ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable(self::NOW));

		$resolver = new ConnectionStatusResolver(appConfig: $appConfig, timeFactory: $time);
		$row = [
			'app' => 'pipelinq',
			'declaration' => ['adapter' => ['configKey' => 'llm', 'jsonPath' => 'provider', 'simulatedValues' => ['none']]],
		];

		$this->assertSame(expected: 'simulated', actual: $resolver->resolve($row, true)['status']);
	}//end testJsonPathReadsAnArrayTypedValue()

	/**
	 * Without the new fields a declaration resolves exactly as before, and the explicit defaults change nothing.
	 *
	 * Before the amendment rule 3 applied only when the trimmed value was empty,
	 * so only the empty and blank values expect simulated here.
	 *
	 * @return void
	 */
	public function testDefaultsKeepTheOldMeaning(): void {
		$legacy = ['configKey' => 'berichtenbox_adapter', 'simulatedMessage' => 'A mock answers.'];
		$explicit = $legacy + ['simulatedValues' => ['']];
		$oldStatus = [
			'' => 'simulated',
			'   ' => 'simulated',
			'OCA\\Dossiq\\Real' => 'unconfigured',
			'null' => 'unconfigured',
			'none' => 'unconfigured',
			'NULL' => 'unconfigured',
		];

		foreach ($oldStatus as $value => $expected) {
			$value = (string)$value;
			$resolver = $this->makeResolver(config: ['dossiq.berichtenbox_adapter' => $value]);
			$withoutFields = $resolver->resolve(['app' => 'dossiq', 'declaration' => ['adapter' => $legacy]], true);
			$withDefaults = $resolver->resolve(
				['app' => 'dossiq', 'declaration' => ['adapter' => $explicit, 'reportedOnly' => false]],
				true
			);

			$this->assertSame(expected: $expected, actual: $withoutFields['status'], message: 'value "' . $value . '"');
			$this->assertSame(expected: $withoutFields, actual: $withDefaults, message: 'value "' . $value . '"');
		}
	}//end testDefaultsKeepTheOldMeaning()

	/**
	 * A reported-only row ignores filled settings and an empty adapter key.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-reported-only-row-ignores-filled-settings
	 */
	public function testReportedOnlyRowIgnoresFilledSettings(): void {
		$row = [
			'app' => 'pipelinq',
			'declaration' => [
				'reportedOnly' => true,
				'requiredConfig' => ['cti_register'],
				'adapter' => ['configKey' => 'cti_adapter'],
			],
		];

		$outcome = $this->makeResolver(config: ['pipelinq.cti_register' => 'pipelinq'])->resolve($row, true);

		$this->assertSame(expected: 'unconfigured', actual: $outcome['status']);
		$this->assertSame(expected: 6, actual: $outcome['rule']);
	}//end testReportedOnlyRowIgnoresFilledSettings()

	/**
	 * A reported-only row still takes the app's report.
	 *
	 * @return void
	 */
	public function testReportedOnlyRowShowsTheReport(): void {
		$row = [
			'app' => 'pipelinq',
			'declaration' => ['reportedOnly' => true, 'requiredConfig' => ['cti_register']],
			'lastReport' => ['status' => 'configured', 'message' => 'Platform chosen: Voys.', 'at' => '2026-09-14T10:00:00+00:00'],
		];

		$outcome = $this->makeResolver(config: ['pipelinq.cti_register' => 'pipelinq'])->resolve($row, true);

		$this->assertSame(expected: 'configured', actual: $outcome['status']);
		$this->assertSame(expected: 'Platform chosen: Voys.', actual: $outcome['statusMessage']);
	}//end testReportedOnlyRowShowsTheReport()

	/**
	 * Rule 4a: a simulated report stands against a newer passing probe, with the report's time.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-a-simulated-report-stands-against-a-newer-probe
	 */
	public function testSimulatedReportStandsAgainstANewerProbe(): void {
		$row = [
			'app' => 'shillinq',
			'declaration' => [],
			'lastReport' => ['status' => 'simulated', 'message' => 'A Log adapter answers.', 'at' => '2026-09-14T10:00:00+00:00'],
			'lastProbe' => ['status' => 'ok', 'message' => 'HTTP 200', 'at' => '2026-09-14T11:00:00+00:00'],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame(expected: 'simulated', actual: $outcome['status']);
		$this->assertSame(expected: '2026-09-14T10:00:00+00:00', actual: $outcome['checkedAt']);
		$this->assertSame(expected: 'A Log adapter answers.', actual: $outcome['statusMessage']);
	}//end testSimulatedReportStandsAgainstANewerProbe()

	/**
	 * A report may carry limited, and rule 4b shows it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#scenario-an-app-reports-a-connection-that-works-in-part
	 */
	public function testLimitedReportIsShown(): void {
		$row = [
			'app' => 'pipelinq',
			'declaration' => [],
			'lastReport' => [
				'status' => 'limited',
				'message' => 'Preview API: posting works, reading replies does not.',
				'at' => '2026-09-14T10:00:00+00:00',
			],
		];

		$outcome = $this->makeResolver()->resolve($row, true);

		$this->assertSame(expected: 'limited', actual: $outcome['status']);
		$this->assertSame(expected: 'Preview API: posting works, reading replies does not.', actual: $outcome['statusMessage']);
		$this->assertContains(needle: 'limited', haystack: ConnectionStatusResolver::STATUSES);
	}//end testLimitedReportIsShown()

	/**
	 * No declaration produces limited: every rule that reads the declaration answers another status.
	 *
	 * @return void
	 */
	public function testNoDeclarationProducesLimited(): void {
		$declarations = [
			['available' => false],
			['adapter' => ['configKey' => 'x', 'simulatedValues' => ['limited']]],
			['requiredConfig' => ['limited']],
			['reportedOnly' => true],
			[],
		];

		foreach ($declarations as $declaration) {
			$outcome = $this->makeResolver(config: ['dossiq.x' => 'limited', 'dossiq.limited' => 'limited'])
				->resolve(['app' => 'dossiq', 'declaration' => $declaration], true);
			$this->assertNotSame(expected: 'limited', actual: $outcome['status']);
		}
	}//end testNoDeclarationProducesLimited()
}//end class
