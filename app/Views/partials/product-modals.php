<!-- Unified Product & AI Modals Component -->

<!-- 1. Product Details & Timeline Modal -->
<div class="details-modal-overlay" id="details-modal">
  <div class="details-modal-card">
    <div class="details-modal-header">
      <div class="details-modal-title" id="details-title">
        تفاصيل الإعلان والنشاط
      </div>
      <div class="details-modal-header-actions">
        <button
          class="btn btn-secondary"
          onclick="openDetailsHelpModal()"
          style="
            border: 1px solid var(--color-primary);
            color: var(--color-primary);
            background: transparent;
            margin-left: 8px;
            font-weight: 600;
          "
        >
          💡 دليل القراءة
        </button>
        <button
          class="btn btn-success"
          id="details-store-btn"
          onclick="toggleStoreListAction()"
        >
          ➕ إضافة المتجر للقائمة
        </button>
        <button
          class="btn btn-secondary"
          id="details-save-btn"
          style="
            border: 1px solid var(--color-success);
            color: var(--color-success);
            background: transparent;
          "
        >
          احفظ المنتج
        </button>
        <select
          id="details-collection-select"
          style="
            font-size: 0.8rem;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--color-text-main);
            width: 145px;
            display: none;
            margin-left: 8px;
            cursor: pointer;
          "
          onchange="handleDetailsCollectionChange()"
        ></select>
        <button class="details-modal-close" onclick="closeDetailsModal()">
          &times;
        </button>
      </div>
    </div>
    <div class="details-modal-body">
      <!-- Left Panel: Chart & Metrics -->
      <div class="details-left-panel">
        <!-- Timeline Section -->
        <div class="details-section-card">
          <div class="details-section-title">
            🕒 المخطط الزمني
            <span
              style="
                font-size: 0.85rem;
                color: var(--color-text-muted);
                font-weight: normal;
                margin-right: 8px;
              "
              >تاريخ مرئي لنشاط الإعلان وعدد مرات إعادة تفعيله.</span
            >
          </div>
          <div class="details-timeline-chart" id="details-chart">
            <!-- Dynamic Bars generated via JS -->
          </div>
          <div class="details-chart-legend">
            <div class="legend-item">
              <div class="legend-marker bar"></div>
              <span>ارتفاع العمود: يمثل عدد الكرياتيف النشطة (الكثافة).</span>
            </div>
            <div class="legend-item">
              <div class="legend-marker dot"></div>
              <span>النقطة الحمراء: تشير إلى "تاريخ انتهاء مجدول".</span>
            </div>
            <div class="legend-item">
              <div class="legend-marker orange"></div>
              <span>الشريط البرتقالي: إعادة إحياء ونشاط بعد توقف.</span>
            </div>
          </div>
        </div>

        <!-- Ad Strategy Analysis Section -->
        <div class="strategy-analysis-card">
          <div
            class="details-section-title"
            style="color: var(--color-primary)"
          >
            ⚡ تحليل استراتيجية الإعلان
          </div>
          <div class="strategy-badge">✓ منتج رابح (تم التحسين)</div>
          <p
            id="details-analysis-text"
            style="
              font-size: 0.85rem;
              line-height: 1.6;
              color: var(--color-text-main);
              margin-top: 8px;
            "
          >
            قام المعلن باختيار مكثف (High Peak)، ثم ركز على الإعلانات الرابحة لزيادة المبيعات (Scaling).
          </p>
        </div>

        <!-- Key Indicators Section -->
        <div class="details-section-card">
          <div class="details-section-title">⚙️ المؤشرات الرئيسية</div>
          <div class="indicators-grid">
            <div class="indicator-card">
              <div class="indicator-title">👁️ المشاهدات المقدرة</div>
              <div class="indicator-value" id="details-views">0</div>
            </div>
            <div class="indicator-card">
              <div class="indicator-title">❤️ التفاعل المقدر</div>
              <div class="indicator-value" id="details-engagement">0</div>
            </div>
            <div class="indicator-card">
              <div class="indicator-title">📅 أول ظهور</div>
              <div class="indicator-value" id="details-first-seen">-</div>
            </div>
            <div class="indicator-card">
              <div class="indicator-title">📅 آخر ظهور</div>
              <div class="indicator-value" id="details-last-seen">-</div>
            </div>
            <div class="indicator-card">
              <div class="indicator-title">🔝 أقصى عدد كرياتيف</div>
              <div class="indicator-value" id="details-max-creatives">0</div>
            </div>
            <div class="indicator-card">
              <div class="indicator-title">🔄 إعادة النشاط</div>
              <div class="indicator-value" id="details-reactivations">0</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Showcase Media & Description -->
      <div class="details-right-panel">
        <div class="details-media-showcase" id="details-media">
          <!-- Dynamic media items -->
        </div>

        <div class="details-product-info">
          <div class="details-product-title" id="details-info-title">-</div>
          <p class="details-product-desc" id="details-info-desc">-</p>
          
          <!-- Price edit input -->
          <div class="details-price-edit" style="margin-top: 15px; display: flex; align-items: center; gap: 10px; background: var(--bg-card); padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <span style="font-weight: 700; font-size: 0.85rem; color: var(--color-primary);">💰 سعر المنتج:</span>
            <input type="text" id="details-price-input" value="0" style="width: 100px; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-size: 0.85rem; text-align: center; font-weight: 600;" onchange="handleDetailsPriceChange(this.value)" />
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">DH / عملة محلية</span>
          </div>
        </div>

        <!-- All Data & JSON Download Section -->
        <div
          class="details-raw-data-card"
          style="
            background: var(--bg-input);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            padding: 12px;
            margin-top: 15px;
          "
        >
          <div
            style="
              display: flex;
              justify-content: space-between;
              align-items: center;
              margin-bottom: 8px;
            "
          >
            <span
              style="
                font-weight: 700;
                font-size: 0.85rem;
                color: var(--color-primary);
              "
              >📋 بيانات المنتج الكاملة (JSON)</span
            >
            <button
              class="btn btn-secondary"
              id="details-json-download-btn"
              onclick="downloadProductDataJSON()"
              style="
                padding: 4px 8px;
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                gap: 4px;
              "
            >
              📥 تحميل JSON
            </button>
          </div>
          <div
            id="details-raw-data-list"
            style="
              max-height: 150px;
              overflow-y: auto;
              font-size: 0.75rem;
              color: var(--color-text-muted);
              font-family: var(--font-mono);
              line-height: 1.4;
              display: flex;
              flex-direction: column;
              gap: 4px;
              direction: ltr;
              text-align: left;
            "
          >
            <!-- Dynamically populated key-value list -->
          </div>
        </div>

        <div class="details-action-buttons">
          <button
            class="btn btn-success"
            id="details-product-action-btn"
            onclick="showProductAnalysisToast()"
          >
            📊 تحليل المنتج AI
          </button>
          <button
            class="btn btn-primary"
            id="details-download-btn"
            onclick="downloadProductMedia()"
          >
            📥 تحميل
          </button>
          <button
            class="btn btn-purple"
            id="details-ad-analysis-btn"
            onclick="showAdAnalysisToast()"
          >
            ✨ تحليل الإعلان
          </button>
          <a
            href="#"
            target="_blank"
            class="btn btn-dashed"
            id="details-fb-library-btn"
            >🌐 عرض في مكتبة الإعلانات</a
          >
          <button
            class="btn btn-secondary"
            id="details-refresh-activity-btn"
            onclick="refreshActivityData()"
            style="border:1px solid var(--color-primary);color:var(--color-primary)"
          >
            🔄 تحديث النشاط
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 2. Details Help Modal -->
<div class="help-modal-overlay" id="details-help-modal">
  <div class="help-modal-card">
    <div class="help-modal-header">
      <div class="help-modal-title">💡 دليل قراءة وتحليل الإحصائيات</div>
      <button class="help-modal-close" onclick="closeDetailsHelpModal()">
        &times;
      </button>
    </div>
    <div class="help-modal-body">
      <div class="help-section">
        <div class="help-section-title">
          🕒 المخطط الزمني (Activity Timeline)
        </div>
        <div class="help-section-desc">
          يُظهر حجم ونشاط إعلانات المنتج على مدار الـ 12 أسبوعاً الماضية.
          <br />• <b>ارتفاع الأعمدة</b>: يُمثل كثافة الإعلانات النشطة وحجم الميزانيات المخصصة من المنافسين.
          <br />• <b>النقاط الحمراء</b>: تُشير لانتهاء وتوقف حملات إعلانية معينة (غربلة الإعلانات الخاسرة).
        </div>
      </div>

      <div
        class="help-section"
        style="border-left: 4px solid var(--color-success)"
      >
        <div class="help-section-title" style="color: var(--color-success)">
          🔄 أحداث إعادة التنشيط (Reactivation Events)
        </div>
        <div class="help-section-desc">
          <b>الإشارة الذهبية للأرباح!</b> تُحسب عند رصد فترة خمول تليها عودة قوية ومفاجئة للإعلانات. يدل هذا على أن المنتج مربح للغاية، وأن المعلن أوقفه مؤقتاً بسبب <b>نفاد المخزون</b> ثم أعاد تشغيله فور توفر البضاعة.
        </div>
      </div>

      <div class="help-section">
        <div class="help-section-title">
          🧠 تحليل استراتيجية الإعلان (Marketing Strategy)
        </div>
        <div class="help-section-desc">
          قراءة تسويقية ذكية تلخص لك توجه المنافس الإعلاني:
          <br />• <b>التكبير والتوسع (Scaling)</b>: تفعيل ميزانيات ضخمة وعشرات الإعلانات النشطة في نفس الوقت.
          <br />• <b>الاختبار الأولي (Testing)</b>: تشغيل إعلانات محدودة لاستكشاف اهتمام السوق.
        </div>
      </div>

      <div class="help-section">
        <div class="help-section-title">
          📊 المؤشرات الرئيسية (KPIs Metrics)
        </div>
        <div class="help-section-desc">
          • <b>المشاهدات المقدرة (Estimated Views)</b>: حساب المدى المحتمل للانتشار والوصول.
          <br />• <b>التفاعل المقدر (Estimated Engagement)</b>: معدل التفاعل المتوقع في السوق المحلي بمتوسط 7%.
          <br />• <b>أقصى عدد كرياتيف (Max Creatives)</b>: عدد الزوايا الإعلانية الفريدة والفيديوهات التي يختبرها المنافس.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3. Index Info Modal (معلومات سريعة) -->
