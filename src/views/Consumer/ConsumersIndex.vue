<script setup>
import { consumerStore, navigationStore } from '../../store/store.js'
</script>

<template>
	<NcAppContent>
		<template #list>
			<ConsumersList />
		</template>
		<template #default>
			<NcEmptyContent v-if="!consumerStore.consumerItem || navigationStore.selected != 'consumers'"
				class="detailContainer"
				name="Geen consumer"
				description="Nog geen consumer geselecteerd">
				<template #icon>
					<Webhook />
				</template>
				<template #action>
					<NcButton type="primary" @click="consumerStore.setConsumerItem(null); navigationStore.setModal('editConsumer')">
						Consumer toevoegen
					</NcButton>
				</template>
			</NcEmptyContent>
			<ConsumerDetails v-if="consumerStore.consumerItem && navigationStore.selected === 'consumers'" />
		</template>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import ConsumersList from './ConsumersList.vue'
import ConsumerDetails from './ConsumerDetails.vue'
import Webhook from 'vue-material-design-icons/Webhook.vue'

export default {
	name: 'ConsumersIndex',
	components: {
		NcAppContent,
		NcEmptyContent,
		NcButton,
		ConsumersList,
		ConsumerDetails,
		Webhook,
	},
}
</script>
