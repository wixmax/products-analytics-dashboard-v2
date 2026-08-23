/**
 * product-modal-core.js - Centralized Product Modals & Interaction Controller
 * Shared across: index.php, saved-ads.php, all-products.php, international-products.php
 */

let currentProductForDetails = null;
let currentActiveProduct = null;
let currentProductDetailsWithAnalysis = null;
let vidObserver = null;

const CORE_COUNTRIES_LIST = [
  { code: "DZ", name: "الجزائر", flag: "🇩🇿" },
  { code: "TN", name: "تونس", flag: "🇹🇳" },
  { code: "MA", name: "المغرب", flag: "🇲🇦" },
  { code: "LY", name: "ليبيا", flag: "🇱🇾" },
  { code: "EG", name: "مصر", flag: "🇪🇬" },
  { code: "SA", name: "السعودية", flag: "🇸🇦" },
  { code: "QA", name: "قطر", flag: "🇶🇦" },
  { code: "OM", name: "عُمان", flag: "🇴🇲" },
  { code: "BH", name: "البحرين", flag: "🇧🇭" },
  { code: "KW", name: "الكويت", flag: "🇰🇼" },
  { code: "AE", name: "الإمارات", flag: "🇦🇪" },
  { code: "GB", name: "بريطانيا", flag: "🇬🇧" },
  { code: "FR", name: "فرنسا", flag: "🇫🇷" },
  { code: "ES", name: "إسبانيا", flag: "🇪🇸" },
  { code: "DE", name: "ألمانيا", flag: "🇩🇪" }
];

function initVideoJs(scope) {
  (scope || document).querySelectorAll("video.video-js").forEach((el) => {
    if (el.dataset.vjsInited) return;
    el.dataset.vjsInited = "1";
    try {
      if (typeof videojs === "function") {
        const player = videojs(el, {
          fluid: true,
          controls: true,
          preload: "none",
        });
        player.on("play", () => {
          const all = videojs.getPlayers();
          Object.keys(all).forEach((id) => {
            const p = all[id];
            if (p !== player && !p.paused()) p.pause();
          });
        });
      }
    } catch (e) { /* ignore */ }
  });
}

function normalizeProductObject(p) {
  if (!p) return null;
  const rawImages = p.ad_image_urls || p.product_image || p.thumbnail_url || p.image_url || p.media_url || p.images || p.image || '';
  const rawVideos = p.ad_video_urls || p.video_url || p.video || p.videos || '';
  const countryVal = (p.country || 'MA').toUpperCase();

  return {
    ...p,
    id: p.id || p.product_id,
    productUrl: p.product_url || p.productUrl || p.url || '#',
    actualPrice: p.price_1 || p.actualPrice || p.product_price || p.price || '0',
    title: p.title || p.product_title || p.name || 'بدون عنوان',
    country: countryVal,
    ad_title: p.ad_title || p.title || p.product_title || '',
    ad_body: p.ad_body || p.ad_title || p.description || '',
    ad_image_urls: typeof rawImages === 'string' ? rawImages : (Array.isArray(rawImages) ? rawImages.join(';') : ''),
    ad_video_urls: typeof rawVideos === 'string' ? rawVideos : (Array.isArray(rawVideos) ? rawVideos.join(';') : ''),
    ad_start_date: p.ad_start_date || p.created_at || '',
    ads_count: parseInt(p.ads_count) || 1,
    avg_creatives: parseInt(p.avg_creatives) || 1
  };
}

async function fetchActivityData(productUrl, refresh = false) {
  try {
    if (!productUrl || productUrl === '#') return null;
    const params = new URLSearchParams({ product_url: productUrl });
    if (refresh) params.set("refresh", "1");
    const res = await fetch(`/api/products/activity?${params.toString()}`);
    if (!res.ok) return null;
    const result = await res.json();
    if (result.source === "error") return null;

    return {
      activity: result.activity || null,
      strategy_analysis: result.strategy_analysis || null,
    };
  } catch (e) {
    console.warn("Failed to fetch activity data", e);
    return null;
  }
}

