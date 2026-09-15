package com.truvo.pay.listener.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.util.Log
import com.truvo.pay.listener.filter.PaymentSmsPreFilter
import com.truvo.pay.listener.network.TruvoApiClient
import com.truvo.pay.listener.storage.DevicePreferences
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class SmsBroadcastReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) {
            return
        }

        val prefs = DevicePreferences(context)
        if (!prefs.isPaired || !prefs.serviceActive) {
            return
        }

        val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent)
        if (messages.isNullOrEmpty()) return

        val sender = messages[0].originatingAddress ?: "Unknown"
        val body = messages.joinToString("") { it.messageBody ?: "" }

        if (!PaymentSmsPreFilter.isFinancial(sender, body)) {
            return
        }

        Log.i("SmsBroadcastReceiver", "Financial SMS intercepted from $sender: $body")

        val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US)
        val timestamp = sdf.format(Date(messages[0].timestampMillis))

        val apiClient = TruvoApiClient(prefs)
        val pendingResult = goAsync()

        CoroutineScope(Dispatchers.IO).launch {
            try {
                apiClient.forwardSms(sender, body, timestamp)
            } catch (e: Exception) {
                Log.e("SmsBroadcastReceiver", "Forwarding failed: ${e.message}")
            } finally {
                pendingResult.finish()
            }
        }
    }
}
