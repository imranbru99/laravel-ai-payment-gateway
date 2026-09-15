package com.truvo.pay.listener.filter

object PaymentSmsPreFilter {
    private val KNOWN_FINANCIAL_SENDERS = listOf(
        "BKASH", "16247",
        "NAGAD", "16167",
        "ROCKET", "16216", "DBBL",
        "UPAY", "16268",
        "CELLFIN", "IBBL",
        "CITYBANK", "BRACBANK", "EBL", "SCB", "HSBC", "BANK"
    )

    private val FINANCIAL_KEYWORDS = listOf(
        "tk", "bdt", "received", "credited", "cash in", "send money",
        "trxid", "txnid", "fee", "balance", "trans id", "deposit"
    )

    /**
     * Check whether incoming SMS or notification is financial/payment related.
     */
    fun isFinancial(sender: String, body: String): Boolean {
        val upperSender = sender.uppercase()
        val lowerBody = body.lowercase()

        // 1. Check sender
        val senderMatch = KNOWN_FINANCIAL_SENDERS.any { upperSender.contains(it) }
        if (senderMatch) return true

        // 2. Check content keywords
        var keywordHits = 0
        for (kw in FINANCIAL_KEYWORDS) {
            if (lowerBody.contains(kw)) {
                keywordHits++
            }
        }

        // If at least two financial keywords appear in the SMS, classify as financial
        return keywordHits >= 2
    }
}
