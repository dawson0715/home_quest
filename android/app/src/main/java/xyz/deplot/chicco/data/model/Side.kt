package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
enum class Side {
    @SerialName("left") LEFT,
    @SerialName("right") RIGHT,
    @SerialName("both") BOTH,
}
