<script setup>
import { jobStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'testJob'"
		ref="modalRef"
		label-id="testJob"
		@close="closeModal">
		<div class="modalContent">
			<h2>Test job</h2>

			<NcButton
				:disabled="loading"
				type="primary"
				@click="testJob()">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Sync v-if="!loading" :size="20" />
				</template>
				Test job
			</NcButton>

			<NcNoteCard v-if="jobStore.jobTest && jobStore.jobTest.success" type="success">
				<p>The job test was successful.</p>
			</NcNoteCard>
			<NcNoteCard v-if="(jobStore.jobTest && !jobStore.jobTest.success) || error" type="error">
				<p>An error occurred while testing the job: {{ jobStore.jobTest ? jobStore.jobTest.message : error }}</p>
			</NcNoteCard>

			<div v-if="jobStore.jobTest">
				<p><b>UUID:</b> {{ jobStore.jobTest.uuid }}</p>
				<p><b>Level:</b> {{ jobStore.jobTest.level }}</p>
				<p><b>Message:</b> {{ jobStore.jobTest.message }}</p>
				<p><b>Job ID:</b> {{ jobStore.jobTest.jobId }}</p>
				<p><b>Job List ID:</b> {{ jobStore.jobTest.jobListId }}</p>
				<p><b>Job Class:</b> {{ jobStore.jobTest.jobClass }}</p>
				<p><b>Arguments:</b></p>
				<ul>
					<li v-for="(value, key) in jobStore.jobTest.arguments" :key="key">
						{{ key }}: {{ value }}
					</li>
				</ul>
				<p><b>Execution Time:</b> {{ jobStore.jobTest.executionTime }} ms</p>
				<p><b>User ID:</b> {{ jobStore.jobTest.userId || 'N/A' }}</p>
				<p><b>Session ID:</b> {{ jobStore.jobTest.sessionId || 'N/A' }}</p>
				<p><b>Stack Trace:</b></p>
				<ol>
					<li v-for="(step, index) in jobStore.jobTest.stackTrace" :key="index">
						{{ step }}
					</li>
				</ol>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcModal,
	NcLoadingIcon,
	NcTextField,
	NcNoteCard,
} from '@nextcloud/vue'
import Sync from 'vue-material-design-icons/Sync.vue'

export default {
	name: 'TestJob',
	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
		NcTextField,
		NcNoteCard,
	},
	data() {
		return {
			success: false,
			loading: false,
			error: false,
		}
	},
	methods: {
		closeModal() {
			navigationStore.setModal(false)
			this.success = false
			this.loading = false
			this.error = false
		},
		async testJob() {
			this.loading = true

			try {
				await jobStore.testJob()
				this.success = true
				this.loading = false
				this.error = false
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while testing the job'
				jobStore.setJobTest(false)
			}
		},
	},
}
</script>
<style>
.testJobDetailGrid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 5px;
}
</style>
