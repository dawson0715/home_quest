package xyz.deplot.chicco.ui.liste

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import xyz.deplot.chicco.data.ChiccoErrorType
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList
import xyz.deplot.chicco.data.repository.ChecklistRepository

data class ChecklistUiState(
    val isLoading: Boolean = true,
    val error: ChiccoErrorType? = null,
    val itemsByList: Map<ChecklistList, List<ChecklistItem>> = emptyMap(),
    val expandedList: ChecklistList? = ChecklistList.BAG,
    val newItemText: Map<ChecklistList, String> = emptyMap(),
)

@HiltViewModel
class ChecklistViewModel @Inject constructor(
    private val repository: ChecklistRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(ChecklistUiState())
    val uiState: StateFlow<ChecklistUiState> = _uiState.asStateFlow()

    fun load() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            var firstError: ChiccoErrorType? = null
            val itemsByList = ChecklistList.entries.associateWith { list ->
                when (val result = repository.getItems(list)) {
                    is ChiccoResult.Success -> result.data
                    is ChiccoResult.Error -> {
                        if (firstError == null) firstError = result.type
                        emptyList()
                    }
                }
            }
            _uiState.update { it.copy(isLoading = false, error = firstError, itemsByList = itemsByList) }
        }
    }

    fun toggleExpanded(list: ChecklistList) {
        _uiState.update { it.copy(expandedList = if (it.expandedList == list) null else list) }
    }

    fun onNewItemTextChange(list: ChecklistList, text: String) {
        _uiState.update { it.copy(newItemText = it.newItemText + (list to text)) }
    }

    /** Commits the pending new-item text for [list] and clears the input so the next line is ready to type. */
    fun addItem(list: ChecklistList) {
        val text = _uiState.value.newItemText[list]?.trim().orEmpty()
        if (text.isEmpty()) return
        _uiState.update { it.copy(newItemText = it.newItemText + (list to "")) }
        viewModelScope.launch {
            when (val result = repository.createItem(ChecklistItem(id = 0, list = list, text = text))) {
                is ChiccoResult.Success -> _uiState.update { state ->
                    val current = state.itemsByList[list].orEmpty()
                    state.copy(itemsByList = state.itemsByList + (list to (current + result.data)))
                }
                is ChiccoResult.Error -> Unit
            }
        }
    }

    /** Optimistically flips [item] and re-sorts so checked items sink below the unchecked ones. */
    fun toggleChecked(item: ChecklistItem) {
        val updated = item.copy(isChecked = !item.isChecked)
        _uiState.update { state ->
            val reordered = state.itemsByList[item.list].orEmpty()
                .map { if (it.id == item.id) updated else it }
                .sortedWith(compareBy({ it.isChecked }, { it.position }))
            state.copy(itemsByList = state.itemsByList + (item.list to reordered))
        }
        viewModelScope.launch { repository.updateItem(updated) }
    }

    fun deleteItem(item: ChecklistItem) {
        _uiState.update { state ->
            val filtered = state.itemsByList[item.list].orEmpty().filterNot { it.id == item.id }
            state.copy(itemsByList = state.itemsByList + (item.list to filtered))
        }
        viewModelScope.launch { repository.deleteItem(item.id) }
    }
}
