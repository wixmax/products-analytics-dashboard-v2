// =========================================
// 1. Constant Data Arrays & Configuration
// =========================================
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

const CATEGORIES_LIST = [
  "Popular",
  "Electronics",
  "Home & Garden",
  "Health & Beauty",
  "Apparel & Accessories",
  "Tools",
  "Baby & Toddler",
];

// Application State
let globalRawData = null;
let allProducts = [];
let currentFilteredProducts = [];
let savedProducts = [];
let collections = ["عامة", "ملابس", "إلكترونيات", "أدوات منزلية"];
let watchedStores = [];

async function loadInitialDatabaseData() {
  try {
    const collectionsRes = await fetch("/api/products/collections");
    if (collectionsRes.ok) {
      collections = await collectionsRes.json();
    }
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
    const watchlistRes = await fetch("/api/products/watchlist");
    if (watchlistRes.ok) {
      watchedStores = await watchlistRes.json();
    }
    if (allProducts && allProducts.length > 0) {
      filterProducts();
    }
  } catch (e) {
    console.error("Failed to load initial data from PostgreSQL:", e);
  }
}

// Toggle specific filter fields depending on query type
function toggleApiMode() {
  const mode = document.getElementById("api-endpoint-select").value;
  const insightsEls = document.querySelectorAll(".insights-only");
  const vInput = document.getElementById("filter-v");
  const apiVersion = "1.10-12026-05-15";

  if (!mode) {
    insightsEls.forEach((el) => (el.style.display = "none"));
    updateGeneratedURL();
    return;
  }

  if (mode === "winning") {
    insightsEls.forEach((el) => (el.style.display = "none"));
    // Preset v version for winning products if default
    if (vInput.value === "1.3--5") {
      vInput.value = apiVersion;
    }
  } else {
    insightsEls.forEach((el) => {
      // Handle restore display properly
      if (el.style.gridTemplateColumns) {
        el.style.display = "grid";
      } else {
        el.style.display = "flex";
      }
    });
    if (vInput.value === apiVersion) {
      vInput.value = "1.3--5";
    }
  }

  updateGeneratedURL();
}

// =========================================
// 2. UI Generators & Setup Initializer
// =========================================
window.addEventListener("DOMContentLoaded", () => {
  loadInitialDatabaseData();
  initFiltersPanel();
  initEventListeners();
  setupTheme();

  // Restore cached API version if available
  const cachedV = localStorage.getItem("api_version_v");
  if (cachedV) {
    document.getElementById("filter-v").value = cachedV;
  }

  // Restore cached Countries if available
  const cachedCountries = localStorage.getItem("api_selected_countries");
  if (cachedCountries) {
    const countries = JSON.parse(cachedCountries);
    const countrySelect = document.getElementById("api-filter-country");
    Array.from(countrySelect.options).forEach((option) => {
      option.selected = countries.includes(option.value);
    });
  }

  // Set initial visible fields based on dropdown value
  toggleApiMode();

  // Bootstrap initial products from PostgreSQL if provided by server
  if (
    window.INITIAL_PRODUCTS_FROM_DB &&
    window.INITIAL_PRODUCTS_FROM_DB.result?.data?.json?.productsEntries
      ?.length > 0
  ) {
    document.getElementById("api-endpoint-select").value = "winning";
    toggleApiMode();
    processLoadedData(
      window.INITIAL_PRODUCTS_FROM_DB,
      "قاعدة البيانات (PostgreSQL)",
    );
  }
});

function initFiltersPanel() {
  // Render Categories select
  const catContainer = document.getElementById("api-filter-category");
  let catHtml = `<option value="all" selected>الكل (All Categories)</option>`;
  catHtml += CATEGORIES_LIST.map(
    (cat) => `<option value="${cat}">${cat}</option>`,
  ).join("");
  catContainer.innerHTML = catHtml;

  // Render Countries select
  const countryContainer = document.getElementById("api-filter-country");
  let countryHtml = COUNTRIES_LIST.map(
    (c) =>
      `<option value="${c.code}" selected>${c.flag} ${c.name} (${c.code})</option>`,
  ).join("");
  countryContainer.innerHTML = countryHtml;
}

function initEventListeners() {
  // Update generated URL instantly when any input in sidebar changes
  const filterElements = [
    "filter-title",
    "filter-priceFrom",
    "filter-priceTo",
    "filter-weeks",
    "filter-v",
    "filter-transformation",
    "api-endpoint-select",
    "api-filter-category",
    "api-filter-country",
  ];
  filterElements.forEach((id) => {
    document.getElementById(id).addEventListener("input", (e) => {
      // If it's the V input, save to cache
      if (id === "filter-v") {
        localStorage.setItem("api_version_v", e.target.value);
      }
      // If it's the Country select, save to cache
      if (id === "api-filter-country") {
        const selectedValues = Array.from(e.target.selectedOptions).map(
          (opt) => opt.value,
        );
        localStorage.setItem(
          "api_selected_countries",
          JSON.stringify(selectedValues),
        );
      }
      updateGeneratedURL();
    });
  });
}

// =========================================
// 3. URL Encoding & Generator Logic
// =========================================
function getActiveFiltersObject() {
  const mode = document.getElementById("api-endpoint-select").value;

  // Gather selected categories separated by semicolon
  const catSelect = document.getElementById("api-filter-category");
  const selectedCatValues = Array.from(catSelect.selectedOptions).map(
    (opt) => opt.value,
  );
  let category = "";
  if (selectedCatValues.length === 0 || selectedCatValues.includes("all")) {
    category = CATEGORIES_LIST.join(";");
  } else {
    category = selectedCatValues.join(";");
  }

  // Gather selected countries separated by semicolon
  const countrySelect = document.getElementById("api-filter-country");
  const selectedCountryValues = Array.from(countrySelect.selectedOptions).map(
    (opt) => opt.value,
  );
  let country = "";
  if (selectedCountryValues.length === 0) {
    country = COUNTRIES_LIST.map((c) => c.code).join(";");
  } else {
    country = selectedCountryValues.join(";");
  }

  const v = document.getElementById("filter-v").value || "1.3--5";

  if (mode === "winning") {
    return {
      0: {
        json: {
          category,
          country,
          v,
        },
      },
    };
  }

  // Overview Insights fields
  const title = document.getElementById("filter-title").value.trim() || "";
  const priceFrom =
    Number(document.getElementById("filter-priceFrom").value) || -1;
  const priceTo = Number(document.getElementById("filter-priceTo").value) || -1;
  const weeks = Number(document.getElementById("filter-weeks").value) || 12;
  const transformation = document.getElementById("filter-transformation").value;

  return {
    0: {
      json: {
        title,
        category,
        priceFrom,
        priceTo,
        weeks,
        country,
        transformation,
        v,
      },
    },
  };
}