<div class="modal-overlay" id="index-info-modal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 200;">
  <div class="modal-card" style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); width: 90%; max-width: 520px; box-shadow: var(--shadow-lg); transition: var(--transition-all); max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
      <div>
        <h3 id="index-info-title" style="font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;">-</h3>
        <div id="index-info-domain" style="font-size: 0.8rem; color: var(--color-text-muted); font-weight: 600;">-</div>
      </div>
      <button class="details-modal-close" onclick="closeIndexInfoModal()">&times;</button>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
      <div style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
        <div id="index-info-ads" style="font-size: 1.2rem; font-weight: 700; color: var(--color-primary);">0</div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted);">إعلانات نشطة</div>
      </div>
      <div style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
        <div id="index-info-images" style="font-size: 1.2rem; font-weight: 700; color: var(--color-primary);">0</div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted);">عدد الصور</div>
      </div>
      <div style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
        <div id="index-info-creatives" style="font-size: 1.2rem; font-weight: 700; color: var(--color-primary);">1</div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted);">متوسط الكرياتيف</div>
      </div>
      <div style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
        <div id="index-info-date" style="font-size: 0.85rem; font-weight: 700; color: var(--color-text-main); margin-top: 4px;">--</div>
        <div style="font-size: 0.75rem; color: var(--color-text-muted);">تاريخ الإطلاق</div>
      </div>
    </div>

    <div style="margin-bottom: 1rem;">
      <div id="index-info-ad-title" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; color: var(--color-text-main);">💬 نص الإعلان</div>
      <div id="index-info-ad-body" style="font-size: 0.8rem; line-height: 1.6; color: var(--color-text-muted); background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); max-height: 120px; overflow-y: auto;">-</div>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 8px;">
      <button class="btn btn-secondary" onclick="closeIndexInfoModal()">إغلاق</button>
      <button class="btn btn-primary" id="index-info-visit-btn">🛒 زيارة المتجر</button>
    </div>
  </div>
