/**
 * سامانه آموزشی هوشمند | منطق ورود متمرکز (کد ملی و پشتیبانی)
 * Centered Login Logic with National Code validation & Support Contact
 */

document.addEventListener('DOMContentLoaded', () => {
  // ------------------------------------------------------------------------
  // ۱. مدیریت انتخابگر نقش (ورود والدین / ورود معلم)
  // ------------------------------------------------------------------------
  const loginCard = document.querySelector('.login-card');
  const roleButtons = document.querySelectorAll('.role-btn');
  const loginPanels = document.querySelectorAll('.login-panel');

  function setActiveRole(role) {
    if (!loginCard) return;
    loginCard.setAttribute('data-active-role', role);

    roleButtons.forEach(btn => {
      if (btn.getAttribute('data-role') === role) {
        btn.classList.add('active'); btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.classList.remove('active'); btn.setAttribute('aria-pressed', 'false');
      }
    });

    loginPanels.forEach(panel => {
      if (panel.getAttribute('data-role-panel') === role) {
        panel.classList.add('active');
        const input = panel.querySelector('input');
        if (input) input.focus();
      } else {
        panel.classList.remove('active');
      }
    });
  }

  roleButtons.forEach(button => {
    button.addEventListener('click', () => {
      const selectedRole = button.getAttribute('data-role');
      setActiveRole(selectedRole);
    });
  });

  // ------------------------------------------------------------------------
  // ۲. مدیریت و پالایش فیلد کد ملی (تبدیل اعداد فارسی و محدود به ارقام)
  // ------------------------------------------------------------------------
  function normalizeToEnglishDigits(str) {
    const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
    const arabicNumbers  = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
    for (let i = 0; i < 10; i++) {
      str = str.replace(persianNumbers[i], i).replace(arabicNumbers[i], i);
    }
    return str;
  }

  const nationalInputs = document.querySelectorAll('.input-national-code');
  nationalInputs.forEach(input => {
    input.addEventListener('input', (e) => {
      // فقط اعداد مجاز هستند
      let normalized = normalizeToEnglishDigits(e.target.value);
      normalized = normalized.replace(/\D/g, '');
      if (normalized.length > 10) normalized = normalized.slice(0, 10);
      e.target.value = normalized;
    });
  });

  let challenge = '';
  const verifyPanel = document.getElementById('verificationPanel');
  const formsWrapper = document.querySelector('.forms-wrapper');
  const roleSelector = document.querySelector('.role-selector-wrapper');
  const request = async (path, data) => {
    const response = await fetch(MYR_LOGIN.rest + path, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data) });
    const out = await response.json();
    if (!response.ok) throw new Error(out.message || 'ارتباط با سامانه برقرار نشد.');
    return out;
  };
  ['parent', 'teacher'].forEach(role => {
    const form = document.getElementById(role + 'LoginForm');
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const nationalId = document.getElementById(role + 'NationalCode').value.trim();
      if (!/^\d{10}$/.test(nationalId)) { showToast('کد ملی باید دقیقاً ۱۰ رقم باشد.', true); return; }
      const button = form.querySelector('[type=submit]');
      button.disabled = true;
      try {
        const out = await request('auth/identify', {national_id:nationalId, role});
        challenge = out.challenge;
        formsWrapper.hidden = true; roleSelector.hidden = true; verifyPanel.hidden = false;
        document.getElementById('verificationCaption').textContent = 'کد تأیید شماره همراه ' + out.masked_phone + ' را وارد کنید.';
        const notice = document.getElementById('localCodeNotice');
        notice.hidden = !out.development_code;
        notice.textContent = out.development_code ? 'نسخه محلی — کد آزمایشی: ' + out.development_code : '';
        document.getElementById('verificationCode').focus();
      } catch (error) { showToast(error.message, true); }
      finally { button.disabled = false; }
    });
  });
  document.getElementById('verificationCode').addEventListener('input', event => { event.target.value = normalizeToEnglishDigits(event.target.value).replace(/\D/g, '').slice(0,5); });
  document.getElementById('verificationForm').addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('[type=submit]');
    const errorBox = document.getElementById('verificationError');
    button.disabled = true; errorBox.textContent = '';
    try {
      const out = await request('auth/verify', {challenge, code:document.getElementById('verificationCode').value, remember:document.getElementById('rememberDevice').checked});
      window.location.assign(out.redirect);
    } catch(error) { errorBox.textContent = error.message; button.disabled = false; }
  });
  document.getElementById('changeIdentity').addEventListener('click', () => {
    challenge = ''; verifyPanel.hidden = true; formsWrapper.hidden = false; roleSelector.hidden = false;
    document.getElementById('verificationForm').reset(); document.getElementById('verificationError').textContent = '';
  });

  // ------------------------------------------------------------------------
  // ۳. مدیریت تیزر اختصاصی نشان‌واره برند (Logo-Only Splash Teaser)
  // ------------------------------------------------------------------------
  const splashOverlay = document.getElementById('logoSplashOverlay');
  const splashLogoImg = document.getElementById('splashLogoImg');
  const replayTeaserBtn = document.getElementById('replayTeaserBtn');

  let teaserTimeout = null;

  function hideTeaser() {
    if (splashOverlay && !splashOverlay.classList.contains('hidden')) {
      splashOverlay.classList.add('hidden');
    }
  }

  function playTeaser() {
    if (splashOverlay) {
      if (teaserTimeout) clearTimeout(teaserTimeout);
      splashOverlay.classList.remove('hidden');

      const card = splashOverlay.querySelector('.splash-logo-card');
      const sheen = splashOverlay.querySelector('.splash-light-sheen');
      
      if (card) {
        card.style.animation = 'none';
        card.offsetHeight; /* اعمال مجدد انیمیشن */
        card.style.animation = '';
      }

      if (sheen) {
        sheen.style.animation = 'none';
        sheen.offsetHeight;
        sheen.style.animation = '';
      }

      teaserTimeout = setTimeout(hideTeaser, 2700);
    }
  }

  // اجرای تیزر در بارگذاری اولیه و محو شدن پس از ۲.۷ ثانیه
  teaserTimeout = setTimeout(hideTeaser, 2700);

  // رد کردن آنی تیزر با کلیک روی هر کجای صفحه
  if (splashOverlay) {
    splashOverlay.addEventListener('click', hideTeaser);
  }

  // کلید بازپخش تیزر
  if (replayTeaserBtn) {
    replayTeaserBtn.addEventListener('click', playTeaser);
  }

  // ------------------------------------------------------------------------
  // ۴. مدیریت تعویض نشان‌واره برند
  // ------------------------------------------------------------------------
  const logoImage = document.getElementById('brandLogoImg');
  const logoTrigger = document.getElementById('logoChangeTrigger');
  const availableLogos = [
    { src: 'logo.png', label: 'تم تمشکی (پیش‌فرض)' },
    { src: 'Asset 2@3x.png', label: 'تم یاسی' },
    { src: 'Asset 3@3x.png', label: 'تم سبز سدری' },
    { src: 'Asset 8@3x.png', label: 'تم یاسی با متن تیره' },
    { src: 'Asset 5@3x.png', label: 'تم سبز با متن تیره' }
  ];
  let currentLogoIndex = 0;

  if (logoTrigger) {
    logoTrigger.addEventListener('click', () => {
      currentLogoIndex = (currentLogoIndex + 1) % availableLogos.length;
      const newLogoSrc = MYR_LOGIN.assets + availableLogos[currentLogoIndex].src;
      if (logoImage) logoImage.src = newLogoSrc;
      if (splashLogoImg) splashLogoImg.src = newLogoSrc;
      showToast(`رنگ نشان‌واره به: «${availableLogos[currentLogoIndex].label}» تغییر یافت.`);
    });
  }

  // ------------------------------------------------------------------------
  // ۴. ابزار زنده تنظیم و تست پالت رنگی برند (Live Palette Tuner)
  // ------------------------------------------------------------------------
  const tunerToggleBtn = document.getElementById('tunerToggleBtn');
  const tunerCard = document.getElementById('tunerCard');
  const tunerClose = document.getElementById('tunerClose');

  if (tunerToggleBtn && tunerCard) {
    tunerToggleBtn.addEventListener('click', () => {
      tunerCard.classList.toggle('open');
    });
  }

  if (tunerClose && tunerCard) {
    tunerClose.addEventListener('click', () => {
      tunerCard.classList.remove('open');
    });
  }

  const inputPrimary = document.getElementById('inputPrimary');
  const textPrimary = document.getElementById('textPrimary');
  const inputSecondary = document.getElementById('inputSecondary');
  const textSecondary = document.getElementById('textSecondary');
  const inputBg = document.getElementById('inputBg');
  const textBg = document.getElementById('textBg');

  function hexToRgb(hex) {
    hex = hex.replace(/^#/, '');
    if (hex.length === 3) {
      hex = hex.split('').map(c => c + c).join('');
    }
    const num = parseInt(hex, 16);
    return isNaN(num) ? null : `${(num >> 16) & 255}, ${(num >> 8) & 255}, ${num & 255}`;
  }

  function applyBrandColors(primary, secondary, background) {
    const root = document.documentElement;

    if (primary) {
      root.style.setProperty('--color-primary', primary);
      const rgb = hexToRgb(primary);
      if (rgb) root.style.setProperty('--color-primary-rgb', rgb);
      if (inputPrimary) inputPrimary.value = primary;
      if (textPrimary) textPrimary.value = primary.toUpperCase();
    }

    if (secondary) {
      root.style.setProperty('--color-secondary', secondary);
      const rgbSec = hexToRgb(secondary);
      if (rgbSec) root.style.setProperty('--color-secondary-rgb', rgbSec);
      if (inputSecondary) inputSecondary.value = secondary;
      if (textSecondary) textSecondary.value = secondary.toUpperCase();
    }

    if (background) {
      root.style.setProperty('--color-background', background);
      const rgbBg = hexToRgb(background);
      if (rgbBg) root.style.setProperty('--color-background-rgb', rgbBg);
      if (inputBg) inputBg.value = background;
      if (textBg) textBg.value = background.toUpperCase();
    }
  }

  function syncColorInput(picker, text, applyFn) {
    if (!picker || !text) return;

    picker.addEventListener('input', (e) => {
      const val = e.target.value;
      text.value = val.toUpperCase();
      applyFn(val);
    });

    text.addEventListener('change', (e) => {
      let val = e.target.value.trim();
      if (!val.startsWith('#')) val = '#' + val;
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        picker.value = val;
        applyFn(val);
      }
    });
  }

  syncColorInput(inputPrimary, textPrimary, (c) => applyBrandColors(c, null, null));
  syncColorInput(inputSecondary, textSecondary, (c) => applyBrandColors(null, c, null));
  syncColorInput(inputBg, textBg, (c) => applyBrandColors(null, null, c));

  const presetButtons = document.querySelectorAll('.btn-preset');
  presetButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const p = btn.getAttribute('data-p');
      const s = btn.getAttribute('data-s');
      const b = btn.getAttribute('data-b');
      applyBrandColors(p, s, b);
      showToast(`پالت رنگی «${btn.innerText.trim()}» اعمال گردید.`);
    });
  });

  const copyCssBtn = document.getElementById('copyCssBtn');
  if (copyCssBtn) {
    copyCssBtn.addEventListener('click', () => {
      const p = textPrimary ? textPrimary.value : '#D45D79';
      const s = textSecondary ? textSecondary.value : '#E8998D';
      const b = textBg ? textBg.value : '#FDF9F7';
      const cssCode = `:root {\n  --color-primary: ${p};\n  --color-secondary: ${s};\n  --color-background: ${b};\n}`;

      navigator.clipboard.writeText(cssCode).then(() => {
        showToast('کد استایل پالت در کلیپ‌بورد کپی شد.');
      }).catch(() => {
        showToast('خطا در کپی مقادیر');
      });
    });
  }

  // ------------------------------------------------------------------------
  // ۵. توابع پیام اعلان (Toast Notification)
  // ------------------------------------------------------------------------
  const toast = document.getElementById('toastNotice');
  let toastTimer = null;

  function showToast(message, isError = false) {
    if (!toast) return;
    if (toastTimer) clearTimeout(toastTimer);

    toast.textContent = message;
    if (isError) {
      toast.style.backgroundColor = '#EF4444';
    } else {
      toast.style.backgroundColor = '#1E293B';
    }

    toast.classList.add('show');
    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 3500);
  }
});
