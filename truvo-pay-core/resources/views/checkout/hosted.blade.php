<!DOCTYPE html>
<html lang="{{ $currentLocale ?? 'en' }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('truvo.checkout_title') }} — Truvo Pay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0B0F19;
            --card-bg: rgba(22, 30, 49, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary: #6366F1;
            --primary-hover: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --accent: #10B981;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
            --input-bg: rgba(15, 23, 42, 0.65);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', 'Noto Sans Bengali', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.18) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.12) 0px, transparent 50%);
        }

        .checkout-container {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 32px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px var(--primary-glow);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--card-border);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.25rem;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .brand-badge {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .lang-switch {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--card-border);
            transition: all 0.2s;
        }
        .lang-switch:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .order-summary {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-meta .label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .order-meta .order-id {
            font-size: 0.95rem;
            font-weight: 600;
            color: #e2e8f0;
            font-mono;
        }

        .amount-display {
            text-align: right;
        }

        .amount-display .amount {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .amount-display .currency {
            font-size: 0.85rem;
            color: var(--accent);
            font-weight: 700;
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .gateway-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .gateway-radio {
            display: none;
        }

        .gateway-card {
            border: 1.5px solid var(--card-border);
            background: rgba(15, 23, 42, 0.4);
            border-radius: 14px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 84px;
        }

        .gateway-card:hover {
            border-color: rgba(99, 102, 241, 0.5);
            background: rgba(99, 102, 241, 0.06);
            transform: translateY(-2px);
        }

        .gateway-radio:checked + .gateway-card {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.12);
            box-shadow: 0 0 16px var(--primary-glow);
        }

        .gateway-name {
            font-size: 0.88rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }

        .gateway-badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            align-self: flex-start;
        }

        .badge-manual {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-api {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .instructions-box {
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #cbd5e1;
            white-space: pre-line;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 12px var(--primary-glow);
        }

        .btn-pay {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 10px 20px -5px var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px var(--primary-glow);
        }

        .polling-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            color: #34d399;
            font-size: 0.82rem;
            font-weight: 600;
            margin-top: 16px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(52, 211, 153, 0.3);
            border-top-color: #34d399;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .security-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Top Branding & Language -->
        <div class="header-brand">
            <div class="brand-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="6" fill="#6366F1"/>
                    <path d="M7 12L10.5 15.5L17 8.5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Truvo<span style="color:#818cf8;">Pay</span></span>
                <span class="brand-badge">AI Verified</span>
            </div>
            <a href="?lang={{ ($currentLocale ?? 'en') === 'en' ? 'bn' : 'en' }}" class="lang-switch">
                {{ __('truvo.switch_language') }}
            </a>
        </div>

        <!-- Order Summary Card -->
        <div class="order-summary">
            <div class="order-meta">
                <div class="label">{{ __('truvo.order_id') }}</div>
                <div class="order-id">#{{ $transaction->merchant_order_id }}</div>
            </div>
            <div class="amount-display">
                <div class="label">{{ __('truvo.total_amount') }}</div>
                <div class="amount">
                    @if($transaction->currency === 'BDT') ৳ @elseif($transaction->currency === 'USD') $ @endif{{ number_format($transaction->amount, 2) }}
                    <span class="currency">{{ $transaction->currency }}</span>
                </div>
            </div>
        </div>

        <!-- Manual Instructions if submitted -->
        @if(session('manual_instructions') || ($selectedGateway && $selectedGateway->instructions))
            <div class="instructions-box" id="instructionsBox">
                <strong>{{ __('truvo.instructions') }}:</strong>
                {{ session('manual_instructions') ?? $selectedGateway->instructions }}
            </div>
        @endif

        <form action="{{ route('truvo.checkout.submit', ['reference' => $transaction->truvo_reference]) }}" method="POST" id="checkoutForm">
            @csrf
            <div class="section-title">{{ __('truvo.select_payment_method') }}</div>

            <!-- Gateway Selection Grid -->
            <div class="gateway-grid">
                @forelse($gateways as $gw)
                    @php
                        $isDriverManual = $gw->getDriver()?->isManual();
                        $isChecked = ($selectedGateway && $selectedGateway->id === $gw->id);
                    @endphp
                    <label>
                        <input type="radio" name="gateway_config_id" value="{{ $gw->id }}" class="gateway-radio" {{ $isChecked ? 'checked' : '' }} onchange="onGatewayChange('{{ addslashes($gw->instructions ?? '') }}', {{ $isDriverManual ? 'true' : 'false' }})">
                        <div class="gateway-card">
                            <div class="gateway-name">{{ $gw->display_name }}</div>
                            <span class="gateway-badge {{ $isDriverManual ? 'badge-manual' : 'badge-api' }}">
                                {{ $isDriverManual ? __('truvo.manual_ai') : __('truvo.instant_api') }}
                            </span>
                        </div>
                    </label>
                @empty
                    <div style="grid-column: span 2; text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 20px;">
                        No active payment gateways available for this currency/region.
                    </div>
                @endforelse
            </div>

            <!-- Sender Phone Number Field (for manual gateways) -->
            <div class="form-group" id="senderNumberGroup" style="{{ ($selectedGateway && $selectedGateway->getDriver()?->isManual()) ? 'display:block;' : 'display:none;' }}">
                <label class="form-label" for="sender_number">{{ __('truvo.send_from_number') }}</label>
                <input type="text" name="sender_number" id="sender_number" class="form-input" placeholder="{{ __('truvo.send_from_placeholder') }}" value="{{ $transaction->sender_number ?? '' }}">
            </div>

            <button type="submit" class="btn-pay" id="btnPay">
                <span>{{ __('truvo.proceed_to_pay') }}</span>
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

        <!-- Live AI Polling indicator for manual transactions -->
        <div class="polling-indicator" id="pollingIndicator" style="{{ session('manual_instructions') ? 'display:flex;' : 'display:none;' }}">
            <div class="spinner"></div>
            <span>{{ __('truvo.verifying_payment') }}</span>
        </div>

        <div class="security-footer">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <span>{{ __('truvo.secured_by') }}</span>
        </div>
    </div>

    <script>
        function onGatewayChange(instructions, isManual) {
            const senderGrp = document.getElementById('senderNumberGroup');
            if (isManual) {
                senderGrp.style.display = 'block';
            } else {
                senderGrp.style.display = 'none';
            }
        }

        // Real-time polling for AI SMS auto-verification
        const truvoRef = "{{ $transaction->truvo_reference }}";
        let pollInterval = setInterval(() => {
            fetch(`/pay/${truvoRef}/status`)
                .then(res => res.json())
                .then(data => {
                    if (data.is_paid) {
                        clearInterval(pollInterval);
                        document.getElementById('pollingIndicator').innerHTML = `
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ __('truvo.payment_successful') }}</span>
                        `;
                        if (data.redirect_url) {
                            setTimeout(() => {
                                window.location.href = data.redirect_url;
                            }, 1200);
                        }
                    }
                })
                .catch(() => {});
        }, 3000);
    </script>
</body>
</html>
