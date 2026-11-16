package xyz.deplot.chicco.ui.oggi

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BabyChangingStation
import androidx.compose.material.icons.filled.MonitorWeight
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.WaterDrop
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import java.time.format.DateTimeFormatter
import java.util.Locale
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.FeedingType
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.data.model.label
import xyz.deplot.chicco.ui.components.DateNavigator
import xyz.deplot.chicco.ui.components.EntryRow
import xyz.deplot.chicco.ui.components.NetworkAwareContent
import xyz.deplot.chicco.ui.components.NoteText
import xyz.deplot.chicco.ui.components.StatCard
import xyz.deplot.chicco.ui.navigation.LocalTopBarContent
import xyz.deplot.chicco.ui.theme.customColors
import xyz.deplot.chicco.util.formatHourMinute

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OggiScreen(viewModel: OggiViewModel = hiltViewModel()) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(Unit) { viewModel.load() }

    val topBarContent = LocalTopBarContent.current
    val myTopBarContent: @Composable () -> Unit = {
        val state by viewModel.uiState.collectAsState()
        DateNavigator(
            date = state.selectedDate,
            onPreviousDay = viewModel::giornoPrecedente,
            onNextDay = viewModel::giornoSuccessivo,
        )
    }
    DisposableEffect(Unit) {
        topBarContent.value = myTopBarContent
        // NavHost animates between destinations, so the outgoing screen's onDispose can fire
        // after the incoming one already set its own content — only clear if we're still it.
        onDispose {
            if (topBarContent.value === myTopBarContent) {
                topBarContent.value = null
            }
        }
    }

    NetworkAwareContent(
        isLoading = uiState.isLoading,
        error = uiState.error,
        onRetry = viewModel::load,
        modifier = Modifier.fillMaxSize(),
    ) {
        val summary = uiState.summary ?: return@NetworkAwareContent
        PullToRefreshBox(
            isRefreshing = uiState.isRefreshing,
            onRefresh = viewModel::refresh,
            modifier = Modifier.fillMaxSize(),
        ) {
            OggiContent(
                summary = summary,
                feedings = uiState.feedings,
                pumpings = uiState.pumpings,
                diapers = uiState.diapers,
                dayWeight = uiState.dayWeight,
                weightDeltaGrams = uiState.weightDeltaGrams,
                weightDeltaDays = uiState.weightDeltaDays,
                feedingCount = uiState.feedingCount,
                diaperCount = uiState.diaperCount,
                ultimaPoppataFa = uiState.ultimaPoppataFa,
                ultimoPannolinoFa = uiState.ultimoPannolinoFa,
                showTrend = uiState.showTrend,
                isTrendLoading = uiState.isTrendLoading,
                trend = uiState.trend,
                onToggleTrend = viewModel::toggleFabbisognoTrend,
            )
        }
    }
}

