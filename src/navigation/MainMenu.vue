<script setup>
import { navigationStore } from '../store/store.js'
</script>

<template>
	<NcAppNavigation>
		<NcAppNavigationList>
			<NcAppNavigationItem :active="navigationStore.selected === 'dashboard'" name="Dashboard" @click="navigationStore.setSelected('dashboard')">
				<template #icon>
					<Finance :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'sources'" name="Sources" @click="navigationStore.setSelected('sources')">
				<template #icon>
					<DatabaseArrowLeftOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'endpoints'" name="Endpoints" @click="navigationStore.setSelected('endpoints')">
				<template #icon>
					<Api :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'consumers'" name="Consumers" @click="navigationStore.setSelected('consumers')">
				<template #icon>
					<Webhook :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'mappings'" name="Mappings" @click="navigationStore.setSelected('mappings')">
				<template #icon>
					<SitemapOutline :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'jobs'" name="Jobs" @click="navigationStore.setSelected('jobs')" :allowCollapse="true" :open="true">
				<template #icon>
					<Update :size="20" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'notifications'" name="Cloud Events" @click="navigationStore.setSelected('notifications')">
				<template #icon>
					<CloudUploadOutline :size="20" />
				</template>
				<template>
					<NcAppNavigationItem name="Subscriber List" />
					<NcAppNavigationItem name="Events" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem :active="navigationStore.selected === 'synchronizations'" name="Synchronization" @click="navigationStore.setSelected('synchronizations')">
				<template #icon>
					<VectorPolylinePlus :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigationList>
	</NcAppNavigation>
</template>

<script>
import {
	NcAppNavigation,
	NcAppNavigationList,
	NcAppNavigationItem,
} from '@nextcloud/vue'

// Icons
import Finance from 'vue-material-design-icons/Finance.vue'
import DatabaseArrowLeftOutline from 'vue-material-design-icons/DatabaseArrowLeftOutline.vue'
import Api from 'vue-material-design-icons/Api.vue'
import Webhook from 'vue-material-design-icons/Webhook.vue'
import SitemapOutline from 'vue-material-design-icons/SitemapOutline.vue'
import Update from 'vue-material-design-icons/Update.vue'
import VectorPolylinePlus from 'vue-material-design-icons/VectorPolylinePlus.vue'
import CloudUploadOutline from 'vue-material-design-icons/CloudUploadOutline.vue'
export default {
	name: 'MainMenu',
	components: {
		// components
		NcAppNavigation,
		NcAppNavigationList,
		NcAppNavigationItem,
		// icons
		Finance,
		DatabaseArrowLeftOutline,
		Api,
		Webhook,
		SitemapOutline,
		Update,
		VectorPolylinePlus,
		CloudUploadOutline,
	},
	methods: {
		openLink(url, type = '') {
			window.open(url, type)
		},
	},
}
</script>
