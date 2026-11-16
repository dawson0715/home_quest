package xyz.deplot.chicco.util

import java.time.Duration
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import java.util.Locale

/** The four selectable quarter-hour minute marks used across all time pickers in the app. */
val QUARTER_HOUR_MINUTES = listOf(0, 15, 30, 45)

fun LocalTime.roundedToQuarterHour(): LocalTime {
    val totalMinutes = hour * 60 + minute
    val rounded = ((totalMinutes + 7) / 15 * 15) % (24 * 60)
    return LocalTime.of(rounded / 60, rounded % 60)
}

fun LocalTime.toQuarterHourString(): String {
    val rounded = roundedToQuarterHour()
    return "%02d:%02d".format(rounded.hour, rounded.minute)
}

/** hour/minute as stored by the backend (separate int columns, not a combined time string). */
fun formatHourMinute(hour: Int, minute: Int): String = "%02d:%02d".format(hour, minute)

/** Italian relative-time label ("2h 15m fa" / "45m fa" / "1g 3h fa") for an event's date+hour+minute. */
fun elapsedLabel(date: String, hour: Int, minute: Int, now: LocalDateTime = LocalDateTime.now()): String {
    val eventTime = LocalDate.parse(date).atTime(hour, minute)
    val duration = Duration.between(eventTime, now).let { if (it.isNegative) Duration.ZERO else it }
    val totalMinutes = duration.toMinutes()
    val days = totalMinutes / (24 * 60)
    val hours = (totalMinutes % (24 * 60)) / 60
    val minutes = totalMinutes % 60
    return when {
        days > 0 -> "${days}g ${hours}h fa"
        hours > 0 -> "${hours}h ${minutes}m fa"
        else -> "${minutes}m fa"
    }
}

/** Day-granularity Italian relative-date label ("Oggi" / "Ieri" / "3g fa") for entries with no time-of-day. */
fun elapsedDaysLabel(date: String, today: LocalDate = LocalDate.now()): String {
    val days = ChronoUnit.DAYS.between(LocalDate.parse(date), today)
    return when {
        days <= 0 -> "Oggi"
        days == 1L -> "Ieri"
        else -> "${days}g fa"
    }
}

private val dayLabelFormatter = DateTimeFormatter.ofPattern("d MMMM", Locale.ITALIAN)

/** Label for a day being browsed in a date-navigable list: "Oggi" / "Ieri" / "27 luglio". */
fun dayLabel(date: LocalDate, today: LocalDate = LocalDate.now()): String = when (date) {
    today -> "Oggi"
    today.minusDays(1) -> "Ieri"
    else -> date.format(dayLabelFormatter)
}
