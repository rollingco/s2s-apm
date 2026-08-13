<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — interface example</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg: #14161c;
    --panel: #1c1f27;
    --panel-2: #21242e;
    --line: #2c3040;
    --text: #eef0f4;
    --text-dim: #9aa0b0;
    --accent: #6e7bff;
    --accent-soft: rgba(110,123,255,0.14);
    --success: #35c48a;
    --radius: 14px;
  }

  * { box-sizing: border-box; }

  html, body {
    margin: 0;
    padding: 0;
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', -apple-system, sans-serif;
    min-height: 100vh;
  }

  body {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
  }

  .stage-label {
    position: fixed;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-dim);
    background: var(--panel);
    border: 1px solid var(--line);
    padding: 6px 14px;
    border-radius: 999px;
  }

  .frame {
    width: 100%;
    max-width: 920px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 20px;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr 1fr;
    box-shadow: 0 30px 80px -20px rgba(0,0,0,0.55);
  }

  @media (max-width: 760px) {
    .frame { grid-template-columns: 1fr; }
    .summary { order: 2; border-top: 1px solid var(--line); border-left: none !important; }
  }

  /* ---- Left: order summary ---- */
  .summary {
    background: var(--panel-2);
    padding: 40px 36px;
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .brand-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 40px;
  }

  .brand-mark {
    width: 30px; height: 30px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--accent), #a2abff);
    flex-shrink: 0;
  }

  .brand-name {
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 15px;
    letter-spacing: -0.01em;
  }

  .eyebrow {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
  }

  .item-row {
    display: flex;
    gap: 14px;
    margin-bottom: 24px;
  }

  .item-thumb {
    width: 56px; height: 56px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2a2f42, #1a1d27);
    border: 1px solid var(--line);
    flex-shrink: 0;
  }

  .item-name {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 4px 0;
  }

  .item-desc {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0;
    line-height: 1.4;
  }

  .totals {
    border-top: 1px solid var(--line);
    padding-top: 18px;
    margin-top: 8px;
  }

  .total-line {
    display: flex;
    justify-content: space-between;
    font-size: 13.5px;
    color: var(--text-dim);
    margin-bottom: 8px;
  }

  .total-line.grand {
    color: var(--text);
    font-size: 20px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--line);
  }

  .footnote {
    font-size: 11.5px;
    color: var(--text-dim);
    margin-top: 28px;
    line-height: 1.5;
  }

  /* ---- Right: payment panel ---- */
  .payment {
    padding: 40px 36px;
    display: flex;
    flex-direction: column;
  }

  .payment h2 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 19px;
    font-weight: 600;
    margin: 0 0 4px 0;
  }

  .payment .sub {
    font-size: 13px;
    color: var(--text-dim);
    margin: 0 0 24px 0;
  }

  .method-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 24px;
  }

  .method-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
    background: transparent;
  }

  .method-option:hover {
    border-color: #3a3f52;
  }

  .method-option.selected {
    border-color: var(--accent);
    background: var(--accent-soft);
  }

  .method-radio {
    width: 18px; height: 18px;
    border-radius: 50%;
    border: 1.5px solid var(--line);
    flex-shrink: 0;
    display: grid;
    place-items: center;
    transition: border-color 0.15s ease;
  }

  .method-option.selected .method-radio {
    border-color: var(--accent);
  }

  .method-radio::after {
    content: '';
    width: 9px; height: 9px;
    border-radius: 50%;
    background: var(--accent);
    transform: scale(0);
    transition: transform 0.15s ease;
  }

  .method-option.selected .method-radio::after {
    transform: scale(1);
  }

  .method-icon {
    width: 26px;
    height: 26px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
  }

  .method-label {
    font-size: 14px;
    font-weight: 500;
    flex: 1;
  }

  .method-tag {
    font-size: 10.5px;
    color: var(--text-dim);
    border: 1px solid var(--line);
    padding: 2px 8px;
    border-radius: 999px;
  }

  /* Panel content that changes per method */
  .method-panel {
    border-top: 1px solid var(--line);
    padding-top: 22px;
    flex: 1;
  }

  .panel-block { display: none; }
  .panel-block.active { display: block; animation: fadein 0.2s ease; }

  @keyframes fadein {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Card fields (simple, non-functional demo) */
  .field { margin-bottom: 14px; }
  .field label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 6px;
  }
  .field input {
    width: 100%;
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 9px;
    padding: 11px 12px;
    color: var(--text);
    font-size: 13.5px;
    font-family: inherit;
  }
  .field-row { display: flex; gap: 12px; }
  .field-row .field { flex: 1; }

  /* Google Pay block */
  .gpay-note {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.55;
    margin-bottom: 20px;
  }

  .gpay-button {
    width: 100%;
    height: 48px;
    border-radius: 10px;
    border: none;
    background: #000;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14.5px;
    font-weight: 600;
    cursor: pointer;
    transition: filter 0.15s ease, transform 0.08s ease;
  }
  .gpay-button:hover { filter: brightness(1.15); }
  .gpay-button:active { transform: scale(0.985); }
  .gpay-button svg { height: 18px; width: auto; }

  /* PayPal placeholder block */
  .pp-note {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.55;
    margin-bottom: 20px;
  }
  .pp-button {
    width: 100%;
    height: 48px;
    border-radius: 10px;
    border: none;
    background: #ffc439;
    color: #003087;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
  }

  .pay-cta {
    margin-top: 20px;
    width: 100%;
    height: 50px;
    border-radius: 10px;
    border: none;
    background: var(--accent);
    color: #fff;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: filter 0.15s ease, transform 0.08s ease;
  }
  .pay-cta:hover { filter: brightness(1.08); }
  .pay-cta:active { transform: scale(0.99); }

  .secure-row {
    display: flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
    margin-top: 16px;
    font-size: 11.5px;
    color: var(--text-dim);
  }

  /* ---- Google Pay sheet simulation (modal) ---- */
  .overlay {
    position: fixed;
    inset: 0;
    background: rgba(6,7,10,0.6);
    display: none;
    align-items: flex-end;
    justify-content: center;
    z-index: 50;
    backdrop-filter: blur(2px);
  }
  .overlay.show { display: flex; animation: fadein 0.15s ease; }

  .gsheet {
    width: 100%;
    max-width: 380px;
    background: #fff;
    color: #1a1a1a;
    border-radius: 20px 20px 0 0;
    padding: 22px 22px 28px;
    font-family: 'Inter', sans-serif;
    animation: slideup 0.25s ease;
  }

  @media (min-width: 480px) {
    .gsheet { border-radius: 20px; margin-bottom: 40px; }
  }

  @keyframes slideup {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  .gsheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
  }
  .gsheet-title { font-size: 14px; font-weight: 600; display:flex; align-items:center; gap:8px; }
  .gsheet-close {
    background: #f1f1f1;
    border: none;
    width: 26px; height: 26px;
    border-radius: 50%;
    color: #555;
    cursor: pointer;
    font-size: 14px;
  }

  .gsheet-card {
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid #e4e4e4;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 16px;
  }
  .gsheet-card-icon {
    width: 34px; height: 22px;
    border-radius: 4px;
    background: linear-gradient(135deg, #ff9966, #ff5e62);
  }
  .gsheet-card-label { font-size: 13.5px; font-weight: 600; }
  .gsheet-card-sub { font-size: 11.5px; color: #888; }

  .gsheet-total {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #444;
    margin-bottom: 20px;
  }
  .gsheet-total b { color: #111; font-size: 15px; }

  .gsheet-confirm {
    width: 100%;
    height: 46px;
    border: none;
    border-radius: 24px;
    background: #000;
    color: #fff;
    font-weight: 600;
    font-size: 14.5px;
    cursor: pointer;
  }

  .gsheet-status {
    text-align: center;
    padding: 6px 0 0;
    font-size: 12px;
    color: #999;
    display: none;
  }
  .gsheet-status.show { display: block; }

  .spinner {
    width: 16px; height: 16px;
    border: 2px solid #ddd;
    border-top-color: #333;
    border-radius: 50%;
    display: inline-block;
    vertical-align: -3px;
    margin-right: 6px;
    animation: spin 0.7s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* Success overlay after confirm */
  .success-panel {
    display: none;
    text-align: center;
    padding: 6px 0 4px;
  }
  .success-panel.show { display: block; animation: fadein 0.25s ease; }
  .success-check {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: #e6f9f1;
    display: grid;
    place-items: center;
    margin: 0 auto 14px;
  }
  .success-panel h3 { font-size: 15px; margin: 0 0 4px; }
  .success-panel p { font-size: 12.5px; color: #888; margin: 0; }

</style>
</head>
<body>

<div class="stage-label">Demo mockup · not the real leogcltd Checkout</div>

<div class="frame">

  <!-- ===== LEFT: ORDER SUMMARY ===== -->
  <div class="summary">
    <div>
      <div class="brand-row">
        <div class="brand-mark"></div>
        <div class="brand-name">Merchant Store</div>
      </div>

      <div class="eyebrow">Order #ORD-88213</div>

      <div class="item-row">
        <div class="item-thumb"></div>
        <div>
          <p class="item-name">Pro annual subscription</p>
          <p class="item-desc">Renews automatically every year.<br>Cancel anytime from your account.</p>
        </div>
      </div>

      <div class="totals">
        <div class="total-line"><span>Subtotal</span><span>$99.00</span></div>
        <div class="total-line"><span>Tax</span><span>$3.99</span></div>
        <div class="total-line grand"><span>Total due</span><span id="grand-total">$102.99</span></div>
      </div>
    </div>

    <p class="footnote">This is a demo mockup illustrating the Google Pay selection flow on a checkout page. Data, amounts, and the brand are placeholders.</p>
  </div>

  <!-- ===== RIGHT: PAYMENT PANEL ===== -->
  <div class="payment">
    <h2>Payment method</h2>
    <p class="sub">Choose how you'd like to pay for this order</p>

    <div class="method-list">

      <div class="method-option" data-method="card" onclick="selectMethod('card')">
        <div class="method-radio"></div>
        <div class="method-icon">
          <svg width="24" height="16" viewBox="0 0 24 16"><rect width="24" height="16" rx="3" fill="#3a3f52"/><rect y="4" width="24" height="3" fill="#6e7bff"/></svg>
        </div>
        <div class="method-label">Credit or debit card</div>
        <div class="method-tag">Visa · MC</div>
      </div>

      <div class="method-option selected" data-method="googlepay" onclick="selectMethod('googlepay')">
        <div class="method-radio"></div>
        <div class="method-icon">
          <svg viewBox="0 0 48 20" width="26" height="12">
            <text x="0" y="16" font-family="Arial, sans-serif" font-size="16" font-weight="600" fill="#eef0f4">G Pay</text>
          </svg>
        </div>
        <div class="method-label">Google Pay</div>
        <div class="method-tag">Fast</div>
      </div>

      <div class="method-option" data-method="paypal" onclick="selectMethod('paypal')">
        <div class="method-radio"></div>
        <div class="method-icon">
          <svg viewBox="0 0 48 20" width="26" height="12">
            <text x="0" y="16" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#4d95ff">Pay<tspan fill="#7ea6ff">Pal</tspan></text>
          </svg>
        </div>
        <div class="method-label">PayPal</div>
        <div class="method-tag"></div>
      </div>

    </div>

    <div class="method-panel">

      <!-- Card block -->
      <div class="panel-block" id="panel-card">
        <div class="field">
          <label>Card number</label>
          <input type="text" placeholder="4111 1111 1111 1111" disabled>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Expiry date</label>
            <input type="text" placeholder="MM/YY" disabled>
          </div>
          <div class="field">
            <label>CVV</label>
            <input type="text" placeholder="•••" disabled>
          </div>
        </div>
        <button class="pay-cta" onclick="fakeCardPay()">Pay $102.99</button>
      </div>

      <!-- Google Pay block -->
      <div class="panel-block active" id="panel-googlepay">
        <p class="gpay-note">Payment goes through the method saved in your Google Account. Card details aren't shared with the merchant directly — Google Pay generates a one-time payment token instead.</p>
        <button class="gpay-button" onclick="openGpaySheet()">
          <svg viewBox="0 0 40 17" xmlns="http://www.w3.org/2000/svg">
            <text x="0" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="500" fill="#fff">Pay with </text>
            <text x="46" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#4285F4">G</text>
            <text x="55" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#EA4335">o</text>
            <text x="63" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#FBBC05">o</text>
            <text x="71" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#4285F4">g</text>
            <text x="79" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#34A853">l</text>
            <text x="83" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="700" fill="#EA4335">e</text>
            <text x="93" y="14" font-family="Arial, sans-serif" font-size="15" font-weight="500" fill="#fff"> Pay</text>
          </svg>
        </button>
        <div class="secure-row">🔒 Secured by Google Pay API tokenization</div>
      </div>

      <!-- PayPal block -->
      <div class="panel-block" id="panel-paypal">
        <p class="pp-note">You'll be redirected to PayPal to sign in and confirm the payment, then sent back to the merchant.</p>
        <button class="pp-button" onclick="fakeCardPay()">Continue with PayPal</button>
      </div>

    </div>
  </div>

</div>

<!-- ===== Google Pay sheet simulation ===== -->
<div class="overlay" id="gpay-overlay">
  <div class="gsheet">

    <div id="gsheet-body">
      <div class="gsheet-header">
        <div class="gsheet-title">
          <svg viewBox="0 0 40 17" xmlns="http://www.w3.org/2000/svg">
            <text x="0" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#4285F4">G</text>
            <text x="9" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#EA4335">o</text>
            <text x="16" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#FBBC05">o</text>
            <text x="23" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#4285F4">g</text>
            <text x="31" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#34A853">l</text>
            <text x="35" y="14" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#EA4335">e</text>
          </svg>
          Pay
        </div>
        <button class="gsheet-close" onclick="closeGpaySheet()">✕</button>
      </div>

      <div class="gsheet-card">
        <div class="gsheet-card-icon"></div>
        <div>
          <div class="gsheet-card-label">Mastercard •••• 5179</div>
          <div class="gsheet-card-sub">Default in your Google Account</div>
        </div>
      </div>

      <div class="gsheet-total">
        <span>Merchant: Merchant Store</span>
        <b>$102.99</b>
      </div>

      <button class="gsheet-confirm" id="gsheet-confirm-btn" onclick="confirmGpay()">Confirm</button>
      <div class="gsheet-status" id="gsheet-status"><span class="spinner"></span>Generating payment token…</div>
    </div>

    <div class="success-panel" id="success-panel">
      <div class="success-check">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
          <path d="M5 13l4 4L19 7" stroke="#35c48a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h3>Token received</h3>
      <p>paymentMethodData sent to the merchant.<br>Next: a SALE request with brand=googlepay.</p>
    </div>

  </div>
</div>

<script>
function selectMethod(method) {
  document.querySelectorAll('.method-option').forEach(el => {
    el.classList.toggle('selected', el.dataset.method === method);
  });
  document.querySelectorAll('.panel-block').forEach(el => {
    el.classList.toggle('active', el.id === 'panel-' + method);
  });
}

function fakeCardPay() {
  // Demo button, no real payment processing.
}

function openGpaySheet() {
  document.getElementById('gpay-overlay').classList.add('show');
  document.getElementById('gsheet-body').style.display = 'block';
  document.getElementById('success-panel').classList.remove('show');
  document.getElementById('gsheet-status').classList.remove('show');
  document.getElementById('gsheet-confirm-btn').style.display = 'block';
}

function closeGpaySheet() {
  document.getElementById('gpay-overlay').classList.remove('show');
}

function confirmGpay() {
  document.getElementById('gsheet-confirm-btn').style.display = 'none';
  document.getElementById('gsheet-status').classList.add('show');

  setTimeout(() => {
    document.getElementById('gsheet-body').style.display = 'none';
    document.getElementById('success-panel').classList.add('show');
  }, 1100);

  // After the "token received" step — redirect to a real Google Pay page,
  // simulating the customer's return after confirming the payment.
  setTimeout(() => {
    window.location.href = 'https://pay.google.com/about/';
  }, 2400);
}

document.getElementById('gpay-overlay').addEventListener('click', (e) => {
  if (e.target.id === 'gpay-overlay') closeGpaySheet();
});
</script>

</body>
</html>