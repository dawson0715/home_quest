package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult

interface AuthRepository {
    fun isLoggedIn(): Boolean

    /**
     * Stores the bearer token pasted by the user (there's no login endpoint
     * — tokens are provisioned out-of-band via `./yii user/create` on the
     * backend) and validates it with a lightweight API call.
     */
    suspend fun saveToken(token: String): ChiccoResult<Unit>

    fun logout()
}
