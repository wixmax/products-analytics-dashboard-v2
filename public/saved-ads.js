// saved-ads.js

const COUNTRIES_LIST = [
  { code: "DZ", name: "الجزائر", flag: "🇩🇿" },
  { code: "TN", name: "تونس", flag: "🇹🇳" },
  { code: "MA", name: "المغرب", flag: "🇲🇦" },
  { code: "LY", name: "ليبيا", flag: "🇱🇾" },
  { code: "EG", name: "مصر", flag: "🇪🇬" },
  { code: "SA", name: "السعودية", flag: "🇸🇦" },
  { code: "QA", name: "قطر", flag: "🇶🇦" },
  { code: "EA", name: "شرق أفريقيا", flag: "🌍" },
  { code: "OM", name: "عُمان", flag: "🇴🇲" },
  { code: "BH", name: "البحرين", flag: "🇧🇭" },
  { code: "KW", name: "الكويت", flag: "🇰🇼" },
  { code: "GB", name: "بريطانيا", flag: "🇬🇧" },
  { code: "IE", name: "أيرلندا", flag: "🇮🇪" },
  { code: "FR", name: "فرنسا", flag: "🇫🇷" },
  { code: "BE", name: "بلجيكا", flag: "🇧🇪" },
  { code: "LU", name: "لوكسمبورغ", flag: "🇱🇺" },
  { code: "CH", name: "سويسرا", flag: "🇨🇭" },
  { code: "DE", name: "ألمانيا", flag: "🇩🇪" },
  { code: "AT", name: "النمسا", flag: "🇦🇹" },
  { code: "ES", name: "إسبانيا", flag: "🇪🇸" },
  { code: "IT", name: "إيطاليا", flag: "🇮🇹" },
  { code: "NL", name: "هولندا", flag: "🇳🇱" },
  { code: "PT", name: "البرتغال", flag: "🇵🇹" },
  { code: "NG", name: "نيجيريا", flag: "🇳🇬" },
  { code: "CI", name: "ساحل العاج", flag: "🇨🇮" },
  { code: "SN", name: "السنغال", flag: "🇸🇳" },
  { code: "KE", name: "كينيا", flag: "🇰🇪" },
];

let savedProducts = [];
let collections = ["عامة", "ملابس", "إلكترونيات", "أدوات منزلية"];
let watchedStores = [];
let currentFiltered = [];

async function loadInitialDatabaseData() {
  try {
    const collectionsRes = await fetch("/api/products/collections");
    if (collectionsRes.ok) {
      const data = await collectionsRes.json();
      // Fallback to defaults if API returns empty array
      collections = data && data.length > 0
        ? data
        : ["عامة", "ملابس", "إلكترونيات", "أدوات منزلية"];
    }
    await fetchSavedProductsFromDb();
    const watchlistRes = await fetch("/api/products/watchlist");
    if (watchlistRes.ok) {
      watchedStores = await watchlistRes.json();
    }
    populateCollectionFilters();
    renderSavedGrid();
  } catch (e) {
    console.error("Failed to load initial data from PostgreSQL:", e);
  }
}

async function fetchSavedProductsFromDb() {
  try {
    const savedRes = await fetch("/api/products/saved");
    if (savedRes.ok) {
      const dbSaved = await savedRes.json();
      savedProducts = dbSaved.map((p) => ({
        ...p,
        productUrl: p.product_url,
        algorithm: p.algo,
        actualPrice: p.price_1,
        saved_at: p.saved_at,
        rating: parseInt(p.rating) || 0,
        notes: p.notes || "",
        collection: p.collection || "عامة",
        status: p.saved_status || "active",
      }));
    }
  } catch (e) {
    console.error("Error fetching saved products from DB:", e);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadInitialDatabaseData();
  setupTheme();
});

function populateCollectionFilters() {
  const filterSelect = document.getElementById("collection-filter");
  if (filterSelect) {
    filterSelect.innerHTML =
      `<option value="all">جميع المجموعات 📁</option>` +
      collections.map((c) => `<option value="${c}">📁 ${c}</option>`).join("");
  }
}

