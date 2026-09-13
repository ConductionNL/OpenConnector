/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec coverage: openspec/specs/connector-catalog/spec.md
 * (connector-catalog-ui — Store page (formerly Catalog): card grid, category filter,
 * status badges, detail dialog Enable/Instantiate).
 *
 * Backend-only scenarios (materialization idempotency, new-provider
 * pickup, action-matrix denial, OR data-layer lock) carry `@e2e exclude`
 * in the spec and are covered by PHPUnit
 * (tests/Unit/Service/CatalogRegistryServiceTest.php,
 * tests/Unit/Controller/CatalogControllerTest.php).
 *
 * NOTE (connector-catalog-ui apply): written per the test plan but NOT
 * executed against a live instance in the build environment — requires a
 * running Nextcloud with the MaterializeCatalogItems repair step applied
 * (occ upgrade / app enable). Run via `npm run test:regression` or
 * `npx playwright test tests/e2e/spec-coverage/connector-catalog.spec.ts`
 * against a provisioned instance.
 */

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'

const APP_BASE = '/index.php/apps/integriq'

test.describe('Store page — manifest conformance (ADR-080) (openconnector-app-manifest delta)', () => {
	const manifest = JSON.parse(
		fs.readFileSync(
			path.resolve(__dirname, '../../../src/manifest.json'),
			'utf8',
		),
	)

	// ADR-080 renamed this page Catalog -> Store: a "catalogue" is an
	// outward-facing PUBLISHED catalogue (OpenCatalogi's concept), whereas this
	// page is "browse a registry and install into this instance". The backing
	// schema (`catalog_item`) is unchanged, so only the page/menu id moves.
	//
	// @e2e openconnector-app-manifest::catalog-page-entry-is-present-and-uses-the-cards-index-pattern
	test('Store page entry uses type:index + viewMode:cards on integriq/catalog_item', () => {
		const page = manifest.pages.find((p: { id: string }) => p.id === 'Store')
		expect(page, 'Store page must exist in the manifest').toBeTruthy()
		expect(page.type).toBe('index')
		expect(page.config.viewMode).toBe('cards')
		// The REGISTER slug moved with the app rename; the SCHEMA did not — the
		// @e2e tag above still names openconnector-app-manifest because that is
		// the spec file's name, which has not been renamed.
		expect(page.config.register).toBe('integriq')
		expect(page.config.schema).toBe('catalog_item')
		expect(page.config.cardComponent).toBe('CatalogItemCard')
	})

	// @e2e openconnector-app-manifest::catalog-menu-entry-is-present-and-routes-to-the-catalog-page
	test('Store menu entry routes to the Store page id', () => {
		const flatten = (
			entries: Array<{ id: string; route?: string; children?: unknown[] }>,
		): Array<{ id: string; route?: string }> =>
			entries.flatMap((e) => [e, ...flatten((e.children as never[]) || [])])
		const entry = flatten(manifest.menu).find((e) => e.id === 'Store')
		expect(entry, 'Store menu entry must exist').toBeTruthy()
		expect(entry!.route).toBe('Store')
	})

	// @e2e openconnector-app-manifest::catalog-page-does-not-require-a-new-manifest-page-type
	test('Store page introduces no new page type value', () => {
		const knownTypes = new Set(
			manifest.pages.map((p: { type: string }) => p.type),
		)
		// "index" predates this change (Sources, Endpoints, …) — the Store
		// page reuses it rather than minting a new enum value.
		const catalogPage = manifest.pages.find(
			(p: { id: string }) => p.id === 'Store',
		)
		expect(catalogPage.type).toBe('index')
		const preExistingIndexPages = manifest.pages.filter(
			(p: { id: string; type: string }) =>
				p.type === 'index' && p.id !== 'Store',
		)
		expect(preExistingIndexPages.length).toBeGreaterThan(0)
		expect(knownTypes.has('index')).toBe(true)
	})
})

