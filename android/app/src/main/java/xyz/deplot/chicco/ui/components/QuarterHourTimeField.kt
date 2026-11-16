package xyz.deplot.chicco.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronLeft
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import java.time.LocalTime
import xyz.deplot.chicco.util.QUARTER_HOUR_MINUTES

/**
 * Time selector constrained to the four quarter-hour marks (0/15/30/45) —
 * deliberately not a generic time picker, per the product requirement that
 * poppate/tiraggio/pannolini times can't land on arbitrary minutes.
 */
@Composable
fun QuarterHourTimeField(
    label: String,
    time: LocalTime,
    onTimeChange: (LocalTime) -> Unit,
    modifier: Modifier = Modifier,
) {
    var showDialog by remember { mutableStateOf(false) }

    OutlinedButton(onClick = { showDialog = true }, modifier = modifier) {
        Icon(Icons.Filled.Schedule, contentDescription = null, modifier = Modifier.padding(end = 8.dp))
        Text("$label: %02d:%02d".format(time.hour, time.minute))
    }

    if (showDialog) {
        var pendingHour by remember { mutableIntStateOf(time.hour) }
        var pendingMinute by remember { mutableIntStateOf(time.minute) }

        AlertDialog(
            onDismissRequest = { showDialog = false },
            title = { Text(label) },
            text = {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        IconButton(onClick = { pendingHour = (pendingHour + 23) % 24 }) {
                            Icon(Icons.Filled.ChevronLeft, contentDescription = "Ora precedente")
                        }
                        Text(
                            text = "%02d".format(pendingHour),
                            style = MaterialTheme.typography.headlineMedium,
                            modifier = Modifier.padding(horizontal = 16.dp),
                        )
                        IconButton(onClick = { pendingHour = (pendingHour + 1) % 24 }) {
                            Icon(Icons.Filled.ChevronRight, contentDescription = "Ora successiva")
                        }
                    }
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 16.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
                    ) {
                        QUARTER_HOUR_MINUTES.forEach { minute ->
                            FilterChip(
                                selected = pendingMinute == minute,
                                onClick = { pendingMinute = minute },
                                label = { Text("%02d".format(minute)) },
                            )
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    onTimeChange(LocalTime.of(pendingHour, pendingMinute))
                    showDialog = false
                }) { Text("Conferma") }
            },
            dismissButton = {
                TextButton(onClick = { showDialog = false }) { Text("Annulla") }
            },
        )
    }
}
