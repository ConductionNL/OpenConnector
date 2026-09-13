<?php

/**
 * No code under lib/ pins a superseded register slug.
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

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The case that has never once been caught.
 *
 * A consumer pinned to a superseded register slug on a MIGRATED instance does
 * not raise. `openregister_registers` holds no row with that slug, so the read
 * matches nothing and returns an empty result set, which is byte-for-byte what a
 * healthy but empty register returns. There is no exception, no 404, no log line
 * separating the two. Every other guard in this repository watches behaviour,
 * and this defect has no behaviour to watch: it is a feature that quietly stops
 * happening.
 *
 * So the guard is static, and repo-wide rather than diff-scoped. Diff scope is
 * right for debt a PR could reasonably be asked to carry. It is wrong here,
 * because every one of these references was written BEFORE the rename and will
 * therefore never appear in a diff again. A diff-scoped version of this test
 * passes on a repository full of the defect.
 *
 * ## What it does NOT catch
 *
 * It reads lines, not data flow. A superseded slug arriving from app config,
 * from a manifest, or through more than one assignment is invisible to it, as is
 * a `match` arm built at run time. That is why
 * {@see \OCA\Integriq\Tests\Unit\Controller\MappingsControllerRegisterResolutionTest}
 * sits beside it: this guard stops the literal being TYPED, and that one stops
 * the resolved slug being IGNORED.
 */
class RegisterSlugPinTest extends TestCase {

	/**
	 * Superseded register slug => the canonical slug replacing it.
	 *
	 * All ten, not just this app's own, and that is a departure from the
	 * narrower list buildiq's copy of this guard carries. The reason is what
	 * this app is: the fleet's service bus. It already writes into registers it
	 * does not own (`zaken`, `documenten` and `vng-gemma` all appear in
	 * register position under lib/), so it is the app most likely to be the one
	 * that types another app's register slug. A guard scoped to `openconnector`
	 * alone would watch the one slug this repository has already been cleaned
	 * of and miss the nine it is most exposed to.
	 *
	 * Transcribed from openregister's `lib/Support/RegisterSlugAliases.php`,
	 * which is the authority and is NOT published to consumers. Note that the
	 * list is not derivable from the app-rename map: `stackiq` renamed the
	 * register `voorzieningen`, while its former app id `softwarecatalog` was
	 * never a register slug on any instance.
	 *
	 * @var array<string, string>
	 */
	private const SUPERSEDED = [
		'openconnector'   => 'integriq',
		'openbuild'       => 'buildiq',
		'decidesk'        => 'decidiq',
		'hrmq'            => 'humaniq',
		'larpingapp'      => 'larpinq',
		'planix'          => 'planninq',
		'voorzieningen'   => 'stackiq',
		'procest'         => 'dossiq',
		'procest-default' => 'dossiq-default',
		'scholiq'         => 'learniq',
	];

	/**
	 * Files allowed to name a superseded slug, and why.
	 *
	 * Each entry must be a genuine exception, a file that exists in order to
	 * name the old slug, never a deferral. Anything else belongs in a resolver
	 * call.
	 *
	 * There is exactly one, and it is the rename itself: the repair step whose
	 * `SLUG_MAP` is the authority openregister's `RegisterSlugAliases`
	 * transcribes this app's entry from. A guard that flagged the migration for
	 * naming the thing it migrates would be a guard someone turns off.
	 *
	 * @var array<string, string>
	 */
	private const ALLOWED = [
		'lib/Repair/MigrateRegisterSlug.php' => 'the rename itself; its SLUG_MAP is the authority for what this register was called',
	];

