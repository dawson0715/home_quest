package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Pumping

interface PumpingRepository {
    suspend fun getPumpingsByDate(date: String, childId: Int = DEFAULT_CHILD_ID): ChiccoResult<List<Pumping>>
    suspend fun createPumping(pumping: Pumping): ChiccoResult<Pumping>
    suspend fun updatePumping(pumping: Pumping): ChiccoResult<Pumping>
    suspend fun deletePumping(id: Int): ChiccoResult<Unit>
}