function renderSavedGrid() {
  const container = document.getElementById("saved-products-container");
  const searchQuery = document
    .getElementById("saved-search")
    .value.toLowerCase();
  const sortOrder = document.getElementById("saved-sort").value;
  const statusFilter = document.getElementById("status-filter").value;
  const collectionFilter = document.getElementById("collection-filter").value;

  let filtered = savedProducts.filter((p) => {
    const matchesSearch =
      (p.title && p.title.toLowerCase().includes(searchQuery)) ||
      (p.ad_body && p.ad_body.toLowerCase().includes(searchQuery)) ||
      (p.ad_title && p.ad_title.toLowerCase().includes(searchQuery));

    const productStatus = p.status || "active";
    const matchesStatus =
      statusFilter === "all" || productStatus === statusFilter;

    const productCollection = p.collection || "عامة";
    const matchesCollection =
      collectionFilter === "all" || productCollection === collectionFilter;

    return matchesSearch && matchesStatus && matchesCollection;
  });

  currentFiltered = filtered;

  // Sorting
  filtered.sort((a, b) => {
    if (sortOrder === "newest")
      return new Date(b.saved_at) - new Date(a.saved_at);
    if (sortOrder === "oldest")
      return new Date(a.saved_at) - new Date(b.saved_at);
    if (sortOrder === "rating-desc") return (b.rating || 0) - (a.rating || 0);
    if (sortOrder === "rating-asc") return (a.rating || 0) - (b.rating || 0);
    return 0;
  });

  if (filtered.length === 0) {
    container.innerHTML = `
            <div class="empty-state" style="grid-column: 1/-1;">
                <div class="empty-icon">⭐</div>
                <h3>لا توجد منتجات محفوظة</h3>
                <p>قم بحفظ بعض المنتجات من لوحة التحكم لعرضها هنا.</p>
            </div>
        `;
    return;
  }

  container.innerHTML = filtered
    .map((p) => {
      const safeId = p.productUrl
        ? btoa(unescape(encodeURIComponent(p.productUrl))).replace(/[/+=]/g, "")
        : Math.random().toString(36).slice(2);
      const imageUrls = (p.ad_image_urls || "").split(";").filter(Boolean);
      const videoUrls = (p.ad_video_urls || "").split(";").filter(Boolean);

      const countryMeta = COUNTRIES_LIST.find((c) => c.code === p.country);
      const flag = countryMeta ? countryMeta.flag : "🌍";

      let domain = "متجر خارجي";
      try {
        if (p.productUrl)
          domain = new URL(p.productUrl).hostname.replace("www.", "");
      } catch (e) {
        domain = p.productUrl || "رابط غير معروف";
      }

      // Time elapsed calculation
      let timeAgoText = "";
      if (p.ad_start_date) {
        const startDate = new Date(p.ad_start_date);
        if (!isNaN(startDate.getTime())) {
          const now = new Date();
          now.setHours(0, 0, 0, 0);
          startDate.setHours(0, 0, 0, 0);
          const diffDays = Math.floor(
            (now - startDate) / (1000 * 60 * 60 * 24),
          );
          if (diffDays === 0) timeAgoText = " (اليوم)";
          else if (diffDays === 1) timeAgoText = " (أمس)";
          else if (diffDays < 7) timeAgoText = ` (منذ ${diffDays} أيام)`;
          else if (diffDays < 30)
            timeAgoText = ` (منذ ${Math.floor(diffDays / 7)} أسبوع)`;
          else timeAgoText = ` (منذ ${Math.floor(diffDays / 30)} شهر)`;
        }
      }

      // Stars HTML
      let starsHtml = "";
      for (let i = 1; i <= 5; i++) {
        const escapedUrl = (p.productUrl || "").replace(/'/g, "\\'");
        starsHtml += `<span class="star ${i <= (p.rating || 0) ? "filled" : ""}" onclick="setRating('${escapedUrl}', ${i})">★</span>`;
      }

      const escapedUrlForDelete = (p.productUrl || "").replace(/'/g, "\\'");
      const escapedUrlForNotes = (p.productUrl || "").replace(/'/g, "\\'");

      return `
            <article class="product-card saved-product-card" id="card-${safeId}">
                <div class="product-media">
                    ${
                      videoUrls.length > 0
                        ? `<div class="vid-placeholder" data-vid-src="${videoUrls[0]}" data-vid-poster="${imageUrls[0] || ""}" id="vp-${safeId}">${imageUrls[0] ? `<img src="${imageUrls[0]}" alt="" class="vid-placeholder-img">` : `<div class="vid-placeholder-bg"></div>`}<div class="vid-play-btn">▶</div></div>`
                        : imageUrls.length > 0
                          ? `<img src="${imageUrls[0]}" alt="${p.title}">`
                          : '<div class="no-media"><span>📦 لا توجد وسائط</span></div>'
                    }
                    <div class="status-badge ${p.active_ads ? "active" : "inactive"}">
                        ${p.active_ads ? "🟢 نشط" : "🔴 متوقف"}
                    </div>
                    <div class="country-flag-badge">
                        <span>${flag}</span>
                        <span>${p.country || "--"}</span>
                    </div>
                    <div class="media-badge" style="top: auto; bottom: 10px;">⭐ محفوظة</div>
                </div>
                <div class="card-body">
                    <h4 class="p-title" title="${p.title}">${p.title}</h4>
                    <div style="color: var(--color-text-muted); font-size: 0.75rem; margin-top: -4px;">🏪 ${domain}</div>
                    <div style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">
                        <span class="alg-badge" style="font-size: 0.65rem;">${p.algorithm || "new"}</span>
                        ${p.api_version ? `<span class="snapshot-badge" style="background:rgba(99,102,241,0.1);color:#6366f1;padding:2px 8px;border-radius:var(--radius-full);font-size:0.65rem;">🔖 ${p.api_version}</span>` : ''}
                    </div>
                </div>
                <div class="card-footer" style="display: flex; flex-direction: column; gap: 8px; padding: 12px; border-top: 1px solid var(--border-color);">
                    <!-- Main Actions -->
                    <div style="display: flex; gap: 8px; width: 100%;">
                        <a href="${p.productUrl}" target="_blank" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px;">🛒 زيارة</a>
                        <button onclick='openDetailsModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px;">📊 تفاصيل أكثر</button>
                    </div>
                    <!-- Secondary & Danger Actions -->
                    <div style="display: flex; gap: 8px; width: 100%;">
                        <button onclick='openInfoModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px;">ℹ️ معلومات</button>
                        <button onclick="exportSingleSavedProduct('${escapedUrlForDelete}')" class="btn btn-secondary" title="تصدير JSON" style="flex: 0 0 auto; width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center;">📥</button>
                        <button class="btn btn-error" onclick="removeFromSaved('${escapedUrlForDelete}')" title="إزالة من المحفوظات" style="flex: 0 0 auto; width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.1); color: var(--color-error); border: 1px solid rgba(239, 68, 68, 0.2);">🗑️</button>
                    </div>
                </div>
            </article>
        `;
    })
    .join("");
  initVideoJs();
}

function initVideoJs(scope) {
  if (!vidObserver && typeof IntersectionObserver !== 'undefined') {
    vidObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          loadVideoPlaceholder(entry.target);
          vidObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: '200px' });
  }

  (scope || document).querySelectorAll('video.video-js').forEach(el => {
    if (el.dataset.vjsInited) return;
    el.dataset.vjsInited = '1';
    try {
      if (typeof videojs === 'function') {
        const player = videojs(el, { fluid: true, controls: true, preload: 'none' });
        player.on('play', () => {
          const all = videojs.getPlayers();
          Object.keys(all).forEach(id => {
            const p = all[id];
            if (p !== player && !p.paused()) p.pause();
          });
        });
      }
    } catch (e) { /* ignore */ }
  });

  (scope || document).querySelectorAll('.vid-placeholder:not([data-vid-loaded])').forEach(el => {
    el.dataset.vidLoaded = '1';
    if (vidObserver) {
      vidObserver.observe(el);
    } else {
      loadVideoPlaceholder(el);
    }
  });
}

async function setRating(url, rating) {
  const p = savedProducts.find((p) => p.productUrl === url);
  if (p) {
    try {
      const res = await fetch("/api/products/saved/rating", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_url: url, rating }),
      });
      if (res.ok) {
        p.rating = rating;
        renderSavedGrid();
      }
    } catch (err) {
      console.error("Error setting rating:", err);
      showToast("تعذر الاتصال بالسيرفر لتعديل التقييم", "error");
    }
  }
}

