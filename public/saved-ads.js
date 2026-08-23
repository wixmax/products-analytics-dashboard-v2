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

// State for Progressive Lazy Rendering in saved-ads.js
let currentSavedList = [];
let renderedSavedCount = 0;
const SAVED_PER_BATCH = 16;
let savedScrollObserver = null;

function renderSavedGrid() {
  const container = document.getElementById("saved-products-container");
  if (!container) return;

  const searchQuery = (document.getElementById("saved-search")?.value || "").trim().toLowerCase();
  const sortOrder = document.getElementById("saved-sort")?.value || "newest";
  const statusFilter = document.getElementById("status-filter")?.value || "all";
  const collectionFilter = document.getElementById("collection-filter")?.value || "all";
  const countryFilter = document.getElementById("country-filter")?.value || "all";

  let filtered = savedProducts.filter((p) => {
    const matchesSearch =
      !searchQuery ||
      (p.title && p.title.toLowerCase().includes(searchQuery)) ||
      (p.ad_body && p.ad_body.toLowerCase().includes(searchQuery)) ||
      (p.ad_title && p.ad_title.toLowerCase().includes(searchQuery));

    const productStatus = p.status || "active";
    const matchesStatus =
      statusFilter === "all" || productStatus === statusFilter;

    const productCollection = p.collection || "عامة";
    const matchesCollection =
      collectionFilter === "all" || productCollection === collectionFilter;

    const matchesCountry =
      countryFilter === "all" || !countryFilter || p.country === countryFilter;

    return matchesSearch && matchesStatus && matchesCollection && matchesCountry;
  });

  currentFiltered = filtered;

  // Sorting
  filtered.sort((a, b) => {
    if (sortOrder === "newest" || sortOrder === "date-desc")
      return new Date(b.saved_at || 0) - new Date(a.saved_at || 0);
    if (sortOrder === "oldest" || sortOrder === "date-asc")
      return new Date(a.saved_at || 0) - new Date(b.saved_at || 0);
    if (sortOrder === "rating-desc") return (b.rating || 0) - (a.rating || 0);
    if (sortOrder === "rating-asc") return (a.rating || 0) - (b.rating || 0);
    return 0;
  });

  currentSavedList = filtered;
  renderedSavedCount = 0;

  if (savedScrollObserver) {
    savedScrollObserver.disconnect();
    savedScrollObserver = null;
  }

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

  container.innerHTML = "";
  loadNextSavedBatch();
  setupSavedScrollObserver();
}


function loadNextSavedBatch() {
  const container = document.getElementById("saved-products-container");
  if (!container || renderedSavedCount >= currentSavedList.length) return;

  const start = renderedSavedCount;
  const end = Math.min(start + SAVED_PER_BATCH, currentSavedList.length);
  const batch = currentSavedList.slice(start, end);
  renderedSavedCount = end;

  const batchHtml = batch.map((p) => buildSavedCardHtml(p)).join("");
  const sentinel = document.getElementById("saved-scroll-sentinel");
  if (sentinel) {
    sentinel.insertAdjacentHTML("beforebegin", batchHtml);
  } else {
    container.insertAdjacentHTML("beforeend", batchHtml);
  }

  initVideoJs(container);
  if (typeof ensureVideoThumbnails === "function") {
    ensureVideoThumbnails(container);
  }

  if (renderedSavedCount >= currentSavedList.length) {
    const s = document.getElementById("saved-scroll-sentinel");
    if (s) s.remove();
  }
}

function setupSavedScrollObserver() {
  const container = document.getElementById("saved-products-container");
  if (!container || renderedSavedCount >= currentSavedList.length) return;

  let sentinel = document.getElementById("saved-scroll-sentinel");
  if (!sentinel) {
    sentinel = document.createElement("div");
    sentinel.id = "saved-scroll-sentinel";
    sentinel.className = "infinite-scroll-sentinel";
    sentinel.style.gridColumn = "1 / -1";
    sentinel.innerHTML = "<span>⏳ جاري تحميل المزيد من المنتجات...</span>";
    container.appendChild(sentinel);
  }

  if (typeof IntersectionObserver !== "undefined") {
    savedScrollObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && renderedSavedCount < currentSavedList.length) {
            loadNextSavedBatch();
          }
        });
      },
      { rootMargin: "300px" }
    );
    savedScrollObserver.observe(sentinel);
  }
}

function formatSavedDate(dateStr) {
  if (!dateStr) return "غير محدد";
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    const hh = String(d.getHours()).padStart(2, "0");
    const min = String(d.getMinutes()).padStart(2, "0");
    return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
  } catch (e) {
    return dateStr;
  }
}

