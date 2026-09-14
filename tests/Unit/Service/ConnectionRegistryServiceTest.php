<?php

/**
 * Unit tests for ConnectionRegistryService (connection-registry, umbrella D5 and D6).
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-sync-is-idempotent-and-keeps-linked-rows-req-conn-002
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Integriq\Service\ConnectionDeclarationValidator;
use OCA\Integriq\Service\ConnectionRegistryService;
use OCA\Integriq\Service\ConnectionStatusResolver;
use OCA\Integriq\Service\ConnectionStore;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Sync, removal, report and refresh behaviour against an in-memory store.
 */
class ConnectionRegistryServiceTest extends TestCase {

	/**
	 * Temporary app directories created by a test.
	 *
	 * @var string[]
	 */
	private array $dirs = [];

	/**
	 * The in-memory rows: uuid => data.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $rows = [];

	/**
	 * Saves the store received: [uuid|null, data].
	 *
	 * @var array<int,array{0:?string,1:array<string,mixed>}>
	 */
	private array $saves = [];

	/**
	 * Uuids the store deleted.
	 *
	 * @var string[]
	 */
	private array $deletes = [];

	/**
	 * Remove temporary app directories.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ($this->dirs as $dir) {
			@unlink($dir . '/lib/Settings/connections.json');
			@rmdir($dir . '/lib/Settings');
			@rmdir($dir . '/lib');
			@rmdir($dir);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Make an app directory holding a declaration file.
	 *
	 * @param mixed $declaration The decoded file, or a raw string.
	 *
	 * @return string The app path.
	 */
	private function appDir(mixed $declaration): string {
		$dir = sys_get_temp_dir() . '/integriq-conn-' . bin2hex(random_bytes(6));
		mkdir($dir . '/lib/Settings', 0777, true);
		$content = is_string($declaration) === true ? $declaration : (string)json_encode($declaration);
		file_put_contents($dir . '/lib/Settings/connections.json', $content);
		$this->dirs[] = $dir;

		return $dir;
	}//end appDir()

	/**
	 * A two-entry dossiq declaration.
	 *
	 * @return array<string,mixed>
	 */
	private function dossiqDeclaration(): array {
		return [
			'app' => 'dossiq',
			'connections' => [
				['key' => 'zgw', 'title' => 'ZGW APIs', 'order' => 10, 'requiredConfig' => ['register']],
				['key' => 'brp', 'title' => 'BRP', 'order' => 80, 'sourceTemplate' => 'brp-haalcentraal'],
			],
		];
	}//end dossiqDeclaration()

	/**
	 * Build the service over an app manager and an in-memory store.
	 *
	 * @param array<string,string> $appPaths App id => path of every enabled app.
	 * @param LoggerInterface|null $logger A logger double, or null for a silent one.
	 * @param string[] $disabled App ids that are installed but disabled.
	 *
	 * @return ConnectionRegistryService
	 */
	private function makeService(array $appPaths, ?LoggerInterface $logger = null, array $disabled = []): ConnectionRegistryService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getEnabledApps')->willReturn(array_keys($appPaths));
		$appManager->method('getAppPath')->willReturnCallback(
			static function (string $app) use ($appPaths): string {
				if (isset($appPaths[$app]) === false) {
					throw new \OCP\App\AppPathNotFoundException('no ' . $app);
				}

				return $appPaths[$app];
			}
		);
		$appManager->method('getAppVersion')->willReturn('1.2.0');
		$appManager->method('isEnabledForAnyone')->willReturnCallback(
			static fn (string $app): bool => in_array($app, $disabled, true) === false
		);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturn('');
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-09-14T12:00:00+00:00'));

