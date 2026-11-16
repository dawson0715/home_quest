package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.Weight

interface WeightRepository {
    suspend fun getHistory(childId: Int = DEFAULT_CHILD_ID): ChiccoResult<List<Weight>>
    suspend fun createWeight(weight: Weight): ChiccoResult<Weight>
    suspend fun updateWeight(weight: Weight): ChiccoResult<Weight>
    suspend fun deleteWeight(id: Int): ChiccoResult<Unit>
}
