package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
enum class FeedingType {
    @SerialName("formula") FORMULA,
    @SerialName("breast_milk") BREAST_MILK,
    @SerialName("breast") BREAST,
}

@Serializable
data class Feeding(
    val id: Int,
    @SerialName("child_id") val childId: Int,
    val date: String,
    val hour: Int,
    val minute: Int,
    val type: FeedingType,
    @SerialName("quantity_ml") val quantityMl: Int? = null,
    val side: Side? = null,
    @SerialName("duration_minutes") val durationMinutes: Int? = null,
    // Server-computed only, never client-writable — null for breast feedings.
    @SerialName("ml_remaining_after") val mlRemainingAfter: Int? = null,
    val note: String? = null,
)
