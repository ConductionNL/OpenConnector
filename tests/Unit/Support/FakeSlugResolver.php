<?php

/**
 * A register-slug resolver bound to a fixed instance state.
 *
 * @category Test
 * @package  OCA\Integriq\Tests\Unit\Support
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Integriq\Tests\Unit\Support;

use OCA\OpenRegister\Contract\RegisterSlugResolution;
use OCA\OpenRegister\Contract\RegisterSlugResolverInterface;

/**
 * Answers as an instance that carries exactly the given register slugs.
 *
 * Hand written rather than `createMock()`, and the reason is the point of the
 * tests it serves. What those check is the DIFFERENCE between a migrated and an
 * unmigrated instance, and that difference IS the resolver's behaviour. A mock
 * stubbed to return one slug behaves identically on both, so a test built on one
 * would pass whether or not the code under test used the answer at all.
 *
 * The candidate list is stated here rather than read from
 * `RegisterSlugAliases`, which lives in openregister's `lib/Support/` and is not
 * published to consumers. Only the register this app owns is listed: a double
 * carrying the whole fleet map would be a second copy of OpenRegister's truth,
 * maintained here by someone who does not own it, which is what the published
 * resolver exists to prevent.
 */
final class FakeSlugResolver implements RegisterSlugResolverInterface {

	/**
	 * The slug history of the one register this app owns.
	 *
	 * Newest first, matching openregister's `RegisterSlugAliases::ALIASES` entry
	 * for `integriq`, which is itself transcribed from this app's own
	 * {@see \OCA\Integriq\Repair\MigrateRegisterSlug}::SLUG_MAP.
	 *
	 * @var array<string, list<string>>
	 */
	private const CANDIDATES = ['integriq' => ['integriq', 'openconnector']];

	/**
	 * Constructor.
	 *
	 * @param list<string> $present The register slugs this instance carries.
	 */
	public function __construct(private readonly array $present=[]) {
	}//end __construct()

	/**
	 * Resolve against the fixed instance state.
	 *
	 * @param string       $canonical  The canonical register slug.
	 * @param list<string> $candidates Explicit candidates, newest first.
	 *
	 * @return RegisterSlugResolution The resolution.
	 */
	public function resolve(string $canonical, array $candidates=[]): RegisterSlugResolution {
		$probe = $candidates;
		if ($probe === []) {
			$probe = (self::CANDIDATES[$canonical] ?? [$canonical]);
		}

		$matched = array_values(
			array_filter($probe, fn (string $slug): bool => in_array($slug, $this->present, true))
		);

		$state = RegisterSlugResolution::RESOLVED;
		if ($matched === []) {
			$state = RegisterSlugResolution::ABSENT;
		} else if (count($matched) > 1) {
			$state = RegisterSlugResolution::AMBIGUOUS;
		}

		return new RegisterSlugResolution(
			canonical: $canonical,
			slug: ($matched[0] ?? null),
			state: $state,
			candidates: $probe,
			matched: $matched
		);
	}//end resolve()

	/**
	 * The slug to read with, or null.
	 *
	 * @param string       $canonical  The canonical register slug.
	 * @param list<string> $candidates Explicit candidates, newest first.
	 *
	 * @return string|null The slug, or null.
	 */
	public function slugOrNull(string $canonical, array $candidates=[]): ?string {
		return $this->resolve(canonical: $canonical, candidates: $candidates)->slug;
	}//end slugOrNull()
}//end class
