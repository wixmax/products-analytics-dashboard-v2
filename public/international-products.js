// Application State
let allInternationalProducts = [];
let currentFilteredProducts = []; // Keep track of filtered list for pagination
let loadedOrigins = new Set();

// Pagination State
let currentPage = 1;
const itemsPerPage = 12;

const API_JAPAN = "https://www.overviewdata.io/api/trpc/data.japanProducts?batch=1&input=%7B%220%22%3A%7B%22json%22%3Anull%2C%22meta%22%3A%7B%22values%22%3A%5B%22undefined%22%5D%7D%7D%7D";
const API_CHINA = "https://www.overviewdata.io/api/trpc/data.chinaProducts?batch=1&input=%7B%220%22%3A%7B%22json%22%3Anull%2C%22meta%22%3A%7B%22values%22%3A%5B%22undefined%22%5D%7D%7D%7D";

window.addEventListener("DOMContentLoaded", () => {
    setupTheme();
    renderEmptyState();
});

function renderEmptyState() {
    const container = document.getElementById("products-container");
    container.innerHTML = `
        <div class="empty-state">
            <div class="empty-icon">🌍</div>
            <h3>يرجى اختيار بلد لبدء جلب المنتجات</h3>
            <p>اختر اليابان أو الصين من القائمة الجانبية لعرض أحدث المنتجات.</p>
        </div>
    `;
    document.getElementById("pagination-container").innerHTML = "";
}

async function selectCountry(origin) {
    if (loadedOrigins.has(origin)) {
        filterByOrigin(origin);
        return;
    }
    await fetchByOrigin(origin);
}

async function fetchByOrigin(origin) {
    const localUrl = `/api/products?origin=${origin}&per_page=1000`;
    showToast(`جاري جلب بيانات ${origin} من قاعدة البيانات المحلية...`, "info");
    
    try {
        const response = await fetch(localUrl);
        if (!response.ok) throw new Error("Database Error");
        const json = await response.json();
        
        const mappedList = (json.results || []).map(p => {
            return {
                product_title: p.title,
                product_url: p.product_url,
                product_image: p.ad_image_urls,
                collected_money: p.collected_money,
                collected_supporter: p.collected_supporter,
                remaining_days: p.remaining_days,
                product_price: p.price_1,
                sold: p.sold,
                moq: p.moq,
                category: p.category
            };
        });

        const wrappedData = {
            result: {
                data: {
                    json: mappedList
                }
            }
        };

        if (mappedList.length === 0) {
            showToast(`لا توجد بيانات لـ ${origin} في قاعدة البيانات. جاري المزامنة مع السيرفر...`, "info");
            await triggerSyncForOrigin(origin);
        } else {
            processAndAppendData(wrappedData, origin);
            showToast(`تم جلب بيانات ${origin} بنجاح من قاعدة البيانات 🎉`, "success");
        }
    } catch (error) {
        console.error(`Error fetching local ${origin} data:`, error);
        showToast(`فشل جلب بيانات ${origin} محلياً.`, "error");
    }
}

async function triggerSyncForOrigin(origin) {
    const apiEndpoint = origin === "Japan" ? API_JAPAN : API_CHINA;
    const syncUrl = `/api/products/sync-trpc`;
    
    try {
        const response = await fetch(syncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `url=${encodeURIComponent(apiEndpoint)}`
        });
        
        if (!response.ok) throw new Error("Sync Proxy Error");
        const data = await response.json();
        
        // Detect source & duplication from server response
        const firstItem = (Array.isArray(data) && data[0]) ? data[0] : {};
        const source = firstItem.source || 'api';
        const isDuplicate = Boolean(firstItem.is_duplicate);
        
        processAndAppendData(data, origin);
        if (source === 'database') {
            showToast(`📦 تم جلب بيانات ${origin} من قاعدة البيانات المحلية`, "info");
        } else if (isDuplicate) {
            showToast(`ℹ️ تمت المزامنة: بيانات ${origin} مطابقة لنسخة مسجلة مسبقاً، تم تجاوز التكرار بنجاح!`, "info");
        } else {
            showToast(`✨ تمت مزامنة بيانات ${origin} الجديدة من السيرفر الخارجي وحفظها بنجاح! 🎉`, "success");
        }
    } catch (err) {
        console.error("Sync failed:", err);
        showToast("فشلت عملية المزامنة التلقائية مع السيرفر الرئيسي.", "error");
    }
}

