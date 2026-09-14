<?php

/**
 * Unit tests for ConnectionDeclarationValidator (connection-registry, umbrella D2).
 *
 * Every fixture runs through the hand-written validator AND through
 * lib/Settings/connections.schema.json with opis/json-schema, and the two must
 * agree. That is the guard against the runtime validator and the published
 * schema drifting apart.
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/connection-registry/specs/connection-registry/spec.md#requirement-the-sync-turns-declaration-files-into-connection-rows-req-conn-001
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Service;

use OCA\Integriq\Service\ConnectionDeclarationValidator;
use Opis\JsonSchema\Validator as OpisValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validator and JSON Schema agree on valid and invalid files.
 */
class ConnectionDeclarationValidatorTest extends TestCase {

	/**
	 * A valid file shaped like dossiq's declaration.
	 *
	 * @return array<string,mixed>
	 */
	private static function validFile(): array {
		return [
			'app' => 'dossiq',
			'connections' => [
				[
					'key' => 'zgw',
					'title' => 'ZGW APIs',
					'description' => 'Zaken and Documenten.',
					'order' => 10,
					'settingsUrl' => '/settings/admin/dossiq#section-zgw',
					'requiredConfig' => ['register', 'case_schema'],
				],
				[
					'key' => 'berichtenbox',
					'title' => 'Berichtenbox',
					'adapter' => ['configKey' => 'berichtenbox_adapter', 'simulatedMessage' => 'A mock answers.'],
					'sourceTemplate' => 'berichtenbox',
					'unconfiguredMessage' => 'Set berichtenbox_adapter first.',
				],
				[
					'key' => 'kvk',
					'title' => 'KvK',
					'available' => false,
					'unavailableMessage' => 'Not called yet.',
				],
			],
		];
	}//end validFile()

	/**
	 * Fixtures: name => [data, expected valid].
	 *
	 * @return array<string,array{0:mixed,1:bool}>
	 */
	public static function fixtures(): array {
		$valid = self::validFile();

		$noTitle = $valid;
		unset($noTitle['connections'][0]['title']);

		$badKey = $valid;
		$badKey['connections'][0]['key'] = 'Bad_Key';

		$extraField = $valid;
		$extraField['connections'][1]['settingUrl'] = '/typo';

		$relativeUrl = $valid;
		$relativeUrl['connections'][0]['settingsUrl'] = 'settings/admin';

		$stringOrder = $valid;
		$stringOrder['connections'][0]['order'] = '10';

		$badRequired = $valid;
		$badRequired['connections'][0]['requiredConfig'] = ['register', ''];

		$badAdapter = $valid;
		$badAdapter['connections'][1]['adapter'] = ['configKey' => 'x', 'className' => 'y'];

		$noApp = $valid;
		unset($noApp['app']);

		$badUnconfigured = $valid;
		$badUnconfigured['connections'][1]['unconfiguredMessage'] = ['not', 'a', 'string'];

		$badAvailable = $valid;
		$badAvailable['connections'][2]['available'] = 'no';

		return [
			'valid file' => [$valid, true],
			'empty connections' => [['app' => 'dossiq', 'connections' => []], true],
			'with $schema pointer' => [['$schema' => './connections.schema.json', 'app' => 'dossiq', 'connections' => []], true],
			'entry without title' => [$noTitle, false],
			'key not kebab case' => [$badKey, false],
			'unknown entry field' => [$extraField, false],
			'relative settingsUrl' => [$relativeUrl, false],
			'order as string' => [$stringOrder, false],
			'empty required key' => [$badRequired, false],
			'unknown adapter field' => [$badAdapter, false],
			'no app' => [$noApp, false],
			'available not boolean' => [$badAvailable, false],
			'unconfiguredMessage not a string' => [$badUnconfigured, false],
			'list instead of object' => [[1, 2], false],
		];
	}//end fixtures()

	/**
	 * The validator gives the expected verdict, and the schema agrees.
	 *
	 * @param mixed $data The decoded file.
	 * @param bool $expected Whether the file is valid.
	 *
	 * @return void
	 */
	#[DataProvider('fixtures')]
	public function testValidatorAndSchemaAgree(mixed $data, bool $expected): void {
		$errors = (new ConnectionDeclarationValidator())->validate($data);
		$this->assertSame(expected: $expected, actual: $errors === [], message: 'Validator: ' . implode('; ', $errors));

		$schema = json_decode((string)file_get_contents(__DIR__ . '/../../../lib/Settings/connections.schema.json'));
		$result = (new OpisValidator())->validate(json_decode((string)json_encode($data)), $schema);
		$this->assertSame(expected: $expected, actual: $result->isValid(), message: 'JSON Schema disagrees with the validator');
	}//end testValidatorAndSchemaAgree()

	/**
	 * The error names the failing path.
	 *
	 * @return void
	 */
	public function testErrorNamesTheFailingPath(): void {
		$data = self::validFile();
		unset($data['connections'][1]['title']);

		$errors = (new ConnectionDeclarationValidator())->validate($data);

		$this->assertSame(expected: ['/connections/1/title: is required'], actual: $errors);
	}//end testErrorNamesTheFailingPath()

	/**
	 * A duplicate key is refused, which JSON Schema cannot express.
	 *
	 * @return void
	 */
	public function testDuplicateKeyIsRefused(): void {
		$data = self::validFile();
		$data['connections'][2]['key'] = 'zgw';

		$errors = (new ConnectionDeclarationValidator())->validate($data);

		$this->assertSame(expected: ['/connections/2/key: duplicates key "zgw"'], actual: $errors);
	}//end testDuplicateKeyIsRefused()
}//end class