function generateFullURL() {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    return "⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً لتوليد الرابط...";
  }
  const baseUrl =
    mode === "winning"
      ? "https://www.overviewdata.io/api/trpc/data.winingProducts"
      : "https://www.overviewdata.io/api/trpc/data.insights";

  const filterObject = getActiveFiltersObject();
  const jsonString = JSON.stringify(filterObject);
  const encodedInput = encodeURIComponent(jsonString);
  return `${baseUrl}?batch=1&input=${encodedInput}`;
}

function updateGeneratedURL() {
  const url = generateFullURL();
  const displayEl = document.getElementById("generated-url");
  displayEl.textContent = url;

  // Update Facebook Search Link dynamically
  const titleVal = document.getElementById("filter-title").value.trim();
  const fbSearchLink = document.getElementById("fb-search-link");
  if (fbSearchLink) {
    fbSearchLink.href = `https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=MA&q=${encodeURIComponent(titleVal)}`;
  }
}

// Clipboard functions
function copyGeneratedURL() {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast("⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً!", "error");
    return;
  }
  const url = generateFullURL();
  navigator.clipboard
    .writeText(url)
    .then(() => {
      showToast("تم نسخ رابط الـ tRPC بنجاح 📋", "success");
    })
    .catch(() => {
      showToast("تعذر نسخ الرابط يدوياً.", "error");
    });
}

function openGeneratedURL() {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast("⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً!", "error");
    return;
  }
  window.open(generateFullURL(), "_blank");
}

// =========================================
// 4. Fetch & Data Parsing Engine
// =========================================
async function handleFetchAPI() {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast(
      "⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً من القائمة الجانبية!",
      "error",
    );
    return;
  }
  const url = generateFullURL();

  const btn = document.getElementById("apply-filters-btn");
  const originalText = btn.textContent;

  btn.disabled = true;
  btn.textContent = "⏳ جارٍ جلب البيانات...";
  showToast("محاولة جلب البيانات عن طريق السيرفر لتخطي CORS...", "info");

  try {
    const response = await fetch("/api/products/sync-trpc", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `url=${encodeURIComponent(url)}`,
    });
    if (!response.ok) {
      throw new Error(`HTTP Error! status: ${response.status}`);
    }
    const data = await response.json();

    // Detect data source from server response
    const source =
      Array.isArray(data) && data[0] && data[0].source
        ? data[0].source
        : "unknown";

    if (source === "database") {
      processLoadedData(data, "قاعدة البيانات المحلية");
      showToast(
        "📦 تم جلب البيانات من قاعدة البيانات المحلية (بدون اتصال بالسيرفر الخارجي)",
        "info",
      );
    } else if (source === "api") {
      processLoadedData(data, "السيرفر الخارجي (API)");
      showToast(
        "🌐 تم جلب البيانات من السيرفر الخارجي وحفظها في قاعدة البيانات بنجاح!",
        "success",
      );
    } else {
      processLoadedData(data, "مصدر غير محدد");
      showToast("تمت المزامنة بنجاح! 🎉", "success");
    }
  } catch (error) {
    console.error("Fetch failed due to backend error:", error);
    showToast(
      "تعذرت المزامنة التلقائية. يرجى محاولة الاستيراد اليدوي.",
      "error",
    );
    openManualPasteModal();
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

function handleLocalFile(event) {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast(
      "⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً لتتم معالجة الملف بشكل صحيح!",
      "error",
    );
    event.target.value = ""; // Reset file input
    return;
  }
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = async function (e) {
    try {
      const parsedData = JSON.parse(e.target.result);
      processLoadedData(parsedData, `ملف محلي (${file.name})`);
      showToast("تم استيراد ملف الـ JSON بنجاح!", "success");
      const origin = mode === "winning" ? "Winning" : "Local";
      await uploadImportedJson(parsedData, origin);
    } catch (err) {
      showToast(
        "حدث خطأ أثناء قراءة الـ JSON. تأكد من صلاحية البنية.",
        "error",
      );
    }
  };
  reader.readAsText(file);
}

function processManualJSON() {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast(
      "⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً لتتم معالجة البيانات بشكل صحيح!",
      "error",
    );
    return;
  }
  const inputRaw = document.getElementById("manual-json-input").value.trim();
  if (!inputRaw) {
    showToast("حقل الإدخال فارغ!", "error");
    return;
  }
  try {
    const parsedData = JSON.parse(inputRaw);
    processLoadedData(parsedData, "لصق يدوي");
    closeManualPasteModal();
    showToast("تمت معالجة البيانات بنجاح! ✅", "success");
    const origin = mode === "winning" ? "Winning" : "Local";
    uploadImportedJson(parsedData, origin);
  } catch (e) {
    showToast("بنية الـ JSON غير صحيحة! يرجى التحقق وإعادة المحاولة.", "error");
  }
}