function processAndAppendData(data, origin) {
  let rawProducts = [];
  if (Array.isArray(data)) {
    if (data.length > 0) {
      const first = data[0];
      if (first && typeof first === "object" && (first.product_url !== undefined || first.productUrl !== undefined || first.product_title !== undefined || first.title !== undefined)) {
        // Direct list of products
        rawProducts = data;
      } else {
        // Wrapped array
        const base = data[0];
        const targetData = base?.result?.data?.json ?? base?.data?.json ?? base?.json ?? base ?? {};
        rawProducts = Array.isArray(targetData) ? targetData : (targetData.productsEntries || targetData.results || []);
      }
    } else {
      rawProducts = [];
    }
  } else if (data && typeof data === "object") {
    // Single object or single wrapper
    const targetData = data.result?.data?.json ?? data.data?.json ?? data.json ?? data;
    rawProducts = Array.isArray(targetData) ? targetData : (targetData.productsEntries || targetData.results || [targetData]);
  }

  if (Array.isArray(rawProducts)) {
    const mapped = rawProducts.map((p) => {
      // Common extraction based on origin specific keys
      let priceInfo = "--";
      let secondaryStat = "--";
      let secondaryLabel = "الداعمين";
      let thirdStat = "--";
      let thirdLabel = "أيام متبقية";

      if (origin === "Japan") {
          priceInfo = p.collected_money ? parseInt(p.collected_money).toLocaleString() + " ¥" : "--";
          secondaryStat = p.collected_supporter || "--";
          secondaryLabel = "الداعمين";
          thirdStat = p.remaining_days || "--";
      } else if (origin === "China") {
          priceInfo = p.product_price || "--";
          secondaryStat = p.sold || "--";
          secondaryLabel = "المبيعات";
          thirdStat = p.moq ? `MOQ: ${p.moq}` : "--";
          thirdLabel = "الحد الأدنى";
      }

      let finalUrl = p.product_url || p.projectUrl || p.productUrl || p.url || "#";
      if (finalUrl !== "#" && !finalUrl.startsWith("http")) {
          finalUrl = "https://" + finalUrl;
      }

      return {
        ...p,
        origin: origin,
        originFlag: origin === "Japan" ? "🇯🇵" : "🇨🇳",
        title: p.product_title || p.title || p.name || `منتج من ${origin}`,
        imageUrl: p.product_image || p.product_image_url || p.imageUrl || p.image || "",
        url: finalUrl,
        priceInfo,
        secondaryStat,
        secondaryLabel,
        thirdStat,
        thirdLabel
      };
    });

    allInternationalProducts = [...allInternationalProducts, ...mapped];
    loadedOrigins.add(origin);

    filterByOrigin(origin);
    updateKPIs();
  }
}

