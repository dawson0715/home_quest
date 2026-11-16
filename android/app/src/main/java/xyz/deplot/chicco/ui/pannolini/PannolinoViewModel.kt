package xyz.deplot.chicco.ui.pannolini

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import java.time.LocalDate
import java.time.LocalTime
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.DiaperType
import xyz.deplot.chicco.data.repository.DEFAULT_CHILD_ID
import xyz.deplot.chicco.data.repository.DiaperRepository
import xyz.deplot.chicco.util.roundedToQuarterHour

data class PannolinoFormState(
    val tipo: DiaperType = DiaperType.PEE,
    val data: LocalDate = LocalDate.now(),
    val orario: LocalTime = LocalTime.now().roundedToQuarterHour(),
    val note: String = "",
)

data class PannolinoUiState(
    val isLoading: Boolean = true,
    val error: ChiccoErrorType? = null,
    val pannoliniOggi: List<Diaper> = emptyList(),
    val isSaving: Boolean = false,
    val editing: Diaper? = null,
    val editForm: PannolinoFormState = PannolinoFormState(),
    val isSavingEdit: Boolean = false,
    val isDeleting: Boolean = false,
    val selectedDate: LocalDate = LocalDate.now(),
) {
    val conteggioPerTipo: Map<DiaperType, Int>
        get() = DiaperType.entries.associateWith { tipo -> pannoliniOggi.count { it.type == tipo } }
}

@HiltViewModel
class PannolinoViewModel @Inject constructor(
    private val diaperRepository: DiaperRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(PannolinoUiState())
    val uiState: StateFlow<PannolinoUiState> = _uiState.asStateFlow()

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            val date = _uiState.value.selectedDate
            when (val result = diaperRepository.getDiapersByDate(date.toString())) {
                is ChiccoResult.Success -> _uiState.update {
                    it.copy(isLoading = false, pannoliniOggi = result.data)
                }
                is ChiccoResult.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.type)
                }
            }
        }
    }

    fun giornoPrecedente() {
        _uiState.update { it.copy(selectedDate = it.selectedDate.minusDays(1)) }
        load()
    }

    fun giornoSuccessivo() {
        val current = _uiState.value.selectedDate
        if (current < LocalDate.now()) {
            _uiState.update { it.copy(selectedDate = current.plusDays(1)) }
            load()
        }
    }

    fun registraRapido(tipo: DiaperType) {
        val orario = LocalTime.now().roundedToQuarterHour()
        val diaper = Diaper(
            id = 0,
            childId = DEFAULT_CHILD_ID,
            date = LocalDate.now().toString(),
            hour = orario.hour,
            minute = orario.minute,
            type = tipo,
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true) }
            when (diaperRepository.createDiaper(diaper)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSaving = false) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSaving = false) }
            }
        }
    }

    fun iniziaModificaPannolino(pannolino: Diaper) {
        _uiState.update {
            it.copy(
                editing = pannolino,
                editForm = PannolinoFormState(
                    tipo = pannolino.type,
                    data = LocalDate.parse(pannolino.date),
                    orario = LocalTime.of(pannolino.hour, pannolino.minute),
                    note = pannolino.note ?: "",
                ),
            )
        }
    }

    fun annullaModifica() {
        _uiState.update { it.copy(editing = null) }
    }

    fun onModificaTipoChange(tipo: DiaperType) {
        _uiState.update { it.copy(editForm = it.editForm.copy(tipo = tipo)) }
    }

    fun onModificaOrarioChange(orario: LocalTime) {
        _uiState.update { it.copy(editForm = it.editForm.copy(orario = orario)) }
    }

    fun onModificaDataChange(data: LocalDate) {
        _uiState.update { it.copy(editForm = it.editForm.copy(data = data)) }
    }

    fun onModificaNoteChange(note: String) {
        _uiState.update { it.copy(editForm = it.editForm.copy(note = note)) }
    }

    fun salvaModificaPannolino() {
        val state = _uiState.value
        val editing = state.editing ?: return
        val form = state.editForm
        val orario = form.orario.roundedToQuarterHour()
        val diaper = editing.copy(
            date = form.data.toString(),
            hour = orario.hour,
            minute = orario.minute,
            type = form.tipo,
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSavingEdit = true) }
            when (diaperRepository.updateDiaper(diaper)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSavingEdit = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSavingEdit = false) }
            }
        }
    }

    fun eliminaPannolino() {
        val editing = _uiState.value.editing ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isDeleting = true) }
            when (diaperRepository.deleteDiaper(editing.id)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isDeleting = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isDeleting = false) }
            }
        }
    }
}
