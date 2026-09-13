<?php

/**
 * Minimal stand-in for the OpenRegister service the drain resolves.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The migration guards its drain with a class_exists() probe on this name,
 * because openregister is not on the autoloader when `occ app:enable integriq`
 * runs. That makes the probe answer differently in the two places the suite
 * runs: false on a standalone clone, true in CI, where the app sits inside a
 * server checkout with openregister enabled.
 *
 * A test that wants the drain to proceed therefore cannot rely on either
 * answer. Declaring the class only when it is genuinely absent makes the probe
 * true in both, and leaves the real class untouched wherever it exists.
 */

declare(strict_types=1);

namespace OCA\OpenRegister\Service;

if (class_exists(ConfigurationService::class) === false) {
	/**
	 * Stub carrying only the method the migration calls.
	 */
	class ConfigurationService {
		/**
		 * @param string              $appId   Owning app id.
		 * @param array<string,mixed> $data    Decoded register descriptor.
		 * @param string              $version App version the import is tagged with.
		 *
		 * @return void
		 */
		public function importFromApp(string $appId, array $data, string $version): void {
		}
	}
}