// SKIPPED (unvalidated feature specs, not a Vue-3 migration regression):
// these connector-catalog-ui interaction specs were authored "per the test
// plan but NOT executed against a live instance" and assume a UI that the
// live CnIndexPage cards view does not present:
//   - target cards (BRP HaalCentraal, PDOK, xWiki) are `source-template`/
//     paginated entries that sit BEYOND the 20-item first page, and the page
//     paginates (First/Previous/1/2) rather than rendering all 57;
//   - there is no inline card searchbox (the only `type=search` input is a
//     vue-select's internal `.vs__search`); free-text search lives behind the
//     "Search and columns" sidebar;
//   - the expected status badges are feature-flag/mock-mode dependent (e.g.
//     the spec expects BRP "available", but with no mock-mode flag seeded it
//     materialises "dormant").
// The MIGRATION itself is verified: the Catalog page mounts and renders all
// 57 materialised catalog_item cards with status badges + quick-filter chips
// (confirmed live). Re-enabling these requires feature-flag seeding + a card
// locator strategy that matches the real paginated/sidebar UI — separate
// feature-test work.
//
// TRACKED IN #1187. (The original comment said this was "tracked outside this
// Vue-3 de-compat PR" — no issue existed; #1187 is now that tracker and carries
// the full re-enable checklist.)
/**
 * Walk the paginated grid until a card matching `text` is on screen.
 *
 * The grid pages at 20 of 57, so a card is very often not on page 1: PDOK and
 * BRP are both on page 2 today, and a seed change moves them. Asserting
 * `toBeVisible()` on page 1 is what made these specs look broken.
 *
 * @param page The page.
 * @param text Text the card contains.
 * @return The card locator, already visible.
 */
async function findCardAcrossPages(page, text) {
	const cards = page.getByTestId('catalog-item-card')
	await expect(cards.first()).toBeVisible({ timeout: 15_000 })
	for (let attempt = 0; attempt < 10; attempt++) {
		const match = cards.filter({ hasText: text }).first()
		if ((await match.count()) > 0) {
			await expect(match).toBeVisible()
			return match
		}
		const next = page.getByRole('button', { name: 'Next', exact: true })
		if (
			(await next.count()) === 0
			|| (await next.isDisabled().catch(() => true))
		) {
			break
		}
		await next.click()
		await expect(cards.first()).toBeVisible({ timeout: 10_000 })
	}
	throw new Error(`no catalog card matching ${JSON.stringify(text)} on any page`)
}

/**
 * Walk to a card and read its status badge in one attempt, from page 1.
 *
 * Returns '' rather than throwing when the card or its badge is not reachable,
 * so a caller can poll this and let a mid-walk re-render cost a retry instead of
 * the test. Separating the walk from the read is what made the PDOK spec flaky.
 *
 * @param page The page.
 * @param text Text the card contains.
 * @return The badge text, or '' when it could not be read this attempt.
 */
async function readBadgeAcrossPages(page, text) {
	try {
		await page.goto(`${APP_BASE}/store`, { waitUntil: 'domcontentloaded' })
		const card = await findCardAcrossPages(page, text)
		return (await card.getByTestId('catalog-status-badge').innerText()).trim()
	} catch {
		return ''
	}
}

/**
 * The "Showing N of M" total the index header prints.
 *
 * The CARD COUNT cannot answer "did the filter narrow the grid": it is pinned
 * at the page size of 20 whether 57 or 35 items match. The total is what moves.
 *
 * @param page The page.
 * @return The M in "Showing N of M".
 */
async function shownTotal(page) {
	const header = page.getByTestId('cn-index-page')
	const line = await header.innerText()
	const match = line.match(/Showing\s+\d+\s+of\s+(\d+)/i)
	if (match === null)
		throw new Error(`no "Showing N of M" line in the index header`)
	return Number(match[1])
}

