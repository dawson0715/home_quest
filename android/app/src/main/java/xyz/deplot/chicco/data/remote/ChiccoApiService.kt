package xyz.deplot.chicco.data.remote

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query
import xyz.deplot.chicco.data.model.ChecklistItem
import xyz.deplot.chicco.data.model.Diaper
import xyz.deplot.chicco.data.model.Feeding
import xyz.deplot.chicco.data.model.Pumping
import xyz.deplot.chicco.data.model.TodaySummary
import xyz.deplot.chicco.data.model.Weight

/**
 * Matches the actual Yii2 REST surface in chicco.local (English resource
 * names, no login endpoint — bearer tokens are provisioned out-of-band via
 * `./yii user/create`). Every response is wrapped in [ApiEnvelope].
 */
interface ChiccoApiService {

    @GET("today-summary")
    suspend fun todaySummary(
        @Query("child_id") childId: Int,
        @Query("date") date: String? = null,
    ): ApiEnvelope<TodaySummary>

    @GET("feedings")
    suspend fun feedings(
        @Query("child_id") childId: Int,
        @Query("date") date: String? = null,
    ): ApiEnvelope<List<Feeding>>

    @POST("feedings")
    suspend fun createFeeding(@Body request: Feeding): ApiEnvelope<Feeding>

    @PUT("feedings/{id}")
    suspend fun updateFeeding(@Path("id") id: Int, @Body request: Feeding): ApiEnvelope<Feeding>

    @DELETE("feedings/{id}")
    suspend fun deleteFeeding(@Path("id") id: Int): Response<Unit>

    @GET("pumpings")
    suspend fun pumpings(
        @Query("child_id") childId: Int,
        @Query("date") date: String? = null,
    ): ApiEnvelope<List<Pumping>>

    @POST("pumpings")
    suspend fun createPumping(@Body request: Pumping): ApiEnvelope<Pumping>

    @PUT("pumpings/{id}")
    suspend fun updatePumping(@Path("id") id: Int, @Body request: Pumping): ApiEnvelope<Pumping>

    @DELETE("pumpings/{id}")
    suspend fun deletePumping(@Path("id") id: Int): Response<Unit>

    @GET("weights")
    suspend fun weights(
        @Query("child_id") childId: Int,
        @Query("date") date: String? = null,
    ): ApiEnvelope<List<Weight>>

    @POST("weights")
    suspend fun createWeight(@Body request: Weight): ApiEnvelope<Weight>

    @PUT("weights/{id}")
    suspend fun updateWeight(@Path("id") id: Int, @Body request: Weight): ApiEnvelope<Weight>

    @DELETE("weights/{id}")
    suspend fun deleteWeight(@Path("id") id: Int): Response<Unit>

    @GET("diapers")
    suspend fun diapers(
        @Query("child_id") childId: Int,
        @Query("date") date: String? = null,
    ): ApiEnvelope<List<Diaper>>

    @POST("diapers")
    suspend fun createDiaper(@Body request: Diaper): ApiEnvelope<Diaper>

    @PUT("diapers/{id}")
    suspend fun updateDiaper(@Path("id") id: Int, @Body request: Diaper): ApiEnvelope<Diaper>

    @DELETE("diapers/{id}")
    suspend fun deleteDiaper(@Path("id") id: Int): Response<Unit>

    @GET("checklist-items")
    suspend fun checklistItems(@Query("list") list: String): ApiEnvelope<List<ChecklistItem>>

    @POST("checklist-items")
    suspend fun createChecklistItem(@Body request: ChecklistItem): ApiEnvelope<ChecklistItem>

    @PUT("checklist-items/{id}")
    suspend fun updateChecklistItem(@Path("id") id: Int, @Body request: ChecklistItem): ApiEnvelope<ChecklistItem>

    @DELETE("checklist-items/{id}")
    suspend fun deleteChecklistItem(@Path("id") id: Int): Response<Unit>
}
