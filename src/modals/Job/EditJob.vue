<script setup>
import { jobStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'editJob'"
		ref="modalRef"
		label-id="editJob"
		@close="closeModal">
		<div class="modalContent">
			<h2>{{ jobItem?.id ? 'Edit' : 'Add' }} job</h2>
			<NcNoteCard v-if="success" type="success">
				<p>Successfully added job</p>
			</NcNoteCard>
			<NcNoteCard v-if="error" type="error">
				<p>{{ error }}</p>
			</NcNoteCard>

			<form v-if="!success" @submit.prevent="handleSubmit">
				<div class="form-group">
					<NcTextField
						label="Name"
						maxlength="255"
						:value.sync="jobItem.name"
						required />

					<NcTextArea
						label="Description"
						:value.sync="jobItem.description" />

					<NcTextField
						label="Job Class"
						maxlength="255"
						:value.sync="jobItem.jobClass" />

					<NcInputField
						type="number"
						label="Interval"
						:value.sync="jobItem.interval" />

					<NcInputField
						type="number"
						label="Execution Time"
						:value.sync="jobItem.executionTime" />

					<div class="jobCheckboxContainerGrid">
						<NcCheckboxRadioSwitch
							:disabled="loading"
							:checked.sync="jobItem.timeSensitive">
							Time Sensitive
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch
							:disabled="loading"
							:checked.sync="jobItem.allowParallelRuns">
							Allow Parallel Runs
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch
							:disabled="loading"
							:checked.sync="jobItem.isEnabled">
							Enabled
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch
							:disabled="loading"
							:checked.sync="jobItem.singleRun">
							Single Run
						</NcCheckboxRadioSwitch>
					</div>
					<div>
						<span>
							<p>Schedule After</p>
							<NcDateTimePicker v-model="jobItem.scheduleAfter"
								:disabled="loading"
								label="Schedule After" />
						</span>
					</div>
					<NcTextField
						label="User ID"
						maxlength="255"
						:value.sync="jobItem.userId" />

					<NcInputField
						type="number"
						label="Log Retention"
						:value.sync="jobItem.logRetention" />

					<NcInputField
						type="number"
						label="Error Retention"
						:value.sync="jobItem.errorRetention" />
				</div>
			</form>

			<NcButton
				v-if="!success"
				:disabled="loading || !jobItem.name"
				type="primary"
				@click="editJob()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<ContentSaveOutline v-if="!loading" :size="20" />
				</template>
				Save
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcModal,
	NcTextField,
	NcTextArea,
	NcLoadingIcon,
	NcNoteCard,
	NcInputField,
	NcCheckboxRadioSwitch,
	NcDateTimePicker,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'EditJob',
	components: {
		NcModal,
		NcTextField,
		NcTextArea,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcInputField,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		// Icons
		ContentSaveOutline,
	},
	data() {
		return {
			jobItem: {
				name: '',
				description: '',
				jobClass: 'OCA\\OpenConnector\\Action\\PingAction',
				interval: '3600',
				executionTime: '3600',
				timeSensitive: false,
				allowParallelRuns: false,
				isEnabled: true,
				singleRun: false,
				scheduleAfter: '',
				userId: '',
				logRetention: '3600',
				errorRetention: '86400',
			},
			success: false,
			loading: false,
			error: false,
			statusOptions: [
				{ label: 'Open', value: 'open' },
				{ label: 'In Progress', value: 'in_progress' },
				{ label: 'Completed', value: 'completed' },
			],
			hasUpdated: false,
			closeTimeoutFunc: null,
		}
	},
	mounted() {
		this.initializeJobItem()
	},
	updated() {
		if (navigationStore.modal === 'editJob' && !this.hasUpdated) {
			this.initializeJobItem()
			this.hasUpdated = true
		}
	},
	methods: {
		initializeJobItem() {

			if (jobStore.jobItem?.id) {
				const scheduleAfter = jobStore.jobItem.scheduleAfter ? new Date(jobStore.jobItem.scheduleAfter.date) || '' : null

				this.jobItem = {
					...jobStore.jobItem,
					name: jobStore.jobItem.name || '',
					description: jobStore.jobItem.description || '',
					jobClass: jobStore.jobItem.jobClass || '',
					interval: jobStore.jobItem.interval || '3600',
					executionTime: jobStore.jobItem.executionTime || '3600',
					timeSensitive: typeof jobStore.jobItem.timeSensitive === 'boolean' ? jobStore.jobItem.timeSensitive : false,
					allowParallelRuns: typeof jobStore.jobItem.allowParallelRuns === 'boolean' ? jobStore.jobItem.allowParallelRuns : false,
					isEnabled: typeof jobStore.jobItem.isEnabled === 'boolean' ? jobStore.jobItem.isEnabled : true,
					singleRun: typeof jobStore.jobItem.singleRun === 'boolean' ? jobStore.jobItem.singleRun : false,
					scheduleAfter,
					logRetention: jobStore.jobItem.logRetention || '3600',
					errorRetention: jobStore.jobItem.errorRetention || '86400',
					userId: jobStore.jobItem.userId || '',
				}
			}
		},
		closeModal() {
			navigationStore.setModal(false)
			clearTimeout(this.closeTimeoutFunc)
			this.success = null
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.jobItem = {
				name: '',
				description: '',
				jobClass: '',
				interval: '3600',
				executionTime: '3600',
				timeSensitive: false,
				allowParallelRuns: false,
				isEnabled: true,
				singleRun: false,
				scheduleAfter: '',
				logRetention: '3600',
				errorRetention: '86400',
				userId: '',
			}
		},
		async editJob() {
			this.loading = true
			try {
				await jobStore.saveJob({ ...this.jobItem })
				// Close modal or show success message
				this.success = true
				this.loading = false
				this.closeTimeoutFunc = setTimeout(this.closeModal, 2000)

			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the job'
			}
		},
	},
}
</script>
<style scoped>
.jobCheckboxContainerGrid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;
}
</style>
