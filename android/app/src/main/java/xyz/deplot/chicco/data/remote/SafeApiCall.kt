package xyz.deplot.chicco.data.remote

import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import java.io.IOException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import retrofit2.HttpException

/** Runs a Retrofit suspend call, mapping network/HTTP failures to a [ChiccoResult]. */
suspend fun <T> safeApiCall(block: suspend () -> T): ChiccoResult<T> {
    return try {
        ChiccoResult.Success(block())
    } catch (e: UnknownHostException) {
        ChiccoResult.Error(ChiccoErrorType.NO_CONNECTION, e.message)
    } catch (e: SocketTimeoutException) {
        ChiccoResult.Error(ChiccoErrorType.TIMEOUT, e.message)
    } catch (e: HttpException) {
        val type = if (e.code() == 401 || e.code() == 403) {
            ChiccoErrorType.UNAUTHORIZED
        } else {
            ChiccoErrorType.SERVER
        }
        ChiccoResult.Error(type, e.message())
    } catch (e: IOException) {
        ChiccoResult.Error(ChiccoErrorType.NO_CONNECTION, e.message)
    } catch (e: Exception) {
        ChiccoResult.Error(ChiccoErrorType.UNKNOWN, e.message)
    }
}
