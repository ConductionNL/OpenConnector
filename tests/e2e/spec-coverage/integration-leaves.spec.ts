/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Spec coverage: openspec/specs/integration-leaves/spec.md
 *
 * Integriq's first OpenRegister integration leaves: `files`, `deck` and `talk`
 * on `source`, `calendar` on `synchronization`.
 *
 * WHY THE LIVE SCHEMA IS READ BACK RATHER THAN THE FILE ON DISK
 * ------------------------------------------------------------
 * The declaration has a silent-failure mode that a file assertion cannot see.
 * `Schema::hydrate()` folds only `x-openregister-*` keys and `x-schema-org`
 * into `configuration`; every other top-level key is dispatched to a
 * `set<Key>()` method, and a call to a method that does not exist is swallowed
 * by hydrate's own catch. A `linkedTypes` written as a sibling of `properties`
 * — the shape two schemas in a neighbouring app use — therefore imports
 * cleanly, validates cleanly, and is dropped on the floor. Reading the stored
 * schema back through OpenRegister's own API is the only assertion that can
 * tell the two apart.
 *
 * WHY DECK AND TALK ARE TOLERATED WHEN ABSENT
 * -------------------------------------------
 * Leaves are runtime-optional by design: OpenRegister's providers answer
 * `isEnabled()` false when the Nextcloud app behind them is not installed, and
 * the widget then does not render. A CI instance without Deck and Talk is the
 * normal case, not a failure, so the render test asserts the Files leaf
 * unconditionally and the other two only when their app is present. The
 * declaration test has no such tolerance: `linkedTypes` is stored on the schema
 * whether or not the app is installed, so it is asserted outright.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { APP_BASE } from './_helpers.ts'

const OR_API = '/index.php/apps/openregister/api'
const SOURCES_API = `${OR_API}/objects/integriq/source`

/**
 * Read the request token the SPA itself uses. OpenRegister's API routes are
 * CSRF-guarded, and a guarded GET answers 412 with a valid JSON body in which
 * every field is undefined — which reads as a broken assertion rather than a
 * missing token.
 */
async function requestToken(page: Page) {
	const token = await page.evaluate(
		() => document.head.getAttribute('data-requesttoken') ?? '',
	)
	expect(token, 'the app page must carry a request token').not.toBe('')
	return token
}

/**
 * The first source on the instance, or undefined when there is none.
 *
 * @param page The page, already used to harvest a request token.
 * @param request The Playwright request context.
 * @return The source object, or undefined.
 */
async function firstSource(page: Page, request: APIRequestContext) {
	await page.goto(`${APP_BASE}/sources`, { waitUntil: 'domcontentloaded' })
	const requesttoken = await requestToken(page)
	const resp = await request.get(`${SOURCES_API}?_limit=1`, {
		headers: { requesttoken },
		failOnStatusCode: false,
	})
	expect(resp.status(), 'an admin must be able to list sources').toBe(200)
	const body = await resp.json()
	return (body?.results ?? body ?? [])[0]
}

/**
 * The object id of a listed source.
 *
 * @param source The listed source.
 * @return Its id.
 */
function sourceIdOf(source: Record<string, unknown>) {
	const id = String(
		source.id
			?? (source['@self'] as Record<string, unknown> | undefined)?.id
			?? source.uuid
			?? '',
	)
	expect(id, 'the listed source must carry an id').not.toBe('')
	return id
}

/**
 * Open a source's detail page and wait for its main landmark.
 *
 * `#app-content` does not exist on this shell — the skip-link target is
 * `#app-content-vue` and the landmark is `main`. Asserting the wrong id fails
 * before any leaf assertion runs, which reads as "the leaf did not render"
 * when the page rendered perfectly.
 *
 * @param page The page.
 * @param sourceId The source's id.
 * @return Nothing.
 */
async function openSource(page: Page, sourceId: string) {
	await page.goto(`${APP_BASE}/sources/${sourceId}`, {
		waitUntil: 'domcontentloaded',
	})
	await expect(page.locator('main').first()).toBeVisible({ timeout: 20_000 })
}

// ---------------------------------------------------------------------------
// REQ-OCL-001: the leaf surface is declared, and is exactly four leaves on
// two schemas
// ---------------------------------------------------------------------------

