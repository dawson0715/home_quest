package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import retrofit2.HttpException
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.PumpingRepository

class PumpingRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : PumpingRepository {

    override suspend fun getPumpingsByDate(date: String, childId: Int): ChiccoResult<List<Pumping>> =
        safeApiCall { api.pumpings(childId, date = date).data!! }

    override suspend fun createPumping(pumping: Pumping): ChiccoResult<Pumping> =
        safeApiCall { api.createPumping(pumping).data!! }

    override suspend fun updatePumping(pumping: Pumping): ChiccoResult<Pumping> =
        safeApiCall { api.updatePumping(pumping.id, pumping).data!! }

    override suspend fun deletePumping(id: Int): ChiccoResult<Unit> =
        safeApiCall {
            val response = api.deletePumping(id)
            if (!response.isSuccessful) throw HttpException(response)
        }
}