async function openDetailsModal(productOrIdx) {
  let p = productOrIdx;
  if (typeof productOrIdx === 'number' && typeof catalogProducts !== 'undefined') {
    p = catalogProducts[productOrIdx];
  } else if (typeof productOrIdx === 'number' && typeof products !== 'undefined') {
    p = products[productOrIdx];
  } else if (typeof productOrIdx === 'number' && typeof savedProducts !== 'undefined') {
    p = savedProducts[productOrIdx];
  }

  p = normalizeProductObject(p);
  if (!p) return;

  currentProductForDetails = p;
  currentActiveProduct = p;

  const modal = document.getElementById('details-modal');
  if (!modal) return;
  modal.style.display = 'flex';

  // Basic Info
  const titleElem = document.getElementById('details-title');
  if (titleElem) titleElem.textContent = p.title || 'تفاصيل الإعلان والنشاط';

  const infoTitleElem = document.getElementById('details-info-title');
  if (infoTitleElem) infoTitleElem.textContent = p.title || 'بدون عنوان';

  const infoDescElem = document.getElementById('details-info-desc');
  if (infoDescElem) infoDescElem.textContent = p.ad_body || p.ad_title || 'لا يوجد نص تفصيلي للإعلان.';

  const priceInput = document.getElementById('details-price-input');
  if (priceInput) {
    priceInput.value = p.actualPrice || '0';
  }

  // Populate all raw JSON properties in scrollable container
  const rawDataContainer = document.getElementById("details-raw-data-list");
  if (rawDataContainer) {
    let listHtml = "";
    for (const [key, value] of Object.entries(p)) {
      if (value !== null && value !== undefined && value !== "") {
        let valStr = String(value);
        if (valStr.length > 80) valStr = valStr.slice(0, 80) + "...";
        listHtml += `
          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding: 4px 0; font-family: sans-serif; gap: 10px;">
            <span style="color: var(--color-primary); font-weight: 600; text-transform: capitalize;">${key}:</span>
            <span style="word-break: break-all; text-align: right; color: var(--color-text-main); font-weight: 500;">${valStr}</span>
          </div>
        `;
      }
    }
    rawDataContainer.innerHTML =
      listHtml ||
      `<div style="text-align: center; padding: 10px; color: var(--color-text-muted);">لا توجد بيانات إضافية</div>`;
  }

  // Populate Media Items (Videos & Images)
  const mediaContainer = document.getElementById('details-media');
  if (mediaContainer) {
    const imageUrls = [
      ...new Set(
        p.ad_image_urls.split(';').map(u => u.trim()).filter(Boolean)
      )
    ];
    const videoUrls = [
      ...new Set(
        p.ad_video_urls.split(';').map(u => u.trim()).filter(Boolean)
      )
    ];

    const countryMeta = CORE_COUNTRIES_LIST.find(c => c.code === p.country);
    const countryFlag = countryMeta ? countryMeta.flag : '🌍';
    const overlayText = `${countryFlag} إعلان نشط`;

    let mediaHtml = '';
    if (videoUrls.length > 0) {
      videoUrls.forEach((vUrl) => {
        mediaHtml += `
          <div class="details-media-item">
            <video class="video-js vjs-big-play-centered" controls autoplay muted loop playsinline>
              <source src="${vUrl}" type="video/mp4">
            </video>
            <div class="details-media-overlay-text">${overlayText}</div>
          </div>
        `;
      });
      imageUrls.forEach((imgUrl) => {
        mediaHtml += `
          <div class="details-media-item">
            <img src="${imgUrl}" alt="${p.title}">
            <div class="details-media-overlay-text">${overlayText}</div>
          </div>
        `;
      });
    } else if (imageUrls.length > 0) {
      imageUrls.forEach((imgUrl) => {
        mediaHtml += `
          <div class="details-media-item">
            <img src="${imgUrl}" alt="${p.title}">
            <div class="details-media-overlay-text">${overlayText}</div>
          </div>
        `;
      });
    } else {
      mediaHtml = `<div class="no-media" style="grid-column: 1/-1; height: 200px;"><span>📦 لا توجد وسائط معاينة</span></div>`;
    }
    mediaContainer.innerHTML = mediaHtml;
    initVideoJs(mediaContainer);
  }

  // Store watchlist button & Facebook Library button
  let domain = 'متجر خارجي';
  try {
    if (p.productUrl && p.productUrl !== '#') {
      domain = new URL(p.productUrl).hostname.replace('www.', '');
    }
  } catch(e) {}

  const fbBtn = document.getElementById('details-fb-library-btn');
  if (fbBtn) {
    fbBtn.href = `https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=${encodeURIComponent(p.country)}&q=${encodeURIComponent(p.title || '')}`;
  }

  const storeBtn = document.getElementById('details-store-btn');
  if (storeBtn && typeof watchedStores !== 'undefined') {
    const isStoreAdded = watchedStores.includes(domain);
    if (isStoreAdded) {
      storeBtn.textContent = '🟢 تم إضافة المتجر للقائمة';
      storeBtn.className = 'btn btn-success';
    } else {
      storeBtn.textContent = '➕ إضافة المتجر للقائمة';
      storeBtn.className = 'btn btn-secondary';
    }
  }

  // Save Button and Collection Select
  const saveBtn = document.getElementById('details-save-btn');
  if (saveBtn) {
    saveBtn.onclick = () => {
      if (typeof toggleSaveProduct === 'function') {
        toggleSaveProduct(p.id, null);
      }
    };
  }
  updateModalSaveState();

  // Timeline and simulated/real activity
  const chartElem = document.getElementById('details-chart');
  if (chartElem) {
    chartElem.innerHTML = `
      <div style="width:100%; text-align:center; padding: 2rem 0; color: var(--color-text-muted);">
        ⏳ جاري تحميل مخطط النشاط...
      </div>
    `;

    let resData = await fetchActivityData(p.productUrl, false);
    let activityEntries = null;
    let backendStrategy = null;

    if (resData) {
      activityEntries = resData.activity;
      backendStrategy = resData.strategy_analysis;
    }

    if (!activityEntries || activityEntries.length === 0) {
      activityEntries = generateSimulatedActivity(p);
    }

    if (typeof renderTimelineAndMetrics === 'function') {
      renderTimelineAndMetrics(p, activityEntries);
    } else {
      fallbackRenderTimeline(p, activityEntries);
    }

    if (backendStrategy) {
      const badgeElem = document.querySelector(".strategy-badge");
      if (badgeElem) badgeElem.textContent = backendStrategy.badge;

      const textElem = document.getElementById("details-analysis-text");
      if (textElem) textElem.textContent = backendStrategy.text;
    }
  }
}
const openProductDetailsModal = openDetailsModal;

