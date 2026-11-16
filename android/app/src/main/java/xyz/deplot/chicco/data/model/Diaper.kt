package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
enum class DiaperType {
    @SerialName("pee") PEE,
    @SerialName("poop") POOP,
    @SerialName("mixed") MIXED,
}

@Serializable
data class Diaper(
    val id: Int,
    @SerialName("child_id") val childId: Int,
    val date: String,
    val hour: Int,
    val minute: Int,
    val type: DiaperType,
    val note: String? = null,
)
