package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import java.time.LocalDate
import xyz.deplot.chicco.util.dayLabel

/** Previous/next day navigation header for lists that browse one day at a time. */
@Composable
fun DateNavigator(
    date: LocalDate,
    onPreviousDay: () -> Unit,
    onNextDay: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        IconButton(onClick = onPreviousDay) {
            Icon(Icons.Filled.ChevronLeft, contentDescription = "Giorno precedente")
        }
        Text(text = dayLabel(date), style = MaterialTheme.typography.titleMedium)
        IconButton(onClick = onNextDay, enabled = date < LocalDate.now()) {
            Icon(Icons.Filled.ChevronRight, contentDescription = "Giorno successivo")
        }
    }
}
