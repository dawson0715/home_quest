package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.repository.FeedingRepository

@Singleton
class MockFeedingRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : FeedingRepository {
    override suspend fun getFeedingsByDate(date: String, childId: Int): ChiccoResult<List<Feeding>> {
        delay(300)
        return ChiccoResult.Success(backend.feedingsByDate(childId, date))
    }

    override suspend fun createFeeding(feeding: Feeding): ChiccoResult<Feeding> {
        delay(300)
        return ChiccoResult.Success(backend.createFeeding(feeding))
    }

    override suspend fun updateFeeding(feeding: Feeding): ChiccoResult<Feeding> {
        delay(300)
        return ChiccoResult.Success(backend.updateFeeding(feeding))
    }

    override suspend fun deleteFeeding(id: Int): ChiccoResult<Unit> {
        delay(300)
        backend.deleteFeeding(id)
        return ChiccoResult.Success(Unit)
    }
}
