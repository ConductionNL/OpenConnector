<script setup>
import { synchronizationStore, navigationStore, searchStore } from '../../store/store.js'
</script>

<template>
	<NcAppContentList>
		<ul>
			<div class="listHeader">
				<NcTextField
					:value.sync="searchStore.search"
					:show-trailing-button="searchStore.search !== ''"
					label="Search"
					class="searchField"
					trailing-button-icon="close"
					@trailing-button-click="synchronizationStore.refreshSynchronizationList()">
					<Magnify :size="20" />
				</NcTextField>
				<NcActions>
					<NcActionButton @click="synchronizationStore.refreshSynchronizationList()">
						<template #icon>
							<Refresh :size="20" />
						</template>
						Ververs
					</NcActionButton>
					<NcActionButton @click="synchronizationStore.setSynchronizationItem({}); navigationStore.setModal('editSynchronization')">
						<template #icon>
							<Plus :size="20" />
						</template>
						Synchronisatie toevoegen
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="synchronizationStore.synchronizationList && synchronizationStore.synchronizationList.length > 0">
				<NcListItem v-for="(synchronization, i) in synchronizationStore.synchronizationList"
					:key="`${synchronization}${i}`"
					:name="synchronization.name"
					:active="synchronizationStore.synchronizationItem?.id === synchronization?.id"
					:force-display-actions="true"
					@click="synchronizationStore.setSynchronizationItem(synchronization)">
					<template #icon>
						<VectorPolylinePlus :class="synchronizationStore.synchronizationItem?.id === synchronization.id && 'selectedSynchronizationIcon'"
							disable-menu
							:size="44" />
					</template>
					<template #subname>
						{{ synchronization?.description }}
					</template>
					<template #actions>
						<NcActionButton @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setModal('editSynchronization')">
							<template #icon>
								<Pencil />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="synchronizationStore.setSynchronizationItem(synchronization); navigationStore.setDialog('deleteSynchronization')">
							<template #icon>
								<TrashCanOutline />
							</template>
							Verwijderen
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
		</ul>

		<NcLoadingIcon v-if="!synchronizationStore.synchronizationList"
			class="loadingIcon"
			:size="64"
			appearance="dark"
			name="Synchronisaties aan het laden" />

		<div v-if="synchronizationStore.synchronizationList.length === 0">
			Er zijn nog geen synchronisaties gedefinieerd.
		</div>
	</NcAppContentList>
</template>

<script>
import { NcListItem, NcActionButton, NcAppContentList, NcTextField, NcLoadingIcon, NcActions } from '@nextcloud/vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import VectorPolylinePlus from 'vue-material-design-icons/VectorPolylinePlus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'SynchronizationsList',
	components: {
		NcListItem,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcTextField,
		NcLoadingIcon,
		Magnify,
		VectorPolylinePlus,
		Refresh,
		Plus,
		Pencil,
		TrashCanOutline,
	},
	mounted() {
		synchronizationStore.refreshSynchronizationList()
	},
}
</script>

<style>
/* Styles remain the same */
</style>
