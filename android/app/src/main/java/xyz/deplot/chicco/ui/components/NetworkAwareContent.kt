package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import xyz.deplot.chicco.data.ChiccoErrorType

/**
 * Standard loading / error(+retry) / content switch so every screen shows a
 * recognizable state instead of a blank screen or crash on network failure.
 */
@Composable
fun NetworkAwareContent(
    isLoading: Boolean,
    error: ChiccoErrorType?,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable () -> Unit,
) {
    when {
        isLoading -> Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator()
        }
        error != null -> ErrorState(error = error, onRetry = onRetry, modifier = modifier)
        else -> content()
    }
}

@Composable
fun ErrorState(
    error: ChiccoErrorType,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val (icon, message) = when (error) {
        ChiccoErrorType.NO_CONNECTION -> Icons.Filled.CloudOff to "Nessuna connessione. Controlla la rete e riprova."
        ChiccoErrorType.TIMEOUT -> Icons.Filled.HourglassEmpty to "Il server non risponde. Riprova tra poco."
        ChiccoErrorType.UNAUTHORIZED -> Icons.Filled.Lock to "Sessione scaduta. Accedi di nuovo."
        ChiccoErrorType.SERVER -> Icons.Filled.ErrorOutline to "Il server ha restituito un errore. Riprova."
        ChiccoErrorType.UNKNOWN -> Icons.Filled.ErrorOutline to "Qualcosa è andato storto. Riprova."
    }
    Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier.padding(24.dp),
        ) {
            Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.error)
            Text(text = message, style = MaterialTheme.typography.bodyMedium, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
            Button(onClick = onRetry) { Text("Riprova") }
        }
    }
}
