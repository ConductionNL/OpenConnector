<script setup>
import { consumerStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'editConsumer'"
		ref="modalRef"
		label-id="editConsumer"
		@close="closeModal">
		<div class="modalContent">
			<h2>{{ consumerItem.id ? 'Edit' : 'Add' }} Consumer</h2>

			<div v-if="success !== null">
				<NcNoteCard v-if="success" type="success">
					<p>Consumer successfully added</p>
				</NcNoteCard>
				<NcNoteCard v-if="error" type="error">
					<p>{{ error }}</p>
				</NcNoteCard>
			</div>

			<form v-if="success === null" @submit.prevent="handleSubmit">
				<div class="form-group editConsumerForm">
					<NcTextField
						label="Name*"
						:value.sync="consumerItem.name" />

					<NcTextArea
						label="Description"
						:value.sync="consumerItem.description" />

					<NcTextArea
						label="Domains"
						:value.sync="consumerItem.domains"
						helper-text="Enter domains separated by commas (e.g. example.com, example.org)." />

					<NcTextArea
						label="IPs"
						:value.sync="consumerItem.ips"
						helper-text="Enter IP's separated by commas (e.g. 127.0.0.1, 1.1.1.1)." />

					<NcSelect v-bind="authorizationTypeOptions"
						v-model="authorizationTypeOptions.value" />
				</div>
			</form>

			<NcButton
				v-if="success === null"
				:disabled="loading || !consumerItem.name"
				type="primary"
				@click="editConsumer()">
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
	name: 'EditConsumer',
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
			consumerItem: {
				name: '',
				description: '',
				domains: '',
				ips: '',
				authorizationType: '',
				authorizationConfiguration: '',
			},
			success: null,
			loading: false,
			error: false,
			authorizationTypeOptions: {
				inputLabel: 'Authorization Type',
				options: [
					{ label: 'none' },
					{ label: 'basic' },
					{ label: 'bearer' },
					{ label: 'apiKey' },
					{ label: 'oauth2' },
					{ label: 'jwt' },
				],
				value: {
					label: 'none',
				},
			},
			hasUpdated: false,
			closeTimeoutFunc: null,
		}
	},
	mounted() {
		this.initializeConsumerItem()
	},
	updated() {
		if (navigationStore.modal === 'editConsumer' && !this.hasUpdated) {
			this.initializeConsumerItem()
			this.hasUpdated = true
		}
	},
	methods: {
		initializeConsumerItem() {
			if (consumerStore.consumerItem?.id) {
				this.consumerItem = {
					...consumerStore.consumerItem,
					domains: consumerStore.consumerItem.domains.join(', '),
					ips: consumerStore.consumerItem.ips.join(', '),
				}

				// If the authorizationType of the consumerItem exists on the authorizationTypeOptions, apply it to the value
				// this is done for future proofing incase we were to change the authorization Types
				if (this.authorizationTypeOptions.options.map(i => i.label).indexOf(consumerStore.consumerItem.authorizationType) !== -1) {
					this.authorizationTypeOptions.value = { label: consumerStore.consumerItem.authorizationType }
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
			this.consumerItem = {
				name: '',
				description: '',
				domains: '',
				ips: '',
				authorizationType: '',
				authorizationConfiguration: '',
			}
			this.authorizationTypeOptions.value = { label: 'none' }
		},
		async editConsumer() {
			this.loading = true

			await consumerStore.saveConsumer({
				...this.consumerItem,
				domains: this.consumerItem.domains.trim().split(/ *, */g), // split on comma's, also take any spaces into consideration
				ips: this.consumerItem.ips.trim().split(/ *, */g),
				authorizationType: this.authorizationTypeOptions.value.label,
				authorizationConfiguration: [['']],
				// authorizationConfiguration is unclear as to what it does and why it exists, but to avoid any issues it'll still make a array array string
			}).then(({ response }) => {
				this.success = response.ok
				this.closeTimeoutFunc = setTimeout(this.closeModal, 2000)
			}).catch((e) => {
				this.success = false
				this.error = e.message || 'An error occurred while saving the consumer'
			}).finally(() => {
				this.loading = false
			})
		},
	},
}
</script>

<style lang="css">
.editConsumerForm .textarea__helper-text-message {
    padding-block: 4px;
    padding-inline: var(--border-radius-large);
    display: flex;
    align-items: center;
    color: var(--color-text-maxcontrast);
}
</style>
