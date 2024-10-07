<script setup>
import { sourceStore, navigationStore, logStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="detailHeader">
					<h1 class="h1">
						{{ sourceStore.sourceItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Actions">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editSource')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Edit
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('testSource')">
							<template #icon>
								<Sync :size="20" />
							</template>
							Test
						</NcActionButton>
						<NcActionButton @click="addSourceConfiguration">
							<template #icon>
								<Plus :size="20" />
							</template>
							Add Configuration
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteSource')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Delete
						</NcActionButton>
					</NcActions>
				</div>
				<span>{{ sourceStore.sourceItem.description }}</span>

				<div class="detailGrid">
					<div class="gridContent gridFullWidth">
						<b>location:</b>
						<p>{{ sourceStore.sourceItem.location }}</p>
					</div>
				</div>
				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Endpoints" active>
							No endpoints found
						</BTab>
						<BTab title="Synchronizations">
							No synchronizations found
						</BTab>
						<BTab title="Logs">
							<div v-if="sourceStore.sourceLogs?.length">
								<NcListItem v-for="(log, i) in sourceStore.sourceLogs"
									:key="log.id + i"
									:class="checkIfStatusIsOk(log.statusCode) ? 'okStatus' : 'errorStatus'"
									:name="`${log.statusMessage} (response time: ${(log.response.responseTime / 1000).toFixed(3)} seconds)`"
									:bold="false"
									:counter-number="log.statusCode"
									:force-display-actions="true"
									:active="logStore.activeLogKey === log?.id"
									@click="logStore.setActiveLogKey(log.id)">
									<template #counter-number>
										<MathLog disable-menu
											:size="44" />
									</template>
									<template #icon>
										<TimelineQuestionOutline disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ log.createdAt.date }} - {{ log.createdAt.timezone }}
									</template>
									<template #actions>
										<NcActionButton @click="viewLog(log)">
											<template #icon>
												<EyeOutline :size="20" />
											</template>
											View
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!sourceStore.sourceLogs?.length" class="tabPanel">
								No logs found
							</div>
						</BTab>
					</BTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
// Components
import { BTabs, BTab } from 'bootstrap-vue'
import { NcActions, NcActionButton, NcListItem } from '@nextcloud/vue'

// Icons
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import MathLog from 'vue-material-design-icons/MathLog.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'

export default {
	name: 'SourceDetails',
	components: {
		NcActions,
		NcActionButton,
		BTabs,
		BTab,
		NcListItem,
		// Icons
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
		Sync,
	},
	mounted() {
		this.refreshSourceLogs()
	},
	methods: {
		deleteSourceConfiguration(key) {
			sourceStore.setSourceConfigurationKey(key)
			navigationStore.setModal('deleteSourceConfiguration')
		},
		editSourceConfiguration(key) {
			sourceStore.setSourceConfigurationKey(key)
			navigationStore.setModal('editSourceConfiguration')
		},
		addSourceConfiguration() {
			sourceStore.setSourceConfigurationKey(null)
			navigationStore.setModal('editSourceConfiguration')
		},
		viewLog(log) {
			logStore.setViewLogItem(log)
			navigationStore.setModal('viewLog')
		},
		setActiveSourceConfigurationKey(sourceConfigurationKey) {
			if (sourceStore.sourceConfigurationKey === sourceConfigurationKey) {
				sourceStore.setSourceConfigurationKey(false)
			} else { sourceStore.setSourceConfigurationKey(sourceConfigurationKey) }
		},
		refreshSourceLogs() {
			sourceStore.refreshSourceLogs()

		},
		checkIfStatusIsOk(statusCode) {
			if (statusCode > 199 && statusCode < 300) {
				return true
			}
			return false
		},
	},
}
</script>

<style>
.h1 {
	display: block !important;
	font-size: 2em !important;
	margin-block-start: 0.67em !important;
	margin-block-end: 0.67em !important;
	margin-inline-start: 0px !important;
	margin-inline-end: 0px !important;
	font-weight: bold !important;
	unicode-bidi: isolate !important;
  }

  .okStatus * .counter-bubble__counter {
	background-color: #69b090;
	color: white
  }

  .errorStatus * .counter-bubble__counter {
	background-color: #dd3c49;
	color: white
  }
</style>