async function updateNotes(url, notes) {
  const p = savedProducts.find((p) => p.productUrl === url);
  if (p) {
    try {
      const res = await fetch("/api/products/saved/notes", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_url: url, notes }),
      });
      if (res.ok) {
        p.notes = notes;
        showToast("تم حفظ الملاحظات", "success");
      }
    } catch (err) {
      console.error("Error saving notes:", err);
      showToast("تعذر الاتصال بالسيرفر لحفظ الملاحظات", "error");
    }
  }
}

async function updateStatus(url, status) {
  const p = savedProducts.find((p) => p.productUrl === url);
  if (p) {
    try {
      const res = await fetch("/api/products/saved/status", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_url: url, status }),
      });
      if (res.ok) {
        p.status = status;
        renderSavedGrid();
        showToast(
          `تم نقل المنتج إلى: ${status === "active" ? "نشط" : status === "tested" ? "تمت التجربة" : "الأرشيف"}`,
          "info",
        );
      }
    } catch (err) {
      console.error("Error updating status:", err);
      showToast("تعذر تغيير الحالة.", "error");
    }
  }
}

async function updateProductCollection(url, collectionName) {
  const p = savedProducts.find((p) => p.productUrl === url);
  if (p) {
    try {
      const res = await fetch("/api/products/saved/collection", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_url: url, collection: collectionName }),
      });
      if (res.ok) {
        p.collection = collectionName;
        renderSavedGrid();
        showToast(`تم نقل المنتج لمجموعة: ${collectionName}`, "success");
      }
    } catch (err) {
      console.error("Error updating collection:", err);
      showToast("تعذر نقل المنتج للمجموعة.", "error");
    }
  }
}

async function importSavedAdsFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  event.target.value = '';

  try {
    const text = await file.text();
    const res = await fetch('/api/products/saved/import', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ raw_json: text })
    });
    if (!res.ok) throw new Error('فشل الاستيراد');
    const result = await res.json();
    showToast(`✅ تم استيراد ${result.inserted} منتج جديد، تحديث ${result.updated} منتج`);
    loadSavedProducts();
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

function downloadSavedJSON() {
  if (currentFiltered.length === 0) {
    showToast("لا توجد بيانات مفلترة لتحميلها!", "warning");
    return;
  }
  const dataStr = JSON.stringify(currentFiltered, null, 2);
  const blob = new Blob([dataStr], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `saved_products_${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast("تم تحميل ملف JSON بنجاح 📥", "success");
}

async function exportSingleSavedProduct(url) {
  const p = savedProducts.find(pr => pr.productUrl === url);
  if (!p) { showToast('المنتج غير موجود', 'warning'); return; }
  const dataStr = JSON.stringify(p, null, 2);
  const blob = new Blob([dataStr], { type: 'application/json' });
  const urlBlob = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = urlBlob;
  const safeTitle = (p.title || 'product').replace(/[^\w\s\-]/g, '_').slice(0, 40);
  a.download = `${safeTitle}_${(p.ad_start_date || '').slice(0, 10) || 'nodate'}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(urlBlob);
  showToast('✅ تم تصدير المنتج');
}

async function removeFromSaved(url) {
  if (confirm("هل أنت متأكد من إزالة هذا المنتج من المحفوظات؟")) {
    try {
      const res = await fetch("/api/products/saved/toggle", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_url: url }),
      });
      if (res.ok) {
        savedProducts = savedProducts.filter((p) => p.productUrl !== url);
        renderSavedGrid();
        showToast("تمت إزالة المنتج من المحفوظات.", "info");
      }
    } catch (err) {
      console.error("Error removing saved product:", err);
      showToast("تعذر حذف المنتج.", "error");
    }
  }
}

async function clearAllSaved() {
  if (confirm("هل أنت متأكد من مسح جميع المنتجات المحفوظة؟")) {
    try {
      const res = await fetch("/api/products/saved/clear", {
        method: "POST",
      });
      if (res.ok) {
        savedProducts = [];
        renderSavedGrid();
        showToast("تم مسح قائمة المحفوظات.", "info");
      }
    } catch (err) {
      console.error("Error clearing saved products:", err);
      showToast("تعذر مسح المحفوظات.", "error");
    }
  }
}

