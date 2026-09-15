/**
 * Truvo Pay Client Checkout SDK
 * Zero-dependency embeddable checkout script
 * Usage:
 *   TruvoPay.checkout({
 *       order_id: 'ORD-12345',
 *       amount: 1500,
 *       currency: 'BDT',
 *       callback_url: 'https://mysite.com/payment/callback',
 *       mode: 'modal' // or 'redirect'
 *   });
 */
(function (window) {
    'use strict';

    var TruvoPay = {
        baseUrl: window.TRUVO_BASE_URL || window.location.origin,

        checkout: function (options) {
            if (!options || !options.order_id || !options.amount) {
                console.error('[TruvoPay] order_id and amount are required.');
                return;
            }

            var mode = options.mode || 'modal';
            var apiKey = options.api_key || window.TRUVO_PUBLIC_KEY;

            // 1. Create checkout session via API
            var endpoint = (options.base_url || this.baseUrl) + '/api/v1/checkout/create';
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': apiKey ? ('Bearer ' + apiKey) : ''
                },
                body: JSON.stringify({
                    order_id: options.order_id,
                    amount: options.amount,
                    currency: options.currency || 'BDT',
                    customer_name: options.customer_name,
                    customer_email: options.customer_email,
                    customer_phone: options.customer_phone,
                    redirect_url: options.callback_url || options.redirect_url,
                    callback_url: options.callback_url,
                    metadata: options.metadata || {}
                })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success || !data.payment_url) {
                    if (options.onError) {
                        options.onError(data);
                    } else {
                        alert(data.error || 'Failed to initialize Truvo Pay checkout.');
                    }
                    return;
                }

                if (mode === 'redirect') {
                    window.location.href = data.payment_url;
                } else {
                    TruvoPay.openModal(data.payment_url, options);
                }
            })
            .catch(function (err) {
                console.error('[TruvoPay] Checkout error:', err);
                if (options.onError) options.onError(err);
            });
        },

        openModal: function (paymentUrl, options) {
            // Create backdrop
            var backdrop = document.createElement('div');
            backdrop.id = 'truvo-modal-backdrop';
            backdrop.style.position = 'fixed';
            backdrop.style.top = '0';
            backdrop.style.left = '0';
            backdrop.style.width = '100vw';
            backdrop.style.height = '100vh';
            backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            backdrop.style.backdropFilter = 'blur(6px)';
            backdrop.style.zIndex = '999999';
            backdrop.style.display = 'flex';
            backdrop.style.alignItems = 'center';
            backdrop.style.justifyContent = 'center';
            backdrop.style.animation = 'truvoFadeIn 0.25s ease';

            // Create iframe container
            var iframe = document.createElement('iframe');
            iframe.src = paymentUrl;
            iframe.style.width = '100%';
            iframe.style.maxWidth = '540px';
            iframe.style.height = '90vh';
            iframe.style.maxHeight = '720px';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '20px';
            iframe.style.boxShadow = '0 25px 50px -12px rgba(0,0,0,0.5)';

            // Close button
            var closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '20px';
            closeBtn.style.right = '24px';
            closeBtn.style.background = 'rgba(255,255,255,0.1)';
            closeBtn.style.color = '#fff';
            closeBtn.style.border = 'none';
            closeBtn.style.borderRadius = '50%';
            closeBtn.style.width = '36px';
            closeBtn.style.height = '36px';
            closeBtn.style.fontSize = '24px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.onclick = function () {
                document.body.removeChild(backdrop);
                if (options.onClose) options.onClose();
            };

            backdrop.appendChild(closeBtn);
            backdrop.appendChild(iframe);
            document.body.appendChild(backdrop);
        }
    };

    window.TruvoPay = TruvoPay;
})(window);
