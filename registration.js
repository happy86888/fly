(() => {
  const plans = {
    '19800': {
      label: '機票里程實戰課＋一年一對一諮詢',
      price: 19800,
      transfer: 19785
    },
    '29800': {
      label: '完整課程＋美卡諮詢服務',
      price: 29800,
      transfer: 29785
    }
  };

  const qs = new URLSearchParams(location.search);
  const planInputs = [...document.querySelectorAll('input[name="course_plan"]')];
  const planCards = [...document.querySelectorAll('[data-plan-card]')];
  const form = document.getElementById('registrationForm');
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const formError = document.getElementById('formError');
  const paymentError = document.getElementById('paymentError');
  const cardPaymentError = document.getElementById('cardPaymentError');
  const backButton = document.getElementById('backToStep1');
  const finishButton = document.getElementById('finishRegistration');
  const startCardPayment = document.getElementById('startCardPayment');
  const paymentLoading = document.getElementById('paymentLoading');
  const paymentDate = document.getElementById('paymentDate');
  const last5 = document.getElementById('last5');
  const paymentMethods = [...document.querySelectorAll('[data-payment-method]')];
  const cardPanel = document.getElementById('cardPanel');
  const bankPanel = document.getElementById('bankPanel');
  const sandboxNotice = document.getElementById('sandboxNotice');
  let selectedPlan = null;
  let selectedPaymentMethod = 'card';

  const formatMoney = n => `NT$${Number(n).toLocaleString('en-US')}`;

  function setPlan(value) {
    if (!plans[value]) return;
    selectedPlan = value;
    planInputs.forEach(input => { input.checked = input.value === value; });
    planCards.forEach(card => card.classList.toggle('selected', card.dataset.planCard === value));
  }

  function setPaymentMethod(method) {
    selectedPaymentMethod = method;
    paymentMethods.forEach(button => {
      const active = button.dataset.paymentMethod === method;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    cardPanel.hidden = method !== 'card';
    bankPanel.hidden = method !== 'bank';
  }

  const requestedPlan = qs.get('plan');
  setPlan(plans[requestedPlan] ? requestedPlan : '19800');
  setPaymentMethod(qs.get('pay') === 'bank' ? 'bank' : 'card');

  planInputs.forEach(input => input.addEventListener('change', () => setPlan(input.value)));
  planCards.forEach(card => card.addEventListener('click', () => setPlan(card.dataset.planCard)));
  paymentMethods.forEach(button => button.addEventListener('click', () => setPaymentMethod(button.dataset.paymentMethod)));

  fetch('payment/public-config.php', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : null)
    .then(data => { if (data && data.sandbox) sandboxNotice.hidden = false; })
    .catch(() => {});

  function validContact() {
    const name = document.getElementById('name');
    const phone = document.getElementById('phone');
    const email = document.getElementById('email');
    if (!selectedPlan) return '請先選擇課程方案。';
    if (!name.value.trim()) return '請填寫稱呼。';
    if (!phone.value.trim()) return '請填寫手機。';
    if (!email.value.trim() || !email.validity.valid) return '請填寫正確的 Email。';
    return '';
  }

  function updateSummary() {
    const plan = plans[selectedPlan];
    document.getElementById('summaryPlan').textContent = plan.label;
    document.getElementById('summaryPrice').textContent = formatMoney(plan.price);
  }

  function registrationPayload() {
    return {
      plan: selectedPlan,
      name: document.getElementById('name').value.trim(),
      phone: document.getElementById('phone').value.trim(),
      email: document.getElementById('email').value.trim(),
      note: document.getElementById('note').value.trim(),
      contract: document.getElementById('contract').checked
    };
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    const error = validContact();
    formError.textContent = error;
    if (error) return;
    updateSummary();
    step1.hidden = true;
    step2.hidden = false;
    document.querySelector('[data-step-dot="1"]').classList.remove('active');
    document.querySelector('[data-step-dot="2"]').classList.add('active');
    if (!paymentDate.value) paymentDate.value = new Date().toISOString().slice(0, 10);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  backButton.addEventListener('click', () => {
    step2.hidden = true;
    step1.hidden = false;
    document.querySelector('[data-step-dot="2"]').classList.remove('active');
    document.querySelector('[data-step-dot="1"]').classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  startCardPayment.addEventListener('click', async () => {
    const error = validContact();
    if (error) {
      cardPaymentError.textContent = error;
      return;
    }
    cardPaymentError.textContent = '';
    startCardPayment.disabled = true;
    paymentLoading.hidden = false;

    try {
      const response = await fetch('payment/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(registrationPayload())
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success || !data.payment_url) {
        throw new Error(data.message || '目前無法建立刷卡訂單。');
      }
      location.href = data.payment_url;
    } catch (err) {
      cardPaymentError.textContent = err && err.message ? err.message : '付款服務暫時無法使用，請稍後再試或改用銀行轉帳。';
      startCardPayment.disabled = false;
      paymentLoading.hidden = true;
    }
  });

  finishButton.addEventListener('click', () => {
    const digits = last5.value.replace(/\D/g, '').slice(0, 5);
    last5.value = digits;
    if (!paymentDate.value) {
      paymentError.textContent = '請填寫付款日期。';
      return;
    }
    if (digits.length !== 5) {
      paymentError.textContent = '請填寫匯款帳號末五碼。';
      return;
    }
    paymentError.textContent = '';
    const plan = plans[selectedPlan];
    const payload = registrationPayload();
    const contract = payload.contract ? '需要' : '不需要';
    const message = [
      '老師您好，我要回報課程報名資料：',
      `方案：${plan.label}（${formatMoney(plan.price)}）`,
      `稱呼：${payload.name}`,
      `手機：${payload.phone}`,
      `Email：${payload.email}`,
      `付款方式：銀行轉帳`,
      `付款日期：${paymentDate.value}`,
      `匯款末五碼：${digits}`,
      `服務契約：${contract}`,
      payload.note ? `備註：${payload.note}` : '',
      '',
      '報名成功，麻煩協助確認，謝謝。'
    ].filter(Boolean).join('\n');
    const url = `https://line.me/R/oaMessage/@tpq5223k/?${encodeURIComponent(message)}`;
    window.location.href = url;
  });
})();
