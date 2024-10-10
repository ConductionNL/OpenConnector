import { SafeParseReturnType, z } from 'zod'
import { TConsumer } from './consumer.types'

export class Consumer implements TConsumer {

	public id: string
	public uuid: string
	public name: string
	public description: string | null
	public reference: string | null
	public version: string
	public domains: string[]
	public ips: string[]
	public authorizationType: string | null
	public authorizationConfiguration: string[][]
	public created: string | null
	public updated: string | null

	constructor(consumer: TConsumer) {
		this.id = consumer.id || ''
		this.uuid = consumer.id || ''
		this.name = consumer.name || ''
		this.description = consumer.description || null
		this.reference = consumer.reference || null
		this.version = consumer.version || '0.0.0'
		this.domains = consumer.domains || []
		this.ips = consumer.ips || []
		this.authorizationType = consumer.authorizationType || null
		this.authorizationConfiguration = consumer.authorizationConfiguration || null
		this.created = consumer.created || null
		this.updated = consumer.updated || null
	}

	public validate(): SafeParseReturnType<TConsumer, unknown> {
		const schema = z.object({
			id: z.string().uuid(),
			uuid: z.string().uuid(),
			name: z.string().max(255),
			description: z.string().nullable(),
			reference: z.string().nullable(),
			version: z.string(),
			domains: z.array(z.string()),
			ips: z.array(z.string()),
			authorizationType: z.enum(['none', 'basic', 'bearer', 'apiKey', 'oauth2', 'jwt']).nullable(),
			authorizationConfiguration: z.string().nullable(),
			created: z.string().nullable(),
			updated: z.string().nullable()
		})

		return schema.safeParse({ ...this })
	}

}
