package xyz.deplot.chicco.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

/**
 * Custom color role not covered by the Material 3 baseline scheme, following
 * the same {role, onRole, roleContainer, onRoleContainer} shape. Used for the
 * "warm" (orange) accent on Pannolini.
 */
data class CustomColors(
    val warm: Color = Color.Unspecified,
    val onWarm: Color = Color.Unspecified,
    val warmContainer: Color = Color.Unspecified,
    val onWarmContainer: Color = Color.Unspecified,
)

val LocalCustomColors = staticCompositionLocalOf { CustomColors() }

val MaterialTheme.customColors: CustomColors
    @Composable
    get() = LocalCustomColors.current
