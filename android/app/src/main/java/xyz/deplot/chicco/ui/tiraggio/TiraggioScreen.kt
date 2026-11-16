package xyz.deplot.chicco.ui.tiraggio

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.WaterDrop
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import java.time.LocalDate
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.Side
import xyz.deplot.chicco.data.model.label
import xyz.deplot.chicco.ui.components.DateField
import xyz.deplot.chicco.ui.components.DateNavigator
import xyz.deplot.chicco.ui.components.EntryRow
import xyz.deplot.chicco.ui.components.NetworkAwareContent
import xyz.deplot.chicco.ui.components.NoteText
import xyz.deplot.chicco.ui.components.NumberStepperField
import xyz.deplot.chicco.ui.components.QuarterHourTimeField
import xyz.deplot.chicco.ui.navigation.LocalTopBarContent
import xyz.deplot.chicco.util.formatHourMinute

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TiraggioScreen(viewModel: TiraggioViewModel = hiltViewModel()) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(Unit) { viewModel.load() }

    val topBarContent = LocalTopBarContent.current
    val myTopBarContent: @Composable () -> Unit = {
        val state by viewModel.uiState.collectAsState()
        DateNavigator(
            date = state.selectedDate,
            onPreviousDay = viewModel::giornoPrecedente,
            onNextDay = viewModel::giornoSuccessivo,
        )
    }
    DisposableEffect(Unit) {
        topBarContent.value = myTopBarContent
        // NavHost animates between destinations, so the outgoing screen's onDispose can fire
        // after the incoming one already set its own content — only clear if we're still it.
        onDispose {
            if (topBarContent.value === myTopBarContent) {
                topBarContent.value = null
            }
        }
    }

    NetworkAwareContent(
        isLoading = uiState.isLoading,
        error = uiState.error,
        onRetry = viewModel::load,
        modifier = Modifier.fillMaxSize(),
    ) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            item { ScortaHeroCard(uiState.pumpingStockMl) }
            item {
                TiraggioForm(
                    state = uiState,
                    onDataChange = viewModel::onDataChange,
                    onOrarioChange = viewModel::onOrarioChange,
                    onLatoChange = viewModel::onLatoChange,
                    onQuantitaChange = viewModel::onQuantitaChange,
                    onNoteChange = viewModel::onNoteChange,
                    onSalva = viewModel::salvaTiraggio,
                )
            }
            if (uiState.tiraggiOggi.isEmpty()) {
                item {
                    Text(
                        text = "Nessun tiraggio registrato in questo giorno.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else {
                item {
                    val tiraggi = uiState.tiraggiOggi.sortedByDescending { it.hour * 60 + it.minute }
                    Column {
                        tiraggi.forEachIndexed { index, tiraggio ->
                            TiraggioRow(tiraggio, onClick = { viewModel.iniziaModificaTiraggio(tiraggio) })
                            if (index != tiraggi.lastIndex) HorizontalDivider()
                        }
                    }
                }
            }
        }
    }

    if (uiState.editing != null) {
        TiraggioEditSheet(
            state = uiState,
            onDismiss = viewModel::annullaModifica,
            onOrarioChange = viewModel::onModificaOrarioChange,
            onLatoChange = viewModel::onModificaLatoChange,
            onQuantitaChange = viewModel::onModificaQuantitaChange,
            onNoteChange = viewModel::onModificaNoteChange,
            onSalva = viewModel::salvaModificaTiraggio,
            onElimina = viewModel::eliminaTiraggio,
        )
    }
}

@Composable
private fun ScortaHeroCard(pumpingStockMl: Double?) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.tertiaryContainer,
            contentColor = MaterialTheme.colorScheme.onTertiaryContainer,
        ),
    ) {
        Column(modifier = Modifier.padding(24.dp)) {
            Text(text = "Scorta latte materno", style = MaterialTheme.typography.labelMedium)
            Text(
                text = "${pumpingStockMl?.toInt() ?: 0} g disponibili",
                style = MaterialTheme.typography.headlineMedium,
            )
        }
    }
}

