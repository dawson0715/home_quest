package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class Child(
    val id: Int,
    val name: String,
    @SerialName("birth_date") val birthDate: String,
    @SerialName("ml_per_kg_day") val mlPerKgDay: Double,
)