async function setupTheme() {
  const themeBtn = document.getElementById("theme-toggle-btn");
  if (!themeBtn) return;

  try {
    const res = await fetch("/api/settings/app-theme");
    if (res.ok) {
      const data = await res.json();
      const currentTheme = data.value || "light";
      document.documentElement.setAttribute("data-theme", currentTheme);
    }
  } catch (err) {
    console.error("Error fetching theme setting:", err);
  }

  themeBtn.onclick = async () => {
    const theme =
      document.documentElement.getAttribute("data-theme") === "dark"
        ? "light"
        : "dark";
    document.documentElement.setAttribute("data-theme", theme);
    try {
      await fetch("/api/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ key: "app-theme", value: theme }),
      });
    } catch (err) {
      console.error("Error saving theme setting:", err);
    }
  };
}

function showToast(message, type = "info") {
  const container = document.getElementById("toast-container");
  const t = document.createElement("div");
  t.className = `toast ${type}`;
  t.innerHTML = `<span>💡</span> <div>${message}</div>`;
  container.appendChild(t);
  setTimeout(() => t.classList.add("show"), 50);
  setTimeout(() => {
    t.classList.remove("show");
    setTimeout(() => t.remove(), 400);
  }, 3000);
}

// =========================================
// 9. Product Details Modal Controller
// =========================================
let currentProductForDetails = null;
let currentProductDetailsWithAnalysis = null;

async function openDetailsModal(product) {
  currentProductForDetails = product;

  const modal = document.getElementById("details-modal");
  if (!modal) return;
  modal.style.display = "flex";

  // Set basic details
  const priceInput = document.getElementById("details-price-input");
  if (priceInput) {
    priceInput.value = product.actualPrice || product.price_1 || "0";
  }
  document.getElementById("details-title").textContent =
    product.title || "تفاصيل الإعلان والنشاط";
  document.getElementById("details-info-title").textContent =
    product.title || "بدون عنوان";
  document.getElementById("details-info-desc").textContent =
    product.ad_body || product.title || "لا يوجد نص تفصيلي للإعلان.";

  // Populate all raw JSON properties in scrollable container
  const rawDataContainer = document.getElementById("details-raw-data-list");
  if (rawDataContainer) {
    let listHtml = "";
    for (const [key, value] of Object.entries(product)) {
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

  // Populate media items
  const mediaContainer = document.getElementById("details-media");
  const imageUrls = (product.ad_image_urls || "")
    .split(";")
    .map((u) => u.trim())
    .filter(Boolean);
  const videoUrls = (product.ad_video_urls || "")
    .split(";")
    .map((u) => u.trim())
    .filter(Boolean);

  const countryMeta = COUNTRIES_LIST.find((c) => c.code === product.country);
  const countryFlag = countryMeta ? countryMeta.flag : "🌍";
  const overlayText = `${countryFlag} إعلان نشط`;

  let mediaHtml = "";
  if (videoUrls.length > 0) {
    videoUrls.forEach((vUrl, i) => {
      mediaHtml += `
        <div class="details-media-item">
          <video class="video-js vjs-big-play-centered" controls autoplay muted loop playsinline>
            <source src="${vUrl}" type="video/mp4">
          </video>
          <div class="details-media-overlay-text">${overlayText}</div>
        </div>
      `;
    });
    imageUrls.forEach((imgUrl, i) => {
      mediaHtml += `
        <div class="details-media-item">
          <img src="${imgUrl}" alt="${product.title}">
          <div class="details-media-overlay-text">${overlayText}</div>
        </div>
      `;
    });
  } else if (imageUrls.length > 0) {
    imageUrls.forEach((imgUrl, i) => {
      mediaHtml += `
        <div class="details-media-item">
          <img src="${imgUrl}" alt="${product.title}">
          <div class="details-media-overlay-text">${overlayText}</div>
        </div>
      `;
    });
  } else {
    mediaHtml = `<div class="no-media" style="grid-column: 1/-1; height: 200px;"><span>📦 لا توجد وسائط معاينة</span></div>`;
  }
  mediaContainer.innerHTML = mediaHtml;
  initVideoJs(mediaContainer);

  // Set up Facebook library link
  let domain = "متجر خارجي";
  try {
    if (product.productUrl)
      domain = new URL(product.productUrl).hostname.replace("www.", "");
  } catch (e) {}
  const fbBtn = document.getElementById("details-fb-library-btn");
  if (fbBtn) {
    fbBtn.href = `https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=MA&q=${encodeURIComponent(product.title || "")}`;
  }

  // Update store list button state
  const storeBtn = document.getElementById("details-store-btn");
  const isStoreAdded = watchedStores.includes(domain);
  if (storeBtn) {
    if (isStoreAdded) {
      storeBtn.textContent = "🟢 تم إضافة المتجر للقائمة";
      storeBtn.className = "btn btn-success";
    } else {
      storeBtn.textContent = "➕ إضافة المتجر للقائمة";
      storeBtn.className = "btn btn-secondary";
    }
  }

  // Update save button and collection dropdown state
  const saveBtn = document.getElementById("details-save-btn");
  const collectionSelect = document.getElementById("details-collection-select");
  const isSaved = savedProducts.some(
    (p) => p.productUrl === product.productUrl,
  );

  if (collectionSelect) {
    collectionSelect.style.display = "inline-block";
    const productInSaved = savedProducts.find(
      (p) => p.productUrl === product.productUrl,
    );
    const savedCol = productInSaved ? productInSaved.collection : "عامة";
    collectionSelect.innerHTML = collections
      .map(
        (c) =>
          `<option value="${c}" ${savedCol === c ? "selected" : ""}>📁 ${c}</option>`,
      )
      .join("");
  }

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
    saveBtn.onclick = () => {
      toggleSaveProductDirectly(product);
      const stillSaved = savedProducts.some(
        (p) => p.productUrl === product.productUrl,
      );
      if (stillSaved) {
        saveBtn.textContent = "⭐ محفوظ";
        saveBtn.style.background = "var(--color-success)";
        saveBtn.style.color = "white";
        if (collectionSelect) {
          collectionSelect.style.display = "inline-block";
          const productInSaved = savedProducts.find(
            (p) => p.productUrl === product.productUrl,
          );
          collectionSelect.innerHTML = collections
            .map(
              (c) =>
                `<option value="${c}" ${(productInSaved.collection || "عامة") === c ? "selected" : ""}>📁 ${c}</option>`,
            )
            .join("");
        }
      } else {
        saveBtn.textContent = "احفظ المنتج";
        saveBtn.style.background = "transparent";
        saveBtn.style.color = "var(--color-success)";
        if (collectionSelect) collectionSelect.style.display = "none";
      }
    };
  }

  // Draw timeline loading state
  document.getElementById("details-chart").innerHTML = `
    <div style="width:100%; text-align:center; padding: 2rem 0; color: var(--color-text-muted);">
      ⏳ جاري تحميل مخطط النشاط...
    </div>
  `;

  // Fetch activity from local API (cached or from external)
  let resData = await fetchActivityData(product.productUrl, false);
  let activityEntries = null;
  let backendStrategy = null;

  if (resData) {
    activityEntries = resData.activity;
    backendStrategy = resData.strategy_analysis;
  }

  if (!activityEntries || activityEntries.length === 0) {
    activityEntries = generateSimulatedActivity(product);
  }

  // تمرير البيانات إلى دالة الرسم
  renderTimelineAndMetrics(product, activityEntries);

  // تفعيل التحليل الواقعي القادم من الـ Controller فوراً إذا وُجد
  if (backendStrategy) {
    const badgeElem = document.querySelector(".strategy-badge");
    if (badgeElem) badgeElem.textContent = backendStrategy.badge;

    const textElem = document.getElementById("details-analysis-text");
    if (textElem) textElem.textContent = backendStrategy.text;
  }
}

async function toggleSaveProductDirectly(product) {
  try {
    const res = await fetch("/api/products/saved/toggle", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ product_url: product.productUrl }),
    });
    if (res.ok) {
      const data = await res.json();
      if (data.action === "saved") {
        product.saved_at = new Date().toISOString();
        product.rating = 0;
        product.notes = "";
        product.status = "active";
        product.collection = "عامة";
        savedProducts.push(product);
        showToast("تم حفظ المنتج بنجاح! ⭐", "success");
      } else {
        savedProducts = savedProducts.filter(
          (p) => p.productUrl !== product.productUrl,
        );
        showToast("تمت إزالة المنتج من المحفوظات.", "info");
      }
      renderSavedGrid();

      const saveBtn = document.getElementById("details-save-btn");
      if (
        saveBtn &&
        currentProductForDetails &&
        currentProductForDetails.productUrl === product.productUrl
      ) {
        const stillSaved = data.action === "saved";
        if (stillSaved) {
          saveBtn.textContent = "⭐ محفوظ";
          saveBtn.style.background = "var(--color-success)";
          saveBtn.style.color = "white";
        } else {
          saveBtn.textContent = "احفظ المنتج";
          saveBtn.style.background = "transparent";
          saveBtn.style.color = "var(--color-success)";
        }
      }
    }
  } catch (err) {
    console.error("Error toggling save directly:", err);
    showToast("تعذر الاتصال بالسيرفر لحفظ المنتج.", "error");
  }
}

