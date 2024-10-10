import { Endpoint } from './endpoint'
import { TEndpoint } from './endpoint.types'

export const mockEndpointData = (): TEndpoint[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		uuid: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		name: 'User Authentication',
		description: 'Handles user authentication requests',
		reference: '',
		version: '1.0.0',
		endpoint: '/api/auth',
		endpointArray: ['/api/auth'],
		endpointRegex: '',
		method: 'POST',
		targetType: '',
		targetId: '',
		created: '2024-10-08T09:05:25.812Z',
		updated: '2024-10-08T09:05:25.812Z',
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		uuid: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		name: 'Get User Profile',
		description: 'Retrieves user profile information',
		reference: '',
		version: '1.1.0',
		endpoint: '/api/user/profile',
		endpointArray: ['/api/user/profile'],
		endpointRegex: '',
		method: 'GET',
		targetType: '',
		targetId: '',
		created: '2024-10-08T09:05:25.812Z',
		updated: '2024-10-08T09:05:25.812Z',
	},
]

export const mockEndpoint = (data: TEndpoint[] = mockEndpointData()): TEndpoint[] => data.map(item => new Endpoint(item))