</div>

<!-- 4. AI Saved History Slide Drawer -->
<div class="modal-overlay" id="ai-history-drawer" style="display: none; z-index: 10002; justify-content: flex-start;">
  <div style="width: 480px; max-width: 90%; height: 100vh; background: var(--bg-card); border-left: 1px solid var(--border-color); padding: 1.5rem; display: flex; flex-direction: column; box-shadow: var(--shadow-lg); overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
      <div>
        <h3 style="font-weight: 800; font-size: 1.15rem; color: var(--color-primary); margin: 0;">
          📜 التحليلات المحفوظة لحسابك
        </h3>
        <span style="font-size: 0.78rem; color: var(--color-text-muted);">سجل عمليات التقييم السابقة للرجوع إليها</span>
      </div>
      <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiHistoryDrawer()">&times;</button>
    </div>

    <div id="ai-history-list" style="display: flex; flex-direction: column; gap: 12px; flex: 1;">
      <div style="text-align: center; color: var(--color-text-muted); padding: 2rem 0;">جاري تحميل السجل...</div>
    </div>
  </div>
</div>

<!-- 5. AI Input/Output Payload Inspector Modal -->
<div class="modal-overlay" id="ai-io-modal" style="display: none; z-index: 10008;">
  <div class="modal-card" style="max-width: 800px; width: 92%; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem;">
      <h3 style="font-weight: 800; font-size: 1.1rem; color: var(--color-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
        🖥️ مستعرض مدخلات ومخرجات الذكاء الاصطناعي (Input / Output Inspector)
      </h3>
      <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiIoModal()">&times;</button>
    </div>

    <div style="display: flex; gap: 8px; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
      <button id="ai-io-tab-input" onclick="switchAiIoTab('input')" class="btn btn-secondary" style="font-weight: 700; font-size: 0.85rem; padding: 6px 16px; border-color: var(--color-primary); color: var(--color-primary);">
        📤 المدخلات (Input Payload / Prompt)
      </button>
      <button id="ai-io-tab-output" onclick="switchAiIoTab('output')" class="btn btn-secondary" style="font-weight: 700; font-size: 0.85rem; padding: 6px 16px; border-color: var(--border-color); color: var(--color-text-muted);">
        📥 المخرجات (Raw Output / API Response)
      </button>
      <button onclick="copyAiIoContent()" class="btn btn-secondary" style="margin-right: auto; font-size: 0.8rem; padding: 6px 14px; font-weight: 700;">
        📋 نسخ النص
      </button>
    </div>

    <div style="position: relative;">
      <textarea id="ai-io-content-box" readonly style="width: 100%; height: 360px; font-family: var(--font-mono, monospace); font-size: 0.82rem; background: var(--bg-input); color: var(--color-text-main); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; direction: ltr; text-align: left; line-height: 1.5; resize: vertical;"></textarea>
    </div>

    <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
      <button class="btn btn-secondary" onclick="closeAiIoModal()">إغلاق</button>
    </div>
  </div>
</div>