function generateSimulatedActivity(product) {
  // Seed a simple PRNG from product_url for deterministic output
  let seed = 0;
  const url = product.productUrl || product.product_url || "";
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
  const gap1 = 45;
  for (let i = 0; i < numInt2; i++) {
    const start = new Date(baseDate);
    start.setDate(start.getDate() + gap1 + i * 3);
    const end = new Date(start);
    end.setDate(end.getDate() + 20 + Math.floor(pseudoRand() * 30));
    entries.push({
      ad_start_date: start.toISOString().split("T")[0],
      ad_end_date: end.toISOString().split("T")[0],
      ad_video_urls: videoUrls[(i + numInt1) % videoUrls.length] || "",
    });
  }

  const numInt3 = Math.max(1, totalAds - numInt1 - numInt2);
  const gap2 = 120;
  const today = new Date();
  for (let i = 0; i < numInt3; i++) {
    const start = new Date(baseDate);
    start.setDate(start.getDate() + gap2 + i * 4);
    const end = new Date();
    end.setDate(today.getDate() + 5 + i * 2);
    entries.push({
      ad_start_date: start.toISOString().split("T")[0],
      ad_end_date: end.toISOString().split("T")[0],
      ad_video_urls:
        videoUrls[(i + numInt1 + numInt2) % videoUrls.length] || "",
    });
  }

  return entries;
}

// Shared functions (renderTimelineAndMetrics, getMonthNameAr, formatArDateString, formatMetricRange, generateAdAnalysis) are loaded from analysis-helper.js

function closeDetailsModal() {
  const modal = document.getElementById("details-modal");
  if (modal) modal.style.display = "none";
}

function openDetailsHelpModal() {
  const modal = document.getElementById("details-help-modal");
  if (modal) modal.style.display = "flex";
}

function closeDetailsHelpModal() {
  const modal = document.getElementById("details-help-modal");
  if (modal) modal.style.display = "none";
}

// Close modals when clicking outside their card area
window.addEventListener("click", (event) => {
  const helpModal = document.getElementById("details-help-modal");
  const detailsModal = document.getElementById("details-modal");
  if (event.target === helpModal) {
    closeDetailsHelpModal();
  } else if (event.target === detailsModal) {
    closeDetailsModal();
  }
});