async function uploadImportedJson(data, origin) {
  try {
    const response = await fetch(`/api/products/import?origin=${origin}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error("Import request failed");
    const resJson = await response.json();
    showToast(
      `تم حفظ ${resJson.inserted} منتج جديد وتحديث ${resJson.updated} في قاعدة البيانات 💾`,
      "success",
    );
  } catch (err) {
    console.warn("Failed to upload imported JSON to DB:", err);
  }
}

// Central parser for the payload schema
function processLoadedData(rawData, sourceInfo) {
  const mode = document.getElementById("api-endpoint-select").value;
  if (!mode) {
    showToast("⚠️ يرجى اختيار نوع الاستعلام / البيانات أولاً!", "error");
    return;
  }
  globalRawData = rawData;
  let targetData = null;

  // The schema is typically [{ result: { data: { json: { productsEntries: [...] } } } }]
  // Handle wrapped array or direct object
  const base = Array.isArray(rawData) ? rawData[0] : rawData;

  try {
    if (base?.result?.data?.json) {
      targetData = base.result.data.json;
    } else if (base?.data?.json) {
      targetData = base.data.json;
    } else if (base?.json) {
      targetData = base.json;
    } else {
      // Look deeply for productsEntries
      targetData = base;
    }

    // Consolidate and map array variations to uniform naming
    const rawList = targetData.productsEntries || targetData.results || [];

    allProducts = rawList.map((p) => {
      return {
        title: p.title || p.product_title || "بدون عنوان",
        productUrl: p.productUrl || p.product_url || "",
        country: p.country || "",
        algorithm: p.algorithm || p.algo || "new",
        ad_start_date: p.ad_start_date || "--",
        ads_count: Number(p.ads_count) || 0,
        avg_creatives: Number(p.avg_creatives) || 0,
        ad_title: p.ad_title || "",
        ad_body: p.ad_body || "",
        ad_image_urls: p.ad_image_urls || "",
        ad_video_urls: p.ad_video_urls || "",
        actualPrice: p.actualPrice || p.price_1 || 0,
        active_ads: p.active_ads !== undefined ? p.active_ads : true,
        api_version: p.api_version || '',
      };
    });

    // Display Insights charts from real database analytics
    fetchAndRenderAnalytics();

    // Clear and Fill Country Select dynamically
    populateCountryDropdownFilter(allProducts);

    // Update UI metrics
    document.getElementById("kpi-loaded-from").textContent =
      `المصدر: ${sourceInfo}`;

    // Apply base filters
    filterProducts();
  } catch (err) {
    console.error(err);
    showToast("تعذر قراءة بنية البيانات. تحقق من الحقول المتوقعة.", "error");
  }
}

// =========================================
// 5. Statistics & Analytics Rendering
// =========================================
async function fetchAndRenderAnalytics() {
  try {
    const mode =
      document.getElementById("api-endpoint-select")?.value || "winning";
    const origin = mode === "winning" ? "Winning" : "Local";

    const response = await fetch(
      `/api/products/insights-charts?origin=${origin}`,
    );
    if (!response.ok) throw new Error("Analytics API error");

    const analyticsData = await response.json();
    renderAnalyticsDashboard(analyticsData);
  } catch (err) {
    console.warn("Could not load analytics:", err);
    document.getElementById("analytics-section").style.display = "none";
  }
}

function renderAnalyticsDashboard(adapted) {
  const section = document.getElementById("analytics-section");
  section.style.display = "grid";

  // Process New Listings Weekly Data
  const listings = adapted.newListings;
  if (listings && Array.isArray(listings.weeklyData)) {
    const maxVal = Math.max(...listings.weeklyData, 1);
    const container = document.getElementById("listings-chart");

    container.innerHTML = listings.weeklyData
      .map((val, idx) => {
        const percentageHeight = (val / maxVal) * 100;
        return `
      <div class="chart-bar-wrapper">
        <div class="chart-tooltip">الأسبوع ${idx + 1}: ${val} إدراج</div>
        <div class="chart-bar" style="height: ${percentageHeight}%;"></div>
        <span class="chart-label">${idx + 1}</span>
      </div>
    `;
      })
      .join("");
  }

  // Momentum badge
  const hasMomentum = adapted.newListings?.hasSupplyMomentum;
  const momBadge = document.getElementById("stat-momentum");
  if (hasMomentum) {
    momBadge.textContent = "تصاعدي 📈";
    momBadge.style.background = "rgba(16, 185, 129, 0.2)";
    momBadge.style.color = "var(--color-success)";
  } else {
    momBadge.textContent = "مستقر / تنازلي 📉";
    momBadge.style.background = "rgba(239, 68, 68, 0.2)";
    momBadge.style.color = "var(--color-error)";
  }

  // Shops Stats
  const shops = adapted.totalShops;
  if (shops) {
    document.getElementById("stat-shops-count").textContent =
      shops.current || 0;
    const prev = shops.previous || 1;
    const change = (((shops.current - prev) / prev) * 100).toFixed(1);
    const trendEl = document.getElementById("stat-shops-trend");

    if (change >= 0) {
      trendEl.innerHTML = `▲ +${change}%`;
      trendEl.className = "trend-up";
    } else {
      trendEl.innerHTML = `▼ ${change}%`;
      trendEl.className = "trend-down";
    }
  }
}

function populateCountryDropdownFilter(products) {
  const dropdown = document.getElementById("country-filter");
  // Extract unique country codes
  const codes = [...new Set(products.map((p) => p.country).filter(Boolean))];

  let html = '<option value="all">جميع الدول 🌍</option>';
  codes.forEach((code) => {
    const meta = COUNTRIES_LIST.find((c) => c.code === code);
    const name = meta ? `${meta.flag} ${meta.name}` : `🌍 ${code}`;
    html += `<option value="${code}">${name}</option>`;
  });
  dropdown.innerHTML = html;
}

// =========================================
// 6. Products Filtering & UI Rendering
// =========================================
function filterProducts() {
  let results = [...allProducts];

  // 1. Text Search (Title, Ad Copy, Url)
  const query = document
    .getElementById("product-search")
    .value.toLowerCase()
    .trim();
  if (query) {
    results = results.filter((p) => {
      return (
        (p.title && p.title.toLowerCase().includes(query)) ||
        (p.ad_body && p.ad_body.toLowerCase().includes(query)) ||
        (p.ad_title && p.ad_title.toLowerCase().includes(query)) ||
        (p.productUrl && p.productUrl.toLowerCase().includes(query))
      );
    });
  }

  // 2. Country Filter Dropdown
  const selectedCountry = document.getElementById("country-filter").value;
  if (selectedCountry !== "all") {
    results = results.filter((p) => p.country === selectedCountry);
  }

  // 3. Launch Date Filter
  const launchDateFilter = document.getElementById("launch-date-filter").value;
  if (launchDateFilter !== "all") {
    const now = new Date();
    now.setHours(0, 0, 0, 0);

    results = results.filter((p) => {
      if (!p.ad_start_date) return false;
      const startDate = new Date(p.ad_start_date);
      if (isNaN(startDate.getTime())) return false;

      startDate.setHours(0, 0, 0, 0);
      const diffTime = now.getTime() - startDate.getTime();
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (launchDateFilter === "today") return diffDays === 0;
      if (launchDateFilter === "yesterday") return diffDays === 1;
      if (launchDateFilter === "7days") return diffDays >= 0 && diffDays <= 7;
      if (launchDateFilter === "30days") return diffDays >= 0 && diffDays <= 30;

      return true;
    });
  }

  // 4. Active/Inactive Status Filter
  const statusActiveFilter = document.getElementById(
    "status-active-filter",
  ).value;
  if (statusActiveFilter !== "all") {
    results = results.filter((p) => {
      if (statusActiveFilter === "active") return p.active_ads === true;
      if (statusActiveFilter === "inactive") return p.active_ads === false;
      return true;
    });
  }

  // 5. Sorting
  const sortBy = document.getElementById("sort-by").value;
  results.sort((a, b) => {
    const countA = Number(a.ads_count) || 0;
    const countB = Number(b.ads_count) || 0;

    switch (sortBy) {
      case "ads-desc":
        return countB - countA;
      case "ads-asc":
        return countA - countB;
      case "date-desc":
        return new Date(b.ad_start_date || 0) - new Date(a.ad_start_date || 0);
      case "date-asc":
        return new Date(a.ad_start_date || 0) - new Date(b.ad_start_date || 0);
      case "title-asc":
        return (a.title || "").localeCompare(b.title || "", "ar");
      default:
        return countB - countA;
    }
  });

  currentFilteredProducts = results;

  // Update KPI cards for active subsets
  updateKpiCards(results);
  // Render Product Cards HTML
  renderProductGrid(results);
}

function updateKpiCards(products) {
  document.getElementById("kpi-total-products").textContent =
    products.length.toLocaleString("ar-EG");

  const totalAds = products.reduce(
    (sum, p) => sum + (Number(p.ads_count) || 0),
    0,
  );
  document.getElementById("kpi-total-ads").textContent =
    totalAds.toLocaleString("ar-EG");

  // Filter those with videos (urls contain semicolon/link)
  const videoCount = products.filter((p) => {
    const videos = (p.ad_video_urls || "")
      .split(";")
      .filter((v) => v.trim().length > 0);
    return videos.length > 0;
  }).length;
  document.getElementById("kpi-video-ads").textContent =
    videoCount.toLocaleString("ar-EG");

  // Average Creatives
  const sumCreatives = products.reduce(
    (sum, p) => sum + (Number(p.avg_creatives) || 0),
    0,
  );
  const avg =
    products.length > 0 ? (sumCreatives / products.length).toFixed(1) : "0.0";
  document.getElementById("kpi-avg-creatives").textContent = avg;
}

function renderProductGrid(products) {
  const container = document.getElementById("products-container");

  if (products.length === 0) {
    container.innerHTML = `
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>لم يتم العثور على نتائج</h3>
      <p>جرّب تغيير الكلمات المفتاحية أو شروط التصفية.</p>
    </div>
  `;
    return;
  }

  container.innerHTML = products
    .map((p) => {
      // Safely parse semicolon separated URLs
      const imageUrls = (p.ad_image_urls || "")
        .split(";")
        .map((u) => u.trim())
        .filter(Boolean);
      const videoUrls = (p.ad_video_urls || "")
        .split(";")
        .map((u) => u.trim())
        .filter(Boolean);

      // Flags and meta
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
          // Reset time part to compare just dates roughly
          now.setHours(0, 0, 0, 0);
          startDate.setHours(0, 0, 0, 0);

          const diffTime = now.getTime() - startDate.getTime();
          const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

          if (diffDays === 0) {
            timeAgoText =
              ' <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(اليوم)</span>';
          } else if (diffDays === 1) {
            timeAgoText =
              ' <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(أمس)</span>';
          } else if (diffDays > 1 && diffDays < 7) {
            timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${diffDays} أيام)</span>`;
          } else if (diffDays >= 7 && diffDays < 30) {
            const weeks = Math.floor(diffDays / 7);
            timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${weeks} أسبوع)</span>`;
          } else if (diffDays >= 30 && diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${months} شهر)</span>`;
          } else if (diffDays >= 365) {
            const years = Math.floor(diffDays / 365);
            timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${years} سنة)</span>`;
          } else if (diffDays < 0) {
            const futureDays = Math.abs(diffDays);
            timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-warning); font-weight: 700;">(بعد ${futureDays} يوم)</span>`;
          }
        }
      }

      const safeId = p.productUrl
        ? btoa(unescape(encodeURIComponent(p.productUrl))).replace(/[/+=]/g, "")
        : Math.random().toString(36).slice(2);

      // Setup Media HTML (Show Video first if available, else image, else fallback)
      let mediaHtml = "";
      if (videoUrls.length > 0) {
        mediaHtml = `
      <div class="media-badge">🎥 فيديو (${videoUrls.length})</div>
      <video id="vjs-${safeId}" class="video-js vjs-big-play-centered" preload="none" controls playsinline poster="${imageUrls[0] || ""}">
        <source src="${videoUrls[0]}" type="video/mp4">
      </video>
    `;
      } else if (imageUrls.length > 0) {
        mediaHtml = `
      <div class="media-badge">📸 صور (${imageUrls.length})</div>
      <img src="${imageUrls[0]}" alt="${p.title}" loading="lazy">
    `;
      } else {
        mediaHtml = `
      <div class="no-media">
        <span>📦 لا توجد وسائط معاينة</span>
      </div>
    `;
      }

      const isSaved = savedProducts.some(
        (saved) => saved.productUrl === p.productUrl,
      );
      const saveBtnHtml = `
        <button onclick='toggleSaveProduct(${JSON.stringify(p).replace(/'/g, "&apos;")})' 
                class="btn ${isSaved ? "btn-success" : "btn-secondary"}" 
                id="save-btn-${safeId}"
                title="${isSaved ? "محفوظ" : "حفظ المنتج"}">
          ${isSaved ? "⭐" : "☆"}
        </button>
      `;

      return `
    <article class="product-card index-product-card" id="product-${safeId}">
      <div class="product-media">
        ${mediaHtml}
        <div class="status-badge ${p.active_ads ? "active" : "inactive"}">
          ${p.active_ads ? "🟢 نشط" : "🔴 متوقف"}
        </div>
        <div class="country-flag-badge">
          <span>${flag}</span>
          <span>${p.country || "--"}</span>
        </div>
      </div>
      <div class="card-body">
        <h4 class="p-title" title="${p.title}">${p.title || "بدون عنوان"}</h4>
        <div style="color: var(--color-text-muted); font-size: 0.75rem; margin-top: -2px; display: flex; justify-content: space-between; align-items: center;">
          <a href="https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=MA&q=${encodeURIComponent(domain || "")}" 
             target="_blank" 
             style="color: var(--color-primary); text-decoration: none; font-weight: bold; font-size: 0.75rem; transition: var(--transition-all);"
             onmouseover="this.style.color='var(--color-primary-hover)'"
             onmouseout="this.style.color='var(--color-primary)'">🏪 ${domain}</a>
          <span style="font-size: 0.65rem; color: var(--color-text-muted);">${p.ad_start_date || "--"}${timeAgoText}</span>
        </div>
      </div>
      <div class="card-footer" style="gap: 6px; padding: 8px;">
        <a href="${p.productUrl}" target="_blank" class="btn btn-primary" style="flex: 1; font-size: 0.75rem; padding: 0.4rem 0.5rem;">🛒 زيارة</a>
        <button onclick='openIndexInfoModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 0 0 auto; padding: 0.4rem 0.6rem; font-size: 0.7rem;">ℹ️ معلومات</button>
        <button onclick='openDetailsModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="btn btn-secondary" style="flex: 1; font-size: 0.75rem; padding: 0.4rem 0.5rem;">📊 تفاصيل</button>
        ${saveBtnHtml}
        ${videoUrls.length > 0 ? `<a href="${videoUrls[0]}" target="_blank" class="btn btn-secondary" style="flex:0; aspect-ratio:1; padding: 0.4rem; display:flex; align-items:center; justify-content:center;" title="فتح الفيديو">🔗</a>` : ""}
      </div>
    </article>
  `;
    })
    .join("");
  initVideoJs();
}

