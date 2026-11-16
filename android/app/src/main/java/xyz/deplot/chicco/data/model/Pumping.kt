package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Pumping(
    val id: Int,
    @SerialName("child_id") val childId: Int,
    val date: String,
    val hour: Int,
    val minute: Int,
    val side: Side,
    @SerialName("quantity_ml") val quantityMl: Int,
    val note: String? = null,
)
