package com.truvo.pay.listener.network

import com.google.gson.Gson
import com.google.gson.JsonObject
import com.truvo.pay.listener.crypto.HmacSigner
import com.truvo.pay.listener.storage.DevicePreferences
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.util.concurrent.TimeUnit

class TruvoApiClient(private val prefs: DevicePreferences) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .build()

    private val gson = Gson()
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    /**
     * Pair device with Laravel backend using pairing token.
     */
    suspend fun pairDevice(pairingToken: String, deviceName: String): Result<Boolean> =
        withContext(Dispatchers.IO) {
            try {
                val payload = JsonObject().apply {
                    addProperty("pairing_token", pairingToken)
                    addProperty("device_name", deviceName)
                }

                val url = "${prefs.serverUrl}/api/v1/devices/pair"
                val body = payload.toString().toRequestBody(jsonMediaType)
                val request = Request.Builder()
                    .url(url)
                    .post(body)
                    .build()

                val response = client.newCall(request).execute()
                val responseBody = response.body?.string() ?: ""

                if (response.isSuccessful) {
                    val json = gson.fromJson(responseBody, JsonObject::class.java)
                    if (json.get("success")?.asBoolean == true) {
                        prefs.deviceId = json.get("device_id").asString
                        prefs.secretKey = json.get("secret_key").asString
                        prefs.isPaired = true
                        return@withContext Result.success(true)
                    }
                }
                Result.failure(Exception("Pairing failed: HTTP ${response.code} $responseBody"))
            } catch (e: Exception) {
                Result.failure(e)
            }
        }

    /**
     * Forward raw SMS / push notification to signed webhook.
     */
    suspend fun forwardSms(sender: String, bodyText: String, receivedAt: String): Result<Boolean> =
        withContext(Dispatchers.IO) {
            if (!prefs.isPaired) {
                return@withContext Result.failure(Exception("Device not paired yet."))
            }

            try {
                val payload = JsonObject().apply {
                    addProperty("sender", sender)
                    addProperty("body", bodyText)
                    addProperty("received_at", receivedAt)
                }
                val rawJson = payload.toString()
                val signature = HmacSigner.sign(rawJson, prefs.secretKey)

                val url = "${prefs.serverUrl}/api/v1/devices/sms-webhook"
                val requestBody = rawJson.toRequestBody(jsonMediaType)

                val request = Request.Builder()
                    .url(url)
                    .addHeader("Content-Type", "application/json")
                    .addHeader("X-Truvo-Device-Id", prefs.deviceId)
                    .addHeader("X-Truvo-Signature", signature)
                    .post(requestBody)
                    .build()

                val response = client.newCall(request).execute()
                if (response.isSuccessful) {
                    Result.success(true)
                } else {
                    Result.failure(Exception("Webhook rejected: HTTP ${response.code}"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }

    /**
     * Send periodic heartbeat & battery telemetry.
     */
    suspend fun sendTelemetry(batteryLevel: Int, networkType: String): Result<Boolean> =
        withContext(Dispatchers.IO) {
            if (!prefs.isPaired) return@withContext Result.failure(Exception("Not paired"))

            try {
                val payload = JsonObject().apply {
                    addProperty("battery_level", batteryLevel)
                    addProperty("network_type", networkType)
                    addProperty("app_version", "1.0.0")
                }
                val rawJson = payload.toString()
                val signature = HmacSigner.sign(rawJson, prefs.secretKey)

                val url = "${prefs.serverUrl}/api/v1/devices/telemetry"
                val request = Request.Builder()
                    .url(url)
                    .addHeader("Content-Type", "application/json")
                    .addHeader("X-Truvo-Device-Id", prefs.deviceId)
                    .addHeader("X-Truvo-Signature", signature)
                    .post(rawJson.toRequestBody(jsonMediaType))
                    .build()

                val response = client.newCall(request).execute()
                Result.success(response.isSuccessful)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
}
