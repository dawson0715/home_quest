package xyz.deplot.chicco.ui.liste

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList
import xyz.deplot.chicco.data.model.label
import xyz.deplot.chicco.ui.components.NetworkAwareContent

@Composable
fun ChecklistScreen(viewModel: ChecklistViewModel = hiltViewModel()) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(Unit) { viewModel.load() }

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
            items(ChecklistList.entries.toList()) { list ->
                ChecklistAccordionItem(
                    list = list,
                    items = uiState.itemsByList[list].orEmpty(),
                    expanded = uiState.expandedList == list,
                    newItemText = uiState.newItemText[list].orEmpty(),
                    onHeaderClick = { viewModel.toggleExpanded(list) },
                    onNewItemTextChange = { viewModel.onNewItemTextChange(list, it) },
                    onAddItem = { viewModel.addItem(list) },
                    onToggleChecked = viewModel::toggleChecked,
                    onDeleteItem = viewModel::deleteItem,
                )
            }
        }
    }
}

@Composable
private fun ChecklistAccordionItem(
    list: ChecklistList,
    items: List<ChecklistItem>,
    expanded: Boolean,
    newItemText: String,
    onHeaderClick: () -> Unit,
    onNewItemTextChange: (String) -> Unit,
    onAddItem: () -> Unit,
    onToggleChecked: (ChecklistItem) -> Unit,
    onDeleteItem: (ChecklistItem) -> Unit,
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable(onClick = onHeaderClick)
                    .padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(text = list.label(), style = MaterialTheme.typography.titleMedium)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    val uncheckedCount = items.count { !it.isChecked }
                    if (uncheckedCount > 0) {
                        Text(
                            text = "$uncheckedCount",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    Icon(
                        imageVector = if (expanded) Icons.Filled.ExpandLess else Icons.Filled.ExpandMore,
                        contentDescription = if (expanded) "Comprimi" else "Espandi",
                    )
                }
            }
            AnimatedVisibility(visible = expanded) {
                Column(modifier = Modifier.padding(start = 16.dp, end = 16.dp, bottom = 16.dp)) {
                    items.filterNot { it.isChecked }.forEach { item ->
                        ChecklistItemRow(item = item, onToggleChecked = onToggleChecked, onDelete = onDeleteItem)
                    }
                    NewChecklistItemRow(
                        text = newItemText,
                        onTextChange = onNewItemTextChange,
                        onCommit = onAddItem,
                    )
                    val checked = items.filter { it.isChecked }
                    if (checked.isNotEmpty()) {
                        HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
                        checked.forEach { item ->
                            ChecklistItemRow(item = item, onToggleChecked = onToggleChecked, onDelete = onDeleteItem)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ChecklistItemRow(
    item: ChecklistItem,
    onToggleChecked: (ChecklistItem) -> Unit,
    onDelete: (ChecklistItem) -> Unit,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Checkbox(checked = item.isChecked, onCheckedChange = { onToggleChecked(item) })
        Text(
            text = item.text,
            style = MaterialTheme.typography.bodyLarge,
            textDecoration = if (item.isChecked) TextDecoration.LineThrough else null,
            color = if (item.isChecked) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.weight(1f),
        )
        IconButton(onClick = { onDelete(item) }) {
            Icon(
                Icons.Filled.Delete,
                contentDescription = "Elimina elemento",
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

/** Google Keep-style always-visible input: type and press invio to add, the row stays ready for the next one. */
@Composable
private fun NewChecklistItemRow(
    text: String,
    onTextChange: (String) -> Unit,
    onCommit: () -> Unit,
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            Icons.Filled.Add,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(start = 12.dp, end = 12.dp),
        )
        TextField(
            value = text,
            onValueChange = onTextChange,
            placeholder = {
                Text("Aggiungi elemento", fontStyle = FontStyle.Italic, color = MaterialTheme.colorScheme.onSurfaceVariant)
            },
            singleLine = true,
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
            keyboardActions = KeyboardActions(onDone = { onCommit() }),
            colors = TextFieldDefaults.colors(
                focusedIndicatorColor = Color.Transparent,
                unfocusedIndicatorColor = Color.Transparent,
                focusedContainerColor = Color.Transparent,
                unfocusedContainerColor = Color.Transparent,
            ),
            modifier = Modifier.weight(1f),
        )
    }
}
