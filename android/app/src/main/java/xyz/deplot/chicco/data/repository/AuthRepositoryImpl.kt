package xyz.deplot.chicco.data.repository

import javax.inject.Inject
import javax.inject.Singleton
import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.auth.TokenStorage

@Singleton
class AuthRepositoryImpl @Inject constructor(
    private val tokenStorage: TokenStorage,
    private val todaySummaryRepository: TodaySummaryRepository,
) : AuthRepository {

    override fun isLoggedIn(): Boolean = !tokenStorage.getToken().isNullOrBlank()

    override suspend fun saveToken(token: String): ChiccoResult<Unit> {
        tokenStorage.saveToken(token)
        // Ping a real endpoint to catch a bad token immediately; a network
        // hiccup shouldn't block the user from proceeding, only an explicit
        // 401/403 should.
        return when (val result = todaySummaryRepository.getTodaySummary()) {
            is ChiccoResult.Success -> ChiccoResult.Success(Unit)
            is ChiccoResult.Error -> if (result.type == ChiccoErrorType.UNAUTHORIZED) {
                tokenStorage.clearToken()
                ChiccoResult.Error(ChiccoErrorType.UNAUTHORIZED, "Token non valido.")
            } else {
                ChiccoResult.Success(Unit)
            }
        }
    }

    override fun logout() {
        tokenStorage.clearToken()
    }
}
