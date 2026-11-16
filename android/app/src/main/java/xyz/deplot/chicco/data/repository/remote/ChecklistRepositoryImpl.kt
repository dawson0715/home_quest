package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import retrofit2.HttpException
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList
import xyz.deplot.chicco.data.model.apiValue
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.ChecklistRepository

class ChecklistRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : ChecklistRepository {

    override suspend fun getItems(list: ChecklistList): ChiccoResult<List<ChecklistItem>> =
        safeApiCall { api.checklistItems(list.apiValue()).data!! }

    override suspend fun createItem(item: ChecklistItem): ChiccoResult<ChecklistItem> =
        safeApiCall { api.createChecklistItem(item).data!! }

    override suspend fun updateItem(item: ChecklistItem): ChiccoResult<ChecklistItem> =
        safeApiCall { api.updateChecklistItem(item.id, item).data!! }

    override suspend fun deleteItem(id: Int): ChiccoResult<Unit> =
        safeApiCall {
            val response = api.deleteChecklistItem(id)
            if (!response.isSuccessful) throw HttpException(response)
        }
}
