package com.truvo.pay.listener.storage

import android.content.Context
import android.content.SharedPreferences

class DevicePreferences(context: Context) {
    private val prefs: SharedPreferences =
        context.getSharedPreferences("truvo_pay_prefs", Context.MODE_PRIVATE)

    var serverUrl: String
        get() = prefs.getString(KEY_SERVER_URL, "https://pay.example.com") ?: "https://pay.example.com"
        set(value) = prefs.edit().putString(KEY_SERVER_URL, value.trimEnd('/')).apply()

    var deviceId: String
        get() = prefs.getString(KEY_DEVICE_ID, "") ?: ""
        set(value) = prefs.edit().putString(KEY_DEVICE_ID, value).apply()

    var secretKey: String
        get() = prefs.getString(KEY_SECRET_KEY, "") ?: ""
        set(value) = prefs.edit().putString(KEY_SECRET_KEY, value).apply()

    var isPaired: Boolean
        get() = prefs.getBoolean(KEY_IS_PAIRED, false) && deviceId.isNotEmpty() && secretKey.isNotEmpty()
        set(value) = prefs.edit().putBoolean(KEY_IS_PAIRED, value).apply()

    var serviceActive: Boolean
        get() = prefs.getBoolean(KEY_SERVICE_ACTIVE, true)
        set(value) = prefs.edit().putBoolean(KEY_SERVICE_ACTIVE, value).apply()

    fun clear() {
        prefs.edit().clear().apply()
    }

    companion object {
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_SECRET_KEY = "secret_key"
        private const val KEY_IS_PAIRED = "is_paired"
        private const val KEY_SERVICE_ACTIVE = "service_active"
    }
}
