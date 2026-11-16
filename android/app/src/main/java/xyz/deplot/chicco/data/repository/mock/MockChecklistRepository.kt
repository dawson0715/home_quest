package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList
import xyz.deplot.chicco.data.repository.ChecklistRepository

@Singleton
class MockChecklistRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : ChecklistRepository {
    override suspend fun getItems(list: ChecklistList): ChiccoResult<List<ChecklistItem>> {
        delay(300)
        return ChiccoResult.Success(backend.checklistItemsByList(list))
    }

    override suspend fun createItem(item: ChecklistItem): ChiccoResult<ChecklistItem> {
        delay(300)
        return ChiccoResult.Success(backend.createChecklistItem(item))
    }

    override suspend fun updateItem(item: ChecklistItem): ChiccoResult<ChecklistItem> {
        delay(300)
        return ChiccoResult.Success(backend.updateChecklistItem(item))
    }

    override suspend fun deleteItem(id: Int): ChiccoResult<Unit> {
        delay(300)
        backend.deleteChecklistItem(id)
        return ChiccoResult.Success(Unit)
    }
}
