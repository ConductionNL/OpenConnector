<script setup>
import { endpointStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'editEndpoint'"
		ref="modalRef"
		label-id="editEndpoint"
		@close="closeModal">
		<div class="modalContent">
			<h2>{{ endpointItem.id ? 'Edit' : 'Add' }} Endpoint</h2>

			<div v-if="success !== null">
				<NcNoteCard v-if="success" type="success">
					<p>Endpoint successfully added</p>
				</NcNoteCard>
				<NcNoteCard v-if="error" type="error">
					<p>{{ error }}</p>
				</NcNoteCard>
			</div>

			<form v-if="success === null" @submit.prevent="handleSubmit">
				<div class="form-group">
					<NcTextField
						label="Name*"
						:value.sync="endpointItem.name" />

					<NcTextArea
						label="Description"
						:value.sync="endpointItem.description" />

					<NcTextField
						label="Reference"
						:value.sync="endpointItem.reference" />

					<NcTextField
						label="Version"
						:value.sync="endpointItem.version" />

					<NcTextField
						label="Endpoint"
						:value.sync="endpointItem.endpoint" />

					<NcTextArea
						label="Endpoint Array (split on ,)"
						:value.sync="endpointItem.endpointArray" />

					<NcTextField
						label="Endpoint Regex"
						:value.sync="endpointItem.endpointRegex" />

					<NcSelect v-bind="methodOptions"
						v-model="methodOptions.value" />

					<NcTextField
						label="Target Type"
						:value.sync="endpointItem.targetType" />

					<NcTextField
						label="Target Id"
						:value.sync="endpointItem.targetId" />
				</div>
			</form>

			<NcButton
				v-if="success === null"
				:disabled="loading || !endpointItem.name"
				type="primary"
				@click="editEndpoint()">
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
	NcSelect,
	NcLoadingIcon,
	NcNoteCard,
	NcTextField,
	NcTextArea,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'EditEndpoint',
	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		NcTextArea,
	},
	data() {
		return {
			endpointItem: {
				name: '',
				description: '',
				reference: '',
				version: '',
				endpoint: '',
				endpointArray: '',
				endpointRegex: '',
				method: '',
				targetType: '',
				targetId: '',
			},
			success: null,
			loading: false,
			error: false,
			methodOptions: {
				inputLabel: 'Method',
				options: [
					{ label: 'GET' },
					{ label: 'POST' },
					{ label: 'PUT' },
					{ label: 'DELETE' },
					{ label: 'PATCH' },
				],
				value: {
					label: 'GET',
				},
			},
			hasUpdated: false,
			closeTimeoutFunc: null,
		}
	},
	mounted() {
		this.initializeEndpointItem()
	},
	updated() {
		if (navigationStore.modal === 'editEndpoint' && !this.hasUpdated) {
			this.initializeEndpointItem()
			this.hasUpdated = true
		}
	},
	methods: {
		initializeEndpointItem() {
			if (endpointStore.endpointItem?.id) {
				this.endpointItem = {
					...endpointStore.endpointItem,
					name: endpointStore.endpointItem.name,
					description: endpointStore.endpointItem.description,
					reference: endpointStore.endpointItem.reference,
					version: endpointStore.endpointItem.version,
					endpoint: endpointStore.endpointItem.endpoint,
					endpointArray: endpointStore.endpointItem.endpointArray.join(', '),
					endpointRegex: endpointStore.endpointItem.endpointRegex,
					method: endpointStore.endpointItem.method,
					targetType: endpointStore.endpointItem.targetType,
					targetId: endpointStore.endpointItem.targetId,
				}

				// If the method of the endpointItem exists on the methodOptions, apply it to the value
				// this is done for future proofing incase we were to change the method options
				if (this.methodOptions.options.map(i => i.label).indexOf(endpointStore.endpointItem.method) >= 0) {
					this.methodOptions.value = { label: endpointStore.endpointItem.method }
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
			this.endpointItem = {
				name: '',
				description: '',
				reference: '',
				version: '',
				endpoint: '',
				endpointArray: '',
				endpointRegex: '',
				method: '',
				targetType: '',
				targetId: '',
			}
			this.methodOptions.value = { label: 'GET' }
		},
		async editEndpoint() {
			this.loading = true

			await endpointStore.saveEndpoint({
				...this.endpointItem,
				endpointArray: this.endpointItem.endpointArray.split(/ *, */g), // split on comma's, also take any spaces into consideration
				method: this.methodOptions.value.label,
			}).then(({ response }) => {
				this.success = response.ok
				this.closeTimeoutFunc = setTimeout(this.closeModal, 2000)
			}).catch((e) => {
				this.success = false
				this.error = e.message || 'An error occurred while saving the endpoint'
			}).finally(() => {
				this.loading = false
			})
		},
	},
}
</script>
