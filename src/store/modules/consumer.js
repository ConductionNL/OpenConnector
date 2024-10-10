/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Consumer } from '../../entities/index.js'

export const useConsumerStore = defineStore(
	'consumer', {
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
				let consumer = '/index.php/apps/openconnector/api/consumers'
				if (search !== null && search !== '') {
					consumer = consumer + '?_search=' + search
				}
				return fetch(consumer, {
					method: 'GET',
				})
					.then(
						(response) => {
							response.json().then(
								(data) => {
									this.setConsumerList(data.results)
								},
							)
						},
					)
					.catch(
						(err) => {
							console.error(err)
						},
					)
			},
			// New function to get a single consumer
			async getConsumer(id) {
				const consumer = `/index.php/apps/openconnector/api/consumers/${id}`
				try {
					const response = await fetch(consumer, {
						method: 'GET',
					})
					const data = await response.json()
					this.setConsumerItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a consumer
			deleteConsumer() {
				if (!this.consumerItem || !this.consumerItem.id) {
					throw new Error('No consumer item to delete')
				}

				console.log('Deleting consumer...')

				const consumer = `/index.php/apps/openconnector/api/consumers/${this.consumerItem.id}`

				return fetch(consumer, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshConsumerList()
					})
					.catch((err) => {
						console.error('Error deleting consumer:', err)
						throw err
					})
			},
			// Create or save a consumer from store
			saveConsumer() {
				if (!this.consumerItem) {
					throw new Error('No consumer item to save')
				}

				console.log('Saving consumer...')

				const isNewConsumer = !this.consumerItem.id
				const consumer = isNewConsumer
					? '/index.php/apps/openconnector/api/consumers'
					: `/index.php/apps/openconnector/api/consumers/${this.consumerItem.id}`
				const method = isNewConsumer ? 'POST' : 'PUT'

				// Create a copy of the consumer item and remove empty properties
				const consumerToSave = { ...this.consumerItem }
				Object.keys(consumerToSave).forEach(key => {
					if (consumerToSave[key] === '' || (Array.isArray(consumerToSave[key]) && !consumerToSave[key].length)) {
						delete consumerToSave[key]
					}
				})

				return fetch(
					consumer,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(consumerToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setConsumerItem(data)
						console.log('Consumer saved')
						// Refresh the consumer list
						return this.refreshConsumerList()
					})
					.catch((err) => {
						console.error('Error saving consumer:', err)
						throw err
					})
			},
		},
	},
)
