/* eslint-disable @typescript-eslint/no-explicit-any */
import { SafeParseReturnType, z } from 'zod'
import { TCallLog } from './callLog.types'

export class CallLog implements TCallLog {

	public id?: string
	public sourceId: string
	public endpoint: string
	public method: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'
	public statusCode: number
	public requestHeaders?: object
	public requestBody?: any
	public responseHeaders?: object
	public responseBody?: any
	public duration: number
	public error?: string | null
	public created: string
	public updated?: string | null

	constructor(callLog: TCallLog) {
		this.id = callLog.id
		this.sourceId = callLog.sourceId
		this.endpoint = callLog.endpoint
		this.method = callLog.method
		this.statusCode = callLog.statusCode
		this.requestHeaders = callLog.requestHeaders
		this.requestBody = callLog.requestBody
		this.responseHeaders = callLog.responseHeaders
		this.responseBody = callLog.responseBody
		this.duration = callLog.duration
		this.error = callLog.error || null
		this.created = callLog.created
		this.updated = callLog.updated || null
	}

	public validate(): SafeParseReturnType<TCallLog, unknown> {
		const schema = z.object({
			id: z.string().uuid().optional(),
			sourceId: z.string().uuid(),
			endpoint: z.string(),
			method: z.enum(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']),
			statusCode: z.number().int().positive(),
			requestHeaders: z.record(z.any()).optional(),
			requestBody: z.any().optional(),
			responseHeaders: z.record(z.any()).optional(),
			responseBody: z.any().optional(),
			duration: z.number().positive(),
			error: z.string().nullable().optional(),
			created: z.string().datetime(),
			updated: z.string().datetime().nullable().optional()
		})

		return schema.safeParse({ ...this })
	}

}
