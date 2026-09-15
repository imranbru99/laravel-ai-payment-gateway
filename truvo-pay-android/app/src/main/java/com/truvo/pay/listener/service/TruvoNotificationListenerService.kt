package com.truvo.pay.listener.service

import android.app.Notification
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import com.truvo.pay.listener.filter.PaymentSmsPreFilter
import com.truvo.pay.listener.network.TruvoApiClient
import com.truvo.pay.listener.storage.DevicePreferences
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class TruvoNotificationListenerService : NotificationListenerService() {
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private lateinit var prefs: DevicePreferences
    private lateinit var apiClient: TruvoApiClient

    override fun onCreate() {
        super.onCreate()
        prefs = DevicePreferences(this)
        apiClient = TruvoApiClient(prefs)
        Log.i(TAG, "TruvoNotificationListenerService started.")
    }

    override fun onNotificationPosted(sbn: StatusBarNotification?) {
        super.onNotificationPosted(sbn)

        if (sbn == null || !prefs.serviceActive || !prefs.isPaired) {
            return
        }

        val notification = sbn.notification ?: return
        val extras = notification.extras ?: return

        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString() ?: ""
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: ""
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString() ?: text

        val combinedBody = if (bigText.isNotEmpty()) bigText else text
        val sender = if (title.isNotEmpty()) title else sbn.packageName

        // Light on-device pre-filter
        if (!PaymentSmsPreFilter.isFinancial(sender, combinedBody)) {
            return
        }

        Log.d(TAG, "Financial notification detected from [$sender]: $combinedBody")

        val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US)
        val timestamp = sdf.format(Date(sbn.postTime))

        // Forward to Laravel HMAC signed webhook in coroutine
        serviceScope.launch {
            val result = apiClient.forwardSms(
                sender = sender,
                bodyText = combinedBody,
                receivedAt = timestamp
            )

            if (result.isSuccess) {
                Log.i(TAG, "Notification forwarded successfully to Truvo Pay server.")
            } else {
                Log.e(TAG, "Failed forwarding notification: ${result.exceptionOrNull()?.message}")
            }
        }
    }

    companion object {
        private const val TAG = "TruvoNotificationService"
    }
}
