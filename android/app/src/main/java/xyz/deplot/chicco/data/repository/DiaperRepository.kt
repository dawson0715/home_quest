package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Diaper

interface DiaperRepository {
    suspend fun getDiapersByDate(date: String, childId: Int = DEFAULT_CHILD_ID): ChiccoResult<List<Diaper>>
    suspend fun createDiaper(diaper: Diaper): ChiccoResult<Diaper>
    suspend fun updateDiaper(diaper: Diaper): ChiccoResult<Diaper>
    suspend fun deleteDiaper(id: Int): ChiccoResult<Unit>
}
