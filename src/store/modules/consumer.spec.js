/* eslint-disable no-console */
import { setActivePinia, createPinia } from 'pinia'

import { useConsumerStore } from './consumer.js'
import { Consumer, mockConsumer } from '../../entities/index.js'

describe('Consumer Store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('sets consumer item correctly', () => {
		const store = useConsumerStore()

		store.setConsumerItem(mockConsumer()[0])

		expect(store.consumerItem).toBeInstanceOf(Consumer)
		expect(store.consumerItem).toEqual(mockConsumer()[0])

		expect(store.consumerItem.validate().success).toBe(true)
	})

	it('sets consumer list correctly', () => {
		const store = useConsumerStore()

		store.setConsumerList(mockConsumer())

		expect(store.consumerList).toHaveLength(mockConsumer().length)

		store.consumerList.forEach((item, index) => {
			expect(item).toBeInstanceOf(Consumer)
			expect(item).toEqual(mockConsumer()[index])
			expect(item.validate().success).toBe(true)
		})
	})

	// ... other tests ...
})