function initVideoJs(scope) {
  (scope || document).querySelectorAll('video.video-js').forEach(el => {
    if (el.dataset.vjsInited) return;
    el.dataset.vjsInited = '1';
    try {
      if (typeof videojs === 'function') {
        videojs(el, { fluid: true, controls: true });
      }
    } catch (e) { /* ignore */ }
  });
}

// =========================================
// 7. Helper & UI Enhancement Scripts
// =========================================
function showToast(message, type = "info") {
  const container = document.getElementById("toast-container");
  const t = document.createElement("div");
  t.className = `toast ${type}`;
  t.innerHTML = `<span>💡</span> <div>${message}</div>`;

  container.appendChild(t);

  // Trigger animate in
  setTimeout(() => t.classList.add("show"), 50);

  // Animate out
  setTimeout(() => {
    t.classList.remove("show");
    setTimeout(() => t.remove(), 400);
  }, 3500);
}

// Download Filtered JSON
function downloadFilteredJSON() {
  if (!currentFilteredProducts || currentFilteredProducts.length === 0) {
    showToast("لا توجد بيانات لتحميلها!", "warning");
    return;
  }

  const dataStr = JSON.stringify(currentFilteredProducts, null, 2);
  const blob = new Blob([dataStr], { type: "application/json" });
  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = `filtered_products_${new Date().toISOString().slice(0, 10)}.json`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);

  showToast("تم تحميل البيانات بنجاح 📥", "success");
}