function buildSavedCardHtml(p) {
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

  // Stars HTML
  let starsHtml = "";
  for (let i = 1; i <= 5; i++) {
    const escapedUrl = (p.productUrl || "").replace(/'/g, "\\'");
    starsHtml += `<span class="star ${i <= (p.rating || 0) ? "filled" : ""}" onclick="setRating('${escapedUrl}', ${i})">★</span>`;
  }

  const escapedUrlForDelete = (p.productUrl || "").replace(/'/g, "\\'");
  const savedDateFormatted = formatSavedDate(p.saved_at || p.created_at);

  return `
        <article class="product-card saved-product-card card-lazy-load" id="card-${safeId}">
            <div class="product-media">
                ${
                  videoUrls.length > 0
                    ? `<div class="vid-placeholder" data-vid-src="${videoUrls[0]}" data-vid-poster="${imageUrls[0] || ""}" data-product-id="${p.id || ""}" data-product-url="${p.productUrl || ""}" id="vp-${safeId}">${imageUrls[0] ? `<img src="${imageUrls[0]}" alt="" class="vid-placeholder-img">` : `<div class="vid-placeholder-bg"></div>`}<div class="vid-play-btn">▶</div></div>`
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
                <div style="margin-top: 6px; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">المجموعة:</span>
                        <select onchange="updateProductCollection('${escapedUrlForDelete}', this.value)" style="padding: 2px 6px; font-size: 0.75rem; border-radius: 4px; background: var(--bg-card); color: var(--color-text-main); border: 1px solid var(--border-color);">
                            ${collections.map((c) => `<option value="${c}" ${p.collection === c ? "selected" : ""}>${c}</option>`).join("")}
                        </select>
                    </div>
                    <div style="font-size: 0.72rem; color: #6366f1; background: rgba(99, 102, 241, 0.08); padding: 2px 8px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="تاريخ الحفظ في المفضلة">
                        📅 <span>تاريخ الحفظ: ${savedDateFormatted}</span>
                    </div>
                </div>
                <div class="rating-stars" style="margin-top: 6px;">
                    ${starsHtml}
                </div>
            </div>
            <div class="card-footer" style="display: flex; flex-direction: column; gap: 8px; padding: 12px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; gap: 8px; width: 100%;">
                    <a href="${p.productUrl}" target="_blank" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px; display: flex; align-items: center; justify-content: center;">🛒 زيارة</a>
                    <button onclick='openDetailsModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px;">📊 تفاصيل أكثر</button>
                </div>
                <div style="display: flex; gap: 8px; width: 100%;">
                    <button onclick='openInfoModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 1; font-size: 0.8rem; padding: 0.5rem; height: 36px;">ℹ️ معلومات</button>
                    <button onclick="exportSingleSavedProduct('${escapedUrlForDelete}')" class="btn btn-secondary" title="تصدير JSON" style="flex: 0 0 auto; width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center;">📥</button>
                    <button class="btn btn-error" onclick="removeFromSaved('${escapedUrlForDelete}')" title="إزالة من المحفوظات" style="flex: 0 0 auto; width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; background: rgba(239, 68, 68, 0.1); color: var(--color-error); border: 1px solid rgba(239, 68, 68, 0.2);">🗑️</button>
                </div>
            </div>
        </article>
    `;
}

function initBackToTop() {
  if (document.getElementById("back-to-top-btn")) return;
  const btn = document.createElement("button");
  btn.id = "back-to-top-btn";
  btn.className = "back-to-top-btn";
  btn.setAttribute("aria-label", "التوجه إلى الأعلى");
  btn.setAttribute("title", "التوجه إلى الأعلى");
  btn.innerHTML = "⬆️";
  document.body.appendChild(btn);

  const mainContent = document.querySelector(".main-content");

  const toggleBtn = () => {
    const windowScroll = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    const mainScroll = mainContent ? mainContent.scrollTop : 0;
    const currentScroll = Math.max(windowScroll, mainScroll);

    if (currentScroll > 150) {
      btn.classList.add("visible");
    } else {
      btn.classList.remove("visible");
    }
  };

  window.addEventListener("scroll", toggleBtn, { passive: true });
  document.addEventListener("scroll", toggleBtn, { passive: true });
  if (mainContent) {
    mainContent.addEventListener("scroll", toggleBtn, { passive: true });
  }

  toggleBtn();

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
    document.documentElement.scrollTo({ top: 0, behavior: "smooth" });
    document.body.scrollTo({ top: 0, behavior: "smooth" });
    if (mainContent) {
      mainContent.scrollTo({ top: 0, behavior: "smooth" });
    }
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initBackToTop);
} else {
  initBackToTop();
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
const updateCollection = updateProductCollection;

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

  const localTheme = localStorage.getItem("app-theme");
  if (localTheme) {
    document.documentElement.setAttribute("data-theme", localTheme);
  }

  try {
    const res = await fetch("/api/settings/app-theme");
    if (res.ok) {
      const data = await res.json();
      if (data.value) {
        document.documentElement.setAttribute("data-theme", data.value);
        localStorage.setItem("app-theme", data.value);
      }
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
    localStorage.setItem("app-theme", theme);
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
var currentProductForDetails = window.currentProductForDetails || null;
var currentProductDetailsWithAnalysis = window.currentProductDetailsWithAnalysis || null;

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

var vidObserver = window.vidObserver || null;

function loadVideoPlaceholder(ph) {
  if (ph.dataset.vidClickHandled) return;
  ph.dataset.vidClickHandled = "1";

  if (!ph.querySelector(".vid-play-btn")) {
    const playBtn = document.createElement("div");
    playBtn.className = "vid-play-btn";
    playBtn.textContent = "▶";
    ph.appendChild(playBtn);
  }

  let activePlayer = null;
  let activeVid = null;
  let mountedTarget = null;

  const ensureVideoMounted = () => {
    if (activeVid && mountedTarget) return { vid: activeVid, player: activePlayer, container: mountedTarget };

    const src = ph.getAttribute("data-vid-src");
    const poster = ph.getAttribute("data-vid-poster") || "";
    if (!src || !ph.parentNode) return null;

    activeVid = createVidEl(ph.id, src, poster);
    mountedTarget = activeVid;
    ph.parentNode.replaceChild(activeVid, ph);
    activePlayer = initVjs(activeVid, true);
    return { vid: activeVid, player: activePlayer, container: mountedTarget };
  };

  const playVideo = (isMuted = false) => {
    const mounted = ensureVideoMounted();
    if (!mounted) return;
    const { vid, player } = mounted;

    const doPlay = () => {
      if (player && typeof player.play === "function") {
        if (typeof player.addClass === "function") player.addClass("vjs-has-started");
        if (isMuted) {
          player.muted(true);
        } else {
          player.muted(false);
        }
        const promise = player.play();
        if (promise !== undefined) {
          promise.catch(() => {
            player.muted(true);
            player.play();
          });
        }
      } else if (vid && typeof vid.play === "function") {
        vid.muted = isMuted;
        vid.play().catch(() => {
          vid.muted = true;
          vid.play();
        });
      }
    };

    if (player && typeof player.ready === "function") {
      player.ready(doPlay);
    }
    doPlay();
  };

  const pauseVideo = () => {
    if (activePlayer && typeof activePlayer.pause === "function") {
      activePlayer.pause();
    } else if (activeVid && typeof activeVid.pause === "function") {
      activeVid.pause();
    }
  };

  // Click handler (unmutes audio and plays)
  ph.addEventListener("click", function (e) {
    e.stopPropagation();
    playVideo(false);
  });

  // Hover handlers on product card (plays with sound on hover, pauses on mouseleave)
  const cardContainer = ph.closest(".product-card") || ph;
  
  cardContainer.addEventListener("mouseenter", function () {
    playVideo(false);
  });

  cardContainer.addEventListener("mouseleave", function () {
    pauseVideo();
  });
}

function createVidEl(id, src, posterUrl) {
  const vid = document.createElement('video');
  vid.id = id ? id.replace('vp-', 'vjs-') : '';
  vid.className = 'video-js';
  vid.controls = true;
  vid.playsInline = true;
  vid.preload = 'auto';
  if (posterUrl) vid.poster = posterUrl;
  const source = document.createElement('source');
  source.src = src;
  source.type = 'video/mp4';
  vid.appendChild(source);
  return vid;
}

function initVjs(vid, shouldAutoplay = false) {
  try {
    if (typeof videojs === 'function' && !vid.dataset.vjsInited) {
      vid.dataset.vjsInited = '1';
      const player = videojs(vid, { 
        fluid: true, 
        controls: true, 
        preload: 'auto',
        autoplay: shouldAutoplay ? true : false,
        bigPlayButton: false
      });
      player.on('play', () => {
        const all = videojs.getPlayers();
        Object.keys(all).forEach(id => {
          const p = all[id];
          if (p !== player && p && typeof p.pause === 'function' && !p.paused()) {
            p.pause();
          }
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

// =========================================
// AI Analysis (Phase 1 & Phase 2) & History Drawer Engine for Saved Ads
// =========================================

window.lastAiInputPayload = null;
window.lastAiOutputResponse = null;
let currentAiIoTab = 'input';

function addAiConsoleLog(message, type = 'info') {
  const logBody = document.getElementById('ai-console-log-body');
  const badge = document.getElementById('ai-console-status-badge');
  if (badge) {
    if (type === 'error') {
      badge.textContent = '❌ خطأ';
      badge.style.background = 'rgba(239, 68, 68, 0.15)';
      badge.style.color = '#ef4444';
    } else if (type === 'success') {
      badge.textContent = '✅ مكتمل بنجاح';
      badge.style.background = 'rgba(16, 185, 129, 0.15)';
      badge.style.color = '#10b981';
    } else if (type === 'process') {
      badge.textContent = '⏳ جاري التنفيذ...';
      badge.style.background = 'rgba(99, 102, 241, 0.15)';
      badge.style.color = '#6366f1';
    }
  }
  if (!logBody) return;
  const now = new Date();
  const timeStr = now.toTimeString().split(' ')[0];
  let color = 'var(--color-text-main)';
  let prefix = 'ℹ️';
  if (type === 'error') { color = '#ef4444'; prefix = '❌'; }
  if (type === 'success') { color = '#10b981'; prefix = '✅'; }
  if (type === 'process') { color = '#6366f1'; prefix = '🚀'; }
  if (type === 'warning') { color = '#f59e0b'; prefix = '⚠️'; }

  const entry = document.createElement('div');
  entry.style.color = color;
  entry.style.marginBottom = '4px';
  entry.innerHTML = `<span style="opacity:0.65;">[${timeStr}]</span> ${prefix} ${message}`;
  logBody.appendChild(entry);
  logBody.scrollTop = logBody.scrollHeight;
}

function clearAiConsoleLogs() {
  const logBody = document.getElementById('ai-console-log-body');
  const badge = document.getElementById('ai-console-status-badge');
  if (logBody) {
    logBody.innerHTML = '<div style="color: var(--color-text-muted);">[00:00:00] ℹ️ تم مسح سجل العمليات المباشرة.</div>';
  }
  if (badge) {
    badge.textContent = 'جاهز';
    badge.style.background = 'rgba(16, 185, 129, 0.15)';
    badge.style.color = '#10b981';
  }
}

function openAiInputOutputInspectorModal(tab = 'input') {
  const modal = document.getElementById('ai-io-modal');
  if (!modal) return;
  modal.style.display = 'flex';
  switchAiIoTab(tab);
}

function closeAiIoModal() {
  const modal = document.getElementById('ai-io-modal');
  if (modal) modal.style.display = 'none';
}

function switchAiIoTab(tab) {
  currentAiIoTab = tab;
  const btnInput = document.getElementById('ai-io-tab-input');
  const btnOutput = document.getElementById('ai-io-tab-output');
  const box = document.getElementById('ai-io-content-box');
  if (!box) return;

  if (tab === 'input') {
    if (btnInput) {
      btnInput.style.borderColor = 'var(--color-primary)';
      btnInput.style.color = 'var(--color-primary)';
    }
    if (btnOutput) {
      btnOutput.style.borderColor = 'var(--border-color)';
      btnOutput.style.color = 'var(--color-text-muted)';
    }
    const val = window.lastAiInputPayload ? (typeof window.lastAiInputPayload === 'string' ? window.lastAiInputPayload : JSON.stringify(window.lastAiInputPayload, null, 2)) : 'لا توجد مدخلات مسجلة بعد للعملية الأخيرة.';
    box.value = val;
  } else {
    if (btnOutput) {
      btnOutput.style.borderColor = 'var(--color-primary)';
      btnOutput.style.color = 'var(--color-primary)';
    }
    if (btnInput) {
      btnInput.style.borderColor = 'var(--border-color)';
      btnInput.style.color = 'var(--color-text-muted)';
    }
    const val = window.lastAiOutputResponse ? (typeof window.lastAiOutputResponse === 'string' ? window.lastAiOutputResponse : JSON.stringify(window.lastAiOutputResponse, null, 2)) : 'لا توجد مخرجات مسجلة بعد للعملية الأخيرة.';
    box.value = val;
  }
}

function copyAiIoContent() {
  const box = document.getElementById('ai-io-content-box');
  if (!box || !box.value) return;
  navigator.clipboard.writeText(box.value);
  if (typeof showToast === 'function') {
    showToast('تم نسخ محتوى ' + (currentAiIoTab === 'input' ? 'المدخلات' : 'المخرجات') + ' للحافظة! 📋', 'success');
  }
}

let aiProvidersConfigCache = null;

async function fetchAiModelsConfig() {
  if (aiProvidersConfigCache) return aiProvidersConfigCache;
  try {
    const res = await fetch("/api/settings/ai_providers_config");
    if (res.ok) {
      const data = await res.json();
      if (data && data.value) {
        aiProvidersConfigCache = typeof data.value === "string" ? JSON.parse(data.value) : data.value;
        return aiProvidersConfigCache;
      }
    }
  } catch (e) {
    console.error("Error fetching AI providers config:", e);
  }
  return null;
}

async function populateAiProviderSelect() {
  const providerSelect = document.getElementById("ai-provider-select");
  if (!providerSelect) return;

  const config = await fetchAiModelsConfig();
  const globalActiveProvider = config && config.active_provider ? config.active_provider : "auto";
  const globalActiveModel = config && config.active_model ? config.active_model : "";

  const providerNames = {
    auto: "✨ التلقائي الافتراضي",
    openrouter: "🌐 OpenRouter",
    apiyi: "🚀 APIyi",
    openai: "🤖 OpenAI (ChatGPT)",
    gemini: "💎 Google Gemini",
    deepseek: "🐋 DeepSeek",
    custom: "⚡ محرك مخصص / Ollama",
    internal: "⚡ المحرك الداخلي السريع",
  };

  let html = `<option value="auto" ${globalActiveProvider === "auto" ? "selected" : ""}>✨ التلقائي (حسب إعدادات النظام)</option>`;

  const providers = config && config.providers ? config.providers : {
    openrouter: { name: "🌐 OpenRouter" },
    apiyi: { name: "🚀 APIyi" },
    openai: { name: "🤖 OpenAI" },
    gemini: { name: "💎 Google Gemini" },
    deepseek: { name: "🐋 DeepSeek" },
    custom: { name: "⚡ محرك مخصص / Ollama" },
  };

  for (const [pKey, pData] of Object.entries(providers)) {
    const isSelected = globalActiveProvider === pKey ? "selected" : "";
    const name = (pData && pData.name) || providerNames[pKey] || pKey;
    const badge = globalActiveProvider === pKey ? " 🌟 (الافتراضي)" : "";
    html += `<option value="${pKey}" ${isSelected}>${name}${badge}</option>`;
  }
  if (!providers.internal) {
    html += `<option value="internal">⚡ المحرك الداخلي السريع</option>`;
  }

  providerSelect.innerHTML = html;
  updateModelOptions(globalActiveProvider, globalActiveModel);
}

async function updateModelOptions(selectedProvider = null, defaultModelToSelect = null) {
  const providerSelect = document.getElementById("ai-provider-select");
  const modelSelect = document.getElementById("ai-model-select");
  if (!modelSelect) return;

  const provider = selectedProvider || (providerSelect ? providerSelect.value : "auto");
  const config = await fetchAiModelsConfig();

  if (provider === "auto" || provider === "internal") {
    modelSelect.innerHTML = `<option value="">✨ الموديل الافتراضي في النظام</option>`;
    return;
  }

  const defaultModelsMap = {
    openrouter: ["openai/gpt-4o-mini", "anthropic/claude-3.5-sonnet"],
    apiyi: ["gpt-4o-mini", "gpt-4o", "claude-3-5-sonnet-20241022", "deepseek-chat"],
    openai: ["gpt-4o-mini", "gpt-4o"],
    gemini: ["gemini-1.5-flash", "gemini-1.5-pro", "gemini-2.5-flash"],
    deepseek: ["deepseek-chat"],
    custom: ["ollama/llama3", "custom-model"],
  };

  let modelsList = defaultModelsMap[provider] || [];
  let activeModelInConfig = "";

  if (config && config.providers && config.providers[provider]) {
    const pInfo = config.providers[provider];
    if (Array.isArray(pInfo.models) && pInfo.models.length > 0) {
      modelsList = pInfo.models;
    }
    activeModelInConfig = pInfo.active_model || "";
  }

  const modelToHighlight = defaultModelToSelect || activeModelInConfig;

  let html = `<option value="">✨ الموديل الافتراضي لـ (${provider})</option>`;
  modelsList.forEach((m) => {
    const isSel = m === modelToHighlight ? "selected" : "";
    const badge = m === activeModelInConfig ? " ⭐" : "";
    html += `<option value="${m}" ${isSel}>${m}${badge}</option>`;
  });

  modelSelect.innerHTML = html;
}

function handleAiProviderChangeInModal() {
  updateModelOptions();
}

function openAiAnalysisModal() {
  const modal = document.getElementById("ai-analysis-modal");
  if (!modal) return;
  populateAiProviderSelect();
  modal.style.display = "flex";
}

function closeAiAnalysisModal() {
  const modal = document.getElementById("ai-analysis-modal");
  if (modal) modal.style.display = "none";
}

async function handleRunAiAnalysis(event) {
  if (event) event.preventDefault();

  let productsSource = currentFiltered && currentFiltered.length > 0 ? currentFiltered : savedProducts;
  if (!productsSource || productsSource.length === 0) {
    showToast("لا توجد منتجات محفوظة معروضة لتحليلها!", "warning");
    return;
  }

  const provider = document.getElementById("ai-provider-select")?.value || "auto";
  const model = document.getElementById("ai-model-select")?.value || "";
  const mode = document.getElementById("ai-mode-select")?.value || "comprehensive";
  const budget = Number(document.getElementById("ai-budget-input")?.value || 5000);
  const season = document.getElementById("ai-season-select")?.value || "auto";
  const cShipping = Number(document.getElementById("ai-shipping-input")?.value || 35);

  const products = productsSource.map((p, idx) => ({
    title: p.title || p.name || `منتج #${idx + 1}`,
    price: Number(p.price || p.actualPrice || p.price_1 || 250),
    selling_price: Number(p.price || p.actualPrice || p.price_1 || 250),
    ads_count: Number(p.ads_count || 1),
    active_ads: Number(p.ads_count || 1),
    ad_body: p.ad_body || p.description || "",
    ad_title: p.ad_title || "",
    country: p.country || "MA",
    product_url: p.productUrl || p.product_url || "",
  }));

  const payload = {
    provider,
    model,
    analysis_mode: mode,
    ad_budget_total: budget,
    season,
    c_shipping_default: cShipping,
    products,
  };

  const submitBtn = document.getElementById("ai-submit-btn");
  const originalText = submitBtn ? submitBtn.innerHTML : "";
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = "⏳ جاري التحليل بالذكاء الاصطناعي...";
  }

  const liveStatusBox = document.getElementById("ai-modal-live-status");
  const liveStatusText = document.getElementById("ai-modal-status-text");
  if (liveStatusBox) liveStatusBox.style.display = "block";
  if (liveStatusText) {
    liveStatusText.textContent = `⏳ جاري الاتصال بالمزود (${provider}) وتحليل المنتجات...`;
    liveStatusText.style.color = "var(--color-primary)";
  }

  window.lastAiInputPayload = payload;

  try {
    const res = await fetch("/api/ai/analyze", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    window.lastAiOutputResponse = data;

    if (!res.ok || !data || data.success === false) {
      const errMsg = data?.error || data?.message || `خطأ في الخادم (${res.status})`;
      if (liveStatusText) {
        liveStatusText.textContent = `❌ فشل التقييم: ${errMsg}`;
        liveStatusText.style.color = "#ef4444";
      }
      showToast("فشل إجراء التقييم: " + errMsg, "error");
      return;
    }

    if (liveStatusText) {
      liveStatusText.textContent = `✅ اكتمل التحليل بنجاح! [${data.ai_powered_by || 'Done'}]`;
      liveStatusText.style.color = "#10b981";
    }

    window.currentAiAnalysis = data;
    window.currentAiEvaluations = data.evaluations || [];

    showToast("✨ تم تحليل المنتجات بنجاح وحفظ النتائج في الأرشيف!", "success");
    closeAiAnalysisModal();
    fetchAiAnalysisHistory();
    openAiFullReportModal();
  } catch (err) {
    console.error("AI Analysis error:", err);
    showToast("تعذر الاتصال بخادم التحليل: " + (err.message || err), "error");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
}

function openAiHistoryDrawer() {
  const drawer = document.getElementById("ai-history-drawer");
  if (!drawer) return;
  drawer.style.display = "flex";
  fetchAiAnalysisHistory();
}

function closeAiHistoryDrawer() {
  const drawer = document.getElementById("ai-history-drawer");
  if (drawer) drawer.style.display = "none";
}

async function fetchAiAnalysisHistory() {
  const container = document.getElementById("ai-history-list");
  if (!container) return;

  try {
    const res = await fetch("/api/ai/history");
    const data = await res.json();

    if (!data.success || !Array.isArray(data.history) || data.history.length === 0) {
      container.innerHTML = `<div style="text-align: center; color: var(--color-text-muted); padding: 2rem 0;">لا توجد تحليلات محفوظة حتى الآن.</div>`;
      return;
    }

    container.innerHTML = data.history
      .map(
        (item) => `
      <div style="background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; cursor: pointer; transition: var(--transition-all);" onclick="loadAiAnalysisHistoryDetail(${item.id})">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
          <h4 style="font-weight: 800; font-size: 0.95rem; margin: 0; color: var(--color-primary);">${item.title}</h4>
          <button style="background: none; border: none; color: #ef4444; font-size: 0.9rem; cursor: pointer;" onclick="deleteAiAnalysisHistoryItem(${item.id}, event)" title="حذف">🗑️</button>
        </div>
        <div style="font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 6px;">📅 ${item.created_at || ""} | 🤖 ${item.ai_powered_by || "الذكاء الاصطناعي"}</div>
        <div style="display: flex; gap: 10px; font-size: 0.78rem; color: var(--color-text-main);">
          <span>📦 المعاينة: ${item.summary?.total_analyzed || 0} منتج</span>
          <span style="color: #10b981;">🟢 رابحة: ${item.summary?.winners_count || 0}</span>
        </div>
      </div>
    `,
      )
      .join("");
  } catch (err) {
    console.error("Fetch AI History Error:", err);
    container.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 1.5rem 0;">⚠️ حدث خطأ أثناء تحميل السجل.</div>`;
  }
}

async function loadAiAnalysisHistoryDetail(id) {
  try {
    const res = await fetch(`/api/ai/history/${id}`);
    const data = await res.json();
    if (!data.success || !data.analysis) {
      showToast("تعذر استرجاع تفاصيل هذا التحليل.", "error");
      return;
    }
    window.currentAiAnalysis = {
      success: true,
      title: data.analysis.title,
      summary: data.analysis.summary,
      evaluations: data.analysis.evaluations,
    };
    window.currentAiEvaluations = data.analysis.evaluations || [];
    closeAiHistoryDrawer();
    openAiFullReportModal();
    showToast(`🔄 تم استرجاع التحليل: ${data.analysis.title}`, "info");
  } catch (err) {
    console.error("Load AI History Detail error:", err);
    showToast("خطأ في استرجاع التحليل.", "error");
  }
}

async function deleteAiAnalysisHistoryItem(id, event) {
  if (event) event.stopPropagation();
  if (!confirm("هل أنت تأكد من رغبتك في حذف هذا التحليل من السجل؟")) return;
  try {
    const res = await fetch(`/api/ai/history/${id}/delete`, { method: "POST" });
    const data = await res.json();
    if (data.success) {
      fetchAiAnalysisHistory();
    } else {
      showToast("فشل الحذف.", "error");
    }
  } catch (err) {
    console.error("Delete history item error:", err);
  }
}

function openAiFullReportModal() {
  const modal = document.getElementById("ai-full-report-modal");
  if (!modal) return;
  renderAiFullReportModalContent();
  modal.style.display = "flex";
}

function closeAiFullReportModal() {
  const modal = document.getElementById("ai-full-report-modal");
  if (modal) modal.style.display = "none";
}

function renderAiFullReportModalContent() {
  const container = document.getElementById("ai-report-modal-body");
  if (!container) return;

  if (!window.currentAiAnalysis || !Array.isArray(window.currentAiEvaluations) || window.currentAiEvaluations.length === 0) {
    container.innerHTML = `<div style="text-align: center; color: var(--color-text-muted); padding: 3rem 0;">لا تتوفر نتائج تحليل حالية. يرجى تشغيل الذكاء الاصطناعي أولاً.</div>`;
    return;
  }

  const summary = window.currentAiAnalysis.summary || {};
  const evaluations = window.currentAiEvaluations;

  let tableRows = evaluations
    .map((item) => {
      const fin = item.financials || {};
      const brk = item.breakdown || {};
      let badgeColor = "#10b981";
      if (item.verdict === "promising") badgeColor = "#f59e0b";
      if (item.verdict === "risk") badgeColor = "#ef4444";
      const escapedTitle = (item.title || "").replace(/'/g, "\\'");

      return `
      <tr>
        <td style="font-weight: 700; max-width: 180px;">${item.title}</td>
        <td>
          <span style="background: ${badgeColor}; color: white; padding: 3px 8px; border-radius: 12px; font-weight: 800; font-size: 0.75rem;">
            ${item.score}/100
          </span>
        </td>
        <td>${brk.demand_score || 0}/40</td>
        <td>${brk.season_score || 0}/30</td>
        <td>${brk.logistics_score || 0}/20</td>
        <td style="font-weight: 700; color: var(--color-primary);">${fin.target_price || 0} DH</td>
        <td style="font-weight: 700; color: #10b981;">+${fin.net_margin_pct || 0}%</td>
        <td style="display: flex; gap: 4px; align-items: center;">
          <button class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.75rem; font-weight: 700;" onclick="openAiProductTextModal('${escapedTitle}')">
            📖 النص
          </button>
          <button class="btn btn-success" style="padding: 3px 8px; font-size: 0.75rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); border: none;" onclick="openPhase2ByEvaluationIndex(${evaluations.indexOf(item)})">
            🚀 Phase 2
          </button>
        </td>
      </tr>
    `;
    })
    .join("");

  container.innerHTML = `
    <div style="background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem; display: flex; justify-content: space-around; text-align: center;">
      <div>
        <span style="display: block; font-size: 0.8rem; color: var(--color-text-muted);">إجمالي المنتجات</span>
        <strong style="font-size: 1.2rem;">${summary.total_analyzed || 0}</strong>
      </div>
      <div>
        <span style="display: block; font-size: 0.8rem; color: #10b981;">المنجات الرابحة 🟢</span>
        <strong style="font-size: 1.2rem; color: #10b981;">${summary.winners_count || 0}</strong>
      </div>
      <div>
        <span style="display: block; font-size: 0.8rem; color: #f59e0b;">المنتجات الواعدة 🟡</span>
        <strong style="font-size: 1.2rem; color: #f59e0b;">${summary.promising_count || 0}</strong>
      </div>
      <div>
        <span style="display: block; font-size: 0.8rem; color: #ef4444;">عالية المخاطر 🔴</span>
        <strong style="font-size: 1.2rem; color: #ef4444;">${summary.risk_count || 0}</strong>
      </div>
    </div>
    <table class="ai-matrix-table">
      <thead>
        <tr>
          <th>عنوان المنتج</th>
          <th>النقاط</th>
          <th>الطلب (40)</th>
          <th>الموسم (30)</th>
          <th>اللوجستيك (20)</th>
          <th>السعر المقترح</th>
          <th>هامش الربح %</th>
          <th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        ${tableRows}
      </tbody>
    </table>
  `;
}

function openAiProductTextModal(identifier) {
  const modal = document.getElementById("ai-product-text-modal");
  if (!modal) return;
  let item = null;
  if (Array.isArray(window.currentAiEvaluations)) {
    item = window.currentAiEvaluations.find(
      (ev) => ev.url === identifier || ev.id === identifier || ev.title === identifier || ev.title?.trim() === identifier
    );
  }
  if (!item && window.currentAiAnalysis?.summary?.top_winner) {
    if (window.currentAiAnalysis.summary.top_winner.title === identifier) {
      item = window.currentAiAnalysis.summary.top_winner;
    }
  }
  if (!item) {
    showToast("تعذر العثور على بيانات التحليل لهذا المنتج.", "warning");
    return;
  }
  renderAiProductTextModalContent(item);
  modal.style.display = "flex";
}

function closeAiProductTextModal() {
  const modal = document.getElementById("ai-product-text-modal");
  if (modal) modal.style.display = "none";
}

function renderAiProductTextModalContent(item) {
  const container = document.getElementById("ai-text-modal-body");
  if (!container) return;

  const fin = item.financials || {};
  const nar = item.full_narrative || "لا يتوفر نص تحليل تفصيلي لهذا المنتج.";

  container.innerHTML = `
    <div style="background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
      <h4 style="font-weight: 800; font-size: 1.1rem; color: var(--color-primary); margin-bottom: 6px;">${item.title}</h4>
      <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--color-text-muted);">
        <span>⭐ النتيجة: <strong style="color: #10b981;">${item.score}/100</strong></span>
        <span>🏷️ السعر المستهدف: <strong>${fin.target_price || 0} DH</strong></span>
        <span>📈 هامش الربح: <strong style="color: #10b981;">+${fin.net_margin_pct || 0}%</strong></span>
      </div>
    </div>
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.2rem; font-size: 0.9rem; line-height: 1.8; color: var(--color-text-main); white-space: pre-line;">
      ${nar}
    </div>
  `;
}

// =========================================
// Phase 2: Single Product Deep-Dive Engine (Saved Ads)
// =========================================

async function populatePhase2AiModels() {
  const select = document.getElementById("p2-provider-select");
  if (!select) return;
  const currentVal = select.value || "auto";

  try {
    const config = await fetchAiModelsConfig();
    let html = `
      <option value="auto" data-provider="auto" data-model="" ${currentVal === "auto" ? "selected" : ""}>✨ التلقائي (الموديل الافتراضي في النظام)</option>
      <option value="internal" data-provider="internal" data-model="internal-engine" ${currentVal === "internal" ? "selected" : ""}>⚡ المحرك الداخلي السريع (Internal Engine)</option>
    `;

    const providerDisplayNames = {
      openrouter: "🌐 OpenRouter",
      apiyi: "🚀 APIyi",
      openai: "🤖 OpenAI",
      gemini: "💎 Google Gemini",
      deepseek: "🐋 DeepSeek",
      custom: "⚡ محرك مخصص / Ollama",
    };

    const defaultProviders = {
      openrouter: { name: "🌐 OpenRouter", models: ["openai/gpt-4o-mini", "anthropic/claude-3.5-sonnet"], active_model: "openai/gpt-4o-mini" },
      apiyi: { name: "🚀 APIyi", models: ["gpt-4o-mini", "gpt-4o", "claude-3-5-sonnet-20241022", "deepseek-chat"], active_model: "gpt-4o-mini" },
      openai: { name: "🤖 OpenAI", models: ["gpt-4o-mini", "gpt-4o"], active_model: "gpt-4o-mini" },
      gemini: { name: "💎 Google Gemini", models: ["gemini-1.5-flash", "gemini-1.5-pro", "gemini-2.5-flash"], active_model: "gemini-1.5-flash" },
      deepseek: { name: "🐋 DeepSeek", models: ["deepseek-chat"], active_model: "deepseek-chat" },
    };

    const providersData = config && config.providers ? config.providers : defaultProviders;
    const globalActiveProvider = config && config.active_provider ? config.active_provider : "openrouter";

    for (const [pKey, pData] of Object.entries(providersData)) {
      const models = pData && Array.isArray(pData.models) ? pData.models : [];
      if (models.length > 0) {
        const pName = (pData && pData.name) || providerDisplayNames[pKey] || pKey;
        const activeModel = (pData && pData.active_model) || models[0] || "";
        html += `<optgroup label="${pName}">`;
        models.forEach((m) => {
          const isGlobalDefault = pKey === globalActiveProvider && m === activeModel;
          const badge = isGlobalDefault ? " 🌟 (افتراضي النظام)" : m === activeModel ? " ⭐" : "";
          const isSelected = currentVal === m;
          html += `<option value="${m}" data-provider="${pKey}" data-model="${m}" ${isSelected ? "selected" : ""}>${m}${badge}</option>`;
        });
        html += `</optgroup>`;
      }
    }

    select.innerHTML = html;
  } catch (e) {
    console.error("Error populating Phase 2 AI models:", e);
  }
}

function openPhase2InputModal(product) {
  if (!product) {
    showToast("لم يتم تحديد منتج للتحليل العميق.", "error");
    return;
  }

  populatePhase2AiModels();

  document.getElementById("p2-product-id").value = product.id || product.product_id || "";
  document.getElementById("p2-product-title-input").value = product.title || product.name || "منتج بدون عنوان";
  document.getElementById("p2-product-raw-json").value = JSON.stringify(product);

  const defaultPrice = parseFloat(product.custom_price || product.selling_price || product.actualPrice || product.price_1 || product.price || 250);
  document.getElementById("p2-price-selling").value = isNaN(defaultPrice) || defaultPrice <= 0 ? 250 : defaultPrice;

  const defaultWholesale = parseFloat(product.c_wholesale || product.wholesale_price || product.cost_price || 70);
  document.getElementById("p2-c-wholesale").value = isNaN(defaultWholesale) || defaultWholesale <= 0 ? 70 : defaultWholesale;

  document.getElementById("p2-c-shipping").value = product.c_shipping || 35;
  document.getElementById("p2-c-packaging").value = product.c_packaging || 10;
  document.getElementById("p2-stock-quantity").value = product.stock_quantity || product.stock_qty || product.quantity || 100;
  document.getElementById("p2-total-ad-budget").value = product.total_ad_budget || product.ad_budget || 1000;
  document.getElementById("p2-extra-notes").value = product.notes || "";

  const modal = document.getElementById("phase2-input-modal");
  if (modal) modal.style.display = "flex";
}

function openPhase2FromDetails() {
  if (!currentProductForDetails) {
    showToast("لم يتم العثور على المنتج الحالي.", "error");
    return;
  }
  openPhase2InputModal(currentProductForDetails);
}

function closePhase2InputModal() {
  const modal = document.getElementById("phase2-input-modal");
  if (modal) modal.style.display = "none";
}

async function handleRunPhase2DeepAnalysis(event) {
  if (event) event.preventDefault();

  const rawJsonStr = document.getElementById("p2-product-raw-json")?.value;
  let product = null;
  try {
    product = JSON.parse(rawJsonStr);
  } catch (e) {
    product = currentProductForDetails || {};
  }

  if (product && typeof product === "object") {
    product = { ...product };
    delete product.ad_image_urls;
    delete product.images;
    delete product.image_url;
    delete product.image;
    delete product.ad_video_urls;
    delete product.video_url;
    delete product.video_path;
    delete product.video;
  }

  const select = document.getElementById("p2-provider-select");
  const selectedOption = select ? select.options[select.selectedIndex] : null;

  let provider = selectedOption ? selectedOption.getAttribute("data-provider") : "auto";
  let model = selectedOption ? selectedOption.getAttribute("data-model") : "";
  if (!provider) provider = select ? select.value : "auto";

  const payload = {
    product: product,
    product_id: document.getElementById("p2-product-id")?.value || "",
    product_title: document.getElementById("p2-product-title-input")?.value || product.title || product.name || "منتج",
    product_url: product.productUrl || product.product_url || "",
    price_selling: parseFloat(document.getElementById("p2-price-selling")?.value || 250),
    c_wholesale: parseFloat(document.getElementById("p2-c-wholesale")?.value || 70),
    c_shipping: parseFloat(document.getElementById("p2-c-shipping")?.value || 35),
    c_packaging: parseFloat(document.getElementById("p2-c-packaging")?.value || 10),
    stock_quantity: parseInt(document.getElementById("p2-stock-quantity")?.value || 100),
    total_ad_budget: parseFloat(document.getElementById("p2-total-ad-budget")?.value || 1000),
    extra_notes: document.getElementById("p2-extra-notes")?.value || "",
    provider: provider,
    model: model,
  };

  const submitBtn = document.getElementById("p2-submit-btn");
  const originalText = submitBtn ? submitBtn.innerHTML : "";
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = "⏳ جاري إعداد دراسة الجدوى وتكلفة الإعلانات...";
  }

  const p2LiveStatusBox = document.getElementById("p2-modal-live-status");
  const p2StatusText = document.getElementById("p2-modal-status-text");
  if (p2LiveStatusBox) p2LiveStatusBox.style.display = "block";
  if (p2StatusText) {
    p2StatusText.textContent = `⏳ جاري إرسال الطلب وحساب المعايير المالية والتسويقية...`;
    p2StatusText.style.color = "#10b981";
  }

  window.lastAiInputPayload = payload;

  try {
    const res = await fetch("/api/ai/analyze-deep", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    window.lastAiOutputResponse = data;

    if (res.ok && data.success && data.result) {
      if (p2StatusText) p2StatusText.textContent = `✅ اكتملت الدراسة والتحليل العميق بنجاح!`;
      closePhase2InputModal();
      renderPhase2Results(data.result, payload.product_title);
      showToast("تم توليد دراسة الجدوى وتكتيكات الإطلاق بنجاح! 🚀", "success");
    } else {
      const errMsg = data.error || data.message || "فشل إجراء التحليل التفصيلي للمنتج.";
      if (p2StatusText) {
        p2StatusText.textContent = `❌ فشل تحليل Phase 2: ${errMsg}`;
        p2StatusText.style.color = "#ef4444";
      }
      showToast(errMsg, "error");
    }
  } catch (err) {
    console.error("Phase 2 Analysis Error:", err);
    showToast("تعذر الاتصال بخادم التحليل Deep Analyze.", "error");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }
  }
}

function renderPhase2Results(result, productTitle) {
  const modal = document.getElementById("phase2-results-modal");
  const body = document.getElementById("p2-results-body");
  if (!modal || !body) return;

  const fm = result.financial_model || {};
  const ta = result.target_audience || {};
  const os = result.offers_strategy || {};
  const creatives = result.ad_creatives || [];
  const log = result.logistics_and_call_center || {};
  const verdict = result.executive_verdict || "";
  const providerTag = result.ai_powered_by || "Phase 2 Blueprint";

  const subtitleEl = document.getElementById("p2-results-subtitle");
  if (subtitleEl)
    subtitleEl.textContent = `${productTitle || "منتج"} | المحرك: ${providerTag}`;

  let creativesHtml = creatives
    .map(
      (c, i) => `
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 10px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
        <span style="font-weight: 700; color: var(--color-primary); font-size: 0.85rem;">🎬 الزاوية ${i + 1}: ${c.angle || ""}</span>
        <button class="btn btn-secondary" onclick="navigator.clipboard.writeText('${(c.headline || "").replace(/'/g, "\\'") + "\\n" + (c.body || "").replace(/'/g, "\\'")}'); showToast('تم نسخ النص الإعلاني! 📋', 'info');" style="padding: 2px 8px; font-size: 0.75rem;">📋 نسخ النص</button>
      </div>
      <div style="font-weight: 700; color: var(--color-text-main); font-size: 0.95rem; margin-bottom: 4px;">${c.headline || ""}</div>
      <div style="color: var(--color-text-muted); font-size: 0.85rem; line-height: 1.5; white-space: pre-line;">${c.body || ""}</div>
    </div>
  `,
    )
    .join("");

  body.innerHTML = `
    <!-- Top Summary Banner -->
    <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(6, 182, 212, 0.12)); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
      <div style="flex: 1; min-width: 250px;">
        <span style="font-weight: 800; font-size: 1.1rem; color: #10b981; display: block;">🏆 ملخص الجدوى والربحية المحتملة</span>
        <span style="font-size: 0.85rem; color: var(--color-text-muted);">${verdict}</span>
      </div>
      <div style="display: flex; gap: 15px; background: var(--bg-card); padding: 8px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
        <div style="text-align: center;">
          <span style="font-size: 0.72rem; color: var(--color-text-muted); display: block;">ربح الطلبية الصافي</span>
          <strong style="color: #10b981; font-size: 1.15rem;">${fm.net_profit_per_order || 0} DH</strong>
        </div>
        <div style="text-align: center; border-right: 1px solid var(--border-color); padding-right: 15px;">
          <span style="font-size: 0.72rem; color: var(--color-text-muted); display: block;">هامش الربح %</span>
          <strong style="color: #6366f1; font-size: 1.15rem;">${fm.net_margin_pct || 0}%</strong>
        </div>
        <div style="text-align: center; border-right: 1px solid var(--border-color); padding-right: 15px;">
          <span style="font-size: 0.72rem; color: var(--color-text-muted); display: block;">الربح اليومي المستهدف</span>
          <strong style="color: #f59e0b; font-size: 1.15rem;">${fm.projected_daily_net_profit_dh || 0} DH</strong>
        </div>
      </div>
    </div>

    <!-- Financial Blueprint Matrix -->
    <div style="margin-bottom: 1.5rem;">
      <h4 style="font-size: 1rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
        💳 النموذج المالي وحساب تكاليف COD المغرب
      </h4>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
        <div style="background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">سعر البيع المقترح</span>
          <strong style="font-size: 1rem; color: var(--color-text-main);">${fm.selling_price || 0} DH</strong>
        </div>
        <div style="background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">شراء الجملة (C_wholesale)</span>
          <strong style="font-size: 1rem; color: var(--color-text-main);">${fm.c_wholesale || 0} DH</strong>
        </div>
        <div style="background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">الشحن الفعلي (مع المرجوعات 20%)</span>
          <strong style="font-size: 1rem; color: #ef4444;">${fm.real_shipping_with_returns || 0} DH</strong>
        </div>
        <div style="background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">التأكيد والتغليف (Packaging)</span>
          <strong style="font-size: 1rem; color: var(--color-text-main);">${fm.c_packaging || 0} DH</strong>
        </div>
        <div style="background: rgba(239, 68, 68, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">أقصى تكلفة إعلانية Breakeven CPA</span>
          <strong style="font-size: 1rem; color: #ef4444;">${fm.breakeven_cpa || 0} DH</strong>
        </div>
        <div style="background: rgba(16, 185, 129, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">المستهدف الإعلاني Target CPA</span>
          <strong style="font-size: 1rem; color: #10b981;">${fm.target_cpa || 0} DH</strong>
        </div>
        <div style="background: rgba(99, 102, 241, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(99, 102, 241, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">الميزانية الإعلانية اليومية</span>
          <strong style="font-size: 1rem; color: #6366f1;">${fm.daily_ad_budget_dh || 0} DH</strong>
        </div>
        <div style="background: rgba(16, 185, 129, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">📦 كمية المخزون المستهدفة</span>
          <strong style="font-size: 1rem; color: #10b981;">${fm.stock_quantity || 100} قطعة</strong>
        </div>
        <div style="background: rgba(245, 158, 11, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(245, 158, 11, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">💵 رأس مال شراء المخزون</span>
          <strong style="font-size: 1rem; color: #f59e0b;">${fm.initial_inventory_capital || fm.stock_quantity * fm.c_wholesale || 0} DH</strong>
        </div>
        <div style="background: rgba(139, 92, 246, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(139, 92, 246, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">⏳ نفاد المخزون المقدر</span>
          <strong style="font-size: 1rem; color: #8b5cf6;">${fm.days_to_sell_out || 0} يوم</strong>
        </div>
        <div style="background: rgba(236, 72, 153, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(236, 72, 153, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">🎯 المبيعات اليومية الموصى بها</span>
          <strong style="font-size: 1rem; color: #ec4899;">${fm.target_daily_orders || 15} طلب/يوم</strong>
        </div>
        <div style="background: rgba(14, 165, 233, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(14, 165, 233, 0.3);">
          <span style="font-size: 0.78rem; color: var(--color-text-muted); display: block;">💳 الميزانية الإعلانية الإجمالية</span>
          <strong style="font-size: 1rem; color: #0ea5e9;">${fm.total_ad_budget || 1000} DH</strong>
        </div>
      </div>
    </div>

    <!-- Audience & Offers Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
        <h5 style="font-size: 0.95rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
          🎯 الجمهور المستهدف والقنوات الإعلانية
        </h5>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem; display: flex; flex-direction: column; gap: 8px;">
          <li><strong>الفئة العمرية:</strong> ${ta.age_range || ""}</li>
          <li><strong>الجنس:</strong> ${ta.gender || ""}</li>
          <li><strong>أبرز المدن:</strong> ${Array.isArray(ta.top_cities) ? ta.top_cities.join("، ") : ta.top_cities || ""}</li>
          <li><strong>منصات الإطلاق:</strong> ${Array.isArray(ta.best_platforms) ? ta.best_platforms.join(" | ") : ta.best_platforms || ""}</li>
          <li><strong>الاهتمامات:</strong> ${Array.isArray(ta.interests) ? ta.interests.join("، ") : ta.interests || ""}</li>
        </ul>
      </div>

      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
        <h5 style="font-size: 0.95rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
          🏷️ استراتيجية العروض والحزم (Bundles)
        </h5>
        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem;">
          <div style="background: var(--bg-input); padding: 6px 10px; border-radius: 4px; border-right: 3px solid #6366f1;">
            <strong>القطعة الواحدة:</strong> ${os.single_unit || ""}
          </div>
          <div style="background: var(--bg-input); padding: 6px 10px; border-radius: 4px; border-right: 3px solid #10b981;">
            <strong>عرض القطعتين:</strong> ${os.bundle_2_units || ""}
          </div>
          <div style="background: var(--bg-input); padding: 6px 10px; border-radius: 4px; border-right: 3px solid #f59e0b;">
            <strong>عرض 3 قطع:</strong> ${os.bundle_3_units || ""}
          </div>
        </div>
      </div>
    </div>

    <!-- Ad Creatives & Copies -->
    <div style="margin-bottom: 1.5rem;">
      <h4 style="font-size: 1rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
        📢 النصوص والزوايا الإعلانية الموصى بها (Ad Copy Angles)
      </h4>
      ${creativesHtml}
    </div>

    <!-- Logistics & Call Center Advice -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.85rem;">
      <h5 style="font-size: 0.95rem; font-weight: 800; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px;">
        📞 نصائح مركز الاتصال والشحن (Call Center & Logistics)
      </h5>
      <div style="display: flex; flex-direction: column; gap: 6px; color: var(--color-text-muted);">
        <div>📞 <strong>تأكيد الطلبيات:</strong> ${log.confirmation_script_tip || ""}</div>
        <div>📦 <strong>التغليف والحماية:</strong> ${log.packaging_advice || ""}</div>
        <div>🚚 <strong>شركة التوصيل:</strong> ${log.shipping_carrier_recommendation || ""}</div>
      </div>
    </div>
  `;

  modal.style.display = "flex";
}

function openPhase2ResultsModal() {
  const modal = document.getElementById("phase2-results-modal");
  if (modal) modal.style.display = "flex";
}

function closePhase2ResultsModal() {
  const modal = document.getElementById("phase2-results-modal");
  if (modal) modal.style.display = "none";
}

function openPhase2ByEvaluationIndex(idx) {
  if (window.currentAiEvaluations && window.currentAiEvaluations[idx]) {
    const item = window.currentAiEvaluations[idx];
    openPhase2InputModal({
      title: item.title,
      price: item.financials?.target_price || 250,
      c_wholesale: item.financials?.wholesale_price || 70,
      notes: item.full_narrative || "",
    });
  }
}

let currentPhase2HistoryCache = [];
let allPhase2HistoryCache = [];
let currentFilterTitle = "";

async function openSavedPhase2Modal(productTitle = "", productId = "") {
  const modal = document.getElementById("phase2-history-modal");
  const body = document.getElementById("p2-history-list-body");
  const searchInput = document.getElementById("p2-history-search-input");

  currentFilterTitle = productTitle;
  if (searchInput) searchInput.value = productTitle;
  if (modal) modal.style.display = "flex";
  if (body) body.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--color-text-muted);">⏳ جاري تحميل السجل المحفوظ...</div>';

  try {
    let url = "/api/ai/phase2-history";
    const queryParams = [];
    if (productTitle) queryParams.push(`title=${encodeURIComponent(productTitle)}`);
    if (productId) queryParams.push(`product_id=${encodeURIComponent(productId)}`);
    if (queryParams.length > 0) url += "?" + queryParams.join("&");

    const res = await fetch(url);
    const data = await res.json();

    if (res.ok && data.success && Array.isArray(data.history)) {
      currentPhase2HistoryCache = data.history;
      allPhase2HistoryCache = data.all_history || data.history;
      renderPhase2HistoryList(currentPhase2HistoryCache, productTitle);
    } else {
      if (body) body.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">❌ تعذر تحميل سجل التحليلات التفصيلية.</div>';
    }
  } catch (err) {
    console.error("Error fetching Phase 2 history:", err);
    if (body) body.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">❌ خطأ في الاتصال بالخادم.</div>';
  }
}

function openSavedPhase2ForCurrentProduct() {
  const domTitle = document.getElementById("details-title")?.textContent?.trim() || "";
  const title = currentProductForDetails?.title || currentProductForDetails?.name || (domTitle && domTitle !== "تفاصيل الإعلان والنشاط" ? domTitle : "");
  const productId = currentProductForDetails?.id || currentProductForDetails?.product_id || document.getElementById("p2-product-id")?.value || "";
  openSavedPhase2Modal(title, productId);
}

function closePhase2HistoryModal() {
  const modal = document.getElementById("phase2-history-modal");
  if (modal) modal.style.display = "none";
}

function showAllPhase2History() {
  const searchInput = document.getElementById("p2-history-search-input");
  if (searchInput) searchInput.value = "";
  currentFilterTitle = "";
  currentPhase2HistoryCache = allPhase2HistoryCache;
  renderPhase2HistoryList(allPhase2HistoryCache);
}

function renderPhase2HistoryList(items, filterTitle = "") {
  const body = document.getElementById("p2-history-list-body");
  if (!body) return;

  if (!items || items.length === 0) {
    const displayTitle = filterTitle || currentFilterTitle;
    body.innerHTML = `
      <div style="text-align: center; padding: 2.5rem 1rem; color: var(--color-text-muted);">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;">📭</div>
        <h4 style="font-weight: 800; font-size: 1.1rem; color: var(--color-text-main); margin-bottom: 0.5rem;">
          ${displayTitle ? `لا توجد تحليلات تفصيلية محفوظة للمنتج: "${displayTitle}"` : "لا توجد تحليلات تفصيلية محفوظة حتى الآن"}
        </h4>
        <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
          <button class="btn btn-success" onclick="closePhase2HistoryModal(); openPhase2FromDetails();" style="padding: 0.65rem 1.4rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); border: none; font-size: 0.88rem; border-radius: var(--radius-sm); cursor: pointer;">
            🚀 إجراء أول تحليل تفصيلي (Phase 2) الآن
          </button>
          ${displayTitle ? `<button class="btn btn-secondary" onclick="showAllPhase2History();" style="padding: 0.65rem 1.4rem; font-weight: 700; font-size: 0.88rem; border-radius: var(--radius-sm); cursor: pointer;">🌐 عرض سجل جميع المنتجات</button>` : ""}
        </div>
      </div>
    `;
    return;
  }

  body.innerHTML = items
    .map((item, idx) => {
      const res = item.result || {};
      const fm = res.financial_model || {};
      const cleanTitle = item.product_title || (item.title ? item.title.replace(/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/iu, "") : "منتج بدون عنوان");
      const createdAt = item.created_at || "";
      const verdict = res.executive_verdict || "";
      const aiPoweredBy = item.ai_powered_by || res.ai_powered_by || "Internal Engine";

      return `
      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; display: flex; flex-direction: column; gap: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
          <div>
            <h4 style="font-weight: 800; font-size: 1rem; color: var(--color-primary); margin: 0 0 4px 0;">📦 ${cleanTitle}</h4>
            <div style="display: flex; gap: 10px; align-items: center; font-size: 0.78rem; color: var(--color-text-muted); flex-wrap: wrap;">
              <span>📅 تاريخ التحليل: ${createdAt}</span>
              <span style="background: rgba(99,102,241,0.15); color: #6366f1; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">🤖 ${aiPoweredBy}</span>
            </div>
          </div>
          <button class="btn btn-primary" onclick="viewPhase2HistoryItem(${idx})" style="padding: 6px 14px; font-size: 0.82rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); border: none; white-space: nowrap; cursor: pointer;">
            👁️ عرض الدراسة
          </button>
        </div>
        ${verdict ? `<div style="font-size: 0.82rem; color: var(--color-text-main); background: var(--bg-input); padding: 6px 10px; border-radius: 4px; border-right: 3px solid #10b981;">💡 ${verdict}</div>` : ""}
        <div style="display: flex; gap: 15px; font-size: 0.8rem; color: var(--color-text-muted); flex-wrap: wrap;">
          <span>💰 سعر البيع: <strong style="color: var(--color-text-main);">${fm.selling_price || 0} DH</strong></span>
          <span>📈 هامش الربح: <strong style="color: #10b981;">+${fm.net_margin_pct || 0}%</strong></span>
          <span>💵 صافي الربح: <strong style="color: #6366f1;">${fm.net_profit_per_order || 0} DH/طلب</strong></span>
        </div>
      </div>
    `;
    })
    .join("");
}

function viewPhase2HistoryItem(idx) {
  if (currentPhase2HistoryCache && currentPhase2HistoryCache[idx]) {
    const item = currentPhase2HistoryCache[idx];
    if (item.result) {
      closePhase2HistoryModal();
      const cleanTitle = item.product_title || (item.title ? item.title.replace(/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/iu, "") : "منتج");
      renderPhase2Results(item.result, cleanTitle);
    }
  }
}

function filterPhase2HistoryList() {
  const query = (document.getElementById("p2-history-search-input")?.value || "").toLowerCase().trim();
  if (!query) {
    currentPhase2HistoryCache = allPhase2HistoryCache;
    renderPhase2HistoryList(allPhase2HistoryCache);
    return;
  }
  const filtered = allPhase2HistoryCache.filter((item) => {
    const title = (item.product_title || item.title || "").toLowerCase();
    const verdict = (item.result?.executive_verdict || "").toLowerCase();
    return title.includes(query) || verdict.includes(query);
  });
  currentPhase2HistoryCache = filtered;
  renderPhase2HistoryList(filtered, query);
}
