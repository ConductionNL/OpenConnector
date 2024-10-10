<script setup>
import { mappingStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcModal v-if="navigationStore.modal === 'editMapping'"
		ref="modalRef"
		label-id="editMapping"
		@close="closeModal">
		<div class="modalContent">
			<h2>Mapping {{ mappingStore.mappingItem?.id ? 'Edit' : 'Add' }}</h2>
			<NcNoteCard v-if="success" type="success">
				<p>Mapping successfully added</p>
			</NcNoteCard>
			<NcNoteCard v-if="error" type="error">
				<p>{{ error }}</p>
			</NcNoteCard>

			<form v-if="!success" @submit.prevent="handleSubmit">
				<div class="form-group">
					<NcTextField
						id="name"
						label="Name"
						:value.sync="mappingItem.name" />

					<NcTextArea
						id="description"
						label="Description"
						:value.sync="mappingItem.description" />
				</div>
			</form>

			<NcButton
				v-if="!success"
				:disabled="loading"
				type="primary"
				@click="editMapping()">
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
	NcLoadingIcon,
	NcNoteCard,
	NcTextField,
	NcTextArea,
} from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'

export default {
	name: 'EditMapping',
	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		NcTextArea,
		// Icons
		ContentSaveOutline,
	},
	data() {
		return {
			mappingItem: {
				name: '',
				description: '',
			},
			success: false,
			loading: false,
			error: false,
			hasUpdated: false,
			closeTimeoutFunc: null,
		}
	},
	mounted() {
		this.initializeMappingItem()
	},
	updated() {
		if (navigationStore.modal === 'editMapping' && !this.hasUpdated) {
			this.initializeMappingItem()
			this.hasUpdated = true
		}
	},
	methods: {
		initializeMappingItem() {
			if (mappingStore.mappingItem?.id) {
				this.mappingItem = {
					...mappingStore.mappingItem,
				}
			}
		},
		closeModal() {
			navigationStore.setModal(false)
			clearTimeout(this.closeTimeoutFunc)
			this.success = false
			this.loading = false
			this.error = false
			this.hasUpdated = false
			this.mappingItem = {
				id: null,
				name: '',
				description: '',
			}
		},
		async editMapping() {
			this.loading = true
			try {
				await mappingStore.saveMapping(this.mappingItem)
				// Close modal or show success message
				this.success = true
				this.loading = false
				this.closeTimeoutFunc = setTimeout(this.closeModal, 2000)
			} catch (error) {
				this.loading = false
				this.success = false
				this.error = error.message || 'An error occurred while saving the mapping'
			}
		},
	},
}
</script>