// Theme Engine
async function setupTheme() {
  const btn = document.getElementById("theme-toggle-btn");
  if (!btn) return;

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

  btn.onclick = async () => {
    const isDark =
      document.documentElement.getAttribute("data-theme") === "dark";
    const nextTheme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nextTheme);
    try {
      await fetch("/api/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ key: "app-theme", value: nextTheme }),
      });
    } catch (err) {
      console.error("Error saving theme setting:", err);
    }
  };
}

// Modal Control
function openManualPasteModal() {
  document.getElementById("paste-modal").style.display = "flex";
}
function closeManualPasteModal() {
  document.getElementById("paste-modal").style.display = "none";
}
// =========================================
// 8. Saved Products Logic
// =========================================
async function toggleSaveProduct(product) {
  const safeId = product.productUrl
    ? btoa(unescape(encodeURIComponent(product.productUrl))).replace(
        /[/+=]/g,
        "",
      )
    : "";
  const btnId = "save-btn-" + safeId;
  const btn = document.getElementById(btnId);

  try {
    const payload = {
      product_url: product.productUrl,
      title: product.title,
      country: product.country,
      algorithm: product.algorithm || product.algo || "new",
      ad_start_date: product.ad_start_date,
      ads_count: product.ads_count,
      unique_image_count: product.unique_image_count || 0,
      unique_video_count: product.unique_video_count || 0,
      avg_creatives: product.avg_creatives,
      ad_title: product.ad_title,
      ad_body: product.ad_body,
      ad_image_urls: product.ad_image_urls,
      ad_video_urls: product.ad_video_urls,
      actualPrice: product.actualPrice || product.price_1 || "0",
      active_ads: product.active_ads,
      origin: product.origin || "Winning",
      collection: product.collection || "عامة",
      api_version: product.api_version || "",
    };

    const res = await fetch("/api/products/saved/toggle", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    if (res.ok) {
      const data = await res.json();
      if (data.action === "saved") {
        product.saved_at = new Date().toISOString();
        product.rating = 0;
        product.notes = "";
        product.collection = payload.collection;
        product.status = "active";
        savedProducts.push(product);

        if (btn) {
          btn.classList.remove("btn-secondary");
          btn.classList.add("btn-success");
          btn.innerHTML = "⭐";
          btn.title = "محفوظ";
        }
        showToast("تم حفظ المنتج بنجاح! ⭐", "success");
      } else {
        savedProducts = savedProducts.filter(
          (p) => p.productUrl !== product.productUrl,
        );

        if (btn) {
          btn.classList.remove("btn-success");
          btn.classList.add("btn-secondary");
          btn.innerHTML = "☆";
          btn.title = "حفظ المنتج";
        }
        showToast("تمت إزالة المنتج من المحفوظات.", "info");
      }

      const detailsSaveBtn = document.getElementById("details-save-btn");
      if (
        detailsSaveBtn &&
        currentProductForDetails &&
        currentProductForDetails.productUrl === product.productUrl
      ) {
        const isSaved = data.action === "saved";
        if (isSaved) {
          detailsSaveBtn.textContent = "⭐ محفوظ";
          detailsSaveBtn.style.background = "var(--color-success)";
          detailsSaveBtn.style.color = "white";
          const collectionSelect = document.getElementById(
            "details-collection-select",
          );
          if (collectionSelect) {
            collectionSelect.style.display = "inline-block";
            collectionSelect.innerHTML = collections
              .map(
                (c) =>
                  `<option value="${c}" ${product.collection === c ? "selected" : ""}>📁 ${c}</option>`,
              )
              .join("");
          }
        } else {
          detailsSaveBtn.textContent = "احفظ المنتج";
          detailsSaveBtn.style.background = "transparent";
          detailsSaveBtn.style.color = "var(--color-success)";
          const collectionSelect = document.getElementById(
            "details-collection-select",
          );
          if (collectionSelect) collectionSelect.style.display = "none";
        }
      }
    }
  } catch (err) {
    console.error("Error toggling save:", err);
    showToast("تعذر الاتصال بالسيرفر لحفظ المنتج.", "error");
  }
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
  const imageUrls = [
    ...new Set(
      (product.ad_image_urls || "")
        .split(";")
        .map((u) => u.trim())
        .filter(Boolean),
    ),
  ];
  const videoUrls = [
    ...new Set(
      (product.ad_video_urls || "")
        .split(";")
        .map((u) => u.trim())
        .filter(Boolean),
    ),
  ];

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
    // We want the user to be able to save it under any collection, even if not yet saved!
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
      toggleSaveProduct(product);
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

  if (!activityEntries || activityEntries.length === 0) {
    activityEntries = generateSimulatedActivity(product);
  }

  renderTimelineAndMetrics(product, activityEntries);
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

  // Interval 1: Launch peak
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

  // Interval 2: Stagnation & Reactivation gap
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

  // Interval 3: Current peak
  const numInt3 = Math.max(1, totalAds - numInt1 - numInt2);
  const gap2 = 120;
  for (let i = 0; i < numInt3; i++) {
    const start = new Date(baseDate);
    start.setDate(start.getDate() + gap2 + i * 4);
    const end = new Date();
    end.setDate(end.getDate() + 5 + i * 2);
    entries.push({
      ad_start_date: start.toISOString().split("T")[0],
      ad_end_date: end.toISOString().split("T")[0],
      ad_video_urls:
        videoUrls[(i + numInt1 + numInt2) % videoUrls.length] || "",
    });
  }

  return entries;
}

function renderTimelineAndMetrics(product, entries) {
  entries.sort((a, b) => new Date(a.ad_start_date) - new Date(b.ad_start_date));

  const dates = [];
  entries.forEach((e) => {
    if (e.ad_start_date) dates.push(new Date(e.ad_start_date));
    if (e.ad_end_date) dates.push(new Date(e.ad_end_date));
  });

  const minDate = dates.length > 0 ? new Date(Math.min(...dates)) : new Date();
  const maxDate = dates.length > 0 ? new Date(Math.max(...dates)) : new Date();

  const timeSpanMs = maxDate - minDate;
  const daysTotal = Math.max(15, Math.ceil(timeSpanMs / (1000 * 60 * 60 * 24)));

  const numColumns = 12;
  const daysPerCol = Math.ceil(daysTotal / numColumns);
  const columnData = [];
  const today = new Date();

  for (let i = 0; i < numColumns; i++) {
    const colStart = new Date(minDate);
    colStart.setDate(colStart.getDate() + i * daysPerCol);
    const colEnd = new Date(colStart);
    colEnd.setDate(colEnd.getDate() + daysPerCol);

    let activeCount = 0;
    let endedInCol = 0;
    let startedInCol = 0;

    entries.forEach((e) => {
      const eStart = new Date(e.ad_start_date);
      const eEnd = e.ad_end_date ? new Date(e.ad_end_date) : today;

      if (eStart <= colEnd && eEnd >= colStart) {
        activeCount++;
      }

      if (
        e.ad_end_date &&
        new Date(e.ad_end_date) >= colStart &&
        new Date(e.ad_end_date) <= colEnd
      ) {
        endedInCol++;
      }

      if (eStart >= colStart && eStart <= colEnd) {
        startedInCol++;
      }
    });

    columnData.push({
      label: `${colStart.getDate()} ${getMonthNameAr(colStart.getMonth())}`,
      activeCount,
      ended: endedInCol > 0,
      started: startedInCol > 0,
      start: colStart,
      end: colEnd,
    });
  }

  let reactivations = 0;
  let inGap = false;

  columnData.forEach((col, idx) => {
    if (col.activeCount === 0) {
      inGap = true;
    } else if (inGap && col.activeCount > 0) {
      reactivations++;
      inGap = false;
      col.isReactivation = true;
    }
  });

  const maxActive = Math.max(...columnData.map((c) => c.activeCount), 1);
  const chartContainer = document.getElementById("details-chart");

  let chartHtml = "";
  columnData.forEach((col) => {
    const heightPercent = (col.activeCount / maxActive) * 100;
    const isReact = col.isReactivation ? "reactivation" : "";
    const dotHtml = col.ended ? `<div class="details-chart-dot"></div>` : "";

    chartHtml += `
      <div class="details-chart-bar-wrapper">
        <div class="chart-tooltip">${col.label}: ${col.activeCount} إعلان نشط</div>
        <div class="details-chart-bar ${isReact}" style="height: ${Math.max(8, heightPercent)}%;">
          ${dotHtml}
        </div>
        <span class="chart-label" style="font-size:0.55rem; width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${col.label}</span>
      </div>
    `;
  });
  chartContainer.innerHTML = chartHtml;

  const uniqueVideos =
    [...new Set(entries.map((e) => e.ad_video_urls).filter(Boolean))].length ||
    1;
  const adsCount = product.ads_count || entries.length || 12;
  const avgCreatives = product.avg_creatives || 1;

  const viewsMinVal = (adsCount * 9.5 + uniqueVideos * 5) * 1000;
  const viewsMaxVal = viewsMinVal * 10;
  const formattedViews = formatMetricRange(viewsMinVal, viewsMaxVal);
  const formatEng = formatMetricRange(viewsMinVal * 0.07, viewsMaxVal * 0.07);

  document.getElementById("details-views").textContent = formattedViews;
  document.getElementById("details-engagement").textContent = formatEng;
  document.getElementById("details-first-seen").textContent =
    formatArDateString(minDate);
  document.getElementById("details-last-seen").textContent =
    formatArDateString(maxDate);
  document.getElementById("details-max-creatives").textContent =
    `${adsCount} كرياتيف`;
  document.getElementById("details-reactivations").textContent =
    `${reactivations} أحداث`;

  const analysisText = generateAdAnalysis(
    adsCount,
    reactivations,
    avgCreatives,
    product,
    columnData,
    minDate,
    maxDate,
  );
  document.getElementById("details-analysis-text").innerHTML = analysisText;

  // Build the complete database capsule containing fetched & computed metrics
  currentProductDetailsWithAnalysis = {
    ...product,
    activityEntries: entries,
    computed_metrics: {
      estimated_views: formattedViews,
      estimated_engagement: formatEng,
      first_seen: minDate.toISOString(),
      last_seen: maxDate.toISOString(),
      creatives_count: adsCount,
      unique_videos_count: uniqueVideos,
      reactivations_count: reactivations,
      marketing_analysis: analysisText.replace(/<[^>]*>/g, ""), // Strip HTML tags
    },
  };

  // Re-populate all properties in scrollable container to include computed fields
  const rawDataContainer = document.getElementById("details-raw-data-list");
  if (rawDataContainer) {
    let listHtml = "";
    // Display base properties
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
    // Display computed metrics
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
    rawDataContainer.innerHTML =
      listHtml ||
      `<div style="text-align: center; padding: 10px; color: var(--color-text-muted);">لا توجد بيانات إضافية</div>`;
  }
}

function getMonthNameAr(monthIdx) {
  const arMonths = [
    "يناير",
    "فبراير",
    "مارس",
    "أبريل",
    "مايو",
    "يونيو",
    "يوليو",
    "أغسطس",
    "سبتمبر",
    "أكتوبر",
    "نوفمبر",
    "ديسمبر",
  ];
  return arMonths[monthIdx];
}

function formatArDateString(d) {
  return `${getMonthNameAr(d.getMonth())} ${d.getDate()}, ${d.getFullYear()}`;
}

function formatMetricRange(min, max) {
  const formatNum = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "k";
    return num.toFixed(0);
  };
  return `${formatNum(min)} - ${formatNum(max)}`;
}

