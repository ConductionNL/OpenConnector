/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useEndpointStore } from './endpoints.js'
import { Endpoint, mockEndpoint } from '../../entities/index.js'

describe('Endpoint Store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('sets endpoint item correctly', () => {
		const store = useEndpointStore()

		store.setEndpointItem(mockEndpoint()[0])

		expect(store.endpointItem).toBeInstanceOf(Endpoint)
		expect(store.endpointItem).toEqual(mockEndpoint()[0])

		expect(store.endpointItem.validate().success).toBe(true)
	})

	it('sets endpoint list correctly', () => {
		const store = useEndpointStore()

		store.setEndpointList(mockEndpoint())

		expect(store.endpointList).toHaveLength(mockEndpoint().length)

		store.endpointList.forEach((item, index) => {
			expect(item).toBeInstanceOf(Endpoint)
			expect(item).toEqual(mockEndpoint()[index])
			expect(item.validate().success).toBe(true)
		})
	})

	// ... other tests ...
})
