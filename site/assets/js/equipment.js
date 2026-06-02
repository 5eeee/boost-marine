// equipment.js – динамическая загрузка товаров и контактов для страницы магазина
// Исправлено: гарантированный вызов updateContacts для обновления номера в футере

document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // ==================== ПЕРЕМЕННЫЕ ====================
  let products = [];                // все товары из API
  let filteredProducts = [];        // отфильтрованные
  let currentFilter = 'all';        // all / parts / equipment
  let currentSearchTerm = '';       // текущий поисковый запрос
  let currentEscHandler = null;     // ссылка на текущий Escape-обработчик

  // DOM-элементы
  const productsGrid = document.getElementById('productsGrid');
  const searchInput = document.getElementById('searchInput');
  const searchSuggestions = document.getElementById('searchSuggestions');
  const filterButtons = document.querySelectorAll('.filter-btn');
  const productModalOverlay = document.getElementById('productModalOverlay');
  const productModalClose = document.getElementById('productModalClose');
  const modalMainImage = document.getElementById('modalMainImage');
  const modalThumbnails = document.getElementById('modalThumbnails');
  const modalProductTitle = document.getElementById('modalProductTitle');
  const modalProductPrice = document.getElementById('modalProductPrice');
  const modalProductDescription = document.getElementById('modalProductDescription');

  // ==================== ЗАГРУЗКА ТОВАРОВ ====================
  async function loadProducts() {
    if (!productsGrid) return;

    // Показываем загрузку
    productsGrid.innerHTML = '<div class="equipment-loading"><div class="equipment-loading__spinner"></div></div>';

    try {
      if (typeof window.fetchAPI !== 'function') {
        throw new Error('fetchAPI не загружен — проверьте script.js');
      }
      const data = await window.fetchAPI('products');
      products = Array.isArray(data) ? data : [];
      filteredProducts = [...products];
      renderProducts(filteredProducts);
    } catch (error) {
      console.error('Ошибка загрузки товаров:', error);
      productsGrid.innerHTML = '<div class="error">Не удалось загрузить товары. Попробуйте позже.</div>';
    }
  }

  // ==================== ЗАГРУЗКА КОНТАКТОВ ====================
  async function loadContacts() {
    try {
      const contacts = await window.fetchAPI('contacts');
      if (typeof window.updateContacts === 'function') {
        window.updateContacts(contacts);
        console.log('Контакты обновлены:', contacts);
      } else {
        console.warn('Функция updateContacts не найдена');
      }
    } catch (error) {
      console.error('Ошибка загрузки контактов:', error);
    }
  }

  // ==================== РЕНДЕРИНГ КАРТОЧЕК ====================
  function renderProducts(productsArray) {
    if (!productsGrid) return;

    if (productsArray.length === 0) {
      productsGrid.innerHTML = `
        <div class="no-results">
          <i class="fas fa-search"></i>
          <h3>Товары не найдены</h3>
          <p>Попробуйте изменить параметры поиска или выберите другую категорию</p>
        </div>
      `;
      return;
    }

    let html = '';
    productsArray.forEach(product => {
      const categoryType = product.category === 'parts' ? 'parts' : 'equipment';
      const imagePath = product.images && product.images.length > 0
        ? window.getImageUrl(product.images[0].image_path)
        : '/assets/img/default.jpg';

      html += `
        <div class="product-card catalog-card" data-type="${categoryType}" data-product-id="${product.id}">
          <div class="product-card__image">
            <img src="${imagePath}" 
                 alt="${escapeHtml(product.name)}" 
                 loading="lazy"
                 onerror="this.onerror=null; this.src='/assets/img/default.jpg';">
          </div>
          <div class="product-card__content">
            <h3 class="product-card__title">${escapeHtml(product.name)}</h3>
            <div class="product-card__price">${product.price ? escapeHtml(product.price) : 'Цена по запросу'}</div>
            <button class="btn btn--primary product-details-btn" data-product-id="${product.id}">
              <i class="fas fa-info-circle"></i> Подробнее
            </button>
          </div>
        </div>
      `;
    });

    productsGrid.innerHTML = html;
  }

  // Простое экранирование HTML
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ==================== ФИЛЬТРАЦИЯ ====================
  function filterProducts() {
    filteredProducts = products.filter(product => {
      let categoryMatch = false;
      if (currentFilter === 'all') {
        categoryMatch = true;
      } else if (currentFilter === 'parts' && product.category === 'parts') {
        categoryMatch = true;
      } else if (currentFilter === 'equipment' && product.category === 'equipment') {
        categoryMatch = true;
      }

      let searchMatch = true;
      if (currentSearchTerm) {
        searchMatch = product.name.toLowerCase().includes(currentSearchTerm.toLowerCase());
      }

      return categoryMatch && searchMatch;
    });

    renderProducts(filteredProducts);
  }

  // ==================== ПОИСК И ПОДСКАЗКИ ====================
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      currentSearchTerm = this.value.trim();

      if (currentSearchTerm.length > 1) {
        const suggestions = products
          .filter(p => p.name.toLowerCase().includes(currentSearchTerm.toLowerCase()))
          .slice(0, 5);

        if (suggestions.length > 0) {
          searchSuggestions.innerHTML = '';
          searchSuggestions.style.display = 'block';
          suggestions.forEach(product => {
            const div = document.createElement('div');
            div.className = 'search-suggestion';
            div.innerHTML = `<i class="fas fa-search"></i><span>${escapeHtml(product.name)}</span>`;
            div.addEventListener('click', () => {
              searchInput.value = product.name;
              currentSearchTerm = product.name;
              searchSuggestions.style.display = 'none';
              filterProducts();
            });
            searchSuggestions.appendChild(div);
          });
        } else {
          searchSuggestions.innerHTML = '<div class="search-suggestion"><i class="fas fa-exclamation-circle"></i><span>Товары не найдены</span></div>';
          searchSuggestions.style.display = 'block';
        }
      } else {
        searchSuggestions.style.display = 'none';
        filterProducts();
      }
    });

    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
        searchSuggestions.style.display = 'none';
      }
    });

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        currentSearchTerm = searchInput.value.trim();
        filterProducts();
        searchSuggestions.style.display = 'none';
      }
    });
  }

  // ==================== ФИЛЬТРЫ КАТЕГОРИЙ ====================
  filterButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      filterButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.dataset.filter;
      filterProducts();
    });
  });

  // ==================== МОДАЛЬНОЕ ОКНО ТОВАРА ====================
  function openProductModal(productId) {
    const product = products.find(p => Number(p.id) === Number(productId));
    if (!product) return;

    if (modalProductTitle) modalProductTitle.textContent = product.name;
    if (modalProductPrice) modalProductPrice.textContent = product.price || 'Цена по запросу';
    if (modalProductDescription) modalProductDescription.textContent = product.description || 'Описание отсутствует';

    if (modalMainImage && modalThumbnails) {
      if (product.images && product.images.length > 0) {
        modalMainImage.src = window.getImageUrl(product.images[0].image_path);
        modalMainImage.alt = product.name;
        modalMainImage.onerror = function() { this.src = '/assets/img/default.jpg'; };

        modalThumbnails.innerHTML = '';
        product.images.forEach((img, index) => {
          const thumb = document.createElement('div');
          thumb.className = `thumbnail ${index === 0 ? 'active' : ''}`;
          const imgUrl = window.getImageUrl(img.image_path);
          thumb.innerHTML = `<img src="${imgUrl}" alt="${product.name} - фото ${index + 1}" onerror="this.src='/assets/img/default.jpg';">`;
          thumb.addEventListener('click', function() {
            modalMainImage.src = imgUrl;
            modalMainImage.onerror = function() { this.src = '/assets/img/default.jpg'; };
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
          });
          modalThumbnails.appendChild(thumb);
        });
      } else {
        modalMainImage.src = '/assets/img/default.jpg';
        modalThumbnails.innerHTML = '';
      }
    }

    if (productModalOverlay) {
      productModalOverlay.classList.add('active');
      if (window.Utils) window.Utils.lockScroll('modal');

      if (currentEscHandler) {
        document.removeEventListener('keydown', currentEscHandler);
      }
      currentEscHandler = (e) => {
        if (e.key === 'Escape') closeProductModal();
      };
      document.addEventListener('keydown', currentEscHandler);
    }
  }

  function closeProductModal() {
    if (productModalOverlay) {
      productModalOverlay.classList.remove('active');
      if (window.Utils) window.Utils.unlockScroll('modal');
      if (currentEscHandler) {
        document.removeEventListener('keydown', currentEscHandler);
        currentEscHandler = null;
      }
    }
  }

  if (productModalClose) {
    productModalClose.addEventListener('click', closeProductModal);
  }
  if (productModalOverlay) {
    productModalOverlay.addEventListener('click', (e) => {
      if (e.target === productModalOverlay) closeProductModal();
    });
  }

  if (productsGrid) {
    productsGrid.addEventListener('click', (e) => {
      const btn = e.target.closest('.product-details-btn');
      if (btn) {
        const productId = parseInt(btn.dataset.productId);
        openProductModal(productId);
      }
    });
  }

  // ==================== ИНИЦИАЛИЗАЦИЯ ====================
  async function init() {
    let attempts = 0;
    while (typeof window.fetchAPI !== 'function' && attempts < 30) {
      await new Promise((r) => setTimeout(r, 50));
      attempts++;
    }
    await loadProducts();
    await loadContacts();
  }

  init();
  console.log('Equipment page JavaScript loaded successfully with dynamic data!');
});