@Composable
private fun TiraggioForm(
    state: TiraggioUiState,
    onDataChange: (LocalDate) -> Unit,
    onOrarioChange: (java.time.LocalTime) -> Unit,
    onLatoChange: (Side) -> Unit,
    onQuantitaChange: (Int) -> Unit,
    onNoteChange: (String) -> Unit,
    onSalva: () -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(text = "Nuovo tiraggio", style = MaterialTheme.typography.titleMedium)

            DateField(
                label = "Data",
                date = state.form.data,
                onDateChange = onDataChange,
            )

            QuarterHourTimeField(
                label = "Orario",
                time = state.form.orario,
                onTimeChange = onOrarioChange,
            )

            Text(text = "Lato", style = MaterialTheme.typography.labelLarge)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Side.entries.forEach { lato ->
                    FilterChip(
                        selected = state.form.lato == lato,
                        onClick = { onLatoChange(lato) },
                        label = { Text(lato.label()) },
                    )
                }
            }

            NumberStepperField(
                label = "Quantità",
                value = state.form.quantitaMl,
                step = 5,
                unit = "g",
                onValueChange = onQuantitaChange,
            )

            OutlinedTextField(
                value = state.form.note,
                onValueChange = onNoteChange,
                label = { Text("Note") },
                minLines = 2,
                maxLines = 3,
                modifier = Modifier.fillMaxWidth(),
            )

            Button(
                onClick = onSalva,
                enabled = !state.isSaving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isSaving) "Salvataggio…" else "Salva tiraggio")
            }
        }
    }
}

@Composable
private fun TiraggioRow(tiraggio: Pumping, onClick: () -> Unit) {
    EntryRow(
        icon = Icons.Filled.WaterDrop,
        iconContainerColor = MaterialTheme.colorScheme.tertiaryContainer,
        iconContentColor = MaterialTheme.colorScheme.onTertiaryContainer,
        headline = "Tiraggio ${tiraggio.side.label()}",
        onClick = onClick,
        trailingContent = {
            Text("${tiraggio.quantityMl} g", style = MaterialTheme.typography.titleMedium)
            Text(
                formatHourMinute(tiraggio.hour, tiraggio.minute),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        },
        supportingContent = { NoteText(tiraggio.note) },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun TiraggioEditSheet(
    state: TiraggioUiState,
    onDismiss: () -> Unit,
    onOrarioChange: (java.time.LocalTime) -> Unit,
    onLatoChange: (Side) -> Unit,
    onQuantitaChange: (Int) -> Unit,
    onNoteChange: (String) -> Unit,
    onSalva: () -> Unit,
    onElimina: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState()
    var showEliminaDialog by remember { mutableStateOf(false) }
    val form = state.editForm

    ModalBottomSheet(onDismissRequest = onDismiss, sheetState = sheetState) {
        Column(
            modifier = Modifier
                .padding(16.dp)
                .fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(text = "Modifica tiraggio", style = MaterialTheme.typography.titleMedium)

            QuarterHourTimeField(
                label = "Orario",
                time = form.orario,
                onTimeChange = onOrarioChange,
            )

            Text(text = "Lato", style = MaterialTheme.typography.labelLarge)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Side.entries.forEach { lato ->
                    FilterChip(
                        selected = form.lato == lato,
                        onClick = { onLatoChange(lato) },
                        label = { Text(lato.label()) },
                    )
                }
            }

            NumberStepperField(
                label = "Quantità",
                value = form.quantitaMl,
                step = 5,
                unit = "g",
                onValueChange = onQuantitaChange,
            )

            OutlinedTextField(
                value = form.note,
                onValueChange = onNoteChange,
                label = { Text("Note") },
                minLines = 2,
                maxLines = 3,
                modifier = Modifier.fillMaxWidth(),
            )

            Button(
                onClick = onSalva,
                enabled = !state.isSavingEdit && !state.isDeleting,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isSavingEdit) "Salvataggio…" else "Salva modifiche")
            }

            OutlinedButton(
                onClick = { showEliminaDialog = true },
                enabled = !state.isSavingEdit && !state.isDeleting,
                colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isDeleting) "Eliminazione…" else "Elimina")
            }
        }
    }

    if (showEliminaDialog) {
        AlertDialog(
            onDismissRequest = { showEliminaDialog = false },
            title = { Text("Eliminare questo tiraggio?") },
            text = { Text("L'operazione non può essere annullata.") },
            confirmButton = {
                TextButton(onClick = {
                    showEliminaDialog = false
                    onElimina()
                }) { Text("Elimina") }
            },
            dismissButton = {
                TextButton(onClick = { showEliminaDialog = false }) { Text("Annulla") }
            },
        )
    }
}
