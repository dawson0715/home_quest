package xyz.deplot.chicco.data.repository.mock

import java.time.LocalDate
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.ChecklistList
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.DiaperType
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.FeedingType
import xyz.deplot.chicco.data.model.LatestWeight
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.Side
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.model.Weight

/**
 * In-memory stand-in for the Yii2 backend, reproducing its business rules
 * (daily need = latest weight kg * ml_per_kg_day, ml_remaining_after
 * recomputed in chronological order, cumulative pumping stock) so the UI
 * behaves the same way it will once wired to the real API.
 */
@Singleton
class FakeChiccoBackend @Inject constructor() {

    private val mutex = Mutex()
    private val mlPerKgDay = 160.0

    private val weights = mutableListOf(
        Weight(id = 1, childId = 1, date = "2026-07-05", weightGrams = 4200),
        Weight(id = 2, childId = 1, date = "2026-07-12", weightGrams = 4400),
        Weight(id = 3, childId = 1, date = "2026-07-19", weightGrams = 4550),
        Weight(id = 4, childId = 1, date = "2026-07-26", weightGrams = 4700),
    )
    private var nextWeightId = 5

    private val feedings = mutableListOf(
        Feeding(id = 1, childId = 1, date = today(), hour = 7, minute = 0, type = FeedingType.BREAST, side = Side.LEFT, durationMinutes = 15),
        Feeding(id = 2, childId = 1, date = today(), hour = 10, minute = 30, type = FeedingType.FORMULA, quantityMl = 120),
        Feeding(id = 3, childId = 1, date = today(), hour = 13, minute = 15, type = FeedingType.BREAST_MILK, quantityMl = 90),
    )
    private var nextFeedingId = 4

    private val pumpings = mutableListOf(
        Pumping(id = 1, childId = 1, date = today(), hour = 9, minute = 0, side = Side.BOTH, quantityMl = 150),
    )
    private var nextPumpingId = 2

    private val diapers = mutableListOf(
        Diaper(id = 1, childId = 1, date = today(), hour = 7, minute = 15, type = DiaperType.PEE),
        Diaper(id = 2, childId = 1, date = today(), hour = 11, minute = 30, type = DiaperType.MIXED),
    )
    private var nextDiaperId = 3

    private val checklistItems = mutableListOf<ChecklistItem>()
    private var nextChecklistItemId = 1

    private fun today(): String = LocalDate.now().toString()

    private fun latestWeightFor(childId: Int): Weight? =
        weights.filter { it.childId == childId }.maxByOrNull { it.date }

    private fun dailyNeedMlFor(childId: Int): Double? {
        val latest = latestWeightFor(childId) ?: return null
        return (latest.weightGrams / 1000.0) * mlPerKgDay
    }

    /** Recomputes ml_remaining_after for every formula/breast_milk feeding of the day, in hour:minute order. */
    private fun recomputeDay(childId: Int, date: String) {
        val dailyNeed = dailyNeedMlFor(childId) ?: run {
            feedings.replaceAll {
                if (it.childId == childId && it.date == date) it.copy(mlRemainingAfter = null) else it
            }
            return
        }
        var remaining = dailyNeed
        val dayFeedings = feedings
            .filter { it.childId == childId && it.date == date }
            .sortedWith(compareBy({ it.hour }, { it.minute }, { it.id }))
        val updated = mutableMapOf<Int, Int?>()
        for (feeding in dayFeedings) {
            if (feeding.type == FeedingType.BREAST) {
                updated[feeding.id] = null
            } else {
                remaining = (remaining - (feeding.quantityMl ?: 0)).coerceAtLeast(0.0)
                updated[feeding.id] = remaining.toInt()
            }
        }
        feedings.replaceAll { f ->
            if (f.childId == childId && f.date == date && updated.containsKey(f.id)) {
                f.copy(mlRemainingAfter = updated[f.id])
            } else {
                f
            }
        }
    }

    private fun pumpingStockFor(childId: Int): Double {
        val produced = pumpings.filter { it.childId == childId }.sumOf { it.quantityMl }
        val consumed = feedings
            .filter { it.childId == childId && it.type == FeedingType.BREAST_MILK }
            .sumOf { it.quantityMl ?: 0 }
        return (produced - consumed).toDouble()
    }

