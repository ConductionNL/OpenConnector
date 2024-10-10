export type TConsumer = {
    id: number
    uuid: string
    name: string
    description: string
    domains: string[]
    ips: string[]
    authorizationType: 'none' | 'basic' | 'bearer' | 'apiKey' | 'oauth2' | 'jwt'
    authorizationConfiguration: string[][]
    created: string
    updated: string
}
