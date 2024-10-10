<script setup>
import { logStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'viewSourceLog'"
		ref="modalRef"
		label-id="viewSourceLog"
		@close="closeModal">
		<div class="logModalContent">
			<div class="logModalContentHeader">
				<h2>View Source Log</h2>
			</div>

			<strong>Standard</strong>
			<table>
				<tr v-for="(value, key) in standardItems"

					:key="key">
					<td class="keyColumn">
						{{ key }}
					</td>
					<td v-if="typeof value === 'string' && (key === 'created' || key === 'updated' || key === 'expires' || key === 'lastRun' || key === 'nextRun')">
						{{ new Date(value).toLocaleString() }}
					</td>
					<td v-else>
						{{ value }}
					</td>
				</tr>
			</table>
			<br>

			<strong>Request</strong>
			<table>
				<tr v-for="(value, key) in requestItems"
					:key="key">
					<td class="keyColumn">
						{{ key }}
					</td>
					<td>{{ value }}</td>
				</tr>
			</table>
			<br>
			<div>
				<strong>Response</strong>
				<table>
					<tr v-for="(value, key) in responseItems"
						:key="key">
						<td v-if="key !== 'body' && key !== 'headers'" class="keyColumn">
							{{ key }}
						</td>
						<td v-if="key !== 'body' && key !== 'headers'">
							{{ value }}
						</td>
					</tr>
				</table>
				<span>headers</span>
				<table class="responseHeadersTable">
					<tr v-for="(value, key) in headersItems"
						:key="key">
						<td class="keyColumn">
							{{ key }}
						</td>
						<td>{{ value }}</td>
					</tr>
				</table>

				<div>
					<span>body</span>
					<div class="responseBody">
						{{ responseItems.body }}
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	NcModal,
} from '@nextcloud/vue'

export default {
	name: 'ViewSourceLog',
	components: {
		NcModal,
	},
	data() {
		return {
			hasUpdated: false,
			standardItems: {},
			requestItems: {},
			responseItems: {},
			headersItems: {},
		}
	},
	mounted() {
		logStore.viewLogItem && this.splitItems()
	},
	updated() {
		if (navigationStore.modal === 'viewSourceLog' && !this.hasUpdated) {
			logStore.viewLogItem && this.splitItems()
			this.hasUpdated = true
		}
	},
	methods: {
		splitItems() {
			Object.entries(logStore.viewLogItem).forEach(([key, value]) => {
				if (key === 'request' || key === 'response') {
					this[`${key}Items`] = { ...value }
				} else {
					this.standardItems = { ...this.standardItems, [key]: value }
				}
			})
			this.headersToObject()
		},
		headersToObject() {
			Object.entries(this.requestItems).forEach(([key, value]) => {
				this.headersItems = { ...this.headersItems, [key]: value }
			})
		},
		closeModal() {
			navigationStore.setModal(false)
			this.hasUpdated = false
			this.standardItems = {}
			this.requestItems = {}
			this.responseItems = {}
			this.headersItems = {}
		},
	},

}
</script>
<style>

.responseHeadersTable {
    margin-inline-start: 65px;
}

.responseBody {
    word-break: break-all;
}

.keyColumn {
    padding-inline-end: 10px;
}

.logModalContent {
    margin: var(--OC-margin-30);
}

.logModalContentHeader {
    text-align: center;
}

.logModalContent > *:not(:last-child) {
    margin-block-end: 1rem;
}

</style>