    suspend fun todaySummary(childId: Int, date: String? = null): TodaySummary = mutex.withLock {
        val date = date ?: today()
        val dailyNeed = dailyNeedMlFor(childId)
        val consumedToday = feedings
            .filter { it.childId == childId && it.date == date && it.type != FeedingType.BREAST }
            .sumOf { it.quantityMl ?: 0 }
        val latestFeeding = feedings
            .filter { it.childId == childId }
            .maxWithOrNull(compareBy({ it.date }, { it.hour }, { it.minute }, { it.id }))
        TodaySummary(
            childId = childId,
            date = date,
            dailyNeedMl = dailyNeed,
            currentRemainingMl = dailyNeed?.let { (it - consumedToday).coerceAtLeast(0.0) },
            dailyNeedError = if (dailyNeed == null) "Nessun peso registrato per calcolare il fabbisogno giornaliero." else null,
            pumpingStockMl = pumpingStockFor(childId),
            latestWeight = latestWeightFor(childId)?.let { LatestWeight(it.date, it.weightGrams) },
            latestFeeding = latestFeeding,
            diaperCountToday = diapers.count { it.childId == childId && it.date == date },
        )
    }

    suspend fun feedingsByDate(childId: Int, date: String): List<Feeding> = mutex.withLock {
        feedings.filter { it.childId == childId && it.date == date }
    }

    suspend fun createFeeding(feeding: Feeding): Feeding = mutex.withLock {
        val saved = feeding.copy(id = nextFeedingId++)
        feedings.add(saved)
        recomputeDay(saved.childId, saved.date)
        feedings.first { it.id == saved.id }
    }

    suspend fun updateFeeding(feeding: Feeding): Feeding = mutex.withLock {
        val index = feedings.indexOfFirst { it.id == feeding.id }
        feedings[index] = feeding
        recomputeDay(feeding.childId, feeding.date)
        feedings.first { it.id == feeding.id }
    }

    suspend fun deleteFeeding(id: Int) = mutex.withLock {
        val removed = feedings.firstOrNull { it.id == id }
        feedings.removeIf { it.id == id }
        if (removed != null) recomputeDay(removed.childId, removed.date)
    }

    suspend fun pumpingsByDate(childId: Int, date: String): List<Pumping> = mutex.withLock {
        pumpings.filter { it.childId == childId && it.date == date }
    }

    suspend fun createPumping(pumping: Pumping): Pumping = mutex.withLock {
        val saved = pumping.copy(id = nextPumpingId++)
        pumpings.add(saved)
        saved
    }

    suspend fun updatePumping(pumping: Pumping): Pumping = mutex.withLock {
        val index = pumpings.indexOfFirst { it.id == pumping.id }
        pumpings[index] = pumping
        pumping
    }

    suspend fun deletePumping(id: Int) = mutex.withLock {
        pumpings.removeIf { it.id == id }
    }

    suspend fun weightsHistory(childId: Int): List<Weight> = mutex.withLock {
        weights.filter { it.childId == childId }.sortedBy { it.date }
    }

    suspend fun createWeight(weight: Weight): Weight = mutex.withLock {
        val saved = weight.copy(id = nextWeightId++)
        weights.add(saved)
        saved
    }

    suspend fun updateWeight(weight: Weight): Weight = mutex.withLock {
        val index = weights.indexOfFirst { it.id == weight.id }
        weights[index] = weight
        weight
    }

    suspend fun deleteWeight(id: Int) = mutex.withLock {
        weights.removeIf { it.id == id }
    }

    suspend fun diapersByDate(childId: Int, date: String): List<Diaper> = mutex.withLock {
        diapers.filter { it.childId == childId && it.date == date }
    }

    suspend fun createDiaper(diaper: Diaper): Diaper = mutex.withLock {
        val saved = diaper.copy(id = nextDiaperId++)
        diapers.add(saved)
        saved
    }

    suspend fun updateDiaper(diaper: Diaper): Diaper = mutex.withLock {
        val index = diapers.indexOfFirst { it.id == diaper.id }
        diapers[index] = diaper
        diaper
    }

    suspend fun deleteDiaper(id: Int) = mutex.withLock {
        diapers.removeIf { it.id == id }
    }

    suspend fun checklistItemsByList(list: ChecklistList): List<ChecklistItem> = mutex.withLock {
        checklistItems.filter { it.list == list }.sortedWith(compareBy({ it.isChecked }, { it.position }))
    }

    suspend fun createChecklistItem(item: ChecklistItem): ChecklistItem = mutex.withLock {
        val nextPosition = 1 + (checklistItems.filter { it.list == item.list }.maxOfOrNull { it.position } ?: 0)
        val saved = item.copy(id = nextChecklistItemId++, position = nextPosition)
        checklistItems.add(saved)
        saved
    }

    suspend fun updateChecklistItem(item: ChecklistItem): ChecklistItem = mutex.withLock {
        val index = checklistItems.indexOfFirst { it.id == item.id }
        checklistItems[index] = item
        item
    }

    suspend fun deleteChecklistItem(id: Int) = mutex.withLock {
        checklistItems.removeIf { it.id == id }
    }
}
