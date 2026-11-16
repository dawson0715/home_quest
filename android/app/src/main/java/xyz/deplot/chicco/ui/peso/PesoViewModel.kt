package xyz.deplot.chicco.ui.peso

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import java.time.LocalDate
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.data.repository.DEFAULT_CHILD_ID
import xyz.deplot.chicco.data.repository.WeightRepository

data class PesoFormState(
    val grammi: Int = 4000,
    val data: LocalDate = LocalDate.now(),
    val note: String = "",
)

data class PesoUiState(
    val isLoading: Boolean = true,
    val error: ChiccoErrorType? = null,
    val storico: List<Weight> = emptyList(),
    val form: PesoFormState = PesoFormState(),
    val isSaving: Boolean = false,
    val editing: Weight? = null,
    val editForm: PesoFormState = PesoFormState(),
    val isSavingEdit: Boolean = false,
    val isDeleting: Boolean = false,
)

@HiltViewModel
class PesoViewModel @Inject constructor(
    private val weightRepository: WeightRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(PesoUiState())
    val uiState: StateFlow<PesoUiState> = _uiState.asStateFlow()

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = weightRepository.getHistory()) {
                is ChiccoResult.Success -> _uiState.update {
                    it.copy(isLoading = false, storico = result.data)
                }
                is ChiccoResult.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.type)
                }
            }
        }
    }

    fun onGrammiChange(grammi: Int) {
        _uiState.update { it.copy(form = it.form.copy(grammi = grammi.coerceAtLeast(0))) }
    }

    fun onDataChange(data: LocalDate) {
        _uiState.update { it.copy(form = it.form.copy(data = data)) }
    }

    fun onNoteChange(note: String) {
        _uiState.update { it.copy(form = it.form.copy(note = note)) }
    }

    fun salvaPeso() {
        val form = _uiState.value.form
        val weight = Weight(
            id = 0,
            childId = DEFAULT_CHILD_ID,
            date = form.data.toString(),
            weightGrams = form.grammi,
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true) }
            when (weightRepository.createWeight(weight)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSaving = false, form = PesoFormState()) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSaving = false) }
            }
        }
    }

    fun iniziaModificaPeso(peso: Weight) {
        _uiState.update {
            it.copy(
                editing = peso,
                editForm = PesoFormState(
                    grammi = peso.weightGrams,
                    data = LocalDate.parse(peso.date),
                    note = peso.note ?: "",
                ),
            )
        }
    }

    fun annullaModifica() {
        _uiState.update { it.copy(editing = null) }
    }

    fun onModificaGrammiChange(grammi: Int) {
        _uiState.update { it.copy(editForm = it.editForm.copy(grammi = grammi.coerceAtLeast(0))) }
    }

    fun onModificaDataChange(data: LocalDate) {
        _uiState.update { it.copy(editForm = it.editForm.copy(data = data)) }
    }

    fun onModificaNoteChange(note: String) {
        _uiState.update { it.copy(editForm = it.editForm.copy(note = note)) }
    }

    fun salvaModificaPeso() {
        val state = _uiState.value
        val editing = state.editing ?: return
        val form = state.editForm
        val weight = editing.copy(
            date = form.data.toString(),
            weightGrams = form.grammi,
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSavingEdit = true) }
            when (weightRepository.updateWeight(weight)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSavingEdit = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSavingEdit = false) }
            }
        }
    }

    fun eliminaPeso() {
        val editing = _uiState.value.editing ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isDeleting = true) }
            when (weightRepository.deleteWeight(editing.id)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isDeleting = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isDeleting = false) }
            }
        }
    }
}