async function toggleStoreListAction() {
  if (!currentProductForDetails) return;

  let domain = "متجر خارجي";
  try {
    if (currentProductForDetails.productUrl) {
      domain = new URL(currentProductForDetails.productUrl).hostname.replace(
        "www.",
        "",
      );
    }
  } catch (e) {}

  const btn = document.getElementById("details-store-btn");

  try {
    const res = await fetch("/api/products/watchlist/toggle", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ domain }),
    });

    if (res.ok) {
      const data = await res.json();
      if (data.action === "removed") {
        watchedStores = watchedStores.filter((d) => d !== domain);
        if (btn) {
          btn.textContent = "➕ إضافة المتجر للقائمة";
          btn.className = "btn btn-secondary";
        }
        showToast("تمت إزالة المتجر من قائمتك الخاصة", "info");
      } else {
        if (!watchedStores.includes(domain)) {
          watchedStores.push(domain);
        }
        if (btn) {
          btn.textContent = "🟢 تم إضافة المتجر للقائمة";
          btn.className = "btn btn-success";
        }
        showToast("تمت إضافة المتجر لقائمتك بنجاح! 🛍️", "success");
      }
    }
  } catch (err) {
    console.error("Error toggling store watchlist:", err);
    showToast("تعذر الاتصال بالسيرفر لتعديل قائمة المتاجر.", "error");
  }
}

let vidObserver = null;

function loadVideoPlaceholder(ph) {
  const src = ph.dataset.vidSrc;
  const poster = ph.dataset.vidPoster;
  if (poster) {
    const vid = createVidEl(ph.id, src, poster);
    ph.parentNode.replaceChild(vid, ph);
    initVjs(vid);
    return;
  }
  ph.style.display = 'block';
  ph.style.position = 'relative';
  ph.innerHTML = '';
  const vid = createVidEl(ph.id, src, '');
  vid.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;';
  ph.appendChild(vid);
  const overlay = document.createElement('div');
  overlay.className = 'vid-placeholder-overlay';
  overlay.innerHTML = '<div class="vid-placeholder-bg"></div><div class="vid-play-btn">▶</div>';
  ph.appendChild(overlay);
  const player = initVjs(vid);
  overlay.addEventListener('click', (e) => {
    e.stopPropagation();
    overlay.style.display = 'none';
    const p = player || videojs.getPlayer(vid.id) || videojs(vid);
    if (p && typeof p.play === 'function') p.play();
  });
}

function createVidEl(id, src, posterUrl) {
  const vid = document.createElement('video');
  vid.id = id ? id.replace('vp-', 'vjs-') : '';
  vid.className = 'video-js vjs-big-play-centered';
  vid.controls = true;
  vid.playsInline = true;
  vid.preload = 'none';
  if (posterUrl) vid.poster = posterUrl;
  const source = document.createElement('source');
  source.src = src;
  source.type = 'video/mp4';
  vid.appendChild(source);
  return vid;
}

function initVjs(vid) {
  try {
    if (typeof videojs === 'function' && !vid.dataset.vjsInited) {
      vid.dataset.vjsInited = '1';
      const player = videojs(vid, { fluid: true, controls: true, preload: 'none' });
      player.on('play', () => {
        const all = videojs.getPlayers();
        Object.keys(all).forEach(id => {
          const p = all[id];
          if (p !== player && !p.paused()) p.pause();
        });
      });
      return player;
    }
  } catch (e) { /* ignore */ }
}

let currentInfoProduct = null;

function openInfoModal(p) {
  currentInfoProduct = p;
  const modal = document.getElementById("saved-info-modal");
  if (!modal) return;

  const imageUrls = (p.ad_image_urls || "").split(";").filter(Boolean);
  const videoUrls = (p.ad_video_urls || "").split(";").filter(Boolean);

  let domain = "متجر خارجي";
  try {
    if (p.productUrl)
      domain = new URL(p.productUrl).hostname.replace("www.", "");
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
      else if (diffDays < 30) timeAgoText = ` (منذ ${Math.floor(diffDays / 7)} أسبوع)`;
      else timeAgoText = ` (منذ ${Math.floor(diffDays / 30)} شهر)`;
    }
  }

  document.getElementById("saved-info-title").textContent = p.title || "بدون عنوان";
  document.getElementById("saved-info-domain").textContent = `🏪 ${domain}`;
  document.getElementById("saved-info-ads").textContent = p.ads_count || 0;
  document.getElementById("saved-info-images").textContent = imageUrls.length;
  document.getElementById("saved-info-creatives").textContent = p.avg_creatives || 1;
  document.getElementById("saved-info-date").textContent = `${p.ad_start_date || "--"}${timeAgoText}`;
  document.getElementById("saved-info-ad-title").textContent = `💬 ${p.ad_title || "نص الإعلان"}`;
  document.getElementById("saved-info-ad-body").textContent = p.ad_body || "لا يوجد نص تفصيلي.";

  // Rating stars
  let starsHtml = "";
  const escUrl = (p.productUrl || "").replace(/'/g, "\\'");
  for (let i = 1; i <= 5; i++) {
    starsHtml += `<span class="star ${i <= (p.rating || 0) ? "filled" : ""}" onclick="setInfoRating('${escUrl}', ${i})">★</span>`;
  }
  document.getElementById("saved-info-stars").innerHTML = starsHtml;

  // Notes
  document.getElementById("saved-info-notes").value = p.notes || "";

  // Status
  document.getElementById("saved-info-status").value = p.status || "active";

  // Collection
  const collSelect = document.getElementById("saved-info-collection");
  collSelect.innerHTML = collections.map((c) =>
    `<option value="${c}" ${(p.collection || "عامة") === c ? "selected" : ""}>📁 ${c}</option>`
  ).join("");

  // Visit button
  document.getElementById("saved-info-visit-btn").onclick = () => {
    if (p.productUrl) window.open(p.productUrl, "_blank");
  };

  modal.style.display = "flex";
}

function closeInfoModal() {
  const modal = document.getElementById("saved-info-modal");
  if (modal) modal.style.display = "none";
  currentInfoProduct = null;
}

