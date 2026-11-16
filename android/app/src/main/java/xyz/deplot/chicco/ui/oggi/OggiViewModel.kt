package xyz.deplot.chicco.ui.oggi

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import java.time.LocalDate
import java.time.temporal.ChronoUnit
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.data.repository.DiaperRepository
import xyz.deplot.chicco.data.repository.FeedingRepository
import xyz.deplot.chicco.data.repository.PumpingRepository
import xyz.deplot.chicco.data.repository.TodaySummaryRepository
import xyz.deplot.chicco.data.repository.WeightRepository
import xyz.deplot.chicco.util.elapsedLabel

data class DayTrend(
    val date: LocalDate,
    val neededMl: Double?,
    val consumedMl: Int,
)

data class OggiUiState(
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    val error: ChiccoErrorType? = null,
    val summary: TodaySummary? = null,
    val feedings: List<Feeding> = emptyList(),
    val pumpings: List<Pumping> = emptyList(),
    val diapers: List<Diaper> = emptyList(),
    val dayWeight: Weight? = null,
    val weightDeltaGrams: Int? = null,
    val weightDeltaDays: Long? = null,
    val ultimaPoppataFa: String? = null,
    val ultimoPannolinoFa: String? = null,
    val selectedDate: LocalDate = LocalDate.now(),
    val showTrend: Boolean = false,
    val isTrendLoading: Boolean = false,
    val trend: List<DayTrend> = emptyList(),
) {
    val feedingCount: Int get() = feedings.size
    val diaperCount: Int get() = diapers.size
}

@HiltViewModel
class OggiViewModel @Inject constructor(
    private val todaySummaryRepository: TodaySummaryRepository,
    private val feedingRepository: FeedingRepository,
    private val pumpingRepository: PumpingRepository,
    private val diaperRepository: DiaperRepository,
    private val weightRepository: WeightRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(OggiUiState())
    val uiState: StateFlow<OggiUiState> = _uiState.asStateFlow()

    fun load() = fetch(isRefresh = false)

    fun refresh() = fetch(isRefresh = true)

    private fun fetch(isRefresh: Boolean) {
        viewModelScope.launch {
            _uiState.update {
                if (isRefresh) it.copy(isRefreshing = true, error = null) else it.copy(isLoading = true, error = null)
            }
            val date = _uiState.value.selectedDate
            val isToday = date == LocalDate.now()
            val summary = when (val result = todaySummaryRepository.getTodaySummary(date = date.toString())) {
                is ChiccoResult.Success -> result.data
                is ChiccoResult.Error -> {
                    _uiState.update {
                        if (isRefresh) it.copy(isRefreshing = false, error = result.type) else it.copy(isLoading = false, error = result.type)
                    }
                    return@launch
                }
            }
            _uiState.update { it.copy(summary = summary) }
            val feedings = (feedingRepository.getFeedingsByDate(date.toString()) as? ChiccoResult.Success)?.data.orEmpty()
            val pumpings = (pumpingRepository.getPumpingsByDate(date.toString()) as? ChiccoResult.Success)?.data.orEmpty()
            val diapers = (diaperRepository.getDiapersByDate(date.toString()) as? ChiccoResult.Success)?.data.orEmpty()
            // "Peso del giorno" is the latest weight known as of the selected date — recorded
            // that day if there is one, otherwise carried over from the last weigh-in before it
            // (weigh-ins aren't necessarily daily) — same fallback as the backend's daily-need
            // calculation, not literally "empty unless weighed on this exact day".
            val history = (weightRepository.getHistory() as? ChiccoResult.Success)?.data.orEmpty()
            val weightsUpToDay = history.filter { LocalDate.parse(it.date) <= date }.sortedByDescending { it.date }
            val dayWeight = weightsUpToDay.firstOrNull()
            val previousWeight = weightsUpToDay.drop(1).firstOrNull()
            val weightDeltaGrams = if (dayWeight != null && previousWeight != null) {
                dayWeight.weightGrams - previousWeight.weightGrams
            } else {
                null
            }
            val weightDeltaDays = if (dayWeight != null && previousWeight != null) {
                ChronoUnit.DAYS.between(LocalDate.parse(previousWeight.date), LocalDate.parse(dayWeight.date))
            } else {
                null
            }
            // Elapsed-time sub-labels only make sense relative to now, so only compute/show
            // them when browsing today. When today has no feedings yet, fall back to
            // summary.latestFeeding (the globally-latest one, e.g. yesterday's last) so the
            // elapsed count doesn't just disappear before the first feeding of the day.
            val ultimaPoppataOggi = if (isToday) {
                feedings.maxByOrNull { it.hour * 60 + it.minute } ?: summary.latestFeeding
            } else {
                null
            }
            val ultimoPannolinoOggi = if (isToday) diapers.maxByOrNull { it.hour * 60 + it.minute } else null
            _uiState.update {
                it.copy(
                    isLoading = false,
                    isRefreshing = false,
                    feedings = feedings,
                    pumpings = pumpings,
                    diapers = diapers,
                    dayWeight = dayWeight,
                    weightDeltaGrams = weightDeltaGrams,
                    weightDeltaDays = weightDeltaDays,
                    ultimaPoppataFa = ultimaPoppataOggi?.let { feeding -> elapsedLabel(feeding.date, feeding.hour, feeding.minute) },
                    ultimoPannolinoFa = ultimoPannolinoOggi?.let { diaper -> elapsedLabel(diaper.date, diaper.hour, diaper.minute) },
                )
            }
            if (_uiState.value.showTrend) loadTrend()
        }
    }

    /** Toggles the gauge card between the single-day view and the 7-day need-vs-consumed trend. */
    fun toggleFabbisognoTrend() {
        val turningOn = !_uiState.value.showTrend
        _uiState.update { it.copy(showTrend = turningOn) }
        if (turningOn) loadTrend()
    }

    private fun loadTrend() {
        viewModelScope.launch {
            _uiState.update { it.copy(isTrendLoading = true) }
            val endDate = _uiState.value.selectedDate
            val days = (6 downTo 0).map { endDate.minusDays(it.toLong()) }
            // Daily need depends on the weight recorded on (or last before) each day, so it's
            // re-fetched per day rather than reused from the currently-viewed day's summary.
            val trend = days.map { day ->
                val neededMl = (todaySummaryRepository.getTodaySummary(date = day.toString()) as? ChiccoResult.Success)?.data?.dailyNeedMl
                val consumedMl = (feedingRepository.getFeedingsByDate(day.toString()) as? ChiccoResult.Success)?.data
                    .orEmpty()
                    .sumOf { it.quantityMl ?: 0 }
                DayTrend(date = day, neededMl = neededMl, consumedMl = consumedMl)
            }
            _uiState.update { it.copy(isTrendLoading = false, trend = trend) }
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
}
