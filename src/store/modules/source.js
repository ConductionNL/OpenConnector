/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Source } from '../../entities/index.js'

export const useSourceStore = defineStore(
	'source', {
		state: () => ({
			sourceItem: false,
			sourceTest: false,
			sourceList: [],
			sourceLog: false,
			sourceLogs: [],
			sourceConfigurationKey: null,
		}),
		actions: {
			setSourceItem(sourceItem) {
				this.sourceItem = sourceItem && new Source(sourceItem)
				console.log('Active source item set to ' + sourceItem)
				this.refreshSourceLogs()

			},
			setSourceTest(sourceTest) {
				this.sourceTest = sourceTest
				console.log('Source test set to ' + sourceTest)
			},
			setSourceList(sourceList) {
				this.sourceList = sourceList.map(
					(sourceItem) => new Source(sourceItem),
				)
				console.log('Source list set to ' + sourceList.length + ' items')
			},
			setSourceLog(sourceLog) {
				this.sourceLog = sourceLog
				console.log('Source log set')
			},
			setSourceLogs(sourceLogs) {
				this.sourceLogs = sourceLogs
				console.log('Source logs set to ' + sourceLogs.length + ' items')
			},
			setSourceConfigurationKey(sourceConfigurationKey) {
				this.sourceConfigurationKey = sourceConfigurationKey
				console.log('Source configuration key set to ' + sourceConfigurationKey)
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshSourceList(search = null) {
				// @todo this might belong in a service?
				let endpoint = '/index.php/apps/openconnector/api/sources'
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
									this.setSourceList(data.results)
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
			// New function to get a single source
			async getSource(id) {
				const endpoint = `/index.php/apps/openconnector/api/sources/${id}`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setSourceItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// New function to get source logs
			async refreshSourceLogs() {
				if (!this.sourceItem?.id) {
					return console.warn('No source item to refresh logs')
				}
				const endpoint = `/index.php/apps/openconnector/api/sources-logs/${this.sourceItem.id}`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setSourceLogs(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a source
			deleteSource() {
				if (!this.sourceItem || !this.sourceItem.id) {
					throw new Error('No source item to delete')
				}

				console.log('Deleting source...')

				const endpoint = `/index.php/apps/openconnector/api/sources/${this.sourceItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshSourceList()
					})
					.catch((err) => {
						console.error('Error deleting source:', err)
						throw err
					})
			},
			// Test a source
			testSource(testSourceItem) {
				if (!this.sourceItem) {
					throw new Error('No source item to test')
				}
				if (!testSourceItem) {
					throw new Error('No testobject to test')
				}

				console.log('Testing source...')

				const endpoint = `/index.php/apps/openconnector/api/source-test/${this.sourceItem.id}`

				return fetch(endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify(testSourceItem),
				})
					.then((response) => response.json())
					.then((data) => {
						this.setSourceTest(data)
						console.log('Source tested')
						// Refresh the source list
						this.refreshSourceLogs()
					})
					.catch((err) => {
						console.error('Error saving source:', err)
						this.refreshSourceLogs()
						throw err
					})
			},
			// Create or save a source from store
			saveSource(sourceItem) {
				if (!sourceItem) {
					throw new Error('No source item to save')
				}

				console.log('Saving source...')

				const isNewSource = !sourceItem.id
				const endpoint = isNewSource
					? '/index.php/apps/openconnector/api/sources'
					: `/index.php/apps/openconnector/api/sources/${sourceItem.id}`
				const method = isNewSource ? 'POST' : 'PUT'

				// Create a copy of the source item and remove empty properties
				const sourceToSave = { ...sourceItem }
				Object.keys(sourceToSave).forEach(key => {
					if (sourceToSave[key] === '' || (Array.isArray(sourceToSave[key]) && !sourceToSave[key].length)) {
						delete sourceToSave[key]
					}
				})

				// remove the dateCreated and dateModified fields
				delete sourceToSave.dateCreated
				delete sourceToSave.dateModified

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(sourceToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setSourceItem(data)
						console.log('Source saved')
						// Refresh the source list
						return this.refreshSourceList()
					})
					.catch((err) => {
						console.error('Error saving source:', err)
						throw err
					})
			},
		},
	},
)
