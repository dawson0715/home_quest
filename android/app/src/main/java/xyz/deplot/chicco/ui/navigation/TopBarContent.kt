package xyz.deplot.chicco.ui.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.MutableState
import androidx.compose.runtime.compositionLocalOf

/**
 * Lets a screen replace the shared top app bar's title slot with custom content (e.g. a date
 * navigator) — set from a `DisposableEffect` so it's cleared again when the screen is left,
 * restoring the default destination-label title. Provided by [ChiccoMainScaffold].
 */
val LocalTopBarContent = compositionLocalOf<MutableState<(@Composable () -> Unit)?>> {
    error("LocalTopBarContent not provided — must be read within ChiccoMainScaffold's NavHost")
}
