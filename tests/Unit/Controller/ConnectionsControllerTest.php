<?php

/**
 * Unit tests for ConnectionsController (connection-registry, umbrella D9).
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-add-integration-links-a-source-and-probes-it-at-once-req-conn-007
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Controller;

use OCA\Integriq\Controller\ConnectionsController;
use OCA\Integriq\Exception\ConnectionLinkException;
use OCA\Integriq\Service\ConnectionProbeService;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Request parsing and error translation of the link endpoint.
 */
class ConnectionsControllerTest extends TestCase {

	/**
	 * The probe service double.
	 *
	 * @var ConnectionProbeService&MockObject
	 */
	private ConnectionProbeService $probe;

	/**
	 * Build the probe double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->probe = $this->getMockBuilder(ConnectionProbeService::class)->disableOriginalConstructor()
			->onlyMethods(['linkSource', 'linkTemplate'])->getMock();
	}//end setUp()

	/**
	 * Build the controller over request params.
	 *
	 * @param array<string,mixed> $params The request params.
	 *
	 * @return ConnectionsController
	 */
	private function controller(array $params): ConnectionsController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters)
		);

		return new ConnectionsController('integriq', $request, $this->probe, $l10n, $this->createMock(LoggerInterface::class));
	}//end controller()

	/**
	 * Neither a source nor a template is a 400.
	 *
	 * @return void
	 */
	public function testMissingChoiceIsBadRequest(): void {
		$this->probe->expects($this->never())->method('linkSource');

		$response = $this->controller([])->link('c-1');

		$this->assertSame(400, $response->getStatus());
	}//end testMissingChoiceIsBadRequest()

	/**
	 * A source link returns the row and the probe.
	 *
	 * @return void
	 */
	public function testLinkSourceReturnsRowAndProbe(): void {
		$probe = ['status' => 'ok', 'message' => 'The source answered with HTTP 200.', 'at' => '2026-09-14T12:00:00+00:00'];
		$this->probe->expects($this->once())->method('linkSource')->with('c-1', 's-1')
			->willReturn(['uuid' => 'c-1', 'data' => ['key' => 'kvk', 'source' => 's-1', 'lastProbe' => $probe]]);

		$response = $this->controller(['source' => 's-1'])->link('c-1');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame($probe, $response->getData()['probe']);
		$this->assertSame('c-1', $response->getData()['connection']['id']);
	}//end testLinkSourceReturnsRowAndProbe()

	/**
	 * fromTemplate calls the template link.
	 *
	 * @return void
	 */
	public function testFromTemplateUsesTemplateLink(): void {
		$this->probe->expects($this->once())->method('linkTemplate')->with('c-1')
			->willReturn(['uuid' => 'c-1', 'data' => ['source' => 's-9']]);

		$response = $this->controller(['fromTemplate' => 'true'])->link('c-1');

		$this->assertSame(200, $response->getStatus());
	}//end testFromTemplateUsesTemplateLink()

	/**
	 * Each refusal maps to its status.
	 *
	 * @return void
	 */
	public function testRefusalsMapToStatuses(): void {
		$cases = [
			ConnectionLinkException::ALREADY_LINKED => 409,
			ConnectionLinkException::CONNECTION_NOT_FOUND => 404,
			ConnectionLinkException::SOURCE_NOT_FOUND => 404,
			ConnectionLinkException::NO_TEMPLATE => 409,
			ConnectionLinkException::TEMPLATE_NOT_FOUND => 404,
		];
		$reasons = array_keys($cases);
		$this->probe->method('linkSource')->willReturnCallback(
			static function () use (&$reasons): array {
				throw new ConnectionLinkException(array_shift($reasons));
			}
		);

		foreach ($cases as $reason => $status) {
			$response = $this->controller(['source' => 's-1'])->link('c-1');
			$this->assertSame($status, $response->getStatus(), $reason);
			$this->assertNotSame('', $response->getData()['error']);
		}
	}//end testRefusalsMapToStatuses()

	/**
	 * An unexpected failure is a 500 with a readable error.
	 *
	 * @return void
	 */
	public function testUnexpectedFailureIsServerError(): void {
		$this->probe->method('linkSource')->willThrowException(new \RuntimeException('database gone'));

		$response = $this->controller(['source' => 's-1'])->link('c-1');

		$this->assertSame(500, $response->getStatus());
		$this->assertSame('The source could not be linked: database gone', $response->getData()['error']);
	}//end testUnexpectedFailureIsServerError()
}//end class
