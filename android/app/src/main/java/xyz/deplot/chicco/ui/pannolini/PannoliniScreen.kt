package xyz.deplot.chicco.ui.pannolini

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BabyChangingStation
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import java.time.LocalDate
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.DiaperType
import xyz.deplot.chicco.data.model.label
import xyz.deplot.chicco.ui.components.DateField
import xyz.deplot.chicco.ui.components.DateNavigator
import xyz.deplot.chicco.ui.components.EntryRow
import xyz.deplot.chicco.ui.components.NetworkAwareContent
import xyz.deplot.chicco.ui.components.NoteText
import xyz.deplot.chicco.ui.components.QuarterHourTimeField
import xyz.deplot.chicco.ui.navigation.LocalTopBarContent
import xyz.deplot.chicco.ui.theme.customColors
import xyz.deplot.chicco.util.formatHourMinute

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PannoliniScreen(viewModel: PannolinoViewModel = hiltViewModel()) {
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
            if (uiState.selectedDate == LocalDate.now()) {
                item {
                    Text(text = "Registrazione rapida", style = MaterialTheme.typography.titleMedium)
                }
                item {
                    QuickTilesRow(
                        isSaving = uiState.isSaving,
                        onTileClick = viewModel::registraRapido,
                    )
                }
            } else {
                item {
                    Text(
                        text = "Stai visualizzando un giorno passato. Torna a oggi per registrare un nuovo pannolino.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            item {
                RiepilogoConteggioCard(uiState.conteggioPerTipo)
            }
            if (uiState.pannoliniOggi.isEmpty()) {
                item {
                    Text(
                        text = "Nessun pannolino registrato in questo giorno.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else {
                item {
                    val pannolini = uiState.pannoliniOggi.sortedByDescending { it.hour * 60 + it.minute }
                    Column {
                        pannolini.forEachIndexed { index, pannolino ->
                            PannolinoRow(pannolino, onClick = { viewModel.iniziaModificaPannolino(pannolino) })
                            if (index != pannolini.lastIndex) HorizontalDivider()
                        }
                    }
                }
            }
        }
    }

    if (uiState.editing != null) {
        PannolinoEditSheet(
            state = uiState,
            onDismiss = viewModel::annullaModifica,
            onTipoChange = viewModel::onModificaTipoChange,
            onDataChange = viewModel::onModificaDataChange,
            onOrarioChange = viewModel::onModificaOrarioChange,
            onNoteChange = viewModel::onModificaNoteChange,
            onSalva = viewModel::salvaModificaPannolino,
            onElimina = viewModel::eliminaPannolino,
        )
    }
}

@Composable
private fun QuickTilesRow(isSaving: Boolean, onTileClick: (DiaperType) -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        DiaperType.entries.forEach { tipo ->
            QuickTile(
                tipo = tipo,
                enabled = !isSaving,
                onClick = { onTileClick(tipo) },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun QuickTile(
    tipo: DiaperType,
    enabled: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier.clickable(enabled = enabled, onClick = onClick),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.customColors.warmContainer,
            contentColor = MaterialTheme.customColors.onWarmContainer,
        ),
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Icon(Icons.Filled.BabyChangingStation, contentDescription = null)
            Text(text = tipo.label(), style = MaterialTheme.typography.labelLarge)
        }
    }
}

@Composable
private fun RiepilogoConteggioCard(conteggio: Map<DiaperType, Int>) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
        ) {
            conteggio.forEach { (tipo, count) ->
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(text = "$count", style = MaterialTheme.typography.headlineSmall)
                    Text(text = tipo.label(), style = MaterialTheme.typography.bodySmall)
                }
            }
        }
    }
}

@Composable
private fun PannolinoRow(pannolino: Diaper, onClick: () -> Unit) {
    EntryRow(
        icon = Icons.Filled.BabyChangingStation,
        iconContainerColor = MaterialTheme.customColors.warmContainer,
        iconContentColor = MaterialTheme.customColors.onWarmContainer,
        headline = pannolino.type.label(),
        onClick = onClick,
        trailing = formatHourMinute(pannolino.hour, pannolino.minute),
        supportingContent = { NoteText(pannolino.note) },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PannolinoEditSheet(
    state: PannolinoUiState,
    onDismiss: () -> Unit,
    onTipoChange: (DiaperType) -> Unit,
    onDataChange: (LocalDate) -> Unit,
    onOrarioChange: (java.time.LocalTime) -> Unit,
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
            Text(text = "Modifica pannolino", style = MaterialTheme.typography.titleMedium)

            DateField(
                label = "Data",
                date = form.data,
                onDateChange = onDataChange,
            )

            Text(text = "Tipo", style = MaterialTheme.typography.labelLarge)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                DiaperType.entries.forEach { tipo ->
                    FilterChip(
                        selected = form.tipo == tipo,
                        onClick = { onTipoChange(tipo) },
                        label = { Text(tipo.label()) },
                    )
                }
            }

            QuarterHourTimeField(
                label = "Orario",
                time = form.orario,
                onTimeChange = onOrarioChange,
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
            title = { Text("Eliminare questo pannolino?") },
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
