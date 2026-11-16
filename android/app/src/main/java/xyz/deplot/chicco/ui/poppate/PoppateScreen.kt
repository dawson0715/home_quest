package xyz.deplot.chicco.ui.poppate

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SegmentedButton
import androidx.compose.material3.SegmentedButtonDefaults
import androidx.compose.material3.SingleChoiceSegmentedButtonRow
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import java.time.LocalDate
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.FeedingType
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
fun PoppateScreen(viewModel: PoppataViewModel = hiltViewModel()) {
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
            item {
                PoppataForm(
                    state = uiState,
                    onTipoChange = viewModel::onTipoChange,
                    onDataChange = viewModel::onDataChange,
                    onOrarioChange = viewModel::onOrarioChange,
                    onQuantitaChange = viewModel::onQuantitaChange,
                    onLatoChange = viewModel::onLatoChange,
                    onDurataChange = viewModel::onDurataChange,
                    onNoteChange = viewModel::onNoteChange,
                    onSalva = viewModel::salvaPoppata,
                )
            }
            if (uiState.poppateOggi.isEmpty()) {
                item {
                    Text(
                        text = "Nessuna poppata registrata in questo giorno.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else {
                item {
                    val poppate = uiState.poppateOggi.sortedByDescending { it.hour * 60 + it.minute }
                    Column {
                        poppate.forEachIndexed { index, poppata ->
                            PoppataRow(poppata, onClick = { viewModel.iniziaModificaPoppata(poppata) })
                            if (index != poppate.lastIndex) HorizontalDivider()
                        }
                    }
                }
            }
        }
    }

    if (uiState.editing != null) {
        PoppataEditSheet(
            state = uiState,
            onDismiss = viewModel::annullaModifica,
            onTipoChange = viewModel::onModificaTipoChange,
            onOrarioChange = viewModel::onModificaOrarioChange,
            onQuantitaChange = viewModel::onModificaQuantitaChange,
            onLatoChange = viewModel::onModificaLatoChange,
            onDurataChange = viewModel::onModificaDurataChange,
            onNoteChange = viewModel::onModificaNoteChange,
            onSalva = viewModel::salvaModificaPoppata,
            onElimina = viewModel::eliminaPoppata,
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PoppataForm(
    state: PoppataUiState,
    onTipoChange: (FeedingType) -> Unit,
    onDataChange: (LocalDate) -> Unit,
    onOrarioChange: (java.time.LocalTime) -> Unit,
    onQuantitaChange: (Int) -> Unit,
    onLatoChange: (Side) -> Unit,
    onDurataChange: (Int) -> Unit,
    onNoteChange: (String) -> Unit,
    onSalva: () -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(text = "Nuova poppata", style = MaterialTheme.typography.titleMedium)

            DateField(
                label = "Data",
                date = state.form.data,
                onDateChange = onDataChange,
            )

            SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                val tipi = FeedingType.entries
                tipi.forEachIndexed { index, tipo ->
                    SegmentedButton(
                        shape = SegmentedButtonDefaults.itemShape(index = index, count = tipi.size),
                        selected = state.form.tipo == tipo,
                        onClick = { onTipoChange(tipo) },
                        label = {
                            Text(
                                text = tipo.label(),
                                style = MaterialTheme.typography.labelMedium,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                }
            }

            QuarterHourTimeField(
                label = "Orario",
                time = state.form.orario,
                onTimeChange = onOrarioChange,
            )

            if (state.form.tipo == FeedingType.BREAST) {
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
                    label = "Durata",
                    value = state.form.durataMinuti,
                    step = 5,
                    unit = "min",
                    onValueChange = onDurataChange,
                )
            } else {
                NumberStepperField(
                    label = "Quantità",
                    value = state.form.quantitaMl,
                    step = 5,
                    unit = "g",
                    onValueChange = onQuantitaChange,
                )
                val stima = state.mlRimastiStimati
                Text(
                    text = if (stima != null) "Resteranno circa ${stima.toInt()} g oggi" else "Fabbisogno non disponibile",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

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
                Text(if (state.isSaving) "Salvataggio…" else "Salva poppata")
            }
        }
    }
}

@Composable
private fun PoppataRow(poppata: Feeding, onClick: () -> Unit) {
    val quantita = when (poppata.type) {
        FeedingType.BREAST -> "${poppata.side?.label()} · ${poppata.durationMinutes ?: 0} min"
        else -> "${poppata.quantityMl ?: 0} g"
    }
    EntryRow(
        icon = Icons.Filled.Restaurant,
        iconContainerColor = MaterialTheme.colorScheme.primaryContainer,
        iconContentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        headline = poppata.type.label(),
        onClick = onClick,
        trailingContent = {
            Text(quantita, style = MaterialTheme.typography.titleMedium)
            Text(
                formatHourMinute(poppata.hour, poppata.minute),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        },
        supportingContent = { NoteText(poppata.note) },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PoppataEditSheet(
    state: PoppataUiState,
    onDismiss: () -> Unit,
    onTipoChange: (FeedingType) -> Unit,
    onOrarioChange: (java.time.LocalTime) -> Unit,
    onQuantitaChange: (Int) -> Unit,
    onLatoChange: (Side) -> Unit,
    onDurataChange: (Int) -> Unit,
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
            Text(text = "Modifica poppata", style = MaterialTheme.typography.titleMedium)

            SingleChoiceSegmentedButtonRow(modifier = Modifier.fillMaxWidth()) {
                val tipi = FeedingType.entries
                tipi.forEachIndexed { index, tipo ->
                    SegmentedButton(
                        shape = SegmentedButtonDefaults.itemShape(index = index, count = tipi.size),
                        selected = form.tipo == tipo,
                        onClick = { onTipoChange(tipo) },
                        label = {
                            Text(
                                text = tipo.label(),
                                style = MaterialTheme.typography.labelMedium,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                            )
                        },
                    )
                }
            }

            QuarterHourTimeField(
                label = "Orario",
                time = form.orario,
                onTimeChange = onOrarioChange,
            )

            if (form.tipo == FeedingType.BREAST) {
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
                    label = "Durata",
                    value = form.durataMinuti,
                    step = 5,
                    unit = "min",
                    onValueChange = onDurataChange,
                )
            } else {
                NumberStepperField(
                    label = "Quantità",
                    value = form.quantitaMl,
                    step = 5,
                    unit = "g",
                    onValueChange = onQuantitaChange,
                )
            }

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
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = MaterialTheme.colorScheme.error,
                ),
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (state.isDeleting) "Eliminazione…" else "Elimina")
            }
        }
    }

    if (showEliminaDialog) {
        AlertDialog(
            onDismissRequest = { showEliminaDialog = false },
            title = { Text("Eliminare questa poppata?") },
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
