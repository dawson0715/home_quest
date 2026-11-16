package xyz.deplot.chicco.data.auth

/** Persists the Bearer token used to authenticate Retrofit calls. */
interface TokenStorage {
    fun getToken(): String?
    fun saveToken(token: String)
    fun clearToken()
}
