<?php

/**
 * Unit tests for SourceTestService (connection-registry, umbrella D7).
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-health-job-probes-linked-sources-every-hour-req-conn-005
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Service;

use OCA\Integriq\Service\CallService;
use OCA\Integriq\Service\SourceTestService;
use OCA\OpenRegister\Db\ObjectEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The shared test call: persistLog false, and three readable outcomes.
 */
class SourceTestServiceTest extends TestCase {

	/**
	 * A source entity.
	 *
	 * @return ObjectEntity
	 */
	private function source(): ObjectEntity {
		$source = new ObjectEntity();
		$source->setUuid('s-1');
		$source->setObject(['location' => 'https://example.nl']);

		return $source;
	}//end source()

	/**
	 * A call log entity.
	 *
	 * @param array<string,mixed> $data The log data.
	 *
	 * @return ObjectEntity
	 */
	private function callLog(array $data): ObjectEntity {
		$log = new ObjectEntity();
		$log->setObject($data);

		return $log;
	}//end callLog()

	/**
	 * A response comes back with its code, and no call log is persisted.
	 *
	 * @return void
	 */
	public function testResponseOutcome(): void {
		$callService = $this->getMockBuilder(CallService::class)->disableOriginalConstructor()->onlyMethods(['call'])->getMock();
		$callService->expects($this->once())->method('call')
			->with($this->anything(), '', 'GET', [], false, true, false, false, false, null, null, false)
			->willReturn($this->callLog(['response' => ['statusCode' => 503, 'statusMessage' => 'Service Unavailable']]));

		$outcome = (new SourceTestService($callService, $this->createMock(LoggerInterface::class)))->run($this->source());

		$this->assertSame(SourceTestService::OUTCOME_RESPONSE, $outcome['outcome']);
		$this->assertSame(503, $outcome['statusCode']);
		$this->assertSame('Service Unavailable', $outcome['statusMessage']);
	}//end testResponseOutcome()

	/**
	 * An early-exit log without a response is its own outcome.
	 *
	 * @return void
	 */
	public function testNoResponseOutcome(): void {
		$callService = $this->getMockBuilder(CallService::class)->disableOriginalConstructor()->onlyMethods(['call'])->getMock();
		$callService->method('call')->willReturn($this->callLog(['statusCode' => 409, 'statusMessage' => 'Source is disabled']));

		$outcome = (new SourceTestService($callService, $this->createMock(LoggerInterface::class)))->run($this->source());

		$this->assertSame(SourceTestService::OUTCOME_NO_RESPONSE, $outcome['outcome']);
		$this->assertSame(409, $outcome['statusCode']);
	}//end testNoResponseOutcome()

	/**
	 * An exception is caught, logged and returned as failed.
	 *
	 * @return void
	 */
	public function testFailedOutcome(): void {
		$callService = $this->getMockBuilder(CallService::class)->disableOriginalConstructor()->onlyMethods(['call'])->getMock();
		$callService->method('call')->willThrowException(new \RuntimeException('connection refused'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');

		$outcome = (new SourceTestService($callService, $logger))->run($this->source());

		$this->assertSame(SourceTestService::OUTCOME_FAILED, $outcome['outcome']);
		$this->assertSame('connection refused', $outcome['error']);
		$this->assertNull($outcome['statusCode']);
	}//end testFailedOutcome()
}//end class
