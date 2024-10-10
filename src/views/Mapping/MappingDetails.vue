<script setup>
import { mappingStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="detailHeader">
					<h1 class="h1">
						{{ mappingStore.mappingItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Actions">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editMapping')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Edit
						</NcActionButton>
						<NcActionButton @click="addMappingMapping()">
							<template #icon>
								<MapPlus :size="20" />
							</template>
							Add Mapping
						</NcActionButton>
						<NcActionButton @click="addMappingCast()">
							<template #icon>
								<SwapHorizontal :size="20" />
							</template>
							Add Cast
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteMapping')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Delete
						</NcActionButton>
					</NcActions>
				</div>
				<span>{{ mappingStore.mappingItem.description }}</span>

				<div class="detailGrid">
					<div class="gridContent gridFullWidth">
						<b>id:</b>
						<p>{{ mappingStore.mappingItem.uuid }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Reference:</b>
						<p>{{ mappingStore.mappingItem.reference }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Version:</b>
						<p>{{ mappingStore.mappingItem.version }}</p>
					</div>
				</div>
				<div class="tabContainer">
					<BTabs content-class="mt-3" justified>
						<BTab title="Mapping">
							<div v-if="mappingStore.mappingItem?.mapping !== null && Object.keys(mappingStore.mappingItem?.mapping || {}).length">
								<NcListItem v-for="(value, key, i) in mappingStore.mappingItem?.mapping"
									:key="`${key}${i}`"
									:name="key"
									:bold="false"
									:force-display-actions="true"
									:active="mappingStore.mappingMappingKey === key"
									@click="setActiveMappingMappingKey(key)">
									<template #icon>
										<SitemapOutline
											:class="mappingStore.mappingMappingKey === key && 'selectedZaakIcon'"
											disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ value }}
									</template>
									<template #actions>
										<NcActionButton @click="editMappingMapping(key)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Edit
										</NcActionButton>
										<NcActionButton @click="deleteMappingMapping(key)">
											<template #icon>
												<Delete :size="20" />
											</template>
											Delete
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!Object.keys(mappingStore.mappingItem?.mapping || {}).length" class="tabPanel">
								No mapping found
							</div>
						</BTab>
						<BTab title="Cast">
							<div v-if="mappingStore.mappingItem?.cast !== null && Object.keys(mappingStore.mappingItem?.cast || {}).length">
								<NcListItem v-for="(value, key, i) in mappingStore.mappingItem?.cast"
									:key="`${key}${i}`"
									:name="key"
									:bold="false"
									:force-display-actions="true"
									:active="mappingStore.mappingCastKey === key"
									@click="setActiveMappingCastKey(key)">
									<template #icon>
										<SwapHorizontal
											:class="mappingStore.mappingCastKey === key && 'selectedZaakIcon'"
											disable-menu
											:size="44" />
									</template>
									<template #subname>
										{{ value }}
									</template>
									<template #actions>
										<NcActionButton @click="editMappingCast(key)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											Edit
										</NcActionButton>
										<NcActionButton @click="deleteMappingCast(key)">
											<template #icon>
												<Delete :size="20" />
											</template>
											Delete
										</NcActionButton>
									</template>
								</NcListItem>
							</div>
							<div v-if="!Object.keys(mappingStore.mappingItem?.cast || {}).length" class="tabPanel">
								No cast found
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
import { BTab, BTabs } from 'bootstrap-vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import MapPlus from 'vue-material-design-icons/MapPlus.vue'
import SitemapOutline from 'vue-material-design-icons/SitemapOutline.vue'
import SwapHorizontal from 'vue-material-design-icons/SwapHorizontal.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'MappingDetails',
	components: {
		NcActions,
		NcActionButton,
		NcListItem,
		TrashCanOutline,
		BTab,
		BTabs,
	},
	methods: {
		deleteMappingMapping(key) {
			mappingStore.setMappingMappingKey(key)
			navigationStore.setModal('deleteMappingMapping')
		},
		editMappingMapping(key) {
			mappingStore.setMappingMappingKey(key)
			navigationStore.setModal('editMappingMapping')
		},
		addMappingMapping() {
			mappingStore.setMappingMappingKey(null)
			navigationStore.setModal('editMappingMapping')
		},
		setActiveMappingMappingKey(mappingMappingKey) {
			if (mappingStore.mappingMappingKey === mappingMappingKey) {
				mappingStore.setMappingMappingKey(false)
			} else { mappingStore.setMappingMappingKey(mappingMappingKey) }
		},
		deleteMappingCast(key) {
			mappingStore.setMappingCastKey(key)
			navigationStore.setModal('deleteMappingCast')
		},
		editMappingCast(key) {
			mappingStore.setMappingCastKey(key)
			navigationStore.setModal('editMappingCast')
		},
		addMappingCast() {
			mappingStore.setMappingCastKey(null)
			navigationStore.setModal('editMappingCast')
		},
		setActiveMappingCastKey(mappingCastKey) {
			if (mappingStore.mappingCastKey === mappingCastKey) {
				mappingStore.setMappingCastKey(false)
			} else { mappingStore.setMappingCastKey(mappingCastKey) }
		},
	},
}
</script>

<style>
/* Styles remain the same */
</style>
