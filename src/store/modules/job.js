/* eslint-disable no-console */
import { defineStore } from 'pinia'
import { Job } from '../../entities/index.js'

export const useJobStore = defineStore(
	'job', {
		state: () => ({
			jobItem: false,
			jobTest: false,
			jobList: [],
			jobLog: false,
			jobLogs: [],
			jobArgumentKey: null,
		}),
		actions: {
			setJobItem(jobItem) {
				this.jobItem = jobItem && new Job(jobItem)
				console.log('Active job item set to ' + jobItem)
			},
			setJobTest(jobTest) {
				this.jobTest = jobTest
				console.log('Job test set to ' + jobTest)
			},
			setJobList(jobList) {
				this.jobList = jobList.map(
					(jobItem) => new Job(jobItem),
				)
				console.log('Job list set to ' + jobList.length + ' items')
			},
			setJobLogs(jobLogs) {
				this.jobLogs = jobLogs
				console.log('Job logs set to ' + jobLogs.length + ' items')
			},
			setJobArgumentKey(jobArgumentKey) {
				this.jobArgumentKey = jobArgumentKey
				console.log('Active job argument key set to ' + jobArgumentKey)
			},
			/* istanbul ignore next */ // ignore this for Jest until moved into a service
			async refreshJobList(search = null) {
				// @todo this might belong in a service?
				let endpoint = '/index.php/apps/openconnector/api/jobs'
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
									this.setJobList(data.results)
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
			// New function to get a single job
			async getJob(id) {
				const endpoint = `/index.php/apps/openconnector/api/jobs/${id}`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setJobItem(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// New function to get source logs
			async refreshJobLogs() {
				const endpoint = `/index.php/apps/openconnector/api/jobs-logs/${this.jobItem.id}`
				try {
					const response = await fetch(endpoint, {
						method: 'GET',
					})
					const data = await response.json()
					this.setJobLogs(data)
					return data
				} catch (err) {
					console.error(err)
					throw err
				}
			},
			// Delete a job
			deleteJob() {
				if (!this.jobItem || !this.jobItem.id) {
					throw new Error('No job item to delete')
				}

				console.log('Deleting job...')

				const endpoint = `/index.php/apps/openconnector/api/jobs/${this.jobItem.id}`

				return fetch(endpoint, {
					method: 'DELETE',
				})
					.then((response) => {
						this.refreshJobList()
					})
					.catch((err) => {
						console.error('Error deleting job:', err)
						throw err
					})
			},
			// Test a job
			testJob() {
				if (!this.jobItem) {
					throw new Error('No job item to test')
				}
				console.log('Testing job...')

				const endpoint = `/index.php/apps/openconnector/api/jobs-test/${this.jobItem.id}`

				return fetch(endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					// body: JSON.stringify(testJobItem),
					body: JSON.stringify([]),
				})
					.then((response) => response.json())
					.then((data) => {
						this.setJobTest(data)
						console.log('Job tested')
						// Refresh the job list
						this.refreshJobLogs()
					})
					.catch((err) => {
						console.error('Error testing job:', err)
						this.refreshJobLogs()
						throw err
					})
			},
			// Create or save a job from store
			saveJob(jobItem) {
				if (!jobItem) {
					throw new Error('No job item to save')
				}

				console.log('Saving job...')

				const isNewJob = !jobItem.id
				const endpoint = isNewJob
					? '/index.php/apps/openconnector/api/jobs'
					: `/index.php/apps/openconnector/api/jobs/${jobItem.id}`
				const method = isNewJob ? 'POST' : 'PUT'

				// Create a copy of the job item and remove empty properties
				const jobToSave = { ...jobItem }
				Object.keys(jobToSave).forEach(key => {
					if (jobToSave[key] === '' || (Array.isArray(jobToSave[key]) && !jobToSave[key].length) || key === 'created' || key === 'updated') {
						delete jobToSave[key]
					}
				})

				return fetch(
					endpoint,
					{
						method,
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify(jobToSave),
					},
				)
					.then((response) => response.json())
					.then((data) => {
						this.setJobItem(data)
						console.log('Job saved')
						// Refresh the job list
						return this.refreshJobList()
					})
					.catch((err) => {
						console.error('Error saving job:', err)
						throw err
					})
			},
		},
	},
)
