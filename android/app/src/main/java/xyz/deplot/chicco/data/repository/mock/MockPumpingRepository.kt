package xyz.deplot.chicco.data.repository.mock

import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.delay
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.repository.PumpingRepository

@Singleton
class MockPumpingRepository @Inject constructor(
    private val backend: FakeChiccoBackend,
) : PumpingRepository {
    override suspend fun getPumpingsByDate(date: String, childId: Int): ChiccoResult<List<Pumping>> {
        delay(300)
        return ChiccoResult.Success(backend.pumpingsByDate(childId, date))
    }

    override suspend fun createPumping(pumping: Pumping): ChiccoResult<Pumping> {
        delay(300)
        return ChiccoResult.Success(backend.createPumping(pumping))
    }

    override suspend fun updatePumping(pumping: Pumping): ChiccoResult<Pumping> {
        delay(300)
        return ChiccoResult.Success(backend.updatePumping(pumping))
    }

    override suspend fun deletePumping(id: Int): ChiccoResult<Unit> {
        delay(300)
        backend.deletePumping(id)
        return ChiccoResult.Success(Unit)
    }
}
