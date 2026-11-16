package xyz.deplot.chicco.ui.poppate

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
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.FeedingType
import xyz.deplot.chicco.data.model.Side
import xyz.deplot.chicco.data.repository.DEFAULT_CHILD_ID
import xyz.deplot.chicco.data.repository.FeedingRepository
import xyz.deplot.chicco.data.repository.TodaySummaryRepository
import xyz.deplot.chicco.util.roundedToQuarterHour

data class PoppataFormState(
    val tipo: FeedingType = FeedingType.FORMULA,
    val data: LocalDate = LocalDate.now(),
    val orario: LocalTime = LocalTime.now().roundedToQuarterHour(),
    val quantitaMl: Int = 60,
    val lato: Side = Side.LEFT,
    val durataMinuti: Int = 10,
    val note: String = "",
)

data class PoppataUiState(
    val isLoading: Boolean = true,
    val error: ChiccoErrorType? = null,
    val poppateOggi: List<Feeding> = emptyList(),
    val form: PoppataFormState = PoppataFormState(),
    val isSaving: Boolean = false,
    // Client-side estimate only — the authoritative ml_remaining_after comes
    // back from the POST /feedings response once wired to the real backend.
    val currentRemainingMl: Double? = null,
    val editing: Feeding? = null,
    val editForm: PoppataFormState = PoppataFormState(),
    val isSavingEdit: Boolean = false,
    val isDeleting: Boolean = false,
    val selectedDate: LocalDate = LocalDate.now(),
) {
    val mlRimastiStimati: Double?
        get() {
            val baseline = currentRemainingMl ?: return null
            val incremento = if (form.tipo != FeedingType.BREAST) form.quantitaMl else 0
            return (baseline - incremento).coerceAtLeast(0.0)
        }
}

