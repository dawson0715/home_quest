package xyz.deplot.chicco.ui.peso

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.MonitorWeight
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.drawText
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import java.time.LocalDate
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.ui.components.DateField
import xyz.deplot.chicco.ui.components.EntryRow
import xyz.deplot.chicco.ui.components.NetworkAwareContent
import xyz.deplot.chicco.ui.components.NoteText
import xyz.deplot.chicco.ui.components.NumberStepperField

@Composable
fun PesoScreen(viewModel: PesoViewModel = hiltViewModel()) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(Unit) { viewModel.load() }

    NetworkAwareContent(
        isLoading = uiState.isLoading,
        error = uiState.error,
        onRetry = viewModel::load,
        modifier = Modifier.fillMaxSize(),
    ) {
        val storicoCronologico = uiState.storico.sortedBy { it.date }
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            item { SparklineCard(storicoCronologico) }
            item {
                PesoForm(
                    state = uiState,
                    onGrammiChange = viewModel::onGrammiChange,
                    onDataChange = viewModel::onDataChange,
                    onNoteChange = viewModel::onNoteChange,
                    onSalva = viewModel::salvaPeso,
                )
            }
            item {
                Text(text = "Storico", style = MaterialTheme.typography.titleMedium)
            }
            val storicoDecrescente = storicoCronologico.reversed()
            item {
                Column {
                    storicoDecrescente.forEachIndexed { rowIndex, peso ->
                        val index = storicoCronologico.indexOf(peso)
                        val precedente = storicoCronologico.getOrNull(index - 1)
                        PesoRow(
                            peso,
                            delta = precedente?.let { peso.weightGrams - it.weightGrams },
                            onClick = { viewModel.iniziaModificaPeso(peso) },
                        )
                        if (rowIndex != storicoDecrescente.lastIndex) HorizontalDivider()
                    }
                }
            }
        }
    }

    if (uiState.editing != null) {
        PesoEditSheet(
            state = uiState,
            onDismiss = viewModel::annullaModifica,
            onGrammiChange = viewModel::onModificaGrammiChange,
            onDataChange = viewModel::onModificaDataChange,
            onNoteChange = viewModel::onModificaNoteChange,
            onSalva = viewModel::salvaModificaPeso,
            onElimina = viewModel::eliminaPeso,
        )
    }
}

@Composable
private fun SparklineCard(storico: List<Weight>) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer,
            contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        ),
    ) {
        Column(modifier = Modifier.padding(24.dp)) {
            Text(text = "Andamento peso", style = MaterialTheme.typography.labelMedium)
            Text(
                text = storico.lastOrNull()?.let { "${it.weightGrams} g" } ?: "—",
                style = MaterialTheme.typography.headlineMedium,
                modifier = Modifier.padding(bottom = 16.dp),
            )
            if (storico.size >= 2) {
                Sparkline(
                    values = storico.map { it.weightGrams },
                    lineColor = MaterialTheme.colorScheme.primary,
                    gridColor = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.15f),
                    labelColor = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(110.dp),
                )
            } else {
                Text(
                    text = "Aggiungi almeno due pesate per vedere l'andamento.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        }
    }
}

private const val SPARKLINE_GRID_LEVELS = 4

@Composable
private fun Sparkline(
    values: List<Int>,
    lineColor: Color,
    gridColor: Color,
    labelColor: Color,
    modifier: Modifier = Modifier,
) {
    val min = values.min()
    val max = values.max()
    val range = (max - min).coerceAtLeast(1)
    val textMeasurer = rememberTextMeasurer()
    val labelStyle = TextStyle(fontSize = 10.sp, color = labelColor)

    Canvas(modifier = modifier) {
        val leftPad = 40.dp.toPx()
        val plotWidth = (size.width - leftPad).coerceAtLeast(0f)
        val plotHeight = size.height

        // Horizontal grid with "XXXX g" value labels, evenly spaced between min and max.
        for (level in 0 until SPARKLINE_GRID_LEVELS) {
            val fraction = level / (SPARKLINE_GRID_LEVELS - 1).toFloat()
            val value = min + (fraction * range).toInt()
            val y = plotHeight - fraction * plotHeight
            drawLine(
                color = gridColor,
                start = Offset(leftPad, y),
                end = Offset(size.width, y),
                strokeWidth = 1.dp.toPx(),
            )
            val measured = textMeasurer.measure("$value g", labelStyle)
            drawText(
                textLayoutResult = measured,
                topLeft = Offset(0f, (y - measured.size.height / 2f).coerceIn(0f, plotHeight - measured.size.height)),
            )
        }

        val stepX = if (values.size > 1) plotWidth / (values.size - 1) else 0f
        val points = values.mapIndexed { index, value ->
            val x = leftPad + index * stepX
            val y = plotHeight - ((value - min).toFloat() / range) * plotHeight
            Offset(x, y)
        }
        for (i in 0 until points.size - 1) {
            drawLine(
                color = lineColor,
                start = points[i],
                end = points[i + 1],
                strokeWidth = 6f,
                cap = StrokeCap.Round,
            )
        }
        points.forEach { point ->
            drawCircle(color = lineColor, radius = 6f, center = point)
        }
    }
}

@Composable
private fun PesoForm(
    state: PesoUiState,
    onGrammiChange: (Int) -> Unit,
    onDataChange: (LocalDate) -> Unit,
    onNoteChange: (String) -> Unit,
    onSalva: () -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Text(text = "Nuova pesata", style = MaterialTheme.typography.titleMedium)

            DateField(
                label = "Data",
                date = state.form.data,
                onDateChange = onDataChange,
            )

            NumberStepperField(
                label = "Peso",
                value = state.form.grammi,
                step = 5,
                unit = "g",
                onValueChange = onGrammiChange,
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
                Text(if (state.isSaving) "Salvataggio…" else "Salva peso")
            }
        }
    }
}

@Composable
private fun PesoRow(peso: Weight, delta: Int?, onClick: () -> Unit) {
    val trailing = delta?.let { "${if (it >= 0) "+" else ""}$it g" }
    EntryRow(
        icon = Icons.Filled.MonitorWeight,
        iconContainerColor = MaterialTheme.colorScheme.secondaryContainer,
        iconContentColor = MaterialTheme.colorScheme.onSecondaryContainer,
        headline = "${peso.weightGrams} g",
        onClick = onClick,
        trailing = trailing,
        supportingContent = {
            Text(peso.date, style = MaterialTheme.typography.bodyMedium)
            NoteText(peso.note)
        },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PesoEditSheet(
    state: PesoUiState,
    onDismiss: () -> Unit,
    onGrammiChange: (Int) -> Unit,
    onDataChange: (LocalDate) -> Unit,
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
            Text(text = "Modifica pesata", style = MaterialTheme.typography.titleMedium)

            DateField(
                label = "Data",
                date = form.data,
                onDateChange = onDataChange,
            )

            NumberStepperField(
                label = "Peso",
                value = form.grammi,
                step = 5,
                unit = "g",
                onValueChange = onGrammiChange,
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
            title = { Text("Eliminare questa pesata?") },
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
