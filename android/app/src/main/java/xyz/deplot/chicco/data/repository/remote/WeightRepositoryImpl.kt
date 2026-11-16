package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import retrofit2.HttpException
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.WeightRepository

class WeightRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : WeightRepository {

    override suspend fun getHistory(childId: Int): ChiccoResult<List<Weight>> =
        safeApiCall { api.weights(childId).data!! }

    override suspend fun createWeight(weight: Weight): ChiccoResult<Weight> =
        safeApiCall { api.createWeight(weight).data!! }

    override suspend fun updateWeight(weight: Weight): ChiccoResult<Weight> =
        safeApiCall { api.updateWeight(weight.id, weight).data!! }

    override suspend fun deleteWeight(id: Int): ChiccoResult<Unit> =
        safeApiCall {
            val response = api.deleteWeight(id)
            if (!response.isSuccessful) throw HttpException(response)
        }
}
