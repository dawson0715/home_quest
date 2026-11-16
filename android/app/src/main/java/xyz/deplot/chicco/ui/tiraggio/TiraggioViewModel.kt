package xyz.deplot.chicco.ui.tiraggio

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
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.Side
import xyz.deplot.chicco.data.repository.DEFAULT_CHILD_ID
import xyz.deplot.chicco.data.repository.PumpingRepository
import xyz.deplot.chicco.data.repository.TodaySummaryRepository
import xyz.deplot.chicco.util.roundedToQuarterHour

data class TiraggioFormState(
    val data: LocalDate = LocalDate.now(),
    val orario: LocalTime = LocalTime.now().roundedToQuarterHour(),
    val lato: Side = Side.LEFT,
    val quantitaMl: Int = 60,
    val note: String = "",
)

data class TiraggioUiState(
    val isLoading: Boolean = true,
    val error: ChiccoErrorType? = null,
    // No dedicated "scorta" endpoint on the backend — the stock balance only
    // comes back as pumping_stock_ml inside GET /today-summary.
    val pumpingStockMl: Double? = null,
    val tiraggiOggi: List<Pumping> = emptyList(),
    val form: TiraggioFormState = TiraggioFormState(),
    val isSaving: Boolean = false,
    val editing: Pumping? = null,
    val editForm: TiraggioFormState = TiraggioFormState(),
    val isSavingEdit: Boolean = false,
    val isDeleting: Boolean = false,
    val selectedDate: LocalDate = LocalDate.now(),
)

@HiltViewModel
class TiraggioViewModel @Inject constructor(
    private val pumpingRepository: PumpingRepository,
    private val todaySummaryRepository: TodaySummaryRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(TiraggioUiState())
    val uiState: StateFlow<TiraggioUiState> = _uiState.asStateFlow()

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val summaryResult = todaySummaryRepository.getTodaySummary()) {
                is ChiccoResult.Success -> _uiState.update {
                    it.copy(pumpingStockMl = summaryResult.data.pumpingStockMl)
                }
                is ChiccoResult.Error -> Unit
            }
            val date = _uiState.value.selectedDate
            when (val result = pumpingRepository.getPumpingsByDate(date.toString())) {
                is ChiccoResult.Success -> _uiState.update {
                    it.copy(isLoading = false, tiraggiOggi = result.data)
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

    fun onOrarioChange(orario: LocalTime) {
        _uiState.update { it.copy(form = it.form.copy(orario = orario)) }
    }

    fun onLatoChange(lato: Side) {
        _uiState.update { it.copy(form = it.form.copy(lato = lato)) }
    }

    fun onQuantitaChange(quantitaMl: Int) {
        _uiState.update { it.copy(form = it.form.copy(quantitaMl = quantitaMl.coerceAtLeast(0))) }
    }

    fun onNoteChange(note: String) {
        _uiState.update { it.copy(form = it.form.copy(note = note)) }
    }

    fun onDataChange(data: LocalDate) {
        _uiState.update { it.copy(form = it.form.copy(data = data)) }
    }

    fun salvaTiraggio() {
        val form = _uiState.value.form
        val orario = form.orario.roundedToQuarterHour()
        val pumping = Pumping(
            id = 0,
            childId = DEFAULT_CHILD_ID,
            date = form.data.toString(),
            hour = orario.hour,
            minute = orario.minute,
            side = form.lato,
            quantityMl = form.quantitaMl,
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true) }
            when (pumpingRepository.createPumping(pumping)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSaving = false, form = TiraggioFormState()) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSaving = false) }
            }
        }
    }

    fun iniziaModificaTiraggio(tiraggio: Pumping) {
        _uiState.update {
            it.copy(
                editing = tiraggio,
                editForm = TiraggioFormState(
                    orario = LocalTime.of(tiraggio.hour, tiraggio.minute),
                    lato = tiraggio.side,
                    quantitaMl = tiraggio.quantityMl,
                    note = tiraggio.note ?: "",
                ),
            )
        }
    }

    fun annullaModifica() {
        _uiState.update { it.copy(editing = null) }
    }

    fun onModificaOrarioChange(orario: LocalTime) {
        _uiState.update { it.copy(editForm = it.editForm.copy(orario = orario)) }
    }

    fun onModificaLatoChange(lato: Side) {
        _uiState.update { it.copy(editForm = it.editForm.copy(lato = lato)) }
    }

    fun onModificaQuantitaChange(quantitaMl: Int) {
        _uiState.update { it.copy(editForm = it.editForm.copy(quantitaMl = quantitaMl.coerceAtLeast(0))) }
    }

    fun onModificaNoteChange(note: String) {
        _uiState.update { it.copy(editForm = it.editForm.copy(note = note)) }
    }

    fun salvaModificaTiraggio() {
        val state = _uiState.value
        val editing = state.editing ?: return
        val form = state.editForm
        val orario = form.orario.roundedToQuarterHour()
        val pumping = editing.copy(
            hour = orario.hour,
            minute = orario.minute,
            side = form.lato,
            quantityMl = form.quantitaMl,
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSavingEdit = true) }
            when (pumpingRepository.updatePumping(pumping)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSavingEdit = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSavingEdit = false) }
            }
        }
    }

    fun eliminaTiraggio() {
        val editing = _uiState.value.editing ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isDeleting = true) }
            when (pumpingRepository.deletePumping(editing.id)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isDeleting = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isDeleting = false) }
            }
        }
    }
}
