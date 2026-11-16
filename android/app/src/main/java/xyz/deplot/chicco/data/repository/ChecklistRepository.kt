package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList

interface ChecklistRepository {
    suspend fun getItems(list: ChecklistList): ChiccoResult<List<ChecklistItem>>
    suspend fun createItem(item: ChecklistItem): ChiccoResult<ChecklistItem>
    suspend fun updateItem(item: ChecklistItem): ChiccoResult<ChecklistItem>
    suspend fun deleteItem(id: Int): ChiccoResult<Unit>
}