async function setInfoRating(url, rating) {
  await setRating(url, rating);
  if (currentInfoProduct) {
    currentInfoProduct.rating = rating;
    openInfoModal(currentInfoProduct);
  }
}

function handleInfoNotesChange(val) {
  if (!currentInfoProduct) return;
  const url = (currentInfoProduct.productUrl || "").replace(/'/g, "\\'");
  updateNotes(url, val);
  currentInfoProduct.notes = val;
}

function handleInfoStatusChange(val) {
  if (!currentInfoProduct) return;
  const url = (currentInfoProduct.productUrl || "").replace(/'/g, "\\'");
  updateStatus(url, val);
  currentInfoProduct.status = val;
}

function handleInfoCollectionChange(val) {
  if (!currentInfoProduct) return;
  const url = (currentInfoProduct.productUrl || "").replace(/'/g, "\\'");
  updateProductCollection(url, val);
  currentInfoProduct.collection = val;
}

// Close info modal when clicking overlay
document.addEventListener("click", (event) => {
  const modal = document.getElementById("saved-info-modal");
  if (event.target === modal) closeInfoModal();
});

function showProductAnalysisToast() {
  showToast(
    "📊 جاري بدء تحليل أداء المنتج بالذكاء الاصطناعي وتجهيز لوحة المؤشرات...",
    "info",
  );
}

function showAdAnalysisToast() {
  showToast(
    "✨ جاري فحص زوايا التسويق، العروض والـ Copywriting الخاص بالإعلان...",
    "success",
  );
}

function downloadProductMedia() {
  if (!currentProductForDetails) return;
  const videoUrls = (currentProductForDetails.ad_video_urls || "")
    .split(";")
    .filter(Boolean);
  const imageUrls = (currentProductForDetails.ad_image_urls || "")
    .split(";")
    .filter(Boolean);
  const mediaUrl = videoUrls[0] || imageUrls[0];

  if (mediaUrl) {
    const a = document.createElement("a");
    a.href = mediaUrl;
    a.download = `media_${currentProductForDetails.title.slice(0, 10)}`;
    a.target = "_blank";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast("جاري تنزيل ملف الميديا... 📥", "success");
  } else {
    showToast("لا توجد ميديا صالحة للتحميل.", "warning");
  }
}

// =========================================
// 10. Collections / Groups Management
// =========================================
function openCollectionsModal() {
  const modal = document.getElementById("collections-modal");
  if (modal) {
    modal.style.display = "flex";
    renderCollectionsList();
  }
}

function closeCollectionsModal() {
  const modal = document.getElementById("collections-modal");
  if (modal) modal.style.display = "none";
}

function renderCollectionsList() {
  const container = document.getElementById("collections-list-container");
  if (!container) return;

  container.innerHTML = collections
    .map((c) => {
      const count = savedProducts.filter(
        (p) => (p.collection || "عامة") === c,
      ).length;
      const isDefault = c === "عامة";

      return `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem;">
                <span style="font-weight: 600;">📁 ${c} <span style="font-size: 0.75rem; color: var(--color-text-muted);">(${count} منتج)</span></span>
                ${
                  !isDefault && count === 0
                    ? `
                    <button onclick="handleDeleteCollection('${c.replace(/'/g, "\\'")}')" style="background: none; border: none; cursor: pointer; color: var(--color-error); font-size: 1rem;" title="حذف المجموعة">🗑️</button>
                `
                    : ""
                }
            </div>
        `;
    })
    .join("");
}

async function handleAddCollection() {
  const input = document.getElementById("new-collection-input");
  const name = input.value.trim();
  if (!name) {
    showToast("يرجى إدخال اسم للمجموعة!", "warning");
    return;
  }
  if (collections.includes(name)) {
    showToast("هذه المجموعة موجودة بالفعل!", "warning");
    return;
  }

  try {
    const res = await fetch("/api/products/collections", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name }),
    });
    if (res.ok) {
      collections.push(name);
      input.value = "";
      renderCollectionsList();
      populateCollectionFilters();
      renderSavedGrid();
      showToast(`تمت إضافة مجموعة "${name}" بنجاح!`, "success");
    }
  } catch (err) {
    console.error("Error adding collection:", err);
    showToast("تعذر الاتصال بالسيرفر لإضافة المجموعة.", "error");
  }
}

async function handleDeleteCollection(name) {
  if (confirm(`هل أنت متأكد من حذف مجموعة "${name}"؟`)) {
    try {
      const res = await fetch("/api/products/collections/delete", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name }),
      });
      if (res.ok) {
        collections = collections.filter((c) => c !== name);
        savedProducts.forEach((p) => {
          if (p.collection === name) p.collection = "عامة";
        });
        renderCollectionsList();
        populateCollectionFilters();
        renderSavedGrid();
        showToast(`تمت إزالة مجموعة "${name}".`, "info");
      }
    } catch (err) {
      console.error("Error deleting collection:", err);
      showToast("تعذر حذف المجموعة.", "error");
    }
  }
}

async function handleDetailsCollectionChange() {
  if (!currentProductForDetails) return;
  const select = document.getElementById("details-collection-select");
  if (!select) return;

  const colName = select.value;
  const p = savedProducts.find(
    (x) => x.productUrl === currentProductForDetails.productUrl,
  );
  if (p) {
    try {
      const res = await fetch("/api/products/saved/collection", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_url: p.productUrl,
          collection: colName,
        }),
      });
      if (res.ok) {
        p.collection = colName;
        currentProductForDetails.collection = colName;
        renderSavedGrid();
        showToast(`تم نقل المنتج لمجموعة: ${colName}`, "success");
      }
    } catch (err) {
      console.error("Error changing collection:", err);
      showToast("تعذر تغيير المجموعة.", "error");
    }
  }
}

