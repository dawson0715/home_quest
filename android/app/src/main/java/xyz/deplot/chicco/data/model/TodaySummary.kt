package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LatestWeight(
    val date: String,
    @SerialName("weight_grams") val weightGrams: Int,
)

@Serializable
data class TodaySummary(
    @SerialName("child_id") val childId: Int,
    val date: String,
    @SerialName("daily_need_ml") val dailyNeedMl: Double? = null,
    @SerialName("current_remaining_ml") val currentRemainingMl: Double? = null,
    // Set instead of daily_need_ml/current_remaining_ml when no weight has
    // been recorded yet for the child — surface it, don't crash or hide it.
    @SerialName("daily_need_error") val dailyNeedError: String? = null,
    @SerialName("pumping_stock_ml") val pumpingStockMl: Double,
    @SerialName("latest_weight") val latestWeight: LatestWeight? = null,
    @SerialName("latest_feeding") val latestFeeding: Feeding? = null,
    @SerialName("diaper_count_today") val diaperCountToday: Int,
)
