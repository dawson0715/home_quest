package xyz.deplot.chicco.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BabyChangingStation
import androidx.compose.material.icons.filled.Checklist
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.MonitorWeight
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.WaterDrop
import androidx.compose.ui.graphics.vector.ImageVector

enum class ChiccoDestination(val route: String, val label: String, val icon: ImageVector) {
    OGGI(route = "oggi", label = "Home", icon = Icons.Filled.Home),
    POPPATE(route = "poppate", label = "Poppate", icon = Icons.Filled.Restaurant),
    TIRAGGIO(route = "tiraggio", label = "Tiraggio", icon = Icons.Filled.WaterDrop),
    PESO(route = "peso", label = "Peso", icon = Icons.Filled.MonitorWeight),
    PANNOLINI(route = "pannolini", label = "Pannolini", icon = Icons.Filled.BabyChangingStation),
    LISTE(route = "liste", label = "Liste", icon = Icons.Filled.Checklist),
}
