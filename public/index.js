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
let activeAiFilterEvaluations = null;
let currentAiFilterVerdict = "all";

async function loadInitialDatabaseData() {
  try {
    const collectionsRes = await fetch("/api/products/collections");
    if (collectionsRes.ok) {
      const data = await collectionsRes.json();
      collections =
        data && data.length > 0
          ? data
          : [
              "\u0639\u0627\u0645\u0629",
              "\u0645\u0644\u0627\u0628\u0633",
              "\u0625\u0644\u0643\u062a\u0631\u0648\u0646\u064a\u0627\u062a",
              "\u0623\u062f\u0648\u0627\u062a \u0645\u0646\u0632\u0644\u064a\u0629",
            ];
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

  if (!mode) {
    insightsEls.forEach((el) => (el.style.display = "none"));
    updateGeneratedURL();
    return;
  }

  if (mode === "winning") {
    insightsEls.forEach((el) => (el.style.display = "none"));
  } else {
    insightsEls.forEach((el) => {
      if (el.style.gridTemplateColumns) {
        el.style.display = "grid";
      } else {
        el.style.display = "flex";
      }
    });
  }

  updateGeneratedURL();
}

let availableSnapshotDates = [];
// Keeps a reference to the active flatpickr instance for re-rendering
let _fpDateInstance = null;

/**
 * Map frontend mode values to backend origin strings
 */
function _getOriginFromMode(mode) {
  const map = {
    winning: "Winning",
    insights: "Local",
    china: "China",
    japan: "Japan",
  };
  return map[mode] || "";
}

/**
 * Fetch available-dates from backend for the given origin, then refresh
 * the flatpickr calendar in-place (no full re-init needed after first call).
 */
async function refreshDatePickerForOrigin(origin) {
  try {
    const params = origin ? `?origin=${encodeURIComponent(origin)}` : "";
    const res = await fetch(`/api/products/available-dates${params}`);
    if (!res.ok) return;
    const data = await res.json();

    if (data && Array.isArray(data.snapshotDates)) {
      availableSnapshotDates = Array.from(new Set(data.snapshotDates));
    }

    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    const allDates =
      data && Array.isArray(data.dates)
        ? Array.from(new Set(data.dates))
        : [...availableSnapshotDates];
    const allowedSet = new Set(allDates);
    allowedSet.add(todayStr);

    if (_fpDateInstance) {
      // Update enable rules and redraw
      _fpDateInstance.set("enable", [
        function (date) {
          const fmt = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
          return allowedSet.has(fmt);
        },
      ]);
      // Clear selected date if it's no longer available for this origin
      const cur = _fpDateInstance.selectedDates[0];
      if (cur) {
        const curStr = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, "0")}-${String(cur.getDate()).padStart(2, "0")}`;
        if (!allowedSet.has(curStr)) {
          _fpDateInstance.clear();
          localStorage.removeItem("api_filter_date");
          updateGeneratedURL();
        }
      }
      _fpDateInstance.redraw();
    }
  } catch (err) {
    console.error("Failed to refresh available dates for origin:", err);
  }
}

async function initDatePickerWithSnapshotIndicators() {
  let allSelectableDates = [];

  // Get current origin from the endpoint selector (if already chosen)
  const initialMode =
    document.getElementById("api-endpoint-select")?.value || "";
  const initialOrigin = _getOriginFromMode(initialMode);

  try {
    const params = initialOrigin
      ? `?origin=${encodeURIComponent(initialOrigin)}`
      : "";
    const res = await fetch(`/api/products/available-dates${params}`);
    if (res.ok) {
      const data = await res.json();
      if (data && Array.isArray(data.snapshotDates)) {
        availableSnapshotDates = Array.from(new Set(data.snapshotDates));
      }
      if (data && Array.isArray(data.dates)) {
        allSelectableDates = Array.from(new Set(data.dates));
      }
    }
  } catch (err) {
    console.error("Failed to fetch available snapshot dates:", err);
  }

  const now = new Date();
  const yearStr = now.getFullYear();
  const monthStr = String(now.getMonth() + 1).padStart(2, "0");
  const dayStr = String(now.getDate()).padStart(2, "0");
  const todayStr = `${yearStr}-${monthStr}-${dayStr}`;

  const allowedSet = new Set(allSelectableDates);
  allowedSet.add(todayStr);

  _fpDateInstance = flatpickr("#filter-date", {
    dateFormat: "Y-m-d",
    allowInput: false,
    maxDate: "today",
    enable: [
      function (date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const d = String(date.getDate()).padStart(2, "0");
        return allowedSet.has(`${y}-${m}-${d}`);
      },
    ],
    onDayCreate: function (dObj, dStr, fp, dayElem) {
      if (!dayElem.dateObj) return;
      const y = dayElem.dateObj.getFullYear();
      const m = String(dayElem.dateObj.getMonth() + 1).padStart(2, "0");
      const d = String(dayElem.dateObj.getDate()).padStart(2, "0");
      const dateStr = `${y}-${m}-${d}`;

      const isToday = dateStr === todayStr;
      const hasSnapshot = availableSnapshotDates.includes(dateStr);

      if (isToday) {
        if (hasSnapshot) {
          dayElem.classList.add("today-has-snapshot");
          dayElem.title = `تاريخ اليوم (${dateStr}) - يوجد نسخة مسجلة في قاعدة البيانات ✅`;
          const badge = document.createElement("span");
          badge.className = "snapshot-date-badge today-snapshot-badge";
          badge.innerHTML = "●";
          dayElem.appendChild(badge);
        } else {
          dayElem.classList.add("today-no-snapshot");
          dayElem.title = `تاريخ اليوم (${dateStr}) - لا توجد نسخة مسجلة بعد (جاهز للجلب ⚡)`;
          const badge = document.createElement("span");
          badge.className = "snapshot-date-badge today-no-snapshot-badge";
          badge.innerHTML = "⚡";
          dayElem.appendChild(badge);
        }
      } else if (hasSnapshot) {
        dayElem.classList.add("has-snapshot-date");
        dayElem.title = `نسخة مسجلة في قاعدة البيانات (${dateStr})`;
        const badge = document.createElement("span");
        badge.className = "snapshot-date-badge";
        badge.innerHTML = "●";
        dayElem.appendChild(badge);
      }
    },
    onChange: (dates, dateStr) => {
      if (dateStr) {
        localStorage.setItem("api_filter_date", dateStr);
        document.cookie = `api_filter_date=${dateStr}; path=/; max-age=86400`;
        const newUrl = new URL(window.location);
        newUrl.searchParams.set("date", dateStr);
        window.history.replaceState(null, "", newUrl);
      } else {
        localStorage.removeItem("api_filter_date");
        document.cookie = `api_filter_date=; path=/; max-age=0`;
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete("date");
        window.history.replaceState(null, "", newUrl);
      }
      updateGeneratedURL();
      fetchAiAnalysisHistory();
    },
  });

  const urlParams = new URLSearchParams(window.location.search);
  const paramDate = urlParams.get("date");
  const cachedDate = paramDate || localStorage.getItem("api_filter_date");
  if (cachedDate && allowedSet.has(cachedDate)) {
    _fpDateInstance.setDate(cachedDate, false);
  }
}

// =========================================
// 2. UI Generators & Setup Initializer
// =========================================
window.addEventListener("DOMContentLoaded", () => {
  loadInitialDatabaseData();
  initFiltersPanel();
  initEventListeners();
  setupTheme();

  // Initialize Flatpickr for date picker with snapshot indicators & past date protection
  initDatePickerWithSnapshotIndicators();

  // Restore cached API version if available
  const cachedVersion = localStorage.getItem("api_version_v");
  if (cachedVersion) {
    document.getElementById("filter-version").value = cachedVersion;
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
  let initialProductsList = [];
  if (window.INITIAL_PRODUCTS_FROM_DB) {
    const data = window.INITIAL_PRODUCTS_FROM_DB;
    const target =
      data.result?.data?.json ?? data.data?.json ?? data.json ?? data;
    initialProductsList =
      target?.productsEntries ||
      target?.results ||
      (Array.isArray(target) ? target : []);
  }

  const urlParams = new URLSearchParams(window.location.search);
  const paramDate = urlParams.get("date");
  const userSavedDate = paramDate || localStorage.getItem("api_filter_date");

  let loadedSnapshotDate = "";

  if (initialProductsList.length > 0) {
    const origin = window.INITIAL_PRODUCTS_FROM_DB.origin || "Winning";
    const apiVersion = window.INITIAL_PRODUCTS_FROM_DB.api_version || "";

    if (origin === "Winning") {
      document.getElementById("api-endpoint-select").value = "winning";
    } else if (origin === "Local") {
      document.getElementById("api-endpoint-select").value = "insights";
    }

    if (apiVersion) {
      let versionNum = apiVersion;
      const datePatternMatch =
        apiVersion.match(/^(.*)-(\d{4}-\d{2}-\d{2})$/) ||
        apiVersion.match(/^(.*)(\d{4}-\d{2}-\d{2})$/);
      if (datePatternMatch) {
        versionNum = datePatternMatch[1];
        loadedSnapshotDate = datePatternMatch[2];
      } else if (window.INITIAL_PRODUCTS_FROM_DB.created_at) {
        loadedSnapshotDate = window.INITIAL_PRODUCTS_FROM_DB.created_at.slice(
          0,
          10,
        );
      }

      document.getElementById("filter-version").value = versionNum;
    }

    const activeDate = userSavedDate || loadedSnapshotDate;
    if (activeDate) {
      document
        .getElementById("filter-date")
        ?._flatpickr?.setDate(activeDate, false);
    }

    toggleApiMode();

    const dateLabel = window.INITIAL_PRODUCTS_FROM_DB.created_at
      ? `آخر لقطة محفوظة (#${window.INITIAL_PRODUCTS_FROM_DB.snapshot_id} - ${window.INITIAL_PRODUCTS_FROM_DB.created_at.slice(0, 16)})`
      : "آخر لقطة محفوظة";

    processLoadedData(window.INITIAL_PRODUCTS_FROM_DB, dateLabel);
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
  let countryHtml = `<option value="all">🌍 الكل (All Countries)</option>`;
  countryHtml += COUNTRIES_LIST.map(
    (c) =>
      `<option value="${c.code}" ${c.code === "MA" ? "selected" : ""}>${c.flag} ${c.name} (${c.code})</option>`,
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
    "filter-version",
    "filter-transformation",
    "api-endpoint-select",
    "api-filter-category",
    "api-filter-country",
  ];
  filterElements.forEach((id) => {
    document.getElementById(id).addEventListener("input", (e) => {
      // If it's the version input, save to cache
      if (id === "filter-version") {
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
      // If the endpoint/origin changes, refresh calendar dates for that origin
      if (id === "api-endpoint-select") {
        const newOrigin = _getOriginFromMode(e.target.value);
        refreshDatePickerForOrigin(newOrigin);
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
  if (
    selectedCountryValues.length === 0 ||
    selectedCountryValues.includes("all")
  ) {
    country = COUNTRIES_LIST.map((c) => c.code).join(";");
  } else {
    country = selectedCountryValues.join(";");
  }

  const versionNum = document.getElementById("filter-version").value || "1.10";
  const dateStr = document.getElementById("filter-date").value || "";
  const v = dateStr ? `${versionNum}${dateStr}` : versionNum;

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

    // Detect data source & duplication status from server response
    const firstItem = Array.isArray(data) && data[0] ? data[0] : {};
    const source = firstItem.source || "unknown";
    const isDuplicate = Boolean(firstItem.is_duplicate);

    if (source === "database") {
      processLoadedData(data, "قاعدة البيانات المحلية");
      showToast(
        "📦 تم جلب البيانات من قاعدة البيانات المحلية (بدون اتصال بالسيرفر الخارجي)",
        "info",
      );
    } else if (source === "api") {
      processLoadedData(data, "السيرفر الخارجي (API)");
      if (isDuplicate) {
        showToast(
          "ℹ️ البيانات القادمة مطابقة 100% لنسخة مسجلة مسبقاً، تم تجاوز إعادة الحفظ وتجنب التكرار.",
          "info",
        );
      } else {
        showToast(
          "✨ تم جلب وتسجيل بيانات جديدة بنجاح في قاعدة البيانات (نسخة جديدة)!",
          "success",
        );
      }
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

  try {
    let rawList = [];
    if (Array.isArray(rawData)) {
      if (rawData.length > 0) {
        const first = rawData[0];
        if (
          first &&
          typeof first === "object" &&
          (first.productUrl !== undefined ||
            first.product_url !== undefined ||
            first.title !== undefined ||
            first.product_title !== undefined)
        ) {
          // Direct list of products
          rawList = rawData;
        } else {
          // Wrapped array
          const base = rawData[0];
          const targetData =
            base?.result?.data?.json ??
            base?.data?.json ??
            base?.json ??
            base ??
            {};
          rawList =
            targetData.productsEntries ||
            targetData.results ||
            (Array.isArray(targetData) ? targetData : []);
        }
      } else {
        rawList = [];
      }
    } else if (rawData && typeof rawData === "object") {
      // Single object wrapper or single product
      const targetData =
        rawData.result?.data?.json ??
        rawData.data?.json ??
        rawData.json ??
        rawData;
      rawList =
        targetData.productsEntries ||
        targetData.results ||
        (Array.isArray(targetData) ? targetData : [targetData]);
    }

    allProducts = rawList.map((p) => {
      const priceVal = p.price || p.actualPrice || p.price_1 || 0;
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
        actualPrice: priceVal,
        price: priceVal,
        active_ads: p.active_ads !== undefined ? p.active_ads : true,
        api_version: p.api_version || "",
      };
    });

    window.adaptedResult = { productsEntries: allProducts };

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

  // 0. AI Evaluation Filter (Show only products evaluated/selected by AI when AI analysis is active)
  if (
    activeAiFilterEvaluations &&
    Array.isArray(activeAiFilterEvaluations) &&
    activeAiFilterEvaluations.length > 0
  ) {
    const evalMap = new Set();
    const verdictMap = new Map();

    activeAiFilterEvaluations.forEach((ev) => {
      const v = ev.verdict || "";
      if (ev.url) {
        evalMap.add(ev.url);
        verdictMap.set(ev.url, v);
      }
      if (ev.id) {
        evalMap.add(String(ev.id));
        verdictMap.set(String(ev.id), v);
      }
      if (ev.title) {
        const t = ev.title.trim().toLowerCase();
        evalMap.add(t);
        verdictMap.set(t, v);
      }
      if (ev.product_title) {
        const t = ev.product_title.trim().toLowerCase();
        evalMap.add(t);
        verdictMap.set(t, v);
      }
    });

    results = results.filter((p) => {
      const pUrl = p.productUrl || p.product_url || "";
      const pTitle = (p.title || "").trim().toLowerCase();
      const pId = p.id ? String(p.id) : "";

      const isEvaluated =
        evalMap.has(pUrl) ||
        evalMap.has(pTitle) ||
        evalMap.has(pId) ||
        Array.from(evalMap).some(
          (key) =>
            key.length > 5 && (pTitle.includes(key) || key.includes(pTitle)),
        );

      if (!isEvaluated) return false;

      if (currentAiFilterVerdict && currentAiFilterVerdict !== "all") {
        if (currentAiFilterVerdict === "budget_fit") {
          const budgetFitSet = new Set();
          activeAiFilterEvaluations.forEach((ev) => {
            if (ev.is_budget_fit === true || ev.is_budget_fit === "true" || ev.is_budget_fit === 1) {
              if (ev.url) budgetFitSet.add(ev.url);
              if (ev.product_url) budgetFitSet.add(ev.product_url);
              if (ev.id) budgetFitSet.add(String(ev.id));
              if (ev.title) budgetFitSet.add(ev.title.trim().toLowerCase());
              if (ev.product_title) budgetFitSet.add(ev.product_title.trim().toLowerCase());
              if (ev.name) budgetFitSet.add(ev.name.trim().toLowerCase());
            }
          });

          const targetCount = Number(document.getElementById("ai-cnt-budget")?.textContent) || 2;
          if (budgetFitSet.size > targetCount || budgetFitSet.size === 0) {
            budgetFitSet.clear();
            const topBudgetEvals = [...activeAiFilterEvaluations]
              .sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0))
              .slice(0, targetCount);

            topBudgetEvals.forEach((ev) => {
              if (ev.url) budgetFitSet.add(ev.url);
              if (ev.product_url) budgetFitSet.add(ev.product_url);
              if (ev.id) budgetFitSet.add(String(ev.id));
              if (ev.title) budgetFitSet.add(ev.title.trim().toLowerCase());
              if (ev.product_title) budgetFitSet.add(ev.product_title.trim().toLowerCase());
              if (ev.name) budgetFitSet.add(ev.name.trim().toLowerCase());
            });
          }

          return (
            budgetFitSet.has(pUrl) ||
            budgetFitSet.has(pTitle) ||
            budgetFitSet.has(pId) ||
            Array.from(budgetFitSet).some(
              (key) =>
                key.length > 5 && (pTitle.includes(key) || key.includes(pTitle))
            )
          );
        }
        const cardVerdict =
          verdictMap.get(pUrl) || verdictMap.get(pTitle) || verdictMap.get(pId);
        return cardVerdict === currentAiFilterVerdict;
      }
      return true;
    });
  }

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

// State for Progressive Lazy Rendering
let currentProductsList = [];
let renderedProductsCount = 0;
const PRODUCTS_PER_BATCH = 16;
let infiniteScrollObserver = null;

function renderProductGrid(products) {
  const container = document.getElementById("products-container");
  currentProductsList = products || [];
  renderedProductsCount = 0;

  if (infiniteScrollObserver) {
    infiniteScrollObserver.disconnect();
    infiniteScrollObserver = null;
  }

  if (currentProductsList.length === 0) {
    container.innerHTML = `
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>لم يتم العثور على نتائج</h3>
      <p>جرّب تغيير الكلمات المفتاحية أو شروط التصفية.</p>
    </div>
  `;
    return;
  }

  container.innerHTML = "";
  loadNextProductBatch();
  setupInfiniteScrollObserver();
}

function loadNextProductBatch() {
  const container = document.getElementById("products-container");
  if (!container || renderedProductsCount >= currentProductsList.length) return;

  const start = renderedProductsCount;
  const end = Math.min(start + PRODUCTS_PER_BATCH, currentProductsList.length);
  const batch = currentProductsList.slice(start, end);
  renderedProductsCount = end;

  const batchHtml = batch.map((p) => buildProductCardHtml(p)).join("");

  const sentinel = document.getElementById("infinite-scroll-sentinel");
  if (sentinel) {
    sentinel.insertAdjacentHTML("beforebegin", batchHtml);
  } else {
    container.insertAdjacentHTML("beforeend", batchHtml);
  }

  initVideoJs(container);
  if (typeof ensureVideoThumbnails === "function") {
    ensureVideoThumbnails(container);
  }
  if (window.currentAiEvaluations && window.currentAiEvaluations.length > 0) {
    applyAiBadgesToProductCards(window.currentAiEvaluations);
  }

  if (renderedProductsCount >= currentProductsList.length) {
    const s = document.getElementById("infinite-scroll-sentinel");
    if (s) s.remove();
  }
}

function setupInfiniteScrollObserver() {
  const container = document.getElementById("products-container");
  if (!container || renderedProductsCount >= currentProductsList.length) return;

  let sentinel = document.getElementById("infinite-scroll-sentinel");
  if (!sentinel) {
    sentinel = document.createElement("div");
    sentinel.id = "infinite-scroll-sentinel";
    sentinel.className = "infinite-scroll-sentinel";
    sentinel.innerHTML = "<span>⏳ جاري تحميل المزيد من المنتجات...</span>";
    container.appendChild(sentinel);
  }

  if (typeof IntersectionObserver !== "undefined") {
    infiniteScrollObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (
            entry.isIntersecting &&
            renderedProductsCount < currentProductsList.length
          ) {
            loadNextProductBatch();
          }
        });
      },
      { rootMargin: "300px" },
    );
    infiniteScrollObserver.observe(sentinel);
  }
}

function buildProductCardHtml(p) {
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

  // Setup Media HTML
  let mediaHtml = "";
  if (videoUrls.length > 0) {
    mediaHtml = `
      <div class="media-badge">🎥 فيديو (${videoUrls.length})</div>
      <div class="vid-placeholder" data-vid-src="${videoUrls[0]}" data-vid-poster="${imageUrls[0] || ""}" data-product-id="${p.id || ""}" data-product-url="${p.productUrl || ""}" id="vp-${safeId}">
        ${imageUrls[0] ? `<img src="${imageUrls[0]}" alt="" class="vid-placeholder-img">` : `<div class="vid-placeholder-bg"></div>`}
        <div class="vid-play-btn">▶</div>
      </div>
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
    <article class="product-card index-product-card card-lazy-load" id="product-${safeId}">
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
        ${videoUrls.length < 0 ? `<a href="${videoUrls[0]}" target="_blank" class="btn btn-secondary" style="flex:0; aspect-ratio:1; padding: 0.4rem; display:flex; align-items:center; justify-content:center;" title="فتح الفيديو">🔗</a>` : ""}
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

  window.addEventListener(
    "scroll",
    () => {
      if (window.scrollY > 300) {
        btn.classList.add("visible");
      } else {
        btn.classList.remove("visible");
      }
    },
    { passive: true },
  );

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initBackToTop();
});

let vidObserver = null;

function initVideoJs(scope) {
  if (!vidObserver && typeof IntersectionObserver !== "undefined") {
    vidObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const ph = entry.target;
            loadVideoPlaceholder(ph);
            vidObserver.unobserve(ph);
          }
        });
      },
      { rootMargin: "200px" },
    );
  }

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
    } catch (e) {
      /* ignore */
    }
  });

  (scope || document)
    .querySelectorAll(".vid-placeholder:not([data-vid-loaded])")
    .forEach((el) => {
      el.dataset.vidLoaded = "1";
      if (vidObserver) {
        vidObserver.observe(el);
      } else {
        loadVideoPlaceholder(el);
      }
    });
}

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
    if (activeVid && mountedTarget)
      return { vid: activeVid, player: activePlayer, container: mountedTarget };

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
        if (typeof player.addClass === "function")
          player.addClass("vjs-has-started");
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
  const vid = document.createElement("video");
  vid.id = id ? id.replace("vp-", "vjs-") : "";
  vid.className = "video-js";
  vid.controls = true;
  vid.playsInline = true;
  vid.preload = "auto";
  if (posterUrl) vid.poster = posterUrl;
  const source = document.createElement("source");
  source.src = src;
  source.type = "video/mp4";
  vid.appendChild(source);
  return vid;
}

function initVjs(vid, shouldAutoplay = false) {
  try {
    if (typeof videojs === "function" && !vid.dataset.vjsInited) {
      vid.dataset.vjsInited = "1";
      const player = videojs(vid, {
        fluid: true,
        controls: true,
        preload: "auto",
        autoplay: shouldAutoplay ? true : false,
        bigPlayButton: false,
      });
      player.on("play", () => {
        const all = videojs.getPlayers();
        Object.keys(all).forEach((id) => {
          const p = all[id];
          if (
            p !== player &&
            p &&
            typeof p.pause === "function" &&
            !p.paused()
          ) {
            p.pause();
          }
        });
      });
      return player;
    }
  } catch (e) {
    /* ignore */
  }
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

  btn.onclick = async () => {
    const isDark =
      document.documentElement.getAttribute("data-theme") === "dark";
    const nextTheme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nextTheme);
    localStorage.setItem("app-theme", nextTheme);
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

// Shared functions (renderTimelineAndMetrics, getMonthNameAr, formatArDateString, formatMetricRange, generateAdAnalysis) are loaded from analysis-helper.js

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
      else if (diffDays < 30)
        timeAgoText = ` (منذ ${Math.floor(diffDays / 7)} أسبوع)`;
      else timeAgoText = ` (منذ ${Math.floor(diffDays / 30)} شهر)`;
    }
  }

  document.getElementById("index-info-title").textContent =
    p.title || "بدون عنوان";
  document.getElementById("index-info-domain").textContent = `🏪 ${domain}`;
  document.getElementById("index-info-ads").textContent = p.ads_count || 0;
  document.getElementById("index-info-images").textContent = imageUrls.length;
  document.getElementById("index-info-creatives").textContent =
    p.avg_creatives || 1;
  document.getElementById("index-info-date").textContent =
    `${p.ad_start_date || "--"}${timeAgoText}`;
  document.getElementById("index-info-ad-title").textContent =
    `💬 ${p.ad_title || "نص الإعلان"}`;
  document.getElementById("index-info-ad-body").textContent =
    p.ad_body || "لا يوجد نص تفصيلي.";

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
  if (
    currentProductDetailsWithAnalysis &&
    currentProductDetailsWithAnalysis.computed_metrics
  ) {
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

  if (typeof allProducts !== "undefined") {
    const pMain = allProducts.find(
      (p) => p.productUrl === currentProductForDetails.productUrl,
    );
    if (pMain) {
      pMain.actualPrice = val;
      pMain.price_1 = val;
    }
  }
  if (typeof currentFilteredProducts !== "undefined") {
    const pFiltered = currentFilteredProducts.find(
      (p) => p.productUrl === currentProductForDetails.productUrl,
    );
    if (pFiltered) {
      pFiltered.actualPrice = val;
      pFiltered.price_1 = val;
    }
  }

  const pSaved = savedProducts.find(
    (p) => p.productUrl === currentProductForDetails.productUrl,
  );
  if (pSaved) {
    pSaved.actualPrice = val;
    pSaved.price_1 = val;

    try {
      const res = await fetch("/api/products/saved/price", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_url: pSaved.productUrl,
          price: val,
        }),
      });
      if (res.ok) {
        showToast("✅ تم تحديث سعر المنتج في قاعدة البيانات", "success");
      }
    } catch (e) {
      console.error("Failed to save price update to database", e);
    }
  } else {
    showToast(
      "⚠️ السعر محدث مؤقتاً. لحفظه في قاعدة البيانات بشكل دائم، يرجى حفظ المنتج أولاً.",
      "info",
    );
  }

  updateDetailsRawDataView();
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
    const windowScroll =
      window.scrollY ||
      window.pageYOffset ||
      document.documentElement.scrollTop ||
      document.body.scrollTop ||
      0;
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

/* ════════════════════════════════════════════════════════
   AI PRODUCT ANALYST SYSTEM (PHASE 1 LOGIC)
   ════════════════════════════════════════════════════════ */

window.currentAiAnalysis = null;
window.currentAiEvaluations = [];

function openAiAnalysisModal() {
  const modal = document.getElementById("ai-analysis-modal");
  if (modal) modal.style.display = "flex";
  handleAiProviderChangeInModal();
}

async function handleAiProviderChangeInModal() {
  const providerSelect = document.getElementById("ai-provider-select");
  const modelSelect = document.getElementById("ai-model-select");
  if (!modelSelect) return;

  const provider = providerSelect ? providerSelect.value : "auto";
  modelSelect.innerHTML =
    '<option value="">✨ الموديل الافتراضي للمورد</option>';

  if (provider === "internal") {
    modelSelect.innerHTML =
      '<option value="internal-engine">⚡ المحرك الداخلي الفائق</option>';
    return;
  }

  try {
    const res = await fetch("/api/settings/ai_providers_config");
    if (res.ok) {
      const data = await res.json();
      const config =
        data.value && typeof data.value === "string"
          ? JSON.parse(data.value)
          : data.value || null;

      let targetProvider = provider;
      if (provider === "auto" && config && config.active_provider) {
        targetProvider = config.active_provider;
      }

      if (config && config.providers && config.providers[targetProvider]) {
        const pData = config.providers[targetProvider];
        const models = pData.models || [];
        const activeModel = pData.active_model || "";

        let opts = models
          .map(
            (m) =>
              `<option value="${m}" ${m === activeModel ? "selected" : ""}>${m} ${m === activeModel ? "(الافتراضي 🌟)" : ""}</option>`,
          )
          .join("");
        if (opts) {
          modelSelect.innerHTML =
            '<option value="">✨ الموديل الافتراضي للمورد</option>' + opts;
        }
      }
    }
  } catch (e) {
    console.error("Error loading models for AI modal:", e);
  }
}

function closeAiAnalysisModal() {
  const modal = document.getElementById("ai-analysis-modal");
  if (modal) modal.style.display = "none";
}

function openAiHistoryDrawer() {
  const drawer = document.getElementById("ai-history-drawer");
  if (drawer) {
    drawer.style.display = "flex";
    fetchAiAnalysisHistory();
  }
}

function closeAiHistoryDrawer() {
  const drawer = document.getElementById("ai-history-drawer");
  if (drawer) drawer.style.display = "none";
}

function openAiFullReportModal() {
  const modal = document.getElementById("ai-full-report-modal");
  if (modal) {
    renderAiFullReportModalContent();
    modal.style.display = "flex";
  }
}

function closeAiFullReportModal() {
  const modal = document.getElementById("ai-full-report-modal");
  if (modal) modal.style.display = "none";
}

function openAiProductTextModal(identifier) {
  const modal = document.getElementById("ai-product-text-modal");
  if (!modal) return;

  let item = null;
  if (Array.isArray(window.currentAiEvaluations)) {
    item = window.currentAiEvaluations.find(
      (ev) =>
        ev.url === identifier ||
        ev.id === identifier ||
        ev.title === identifier ||
        ev.title.trim() === identifier,
    );
  }

  if (
    !item &&
    window.currentAiAnalysis &&
    window.currentAiAnalysis.summary &&
    window.currentAiAnalysis.summary.top_winner
  ) {
    if (window.currentAiAnalysis.summary.top_winner.title === identifier) {
      item = window.currentAiAnalysis.summary.top_winner;
    }
  }

  if (!item) {
    alert("⚠️ تعذر العثور على بيانات التحليل لهذا المنتج.");
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
  const brk = item.breakdown || {};
  const nar = item.narrative_analysis || {};

  let badgeColor = "#10b981";
  if (item.verdict === "promising") badgeColor = "#f59e0b";
  if (item.verdict === "risk") badgeColor = "#ef4444";

  const summaryText =
    nar.summary ||
    (item.reasons ? item.reasons.join(" ") : "تم تقييم المنتج بنجاح.");
  const marketFitText = nar.market_fit || "يتناسب مع الطلب الحالي في السوق.";
  const logisticsAdviceText =
    nar.logistics_advice || "تكلفة الشحن والتوصيل احتسبت وفق متوسط السوق.";
  const launchStrategyText =
    nar.launch_strategy ||
    item.recommendation ||
    "ينصح باختباره بميزانية مناسبة.";

  container.innerHTML = `
    <!-- Top Header Overview -->
    <div style="display: flex; gap: 1rem; background: var(--bg-input); padding: 1.2rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; align-items: center;">
      <img src="${item.image_url || "/placeholder.webp"}" style="width: 90px; height: 90px; border-radius: var(--radius-sm); object-fit: cover; border: 2px solid var(--color-primary);" onerror="this.src='https://via.placeholder.com/90?text=Product'" />
      <div style="flex: 1;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
          <span style="background: ${badgeColor}; color: white; padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 0.85rem;">
            ${item.verdict_label || "تقييم المنتج"} (${item.score}/100)
          </span>
        </div>
        <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 6px 0; color: var(--color-text-main);">${item.title}</h3>
        <a href="${item.url || "#"}" target="_blank" style="font-size: 0.8rem; color: var(--color-primary); text-decoration: underline;">🔗 رابط المنتج المصدر</a>
      </div>
    </div>

    <!-- Narrative & Text Analysis Section -->
    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
      <div style="background: rgba(99, 102, 241, 0.06); padding: 1rem; border-radius: var(--radius-sm); border-right: 4px solid var(--color-primary);">
        <h4 style="margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 800; color: var(--color-primary); display: flex; align-items: center; gap: 6px;">
          🎯 الملخص والتشخيص التجاري للذكاء الاصطناعي
        </h4>
        <p style="margin: 0; color: var(--color-text-main); font-size: 0.88rem;">${summaryText}</p>
      </div>

      <div style="background: rgba(16, 185, 129, 0.06); padding: 1rem; border-radius: var(--radius-sm); border-right: 4px solid #10b981;">
        <h4 style="margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 800; color: #10b981; display: flex; align-items: center; gap: 6px;">
          ☀️ ملاءمة الموسم وحجم الطلب إعلانياً
        </h4>
        <p style="margin: 0; color: var(--color-text-main); font-size: 0.88rem;">${marketFitText}</p>
      </div>

      <div style="background: rgba(245, 158, 11, 0.06); padding: 1rem; border-radius: var(--radius-sm); border-right: 4px solid #f59e0b;">
        <h4 style="margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 800; color: #f59e0b; display: flex; align-items: center; gap: 6px;">
          🚚 نصائح اللوجستيك والتوصيل في المغرب (COD)
        </h4>
        <p style="margin: 0; color: var(--color-text-main); font-size: 0.88rem;">${logisticsAdviceText}</p>
      </div>

      <div style="background: rgba(139, 92, 246, 0.08); padding: 1rem; border-radius: var(--radius-sm); border-right: 4px solid #8b5cf6;">
        <h4 style="margin: 0 0 6px 0; font-size: 0.95rem; font-weight: 800; color: #8b5cf6; display: flex; align-items: center; gap: 6px;">
          🚀 خطة الإطلاق والتسويق المقترحة
        </h4>
        <p style="margin: 0; color: var(--color-text-main); font-size: 0.88rem; font-weight: 600;">${launchStrategyText}</p>
      </div>
    </div>

    <!-- Financial Breakdown Table -->
    <h4 style="font-size: 1rem; font-weight: 800; margin: 1.25rem 0 0.5rem 0; color: var(--color-text-main);">
      💰 جدول دراسة الجدوى المالية والحسابات التقديرية (DH)
    </h4>
    <table class="ai-matrix-table" style="margin-bottom: 1.5rem;">
      <thead>
        <tr>
          <th>عنصر الحساب المالي</th>
          <th>التكلفة التقديرية (DH)</th>
          <th>ملاحظات الذكاء الاصطناعي</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>سعر شراء الجملة التقديري</td>
          <td style="font-weight: 700;">${fin.c_wholesale || 0} DH</td>
          <td>تكلفة توريد القطعة واحدة</td>
        </tr>
        <tr>
          <td>تكلفة الشحن والتوصيل الأساسية</td>
          <td style="font-weight: 700;">${fin.c_shipping || 0} DH</td>
          <td>تعريفة التوصيل العادية</td>
        </tr>
        <tr>
          <td>التكلفة الحقيقية للتوصيل (مع 20% مرجوعات)</td>
          <td style="font-weight: 700; color: #f59e0b;">${fin.real_shipping_with_returns || 0} DH</td>
          <td>شاملة تعويض الطلبات الملغاة والمرجعة</td>
        </tr>
        <tr>
          <td>تكلفة الاستحواذ الإعلاني التقديرية (CPA)</td>
          <td style="font-weight: 700; color: var(--color-primary);">${fin.estimated_cpa || 0} DH</td>
          <td>تكلفة الحصول على طلب واحد عبر الإعلانات</td>
        </tr>
        <tr>
          <td>السعر المستهدف لبيع المنتج للزبون</td>
          <td style="font-weight: 700; color: var(--color-primary); font-size: 1.05rem;">${fin.target_price || 0} DH</td>
          <td>سعر البيع المناسب في السوق المغربي</td>
        </tr>
        <tr style="background: rgba(16, 185, 129, 0.08);">
          <td style="font-weight: 800; color: #10b981;">صافي الربح التقديري للقطعة الواحدة</td>
          <td style="font-weight: 800; color: #10b981; font-size: 1.1rem;">+${fin.net_profit || 0} DH</td>
          <td style="font-weight: 800; color: #10b981;">هامش صافي (${fin.net_margin_pct || 0}%)</td>
        </tr>
      </tbody>
    </table>

    <!-- Scoring Points Matrix Table -->
    <h4 style="font-size: 1rem; font-weight: 800; margin: 1.25rem 0 0.5rem 0; color: var(--color-text-main);">
      📊 جدول تفكيك النقاط حسب معايير التقييم (نظام 100 نقطة)
    </h4>
    <table class="ai-matrix-table">
      <thead>
        <tr>
          <th>المعيار</th>
          <th>الرصيد الأقصى</th>
          <th>النقاط الممنوحة</th>
          <th>مستوى الأداء</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>حجم الطلب والحركية الإعلانية</td>
          <td>40 نقطة</td>
          <td style="font-weight: 700;">${brk.demand_score || 0} / 40</td>
          <td>${brk.demand_score >= 30 ? "🟢 مرتفع جداً" : "🟡 متوسط"}</td>
        </tr>
        <tr>
          <td>ملاءمة الموسم والسوق المغربي</td>
          <td>30 نقطة</td>
          <td style="font-weight: 700;">${brk.season_score || 0} / 30</td>
          <td>${brk.season_score >= 20 ? "🟢 ممتاز" : "🟡 عادي"}</td>
        </tr>
        <tr>
          <td>سهولة اللوجستيك وانخفاض المخاطر</td>
          <td>20 نقطة</td>
          <td style="font-weight: 700;">${brk.logistics_score || 0} / 20</td>
          <td>${brk.logistics_score >= 15 ? "🟢 سهل ومريح" : "🔴 يتطلب حذر"}</td>
        </tr>
        <tr>
          <td>الميزانية وهامش العائد المالي</td>
          <td>10 نقاط</td>
          <td style="font-weight: 700;">${brk.budget_score || 0} / 10</td>
          <td>${brk.budget_score >= 7 ? "🟢 ممتاز" : "🟡 مقبول"}</td>
        </tr>
        <tr style="background: var(--bg-input); font-weight: 800;">
          <td>المجموع الكلي النهائي</td>
          <td>100 نقطة</td>
          <td style="color: var(--color-primary); font-size: 1.1rem;">${item.score} / 100</td>
          <td style="color: ${badgeColor};">${item.verdict_label}</td>
        </tr>
      </tbody>
    </table>
  `;
}

async function handleRunAiAnalysis(event) {
  if (event) event.preventDefault();

  const submitBtn = document.getElementById("ai-submit-btn");
  const origBtnText = submitBtn ? submitBtn.innerHTML : "";
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = "⏳ جاري التقييم بالذكاء الاصطناعي...";
  }

  try {
    const provider =
      document.getElementById("ai-provider-select")?.value || "auto";
    const model = document.getElementById("ai-model-select")?.value || "";
    const mode =
      document.getElementById("ai-mode-select")?.value || "comprehensive";
    const budget = parseFloat(
      document.getElementById("ai-budget-input")?.value || 5000,
    );
    const season = document.getElementById("ai-season-select")?.value || "auto";
    const cShipping = parseFloat(
      document.getElementById("ai-shipping-input")?.value || 35,
    );

    let productsSource = [];
    if (
      typeof filteredProducts !== "undefined" &&
      Array.isArray(filteredProducts) &&
      filteredProducts.length > 0
    ) {
      productsSource = filteredProducts;
    } else if (
      typeof allProducts !== "undefined" &&
      Array.isArray(allProducts) &&
      allProducts.length > 0
    ) {
      productsSource = allProducts;
    } else if (
      window.adaptedResult &&
      Array.isArray(window.adaptedResult.productsEntries) &&
      window.adaptedResult.productsEntries.length > 0
    ) {
      productsSource = window.adaptedResult.productsEntries;
    } else if (window.INITIAL_PRODUCTS_FROM_DB) {
      const dbData = window.INITIAL_PRODUCTS_FROM_DB;
      const target =
        dbData.result?.data?.json ?? dbData.data?.json ?? dbData.json ?? dbData;
      productsSource =
        target?.productsEntries ||
        target?.results ||
        (Array.isArray(target) ? target : []);
    }

    const products = productsSource.map((p, idx) => {
      return {
        title: p.title || p.product_title || p.name || `منتج #${idx + 1}`,
        price: Number(p.price || p.actualPrice || p.price_1 || 250),
        selling_price: Number(p.price || p.actualPrice || p.price_1 || 250),
        ads_count: Number(p.ads_count || p.active_ads || 1),
        active_ads: Number(p.ads_count || p.active_ads || 1),
        ad_video_urls: p.ad_video_urls || p.video_url || "",
        video_url: p.ad_video_urls || p.video_url || "",
        ad_body: p.ad_body || p.description || "",
        ad_title: p.ad_title || "",
        country: p.country || "MA",
        product_url: p.productUrl || p.product_url || "",
      };
    });

    const payload = {
      provider: provider,
      model: model,
      analysis_mode: mode,
      ad_budget_total: budget,
      season: season,
      c_shipping_default: cShipping,
      products: products,
      snapshot_date: document.getElementById("filter-date")?.value?.trim() || localStorage.getItem("api_filter_date") || "",
    };

    const res = await fetch("/api/ai/analyze", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error(
        `استجابة الخادم غير صالحة (${res.status} ${res.statusText})`,
      );
    }

    if (!res.ok || !data || data.success === false) {
      const errMsg =
        data?.messages?.error ||
        data?.message ||
        data?.error ||
        `خطأ في الخادم (${res.status})`;
      alert("⚠️ فشل إجراء التقييم: " + errMsg);
      return;
    }

    window.currentAiAnalysis = data;
    window.currentAiEvaluations = data.evaluations || [];
    activeAiFilterEvaluations = data.evaluations || [];
    currentAiFilterVerdict = "all";

    renderAiAnalysisDashboard(data);
    closeAiAnalysisModal();
    filterProducts();
    fetchAiAnalysisHistory();

    if (typeof showNotification === "function") {
      const msg = data.is_cached
        ? `💾 تم جلب التحليل المحفوظ مسبقاً (${data.ai_powered_by || ""})`
        : "✨ تم تحليل المنتجات بنجاح وحفظ النتائج في الأرشيف!";
      showNotification(msg);
    }
  } catch (err) {
    console.error("AI Analysis error:", err);
    alert("⚠️ تعذر الاتصال بخادم التحليل: " + (err.message || err));
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origBtnText;
    }
  }
}

function renderAiAnalysisDashboard(data) {
  const dashCard = document.getElementById("ai-stats-dashboard");
  if (!dashCard) return;

  dashCard.style.display = "block";

  const summary = data.summary || {};
  const evaluations = data.evaluations || [];
  document.getElementById("ai-cnt-all").textContent =
    summary.total_analyzed || 0;
  document.getElementById("ai-cnt-winning").textContent =
    summary.winners_count || 0;
  document.getElementById("ai-cnt-promising").textContent =
    summary.promising_count || 0;
  document.getElementById("ai-cnt-risk").textContent = summary.risk_count || 0;

  const budgetFitCount =
    summary.budget_recommended_count ||
    evaluations.filter((e) => e.is_budget_fit).length ||
    0;
  const budgetCntEl = document.getElementById("ai-cnt-budget");
  if (budgetCntEl) budgetCntEl.textContent = budgetFitCount;

  const adviceBox = document.getElementById("ai-budget-advice-box");
  const adviceText = document.getElementById("ai-budget-advice-text");
  if (summary.budget_allocation_summary && adviceBox && adviceText) {
    adviceBox.style.display = "block";
    adviceText.textContent = summary.budget_allocation_summary;
  } else if (adviceBox) {
    adviceBox.style.display = "none";
  }

  document.getElementById("ai-dash-title").textContent =
    `لوحة تحليلات الذكاء الاصطناعي - ${data.title || ""}`;
  document.getElementById("ai-dash-subtitle").textContent =
    `الموسم المعايَن: ${summary.detected_season || "عام"} | متوسط النقاط: ${summary.avg_score || 0}/100`;

  const spotlightContainer = document.getElementById("ai-winner-spotlight");
  const topWinner = summary.top_winner;

  if (topWinner && spotlightContainer) {
    spotlightContainer.innerHTML = `
      <div class="ai-winner-spotlight-box">
        <img src="${topWinner.image_url || "/placeholder.webp"}" class="ai-spotlight-img" alt="Top Winner" onerror="this.src='https://via.placeholder.com/100?text=Winner'" />
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 800;">🏆 المنتج الأول الرابح (النقاط: ${topWinner.score}/100)</span>
            <button class="btn btn-primary" style="padding: 4px 12px; font-size: 0.78rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); border: none;" onclick="openAiProductTextModal('${topWinner.title.replace(/'/g, "\\'")}')">
              📖 قراءة التحليل المفصل والنص التشخيصي
            </button>
          </div>
          <h4 style="font-size: 1rem; font-weight: 800; margin: 0 0 6px 0; color: var(--color-text-main);">${topWinner.title}</h4>
          <div style="display: flex; gap: 15px; font-size: 0.82rem; color: var(--color-text-muted);">
            <span>🏷️ السعر المستهدف المقترح: <strong style="color: var(--color-primary); font-weight: 700;">${topWinner.target_price} DH</strong></span>
            <span>💰 هامش الربح الصافي: <strong style="color: #10b981; font-weight: 700;">+${topWinner.net_margin_pct}%</strong></span>
          </div>
        </div>
      </div>
    `;
  }
}

function applyAiBadgesToProductCards(evaluations) {
  if (!Array.isArray(evaluations)) return;

  const evalMap = {};
  evaluations.forEach((ev) => {
    if (ev.url) evalMap[ev.url] = ev;
    if (ev.id) evalMap[ev.id] = ev;
    if (ev.title) evalMap[ev.title.trim()] = ev;
  });

  const cards = document.querySelectorAll(".product-card, .index-product-card");
  cards.forEach((card) => {
    const existingBadge = card.querySelector(".ai-product-badge");
    if (existingBadge) existingBadge.remove();

    const existingBtn = card.querySelector(".ai-card-read-btn");
    if (existingBtn) existingBtn.remove();

    const titleEl = card.querySelector(".product-title, h3, h4");
    const linkEl = card.querySelector("a[href*='http'], [data-product-url]");

    const cardTitle = titleEl ? titleEl.textContent.trim() : "";
    const cardUrl = linkEl
      ? linkEl.href || linkEl.getAttribute("data-product-url")
      : "";
    const cardId = card.id ? card.id.replace("product-", "") : "";

    const ev = evalMap[cardUrl] || evalMap[cardId] || evalMap[cardTitle];

    if (ev) {
      card.setAttribute("data-ai-verdict", ev.verdict);
      card.setAttribute("data-ai-score", ev.score);

      const badge = document.createElement("div");
      badge.className = `ai-product-badge badge-${ev.verdict}`;

      let icon = "🟢";
      if (ev.verdict === "promising") icon = "🟡";
      if (ev.verdict === "risk") icon = "🔴";

      const budgetTag = ev.is_budget_fit
        ? `<span style="background: #6366f1; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; margin-right: 4px;" title="${ev.budget_allocation_note || "موصى به للميزانية"}">💰 خيار الميزانية</span>`
        : "";

      badge.innerHTML = `<span>${icon}</span> <span>${ev.score}/100</span> ${budgetTag}`;
      badge.title = `${ev.verdict_label} - نقاط الملاءمة ${ev.score}/100`;

      const mediaContainer = card.querySelector(".product-media") || card;
      mediaContainer.style.position = "relative";
      mediaContainer.appendChild(badge);

      // Append "📖 قراءة التحليل" button at bottom of card
      const readBtn = document.createElement("button");
      readBtn.className = "ai-card-read-btn btn btn-secondary";
      readBtn.style.cssText =
        "width: 100%; margin-top: 8px; font-size: 0.8rem; font-weight: 700; border-color: var(--color-primary); color: var(--color-primary); display: flex; align-items: center; justify-content: center; gap: 6px;";
      readBtn.innerHTML = `<span>📖</span> <span>قراءة التحليل بالنص والجداول</span>`;
      readBtn.onclick = (e) => {
        e.stopPropagation();
        openAiProductTextModal(ev.url || ev.id || ev.title);
      };

      const cardBody =
        card.querySelector(".product-info, .card-content") || card;
      cardBody.appendChild(readBtn);
    }
  });
}

function filterProductsByVerdict(verdict) {
  currentAiFilterVerdict = verdict;

  const pills = document.querySelectorAll(".ai-pill");
  pills.forEach((p) => p.classList.remove("active"));

  const targetPill = document.getElementById(`pill-filter-${verdict}`);
  if (targetPill) targetPill.classList.add("active");

  filterProducts();
}

function resetAiFilter() {
  activeAiFilterEvaluations = null;
  currentAiFilterVerdict = "all";

  const pills = document.querySelectorAll(".ai-pill");
  pills.forEach((p) => p.classList.remove("active"));
  const pillAll = document.getElementById("pill-filter-all");
  if (pillAll) pillAll.classList.add("active");

  const dashCard = document.getElementById("ai-stats-dashboard");
  if (dashCard) dashCard.style.display = "none";

  filterProducts();
  if (typeof showToast === "function") {
    showToast(
      "تم إلغاء تصفية الذكاء الاصطناعي وإعادة عرض كل المنتجات 🔄",
      "info",
    );
  }
}

async function fetchAiAnalysisHistory(ignoreDateFilter = false) {
  const container = document.getElementById("ai-history-list");
  if (!container) return;

  container.innerHTML = `<div style="text-align: center; color: var(--color-text-muted); padding: 2rem 0;">⏳ جاري جلب التحليلات المحفوظة...</div>`;

  try {
    const filterDateInput = ignoreDateFilter
      ? ""
      : (document.getElementById("filter-date")?.value?.trim() || localStorage.getItem("api_filter_date") || "");
    const filterDate = (filterDateInput && /^\d{4}-\d{2}-\d{2}/.test(filterDateInput)) ? filterDateInput.substring(0, 10) : "";

    const fetchUrl = filterDate ? `/api/ai/history?date=${encodeURIComponent(filterDate)}` : "/api/ai/history";
    const res = await fetch(fetchUrl);
    const data = await res.json();

    if (
      !data.success ||
      !Array.isArray(data.history) ||
      data.history.length === 0
    ) {
      if (filterDate && !ignoreDateFilter) {
        container.innerHTML = `
          <div style="text-align: center; color: var(--color-text-muted); padding: 1.5rem 1rem; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 8px;">
            <div style="font-size: 0.95rem; font-weight: 700; margin-bottom: 8px;">📅 لا توجد تحليلات محفوظة بتاريخ <strong>${filterDate}</strong></div>
            <div style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 12px;">لم يتم إجراء أي تحليل ذكاء اصطناعي في هذا اليوم تحديداً.</div>
            <button class="btn btn-secondary" style="font-size: 0.78rem; font-weight: 700; padding: 4px 12px;" onclick="fetchAiAnalysisHistory(true)">
              🌐 عرض جميع التحليلات المحفوظة
            </button>
          </div>
        `;
      } else {
        container.innerHTML = `<div style="text-align: center; color: var(--color-text-muted); padding: 2rem 0;">لا توجد تحليلات محفوظة بعد. قم بإجراء أول تحليل الآن!</div>`;
      }
      return;
    }

    container.innerHTML = "";

    if (filterDate && !ignoreDateFilter) {
      const headerNote = document.createElement("div");
      headerNote.style.cssText = "font-size: 0.8rem; color: #10b981; background: rgba(16,185,129,0.1); padding: 8px 12px; border-radius: 6px; font-weight: 700; margin-bottom: 12px; border: 1px solid rgba(16,185,129,0.3); display: flex; justify-content: space-between; align-items: center;";
      headerNote.innerHTML = `
        <span>📅 تصفية حسب التاريخ المختار: <strong>${filterDate}</strong> (${data.history.length})</span>
        <button onclick="fetchAiAnalysisHistory(true)" style="background: none; border: none; color: #10b981; cursor: pointer; text-decoration: underline; font-size: 0.75rem; font-weight: 700;">عرض الكل</button>
      `;
      container.appendChild(headerNote);
    }

    data.history.forEach((item) => {
      const summary = item.summary || {};
      const aiPoweredBy = item.ai_powered_by || summary.ai_powered_by || "Internal Engine";
      const snapshotDate = item.snapshot_date || summary.snapshot_date || "";
      const el = document.createElement("div");
      el.className = "ai-history-item";
      el.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
          <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--color-text-main);">${item.title}</h4>
          <button style="background: none; border: none; font-size: 0.85rem; cursor: pointer; color: #ef4444;" onclick="deleteAiAnalysisHistoryItem(${item.id}, event)" title="حذف التحليل">&times;</button>
        </div>
        <div style="display: flex; gap: 10px; font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 8px; flex-wrap: wrap; align-items: center;">
          <span>🕐 ${item.created_at || ""}</span>
          ${snapshotDate ? `<span style="background: rgba(16,185,129,0.12); color: #10b981; padding: 2px 6px; border-radius: 4px; font-size: 0.73rem; font-weight: 700; border: 1px solid rgba(16,185,129,0.3);">📅 بيانات: ${snapshotDate}</span>` : ""}
          <span style="background: rgba(99,102,241,0.15); color: #6366f1; padding: 2px 6px; border-radius: 4px; font-size: 0.73rem; font-weight: 700; border: 1px solid rgba(99,102,241,0.3);">🤖 ${aiPoweredBy}</span>
          <span>🟢 ${summary.winners_count || 0} رابح</span>
          <span>🟡 ${summary.promising_count || 0} واعد</span>
        </div>
        <div style="display: flex; justify-content: flex-end;">
          <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 700;" onclick="loadAiAnalysisHistoryDetail(${item.id})">
            🔄 استرجاع وتطبيق النتيجة
          </button>
        </div>
      `;
      container.appendChild(el);
    });
  } catch (err) {
    console.error("Fetch AI History error:", err);
    container.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 1.5rem 0;">⚠️ حدث خطأ أثناء تحميل السجل.</div>`;
  }
}

async function loadAiAnalysisHistoryDetail(id) {
  try {
    const res = await fetch(`/api/ai/history/${id}`);
    const data = await res.json();

    if (!data.success || !data.analysis) {
      alert("⚠️ تعذر استرجاع تفاصيل هذا التحليل.");
      return;
    }

    const analysisData = {
      success: true,
      title: data.analysis.title,
      summary: data.analysis.summary,
      evaluations: data.analysis.evaluations,
    };

    window.currentAiAnalysis = analysisData;
    window.currentAiEvaluations = data.analysis.evaluations || [];
    activeAiFilterEvaluations = data.analysis.evaluations || [];
    currentAiFilterVerdict = "all";

    renderAiAnalysisDashboard(analysisData);
    closeAiHistoryDrawer();
    filterProducts();

    if (typeof showNotification === "function") {
      showNotification(`🔄 تم استرجاع التحليل: ${data.analysis.title}`);
    }
  } catch (err) {
    console.error("Load AI History Detail error:", err);
    alert("⚠️ خطأ في الاسترجاع.");
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
      alert("فشل الحذف.");
    }
  } catch (err) {
    console.error("Delete history item error:", err);
  }
}

function renderAiFullReportModalContent() {
  const container = document.getElementById("ai-report-modal-body");
  if (!container) return;

  if (
    !window.currentAiAnalysis ||
    !Array.isArray(window.currentAiEvaluations) ||
    window.currentAiEvaluations.length === 0
  ) {
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

      const escapedTitle = item.title.replace(/'/g, "\\'");

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
          <button class="btn btn-secondary" style="padding: 3px 8px; font-size: 0.75rem; font-weight: 700; background: #6366f1; border: none; color: white;" onclick="openSavedPhase2Modal('${escapedTitle}')">
            📂 المحفوظ
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
          <th>قراءة التحليل المفصل</th>
        </tr>
      </thead>
      <tbody>
        ${tableRows}
      </tbody>
    </table>
  `;
}

// =========================================
// 12. Phase 2: Single Product Deep-Dive Engine & UI Handlers
// =========================================
// Dynamic loading of AI models into Phase 2 selection dropdown based on system settings
async function populatePhase2AiModels() {
  const select = document.getElementById("p2-provider-select");
  if (!select) return;

  const currentVal = select.value || "auto";

  try {
    const res = await fetch("/api/settings/ai_providers_config");
    let config = null;
    if (res.ok) {
      const data = await res.json();
      if (data && data.value) {
        config =
          typeof data.value === "string" ? JSON.parse(data.value) : data.value;
      }
    }

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
      openrouter: {
        name: "🌐 OpenRouter",
        models: ["openai/gpt-4o-mini", "anthropic/claude-3.5-sonnet"],
        active_model: "openai/gpt-4o-mini",
      },
      apiyi: {
        name: "🚀 APIyi",
        models: ["gpt-4o-mini", "gpt-4o", "claude-3-5-sonnet-20241022", "deepseek-chat"],
        active_model: "gpt-4o-mini",
      },
      openai: {
        name: "🤖 OpenAI",
        models: ["gpt-4o-mini", "gpt-4o"],
        active_model: "gpt-4o-mini",
      },
      gemini: {
        name: "💎 Google Gemini",
        models: ["gemini-1.5-flash", "gemini-1.5-pro"],
        active_model: "gemini-1.5-flash",
      },
      deepseek: {
        name: "🐋 DeepSeek",
        models: ["deepseek-chat"],
        active_model: "deepseek-chat",
      },
    };

    const providersData =
      config && config.providers ? config.providers : defaultProviders;
    const globalActiveProvider =
      config && config.active_provider ? config.active_provider : "openrouter";

    for (const [pKey, pData] of Object.entries(providersData)) {
      const models = pData && Array.isArray(pData.models) ? pData.models : [];
      if (models.length > 0) {
        const pName =
          (pData && pData.name) || providerDisplayNames[pKey] || pKey;
        const activeModel = (pData && pData.active_model) || models[0] || "";

        html += `<optgroup label="${pName}">`;
        models.forEach((m) => {
          const isGlobalDefault =
            pKey === globalActiveProvider && m === activeModel;
          const badge = isGlobalDefault
            ? " 🌟 (افتراضي النظام)"
            : m === activeModel
              ? " ⭐"
              : "";
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

  // Populate AI Models from System Settings dynamically
  populatePhase2AiModels();

  document.getElementById("p2-product-id").value =
    product.id || product.product_id || "";
  document.getElementById("p2-product-title-input").value =
    product.title || product.name || "منتج بدون عنوان";
  document.getElementById("p2-product-raw-json").value =
    JSON.stringify(product);

  const defaultPrice = parseFloat(
    product.price || product.actualPrice || product.price_1 || 250,
  );
  document.getElementById("p2-price-selling").value =
    isNaN(defaultPrice) || defaultPrice <= 0 ? 250 : defaultPrice;

  const defaultWholesale = parseFloat(
    product.c_wholesale || product.wholesale_price || 70,
  );
  document.getElementById("p2-c-wholesale").value =
    isNaN(defaultWholesale) || defaultWholesale <= 0 ? 70 : defaultWholesale;

  document.getElementById("p2-c-shipping").value = product.c_shipping || 35;
  document.getElementById("p2-c-packaging").value = product.c_packaging || 10;
  document.getElementById("p2-stock-quantity").value =
    product.stock_quantity || product.stock_qty || product.quantity || 100;
  document.getElementById("p2-total-ad-budget").value =
    product.total_ad_budget || product.ad_budget || 1000;
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

function closePhase2ResultsModal() {
  const modal = document.getElementById("phase2-results-modal");
  if (modal) modal.style.display = "none";
}

async function handleRunPhase2DeepAnalysis(e) {
  if (e) e.preventDefault();

  const rawJsonStr = document.getElementById("p2-product-raw-json").value;
  let product = {};
  try {
    product = JSON.parse(rawJsonStr);
  } catch (err) {
    product = {
      title: document.getElementById("p2-product-title-input").value,
    };
  }

  const providerSelect = document.getElementById("p2-provider-select");
  const selectedOpt =
    providerSelect && providerSelect.selectedIndex >= 0
      ? providerSelect.options[providerSelect.selectedIndex]
      : null;

  const provider = selectedOpt
    ? selectedOpt.getAttribute("data-provider") || selectedOpt.value
    : "auto";
  const model = selectedOpt ? selectedOpt.getAttribute("data-model") || "" : "";

  const payload = {
    product: product,
    product_id: document.getElementById("p2-product-id").value,
    provider: provider,
    model: model,
    price_selling: parseFloat(
      document.getElementById("p2-price-selling").value,
    ),
    c_wholesale: parseFloat(document.getElementById("p2-c-wholesale").value),
    c_shipping: parseFloat(document.getElementById("p2-c-shipping").value),
    c_packaging: parseFloat(document.getElementById("p2-c-packaging").value),
    stock_quantity: parseInt(
      document.getElementById("p2-stock-quantity").value || 100,
    ),
    total_ad_budget: parseFloat(
      document.getElementById("p2-total-ad-budget").value || 1000,
    ),
    extra_notes: document.getElementById("p2-extra-notes").value,
  };

  const submitBtn = document.getElementById("p2-submit-btn");
  const originalText = submitBtn ? submitBtn.innerHTML : "";
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      "⏳ جاري إعداد دراسة الجدوى وتوليد النصوص الإعلانية...";
  }

  try {
    const res = await fetch("/api/ai/analyze-deep", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (res.ok && data.success && data.result) {
      closePhase2InputModal();
      renderPhase2Results(data.result, product.title);
      showToast("تم توليد دراسة الجدوى وتكتيكات الإطلاق بنجاح! 🚀", "success");
    } else {
      showToast(data.error || "فشل إجراء التحليل التفصيلي للمنتج.", "error");
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

// =========================================
// Phase 2 Saved Analyses History Logic
// =========================================
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
  if (body)
    body.innerHTML =
      '<div style="text-align: center; padding: 2rem; color: var(--color-text-muted);">⏳ جاري تحميل السجل المحفوظ للمنتج...</div>';

  try {
    let url = "/api/ai/phase2-history";
    const queryParams = [];
    if (productTitle)
      queryParams.push(`title=${encodeURIComponent(productTitle)}`);
    if (productId)
      queryParams.push(`product_id=${encodeURIComponent(productId)}`);
    if (queryParams.length > 0) url += "?" + queryParams.join("&");

    const res = await fetch(url);
    const data = await res.json();

    if (res.ok && data.success && Array.isArray(data.history)) {
      currentPhase2HistoryCache = data.history;
      allPhase2HistoryCache = data.all_history || data.history;
      renderPhase2HistoryList(currentPhase2HistoryCache, productTitle);
    } else {
      if (body)
        body.innerHTML =
          '<div style="text-align: center; padding: 2rem; color: #ef4444;">❌ تعذر تحميل سجل التحليلات التفصيلية.</div>';
    }
  } catch (err) {
    console.error("Error fetching Phase 2 history:", err);
    if (body)
      body.innerHTML =
        '<div style="text-align: center; padding: 2rem; color: #ef4444;">❌ خطأ في الاتصال بالخادم.</div>';
  }
}

function openSavedPhase2ForCurrentProduct() {
  const domTitle =
    document.getElementById("details-title")?.textContent?.trim() || "";
  const title =
    currentProductForDetails?.title ||
    currentProductForDetails?.name ||
    (domTitle && domTitle !== "تفاصيل الإعلان والنشاط" ? domTitle : "");
  const productId =
    currentProductForDetails?.id ||
    currentProductForDetails?.product_id ||
    document.getElementById("p2-product-id")?.value ||
    "";
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
        <p style="font-size: 0.88rem; max-width: 500px; margin: 0 auto 1.25rem auto; line-height: 1.5; color: var(--color-text-muted);">
          ${displayTitle ? "لم يتم إجراء أي تحليل تفصيلي (Phase 2) لهذا المنتج بعد. يمكنك إدخال التكاليف وإجراء أول تحليل لتوليد دراسة الجدوى وحفظها تلقائياً!" : "لم يتم العثور على أي نتائج مطابقة."}
        </p>
        <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
          <button class="btn btn-success" onclick="closePhase2HistoryModal(); openPhase2FromDetails();" style="padding: 0.65rem 1.4rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669); border: none; font-size: 0.88rem; border-radius: var(--radius-sm); cursor: pointer;">
            🚀 إجراء أول تحليل تفصيلي (Phase 2) الآن
          </button>
          ${
            displayTitle
              ? `
          <button class="btn btn-secondary" onclick="showAllPhase2History();" style="padding: 0.65rem 1.4rem; font-weight: 700; font-size: 0.88rem; border-radius: var(--radius-sm); cursor: pointer;">
            🌐 عرض سجل جميع المنتجات
          </button>
          `
              : ""
          }
        </div>
      </div>
    `;
    return;
  }

  body.innerHTML = items
    .map((item, idx) => {
      const res = item.result || {};
      const fm = res.financial_model || {};
      const cleanTitle =
        item.product_title ||
        (item.title
          ? item.title.replace(/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/iu, "")
          : "منتج بدون عنوان");
      const createdAt = item.created_at || "";
      const verdict = res.executive_verdict || "";
      const aiPoweredBy =
        item.ai_powered_by ||
        res.ai_powered_by ||
        (item.summary && item.summary.ai_powered_by) ||
        "Internal Engine";

      return `
      <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; display: flex; flex-direction: column; gap: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
          <div>
            <h4 style="font-weight: 800; font-size: 1rem; color: var(--color-primary); margin: 0 0 4px 0;">📦 ${cleanTitle}</h4>
            <div style="display: flex; gap: 10px; align-items: center; font-size: 0.78rem; color: var(--color-text-muted); flex-wrap: wrap;">
              <span>📅 تاريخ التحليل: ${createdAt}</span>
              <span style="background: rgba(99,102,241,0.15); color: #6366f1; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(99,102,241,0.3);">🤖 المزود والموديل: ${aiPoweredBy}</span>
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
          <span>📊 الميزانية اليومية: <strong style="color: #f59e0b;">${fm.daily_ad_budget_dh || 0} DH</strong></span>
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
      const cleanTitle =
        item.product_title ||
        (item.title
          ? item.title.replace(/^تحليل\s+تفصيلي\s*\(Phase\s*2\):\s*/iu, "")
          : "منتج");
      renderPhase2Results(item.result, cleanTitle);
    }
  }
}

function filterPhase2HistoryList() {
  const query = (
    document.getElementById("p2-history-search-input")?.value || ""
  )
    .toLowerCase()
    .trim();
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
