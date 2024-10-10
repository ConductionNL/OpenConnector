/* eslint-disable @typescript-eslint/no-explicit-any */
export type TCallLog = {
    id?: string
    sourceId: string
    endpoint: string
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'
    statusCode: number
    requestHeaders?: object
    requestBody?: any
    responseHeaders?: object
    responseBody?: any
    duration: number
    error?: string | null
    created: string
    updated?: string | null
}