function updateModalSaveState() {
  if (!currentProductForDetails) return;
  const pId = String(currentProductForDetails.id || '');
  const isSaved = (typeof savedProductIds !== 'undefined' && savedProductIds.has(pId)) ||
                  (typeof savedProducts !== 'undefined' && savedProducts.some(p => String(p.id || p.product_id) === pId || (p.productUrl && p.productUrl === currentProductForDetails.productUrl)));
  const saveBtn = document.getElementById("details-save-btn");
  const collectionSelect = document.getElementById("details-collection-select");

  if (saveBtn) {
    if (isSaved) {
      saveBtn.textContent = "⭐ محفوظ";
      saveBtn.style.background = "var(--color-success)";
      saveBtn.style.color = "white";
    } else {
      saveBtn.textContent = "احفظ المنتج";
      saveBtn.style.background = "transparent";
      saveBtn.style.color = "var(--color-success)";
    }
  }

  if (collectionSelect) {
    collectionSelect.style.display = "inline-block";
    const sList = typeof savedProducts !== 'undefined' ? savedProducts : [];
    const productInSaved = sList.find(p => String(p.id || p.product_id) === pId || (p.productUrl && p.productUrl === currentProductForDetails.productUrl));
    const savedCol = productInSaved ? (productInSaved.collection || "عامة") : "عامة";
    const cList = typeof collections !== 'undefined' ? collections : ["عامة"];
    collectionSelect.innerHTML = cList.map(c => `<option value="${c}" ${savedCol === c ? "selected" : ""}>📁 ${c}</option>`).join("");
  }
}

function fallbackRenderTimeline(product, entries) {
  const viewsEl = document.getElementById('details-views');
  if (viewsEl) viewsEl.textContent = ((product.ads_count || 1) * 1500).toLocaleString();
  const engEl = document.getElementById('details-engagement');
  if (engEl) engEl.textContent = '7%';
  const firstSeenEl = document.getElementById('details-first-seen');
  if (firstSeenEl) firstSeenEl.textContent = product.ad_start_date || product.created_at || '-';
  const lastSeenEl = document.getElementById('details-last-seen');
  if (lastSeenEl) lastSeenEl.textContent = product.updated_at || product.created_at || '-';
  const maxCreativesEl = document.getElementById('details-max-creatives');
  if (maxCreativesEl) maxCreativesEl.textContent = `${product.ads_count || 1} كرياتيف`;
  const reactEl = document.getElementById('details-reactivations');
  if (reactEl) reactEl.textContent = `1 أحداث`;
}

