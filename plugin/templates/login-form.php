<?php defined('ABSPATH') || exit; ?>


  <!-- پرده تیزر اختصاصی و باشکوه نشان‌واره برند (Pure Logo Spotlight Teaser) -->
  <div id="logoSplashOverlay" class="splash-overlay" title="برای ورود مستقیم کلیک کنید">
    <div class="splash-stage">
      <div class="splash-ambient-aura"></div>
      <div class="splash-logo-card">
        <img id="splashLogoImg" src="<?php echo esc_url(MYR_URL . 'assets/login/logo.png'); ?>" alt="نشان‌واره برند" class="splash-logo-img">
        <div class="splash-light-sheen"></div>
      </div>
    </div>
  </div>

  <!-- نوار بالای صفحه با نمایش شفاف شماره پشتیبان و بازپخش تیزر -->
  <header class="top-bar">
    <div class="brand-badge">
      <span class="pulse-dot"></span>
      <span>سامانه جامع آموزشی</span>
    </div>

    <div class="top-actions-group">
      <!-- دکمه پخش مجدد تیزر لوگو -->
      <button type="button" id="replayTeaserBtn" class="btn-replay-teaser" title="مشاهده مجدد تیزر نشان‌واره">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
          <polygon points="5 3 19 12 5 21 5 3"></polygon>
        </svg>
        <span>پخش تیزر لوگو</span>
      </button>

      <!-- بخش شماره پشتیبان مستقیم -->
      <div class="top-support">
        <a href="#" aria-disabled="true" class="support-badge-link" title="تماس با پشتیبانی">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <span>شماره پشتیبان:</span>
          <strong class="support-number">پشتیبانی نمایشی</strong>
        </a>
      </div>
    </div>
  </header>

  <!-- کانتینر وسط‌چین برای بخش ورود (Centered Login Screen) -->
  <main class="main-wrapper">
    <div class="login-card" data-active-role="parent">
      
      <!-- نشان‌واره و تیتر سامانه -->
      <div class="card-brand-header">
        <div class="logo-wrapper">
          <img id="brandLogoImg" src="<?php echo esc_url(MYR_URL . 'assets/login/logo.png'); ?>" alt="نشان‌واره برند" class="logo-brand-img">
          <button type="button" id="logoChangeTrigger" class="logo-switch-btn" title="تغییر رنگ لوگو از دارایی‌های موجود">
            🔄
          </button>
        </div>
        <h1 class="card-title">ورود به سامانه آموزشی</h1>
        <p class="card-subtitle">لطفاً برای ورود به پنل، نقش خود را انتخاب کرده و کد ملی خود را وارد نمایید</p>
      </div>

      <!-- کلیدهای انتخاب نقش: ورود والدین / ورود معلم -->
      <div class="role-selector-wrapper">
        <div class="segmented-control">
          <div class="segmented-glider"></div>
          
          <!-- کلید ۱: ورود والدین -->
          <button type="button" class="role-btn active" data-role="parent">
            <span class="role-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </span>
            <span>ورود والدین</span>
          </button>

          <!-- کلید ۲: ورود معلم -->
          <button type="button" class="role-btn" data-role="teacher">
            <span class="role-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
              </svg>
            </span>
            <span>ورود معلم</span>
          </button>
        </div>
      </div>

      <!-- فرم ورود فقط با دریافت کد ملی -->
      <div class="forms-wrapper">

        <!-- پنل ورود والدین (فقط کد ملی) -->
        <div class="login-panel active" data-role-panel="parent">
          <form id="parentLoginForm" autocomplete="off">
            <div class="form-group">
              <label for="parentNationalCode" class="form-label">
                <span>کد ملی ولی یا دانش‌آموز</span>
                <span class="label-hint">(۱۰ رقم بدون خط تیره)</span>
              </label>
              <div class="input-wrapper">
                <input 
                  type="text" 
                  id="parentNationalCode" 
                  class="input-control input-national-code" 
                  placeholder="مثال: ۰۰۱۲۳۴۵۶۷۸" 
                  maxlength="10" 
                  inputmode="numeric" 
                  required 
                  autofocus>
                <span class="input-icon-left">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                    <line x1="7" y1="8" x2="17" y2="8"></line>
                    <line x1="7" y1="12" x2="13" y2="12"></line>
                  </svg>
                </span>
              </div>
            </div>

            <button type="submit" class="btn-submit">
              <span>ورود والدین</span>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
            </button>
          </form>
        </div>

        <!-- پنل ورود معلم (فقط کد ملی) -->
        <div class="login-panel teacher-theme" data-role-panel="teacher">
          <form id="teacherLoginForm" autocomplete="off">
            <div class="form-group">
              <label for="teacherNationalCode" class="form-label">
                <span>کد ملی معلم</span>
                <span class="label-hint">(۱۰ رقم بدون خط تیره)</span>
              </label>
              <div class="input-wrapper">
                <input 
                  type="text" 
                  id="teacherNationalCode" 
                  class="input-control input-national-code" 
                  placeholder="مثال: ۱۲۳۴۵۶۷۸۹۰" 
                  maxlength="10" 
                  inputmode="numeric" 
                  required>
                <span class="input-icon-left">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
              </div>
            </div>

            <button type="submit" class="btn-submit btn-submit-teacher">
              <span>ورود معلم</span>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
            </button>
          </form>
        </div>

      </div>


      <div id="verificationPanel" class="verification-panel" hidden>
        <form id="verificationForm">
          <h2>تأیید ورود</h2><p id="verificationCaption"></p>
          <div id="localCodeNotice" class="local-code-notice" hidden></div>
          <label class="form-label" for="verificationCode">کد تأیید پنج‌رقمی</label>
          <input id="verificationCode" class="input-control" inputmode="numeric" autocomplete="one-time-code" maxlength="5" required>
          <label class="remember-label"><input type="checkbox" id="rememberDevice" checked> روی این دستگاه وارد بمانم</label>
          <div id="verificationError" class="verification-error" role="alert"></div>
          <button type="submit" class="btn-submit">تأیید و ورود به سامانه</button>
          <button type="button" id="changeIdentity" class="change-identity">تغییر کد ملی یا دریافت کد تازه</button>
        </form>
      </div>

      <!-- کادر اختصاصی ارتباط با پشتیبانی و نمایش شماره پشتیبان -->
      <div class="support-card-footer">
        <div class="support-box">
          <div class="support-icon-bubble">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </div>
          <div class="support-content">
            <span class="support-title">ارتباط با پشتیبانی سامانه</span>
            <div class="support-contact-row">
              <span class="support-text-prefix">شماره پشتیبان:</span>
              <a href="#" aria-disabled="true" class="support-phone-highlight">پشتیبانی نمایشی</a>
            </div>
            <span class="support-timing">اطلاعات پشتیبانی را برای محیط مقصد تنظیم کنید</span>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- ابزار زنده تنظیم و تست پالت رنگی برند -->
  <aside class="theme-tuner-panel">
    <button type="button" id="tunerToggleBtn" class="theme-tuner-toggle-btn" title="تنظیم پالت رنگی اختصاصی برند">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="13.5" cy="6.5" r=".5"></circle>
        <circle cx="17.5" cy="10.5" r=".5"></circle>
        <circle cx="8.5" cy="7.5" r=".5"></circle>
        <circle cx="6.5" cy="12.5" r=".5"></circle>
        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>
      </svg>
      <span>پالت رنگی برند</span>
    </button>

    <div id="tunerCard" class="theme-tuner-card">
      <div class="tuner-header">
        <span class="tuner-title">🎨 تنظیم رنگ‌های برند</span>
        <button type="button" id="tunerClose" class="tuner-close">✕</button>
      </div>

      <!-- رنگ اصلی Primary -->
      <div class="color-input-row">
        <div class="color-input-info">
          <span class="color-input-title">رنگ اصلی (Primary)</span>
          <span class="color-input-token">--color-primary</span>
        </div>
        <div class="color-picker-group">
          <input type="color" id="inputPrimary" class="color-picker-swatch" value="#D45D79">
          <input type="text" id="textPrimary" class="hex-text-input" value="#D45D79">
        </div>
      </div>

      <!-- رنگ ثانویه Secondary -->
      <div class="color-input-row">
        <div class="color-input-info">
          <span class="color-input-title">رنگ ثانویه (Secondary)</span>
          <span class="color-input-token">--color-secondary</span>
        </div>
        <div class="color-picker-group">
          <input type="color" id="inputSecondary" class="color-picker-swatch" value="#E8998D">
          <input type="text" id="textSecondary" class="hex-text-input" value="#E8998D">
        </div>
      </div>

      <!-- رنگ پس‌زمینه Background -->
      <div class="color-input-row">
        <div class="color-input-info">
          <span class="color-input-title">رنگ پس‌زمینه (Background)</span>
          <span class="color-input-token">--color-background</span>
        </div>
        <div class="color-picker-group">
          <input type="color" id="inputBg" class="color-picker-swatch" value="#FDF9F7">
          <input type="text" id="textBg" class="hex-text-input" value="#FDF9F7">
        </div>
      </div>

      <!-- پالت‌های آماده هماهنگ با نشان‌واره‌ها -->
      <div class="tuner-presets-title">پالت‌های آماده:</div>
      <div class="preset-buttons-grid">
        <button type="button" class="btn-preset" data-p="#D45D79" data-s="#E8998D" data-b="#FDF9F7">
          <span class="preset-dot" style="background: #D45D79;"></span>
          <span>تمشکی گرم (Asset 1)</span>
        </button>

        <button type="button" class="btn-preset" data-p="#6366F1" data-s="#A5B4FC" data-b="#F8FAFC">
          <span class="preset-dot" style="background: #6366F1;"></span>
          <span>نیلی مدرن (آموزشی)</span>
        </button>

        <button type="button" class="btn-preset" data-p="#7C6EE6" data-s="#C4B5FD" data-b="#FAF9FF">
          <span class="preset-dot" style="background: #7C6EE6;"></span>
          <span>یاسی لطیف (Asset 2)</span>
        </button>

        <button type="button" class="btn-preset" data-p="#3F785C" data-s="#A3E635" data-b="#F7FBF8">
          <span class="preset-dot" style="background: #3F785C;"></span>
          <span>سبز سدری (Asset 3)</span>
        </button>
      </div>

      <button type="button" id="copyCssBtn" class="btn-copy-css">
        📋 کپی کدهای CSS پالت
      </button>
    </div>
  </aside>

  <!-- جعبه پیام بازخورد تستی (Toast) -->
  <div id="toastNotice" class="toast-notice" role="status" aria-live="polite"></div>

  <!-- اسکریپت تعاملات -->
  