test.describe('Catalog page — browse, filter, badges (REQ-001)', () => {
	// @e2e connector-catalog::catalog-lists-built-in-adapters-and-seeded-source-templates-by-category
	test('the store lists catalog items and the kind quick-filter narrows them', async ({
		page,
	}) => {
		// ADR-080 renamed this surface from Catalog to Store. `/catalog` still
		// resolves (it redirects), and the router runs in PATH mode, not the hash
		// mode an older comment here claimed. Navigate to the canonical route.
		await page.goto(`${APP_BASE}/store`, { waitUntil: 'domcontentloaded' })

		const cards = page.getByTestId('catalog-item-card')
		await expect(
			cards.first(),
			'materialised catalog cards must render',
		).toBeVisible({ timeout: 15_000 })

		const before = await shownTotal(page)
		expect(before).toBeGreaterThanOrEqual(3)

		// The quick-filter chips are role="tab", not buttons. Reaching for a
		// button found nothing and the click never landed, which read as "the
		// filter does not work".
		await page.getByRole('tab', { name: 'Adapters', exact: true }).click()
		await expect(cards.first()).toBeVisible({ timeout: 10_000 })

		await expect
			.poll(async () => await shownTotal(page), { timeout: 10_000 })
			.toBeLessThan(before)
	})

	// @e2e connector-catalog::status-badge-reflects-a-flag-gated-dormant-item
	test('the PDOK card shows a dormant badge while its feature flag is off', async ({
		page,
	}) => {
		await page.goto(`${APP_BASE}/store`, { waitUntil: 'domcontentloaded' })

		// Walk AND read inside the same polled attempt. Walking first and then
		// polling the badge is what made this flaky: the walk leaves the grid on
		// PDOK's page, the page query settles a moment later and re-renders the
		// grid back, and the poll then re-resolves a locator for a card that is
		// no longer on screen. It reports "no badge" for a card that is dormant
		// in the data, which is exactly the wrong conclusion. Each attempt here
		// starts from page 1, so a re-render costs a retry rather than the test.
		await expect
			.poll(async () => await readBadgeAcrossPages(page, 'PDOK'), {
				timeout: 30_000,
			})
			.toMatch(/dormant/i)
	})

	// @e2e connector-catalog::status-badge-reflects-a-mock-seeded-available-item
	test('BRP HaalCentraal card shows available (mock mode is not dormant)', async ({
		page,
	}) => {
		// Measured 2026-09-07 against a seeded instance: BRP HaalCentraal is
		// `dormant`, and `available` and `dormant` are the only two status values
		// across all 57 rows. The scenario is about MOCK MODE making it available,
		// and nothing in the e2e seed turns mock mode on, so the expectation
		// cannot hold here. Seed mock mode, or correct the scenario. #1187.
		test.skip(
			true,
			'BRP HaalCentraal is dormant on a plain seeded instance; the scenario asserts the mock-mode state and the seed does not enable mock mode. Measured 2026-09-07 — #1187.',
		)

		const brpCard = await findCardAcrossPages(page, 'BRP HaalCentraal')
		await expect(brpCard.getByTestId('catalog-status-badge')).toHaveText(
			/available/i,
		)
	})

	// @e2e connector-catalog::search-narrows-the-catalog-grid
	test('typing "brp" into the search narrows the grid to matching items', async ({
		page,
	}) => {
		// Measured 2026-09-07: `getByRole('searchbox')` matches NOTHING on this
		// page. The one visible `input[type=search]` belongs to Nextcloud's own
		// header search, and filling it leaves "Showing 20 of 57" unchanged, so
		// the store has no search affordance for a spec to drive yet. The
		// narrowing behaviour itself is covered by the quick-filter test above.
		test.skip(
			true,
			'the store page exposes no catalog searchbox: getByRole("searchbox") matches 0 elements and the visible input is Nextcloud\'s header search, which does not filter the grid. Measured 2026-09-07 — #1187.',
		)

		const cards = page.getByTestId('catalog-item-card')
		await expect(cards.first()).toBeVisible({ timeout: 15_000 })
		const before = await shownTotal(page)

		await page.getByRole('searchbox').first().fill('brp')
		await expect
			.poll(async () => await shownTotal(page), { timeout: 10_000 })
			.toBeLessThan(before)
	})
})

