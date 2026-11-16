package xyz.deplot.chicco.data.remote

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

/**
 * Every Yii2 REST response is wrapped as `{"data": ..., "meta": ...}`
 * (see rest/Serializer.php on the backend) — collections carry
 * `meta.pagination`, validation failures carry `meta.errors`.
 */
@Serializable
data class ApiEnvelope<T>(
    val data: T? = null,
    val meta: JsonElement? = null,
)
