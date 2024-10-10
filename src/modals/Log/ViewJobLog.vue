<script setup>
import { logStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'viewJobLog'"
		ref="modalRef"
		label-id="viewJobLog"
		@close="closeModal">
		<div class="logModalContent">
			<div class="logModalContentHeader">
				<h2>View Job Log</h2>
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

			<strong>Arguments</strong>
			<table>
				<tr v-for="(value, key) in argumentsItems"
					:key="key">
					<td class="keyColumn">
						{{ key }}
					</td>
					<td>{{ value }}</td>
				</tr>
			</table>
			<br>

			<strong>Stack Trace</strong>
			<table>
				<tr v-for="(value, key) in stackTraceItems"
					:key="key">
					<td class="keyColumn">
						{{ key }}
					</td>
					<td>{{ value }}</td>
				</tr>
			</table>
		</div>
	</NcModal>
</template>

<script>
import {
	NcModal,
} from '@nextcloud/vue'

export default {
	name: 'ViewJobLog',
	components: {
		NcModal,
	},
	data() {
		return {
			hasUpdated: false,
			standardItems: {},
			stackTraceItems: {},
			argumentsItems: {},
		}
	},
	mounted() {
		logStore.viewLogItem && this.splitItems()
	},
	updated() {
		if (navigationStore.modal === 'viewJobLog' && !this.hasUpdated) {
			logStore.viewLogItem && this.splitItems()
			this.hasUpdated = true
		}
	},
	methods: {
		splitItems() {
			Object.entries(logStore.viewLogItem).forEach(([key, value]) => {
				if (key === 'stackTrace' || key === 'arguments') {
					this[`${key}Items`] = { ...value }
				} else {
					this.standardItems = { ...this.standardItems, [key]: value }
				}
			})
		},
		closeModal() {
			navigationStore.setModal(false)
			this.hasUpdated = false
			this.standardItems = {}
			this.stackTraceItems = {}
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
