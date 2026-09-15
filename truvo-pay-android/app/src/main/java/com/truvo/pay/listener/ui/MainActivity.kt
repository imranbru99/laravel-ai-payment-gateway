package com.truvo.pay.listener.ui

import android.content.ComponentName
import android.content.Intent
import android.os.Bundle
import android.provider.Settings
import android.text.TextUtils
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.truvo.pay.listener.databinding.ActivityMainBinding
import com.truvo.pay.listener.network.TruvoApiClient
import com.truvo.pay.listener.service.TruvoNotificationListenerService
import com.truvo.pay.listener.storage.DevicePreferences
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: DevicePreferences
    private lateinit var apiClient: TruvoApiClient

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        prefs = DevicePreferences(this)
        apiClient = TruvoApiClient(prefs)

        updateUiState()

        binding.btnPair.setOnClickListener {
            val serverUrl = binding.etServerUrl.text.toString().trim()
            val token = binding.etPairingToken.text.toString().trim()
            val deviceName = android.os.Build.MODEL

            if (serverUrl.isEmpty() || token.isEmpty()) {
                Toast.makeText(this, "Please enter Server URL and Pairing Token", Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }

            prefs.serverUrl = serverUrl
            binding.progressBar.visibility = View.VISIBLE
            binding.btnPair.isEnabled = false

            lifecycleScope.launch {
                val result = apiClient.pairDevice(token, deviceName)
                binding.progressBar.visibility = View.GONE
                binding.btnPair.isEnabled = true

                if (result.isSuccess) {
                    Toast.makeText(this@MainActivity, "Device paired successfully!", Toast.LENGTH_LONG).show()
                    updateUiState()
                    checkNotificationPermission()
                } else {
                    Toast.makeText(this@MainActivity, result.exceptionOrNull()?.message ?: "Pairing failed", Toast.LENGTH_LONG).show()
                }
            }
        }

        binding.btnTestPing.setOnClickListener {
            binding.progressBar.visibility = View.VISIBLE
            lifecycleScope.launch {
                val res = apiClient.sendTelemetry(100, "WiFi-Test")
                binding.progressBar.visibility = View.GONE
                if (res.isSuccess) {
                    Toast.makeText(this@MainActivity, "Server reached & HMAC signature verified!", Toast.LENGTH_SHORT).show()
                } else {
                    Toast.makeText(this@MainActivity, "Failed sending ping: ${res.exceptionOrNull()?.message}", Toast.LENGTH_LONG).show()
                }
            }
        }

        binding.switchService.setOnCheckedChangeListener { _, isChecked ->
            prefs.serviceActive = isChecked
            Toast.makeText(this, if (isChecked) "SMS Listener Active" else "SMS Listener Paused", Toast.LENGTH_SHORT).show()
        }

        binding.btnPermNotification.setOnClickListener {
            startActivity(Intent("android.settings.ACTION_NOTIFICATION_LISTENER_SETTINGS"))
        }

        binding.btnUnpair.setOnClickListener {
            prefs.clear()
            updateUiState()
            Toast.makeText(this, "Device unpaired", Toast.LENGTH_SHORT).show()
        }
    }

    override fun onResume() {
        super.onResume()
        checkNotificationPermission()
    }

    private fun updateUiState() {
        if (prefs.isPaired) {
            binding.layoutPairing.visibility = View.GONE
            binding.layoutDashboard.visibility = View.VISIBLE
            binding.tvDeviceId.text = "Device ID: ${prefs.deviceId}"
            binding.tvServerUrl.text = "Server: ${prefs.serverUrl}"
            binding.switchService.isChecked = prefs.serviceActive
        } else {
            binding.layoutPairing.visibility = View.VISIBLE
            binding.layoutDashboard.visibility = View.GONE
            binding.etServerUrl.setText(prefs.serverUrl)
        }
    }

    private fun checkNotificationPermission() {
        val cn = ComponentName(this, TruvoNotificationListenerService::class.java)
        val flat = Settings.Secure.getString(contentResolver, "enabled_notification_listeners")
        val isGranted = !TextUtils.isEmpty(flat) && flat.contains(cn.flattenToString())

        if (isGranted) {
            binding.cardPermissionAlert.visibility = View.GONE
        } else {
            binding.cardPermissionAlert.visibility = View.VISIBLE
        }
    }
}
