package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Feeding

interface FeedingRepository {
    suspend fun getFeedingsByDate(date: String, childId: Int = DEFAULT_CHILD_ID): ChiccoResult<List<Feeding>>
    suspend fun createFeeding(feeding: Feeding): ChiccoResult<Feeding>
    suspend fun updateFeeding(feeding: Feeding): ChiccoResult<Feeding>
    suspend fun deleteFeeding(id: Int): ChiccoResult<Unit>
}
