package xyz.deplot.chicco.data

/** Outcome of a repository call, distinguishing error causes for UI display. */
sealed interface ChiccoResult<out T> {
    data class Success<T>(val data: T) : ChiccoResult<T>
    data class Error(val type: ChiccoErrorType, val message: String? = null) : ChiccoResult<Nothing>
}

enum class ChiccoErrorType {
    NO_CONNECTION,
    TIMEOUT,
    SERVER,
    UNAUTHORIZED,
    UNKNOWN,
}

inline fun <T> ChiccoResult<T>.onSuccess(action: (T) -> Unit): ChiccoResult<T> {
    if (this is ChiccoResult.Success) action(data)
    return this
}

inline fun <T> ChiccoResult<T>.onError(action: (ChiccoResult.Error) -> Unit): ChiccoResult<T> {
    if (this is ChiccoResult.Error) action(this)
    return this
}
