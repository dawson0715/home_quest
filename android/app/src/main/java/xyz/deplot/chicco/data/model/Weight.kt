package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Weight(
    val id: Int,
    @SerialName("child_id") val childId: Int,
    val date: String,
    @SerialName("weight_grams") val weightGrams: Int,
    val note: String? = null,
)