function renderProducts(products) {
    currentFilteredProducts = products;
    const container = document.getElementById("products-container");
    
    if (!products || products.length === 0) {
        renderEmptyState();
        return;
    }

    // Pagination Logic
    const totalPages = Math.ceil(products.length / itemsPerPage);
    if (currentPage > totalPages) currentPage = 1;

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedItems = products.slice(start, end);

    container.innerHTML = paginatedItems.map((p) => {
        let domain = "المتجر";
        try {
            if (p.url && p.url.startsWith("http")) {
                domain = new URL(p.url).hostname.replace("www.", "");
            }
        } catch (e) {
            domain = "رابط خارجي";
        }
        return `
            <article class="product-card">
                <div class="product-media">
                    <img src="${p.imageUrl}" alt="${p.title}" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    <div class="media-badge">${p.originFlag} ${p.origin}</div>
                </div>
                <div class="card-body">
                    <div class="p-meta-info">
                        <span>🏪 ${domain}</span>
                        <span>${p.category || "عام"}</span>
                    </div>
                    <h4 class="p-title" title="${p.title}">${p.title}</h4>
                    
                    <div class="p-stats">
                        <div class="p-stat-box">
                            <span class="p-stat-val">${p.priceInfo}</span>
                            <span class="p-stat-lbl">التمويل/السعر</span>
                        </div>
                        <div class="p-stat-box">
                            <span class="p-stat-val">${p.secondaryStat}</span>
                            <span class="p-stat-lbl">${p.secondaryLabel}</span>
                        </div>
                        <div class="p-stat-box">
                            <span class="p-stat-val">${p.thirdStat}</span>
                            <span class="p-stat-lbl">${p.thirdLabel}</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="${p.url}" target="_blank" class="btn btn-primary">🔗 معاينة المنتج</a>
                </div>
            </article>
        `;
    }).join("");

    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    const container = document.getElementById("pagination-container");
    if (totalPages <= 1) {
        container.innerHTML = "";
        return;
    }

    let html = `
        <div class="pagination">
            <button class="btn btn-secondary" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">السابق</button>
    `;

    // Show a limited number of page buttons
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <button class="btn ${i === currentPage ? 'btn-primary' : 'btn-secondary'}" onclick="changePage(${i})">${i}</button>
        `;
    }

    html += `
            <button class="btn btn-secondary" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">التالي</button>
        </div>
        <div style="text-align: center; margin-top: 10px; font-size: 0.85rem; color: var(--color-text-muted);">
            صفحة ${currentPage} من ${totalPages} (إجمالي ${currentFilteredProducts.length} منتج)
        </div>
    `;

    container.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    renderProducts(currentFilteredProducts);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateKPIs() {
    document.getElementById("kpi-total").textContent = allInternationalProducts.length;
    document.getElementById("kpi-japan").textContent = allInternationalProducts.filter((p) => p.origin === "Japan").length;
    document.getElementById("kpi-china").textContent = allInternationalProducts.filter((p) => p.origin === "China").length;
}

function filterByOrigin(origin) {
    currentPage = 1;
    if (origin === "all") {
        renderProducts(allInternationalProducts);
    } else {
        const filtered = allInternationalProducts.filter((p) => p.origin === origin);
        renderProducts(filtered);
    }
}

function searchProducts() {
    currentPage = 1;
    const query = document.getElementById("search-input").value.toLowerCase();
    const filtered = allInternationalProducts.filter((p) =>
        p.title.toLowerCase().includes(query) ||
        (p.category && p.category.toLowerCase().includes(query))
    );
    renderProducts(filtered);
}

function handleLocalFile(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function (e) {
        try {
            const data = JSON.parse(e.target.result);
            const origin = file.name.includes("اليابان") || file.name.toLowerCase().includes("japan") ? "Japan" : "China";
            processAndAppendData(data, origin);
            showToast(`تم استيراد ${file.name} بنجاح`, "success");
            await uploadImportedJson(data, origin);
        } catch (err) {
            showToast("خطأ في قراءة ملف JSON", "error");
        }
    };
    reader.readAsText(file);
}

async function uploadImportedJson(data, origin) {
    try {
        const response = await fetch(`/api/products/import?origin=${origin}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        if (!response.ok) throw new Error("Import request failed");
        const resJson = await response.json();
        showToast(`تم حفظ ${resJson.inserted} منتج جديد وتحديث ${resJson.updated} في قاعدة البيانات 💾`, "success");
    } catch (err) {
        console.warn("Failed to upload imported JSON to DB:", err);
    }
}

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

    btn.addEventListener("click", async () => {
        const isDark = document.documentElement.getAttribute("data-theme") === "dark";
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
    });
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