		return new ConnectionRegistryService(
			$appManager,
			new ConnectionDeclarationValidator(),
			new ConnectionStatusResolver($appConfig, $time),
			$this->makeStore(),
			$logger ?? $this->createMock(LoggerInterface::class)
		);
	}//end makeService()

	/**
	 * An in-memory ConnectionStore double. `payload()` stays real.
	 *
	 * @return ConnectionStore&MockObject
	 */
	private function makeStore(): ConnectionStore {
		$store = $this->getMockBuilder(ConnectionStore::class)
			->disableOriginalConstructor()
			->onlyMethods(['findRows', 'save', 'delete'])
			->getMock();

		$store->method('findRows')->willReturnCallback(
			function (?string $app = null): array {
				$rows = [];
				foreach ($this->rows as $uuid => $data) {
					if ($app === null || $data['app'] === $app) {
						$rows[] = ['uuid' => $uuid, 'data' => $data];
					}
				}

				return $rows;
			}
		);
		$store->method('save')->willReturnCallback(
			function (array $data, ?string $uuid = null): string {
				$this->saves[] = [$uuid, $data];
				$uuid = $uuid ?? 'uuid-' . count($this->rows);
				$this->rows[$uuid] = $data;
				return $uuid;
			}
		);
		$store->method('delete')->willReturnCallback(
			function (string $uuid): void {
				$this->deletes[] = $uuid;
				unset($this->rows[$uuid]);
			}
		);

		return $store;
	}//end makeStore()

	/**
	 * A valid file becomes one row per entry, slugged connection-{app}-{key}.
	 *
	 * @return void
	 */
	public function testValidFileBecomesOneRowPerEntry(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);

		$summary = $service->sync();

		$this->assertSame(2, $summary['created']);
		$this->assertCount(2, $this->rows);
		$slugs = array_column(array_values($this->rows), 'slug');
		$this->assertSame(['connection-dossiq-zgw', 'connection-dossiq-brp'], $slugs);
		$first = array_values($this->rows)[0];
		$this->assertSame('dossiq', $first['app']);
		$this->assertSame('1.2.0', $first['declaredVersion']);
		$this->assertSame('unconfigured', $first['status']);
	}//end testValidFileBecomesOneRowPerEntry()

	/**
	 * Running the sync twice writes nothing the second time.
	 *
	 * @return void
	 */
	public function testSyncIsIdempotent(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);
		$service->sync();
		$uuids = array_keys($this->rows);
		$this->saves = [];

		$summary = $service->sync();

		$this->assertSame([], $this->saves);
		$this->assertSame([], $this->deletes);
		$this->assertSame(2, $summary['unchanged']);
		$this->assertSame($uuids, array_keys($this->rows));
	}//end testSyncIsIdempotent()

	/**
	 * An invalid file is skipped whole, and the error names app and path.
	 *
	 * @return void
	 */
	public function testInvalidFileIsSkippedWhole(): void {
		$declaration = $this->dossiqDeclaration();
		unset($declaration['connections'][1]['title']);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			$this->stringContains('skipped the connections.json'),
			$this->callback(
				static fn (array $context): bool => $context['declaringApp'] === 'dossiq'
					&& str_contains($context['errors'], '/connections/1/title')
			)
		);

		$summary = $this->makeService(['dossiq' => $this->appDir($declaration)], $logger)->sync();

		$this->assertSame([], $this->saves);
		$this->assertSame(['dossiq'], $summary['skipped']);
	}//end testInvalidFileIsSkippedWhole()

	/**
	 * A file that is not JSON is skipped with an error.
	 *
	 * @return void
	 */
	public function testBrokenJsonIsSkipped(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$this->makeService(['dossiq' => $this->appDir('{"app": "dossiq",')], $logger)->sync();

		$this->assertSame([], $this->saves);
	}//end testBrokenJsonIsSkipped()

	/**
	 * A file claiming another app's id is refused and changes no row.
	 *
	 * @return void
	 */
	public function testFileClaimingAnotherAppIsRefused(): void {
		$this->rows['existing'] = ['app' => 'dossiq', 'key' => 'zgw', 'title' => 'ZGW APIs', 'declaration' => []];

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error')->with(
			$this->stringContains('claims the app id'),
			$this->callback(static fn (array $context): bool => $context['declaringApp'] === 'pipelinq' && $context['claimedApp'] === 'dossiq')
		);

		$this->makeService(['pipelinq' => $this->appDir($this->dossiqDeclaration())], $logger)->sync();

		$this->assertSame([], $this->saves);
		$this->assertSame([], $this->deletes);
	}//end testFileClaimingAnotherAppIsRefused()

	/**
	 * A removed key without a source is deleted.
	 *
	 * @return void
	 */
	public function testRemovedKeyWithoutSourceIsDeleted(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);
		$service->sync();
		$brpUuid = array_search('brp', array_column($this->rows, 'key', null), true);
		$brpUuid = array_keys($this->rows)[$brpUuid];

		$declaration = $this->dossiqDeclaration();
		unset($declaration['connections'][1]);
		$this->makeService(['dossiq' => $this->appDir($declaration)])->sync();

		$this->assertSame([$brpUuid], $this->deletes);
		$this->assertCount(1, $this->rows);
	}//end testRemovedKeyWithoutSourceIsDeleted()

	/**
	 * A removed key with a linked source is kept and marked unavailable, and
	 * stays so after a later resolve with a passing probe.
	 *
	 * @return void
	 */
	public function testRemovedKeyWithSourceIsKept(): void {
		$this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())])->sync();
		$brpUuid = '';
		foreach ($this->rows as $uuid => $data) {
			if ($data['key'] === 'brp') {
				$brpUuid = $uuid;
				$this->rows[$uuid]['source'] = 'a1b2c3d4-0000-4000-8000-000000000001';
			}
		}

		$declaration = $this->dossiqDeclaration();
		unset($declaration['connections'][1]);
		$service = $this->makeService(['dossiq' => $this->appDir($declaration)]);
		$service->sync();

		$this->assertSame([], $this->deletes);
		$this->assertSame('a1b2c3d4-0000-4000-8000-000000000001', $this->rows[$brpUuid]['source']);
		$this->assertSame('unavailable', $this->rows[$brpUuid]['status']);
		$this->assertSame('No longer declared by dossiq.', $this->rows[$brpUuid]['statusMessage']);

		$row = $this->rows[$brpUuid];
		$row['lastProbe'] = ['status' => 'ok', 'message' => 'HTTP 200', 'at' => '2026-09-14T13:00:00+00:00'];
		$this->assertSame('unavailable', $service->resolveRow($row)['status']);
	}//end testRemovedKeyWithSourceIsKept()

	/**
	 * A report reaches the row as lastReport and the row is resolved.
	 *
	 * @return void
	 */
	public function testReportReachesTheRow(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);
		$service->sync();
		$this->saves = [];

		$this->assertTrue($service->report('dossiq', 'brp', 'configured', 'Logged in'));

		$this->assertCount(1, $this->saves);
		$saved = $this->saves[0][1];
		$this->assertSame(['status' => 'configured', 'message' => 'Logged in', 'at' => '2026-09-14T12:00:00+00:00'], $saved['lastReport']);
		$this->assertSame('configured', $saved['status']);
		$this->assertSame('Logged in', $saved['statusMessage']);
	}//end testReportReachesTheRow()

	/**
	 * A report for an undeclared key is refused with a warning and changes nothing.
	 *
	 * @return void
	 */
	public function testReportForUnknownKeyIsRefused(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			$this->stringContains('no such connection is declared'),
			$this->callback(static fn (array $context): bool => $context['reportingApp'] === 'dossiq' && $context['key'] === 'mailbox')
		);

		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())], $logger);
		$service->sync();
		$this->saves = [];

		$this->assertFalse($service->report('dossiq', 'mailbox', 'configured', 'Logged in'));
		$this->assertSame([], $this->saves);
	}//end testReportForUnknownKeyIsRefused()

	/**
	 * A report with an unknown status is refused with a warning.
	 *
	 * @return void
	 */
	public function testReportWithUnknownStatusIsRefused(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning');

		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())], $logger);
		$service->sync();
		$this->saves = [];

		$this->assertFalse($service->report('dossiq', 'brp', 'green', 'fine'));
		$this->assertSame([], $this->saves);
	}//end testReportWithUnknownStatusIsRefused()

	/**
	 * Syncing a disabled app resolves its rows to D4 rule 1.
	 *
	 * @return void
	 */
	public function testSyncOfDisabledAppResolvesRuleOne(): void {
		$this->rows['r1'] = ['app' => 'shillinq', 'key' => 'bank', 'title' => 'Bank', 'declaration' => [], 'status' => 'configured'];

		$saved = $this->makeService([], null, ['shillinq'])->sync('shillinq');

		$this->assertSame(0, $saved['created']);
		$this->assertSame('unavailable', $this->rows['r1']['status']);
		$this->assertSame('The shillinq app is disabled.', $this->rows['r1']['statusMessage']);
	}//end testSyncOfDisabledAppResolvesRuleOne()

	/**
	 * Refresh saves only rows whose status changed, and honours the key filter.
	 *
	 * @return void
	 */
	public function testRefreshSavesOnlyChangedRows(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);
		$service->sync();
		$this->saves = [];

		$this->assertSame(0, $service->refresh('dossiq'));
		$this->assertSame(0, $service->refresh('dossiq', 'zgw'));
		$this->assertSame([], $this->saves);
	}//end testRefreshSavesOnlyChangedRows()

	/**
	 * The hourly job syncs an app whose version moved, and an app with a file but no rows.
	 *
	 * @return void
	 */
	public function testSyncChangedDeclarationsPicksMovedVersions(): void {
		$service = $this->makeService(['dossiq' => $this->appDir($this->dossiqDeclaration())]);

		$this->assertSame(['dossiq'], $service->syncChangedDeclarations());
		$this->assertCount(2, $this->rows);

		$this->assertSame([], $service->syncChangedDeclarations());

		foreach (array_keys($this->rows) as $uuid) {
			$this->rows[$uuid]['declaredVersion'] = '1.1.0';
		}

		$this->assertSame(['dossiq'], $service->syncChangedDeclarations());
		$this->assertSame('1.2.0', array_values($this->rows)[0]['declaredVersion']);
	}//end testSyncChangedDeclarationsPicksMovedVersions()

	/**
	 * An app without a declaration file and without rows is left alone.
	 *
	 * @return void
	 */
	public function testAppWithoutFileIsIgnored(): void {
		$dir = sys_get_temp_dir() . '/integriq-conn-none-' . bin2hex(random_bytes(4));

		$summary = $this->makeService(['files' => $dir])->sync();

		$this->assertSame(0, $summary['created']);
		$this->assertSame([], $summary['skipped']);
		$this->assertSame([], $this->saves);
	}//end testAppWithoutFileIsIgnored()
}//end class