// SKIPPED (same rationale as REQ-001 above): these detail-dialog specs need a
// specific card (PDOK/xWiki) located past the first page + live feature-flag
// state (Enable/Instantiate depend on a dormant flag-gated / source-template
// item). Not a Vue-3 migration regression — the detail dialog itself opens and
// renders live; re-enabling needs feature seeding + a paginated locator.
//
// TRACKED IN #1187.
test.describe('Catalog detail dialog — Enable / Instantiate (REQ-002)', () => {
	// See the note on REQ-001 above: a reason must reach the report, not just
	// the source.
	test.skip(
		true,
		'catalog detail Enable/Instantiate specs depend on feature-flag and mock-mode seeding that CI does not provide, so the expected status badges never materialise — tracked in #1187.',
	)
	// @e2e connector-catalog::enable-action-flips-a-feature-flag-for-a-flag-gated-item
	test('opening a dormant flag-gated item offers Enable and enabling updates the badge', async ({
		page,
	}) => {
		// The integriq SPA is hash-routed (vue-router createWebHashHistory,
		// unchanged from the Vue 2 `mode: 'hash'` build), so a bare path deep-link
		// like `/apps/integriq/catalog` is ignored by the router and resolves
		// to the default Dashboard route. Deep-link via the hash fragment so we
		// actually land on the Catalog page. (These connector-catalog-ui specs were
		// authored "per the test plan but NOT executed against a live instance", so
		// this navigation was never validated before.)
		await page.goto(`${APP_BASE}/catalog`, { waitUntil: 'domcontentloaded' })

		const pdokCard = page
			.getByTestId('catalog-item-card')
			.filter({ hasText: 'PDOK' })
			.first()
		await expect(pdokCard).toBeVisible({ timeout: 15_000 })
		await pdokCard.click()

		const dialog = page.getByTestId('catalog-item-detail-dialog')
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		// The dialog re-checks live status before offering the action.
		const action = page.getByTestId('catalog-detail-primary-action')
		await expect(action).toHaveText(/Enable/i, { timeout: 10_000 })
		await action.click()

		await expect(page.getByText(/Feature enabled/i).first()).toBeVisible({
			timeout: 10_000,
		})
		await expect(page.getByTestId('catalog-detail-status')).toHaveText(
			/available/i,
			{ timeout: 10_000 },
		)
	})

	// @e2e connector-catalog::instantiate-action-creates-a-source-from-a-seeded-template
	test('instantiating a dormant source-template creates the Source (visible on Sources page)', async ({
		page,
	}) => {
		// The integriq SPA is hash-routed (vue-router createWebHashHistory,
		// unchanged from the Vue 2 `mode: 'hash'` build), so a bare path deep-link
		// like `/apps/integriq/catalog` is ignored by the router and resolves
		// to the default Dashboard route. Deep-link via the hash fragment so we
		// actually land on the Catalog page. (These connector-catalog-ui specs were
		// authored "per the test plan but NOT executed against a live instance", so
		// this navigation was never validated before.)
		await page.goto(`${APP_BASE}/catalog`, { waitUntil: 'domcontentloaded' })

		// xWiki seeds with isEnabled:false → dormant → Instantiate offered.
		const card = page
			.getByTestId('catalog-item-card')
			.filter({ hasText: 'xWiki' })
			.first()
		await expect(card).toBeVisible({ timeout: 15_000 })
		await card.click()

		const action = page.getByTestId('catalog-detail-primary-action')
		await expect(action).toHaveText(/Instantiate/i, { timeout: 10_000 })
		await action.click()
		await expect(page.getByText(/Source instantiated/i).first()).toBeVisible({
			timeout: 10_000,
		})

		// The Source now appears in the Sources index.
		await page.goto(`${APP_BASE}/sources`, { waitUntil: 'domcontentloaded' })
		await expect(page.getByText('xWiki', { exact: false }).first()).toBeVisible({
			timeout: 15_000,
		})
	})
})