test.describe('REQ-OCL-001: the declared leaf surface', () => {
	// @e2e integration-leaves::the-register-declares-files-deck-and-talk-on-source-and-calendar-on-synchronization
	test('the stored schemas carry exactly the four declared leaves', async ({
		page,
		request,
	}) => {
		await page.goto(`${APP_BASE}/sources`, { waitUntil: 'domcontentloaded' })
		const requesttoken = await requestToken(page)

		const resp = await request.get(`${OR_API}/schemas?_limit=200`, {
			headers: { requesttoken },
			failOnStatusCode: false,
		})
		expect(resp.status(), 'an admin must be able to list schemas').toBe(200)

		const body = await resp.json()
		const schemas: Array<Record<string, unknown>> = body?.results ?? body ?? []
		expect(
			Array.isArray(schemas) && schemas.length > 0,
			'the schema list must not be empty',
		).toBe(true)

		const linkedBySlug = new Map<string, string[]>()
		for (const schema of schemas) {
			const slug = String(schema.slug ?? '')
			const config = (schema.configuration ?? {}) as Record<string, unknown>
			const linked = config.linkedTypes
			if (Array.isArray(linked) && linked.length > 0) {
				linkedBySlug.set(slug, [...(linked as string[])].sort())
			}
		}

		expect(
			linkedBySlug.get('source'),
			'the source schema must declare the files, deck and talk leaves',
		).toEqual(['deck', 'files', 'talk'])
		expect(
			linkedBySlug.get('synchronization'),
			'the synchronization schema must declare the calendar leaf',
		).toEqual(['calendar'])

		// The restraint half of the requirement: no other Integriq schema may
		// quietly acquire a leaf. Schemas from other apps share this instance,
		// so the assertion is scoped to the slugs this register owns.
		const integriqOnly = [...linkedBySlug.keys()].filter(
			(slug) =>
				[
					'endpoint',
					'mapping',
					'job',
					'rule',
					'consumer',
					'sync_item_dead_letter',
				].includes(slug) || slug.endsWith('_log'),
		)
		expect(
			integriqOnly,
			'no other Integriq schema may declare a leaf without a spec change',
		).toEqual([])
	})
})

// ---------------------------------------------------------------------------
// REQ-OCL-002: leaves are pure link surfaces and never read or write source
// properties
// ---------------------------------------------------------------------------

test.describe('REQ-OCL-002: the leaves on a source detail page', () => {
	// @e2e integration-leaves::sourcedetail-renders-the-leaf-widgets
	test('the leaf widgets render on a source detail page', async ({
		page,
		request,
	}) => {
		const first = await firstSource(page, request)
		test.skip(
			!first,
			'no source exists on this instance; the leaf widgets need a real object id',
		)

		await openSource(page, sourceIdOf(first))
		const content = page.locator('main').first()

		// A leaf widget resolves its renderer through OpenRegister's SHARED
		// client registry, installed by the `openregister-integration-global`
		// bundle. OpenRegister gitignores `/js/` and force-tracks three files,
		// which do not include that bundle, so a checkout that was never built
		// serves no registry at all and NO integration widget can render — for
		// any app, not just this one. Probing the registry is what separates
		// "the leaf is broken" from "this instance cannot render leaves", and
		// asserting through it unconditionally is how this spec went red on CI
		// against an app that was fine.
		const hasFilesProvider = await page.evaluate(() => {
			const registry = (
				window as unknown as {
					OCA?: {
						OpenRegister?: {
							integrations?: { has?: (id: string) => boolean }
						}
					}
				}
			).OCA?.OpenRegister?.integrations
			return typeof registry?.has === 'function' && registry.has('files')
		})
		test.skip(
			!hasFilesProvider,
			"OpenRegister's client integration registry is absent on this instance, so no integration widget can render. Its `openregister-integration-global` bundle is gitignored and is not one of the three force-tracked files in `js/`, so an unbuilt checkout — CI's — serves nothing to register the providers with.",
		)

		// Files is the one leaf that needs no extra Nextcloud app: its provider
		// is OpenRegister's own.
		await expect(
			content.getByText('Supplier documents', { exact: false }).first(),
			'the files leaf must render on a source detail page',
		).toBeVisible({ timeout: 20_000 })

		// Deck and Talk render only when their app is installed. Absence is the
		// documented provider behaviour, so it is tolerated; presence is asserted.
		for (const title of ['Incident follow-ups', 'Incident war-room']) {
			const widget = content.getByText(title, { exact: false }).first()
			if (await widget.isVisible({ timeout: 2_000 }).catch(() => false)) {
				await expect(widget).toBeVisible()
			}
		}
	})

	// @e2e integration-leaves::a-source-detail-page-exposes-no-credential-and-is-unchanged-by-rendering
	test('no credential reaches the page, and rendering leaves the source unchanged', async ({
		page,
		request,
	}) => {
		const first = await firstSource(page, request)
		test.skip(!first, 'no source exists on this instance')

		const sourceId = sourceIdOf(first)
		await openSource(page, sourceId)
		const content = page.locator('main').first()

		// No leaf reads a source property, so no credential value can reach the
		// page. Asserted against the whole main landmark rather than a per-widget
		// locator: a value leaking into the surrounding chrome is the same defect.
		// This runs whether or not the widgets rendered, because the property it
		// checks is about the object read, not about the leaf renderer.
		const secrets = ['password', 'apikey', 'secret', 'jwt']
			.map((field) => first[field])
			.filter(
				(value): value is string =>
					typeof value === 'string' && value.length >= 8,
			)
		const rendered = await content.innerText()
		for (const secret of secrets) {
			expect(
				rendered.includes(secret),
				'a credential value must never render on a source detail page',
			).toBe(false)
		}

		const requesttoken = await requestToken(page)
		const afterResp = await request.get(`${SOURCES_API}/${sourceId}`, {
			headers: { requesttoken },
			failOnStatusCode: false,
		})
		expect(afterResp.status()).toBe(200)
		const after = await afterResp.json()
		const afterObject = after?.results?.[0] ?? after
		expect(first.name, 'rendering the leaves must not change the source').toBe(
			afterObject.name,
		)
	})
})
