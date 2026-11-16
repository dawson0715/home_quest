package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import retrofit2.HttpException
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.FeedingRepository

class FeedingRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : FeedingRepository {

    override suspend fun getFeedingsByDate(date: String, childId: Int): ChiccoResult<List<Feeding>> =
        safeApiCall { api.feedings(childId, date = date).data!! }

    override suspend fun createFeeding(feeding: Feeding): ChiccoResult<Feeding> =
        safeApiCall { api.createFeeding(feeding).data!! }

    override suspend fun updateFeeding(feeding: Feeding): ChiccoResult<Feeding> =
        safeApiCall { api.updateFeeding(feeding.id, feeding).data!! }

    override suspend fun deleteFeeding(id: Int): ChiccoResult<Unit> =
        safeApiCall {
            val response = api.deleteFeeding(id)
            if (!response.isSuccessful) throw HttpException(response)
        }
}
