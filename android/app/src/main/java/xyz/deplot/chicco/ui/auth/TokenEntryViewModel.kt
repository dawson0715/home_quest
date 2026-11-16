package xyz.deplot.chicco.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.repository.AuthRepository

data class TokenEntryUiState(
    val token: String = "",
    val isSaving: Boolean = false,
    val errorMessage: String? = null,
    val savedSuccessfully: Boolean = false,
)

@HiltViewModel
class TokenEntryViewModel @Inject constructor(
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(TokenEntryUiState())
    val uiState: StateFlow<TokenEntryUiState> = _uiState.asStateFlow()

    fun onTokenChange(value: String) {
        _uiState.update { it.copy(token = value, errorMessage = null) }
    }

    fun save() {
        val token = _uiState.value.token.trim()
        if (token.isBlank()) {
            _uiState.update { it.copy(errorMessage = "Incolla il token generato via CLI (./yii user/create)") }
            return
        }
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true, errorMessage = null) }
            when (val result = authRepository.saveToken(token)) {
                is ChiccoResult.Success -> _uiState.update { it.copy(isSaving = false, savedSuccessfully = true) }
                is ChiccoResult.Error -> _uiState.update {
                    it.copy(isSaving = false, errorMessage = result.message ?: "Token non valido")
                }
            }
        }
    }
}