@HiltViewModel
class PoppataViewModel @Inject constructor(
    private val feedingRepository: FeedingRepository,
    private val todaySummaryRepository: TodaySummaryRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(PoppataUiState())
    val uiState: StateFlow<PoppataUiState> = _uiState.asStateFlow()

    // Seeds the "new entry" form from the most recently recorded feeding exactly once, on the
    // ViewModel's first load — later loads (day navigation, post-save refresh) must not clobber
    // whatever the user has since typed or the sticky in-session defaults.
    private var hasSeededFormFromLastFeeding = false

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val summaryResult = todaySummaryRepository.getTodaySummary()) {
                is ChiccoResult.Success -> _uiState.update {
                    val latestFeeding = summaryResult.data.latestFeeding
                    val form = if (!hasSeededFormFromLastFeeding && latestFeeding != null) {
                        it.form.copy(
                            tipo = latestFeeding.type,
                            quantitaMl = latestFeeding.quantityMl ?: it.form.quantitaMl,
                            lato = latestFeeding.side ?: it.form.lato,
                            durataMinuti = latestFeeding.durationMinutes ?: it.form.durataMinuti,
                        )
                    } else {
                        it.form
                    }
                    hasSeededFormFromLastFeeding = true
                    it.copy(currentRemainingMl = summaryResult.data.currentRemainingMl, form = form)
                }
                is ChiccoResult.Error -> Unit
            }
            val date = _uiState.value.selectedDate
            when (val result = feedingRepository.getFeedingsByDate(date.toString())) {
                is ChiccoResult.Success -> _uiState.update {
                    it.copy(isLoading = false, poppateOggi = result.data)
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

    fun onTipoChange(tipo: FeedingType) {
        _uiState.update { it.copy(form = it.form.copy(tipo = tipo)) }
    }

    fun onOrarioChange(orario: LocalTime) {
        _uiState.update { it.copy(form = it.form.copy(orario = orario)) }
    }

    fun onQuantitaChange(quantitaMl: Int) {
        _uiState.update { it.copy(form = it.form.copy(quantitaMl = quantitaMl.coerceAtLeast(0))) }
    }

    fun onLatoChange(lato: Side) {
        _uiState.update { it.copy(form = it.form.copy(lato = lato)) }
    }

    fun onDurataChange(durataMinuti: Int) {
        _uiState.update { it.copy(form = it.form.copy(durataMinuti = durataMinuti.coerceAtLeast(0))) }
    }

    fun onNoteChange(note: String) {
        _uiState.update { it.copy(form = it.form.copy(note = note)) }
    }

    fun onDataChange(data: LocalDate) {
        _uiState.update { it.copy(form = it.form.copy(data = data)) }
    }

    fun salvaPoppata() {
        val form = _uiState.value.form
        val orario = form.orario.roundedToQuarterHour()
        val feeding = Feeding(
            id = 0,
            childId = DEFAULT_CHILD_ID,
            date = form.data.toString(),
            hour = orario.hour,
            minute = orario.minute,
            type = form.tipo,
            quantityMl = quantitaMlOrNull(form),
            side = latoOrNull(form),
            durationMinutes = durataOrNull(form),
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSaving = true) }
            when (feedingRepository.createFeeding(feeding)) {
                is ChiccoResult.Success -> {
                    // Keep type/quantity/side/duration as defaults for the next entry — only
                    // the date/time (refreshed to now) and note reset, since those are per-entry.
                    _uiState.update {
                        it.copy(
                            isSaving = false,
                            form = it.form.copy(
                                data = LocalDate.now(),
                                orario = LocalTime.now().roundedToQuarterHour(),
                                note = "",
                            ),
                        )
                    }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSaving = false) }
            }
        }
    }

    fun iniziaModificaPoppata(poppata: Feeding) {
        _uiState.update {
            it.copy(
                editing = poppata,
                editForm = PoppataFormState(
                    tipo = poppata.type,
                    orario = LocalTime.of(poppata.hour, poppata.minute),
                    quantitaMl = poppata.quantityMl ?: it.form.quantitaMl,
                    lato = poppata.side ?: it.form.lato,
                    durataMinuti = poppata.durationMinutes ?: it.form.durataMinuti,
                    note = poppata.note ?: "",
                ),
            )
        }
    }

    fun annullaModifica() {
        _uiState.update { it.copy(editing = null) }
    }

    fun onModificaTipoChange(tipo: FeedingType) {
        _uiState.update { it.copy(editForm = it.editForm.copy(tipo = tipo)) }
    }

    fun onModificaOrarioChange(orario: LocalTime) {
        _uiState.update { it.copy(editForm = it.editForm.copy(orario = orario)) }
    }

    fun onModificaQuantitaChange(quantitaMl: Int) {
        _uiState.update { it.copy(editForm = it.editForm.copy(quantitaMl = quantitaMl.coerceAtLeast(0))) }
    }

    fun onModificaLatoChange(lato: Side) {
        _uiState.update { it.copy(editForm = it.editForm.copy(lato = lato)) }
    }

    fun onModificaDurataChange(durataMinuti: Int) {
        _uiState.update { it.copy(editForm = it.editForm.copy(durataMinuti = durataMinuti.coerceAtLeast(0))) }
    }

    fun onModificaNoteChange(note: String) {
        _uiState.update { it.copy(editForm = it.editForm.copy(note = note)) }
    }

    fun salvaModificaPoppata() {
        val state = _uiState.value
        val editing = state.editing ?: return
        val form = state.editForm
        val orario = form.orario.roundedToQuarterHour()
        val feeding = editing.copy(
            hour = orario.hour,
            minute = orario.minute,
            type = form.tipo,
            quantityMl = quantitaMlOrNull(form),
            side = latoOrNull(form),
            durationMinutes = durataOrNull(form),
            note = form.note.ifBlank { null },
        )
        viewModelScope.launch {
            _uiState.update { it.copy(isSavingEdit = true) }
            when (feedingRepository.updateFeeding(feeding)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isSavingEdit = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isSavingEdit = false) }
            }
        }
    }

    fun eliminaPoppata() {
        val editing = _uiState.value.editing ?: return
        viewModelScope.launch {
            _uiState.update { it.copy(isDeleting = true) }
            when (feedingRepository.deleteFeeding(editing.id)) {
                is ChiccoResult.Success -> {
                    _uiState.update { it.copy(isDeleting = false, editing = null) }
                    load()
                }
                is ChiccoResult.Error -> _uiState.update { it.copy(isDeleting = false) }
            }
        }
    }

    private fun quantitaMlOrNull(form: PoppataFormState): Int? =
        if (form.tipo != FeedingType.BREAST) form.quantitaMl else null

    private fun latoOrNull(form: PoppataFormState): Side? =
        if (form.tipo == FeedingType.BREAST) form.lato else null

    private fun durataOrNull(form: PoppataFormState): Int? =
        if (form.tipo == FeedingType.BREAST) form.durataMinuti else null
}
