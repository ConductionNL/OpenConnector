<?php

/**
 * Unit tests for ConnectionHealthJob (connection-registry, umbrella D7).
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\BackgroundJob
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-health-job-probes-linked-sources-every-hour-req-conn-005
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\BackgroundJob;

use OCA\Integriq\BackgroundJob\ConnectionHealthJob;
use OCA\Integriq\Service\ConnectionProbeService;
use OCA\Integriq\Service\ConnectionRegistryService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * The job runs its three phases, isolates failures, and runs hourly.
 */
class ConnectionHealthJobTest extends TestCase {

	/**
	 * Invoke the protected run().
	 *
	 * @param ConnectionHealthJob $job The job.
	 *
	 * @return void
	 */
	private function runJob(ConnectionHealthJob $job): void {
		$method = new \ReflectionMethod($job, 'run');
		$method->invoke($job, null);
	}//end runJob()

	/**
	 * Build a container answering with the two services.
	 *
	 * @param ConnectionRegistryService $registry The registry double.
	 * @param ConnectionProbeService $probe The probe double.
	 *
	 * @return ContainerInterface
	 */
	private function container(ConnectionRegistryService $registry, ConnectionProbeService $probe): ContainerInterface {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			static fn (string $id): object => ($id === ConnectionProbeService::class) ? $probe : $registry
		);

		return $container;
	}//end container()

	/**
	 * The interval is 3600 seconds.
	 *
	 * @return void
	 */
	public function testRunsHourly(): void {
		$job = new ConnectionHealthJob($this->createMock(ITimeFactory::class), $this->createMock(ContainerInterface::class), $this->createMock(LoggerInterface::class));

		$interval = new \ReflectionProperty(\OCP\BackgroundJob\TimedJob::class, 'interval');
		$this->assertSame(3600, $interval->getValue($job));
	}//end testRunsHourly()

	/**
	 * The job syncs moved declarations, refreshes, and probes with the cap of 25.
	 *
	 * @return void
	 */
	public function testRunsAllPhasesWithTheCap(): void {
		$registry = $this->getMockBuilder(ConnectionRegistryService::class)->disableOriginalConstructor()
			->onlyMethods(['syncChangedDeclarations', 'refresh'])->getMock();
		$registry->expects($this->once())->method('syncChangedDeclarations')->willReturn([]);
		$registry->expects($this->once())->method('refresh')->willReturn(0);

		$probe = $this->getMockBuilder(ConnectionProbeService::class)->disableOriginalConstructor()
			->onlyMethods(['probeDue'])->getMock();
		$probe->expects($this->once())->method('probeDue')->with(25)->willReturn(0);

		$this->runJob(new ConnectionHealthJob($this->createMock(ITimeFactory::class), $this->container($registry, $probe), $this->createMock(LoggerInterface::class)));
	}//end testRunsAllPhasesWithTheCap()

	/**
	 * A failing sync is logged and the probes still run.
	 *
	 * @return void
	 */
	public function testFailingPhaseDoesNotStopTheNext(): void {
		$registry = $this->getMockBuilder(ConnectionRegistryService::class)->disableOriginalConstructor()
			->onlyMethods(['syncChangedDeclarations', 'refresh'])->getMock();
		$registry->method('syncChangedDeclarations')->willThrowException(new \RuntimeException('file system gone'));
		$registry->method('refresh')->willThrowException(new \RuntimeException('database gone'));

		$probe = $this->getMockBuilder(ConnectionProbeService::class)->disableOriginalConstructor()
			->onlyMethods(['probeDue'])->getMock();
		$probe->expects($this->once())->method('probeDue')->willReturn(3);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->exactly(2))->method('error');

		$this->runJob(new ConnectionHealthJob($this->createMock(ITimeFactory::class), $this->container($registry, $probe), $logger));
	}//end testFailingPhaseDoesNotStopTheNext()
}//end class