@Composable
private fun OggiContent(
    summary: TodaySummary,
    feedings: List<Feeding>,
    pumpings: List<Pumping>,
    diapers: List<Diaper>,
    dayWeight: Weight?,
    weightDeltaGrams: Int?,
    weightDeltaDays: Long?,
    feedingCount: Int,
    diaperCount: Int,
    ultimaPoppataFa: String?,
    ultimoPannolinoFa: String?,
    showTrend: Boolean,
    isTrendLoading: Boolean,
    trend: List<DayTrend>,
    onToggleTrend: () -> Unit,
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        item {
            GaugeHeroCard(
                summary = summary,
                feedings = feedings,
                showTrend = showTrend,
                isTrendLoading = isTrendLoading,
                trend = trend,
                onToggle = onToggleTrend,
            )
        }
        item {
            QuickStatsRow(
                dayWeight = dayWeight,
                weightDeltaGrams = weightDeltaGrams,
                weightDeltaDays = weightDeltaDays,
                feedingCount = feedingCount,
                diaperCount = diaperCount,
                ultimaPoppataFa = ultimaPoppataFa,
                ultimoPannolinoFa = ultimoPannolinoFa,
            )
        }
        item {
            Text(
                text = "Cronologia",
                style = MaterialTheme.typography.titleMedium,
                modifier = Modifier.padding(top = 8.dp),
            )
        }
        val timeline = buildTimeline(feedings, pumpings, diapers)
        if (timeline.isEmpty()) {
            item {
                Text(
                    text = "Nessun evento registrato in questo giorno.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        } else {
            item {
                Column {
                    timeline.forEachIndexed { index, event ->
                        TimelineRow(event)
                        if (index != timeline.lastIndex) HorizontalDivider()
                    }
                }
            }
        }
    }
}

@Composable
private fun GaugeHeroCard(
    summary: TodaySummary,
    feedings: List<Feeding>,
    showTrend: Boolean,
    isTrendLoading: Boolean,
    trend: List<DayTrend>,
    onToggle: () -> Unit,
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onToggle),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
            contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        ),
    ) {
        if (showTrend) {
            TrendView(isTrendLoading, trend)
            return@Card
        }
        if (summary.dailyNeedMl == null || summary.currentRemainingMl == null) {
            Column(modifier = Modifier.padding(24.dp)) {
                Text(text = "Fabbisogno giornaliero", style = MaterialTheme.typography.labelMedium)
                Text(
                    text = summary.dailyNeedError ?: "Fabbisogno non disponibile.",
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(top = 8.dp),
                )
            }
            return@Card
        }
        Row(
            modifier = Modifier.padding(24.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            // Real total consumed, not capped at dailyNeedMl the way
            // dailyNeedMl - currentRemainingMl would be (currentRemainingMl floors at 0
            // server-side), so going over the daily need still shows the true total.
            val consumato = feedings
                .filter { it.type == FeedingType.FORMULA || it.type == FeedingType.BREAST_MILK }
                .sumOf { it.quantityMl ?: 0 }
            val progress = if (summary.dailyNeedMl > 0) {
                (consumato / summary.dailyNeedMl).toFloat().coerceIn(0f, 1f)
            } else {
                0f
            }
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(120.dp)) {
                val trackColor = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.15f)
                val progressColor = MaterialTheme.colorScheme.primary
                Canvas(modifier = Modifier.fillMaxSize()) {
                    val stroke = Stroke(width = 14.dp.toPx(), cap = StrokeCap.Round)
                    drawArc(
                        color = trackColor,
                        startAngle = -90f,
                        sweepAngle = 360f,
                        useCenter = false,
                        style = stroke,
                        size = Size(size.width - stroke.width, size.height - stroke.width),
                        topLeft = Offset(stroke.width / 2, stroke.width / 2),
                    )
                    drawArc(
                        color = progressColor,
                        startAngle = -90f,
                        sweepAngle = 360f * progress,
                        useCenter = false,
                        style = stroke,
                        size = Size(size.width - stroke.width, size.height - stroke.width),
                        topLeft = Offset(stroke.width / 2, stroke.width / 2),
                    )
                }
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(text = "${summary.currentRemainingMl.toInt()}", style = MaterialTheme.typography.headlineMedium)
                    Text(text = "g rimasti", style = MaterialTheme.typography.labelSmall)
                }
            }
            Column(modifier = Modifier.padding(start = 24.dp)) {
                Text(text = "Fabbisogno giornaliero", style = MaterialTheme.typography.labelMedium)
                Text(
                    text = "$consumato / ${summary.dailyNeedMl.toInt()} g",
                    style = MaterialTheme.typography.titleLarge,
                )
            }
        }
    }
}

private val trendDayFormatter = DateTimeFormatter.ofPattern("EEE d", Locale.ITALIAN)

@Composable
private fun TrendView(isLoading: Boolean, trend: List<DayTrend>) {
    Column(modifier = Modifier.padding(24.dp)) {
        Text(text = "Andamento ultimi 7 giorni", style = MaterialTheme.typography.labelMedium)
        Spacer(modifier = Modifier.height(12.dp))
        if (isLoading) {
            Box(modifier = Modifier.fillMaxWidth().padding(vertical = 16.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = MaterialTheme.colorScheme.onPrimaryContainer)
            }
            return
        }
        trend.forEach { day ->
            val progress = if (day.neededMl != null && day.neededMl > 0) {
                (day.consumedMl / day.neededMl).toFloat().coerceIn(0f, 1f)
            } else {
                0f
            }
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 4.dp)) {
                Text(
                    text = trendDayFormatter.format(day.date),
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.width(56.dp),
                )
                LinearProgressIndicator(
                    progress = { progress },
                    color = MaterialTheme.colorScheme.primary,
                    trackColor = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.15f),
                    modifier = Modifier
                        .weight(1f)
                        .height(8.dp)
                        .clip(RoundedCornerShape(4.dp)),
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = "${day.consumedMl}/${day.neededMl?.toInt() ?: "—"} g",
                    style = MaterialTheme.typography.bodySmall,
                    modifier = Modifier.widthIn(min = 72.dp),
                )
            }
        }
    }
}