function generateSimulatedActivity(product) {
  let seed = 0;
  const url = product.productUrl || product.product_url || product.title || "";
  for (let i = 0; i < url.length; i++) {
    seed = (seed << 5) - seed + url.charCodeAt(i);
    seed = seed & seed;
  }
  function pseudoRand() {
    seed = (seed * 1103515245 + 12345) & 0x7fffffff;
    return seed / 0x7fffffff;
  }

  const entries = [];
  const totalAds = product.ads_count || 12;
  const videoUrls = (product.ad_video_urls || "").split(";").filter(Boolean);

  let baseDate = new Date();
  if (product.ad_start_date) {
    const pDate = new Date(product.ad_start_date);
    if (!isNaN(pDate.getTime())) baseDate = pDate;
  } else {
    baseDate.setDate(baseDate.getDate() - 180);
  }

  const numInt1 = Math.max(1, Math.floor(totalAds * 0.4));
  for (let i = 0; i < numInt1; i++) {
    const start = new Date(baseDate);
    start.setDate(start.getDate() + i * 2);
    const end = new Date(start);
    end.setDate(end.getDate() + 15 + Math.floor(pseudoRand() * 20));
    entries.push({
      ad_start_date: start.toISOString().split("T")[0],
      ad_end_date: end.toISOString().split("T")[0],
      ad_video_urls: videoUrls[i % videoUrls.length] || "",
    });
  }

  const numInt2 = Math.max(1, Math.floor(totalAds * 0.4));
  for (let i = 0; i < numInt2; i++) {
    const start = new Date(baseDate);
    start.setDate(start.getDate() + 45 + i * 2);
    const end = new Date(start);
    end.setDate(end.getDate() + 20 + Math.floor(pseudoRand() * 25));
    entries.push({
      ad_start_date: start.toISOString().split("T")[0],
      ad_end_date: end.toISOString().split("T")[0],
      ad_video_urls: videoUrls[i % videoUrls.length] || "",
    });
  }

  return entries;
}

function openIndexInfoModal(productOrIdx) {
  let p = productOrIdx;
  if (typeof productOrIdx === 'number' && typeof catalogProducts !== 'undefined') {
    p = catalogProducts[productOrIdx];
  } else if (typeof productOrIdx === 'number' && typeof products !== 'undefined') {
    p = products[productOrIdx];
  } else if (typeof productOrIdx === 'number' && typeof savedProducts !== 'undefined') {
    p = savedProducts[productOrIdx];
  }

  p = normalizeProductObject(p);
  if (!p) return;

  const modal = document.getElementById("index-info-modal");
  if (!modal) return;

  const imageUrls = (p.ad_image_urls || "").split(";").filter(Boolean);

  let domain = "متجر خارجي";
  const pUrl = p.productUrl || "";
  try {
    if (pUrl && pUrl !== '#')
      domain = new URL(pUrl).hostname.replace("www.", "");
  } catch (e) {}

  let timeAgoText = "";
  if (p.ad_start_date) {
    const startDate = new Date(p.ad_start_date);
    if (!isNaN(startDate.getTime())) {
      const now = new Date();
      now.setHours(0, 0, 0, 0);
      startDate.setHours(0, 0, 0, 0);
      const diffDays = Math.floor((now - startDate) / (1000 * 60 * 60 * 24));
      if (diffDays === 0) timeAgoText = " (اليوم)";
      else if (diffDays === 1) timeAgoText = " (أمس)";
      else if (diffDays < 7) timeAgoText = ` (منذ ${diffDays} أيام)`;
      else if (diffDays < 30)
        timeAgoText = ` (منذ ${Math.floor(diffDays / 7)} أسبوع)`;
      else timeAgoText = ` (منذ ${Math.floor(diffDays / 30)} شهر)`;
    }
  }

  const titleEl = document.getElementById("index-info-title");
  if (titleEl) titleEl.textContent = p.title || "بدون عنوان";

  const domainEl = document.getElementById("index-info-domain");
  if (domainEl) domainEl.textContent = `🏪 ${domain}`;

  const adsEl = document.getElementById("index-info-ads");
  if (adsEl) adsEl.textContent = p.ads_count || 1;

  const imagesEl = document.getElementById("index-info-images");
  if (imagesEl) imagesEl.textContent = imageUrls.length;

  const creativesEl = document.getElementById("index-info-creatives");
  if (creativesEl) creativesEl.textContent = p.avg_creatives || 1;

  const dateEl = document.getElementById("index-info-date");
  if (dateEl) dateEl.textContent = `${p.ad_start_date ? p.ad_start_date.split(' ')[0] : "--"}${timeAgoText}`;

  const adTitleEl = document.getElementById("index-info-ad-title");
  if (adTitleEl) adTitleEl.textContent = `💬 ${p.ad_title || p.title || "نص الإعلان"}`;

  const adBodyEl = document.getElementById("index-info-ad-body");
  if (adBodyEl) adBodyEl.textContent = p.ad_body || p.ad_title || "لا يوجد نص تفصيلي.";

  const visitBtn = document.getElementById("index-info-visit-btn");
  if (visitBtn) {
    visitBtn.onclick = () => {
      if (pUrl && pUrl !== '#') window.open(pUrl, "_blank");
    };
  }

  modal.style.display = "flex";
}

