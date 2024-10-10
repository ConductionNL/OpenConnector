import { Consumer } from './consumer'
import { TConsumer } from './consumer.types'

export const mockConsumerData = (): TConsumer[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		uuid: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		name: 'Mobile App',
		description: 'Consumer for the mobile application',
		reference: 'MOB-001',
		version: '1.0.0',
		domains: ['mobile.example.com'],
		ips: ['192.168.1.1'],
		authorizationType: 'public',
		authorizationConfiguration: [],
		created: '2023-02-01T00:00:00Z',
		updated: '2023-02-01T00:00:00Z'
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		uuid: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: 'Web Dashboard',
		description: 'Consumer for the web dashboard',
		reference: 'WEB-001',
		version: '1.1.0',
		domains: ['dashboard.example.com'],
		ips: ['192.168.1.2'],
		authorizationType: 'api-key',
		authorizationConfiguration: [['key', '4c3edd34-a90d-4d2a-8894-adb5836ecde8']],
		created: '2023-02-01T00:00:00Z',
		updated: '2023-02-01T00:00:00Z'
	},
]

export const mockConsumer = (data: TConsumer[] = mockConsumerData()): TConsumer[] => data.map(item => new Consumer(item))