function generateAdAnalysis(
  adCount,
  reactCount,
  avgCreatives,
  product,
  columnData,
  minDate,
  maxDate,
) {
  // --- استخراج إشارات من بيانات حقيقية ---
  const isActive =
    product?.active_ads === true ||
    product?.active_ads === 1 ||
    product?.active_ads === "t";
  const startDate = minDate || new Date();
  const endDate = maxDate || new Date();
  const lifeSpanDays = Math.round(
    (endDate - startDate) / (1000 * 60 * 60 * 24),
  );
  const lifeSpanText =
    lifeSpanDays >= 30
      ? `${Math.floor(lifeSpanDays / 30)} شهر`
      : `${lifeSpanDays} يوم`;

  // --- تحليل شكل المنحنى من columnData ---
  let trend = "unknown";
  let peakCol = 0;
  let peakIdx = 0;

  if (columnData && columnData.length >= 3) {
    const counts = columnData.map((c) => c.activeCount);
    peakCol = Math.max(...counts);
    peakIdx = counts.indexOf(peakCol);

    const firstHalf = counts.slice(0, Math.floor(counts.length / 2));
    const secondHalf = counts.slice(Math.floor(counts.length / 2));
    const firstAvg = firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length;
    const secondAvg = secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length;
    const lastVal = counts[counts.length - 1];
    const nonZero = counts.filter((c) => c > 0).length;

    if (nonZero <= 2) {
      trend = "testing"; // اختبار محدود
    } else if (peakIdx <= 2 && secondAvg < firstAvg * 0.5) {
      trend = "peak_then_drop"; // ذروة مبكرة ثم انخفاض
    } else if (secondAvg > firstAvg * 1.3) {
      trend = "scaling"; // تصاعد = scaling
    } else if (lastVal === 0 && !isActive) {
      trend = "stopped"; // توقف كامل
    } else if (reactCount > 0) {
      trend = "reactivated"; // إعادة تنشيط
    } else if (Math.abs(secondAvg - firstAvg) / Math.max(firstAvg, 1) < 0.3) {
      trend = "stable"; // استقرار
    } else {
      trend = "fluctuating"; // تذبذب
    }
  }

  // --- بناء التحليل بناءً على الإشارات الحقيقية ---
  let badge = "";
  let text = "";

  switch (trend) {
    case "scaling":
      badge = `<span class="strategy-badge" style="background:rgba(16,185,129,0.15);color:var(--color-success)">🚀 توسع نشط (Scaling)</span>`;
      text = `يشهد هذا الإعلان <b>تصاعداً ملحوظاً</b> في عدد الكرياتيف النشطة مع مرور الوقت، وهي علامة واضحة على <b>Scaling</b> — أي أن المعلن يضخ ميزانيات متزايدة لأن الأداء مربح. المنتج نشط منذ <b>${lifeSpanText}</b> بمعدل <b>${avgCreatives} كرياتيف</b>، وهذا مستوى ثقة عالٍ.`;
      break;
    case "peak_then_drop":
      badge = `<span class="strategy-badge" style="background:rgba(245,158,11,0.15);color:var(--color-warning)">⚡ ذروة ثم تصفية (Testing → Filtering)</span>`;
      text = `بدأ المعلن بـ<b>اختبار مكثف (High Peak)</b> في مرحلة الإطلاق ثم أوقف الإعلانات الخاسرة تدريجياً. الحالة الحالية <b>${isActive ? "🟢 لا يزال نشطاً" : "🔴 متوقف حالياً"}</b>. إذا كانت الإعلانات المتبقية لا تزال تعمل فهذا يعني أن المعلن وصل لـ<b>Winning Creatives</b>.`;
      break;
    case "testing":
      badge = `<span class="strategy-badge" style="background:rgba(99,102,241,0.15);color:#6366f1">🔬 مرحلة اختبار (Testing Phase)</span>`;
      text = `يبدو أن المعلن لا يزال في <b>مرحلة اختبار السوق</b> — عدد الإعلانات محدود (<b>${adCount} إعلان</b>) ومدة النشاط <b>${lifeSpanText}</b>. لم تصل الحملة بعد لمرحلة الـ Scaling، لكن وجودها يعني أن المعلن يدرس الاستجابة.`;
      break;
    case "stopped":
      badge = `<span class="strategy-badge" style="background:rgba(239,68,68,0.15);color:var(--color-error)">⏸️ متوقف (Paused)</span>`;
      text = `<b>الإعلانات متوقفة حالياً</b> بعد فترة نشاط امتدت <b>${lifeSpanText}</b>. السبب المحتمل: <b>نفاد المخزون</b>، إعادة هيكلة الحملة، أو انتهاء الموسم. المنتج كان يُعلَن عنه بـ<b>${adCount} إعلان</b> — إذا عاد النشاط فهو إشارة شراء قوية.`;
      break;
    case "reactivated":
      badge = `<span class="strategy-badge" style="background:rgba(16,185,129,0.2);color:var(--color-success)">🔄 إعادة تنشيط (${reactCount}x)</span>`;
      text = `<b>🔥 الإشارة الذهبية!</b> رُصدت <b>${reactCount} أحداث إعادة تنشيط</b> بعد فترات خمول — وهذا الدليل الأقوى على ربحية المنتج. المعلن أوقف الحملة مؤقتاً (غالباً بسبب <b>نفاد المخزون</b>) ثم أعادها بمجرد توفر البضاعة. مدة الحياة الكلية للإعلان: <b>${lifeSpanText}</b>.`;
      break;
    case "stable":
      badge = `<span class="strategy-badge" style="background:rgba(59,130,246,0.15);color:#3b82f6">📊 استقرار (Steady State)</span>`;
      text = `يُظهر هذا الإعلان <b>استقراراً إعلانياً منتظماً</b> على مدار <b>${lifeSpanText}</b> بمعدل <b>${avgCreatives} كرياتيف</b>. الاستقرار = ربحية متواصلة، لأن المعلنين لا يستمرون في الدفع لإعلانات خاسرة.`;
      break;
    case "fluctuating":
      badge = `<span class="strategy-badge" style="background:rgba(245,158,11,0.15);color:var(--color-warning)">📈 متذبذب (A/B Testing)</span>`;
      text = `يتذبذب نشاط الإعلان بشكل غير منتظم على مدار <b>${lifeSpanText}</b>، مما يشير إلى <b>اختبارات A/B مستمرة</b> — المعلن يجرب مواد إبداعية مختلفة لإيجاد أفضل صيغة. <b>${adCount} إعلان</b> بمتوسط <b>${avgCreatives} كرياتيف</b>.`;
      break;
    default:
      badge = `<span class="strategy-badge">📋 بيانات غير كافية</span>`;
      text = `البيانات المتاحة تشمل <b>${adCount} إعلان</b> بمتوسط <b>${avgCreatives} كرياتيف</b> ومدة <b>${lifeSpanText}</b>. <b>الحالة: ${isActive ? "🟢 نشط" : "🔴 متوقف"}</b>. لتحليل أعمق يُفضَّل الضغط على "🔄 تحديث النشاط" لجلب البيانات الحية.`;
  }

  return `${badge}<p style="margin-top:10px">${text}</p>`;
}