function closeIndexInfoModal() {
  const modal = document.getElementById("index-info-modal");
  if (modal) modal.style.display = "none";
}

function closeDetailsModal() {
  const modal = document.getElementById('details-modal');
  if (modal) modal.style.display = 'none';
}

function openDetailsHelpModal() {
  const modal = document.getElementById("details-help-modal");
  if (modal) modal.style.display = "flex";
}

function closeDetailsHelpModal() {
  const modal = document.getElementById("details-help-modal");
  if (modal) modal.style.display = "none";
}

function openAiHistoryDrawer() {
  const drawer = document.getElementById('ai-history-drawer');
  if (drawer) drawer.style.display = 'flex';
}

function closeAiHistoryDrawer() {
  const drawer = document.getElementById('ai-history-drawer');
  if (drawer) drawer.style.display = 'none';
}

function openAiIoModal() {
  const modal = document.getElementById('ai-io-modal');
  if (modal) modal.style.display = 'flex';
}

function closeAiIoModal() {
  const modal = document.getElementById('ai-io-modal');
  if (modal) modal.style.display = 'none';
}

function switchAiIoTab(tab) {
  const btnInput = document.getElementById('ai-io-tab-input');
  const btnOutput = document.getElementById('ai-io-tab-output');
  const contentBox = document.getElementById('ai-io-content-box');
  if (!contentBox) return;

  if (tab === 'input') {
    if (btnInput) {
      btnInput.style.borderColor = 'var(--color-primary)';
      btnInput.style.color = 'var(--color-primary)';
    }
    if (btnOutput) {
      btnOutput.style.borderColor = 'var(--border-color)';
      btnOutput.style.color = 'var(--color-text-muted)';
    }
    contentBox.value = window._lastAiInputPayload || 'لا توجد مدخلات مسجلة.';
  } else {
    if (btnOutput) {
      btnOutput.style.borderColor = 'var(--color-primary)';
      btnOutput.style.color = 'var(--color-primary)';
    }
    if (btnInput) {
      btnInput.style.borderColor = 'var(--border-color)';
      btnInput.style.color = 'var(--color-text-muted)';
    }
    contentBox.value = window._lastAiOutputPayload || 'لا توجد مخرجات مسجلة.';
  }
}

function copyAiIoContent() {
  const contentBox = document.getElementById('ai-io-content-box');
  if (contentBox && contentBox.value) {
    navigator.clipboard.writeText(contentBox.value);
    showToast('📋 تم نسخ النص بنجاح!');
  }
}

async function toggleStoreListAction() {
  if (!currentProductForDetails) return;
  const pUrl = currentProductForDetails.productUrl;
  let domain = 'متجر خارجي';
  try {
    if (pUrl && pUrl !== '#') domain = new URL(pUrl).hostname.replace('www.', '');
  } catch(e) {}

  const btn = document.getElementById('details-store-btn');
  try {
    const res = await fetch('/api/products/watchlist/toggle', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ domain })
    });
    if (res.ok) {
      const data = await res.json();
      if (typeof watchedStores !== 'undefined') {
        if (data.action === 'removed') {
          watchedStores = watchedStores.filter(d => d !== domain);
          if (btn) {
            btn.textContent = '➕ إضافة المتجر للقائمة';
            btn.className = 'btn btn-secondary';
          }
          showToast('تمت إزالة المتجر من قائمتك الخاصة', 'info');
        } else {
          if (!watchedStores.includes(domain)) watchedStores.push(domain);
          if (btn) {
            btn.textContent = '🟢 تم إضافة المتجر للقائمة';
            btn.className = 'btn btn-success';
          }
          showToast('تمت إضافة المتجر لقائمتك بنجاح! 🛍️', 'success');
        }
      }
    }
  } catch(err) {
    console.error(err);
    showToast('تعذر تعديل قائمة المتاجر', 'error');
  }
}

