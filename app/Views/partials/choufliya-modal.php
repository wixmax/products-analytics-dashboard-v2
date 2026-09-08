<!-- Choufliya Wholesale Sourcing Modal -->
<div class="details-modal-overlay" id="choufliya-modal" style="display: none; z-index: 10500; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px);">
  <div class="choufliya-modal-window" style="max-width: 1140px; width: 96%; max-height: 92vh; display: flex; flex-direction: column; background: var(--bg-card); border-radius: var(--radius-lg, 14px); border: 1px solid var(--border-color); box-shadow: var(--shadow-xl, 0 20px 30px rgba(0,0,0,0.4)); overflow: hidden; transition: outline 0.2s ease;">
    
    <!-- Modal Header -->
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--bg-card); display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: white; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">
          📦
        </div>
        <div>
          <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-text-main); display: flex; align-items: center; gap: 8px;">
            <span>موردي الجملة في المغرب (ChoufLens / شوف بالجملة)</span>
            <span style="font-size: 0.72rem; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 2px 8px; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700;">سوق الجملة المحلي 🇲🇦</span>
          </div>
          <div id="choufliya-modal-subtitle" style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px; max-width: 650px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            جاري استخراج الموردين وهوامش الربح...
          </div>
        </div>
      </div>

      <button onclick="closeChoufliyaModal()" style="font-size: 1.6rem; color: var(--color-text-muted); cursor: pointer; background: transparent; border: none; padding: 4px 8px; line-height: 1;" title="إغلاق">
        &times;
      </button>
    </div>

    <!-- Sourcing Summary Stats & Search Toolbar -->
    <div style="background: var(--bg-body); padding: 0.85rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
      
      <!-- Metrics Strip -->
      <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 0.7rem; color: var(--color-text-muted); font-weight: 600;">🛒 سعر البيع في الإعلان</span>
          <span id="choufliya-stat-retail-price" style="font-size: 1rem; font-weight: 800; color: var(--color-primary);">-- MAD</span>
        </div>

        <div style="width: 1px; height: 28px; background: var(--border-color);"></div>

        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 0.7rem; color: var(--color-text-muted); font-weight: 600;">🏷️ أقل سعر جملة متاح</span>
          <span id="choufliya-stat-min-wholesale" style="font-size: 1rem; font-weight: 800; color: #10b981;">-- MAD</span>
        </div>

        <div style="width: 1px; height: 28px; background: var(--border-color);"></div>

        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 0.7rem; color: var(--color-text-muted); font-weight: 600;">🎯 هامش الربح المتوقع</span>
          <span id="choufliya-stat-margin" style="font-size: 1rem; font-weight: 800; color: #059669;">--</span>
        </div>

        <div style="width: 1px; height: 28px; background: var(--border-color);"></div>

        <div style="display: flex; flex-direction: column;">
          <span style="font-size: 0.7rem; color: var(--color-text-muted); font-weight: 600;">👥 عدد الموردين المطابقين</span>
          <span id="choufliya-stat-suppliers-count" style="font-size: 1rem; font-weight: 800; color: var(--color-text-main);">0</span>
        </div>
      </div>

      <!-- Search Input & Quick Actions (Text, Ad Image, File Upload) -->
      <div style="display: flex; align-items: center; gap: 6px; flex: 1; min-width: 320px; max-width: 530px;">
        <div style="position: relative; width: 100%;">
          <input type="text" id="choufliya-custom-query" placeholder="اكتب اسم السلعة، أو الصق صورة (Ctrl+V)..." style="width: 100%; font-size: 0.8rem; padding: 0.45rem 0.8rem; border-radius: var(--radius-sm, 6px); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--color-text-main);" onkeydown="if(event.key==='Enter') reSearchChoufliya(false);" />
        </div>
        
        <button onclick="reSearchChoufliya(false)" class="btn btn-primary" style="padding: 0.45rem 0.8rem; font-size: 0.8rem; white-space: nowrap; font-weight: 700;" title="بحث سريع بالاسم والكلمات الدلالية">
          🔍 بحث
        </button>

        <button onclick="reSearchChoufliya(true)" class="btn btn-secondary" style="padding: 0.45rem 0.7rem; font-size: 0.8rem; white-space: nowrap; font-weight: 700; color: #a855f7; border-color: rgba(168, 85, 247, 0.4);" title="البحث العكسي بصورة الإعلان الأصلية">
          🖼️ صورة الإعلان
        </button>

        <!-- Local File Upload Button & Hidden Input -->
        <input type="file" id="choufliya-file-upload-input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleChoufliyaFileSelected(this.files)" />
        <button onclick="document.getElementById('choufliya-file-upload-input').click()" class="btn btn-secondary" style="padding: 0.45rem 0.75rem; font-size: 0.8rem; white-space: nowrap; font-weight: 700; color: #10b981; border-color: rgba(16, 185, 129, 0.4); display: inline-flex; align-items: center; gap: 4px;" title="رفع صورة من جهازك للبحث العكسي عنها في سوق الجملة">
          <span>📁 رفع صورة</span>
        </button>
      </div>

    </div>

    <!-- Uploaded Image Active Preview Bar -->
    <div id="choufliya-uploaded-preview-bar" style="display: none; background: rgba(16, 185, 129, 0.1); border-bottom: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 1.5rem; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <img id="choufliya-uploaded-preview-thumb" src="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px; border: 1px solid #10b981;" />
        <span style="font-size: 0.8rem; font-weight: 700; color: #059669;">
          📷 جاري عرض نتائج الصورة المرفوعة: <strong id="choufliya-uploaded-filename" style="color: var(--color-text-main);"></strong>
        </span>
      </div>
      <button onclick="clearUploadedImage()" class="btn btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; color: var(--color-error); border-color: var(--border-color); font-weight: 700;">
        ✕ إلغاء الصورة والعودة للنص
      </button>
    </div>

    <!-- Filter & Sort Tabs Bar -->
    <div style="padding: 0.6rem 1.5rem; background: var(--bg-card); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
      
      <div style="display: flex; align-items: center; gap: 6px;">
        <button id="chouf-tab-all" onclick="filterChoufliyaTab('all')" class="btn btn-secondary" style="padding: 0.35rem 0.8rem; font-size: 0.75rem; font-weight: 700; background: var(--color-primary); color: white; border: none; border-radius: 9999px;">
          الجميع (<span id="chouf-count-all">0</span>)
        </button>
        <button id="chouf-tab-whatsapp" onclick="filterChoufliyaTab('whatsapp')" class="btn btn-secondary" style="padding: 0.35rem 0.8rem; font-size: 0.75rem; font-weight: 700; border-radius: 9999px;">
          🟢 واتساب متاح (<span id="chouf-count-whatsapp">0</span>)
        </button>
        <button id="chouf-tab-priced" onclick="filterChoufliyaTab('priced')" class="btn btn-secondary" style="padding: 0.35rem 0.8rem; font-size: 0.75rem; font-weight: 700; border-radius: 9999px;">
          💰 بسعر محدد (<span id="chouf-count-priced">0</span>)
        </button>
      </div>

      <div style="display: flex; align-items: center; gap: 8px;">
        <label for="chouf-sort-select" style="font-size: 0.75rem; color: var(--color-text-muted); margin: 0;">ترتيب:</label>
        <select id="chouf-sort-select" onchange="sortChoufliyaResults(this.value)" style="font-size: 0.75rem; padding: 0.35rem 0.7rem; border-radius: var(--radius-sm, 6px); border: 1px solid var(--border-color); background: var(--bg-body); color: var(--color-text-main); cursor: pointer;">
          <option value="match" selected>🎯 الأكثر مطابقة</option>
          <option value="price_asc">💵 الأقل سعراً</option>
          <option value="price_desc">💎 الأعلى سعراً</option>
          <option value="date">⏱️ الأحدث نشراً</option>
        </select>
        
        <button onclick="exportChoufliyaSuppliersCsv()" class="btn btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;" title="تصدير الموردين إلى ملف CSV">
          📥 تصدير
        </button>
      </div>

    </div>

    <!-- Modal Content Grid (Custom Class without conflicting .details-modal-body) -->
    <div style="padding: 1.2rem 1.5rem; overflow-y: auto; flex: 1; background: var(--bg-body); width: 100%;">
      <div id="choufliya-suppliers-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; width: 100%;">
        <!-- Dynamic Cards Injected via JS -->
      </div>
    </div>

  </div>