function downloadProductDataJSON() {
  const targetData =
    currentProductDetailsWithAnalysis || currentProductForDetails;
  if (!targetData) {
    showToast("لا توجد بيانات منتج صالحة للتحميل.", "warning");
    return;
  }
  const dataStr = JSON.stringify(targetData, null, 2);
  const blob = new Blob([dataStr], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `product_data_${currentProductForDetails.title ? currentProductForDetails.title.slice(0, 15).replace(/\s+/g, "_") : "ad"}_${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast("تم تحميل بيانات المنتج بصيغة JSON! 📥", "success");
}

// الكود الجديد للدالة بعد التعديل
async function fetchActivityData(productUrl, refresh = false) {
  try {
    const params = new URLSearchParams({ product_url: productUrl });
    if (refresh) params.set("refresh", "1");
    const res = await fetch(`/api/products/activity?${params.toString()}`);
    if (!res.ok) return null;
    const result = await res.json();
    if (result.source === "error") return null;

    // التعديل: إرجاع كائن يحتوي على النشاط والتحليل الذكي معاً
    return {
      activity: result.activity || null,
      strategy_analysis: result.strategy_analysis || null,
    };
  } catch (e) {
    console.warn("Failed to fetch activity data", e);
    return null;
  }
}

async function refreshActivityData() {
  if (!currentProductForDetails) return;
  const product = currentProductForDetails;
  document.getElementById("details-chart").innerHTML = `
    <div style="width:100%; text-align:center; padding: 2rem 0; color: var(--color-text-muted);">
      ⏳ جاري تحديث بيانات النشاط...
    </div>
  `;

  let resData = await fetchActivityData(product.productUrl, true);
  let activityEntries = null;
  let backendStrategy = null;

  if (resData) {
    activityEntries = resData.activity;
    backendStrategy = resData.strategy_analysis;
  }

  if (!activityEntries || activityEntries.length === 0) {
    activityEntries = generateSimulatedActivity(product);
  }

  renderTimelineAndMetrics(product, activityEntries);

  if (backendStrategy) {
    const badgeElem = document.querySelector(".strategy-badge");
    if (badgeElem) badgeElem.textContent = backendStrategy.badge;

    const textElem = document.getElementById("details-analysis-text");
    if (textElem) textElem.textContent = backendStrategy.text;
  }

  showToast("✅ تم تحديث بيانات النشاط والتحليل الاستراتيجي", "success");
}

function updateDetailsRawDataView() {
  const product = currentProductForDetails;
  if (!product) return;
  const rawDataContainer = document.getElementById("details-raw-data-list");
  if (!rawDataContainer) return;
  
  let listHtml = "";
  for (const [key, value] of Object.entries(product)) {
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
  if (currentProductDetailsWithAnalysis && currentProductDetailsWithAnalysis.computed_metrics) {
    for (const [key, value] of Object.entries(
      currentProductDetailsWithAnalysis.computed_metrics,
    )) {
      if (value !== null && value !== undefined && value !== "") {
        let valStr = String(value);
        if (valStr.length > 80) valStr = valStr.slice(0, 80) + "...";
        listHtml += `
          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding: 4px 0; font-family: sans-serif; gap: 10px; background: var(--bg-card); opacity: 0.9;">
            <span style="color: var(--color-success); font-weight: 600; text-transform: capitalize;">computed_${key}:</span>
            <span style="word-break: break-all; text-align: right; color: var(--color-text-main); font-weight: 500;">${valStr}</span>
          </div>
        `;
      }
    }
  }
  rawDataContainer.innerHTML =
    listHtml ||
    `<div style="text-align: center; padding: 10px; color: var(--color-text-muted);">لا توجد بيانات إضافية</div>`;
}

async function handleDetailsPriceChange(val) {
  if (!currentProductForDetails) return;
  
  currentProductForDetails.actualPrice = val;
  currentProductForDetails.price_1 = val;
  if (currentProductDetailsWithAnalysis) {
    currentProductDetailsWithAnalysis.actualPrice = val;
    currentProductDetailsWithAnalysis.price_1 = val;
  }
  
  if (typeof allProducts !== 'undefined') {
    const pMain = allProducts.find(p => p.productUrl === currentProductForDetails.productUrl);
    if (pMain) {
      pMain.actualPrice = val;
      pMain.price_1 = val;
    }
  }
  if (typeof currentFilteredProducts !== 'undefined') {
    const pFiltered = currentFilteredProducts.find(p => p.productUrl === currentProductForDetails.productUrl);
    if (pFiltered) {
      pFiltered.actualPrice = val;
      pFiltered.price_1 = val;
    }
  }
  if (typeof currentFiltered !== 'undefined') {
    const pFilteredSaved = currentFiltered.find(p => p.productUrl === currentProductForDetails.productUrl);
    if (pFilteredSaved) {
      pFilteredSaved.actualPrice = val;
      pFilteredSaved.price_1 = val;
    }
  }
  
  const pSaved = savedProducts.find(p => p.productUrl === currentProductForDetails.productUrl);
  if (pSaved) {
    pSaved.actualPrice = val;
    pSaved.price_1 = val;
    
    try {
      const res = await fetch("/api/products/saved/price", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_url: pSaved.productUrl,
          price: val
        })
      });
      if (res.ok) {
        showToast("✅ تم تحديث سعر المنتج في قاعدة البيانات", "success");
      }
    } catch (e) {
      console.error("Failed to save price update to database", e);
    }
  } else {
    showToast("⚠️ السعر محدث مؤقتاً. لحفظه في قاعدة البيانات بشكل دائم، يرجى حفظ المنتج أولاً.", "info");
  }
  
  updateDetailsRawDataView();
}