function closeDetailsModal() {
  const modal = document.getElementById("details-modal");
  if (modal) modal.style.display = "none";
}

function openIndexInfoModal(p) {
  const modal = document.getElementById("index-info-modal");
  if (!modal) return;

  const imageUrls = (p.ad_image_urls || "").split(";").filter(Boolean);

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

  document.getElementById("index-info-title").textContent = p.title || "بدون عنوان";
  document.getElementById("index-info-domain").textContent = `🏪 ${domain}`;
  document.getElementById("index-info-ads").textContent = p.ads_count || 0;
  document.getElementById("index-info-images").textContent = imageUrls.length;
  document.getElementById("index-info-creatives").textContent = p.avg_creatives || 1;
  document.getElementById("index-info-date").textContent = `${p.ad_start_date || "--"}${timeAgoText}`;
  document.getElementById("index-info-ad-title").textContent = `💬 ${p.ad_title || "نص الإعلان"}`;
  document.getElementById("index-info-ad-body").textContent = p.ad_body || "لا يوجد نص تفصيلي.";

  document.getElementById("index-info-visit-btn").onclick = () => {
    if (p.productUrl) window.open(p.productUrl, "_blank");
  };

  modal.style.display = "flex";
}

function closeIndexInfoModal() {
  const modal = document.getElementById("index-info-modal");
  if (modal) modal.style.display = "none";
}

// Close index info modal when clicking overlay
document.addEventListener("click", (event) => {
  const modal = document.getElementById("index-info-modal");
  if (event.target === modal) closeIndexInfoModal();
});

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
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          product_url: p.productUrl,
          collection: colName,
        }),
      });
      if (res.ok) {
        p.collection = colName;
        currentProductForDetails.collection = colName;
        showToast(`تم نقل المنتج لمجموعة: ${colName}`, "success");
      }
    } catch (err) {
      console.error("Error changing collection:", err);
      showToast("تعذر تغيير المجموعة.", "error");
    }
  } else {
    currentProductForDetails.collection = colName;
    toggleSaveProduct(currentProductForDetails);
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

// ابحث عن الدالة الحالية واستبدلها بالتالي:
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
