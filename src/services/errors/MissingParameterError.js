/**
 * Error thrown when a required parameter is missing.
 * @augments {Error}
 */
class MissingParameterError extends Error {

	/**
	 * Creates an instance of MissingParameterError.
	 * @param {string} parameterName - The name of the missing parameter.
	 * @param {{}} extraInfo - Extra information you might need to add
	 */
	constructor(parameterName, extraInfo = {}) {
		super(`Missing required parameter: ${parameterName}`)
		this.name = this.constructor.name
		this.parameterName = parameterName
		this.extraInfo = extraInfo

		if (Error.captureStackTrace) {
			Error.captureStackTrace(this, this.constructor)
		}
	}

}

export default MissingParameterError
