import { Consumer } from './consumer'
import { mockConsumer } from './consumer.mock'

describe('Consumer Entity', () => {
	it('create Consumer entity with full data', () => {
		const consumer = new Consumer(mockConsumer()[0])

		expect(consumer).toBeInstanceOf(Consumer)
		expect(consumer).toEqual(mockConsumer()[0])

		expect(consumer.validate().success).toBe(true)
	})

	it('create Consumer entity with partial data', () => {
		const consumer = new Consumer(mockConsumer()[1])

		expect(consumer).toBeInstanceOf(Consumer)
		expect(consumer).toEqual(mockConsumer()[1])

		expect(consumer.validate().success).toBe(true)
	})
})
