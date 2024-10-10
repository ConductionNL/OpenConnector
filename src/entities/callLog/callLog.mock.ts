import { CallLog } from './callLog'
import { TCallLog } from './callLog.types'

export const mockCallLogData = (): TCallLog[] => [
	{
		id: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		sourceId: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		endpoint: '/api/users',
		method: 'GET',
		statusCode: 200,
		requestHeaders: { 'Content-Type': 'application/json' },
		requestBody: null,
		responseHeaders: { 'Content-Type': 'application/json' },
		responseBody: { users: [] },
		duration: 150,
		error: null,
		created: '2023-06-01T12:00:00Z',
		updated: null,
	},
	{
		id: '4c3edd34-a90d-4d2a-8894-adb5836ecde8',
		sourceId: '5137a1e5-b54d-43ad-abd1-4b5bff5fcd3f',
		endpoint: '/api/posts',
		method: 'POST',
		statusCode: 201,
		requestHeaders: { 'Content-Type': 'application/json' },
		requestBody: { title: 'New Post', content: 'This is a new post.' },
		responseHeaders: { 'Content-Type': 'application/json' },
		responseBody: { id: '123', message: 'Post created successfully' },
		duration: 200,
		error: null,
		created: '2023-06-02T14:30:00Z',
		updated: null,
	},
]

export const mockCallLog = (data: TCallLog[] = mockCallLogData()): TCallLog[] => data.map(item => new CallLog(item))
