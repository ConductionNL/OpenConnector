/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Synchronization } from '../../entities/index.js'

export const useSynchronizationStore = defineStore(
	'synchronization', {
		state: () => ({
			synchronizationItem: false,
			synchronizationList: [],
			synchronizationContracts: [],
			synchronizationLogs: [],
		}),
		actions: {
			setSynchronizationItem(synchronizationItem) {
				this.synchronizationItem = synchronizationItem && new Synchronization(synchronizationItem)
				console.log('Active synchronization item set to ' + synchronizationItem)
			},
			setSynchronizationList(synchronizationList) {
				this.synchronizationList = synchronizationList.map(
					(synchronizationItem) => new Synchronization(synchronizationItem),
				)
				console.log('Synchronization list set to ' + synchronizationList.length + ' items')
			},
			setSynchronizationContracts(synchronizationContracts) {
				this.synchronizationContracts = synchronizationContracts.map(
					(synchronizationContract) => new SynchronizationContract(synchronizationContract),
				)
				console.log('Synchronization contracts set to ' + synchronizationContracts.length + ' items')
			},
			setSynchronizationLogs(synchronizationLogs) {
				this.synchronizationLogs = synchronizationLogs.map(
					(synchronizationLog) => new SynchronizationLog(synchronizationLog),
				)
				console.log('Synchronization logs set to ' + synchronizationLogs.length + ' items')
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshSynchronizationList(search = null) {
				// @todo this might belong in a service?
				let endpoint = '/index.php/apps/openconnector/api/synchronizations'
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				return fetch(endpoint, {
					method: 'GET',
				})
					.then(
						(response) => {
							response.json().then(
								(data) => {
									this.setSynchronizationList(data.results)
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
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshSynchronizationContracts(search = null) {
				// @todo this might belong in a service?
				let endpoint = `/index.php/apps/openconnector/api/synchronizations-contracts/${this.synchronizationItem.id}`
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				return fetch(endpoint, {
					method: 'GET',
				})
					.then(
						(response) => {
							response.json().then(
								(data) => {
									this.setSynchronizationContracts(data.results)
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
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshSynchronizationLogs(search = null) {
				// @todo this might belong in a service?
				let endpoint = `/index.php/apps/openconnector/api/synchronizations-logs/${this.synchronizationItem.id}`
				if (search !== null && search !== '') {
					endpoint = endpoint + '?_search=' + search
				}
				return fetch(endpoint, {
					method: 'GET',
				})
					.then(
						(response) => {
							response.json().then(
								(data) => {
									this.setSynchronizationLogs(data.results)
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
			// New function to get a single synchronization
			async getSynchronization(id) {
				const endpoint = `/index.php/apps/openconnector/api/synchronizations/${id}`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setSynchronizationItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a synchronization
			deleteSynchronization() {
				if (!this.synchronizationItem || !this.synchronizationItem.id) {
					throw new Error('No synchronization item to delete')
				}

				console.log('Deleting synchronization...')

				const endpoint = `/index.php/apps/openconnector/api/synchronizations/${this.synchronizationItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshSynchronizationList()
					})
					.catch((err) => {
						console.error('Error deleting synchronization:', err)
						throw err
					})
			},
			// Create or save a synchronization from store
			saveSynchronization(synchronizationItem) {
				if (!synchronizationItem) {
					throw new Error('No synchronization item to save')
				}

				console.log('Saving synchronization...')

				const isNewSynchronization = !synchronizationItem?.id
				const endpoint = isNewSynchronization
					? '/index.php/apps/openconnector/api/synchronizations'
					: `/index.php/apps/openconnector/api/synchronizations/${synchronizationItem.id}`
				const method = isNewSynchronization ? 'POST' : 'PUT'

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(synchronizationItem),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setSynchronizationItem(data)
						console.log('Synchronization saved')

						this.refreshSynchronizationList()
					})
					.catch((err) => {
						console.error('Error saving synchronization:', err)
						throw err
					})
			},
		},
	},
)
