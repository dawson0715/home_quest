package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import retrofit2.HttpException
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.DiaperRepository

class DiaperRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : DiaperRepository {

    override suspend fun getDiapersByDate(date: String, childId: Int): ChiccoResult<List<Diaper>> =
        safeApiCall { api.diapers(childId, date = date).data!! }

    override suspend fun createDiaper(diaper: Diaper): ChiccoResult<Diaper> =
        safeApiCall { api.createDiaper(diaper).data!! }

    override suspend fun updateDiaper(diaper: Diaper): ChiccoResult<Diaper> =
        safeApiCall { api.updateDiaper(diaper.id, diaper).data!! }

    override suspend fun deleteDiaper(id: Int): ChiccoResult<Unit> =
        safeApiCall {
            val response = api.deleteDiaper(id)
            if (!response.isSuccessful) throw HttpException(response)
        }
}
