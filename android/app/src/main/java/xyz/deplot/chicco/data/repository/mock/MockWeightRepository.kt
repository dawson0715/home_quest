package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Weight
import xyz.deplot.chicco.data.repository.WeightRepository

@Singleton
class MockWeightRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : WeightRepository {
    override suspend fun getHistory(childId: Int): ChiccoResult<List<Weight>> {
        delay(300)
        return ChiccoResult.Success(backend.weightsHistory(childId))
    }

    override suspend fun createWeight(weight: Weight): ChiccoResult<Weight> {
        delay(300)
        return ChiccoResult.Success(backend.createWeight(weight))
    }

    override suspend fun updateWeight(weight: Weight): ChiccoResult<Weight> {
        delay(300)
        return ChiccoResult.Success(backend.updateWeight(weight))
    }

    override suspend fun deleteWeight(id: Int): ChiccoResult<Unit> {
        delay(300)
        backend.deleteWeight(id)
        return ChiccoResult.Success(Unit)
    }
}