function downloadProductMedia() {
  if (!currentProductForDetails) return;
  const videoUrls = (currentProductForDetails.ad_video_urls || '').split(';').filter(Boolean);
  const imageUrls = (currentProductForDetails.ad_image_urls || '').split(';').filter(Boolean);
  const mediaUrl = videoUrls[0] || imageUrls[0];

  if (mediaUrl) {
    const a = document.createElement('a');
    a.href = mediaUrl;
    a.download = `media_${(currentProductForDetails.title || 'item').slice(0, 10)}`;
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast('جاري تنزيل ملف الميديا... 📥', 'success');
  } else {
    showToast('لا توجد ميديا صالحة للتحميل.', 'warning');
  }
}

function showProductAnalysisToast() {
  showToast('📊 جاري بدء تحليل أداء المنتج وتجهيز لوحة المؤشرات...');
}

function showAdAnalysisToast() {
  showToast('✨ جاري فحص زوايا التسويق والعروض الخاصة بالإعلان...');
}

async function refreshActivityData() {
  if (!currentProductForDetails) return;
  const p = currentProductForDetails;
  const chartElem = document.getElementById("details-chart");
  if (chartElem) {
    chartElem.innerHTML = `
      <div style="width:100%; text-align:center; padding: 2rem 0; color: var(--color-text-muted);">
        ⏳ جاري تحديث بيانات النشاط...
      </div>
    `;
  }

  let resData = await fetchActivityData(p.productUrl, true);
  let activityEntries = null;
  let backendStrategy = null;

  if (resData) {
    activityEntries = resData.activity;
    backendStrategy = resData.strategy_analysis;
  }

  if (!activityEntries || activityEntries.length === 0) {
    activityEntries = generateSimulatedActivity(p);
  }

  if (typeof renderTimelineAndMetrics === 'function') {
    renderTimelineAndMetrics(p, activityEntries);
  } else {
    fallbackRenderTimeline(p, activityEntries);
  }

  if (backendStrategy) {
    const badgeElem = document.querySelector(".strategy-badge");
    if (badgeElem) badgeElem.textContent = backendStrategy.badge;

    const textElem = document.getElementById("details-analysis-text");
    if (textElem) textElem.textContent = backendStrategy.text;
  }

  showToast('🔄 تم تحديث مؤشرات النشاط والإعلانات بنجاح!');
}

function handleDetailsPriceChange(val) {
  if (currentProductForDetails) {
    currentProductForDetails.price_1 = val;
    currentProductForDetails.actualPrice = val;
    showToast(`تم تعديل السعر إلى ${val} DH`);
  }
}

async function handleDetailsCollectionChange() {
  if (!currentProductForDetails) return;
  const select = document.getElementById("details-collection-select");
  if (!select) return;
  const colName = select.value;
  const pUrl = currentProductForDetails.productUrl;

  try {
    const res = await fetch("/api/products/saved/collection", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ product_url: pUrl, collection: colName }),
    });
    if (res.ok) {
      showToast(`تم نقل المنتج لمجموعة: ${colName}`, "success");
    }
  } catch (err) {
    console.error(err);
    showToast("تعذر تغيير المجموعة.", "error");
  }
}

function downloadProductDataJSON() {
  const targetData = currentProductDetailsWithAnalysis || currentProductForDetails;
  if (!targetData) {
    showToast('لا توجد بيانات متاحة للتحميل', 'warning');
    return;
  }
  const dataStr = JSON.stringify(targetData, null, 2);
  const blob = new Blob([dataStr], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  const safeTitle = (targetData.title || 'product').replace(/[^\w\s\-]/g, '_').slice(0, 30);
  a.download = `product_${safeTitle}_${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast('تم تحميل ملف JSON بنجاح 📥', 'success');
}

function showToast(msg, type = "info") {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.style.cssText = "background: var(--bg-card); color: var(--color-text-main); border: 1px solid var(--border-color); padding: 10px 16px; border-radius: var(--radius-sm); margin-bottom: 8px; box-shadow: var(--shadow-md); font-size: 0.85rem; font-weight: 700;";
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

// Global modal backdrop close listener
window.addEventListener('click', (event) => {
  const helpModal = document.getElementById('details-help-modal');
  const detailsModal = document.getElementById('details-modal');
  const infoModal = document.getElementById('index-info-modal');
  if (event.target === helpModal) closeDetailsHelpModal();
  if (event.target === detailsModal) closeDetailsModal();
  if (event.target === infoModal) closeIndexInfoModal();
});
