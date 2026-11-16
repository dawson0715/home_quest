package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.repository.TodaySummaryRepository

@Singleton
class MockTodaySummaryRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : TodaySummaryRepository {
    override suspend fun getTodaySummary(childId: Int, date: String?): ChiccoResult<TodaySummary> {
        delay(300)
        return ChiccoResult.Success(backend.todaySummary(childId, date))
    }
}
