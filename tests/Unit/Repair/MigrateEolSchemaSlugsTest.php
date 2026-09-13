<?php

/**
 * Tests for the endoflife.date schema-slug migration.
 *
 * @category  Test
 * @package   OCA\Integriq\Tests\Unit\Repair
 * @author    Conduction B.V. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://www.conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Repair;

use OCA\Integriq\Repair\MigrateEolSchemaSlugs;
use OCP\IDBConnection;
use OCP\Migration\IRepairStep;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The schema-slug migration's decision table.
 *
 * The planner is pure, so it is exercised directly rather than through a mocked
 * connection: what has to be right is which pairs it renames, which it skips and
 * which it refuses, and none of that needs a database.
 *
 * PHPUnit assertions take positional arguments; the named-parameter sniff does
 * not apply to them.
 *
 * phpcs:disable CustomSniffs.Functions.NamedParameters
 *
 * @covers \OCA\Integriq\Repair\MigrateEolSchemaSlugs
 *
 * @spec openspec/specs/endoflife-date-source/spec.md
 */
final class MigrateEolSchemaSlugsTest extends TestCase {

	/**
	 * The step under test.
	 *
	 * @var MigrateEolSchemaSlugs
	 */
	private MigrateEolSchemaSlugs $step;

	/**
	 * Set up the subject with mocked collaborators the planner never touches.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->step = new MigrateEolSchemaSlugs(
			$this->createMock(IDBConnection::class),
			$this->createMock(LoggerInterface::class),
		);

	}//end setUp()

	/**
	 * The step is a repair step and names itself.
	 *
	 * @return void
	 */
	public function testItIsARepairStepWithAName(): void {
		$this->assertInstanceOf(IRepairStep::class, $this->step);
		$this->assertNotSame('', $this->step->getName());

	}//end testItIsARepairStepWithAName()

	/**
	 * The map moves both camelCase slugs and nothing else.
	 *
	 * These two were the only camelCase slugs in the register; a third entry
	 * appearing here means somebody widened a rename that was scoped on purpose.
	 *
	 * @return void
	 */
	public function testTheMapCoversExactlyTheTwoCamelCaseSlugs(): void {
		$this->assertSame(
			['eolProduct' => 'eol_product', 'eolCycle' => 'eol_cycle'],
			MigrateEolSchemaSlugs::SLUG_MAP
		);

	}//end testTheMapCoversExactlyTheTwoCamelCaseSlugs()

	/**
	 * A pair whose old slug is present and new slug absent is renamed.
	 *
	 * @return void
	 */
	public function testItRenamesWhenOnlyTheOldSlugExists(): void {
		$plan = $this->step->plan(
			MigrateEolSchemaSlugs::SLUG_MAP,
			['eolProduct', 'eolCycle']
		);

		$this->assertSame(
			['eolProduct' => 'eol_product', 'eolCycle' => 'eol_cycle'],
			$plan['renames']
		);
		$this->assertSame([], $plan['refused']);

	}//end testItRenamesWhenOnlyTheOldSlugExists()

	/**
	 * A second run has nothing to do.
	 *
	 * Idempotence is the property that lets this ship in `<install>` as well as
	 * `<post-migration>`, so it is asserted rather than assumed.
	 *
	 * @return void
	 */
	public function testASecondRunPlansNothing(): void {
		$plan = $this->step->plan(
			MigrateEolSchemaSlugs::SLUG_MAP,
			['eol_product', 'eol_cycle']
		);

		$this->assertSame([], $plan['renames']);
		$this->assertSame([], $plan['refused']);

	}//end testASecondRunPlansNothing()

	/**
	 * Both slugs present is refused, not merged.
	 *
	 * Two rows sharing a slug means the lower id silently wins every lookup and
	 * the other row's objects become unreachable. Choosing between them is a
	 * decision about data, not a migration.
	 *
	 * @return void
	 */
	public function testItRefusesWhenBothSlugsExist(): void {
		$plan = $this->step->plan(
			MigrateEolSchemaSlugs::SLUG_MAP,
			['eolProduct', 'eol_product', 'eolCycle']
		);

		$this->assertSame(['eolCycle' => 'eol_cycle'], $plan['renames']);
		$this->assertArrayHasKey('eolProduct', $plan['refused']);

	}//end testItRefusesWhenBothSlugsExist()

	/**
	 * An install that never had the old schemas plans nothing.
	 *
	 * @return void
	 */
	public function testAFreshInstallPlansNothing(): void {
		$plan = $this->step->plan(MigrateEolSchemaSlugs::SLUG_MAP, []);

		$this->assertSame([], $plan['renames']);
		$this->assertSame([], $plan['refused']);

	}//end testAFreshInstallPlansNothing()
}//end class
