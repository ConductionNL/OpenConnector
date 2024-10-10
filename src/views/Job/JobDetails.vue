<script setup>
import { jobStore, navigationStore, logStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="detailHeader">
					<h1 class="h1">
						{{ jobStore.jobItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Actions">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editJob')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Edit
						</NcActionButton>
						<NcActionButton @click="addJobArgument()">
							<template #icon>
								<Plus :size="20" />
							</template>
							Add Argument
						</NcActionButton>
						<NcActionButton @click="navigationStore.setModal('testJob')">
							<template #icon>
								<Update :size="20" />
							</template>
							Test
						</NcActionButton>

						<NcActionButton @click="refreshJobLogs()">
							<template #icon>
								<Sync :size="20" />
							</template>
							Refresh Logs
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteJob')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Delete
						</NcActionButton>
					</NcActions>
				</div>
				<span>{{ jobStore.jobItem.description }}</span>

				<div class="detailGrid">
					<div class="gridContent gridFullWidth">
						<b>id:</b>
						<p>{{ jobStore.jobItem.uuid }}</p>
					</div>
					<div class="gridContent">
						<b>Status:</b>
						<p>{{ jobStore.jobItem.status }}</p>
					</div>
					<div class="gridContent">
						<b>Enabled:</b>
						<p>{{ jobStore.jobItem.isEnabled }}</p>
					</div>
					<div class="gridContent">
						<b>Job Class:</b>
						<p>{{ jobStore.jobItem.jobClass }}</p>
					</div>
					<div class="gridContent">
						<b>Interval:</b>
						<p>{{ jobStore.jobItem.interval }}</p>
					</div>
					<div class="gridContent">
						<b>Execution Time:</b>
						<p>{{ jobStore.jobItem.executionTime }}</p>
					</div>
					<div class="gridContent">
						<b>Time Sensitive:</b>
						<p>{{ jobStore.jobItem.timeSensitive }}</p>
					</div>
					<div class="gridContent">
						<b>Allow Parallel Runs:</b>
						<p>{{ jobStore.jobItem.allowParallelRuns }}</p>
					</div>
					<div class="gridContent">
						<b>Single Run:</b>
						<p>{{ jobStore.jobItem.singleRun }}</p>
					</div>
					<div class="gridContent">
						<b>Next Run:</b>
						<p>
							{{ new Date(jobStore.jobItem.nextRun).toLocaleString() || 'N/A' }}
						</p>
					</div>
					<div class="gridContent">
						<b>Last Run:</b>
						<p>
							{{ new Date(jobStore.jobItem.lastRun).toLocaleString() || 'N/A' }}
						</p>
					</div>
				</div>

				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Job Arguments">
							<div v-if="jobStore.jobItem?.arguments !== null && Object.keys(jobStore.jobItem?.arguments).length > 0">
								<NcListItem v-for="(value, key, i) in jobStore.jobItem?.arguments"
									:key="`${key}${i}`"
									:name="key"
									:bold="false"
									:force-display-actions="true"
									:active="jobStore.jobArgumentKey === key"
									@click="setActiveJobArgumentKey(key)">
									<template #icon>
										<SitemapOutline
											:class="jobStore.jobArgumentKey === key && 'selectedZaakIcon'"
											disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ value }}
									</template>
									<template #actions>
										<NcActionButton @click="editJobArgument(key)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Edit
										</NcActionButton>
										<NcActionButton @click="deleteJobArgument(key)">
											<template #icon>
												<Delete :size="20" />
											</template>
											Delete
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="jobStore.jobItem?.arguments === null || !Object.keys(jobStore.jobItem?.arguments).length" class="tabPanel">
								No arguments found
							</div>
						</BTab>
						<BTab title="Logs">
							<div v-if="jobStore.jobLogs?.length">
								<NcListItem v-for="(log, i) in jobStore.jobLogs"
									:key="log.id + i"
									:class="getLevelColor(log.level)"
									:name="log.message"
									:bold="false"
									:counter-number="log.level"
									:force-display-actions="true"
									:active="logStore.activeLogKey === `jobLog-${log.id}`"
									@click="setActiveJobLog(log.id)">
									>
									<template #icon>
										<TimelineQuestionOutline disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ new Date(log.created).toLocaleString() }}
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
							<div v-if="!jobStore.jobLogs?.length" class="tabPanel">
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
import { NcActions, NcActionButton, NcListItem } from '@nextcloud/vue'
import { BTabs, BTab } from 'bootstrap-vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import TimelineQuestionOutline from 'vue-material-design-icons/TimelineQuestionOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import SitemapOutline from 'vue-material-design-icons/SitemapOutline.vue'
import Update from 'vue-material-design-icons/Update.vue'
import Sync from 'vue-material-design-icons/Sync.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'

export default {
	name: 'JobDetails',
	components: {
		NcActions,
		NcActionButton,
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
		Update,
		BTabs,
		BTab,
		NcListItem,
	},
	mounted() {
		jobStore.refreshJobLogs()
	},
	methods: {
		deleteJobArgument(key) {
			jobStore.setJobArgumentKey(key)
			navigationStore.setModal('deleteJobArgument')
		},
		editJobArgument(key) {
			jobStore.setJobArgumentKey(key)
			navigationStore.setModal('editJobArgument')
		},
		addJobArgument() {
			jobStore.setJobArgumentKey(null)
			navigationStore.setModal('editJobArgument')
		},
		setActiveJobArgumentKey(jobArgumentKey) {
			if (jobStore.jobArgumentKey === jobArgumentKey) {
				jobStore.setJobArgumentKey(false)
			} else { jobStore.setJobArgumentKey(jobArgumentKey) }
		},
		setActiveJobLog(jobLogId) {
			if (logStore.activeLogKey === `jobLog-${jobLogId}`) {
				logStore.setActiveLogKey(null)
			} else {
				logStore.setActiveLogKey(`jobLog-${jobLogId}`)
			}
		},
		viewLog(log) {
			logStore.setViewLogItem(log)
			navigationStore.setModal('viewJobLog')
		},
		refreshJobLogs() {
			jobStore.refreshJobLogs()
		},
		getLevelColor(level) {
			switch (level) {
			case 'SUCCESS':
				return 'successLevel'
			case 'INFO':
				return 'infoLevel'
			case 'NOTICE':
				return 'noticeLevel'
			case 'WARNING':
				return 'warningLevel'
			case 'ERROR':
				return 'errorLevel'
			case 'CRITICAL':
				return 'criticalLevel'
			case 'ALERT':
				return 'alertLevel'
			case 'EMERGENCY':
				return 'emergencyLevel'
			case 'DEBUG':
				return 'debugLevel'
			default:
				return 'debugLevel'
			}
		},

	},
}
</script>

<style>
	.successLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-success);
		color: var(--OC-color-status-success);
	}

	.errorLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-error);
		color: var(--OC-color-status-error);
	}

	.noticeLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-notice);
		color: var(--OC-color-status-notice);
	}

	.warningLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-warning);
		color: var(--OC-color-status-warning);
	}

	.infoLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-info);
		color: var(--OC-color-status-info);
	}

	.criticalLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-critical);
		color: var(--OC-color-status-critical);
	}

	.alertLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-alert);
		color: var(--OC-color-status-alert);
	}

	.emergencyLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-emergency);
		color: var(--OC-color-status-emergency);
	}

	.debugLevel * .counter-bubble__counter {
		background-color: var(--OC-color-status-background-debug);
		color: var(--OC-color-status-debug);
	}
</style>