	/**
	 * Source patterns that put a string literal in REGISTER position.
	 *
	 * Deliberately narrow. A slug is only a defect where it identifies a
	 * register; the same word in a log message, an app id, a StUF `applicatie`
	 * element, a credential broker's `allowedApps` entry or a webhook signature
	 * scheme is a different string that happens to read the same, and a guard
	 * flagging those would be turned off. All five of those shapes are live in
	 * this repository today and none of them is this defect.
	 *
	 * The last three cover the NULL-COALESCING DEFAULT, and they are not
	 * decoration: `register: ($data['register'] ?? 'openconnector')` in
	 * MappingsController was the pin this guard was written for, and the five
	 * patterns inherited from buildiq all missed it, because each of those
	 * requires the quote to follow `register:` directly. The contract's own
	 * docblock names `?->slug ?? 'literal'` as the shape that reinstates the
	 * defect, so the guard has to read it.
	 *
	 * @var list<string>
	 */
	private const REGISTER_POSITION = [
		'/setRegister\(\s*(?:register:\s*)?\'([a-zA-Z0-9_-]+)\'/',
		'/\bregister:\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\'register\'\s*=>\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\bconst\s+[A-Z0-9_]*REGISTER[A-Z0-9_]*\s*=\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\$[a-zA-Z0-9_]*(?:[Rr]egister|[Ss]lug)[a-zA-Z0-9_]*\s*=\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\bregister:\s*\(?[^,()]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\'register\'\s*=>\s*\(?[^,()]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/',
		'/\$[a-zA-Z0-9_]*(?:[Rr]egister|[Ss]lug)[a-zA-Z0-9_]*\s*=\s*[^;]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/',
	];

	/**
	 * No file under lib/ names a superseded register slug in register position.
	 *
	 * @return void
	 */
	public function testNoSourceFilePinsASupersededRegisterSlug(): void {
		$findings = [];
		foreach ($this->sourceFiles() as $relative => $absolute) {
			if (isset(self::ALLOWED[$relative]) === true) {
				continue;
			}

			// NOT FILE_SKIP_EMPTY_LINES. Skipping blank lines renumbers every
			// line after the first one, so `$index + 1` stops being the line
			// number and becomes the count of non-blank lines. Measured on
			// openregister's reconciler: a pin on line 590 was reported as line
			// 528, because 62 blank lines preceded it. A guard that names the
			// wrong line is a guard whose next reader concludes it is broken.
			$lines = file($absolute, FILE_IGNORE_NEW_LINES);
			if ($lines === false) {
				continue;
			}

			foreach ($lines as $index => $line) {
				foreach (self::REGISTER_POSITION as $pattern) {
					if (preg_match($pattern, $line, $matches) !== 1) {
						continue;
					}

					$slug = strtolower($matches[1]);
					if (isset(self::SUPERSEDED[$slug]) === false) {
						continue;
					}

					$findings[] = sprintf(
						'%s:%d pins the superseded register slug \'%s\'. Resolve \'%s\' through '
						. 'RegisterSlugResolverInterface::resolve() instead, and branch on isResolved(), '
						. 'because reading with a slug this instance does not carry returns zero rows, '
						. 'not an error.',
						$relative,
						($index + 1),
						$slug,
						self::SUPERSEDED[$slug]
					);
				}
			}
		}//end foreach

		$this->assertSame([], $findings, "Superseded register slugs are pinned:\n" . implode("\n", $findings));
	}//end testNoSourceFilePinsASupersededRegisterSlug()

	/**
	 * The guard actually looks at something.
	 *
	 * A file walker that silently finds no files is the classic hollow green:
	 * the assertion above would pass on an empty list forever. This pins the
	 * walker to a floor well below the real count, so a broken path fails here
	 * rather than passing there.
	 *
	 * @return void
	 */
	public function testTheGuardScansTheSourceTree(): void {
		$files = $this->sourceFiles();

		$this->assertGreaterThan(300, count($files), 'The walker must see lib/, or the guard above cannot fail.');
		$this->assertArrayHasKey(
			'lib/Controller/MappingsController.php',
			$files,
			'The controller is the file this guard was written for; the walker must reach it.'
		);
	}//end testTheGuardScansTheSourceTree()

