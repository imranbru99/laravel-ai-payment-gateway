package com.truvo.pay.listener.crypto

import java.nio.charset.StandardCharsets
import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec

object HmacSigner {
    /**
     * Compute HMAC-SHA256 signature of raw JSON payload using device secret key.
     */
    fun sign(payload: String, secretKey: String): String {
        return try {
            val keyBytes = secretKey.toByteArray(StandardCharsets.UTF_8)
            val signingKey = SecretKeySpec(keyBytes, "HmacSHA256")
            val mac = Mac.getInstance("HmacSHA256")
            mac.init(signingKey)
            val rawHmac = mac.doFinal(payload.toByteArray(StandardCharsets.UTF_8))
            bytesToHex(rawHmac)
        } catch (e: Exception) {
            ""
        }
    }

    private fun bytesToHex(bytes: ByteArray): String {
        val hexChars = CharArray(bytes.size * 2)
        val hexArray = "0123456789abcdef".toCharArray()
        for (j in bytes.indices) {
            val v = bytes[j].toInt() and 0xFF
            hexChars[j * 2] = hexArray[v ushr 4]
            hexChars[j * 2 + 1] = hexArray[v and 0x0F]
        }
        return String(hexChars)
    }
}
