/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Consumer } from '../../entities/index.js'
import { MissingParameterError, ValidationError } from '../../services/errors/index.js'

export const useConsumerStore = defineStore('consumer', {
	state: () => ({
		consumerItem: false,
		consumerList: [],
	}),
	actions: {
		setConsumerItem(consumerItem) {
			this.consumerItem = consumerItem && new Consumer(consumerItem)
			console.log('Active consumer item set to ' + consumerItem)
		},
		setConsumerList(consumerList) {
			this.consumerList = consumerList.map(
				(consumerItem) => new Consumer(consumerItem),
			)
			console.log('Consumer list set to ' + consumerList.length + ' items')
		},
		/* istanbul ignore next */ // ignore this for Jest until moved into a service
		async refreshConsumerList(search = null) {
			// @todo this might belong in a service?
			let endpoint = '/index.php/apps/openconnector/api/consumers'
			if (search !== null && search !== '') {
				endpoint = endpoint + '?_search=' + search
			}

			const response = await fetch(endpoint, {
				method: 'GET',
			})

			const data = (await response.json()).results
			const entities = data.map(consumerItem => new Consumer(consumerItem))

			this.setConsumerList(data)

			return { response, data, entities }
		},
		// New function to get a single consumer
		async getConsumer(id) {
			const endpoint = `/index.php/apps/openconnector/api/consumers/${id}`

			const response = await fetch(endpoint, {
				method: 'GET',
			})

			const data = await response.json()
			const entity = new Consumer(data)

			this.setConsumerItem(data)

			return { response, data, entity }
		},
		// Delete a consumer
		async deleteConsumer(consumerItem) {
			if (!consumerItem) {
				throw new MissingParameterError('consumerItem')
			}

			console.log('Deleting consumer...')

			const endpoint = `/index.php/apps/openconnector/api/consumers/${this.consumerItem.id}`

			const response = await fetch(endpoint, {
				method: 'DELETE',
			})

			response.ok && this.setConsumerItem(null)
			this.refreshConsumerList()

			return { response }
		},
		// Create or save a consumer from store
		async saveConsumer(consumerItem) {
			if (!consumerItem) {
				throw new MissingParameterError('consumerItem')
			}

			// update "updated" date to current date
			if (consumerItem.updated) {
				consumerItem.updated = new Date().toISOString()
			}

			// convert to an entity
			consumerItem = new Consumer(consumerItem)

			// verify data with Zod
			const validationResult = consumerItem.validate()
			if (!validationResult.success) {
				console.error(validationResult.error)
				console.log(consumerItem)
				throw new ValidationError(validationResult.error)
			}

			console.log('Saving consumer...')

			const isNewConsumer = !consumerItem.id
			const endpoint = isNewConsumer
				? '/index.php/apps/openconnector/api/consumers'
				: `/index.php/apps/openconnector/api/consumers/${consumerItem.id}`
			const method = isNewConsumer ? 'POST' : 'PUT'

			const response = await fetch(
				endpoint,
				{
					method,
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify(consumerItem),
				},
			)

			const data = await response.json()
			const entity = new Consumer(data)

			this.setConsumerItem(data)
			this.refreshConsumerList()

			return { response, data, entity }
		},
	},
})
