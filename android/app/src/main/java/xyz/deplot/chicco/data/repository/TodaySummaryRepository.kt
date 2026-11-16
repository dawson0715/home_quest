package xyz.deplot.chicco.data.repository

import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.TodaySummary

interface TodaySummaryRepository {
    suspend fun getTodaySummary(childId: Int = DEFAULT_CHILD_ID, date: String? = null): ChiccoResult<TodaySummary>
}
