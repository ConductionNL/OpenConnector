/**
 * Error thrown when data validation fails using Zod.
 * @augments {Error}
 */
class ValidationError extends Error {

	/**
	 * Creates an instance of ValidationError.
	 * @param {import('zod').ZodError} zodError - The ZodError object containing validation issues.
	 */
	constructor(zodError) {
		super('Data validation failed.')
		this.name = 'ZodValidationError'

		/**
		 * An array of validation issues.
		 * @type {Array<{ path: string, message: string, code: string }>}
		 */
		this.errors = zodError.errors.map(err => ({
			field: err.path.join('.'),
			failedRule: err.code,
			message: err.message,
		}))

		if (Error.captureStackTrace) {
			Error.captureStackTrace(this, ValidationError)
		}
	}

}

export default ValidationError