@Composable
private fun QuickStatsRow(
    dayWeight: Weight?,
    weightDeltaGrams: Int?,
    weightDeltaDays: Long?,
    feedingCount: Int,
    diaperCount: Int,
    ultimaPoppataFa: String?,
    ultimoPannolinoFa: String?,
) {
    LazyRow(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            StatCard(
                icon = Icons.Filled.Restaurant,
                label = "Poppate",
                value = "$feedingCount",
                subValue = ultimaPoppataFa,
                containerColor = MaterialTheme.colorScheme.tertiaryContainer,
                contentColor = MaterialTheme.colorScheme.onTertiaryContainer,
            )
        }
        item {
            StatCard(
                icon = Icons.Filled.BabyChangingStation,
                label = "Pannolini",
                value = "$diaperCount",
                subValue = ultimoPannolinoFa,
                containerColor = MaterialTheme.customColors.warmContainer,
                contentColor = MaterialTheme.customColors.onWarmContainer,
            )
        }
        item {
            val pesoSubValue = when {
                dayWeight == null -> null
                weightDeltaGrams == null -> "Prima pesata registrata"
                else -> {
                    val sign = if (weightDeltaGrams > 0) "+" else ""
                    "$sign$weightDeltaGrams g · $weightDeltaDays gg"
                }
            }
            StatCard(
                icon = Icons.Filled.MonitorWeight,
                label = "Peso del giorno",
                value = dayWeight?.let { "${it.weightGrams} g" } ?: "—",
                subValue = pesoSubValue,
                containerColor = MaterialTheme.colorScheme.secondaryContainer,
                contentColor = MaterialTheme.colorScheme.onSecondaryContainer,
            )
        }
    }
}

private enum class EventKind { FEEDING, PUMPING, DIAPER }

private data class TimelineEvent(
    val hour: Int,
    val minute: Int,
    val icon: ImageVector,
    val headline: String,
    // Null for diapers, which (like PannolinoRow) only show the time, no quantity line.
    val quantity: String?,
    val note: String?,
    val kind: EventKind,
)

private fun buildTimeline(
    feedings: List<Feeding>,
    pumpings: List<Pumping>,
    diapers: List<Diaper>,
): List<TimelineEvent> {
    val feedingEvents = feedings.map {
        TimelineEvent(it.hour, it.minute, Icons.Filled.Restaurant, it.type.label(), it.quantity(), it.note, EventKind.FEEDING)
    }
    val pumpingEvents = pumpings.map {
        TimelineEvent(it.hour, it.minute, Icons.Filled.WaterDrop, "Tiraggio ${it.side.label()}", "${it.quantityMl} g", it.note, EventKind.PUMPING)
    }
    val diaperEvents = diapers.map {
        TimelineEvent(it.hour, it.minute, Icons.Filled.BabyChangingStation, it.type.label(), null, it.note, EventKind.DIAPER)
    }
    return (feedingEvents + pumpingEvents + diaperEvents).sortedByDescending { it.hour * 60 + it.minute }
}

private fun Feeding.quantity(): String = when (type) {
    FeedingType.BREAST -> "${side?.label() ?: ""} · ${durationMinutes ?: 0} min"
    else -> "${quantityMl ?: 0} g"
}

@Composable
private fun TimelineRow(event: TimelineEvent) {
    val (containerColor, contentColor) = when (event.kind) {
        EventKind.FEEDING -> MaterialTheme.colorScheme.primaryContainer to MaterialTheme.colorScheme.onPrimaryContainer
        EventKind.PUMPING -> MaterialTheme.colorScheme.tertiaryContainer to MaterialTheme.colorScheme.onTertiaryContainer
        EventKind.DIAPER -> MaterialTheme.customColors.warmContainer to MaterialTheme.customColors.onWarmContainer
    }
    EntryRow(
        icon = event.icon,
        iconContainerColor = containerColor,
        iconContentColor = contentColor,
        headline = event.headline,
        trailingContent = {
            if (event.quantity != null) {
                Text(event.quantity, style = MaterialTheme.typography.titleMedium)
                Text(
                    formatHourMinute(event.hour, event.minute),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            } else {
                Text(
                    formatHourMinute(event.hour, event.minute),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        },
        supportingContent = { NoteText(event.note) },
    )
}