</div>

<script>
  let activeChoufliyaProduct = null;
  let rawChoufliyaItems = [];
  let filteredChoufliyaItems = [];
  let currentChoufTab = 'all';
  let activeUploadedFile = null;

  async function openChoufliyaModal(idx) {
    const product = (typeof idx === 'number' && typeof catalogProducts !== 'undefined') ? catalogProducts[idx] : null;
    if (!product) return;

    activeChoufliyaProduct = product;
    activeUploadedFile = null;

    const modal = document.getElementById('choufliya-modal');
    if (!modal) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Hide uploaded preview bar initially
    const previewBar = document.getElementById('choufliya-uploaded-preview-bar');
    if (previewBar) previewBar.style.display = 'none';

    // Set subtitle & input
    const title = product.title || product.product_title || 'منتج';
    document.getElementById('choufliya-modal-subtitle').textContent = `البحث عن موردين لـ: ${title}`;
    document.getElementById('choufliya-custom-query').value = title;

    // Estimate retail price
    let retailPrice = null;
    if (product.price && !isNaN(parseFloat(product.price))) {
      retailPrice = parseFloat(product.price);
    } else if (product.ad_body || product.ad_title) {
      const match = (product.ad_body + ' ' + (product.ad_title || '')).match(/(\d+(?:[.,]\d+)?)\s*(?:dh|mad|درهم)/i);
      if (match && match[1]) {
        retailPrice = parseFloat(match[1].replace(',', '.'));
      }
    }

    document.getElementById('choufliya-stat-retail-price').textContent = retailPrice ? `${retailPrice} MAD` : 'غير محدد';
    document.getElementById('choufliya-stat-min-wholesale').textContent = '...';
    document.getElementById('choufliya-stat-margin').textContent = '...';
    document.getElementById('choufliya-stat-suppliers-count').textContent = '...';

    // Show loading state
    const container = document.getElementById('choufliya-suppliers-container');
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0;">
        <div style="font-size: 2.5rem; margin-bottom: 12px; animation: spin 1s linear infinite;">⏳</div>
        <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-text-main);">جاري جلب الموردين من سوق الجملة (Choufliya)...</div>
        <div style="font-size: 0.85rem; color: var(--color-text-muted); margin-top: 6px;">مطابقة بالاسم والكلمات الدلالية واستخراج أرقام الواتساب وهوامش الربح</div>
      </div>
    `;

    // Fast text search by default
    await fetchChoufliyaSourcing(title, retailPrice, product.image_url || product.thumbnail_url || product.ad_image_urls, false);
  }

  async function fetchChoufliyaSourcing(title, retailPrice, imageUrl, preferImage = false) {
    try {
      const params = new URLSearchParams({
        title: title,
        limit: '40'
      });
      if (retailPrice) params.set('price', retailPrice);
      if (imageUrl) {
        const firstImg = imageUrl.split(';')[0].trim();
        if (firstImg) params.set('image_url', firstImg);
      }
      if (preferImage) {
        params.set('prefer_image', '1');
      }

      const res = await fetch(`/api/choufliya/suppliers?${params.toString()}`);
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();

      if (data.status !== 'success' || !data.products) {
        throw new Error(data.error || 'فشل جلب بيانات الموردين');
      }

      rawChoufliyaItems = data.products || [];
      updateChoufliyaModalMetrics(data);

    } catch (err) {
      console.error("Choufliya sourcing error:", err);
      const container = document.getElementById('choufliya-suppliers-container');
      container.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; color: var(--color-error); padding: 3rem 0;">
          <div style="font-size: 2rem; margin-bottom: 8px;">❌</div>
          <div style="font-size: 1.05rem; font-weight: 700;">تعذر جلب الموردين من سوق الجملة: ${err.message}</div>
          <button onclick="reSearchChoufliya(false)" class="btn btn-secondary" style="margin-top: 12px;">🔄 إعادة المحاولة</button>
        </div>
      `;
    }
  }

  // Handle Local File Upload for Reverse Image Search
  async function handleChoufliyaFileSelected(files) {
    if (!files || files.length === 0) return;
    const file = files[0];
    if (!file.type.startsWith('image/')) {
      if (typeof showToast === 'function') showToast('يرجى اختيار ملف صورة صالح (JPG, PNG, WebP).', 'warning');
      return;
    }

    activeUploadedFile = file;

    // Show preview bar
    const previewBar = document.getElementById('choufliya-uploaded-preview-bar');
    const previewThumb = document.getElementById('choufliya-uploaded-preview-thumb');
    const filenameEl = document.getElementById('choufliya-uploaded-filename');
    if (previewBar && previewThumb && filenameEl) {
      previewThumb.src = URL.createObjectURL(file);
      filenameEl.textContent = `${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
      previewBar.style.display = 'flex';
    }

    document.getElementById('choufliya-modal-subtitle').textContent = `البحث العكسي بصورة: ${file.name}`;

    const container = document.getElementById('choufliya-suppliers-container');
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0;">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">📁</div>
        <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-text-main);">جاري رفع الصورة والبحث العكسي عنها في سوق الجملة (Choufliya)...</div>
        <div style="font-size: 0.85rem; color: #10b981; margin-top: 6px; font-weight: 600;">${file.name}</div>
      </div>
    `;

    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('limit', '40');
      formData.append('prefer_image', '1');
      if (activeChoufliyaProduct && activeChoufliyaProduct.price) {
        formData.append('price', activeChoufliyaProduct.price);
      }
      if (activeChoufliyaProduct && activeChoufliyaProduct.title) {
        formData.append('title', activeChoufliyaProduct.title);
      }

      const res = await fetch('/api/choufliya/suppliers', {
        method: 'POST',
        body: formData
      });

      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();

      if (data.status !== 'success' || !data.products) {
        throw new Error(data.error || 'فشل جلب نتائج الصورة');
      }

      rawChoufliyaItems = data.products || [];
      updateChoufliyaModalMetrics(data);

      if (typeof showToast === 'function') {
        showToast(`📷 تم العثور على ${rawChoufliyaItems.length} منتج مطابق للصورة المرفوعة!`, 'success');
      }

    } catch (err) {
      console.error("Image upload search error:", err);
      container.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; color: var(--color-error); padding: 3rem 0;">
          <div style="font-size: 2rem; margin-bottom: 8px;">❌</div>
          <div style="font-size: 1.05rem; font-weight: 700;">فشل البحث بالصورة المرفوعة: ${err.message}</div>
          <button onclick="reSearchChoufliya(false)" class="btn btn-secondary" style="margin-top: 12px;">🔙 العودة للبحث بالاسم</button>
        </div>
      `;
    }
  }

  function clearUploadedImage() {
    activeUploadedFile = null;
    const fileInput = document.getElementById('choufliya-file-upload-input');
    if (fileInput) fileInput.value = '';
    const previewBar = document.getElementById('choufliya-uploaded-preview-bar');
    if (previewBar) previewBar.style.display = 'none';
    reSearchChoufliya(false);
  }

  function updateChoufliyaModalMetrics(data) {
    const minPrice = data.min_wholesale_price;
    const retPrice = data.retail_price || (activeChoufliyaProduct ? activeChoufliyaProduct.price : null);
    
    document.getElementById('choufliya-stat-min-wholesale').textContent = minPrice ? `${minPrice} MAD` : 'حسب التواصل';
    document.getElementById('choufliya-stat-suppliers-count').textContent = data.suppliers_count || rawChoufliyaItems.length;

    if (retPrice && minPrice) {
      const profit = retPrice - minPrice;
      const pct = Math.round((profit / retPrice) * 100);
      document.getElementById('choufliya-stat-margin').innerHTML = `<span style="color: #10b981;">+${profit.toFixed(0)} MAD (${pct}%)</span>`;
    } else {
      document.getElementById('choufliya-stat-margin').textContent = '--';
    }

    // Update tab counts
    document.getElementById('chouf-count-all').textContent = rawChoufliyaItems.length;
    document.getElementById('chouf-count-whatsapp').textContent = rawChoufliyaItems.filter(it => !!it.whatsappLink).length;
    document.getElementById('chouf-count-priced').textContent = rawChoufliyaItems.filter(it => it.hasExactPrice).length;

    filterChoufliyaTab(currentChoufTab);
  }

  function reSearchChoufliya(preferImage = false) {
    const q = document.getElementById('choufliya-custom-query').value.trim();
    if (!q && !preferImage && !activeUploadedFile) return;

    if (activeUploadedFile && preferImage) {
      handleChoufliyaFileSelected([activeUploadedFile]);
      return;
    }

    const container = document.getElementById('choufliya-suppliers-container');
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0;">
        <div style="font-size: 2.2rem; margin-bottom: 10px;">${preferImage ? '🖼️' : '🔍'}</div>
        <div style="font-size: 1.05rem; font-weight: 700;">${preferImage ? 'جاري البحث العكسي بصورة المنتج في سوق الجملة...' : 'جاري البحث عن: ' + q + '...'}</div>
      </div>
    `;
    const imgUrl = activeChoufliyaProduct ? (activeChoufliyaProduct.image_url || activeChoufliyaProduct.thumbnail_url || activeChoufliyaProduct.ad_image_urls) : null;
    fetchChoufliyaSourcing(q, activeChoufliyaProduct ? activeChoufliyaProduct.price : null, imgUrl, preferImage);
  }

  // Find alternatives for a specific item inside the results
  function searchAlternativesForItem(itemIdx) {
    const item = filteredChoufliyaItems[itemIdx] || rawChoufliyaItems[itemIdx];
    if (!item) return;

    const title = item.title || 'منتج';
    document.getElementById('choufliya-custom-query').value = title;
    document.getElementById('choufliya-modal-subtitle').textContent = `البحث عن بدائل وموردين لـ: ${title}`;

    const container = document.getElementById('choufliya-suppliers-container');
    container.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0;">
        <div style="font-size: 2.4rem; margin-bottom: 10px;">🔄</div>
        <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-text-main);">جاري استكشاف البدائل وموردي الجملة لـ:</div>
        <div style="font-size: 0.95rem; color: var(--color-primary); font-weight: 700; margin-top: 6px;">${title}</div>
      </div>
    `;

    const retailPrice = activeChoufliyaProduct ? activeChoufliyaProduct.price : null;
    fetchChoufliyaSourcing(title, retailPrice, item.originalImageUrl || item.imageUrl, false);
  }

  function filterChoufliyaTab(tab) {
    currentChoufTab = tab;
    ['all', 'whatsapp', 'priced'].forEach(t => {
      const btn = document.getElementById(`chouf-tab-${t}`);
      if (btn) {
        if (t === tab) {
          btn.style.background = 'var(--color-primary)';
          btn.style.color = 'white';
          btn.style.border = 'none';
        } else {
          btn.style.background = 'transparent';
          btn.style.color = 'var(--color-text-muted)';
          btn.style.border = '1px solid var(--border-color)';
        }
      }
    });

    if (tab === 'whatsapp') {
      filteredChoufliyaItems = rawChoufliyaItems.filter(it => !!it.whatsappLink);
    } else if (tab === 'priced') {
      filteredChoufliyaItems = rawChoufliyaItems.filter(it => it.hasExactPrice);
    } else {
      filteredChoufliyaItems = [...rawChoufliyaItems];
    }

    renderChoufliyaCards(filteredChoufliyaItems);
  }

  function sortChoufliyaResults(sortBy) {
    if (sortBy === 'price_asc') {
      filteredChoufliyaItems.sort((a, b) => {
        if (!a.hasExactPrice) return 1;
        if (!b.hasExactPrice) return -1;
        return a.numericPrice - b.numericPrice;
      });
    } else if (sortBy === 'price_desc') {
      filteredChoufliyaItems.sort((a, b) => {
        if (!a.hasExactPrice) return 1;
        if (!b.hasExactPrice) return -1;
        return b.numericPrice - a.numericPrice;
      });
    } else if (sortBy === 'date') {
      filteredChoufliyaItems.sort((a, b) => new Date(b.rawDate || 0) - new Date(a.rawDate || 0));
    }
    renderChoufliyaCards(filteredChoufliyaItems);
  }

  function renderChoufliyaCards(items) {
    const container = document.getElementById('choufliya-suppliers-container');
    if (!items || items.length === 0) {
      container.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0;">
          <div style="font-size: 2.2rem; margin-bottom: 10px;">📭</div>
          <div style="font-size: 1.05rem; font-weight: 700;">لا توجد عروض جملة مطابقة لهذا التبويب.</div>
          <div style="font-size: 0.8rem; margin-top: 4px;">جرب تغيير كلمة البحث، أو رفع صورة أخرى للمنتج.</div>
        </div>
      `;
      return;
    }

    container.innerHTML = items.map((it, idx) => {
      const hasWa = !!it.whatsappLink;
      const hasTg = !!(it.telegramAppProtocol || it.telegramLink);
      const tgUrl = it.telegramAppProtocol || it.telegramLink || '#';
      const timeAgo = it.publishDateRelative ? `⏱️ ${it.publishDateRelative}` : '';
      
      const marginHtml = it.margin_mad ? `
        <div style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.25); text-align: center;">
          🎯 ربح تقديري: +${it.margin_mad} MAD (${it.margin_percentage}%)
        </div>
      ` : '';

      return `
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md, 10px); overflow: hidden; display: flex; flex-direction: column; transition: all 0.2s ease; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-sm)';">
          
          <!-- Image Box with Badges -->
          <div style="position: relative; width: 100%; height: 180px; background: #0f172a; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            <img src="${it.thumbUrl || it.imageUrl}" alt="${it.title}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='${it.originalThumbUrl || it.originalImageUrl || ''}'; if(!this.src) this.style.display='none';" />
            
            <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(0, 0, 0, 0.82); color: #10b981; font-weight: 800; font-size: 0.85rem; padding: 4px 9px; border-radius: 6px; backdrop-filter: blur(4px); border: 1px solid rgba(16, 185, 129, 0.3);">
              ${it.price}
            </div>

            ${timeAgo ? `<div style="position: absolute; top: 8px; left: 8px; background: rgba(0, 0, 0, 0.75); color: #e2e8f0; font-size: 0.68rem; padding: 3px 7px; border-radius: 4px; backdrop-filter: blur(4px); font-weight: 600;">${timeAgo}</div>` : ''}

            ${it.matchPercentage ? `<div style="position: absolute; top: 8px; right: 8px; background: rgba(168, 85, 247, 0.85); color: white; font-size: 0.68rem; padding: 3px 7px; border-radius: 4px; font-weight: 700;">${it.matchPercentage}% تطابق</div>` : ''}
          </div>

          <!-- Content Details -->
          <div style="padding: 10px 12px; display: flex; flex-direction: column; flex: 1; gap: 8px;">
            
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--color-text-main); line-height: 1.35; height: 2.7em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="${it.title}">
              ${it.title}
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.72rem; color: var(--color-text-muted);">
              <span style="display: flex; align-items: center; gap: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;" title="${it.supplier}">
                🏪 ${it.supplier}
              </span>
            </div>

            ${marginHtml}

            <!-- Card Actions Bar -->
            <div style="margin-top: auto; padding-top: 8px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 6px;">
              
              <!-- Row 1: WhatsApp Contact or Status -->
              ${hasWa ? `
                <a href="${it.whatsappLink}" target="_blank" class="btn btn-success" style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #25d366; border: none; color: white; border-radius: var(--radius-sm, 6px); text-decoration: none;">
                  <span>💬 تواصل عبر واتساب</span>
                </a>
              ` : `
                <div style="width: 100%; padding: 0.35rem 0.6rem; font-size: 0.7rem; color: var(--color-text-muted); background: var(--bg-body); border-radius: var(--radius-sm, 6px); text-align: center; font-weight: 600;">
                  بدون رقم واتساب
                </div>
              `}

              <!-- Row 2: Secondary Buttons (Alternatives, Telegram, Zoom) -->
              <div style="display: flex; align-items: center; gap: 5px;">
                
                <button onclick="searchAlternativesForItem(${idx})" class="btn btn-secondary" style="flex: 1; padding: 0.35rem 0.5rem; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 4px; color: #a855f7; border-color: rgba(168, 85, 247, 0.4);" title="البحث عن موردين وبدائل أخرى لنفس هذا المنتج">
                  <span>🔄 بدائل</span>
                </button>

                ${hasTg ? `
                  <a href="${tgUrl}" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; display: inline-flex; align-items: center; justify-content: center;" title="فتح الإعلان في قناة تليجرام المورد">
                    ✈️
                  </a>
                ` : ''}

                <a href="${it.originalImageUrl || it.imageUrl}" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; display: inline-flex; align-items: center; justify-content: center;" title="عرض الصورة الأصلية كاملة">
                  🔍
                </a>

              </div>

            </div>

          </div>

        </div>
      `;
    }).join('');
  }

  function closeChoufliyaModal() {
    const modal = document.getElementById('choufliya-modal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function exportChoufliyaSuppliersCsv() {
    if (!filteredChoufliyaItems || filteredChoufliyaItems.length === 0) {
      if (typeof showToast === 'function') showToast('لا توجد بيانات موردين لتصديرها.', 'warning');
      return;
    }

    const headers = ['Title', 'Supplier', 'Price', 'Phone', 'WhatsApp', 'Published_Date', 'Image_URL'];
    const csvRows = [headers.join(',')];

    filteredChoufliyaItems.forEach(it => {
      const row = [
        `"${(it.title || '').replace(/"/g, '""')}"`,
        `"${(it.supplier || '').replace(/"/g, '""')}"`,
        `"${(it.price || '').replace(/"/g, '""')}"`,
        `"${(it.phone || '').replace(/"/g, '""')}"`,
        `"${(it.whatsappLink || '').replace(/"/g, '""')}"`,
        `"${(it.publishDateExact || '').replace(/"/g, '""')}"`,
        `"${(it.originalImageUrl || it.imageUrl || '').replace(/"/g, '""')}"`
      ];
      csvRows.push(row.join(','));
    });

    const blob = new Blob(["\uFEFF" + csvRows.join("\n")], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `choufliya_suppliers_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);

    if (typeof showToast === 'function') showToast('📥 تم تصدير بيانات الموردين بنجاح!', 'success');
  }

  // Setup Drag and Drop on Modal Window
  document.addEventListener('DOMContentLoaded', () => {
    const win = document.querySelector('.choufliya-modal-window');
    if (win) {
      ['dragenter', 'dragover'].forEach(name => {
        win.addEventListener(name, (e) => {
          e.preventDefault();
          win.style.outline = '3px dashed #10b981';
        });
      });
      ['dragleave', 'drop'].forEach(name => {
        win.addEventListener(name, (e) => {
          e.preventDefault();
          win.style.outline = 'none';
        });
      });
      win.addEventListener('drop', (e) => {
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          handleChoufliyaFileSelected(e.dataTransfer.files);
        }
      });
    }
  });

  // Setup Clipboard Paste for Images (Ctrl + V)
  window.addEventListener('paste', (e) => {
    const modal = document.getElementById('choufliya-modal');
    if (!modal || modal.style.display === 'none') return;

    if (e.clipboardData && e.clipboardData.items) {
      for (let i = 0; i < e.clipboardData.items.length; i++) {
        const item = e.clipboardData.items[i];
        if (item.type && item.type.startsWith('image/')) {
          e.preventDefault();
          const file = item.getAsFile();
          if (file) {
            const ext = file.type.split('/')[1] || 'png';
            const pastedFile = new File([file], `clipboard_image_${Date.now()}.${ext}`, { type: file.type });
            if (typeof showToast === 'function') {
              showToast('📋 تم التقاط الصورة من الحافظة وجاري البحث العكسي...', 'info');
            }
            handleChoufliyaFileSelected([pastedFile]);
            break;
          }
        }
      }
    }
  });

  // Close on Escape or click outside
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeChoufliyaModal();
  });
  document.addEventListener('click', (e) => {
    const modal = document.getElementById('choufliya-modal');
    if (e.target === modal) closeChoufliyaModal();
  });
</script>
