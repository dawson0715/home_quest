package xyz.deplot.chicco.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
enum class ChecklistList {
    @SerialName("bag") BAG,
    @SerialName("shopping") SHOPPING,
    @SerialName("todo") TODO,
}

@Serializable
data class ChecklistItem(
    val id: Int,
    val list: ChecklistList,
    val text: String,
    @SerialName("is_checked") val isChecked: Boolean = false,
    val position: Int = 0,
)

/** Matches the backend's `list` column values / @SerialName mapping — used to build query params. */
fun ChecklistList.apiValue(): String = when (this) {
    ChecklistList.BAG -> "bag"
    ChecklistList.SHOPPING -> "shopping"
    ChecklistList.TODO -> "todo"
}
