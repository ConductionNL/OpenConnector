export type TConsumer = {
    id: string
    uuid: string
    name: string
    description: string | null
    reference: string | null
    version: string
    domains: string[]
    ips: string[]
    authorizationType: string | null
    authorizationConfiguration: string[][]
    created: string | null
    updated: string | null
}
