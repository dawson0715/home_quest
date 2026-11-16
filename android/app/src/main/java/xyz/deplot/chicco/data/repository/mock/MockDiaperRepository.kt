package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.repository.DiaperRepository

@Singleton
class MockDiaperRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : DiaperRepository {
    override suspend fun getDiapersByDate(date: String, childId: Int): ChiccoResult<List<Diaper>> {
        delay(300)
        return ChiccoResult.Success(backend.diapersByDate(childId, date))
    }

    override suspend fun createDiaper(diaper: Diaper): ChiccoResult<Diaper> {
        delay(300)
        return ChiccoResult.Success(backend.createDiaper(diaper))
    }

    override suspend fun updateDiaper(diaper: Diaper): ChiccoResult<Diaper> {
        delay(300)
        return ChiccoResult.Success(backend.updateDiaper(diaper))
    }

    override suspend fun deleteDiaper(id: Int): ChiccoResult<Unit> {
        delay(300)
        backend.deleteDiaper(id)
        return ChiccoResult.Success(Unit)
    }
}
