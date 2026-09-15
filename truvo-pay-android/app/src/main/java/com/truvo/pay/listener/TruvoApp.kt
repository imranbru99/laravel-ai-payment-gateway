package com.truvo.pay.listener

import android.app.Application
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.truvo.pay.listener.service.HeartbeatWorker
import java.util.concurrent.TimeUnit

class TruvoApp : Application() {
    override fun onCreate() {
        super.onCreate()

        // Schedule periodic heartbeat telemetry worker (15 min interval)
        val heartbeatRequest = PeriodicWorkRequestBuilder<HeartbeatWorker>(15, TimeUnit.MINUTES)
            .build()

        WorkManager.getInstance(this).enqueueUniquePeriodicWork(
            "truvo_heartbeat_work",
            ExistingPeriodicWorkPolicy.KEEP,
            heartbeatRequest
        )
    }
}
