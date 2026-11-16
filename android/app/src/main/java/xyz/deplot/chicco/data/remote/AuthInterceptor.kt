package xyz.deplot.chicco.data.remote

import javax.inject.Inject
import okhttp3.Interceptor
import okhttp3.Response
import xyz.deplot.chicco.data.auth.TokenStorage

/** Attaches the stored Bearer token to every outgoing request, if present. */
class AuthInterceptor @Inject constructor(
    private val tokenStorage: TokenStorage,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = tokenStorage.getToken()
        val request = chain.request().let { original ->
            if (token.isNullOrBlank()) {
                original
            } else {
                original.newBuilder()
                    .addHeader("Authorization", "Bearer $token")
                    .build()
            }
        }
        return chain.proceed(request)
    }
}