	/**
	 * Every allowed file still exists.
	 *
	 * An exemption outlives the code it cites. Once the named file is renamed or
	 * deleted the entry stops exempting anything and starts hiding the next pin
	 * that lands at the same path, silently, because a key that matches nothing
	 * looks exactly like a key that matched and was fine.
	 *
	 * @return void
	 */
	public function testEveryAllowedFileStillExists(): void {
		$files = $this->sourceFiles();

		foreach (array_keys(self::ALLOWED) as $relative) {
			$this->assertArrayHasKey(
				$relative,
				$files,
				'ALLOWED names a file that is not there any more: ' . $relative . '. Remove the entry.'
			);
		}
	}//end testEveryAllowedFileStillExists()

	/**
	 * The patterns match a pinned slug when one is present.
	 *
	 * Watched failing is not enough on its own once the tree is clean: from then
	 * on the guard passes whether or not its regexes still work. This feeds each
	 * register-position form a known-bad line and requires a match, so a regex
	 * that stops matching reddens immediately instead of going quiet.
	 *
	 * The sixth sample is the real defect, copied verbatim from
	 * MappingsController as it stood on `development`.
	 *
	 * @return void
	 */
	public function testEachRegisterPositionPatternStillMatches(): void {
		$samples = [
			'/setRegister\(\s*(?:register:\s*)?\'([a-zA-Z0-9_-]+)\'/' => "\$objectService->setRegister('openconnector');",
			'/\bregister:\s*\'([a-zA-Z0-9_-]+)\'/'                    => "\$svc->find(id: \$id, register: 'openconnector', schema: 'source');",
			'/\'register\'\s*=>\s*\'([a-zA-Z0-9_-]+)\'/'              => "'filters' => ['register' => 'openconnector', 'schema' => 'job'],",
			'/\bconst\s+[A-Z0-9_]*REGISTER[A-Z0-9_]*\s*=\s*\'([a-zA-Z0-9_-]+)\'/' => "\tprivate const SELF_REGISTER = 'openconnector';",
			'/\$[a-zA-Z0-9_]*(?:[Rr]egister|[Ss]lug)[a-zA-Z0-9_]*\s*=\s*\'([a-zA-Z0-9_-]+)\'/' => "\t\t\$registerSlug = 'openconnector';",
			'/\bregister:\s*\(?[^,()]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/'   => "\t\t\tregister: (\$data['register'] ?? 'openconnector'),",
			'/\'register\'\s*=>\s*\(?[^,()]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/' => "'register' => (\$data['register'] ?? 'openconnector'),",
			'/\$[a-zA-Z0-9_]*(?:[Rr]egister|[Ss]lug)[a-zA-Z0-9_]*\s*=\s*[^;]*\?\?\s*\'([a-zA-Z0-9_-]+)\'/' => "\t\t\$register = \$resolution?->slug ?? 'openconnector';",
		];

		foreach (self::REGISTER_POSITION as $pattern) {
			$this->assertArrayHasKey($pattern, $samples, 'Every register-position pattern needs a known-bad sample.');
			$this->assertSame(
				1,
				preg_match($pattern, $samples[$pattern], $matches),
				'Pattern must match its known-bad sample: ' . $pattern
			);
			$this->assertArrayHasKey(
				strtolower($matches[1]),
				self::SUPERSEDED,
				'The sample must capture a slug this guard calls superseded: ' . $pattern
			);
		}
	}//end testEachRegisterPositionPatternStillMatches()

	/**
	 * Every PHP file under lib/, keyed by repository-relative path.
	 *
	 * @return array<string, string> Relative path => absolute path.
	 */
	private function sourceFiles(): array {
		$root = dirname(__DIR__, 3);
		$lib  = $root . '/lib';

		$files    = [];
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($lib, RecursiveDirectoryIterator::SKIP_DOTS)
		);
		foreach ($iterator as $file) {
			if (($file instanceof SplFileInfo) === false || $file->isFile() === false) {
				continue;
			}

			if ($file->getExtension() !== 'php') {
				continue;
			}

			$path = $file->getPathname();

			$files[ltrim(str_replace($root, '', $path), '/')] = $path;
		}

		return $files;
	}//end sourceFiles()
}//end class
