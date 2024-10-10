<script setup>
import { endpointStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<div class="detailContainer">
		<div id="app-content">
			<div>
				<div class="detailHeader">
					<h1 class="h1">
						{{ endpointStore.endpointItem.name }}
					</h1>

					<NcActions :primary="true" menu-name="Acties">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						<NcActionButton @click="navigationStore.setModal('editEndpoint')">
							<template #icon>
								<Pencil :size="20" />
							</template>
							Bewerken
						</NcActionButton>
						<NcActionButton @click="navigationStore.setDialog('deleteEndpoint')">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							Verwijderen
						</NcActionButton>
					</NcActions>
				</div>

				<span>{{ endpointStore.endpointItem.description }}</span>

				<div class="detailGrid">
					<div class="gridContent gridFullWidth">
						<b>uuid:</b>
						<p>{{ endpointStore.endpointItem.uuid }}</p>
					</div>
					<div class="gridContent gridFullWidth" />

					<div class="gridContent gridFullWidth">
						<b>Name:</b>
						<p>{{ endpointStore.endpointItem.name }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Description:</b>
						<p>{{ endpointStore.endpointItem.description }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Reference:</b>
						<p>{{ endpointStore.endpointItem.reference }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Version:</b>
						<p>{{ endpointStore.endpointItem.version }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Endpoint:</b>
						<p>{{ endpointStore.endpointItem.endpoint }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Endpoint Array:</b>
						<p>{{ endpointStore.endpointItem.endpointArray.join(', ') }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Endpoint Regex:</b>
						<p>{{ endpointStore.endpointItem.endpointRegex }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Method:</b>
						<p>{{ endpointStore.endpointItem.method }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Target Type:</b>
						<p>{{ endpointStore.endpointItem.targetType }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>Target Id:</b>
						<p>{{ endpointStore.endpointItem.targetId }}</p>
					</div>

					<div class="gridContent gridFullWidth">
						<b>created:</b>
						<p>{{ endpointStore.endpointItem.created }}</p>
					</div>
					<div class="gridContent gridFullWidth">
						<b>updated:</b>
						<p>{{ endpointStore.endpointItem.updated }}</p>
					</div>
				</div>
				<!-- Add more endpoint-specific details here -->
			</div>
		</div>
	</div>
</template>

<script>
import { NcActions, NcActionButton } from '@nextcloud/vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'EndpointDetails',
	components: {
		NcActions,
		NcActionButton,
		DotsHorizontal,
		Pencil,
		TrashCanOutline,
	},
}
</script>

<style>
/* Styles remain the same */
</style>
