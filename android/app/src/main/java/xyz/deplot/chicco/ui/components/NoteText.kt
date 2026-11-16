package xyz.deplot.chicco.ui.components

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable

/** Second-line note rendered small and gray under a list row's main info line. */
@Composable
fun NoteText(note: String?) {
    if (!note.isNullOrBlank()) {
        Text(
            text = note,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }
}
