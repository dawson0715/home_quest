package xyz.deplot.chicco.data.repository.remote

import javax.inject.Inject
import xyz.deplot.chicco.data.ChiccoResult
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.remote.ChiccoApiService
import xyz.deplot.chicco.data.remote.safeApiCall
import xyz.deplot.chicco.data.repository.DEFAULT_CHILD_ID
import xyz.deplot.chicco.data.repository.TodaySummaryRepository

class TodaySummaryRepositoryImpl @Inject constructor(
    private val api: ChiccoApiService,
) : TodaySummaryRepository {

    override suspend fun getTodaySummary(childId: Int, date: String?): ChiccoResult<TodaySummary> =
        safeApiCall { api.todaySummary(childId, date).data!! }
}
