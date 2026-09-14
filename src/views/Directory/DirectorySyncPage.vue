<!-- SPDX-License-Identifier: EUPL-1.2 -->
<!-- SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl> -->

<!--
  DirectorySyncPage — the directory connections, their mapping, and what each
  run changed (manifest `type: custom`, `component: DirectorySyncPage`).

  One screen rather than three, because the three questions an administrator
  actually has are one question: which groups does this connection fill, what
  would a run change, and what did the last run change. A preview writes
  nothing; a run that crosses the removal guard stops and says so, and the
  Confirm removals button is the administrator taking that decision.

  @spec openspec/changes/directory-and-group-sync/specs/directory-sync/spec.md
-->
<template>
	<NcAppContent>
		<div class="directorySync">
			<h2 class="directorySync__title">
				{{ t('integriq', 'Directory synchronisation') }}
			</h2>

			<NcLoadingIcon v-if="loading" :size="32" />

			<p
				v-else-if="!connections.length"
				class="directorySync__empty"
				data-testid="directory-sync-empty">
				{{
					t(
						'integriq',
						'No directory connection is configured yet. Add a source of type directory to fill Nextcloud groups from the directory.',
					)
				}}
			</p>

			<section
				v-for="connection in connections"
				v-else
				:key="connection.id"
				class="directorySync__connection"
				data-testid="directory-sync-connection">
				<h3>{{ connection.name }}</h3>
				<p v-if="connection.description">{{ connection.description }}</p>

				<table class="directorySync__mapping" data-testid="directory-sync-mapping">
					<caption>
						{{ t('integriq', 'Mapping') }}
					</caption>
					<thead>
						<tr>
							<th scope="col">{{ t('integriq', 'Directory group') }}</th>
							<th scope="col">{{ t('integriq', 'Nextcloud group') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(rule, index) in rulesOf(connection)" :key="index">
							<td>{{ rule.directoryGroup || rule.attribute }}</td>
							<td>{{ rule.group }}</td>
						</tr>
					</tbody>
				</table>

				<div class="directorySync__actions">
					<NcButton
						:disabled="busy"
						data-testid="directory-sync-preview"
						@click="start(connection, { dryRun: true })">
						{{ t('integriq', 'Preview run') }}
					</NcButton>
					<NcButton
						variant="primary"
						:disabled="busy"
						data-testid="directory-sync-run"
						@click="start(connection, {})">
						{{ t('integriq', 'Run now') }}
					</NcButton>
					<NcButton
						v-if="guardOf(connection)"
						:disabled="busy"
						data-testid="directory-sync-confirm"
						@click="start(connection, { confirmRemovals: true })">
						{{ t('integriq', 'Confirm removals') }}
					</NcButton>
				</div>

				<DirectoryRunSummary
					v-if="results[connection.id]"
					:record="results[connection.id]"
					data-testid="directory-sync-result" />
			</section>

			<section class="directorySync__runs">
				<h3>{{ t('integriq', 'Runs') }}</h3>
				<p
					v-if="!runs.length"
					class="directorySync__empty"
					data-testid="directory-sync-no-runs">
					{{ t('integriq', 'This connection has not run yet.') }}
				</p>
				<DirectoryRunSummary
					v-for="run in runs"
					v-else
					:key="run.uuid || run.id"
					:record="run.result"
					:created="run.created"
					data-testid="directory-sync-run-row" />
			</section>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcAppContent, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import DirectoryRunSummary from '../../components/DirectoryRunSummary.vue'

export default {
	name: 'DirectorySyncPage',

	components: {
		DirectoryRunSummary,
		NcAppContent,
		NcButton,
		NcLoadingIcon,
	},

	data() {
		return {
			connections: [],
			runs: [],
			results: {},
			loading: false,
			busy: false,
		}
	},

	mounted() {
		this.reload()
	},

	methods: {
		t,

		/**
		 * Load the connections and the runs they have already had.
		 *
		 * @return {Promise<void>} Resolves once both lists are in place.
		 * @spec openspec/changes/directory-and-group-sync/specs/directory-sync/spec.md#requirement-every-run-says-what-it-changed-req-ds-006
		 */
		async reload() {
			this.loading = true
			try {
				const [connections, runs] = await Promise.all([
					axios.get(generateUrl('/apps/integriq/api/directory/connections')),
					axios.get(generateUrl('/apps/integriq/api/directory/runs')),
				])
				this.connections = connections.data?.results || []
				this.runs = runs.data?.results || []
			} catch (error) {
				showError(t('integriq', 'The directory connections could not be loaded.'))
			} finally {
				this.loading = false
			}
		},

		/**
		 * Run or preview one connection.
		 *
		 * @param {object} connection The connection row.
		 * @param {object} options Run options: dryRun, confirmRemovals.
		 * @return {Promise<void>} Resolves once the run record is in place.
		 * @spec openspec/changes/directory-and-group-sync/specs/directory-sync/spec.md#requirement-a-run-can-be-previewed-and-a-large-removal-is-guarded-req-ds-005
		 */
		async start(connection, options) {
			this.busy = true
			try {
				const response = await axios.post(
					generateUrl(
						`/apps/integriq/api/directory/connections/${connection.id}/run`,
					),
					{
						dryRun: options.dryRun === true,
						confirmRemovals: options.confirmRemovals === true,
					},
				)
				this.results = { ...this.results, [connection.id]: response.data }
				await this.reload()
			} catch (error) {
				showError(t('integriq', 'The run could not be started.'))
			} finally {
				this.busy = false
			}
		},

		/**
		 * The mapping rules declared on a connection.
		 *
		 * @param {object} connection The connection row.
		 * @return {Array} The rules.
		 * @spec openspec/changes/directory-and-group-sync/specs/directory-sync/spec.md#requirement-the-directory-to-group-mapping-is-declared-not-coded-req-ds-002
		 */
		rulesOf(connection) {
			return connection.mapping?.rules || []
		},

		/**
		 * The guard the last run of this connection hit, if it hit one.
		 *
		 * @param {object} connection The connection row.
		 * @return {object|null} The guard, or null.
		 * @spec openspec/changes/directory-and-group-sync/specs/directory-sync/spec.md#requirement-a-run-can-be-previewed-and-a-large-removal-is-guarded-req-ds-005
		 */
		guardOf(connection) {
			return this.results[connection.id]?.guard || null
		},
	},
}
</script>

<style scoped>
.directorySync {
	padding: 16px;
}

.directorySync__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-block: 12px;
}

.directorySync__mapping {
	width: 100%;
	border-collapse: collapse;
}

.directorySync__mapping th,
.directorySync__mapping td {
	text-align: start;
	padding: 4px 8px;
	border-bottom: 1px solid var(--color-border);
}

.directorySync__empty {
	color: var(--color-text-maxcontrast);
}
</style>
