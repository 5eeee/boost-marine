// script.js – полная версия с поддержкой динамической загрузки данных из API
// Исправлена ошибка клонирования слайдов при пустом или малом количестве работ
// Добавлено обновление контактов в футере и блоке контактов
// ==================== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ====================
const API_BASE = 'https://admin.boostmarine.ru/'; // Базовый URL админки
window.API_BASE = API_BASE; // делаем глобально доступным

// Глобальная функция для вызова API
window.fetchAPI = async function(type) {
  try {
    const response = await fetch(`${API_BASE}api.php?type=${type}`);
    const result = await response.json();
    if (result.status !== 'success') {
      throw new Error(result.message || 'Unknown error');
    }
    return result.data;
  } catch (error) {
    console.error(`API error (${type}):`, error);
    throw error;
  }
};

// Глобальная функция маппинга названий разделов в slug
window.getSectionId = function(name) {
  const map = {
    'Ремонт и техническое обслуживание': 'repair',
    'Дооснащение, модернизация и инженерные системы': 'upgrade',
    'Диагностика технического состояния': 'diagnostics',
    'Помощь в покупке и подборе': 'purchase',
    'Консервация и сезонное обслуживание': 'preservation',
    'Ремонт гидроциклов': 'jetski',
    'Иные услуги': 'other'
  };
  return map[name] || name.toLowerCase().replace(/\s+/g, '');
};

// Глобальная функция для формирования полного URL изображения
window.getImageUrl = function(path) {
  if (!path) return '/assets/img/default.jpg';
  if (path.startsWith('http')) return path;
  if (window.API_BASE) {
    const base = window.API_BASE.endsWith('/') ? window.API_BASE : window.API_BASE + '/';
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return base + cleanPath;
  }
  return '/' + (path.startsWith('/') ? path.substring(1) : path);
};

// Основной объект DOM (можно оставить как есть или упростить)
const DOM = {
  body: document.body,
  header: document.querySelector('.header'),
  mobileContactBtn: document.getElementById('mobile-contact'),
  contactToggle: document.querySelector('.contact-toggle'),
  workModal: document.getElementById('work-modal'),
  workModalOverlay: document.getElementById('work-modal-overlay'),
  workModalClose: document.getElementById('work-modal-close'),
  workModalMainImg: document.getElementById('work-modal-main-img'),
  workCards: document.querySelectorAll('.work-card'),
  sections: document.querySelectorAll('section[id]'),
  cards: document.querySelectorAll('.card'),
  teamMembers: document.querySelectorAll('.team-member'),
  heroHalves: document.querySelectorAll('.hero__half'),
  teamModal: document.getElementById('team-modal'),
  teamModalOverlay: document.getElementById('team-modal-overlay'),
  teamModalClose: document.getElementById('team-modal-close'),
  teamModalImg: document.getElementById('team-modal-img'),
  heroDesktopServicesBtn: document.querySelector('.hero__desktop-services-btn'),
  heroMainBtn: document.querySelector('.hero__main-btn'),
  mobileMenu: document.getElementById('mobile-nav'),
  hamburger: document.getElementById('burger-wrapper'),
  burgerCheckbox: document.getElementById('burger-checkbox'),
  menuOverlay: document.querySelector('.menu-overlay'),
  mobileMenuLinks: document.querySelectorAll('.mobile-menu__link'),
  telegramDropdown: document.querySelector('.telegram-dropdown'),
  telegramMenu: document.querySelector('.telegram-dropdown__menu'),
  onsiteFeatures: document.querySelectorAll('.onsite-feature'),
  worksCarouselTrack: document.querySelector('.works-carousel__track'),
  worksCarouselContainer: document.querySelector('.works-carousel__container'),
  worksCarouselSlides: document.querySelectorAll('.works-carousel__slide'),
  worksCarouselPrev: document.querySelector('.works-carousel__prev'),
  worksCarouselNext: document.querySelector('.works-carousel__next'),
  worksCarouselDots: document.querySelectorAll('.works-carousel__dot'),
  workModalThumbnails: document.getElementById('work-modal-thumbnails'),
  dropdowns: document.querySelectorAll('.dropdown'),
  dropdownToggles: document.querySelectorAll('.dropdown-toggle'),
  mobileDropdowns: document.querySelectorAll('.mobile-dropdown'),
  mobileDropdownIcons: document.querySelectorAll('.mobile-dropdown-icon'),
  mobileDropdownLink: document.querySelector('.mobile-menu__link--dropdown'),
  heroSection: document.querySelector('.hero'),
  teamGrid: document.querySelector('.team-grid'),
  worksTrack: document.querySelector('.works-carousel__track'),
  worksDotsContainer: document.querySelector('.works-carousel__dots'),
  headerPhone: document.querySelector('.header-phone'),
  telegramChannelLink: document.querySelector('.telegram-dropdown__menu .telegram-dropdown__link:first-child'),
  telegramChatLink: document.querySelector('.telegram-dropdown__menu .telegram-dropdown__link:last-child'),
  whatsappLink: document.querySelector('.header-social__link.whatsapp'),
  contactAddress: document.querySelector('.contact-item__content p')
};

// Переменные состояния
let lastScrollTop = 0;
let isTouchDevice = false;
let headerUpdatePending = false;
let modalOpenCount = 0; // начинаем с 0
let savedScrollY = 0;

// Переменные для карусели работ
let currentSlideIndex = 0;
let totalSlides = 0;
let slidesPerView = 3;
let isDragging = false;
let startPos = 0;
let startPosY = 0;
let currentTranslate = 0;
let prevTranslate = 0;
let animationID = null;
let slideWidth = 0;
let cloneCount = 0;
let carouselAxisLocked = false;
let carouselIsHorizontal = false;

// ==================== ДАННЫЕ ИЗ API ====================
let worksData = {};
let teamData = [];
let contactsData = null;

// ==================== УТИЛИТЫ ====================
const Utils = {
  throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  },

  debounce(func, wait) {
    let timeout;
    return function() {
      const context = this;
      const args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  },

  smoothScrollTo(element, offset = 20) {
    const headerHeight = DOM.header?.offsetHeight || 70;
    const elementPosition = element.offsetTop - headerHeight - offset;
    window.scrollTo({ top: elementPosition, behavior: 'smooth' });
  },

  closeAllMenus() {
    if (DOM.mobileMenu?.classList.contains('active')) {
      DOM.mobileMenu.classList.remove('active');
      DOM.menuOverlay?.classList.remove('active');
      DOM.body.classList.remove('menu-open');
      document.documentElement.classList.remove('menu-open');
      DOM.body.style.overflow = '';
      if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
    }
    if (DOM.mobileContactBtn?.classList.contains('active')) {
      DOM.mobileContactBtn.classList.remove('active');
    }
    DOM.dropdowns.forEach(d => d.classList.remove('active'));
    DOM.mobileDropdowns.forEach(d => d.classList.remove('active'));
    if (DOM.telegramMenu) {
      DOM.telegramMenu.style.opacity = '0';
      DOM.telegramMenu.style.visibility = 'hidden';
      DOM.telegramMenu.style.transform = 'translateY(-10px)';
    }
  },

  checkMobile() { return window.innerWidth <= 767; },
  checkTablet() { return window.innerWidth >= 768 && window.innerWidth <= 1023; },
  checkDesktop() { return window.innerWidth >= 1024; },

  detectTouchDevice() {
    return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
  },

  _getScrollbarWidth() {
    return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
  },

  _applyScrollbarCompensation(width) {
    const gap = `${width}px`;
    document.documentElement.style.setProperty('--scrollbar-width', gap);
    if (width > 0) {
      document.documentElement.style.paddingRight = gap;
      DOM.body.style.paddingRight = gap;
    }
  },

  _clearScrollbarCompensation() {
    document.documentElement.style.removeProperty('--scrollbar-width');
    document.documentElement.style.paddingRight = '';
    DOM.body.style.paddingRight = '';
  },

  lockScroll(modalType = 'modal') {
    if (modalType === 'modal') {
      if (modalOpenCount === 0) {
        savedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
        Utils._applyScrollbarCompensation(Utils._getScrollbarWidth());
        DOM.body.style.position = 'fixed';
        DOM.body.style.top = `-${savedScrollY}px`;
        DOM.body.style.left = '0';
        DOM.body.style.right = '0';
        DOM.body.style.width = '100%';
        DOM.body.classList.add('modal-open');
        document.documentElement.classList.add('modal-open');
        Utils.bindTouchScrollBlock();
      }
      modalOpenCount++;
    } else if (modalType === 'menu') {
      savedScrollY = window.scrollY || document.documentElement.scrollTop || 0;
      Utils._applyScrollbarCompensation(Utils._getScrollbarWidth());
      DOM.body.style.position = 'fixed';
      DOM.body.style.top = `-${savedScrollY}px`;
      DOM.body.style.left = '0';
      DOM.body.style.right = '0';
      DOM.body.style.width = '100%';
      DOM.body.classList.add('menu-open');
      document.documentElement.classList.add('menu-open');
    }
  },

  unlockScroll(modalType = 'modal') {
    if (modalType === 'modal') {
      modalOpenCount = Math.max(0, modalOpenCount - 1);
      if (modalOpenCount === 0) {
        const scrollY = savedScrollY;
        DOM.body.classList.remove('modal-open');
        document.documentElement.classList.remove('modal-open');
        DOM.body.style.position = '';
        DOM.body.style.top = '';
        DOM.body.style.left = '';
        DOM.body.style.right = '';
        DOM.body.style.width = '';
        Utils._clearScrollbarCompensation();
        window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' });
        if (Math.abs(window.scrollY - scrollY) > 2) window.scrollTo(0, scrollY);
        Utils.unbindTouchScrollBlock();
      }
    } else if (modalType === 'menu') {
      const scrollY = savedScrollY;
      DOM.body.classList.remove('menu-open');
      document.documentElement.classList.remove('menu-open');
      DOM.body.style.position = '';
      DOM.body.style.top = '';
      DOM.body.style.left = '';
      DOM.body.style.right = '';
      DOM.body.style.width = '';
      Utils._clearScrollbarCompensation();
      window.scrollTo({ top: scrollY, left: 0, behavior: 'instant' });
    }
  },

  escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  formatMultilineText(text) {
    if (!text) return '';
    const lines = String(text).split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
    if (!lines.length) return '';

    let html = '';
    let inList = false;

    const closeList = () => {
      if (inList) {
        html += '</ul>';
        inList = false;
      }
    };

    lines.forEach((line) => {
      const bullet = line.match(/^[-•*–—]\s+(.+)$/) || line.match(/^\d+[.)]\s+(.+)$/);
      if (bullet) {
        if (!inList) {
          html += '<ul>';
          inList = true;
        }
        html += `<li>${Utils.escapeHtml(bullet[1])}</li>`;
      } else {
        closeList();
        html += `<p>${Utils.escapeHtml(line)}</p>`;
      }
    });
    closeList();
    return html;
  },

  preloadImage(url) {
    if (!url) return;
    const img = new Image();
    img.decoding = 'async';
    img.src = url;
  },

  _touchBlockHandler: null,

  bindTouchScrollBlock() {
    if (Utils._touchBlockHandler) return;
    Utils._touchBlockHandler = (e) => {
      if (modalOpenCount <= 0) return;
      const t = e.target;
      const allow = t.closest?.(
        '.work-modal__content, .work-modal__gallery, .work-modal-thumbnails, ' +
        '.team-modal__content, .mobile-menu, .zoom-modal, .zoom-modal__image-area, .zoom-modal__img'
      );
      if (allow) return;
      e.preventDefault();
    };
    document.addEventListener('touchmove', Utils._touchBlockHandler, { passive: false });
  },

  unbindTouchScrollBlock() {
    if (modalOpenCount > 0 || !Utils._touchBlockHandler) return;
    document.removeEventListener('touchmove', Utils._touchBlockHandler);
    Utils._touchBlockHandler = null;
  },

  showLoading(container) {
    if (!container) return;
    container.classList.add('loading');
    if (!container.querySelector('.loading-spinner')) {
      const spinner = document.createElement('div');
      spinner.className = 'loading-spinner';
      spinner.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
      container.style.position = 'relative';
      container.appendChild(spinner);
    }
  },

  hideLoading(container) {
    if (!container) return;
    container.classList.remove('loading');
    const spinner = container.querySelector('.loading-spinner');
    if (spinner) spinner.remove();
  },

  // Псевдоним для глобальной fetchAPI (для совместимости)
  fetchAPI: window.fetchAPI
};

// ==================== КАРТОЧКИ УСЛУГ ====================
function initCards() {
  DOM.cards.forEach(card => {
    card.removeEventListener('click', handleCardClick);
    card.addEventListener('click', handleCardClick);
  });
}
function handleCardClick(e) { return true; }

// ==================== КАРТОЧКИ РАБОТ (ДЕЛЕГИРОВАНИЕ) ====================
const WorkCards = {
  init() {
    const container = DOM.worksCarouselContainer || DOM.worksCarouselTrack;
    if (!container) return;
    
    container.addEventListener('click', (e) => {
      const viewBtn = e.target.closest('.work-card__view-btn');
      if (viewBtn) {
        e.preventDefault();
        e.stopPropagation();
        const slide = viewBtn.closest('.works-carousel__slide');
        if (slide) WorkModal.open(slide.dataset.project);
        return;
      }
      const slide = e.target.closest('.works-carousel__slide');
      if (slide) WorkModal.open(slide.dataset.project);
    });
  }
};

// ==================== БУРГЕР МЕНЮ ====================
const BurgerMenu = {
  init() {
    if (!DOM.hamburger || !DOM.burgerCheckbox) return;

    DOM.burgerCheckbox.addEventListener('change', (e) => {
      if (e.target.checked) BurgerMenu.open();
      else BurgerMenu.close();
    });

    DOM.menuOverlay?.addEventListener('click', () => {
      if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
      BurgerMenu.close();
    });

    DOM.mobileMenuLinks.forEach(link => {
      if (!link.classList.contains('mobile-menu__link--dropdown')) {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          const href = link.getAttribute('href');
          if (href?.startsWith('#')) {
            const target = document.querySelector(href);
            if (target) {
              if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
              BurgerMenu.close();
              setTimeout(() => Utils.smoothScrollTo(target), 300);
            }
          } else if (href) {
            if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
            BurgerMenu.close();
            setTimeout(() => window.location.href = href, 300);
          } else {
            if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
            BurgerMenu.close();
          }
        });
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && DOM.mobileMenu?.classList.contains('active')) {
        if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
        BurgerMenu.close();
      }
    });

    document.addEventListener('click', (e) => {
      if (DOM.mobileMenu?.classList.contains('active') &&
          !DOM.hamburger?.contains(e.target) &&
          !DOM.mobileMenu?.contains(e.target)) {
        if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
        BurgerMenu.close();
      }
    });

    BurgerMenu.initMobileDropdowns();
  },

  initMobileDropdowns() {
    DOM.mobileDropdowns.forEach(dropdown => {
      const link = dropdown.querySelector('.mobile-menu__link--dropdown');
      if (link) {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          dropdown.classList.toggle('active');
        });
      }
      const items = dropdown.querySelectorAll('.mobile-dropdown-item');
      items.forEach(item => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          const href = item.getAttribute('href');
          if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
          BurgerMenu.close();
          setTimeout(() => window.location.href = href, 300);
        });
      });
    });
  },

  open() {
    DOM.mobileMenu?.classList.add('active');
    DOM.menuOverlay?.classList.add('active');
    Utils.lockScroll('menu');
    if (DOM.burgerCheckbox && !DOM.burgerCheckbox.checked) {
      DOM.burgerCheckbox.checked = true;
    }
    DOM.mobileContactBtn?.classList.remove('active');
  },

  close() {
    DOM.mobileMenu?.classList.remove('active');
    DOM.menuOverlay?.classList.remove('active');
    Utils.unlockScroll('menu');
    if (DOM.burgerCheckbox && DOM.burgerCheckbox.checked) {
      DOM.burgerCheckbox.checked = false;
    }
    DOM.mobileDropdowns.forEach(d => d.classList.remove('active'));
  }
};

// ==================== МЕНЮ КОНТАКТОВ ====================
const ContactMenu = {
  init() {
    DOM.contactToggle?.addEventListener('click', ContactMenu.toggle);
    const contactLinks = DOM.mobileContactBtn?.querySelectorAll('.mobile-contact-link');
    contactLinks?.forEach(link => {
      link.addEventListener('click', () => DOM.mobileContactBtn?.classList.remove('active'));
    });
    document.addEventListener('click', (e) => {
      if (DOM.mobileContactBtn && !DOM.mobileContactBtn.contains(e.target) &&
          DOM.mobileContactBtn.classList.contains('active')) {
        DOM.mobileContactBtn.classList.remove('active');
      }
    });
  },
  toggle(e) {
    e?.stopPropagation?.();
    e?.preventDefault?.();
    if (DOM.mobileMenu?.classList.contains('active')) {
      BurgerMenu.close();
      if (DOM.burgerCheckbox) DOM.burgerCheckbox.checked = false;
    }
    DOM.mobileContactBtn?.classList.toggle('active');
  }
};

// ==================== КАРУСЕЛЬ РАБОТ ====================
const WorksCarousel = {
  init() {
    if (!DOM.worksCarouselTrack) return;
    const originalSlides = document.querySelectorAll('.works-carousel__slide:not(.clone)');
    if (originalSlides.length === 0) return; // нет слайдов — ничего не делаем

    totalSlides = originalSlides.length;
    WorksCarousel.updateSlidesPerView();
    WorksCarousel.cloneSlides();
    WorksCarousel.updateSlideWidth();
    currentSlideIndex = 0;
    WorksCarousel.setPositionByRealIndex(currentSlideIndex);
    WorksCarousel.initNavigation();
    WorksCarousel.initSwipe();
    window.addEventListener('resize', WorksCarousel.handleResize);
  },

  rebuild() {
    WorksCarousel.init();
  },

  updateSlidesPerView() {
    if (Utils.checkMobile()) slidesPerView = 1;
    else if (Utils.checkTablet()) slidesPerView = 2;
    else slidesPerView = 3;
  },

  updateSlideWidth() {
    const slide = document.querySelector('.works-carousel__slide:not(.clone)');
    const track = DOM.worksCarouselTrack;
    if (slide && track) {
      const gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 0;
      slideWidth = slide.getBoundingClientRect().width + gap;
      return;
    }
    const container = document.querySelector('.works-carousel__container');
    if (container) slideWidth = container.clientWidth / slidesPerView;
  },

  cloneSlides() {
    const slides = Array.from(document.querySelectorAll('.works-carousel__slide:not(.clone)'));
    const track = DOM.worksCarouselTrack;
    if (!track) return;

    // Удаляем старые клоны
    track.querySelectorAll('.clone').forEach(clone => clone.remove());

    // Если слайдов меньше, чем slidesPerView, не клонируем (карусель будет простой)
    if (slides.length < slidesPerView) {
      cloneCount = 0;
      WorksCarousel.updateSlideReferences();
      return;
    }

    // Клонирование в начало
    for (let i = 0; i < slidesPerView; i++) {
      const clone = slides[slides.length - 1 - i].cloneNode(true);
      clone.classList.add('clone');
      track.insertBefore(clone, track.firstChild);
    }
    // Клонирование в конец
    for (let i = 0; i < slidesPerView; i++) {
      const clone = slides[i].cloneNode(true);
      clone.classList.add('clone');
      track.appendChild(clone);
    }

    cloneCount = slidesPerView;
    WorksCarousel.updateSlideReferences();
  },

  updateSlideReferences() {
    DOM.worksCarouselSlides = document.querySelectorAll('.works-carousel__slide');
  },

  initNavigation() {
    DOM.worksCarouselPrev?.addEventListener('click', WorksCarousel.prevSlide);
    DOM.worksCarouselNext?.addEventListener('click', WorksCarousel.nextSlide);
    DOM.worksCarouselDots.forEach((dot, index) => {
      dot.addEventListener('click', () => WorksCarousel.goToSlide(index));
    });
  },

  initSwipe() {
    if (!Utils.checkMobile() || !DOM.worksCarouselTrack) return;
    const el = DOM.worksCarouselTrack;
    el.addEventListener('touchstart', WorksCarousel.touchStart, { passive: true });
    el.addEventListener('touchmove', WorksCarousel.touchMove, { passive: false });
    el.addEventListener('touchend', WorksCarousel.touchEnd, { passive: true });
    el.addEventListener('touchcancel', WorksCarousel.touchEnd, { passive: true });
  },

  touchStart(e) {
    if (!Utils.checkMobile() || !e.touches?.length) return;
    isDragging = true;
    carouselAxisLocked = false;
    carouselIsHorizontal = false;
    startPos = e.touches[0].clientX;
    startPosY = e.touches[0].clientY;
    prevTranslate = currentTranslate;
    DOM.worksCarouselTrack.style.transition = 'none';
    cancelAnimationFrame(animationID);
  },

  touchMove(e) {
    if (!isDragging || !e.touches?.length) return;
    const currentX = e.touches[0].clientX;
    const currentY = e.touches[0].clientY;
    const diffX = currentX - startPos;
    const diffY = currentY - startPosY;

    if (!carouselAxisLocked) {
      if (Math.abs(diffX) < 10 && Math.abs(diffY) < 10) return;
      if (Math.abs(diffY) > Math.abs(diffX)) {
        isDragging = false;
        DOM.worksCarouselTrack.style.transition = '';
        return;
      }
      carouselAxisLocked = true;
      carouselIsHorizontal = true;
    }

    if (!carouselIsHorizontal) return;
    e.preventDefault();
    currentTranslate = prevTranslate + diffX;
    DOM.worksCarouselTrack.style.transform = `translateX(${currentTranslate}px)`;
  },

  touchEnd() {
    if (!isDragging) return;
    isDragging = false;
    carouselAxisLocked = false;
    if (!carouselIsHorizontal) {
      carouselIsHorizontal = false;
      return;
    }
    carouselIsHorizontal = false;
    const movedBy = currentTranslate - prevTranslate;
    if (Math.abs(movedBy) > slideWidth * 0.18) {
      movedBy > 0 ? WorksCarousel.prevSlide() : WorksCarousel.nextSlide();
    } else {
      WorksCarousel.setPositionByRealIndex(currentSlideIndex);
    }
    DOM.worksCarouselTrack.style.transition = 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
  },

  getPositionX(e) {
    return e.type.includes('mouse') ? e.pageX : e.touches[0].clientX;
  },

  prevSlide() {
    if (currentSlideIndex <= 0) {
      WorksCarousel.setPositionByRealIndex(-1, true);
      currentSlideIndex = totalSlides - 1;
    } else {
      currentSlideIndex--;
    }
    WorksCarousel.updateCarousel();
  },

  nextSlide() {
    if (currentSlideIndex >= totalSlides - 1) {
      WorksCarousel.setPositionByRealIndex(totalSlides, true);
      currentSlideIndex = 0;
    } else {
      currentSlideIndex++;
    }
    WorksCarousel.updateCarousel();
  },

  goToSlide(index) {
    currentSlideIndex = index;
    WorksCarousel.updateCarousel();
  },

  updateCarousel() {
    WorksCarousel.setPositionByRealIndex(currentSlideIndex);
    WorksCarousel.updateDots();
  },

  setPositionByRealIndex(index, instant = false) {
    const offset = cloneCount * slideWidth;
    const targetTranslate = -offset - (index * slideWidth);
    currentTranslate = targetTranslate;
    DOM.worksCarouselTrack.style.transform = `translateX(${targetTranslate}px)`;
    DOM.worksCarouselTrack.style.transition = instant ? 'none' : 'transform 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
  },

  updateDots() {
    DOM.worksCarouselDots.forEach((dot, i) => {
      dot.classList.toggle('active', i === currentSlideIndex);
    });
  },

  handleResize: Utils.debounce(function() {
    WorksCarousel.updateSlidesPerView();
    WorksCarousel.updateSlideWidth();
    WorksCarousel.cloneSlides();
    WorksCarousel.setPositionByRealIndex(currentSlideIndex);
  }, 200)
};

// ==================== МОДАЛЬНОЕ ОКНО РАБОТ ====================
const WorkModal = {
  currentImages: null,
  currentImageIndex: 0,

  init() {
    DOM.workModalOverlay?.addEventListener('click', WorkModal.close);
    DOM.workModalClose?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      WorkModal.close();
    });
    DOM.workModal?.querySelector('.work-modal__content')?.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    document.addEventListener('keydown', (e) => {
      if (!DOM.workModal?.classList.contains('active')) return;
      if (document.getElementById('dynamic-zoom-modal')) return;
      if (e.key === 'Escape') WorkModal.close();
      if (e.key === 'ArrowLeft') WorkModal.prevImage();
      if (e.key === 'ArrowRight') WorkModal.nextImage();
    });
    document.getElementById('work-modal-prev')?.addEventListener('click', (e) => {
      e.stopPropagation();
      WorkModal.prevImage();
    });
    document.getElementById('work-modal-next')?.addEventListener('click', (e) => {
      e.stopPropagation();
      WorkModal.nextImage();
    });
    WorkModal.initSwipe();
    DOM.workModalMainImg?.addEventListener('click', () => WorkModal.openZoomView());
  },

  initSwipe() {
    const mainImage = document.getElementById('work-modal-main-image') || document.querySelector('.work-modal-main-image');
    if (!mainImage) return;

    let startX = 0;
    let startY = 0;
    let tracking = false;

    mainImage.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1) return;
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      tracking = true;
    }, { passive: true });

    mainImage.addEventListener('touchmove', (e) => {
      if (!tracking || e.touches.length !== 1) return;
      const dx = Math.abs(e.touches[0].clientX - startX);
      const dy = Math.abs(e.touches[0].clientY - startY);
      if (dx > dy && dx > 12) e.preventDefault();
    }, { passive: false });

    mainImage.addEventListener('touchend', (e) => {
      if (!tracking) return;
      tracking = false;
      const endX = e.changedTouches[0].clientX;
      const endY = e.changedTouches[0].clientY;
      const diffX = startX - endX;
      const diffY = startY - endY;
      if (Math.abs(diffX) < 40 || Math.abs(diffX) < Math.abs(diffY)) return;
      if (diffX > 0) WorkModal.nextImage();
      else WorkModal.prevImage();
    }, { passive: true });
  },

  open(projectId) {
    const project = worksData[projectId];
    if (!project) return;
    Utils.closeAllMenus();

    document.getElementById('work-modal-vessel').textContent = project.vessel;
    document.getElementById('work-modal-type').textContent = project.repair_type;
    document.getElementById('work-modal-duration').textContent = project.duration;

    const descEl = document.getElementById('work-modal-desc');
    const listEl = document.getElementById('work-modal-list');
    const resultEl = document.getElementById('work-modal-result');
    const rawDesc = project.description || '';

    if (descEl) {
      descEl.innerHTML = Utils.formatMultilineText(rawDesc);
    }
    if (listEl) listEl.innerHTML = '';
    if (resultEl) {
      resultEl.innerHTML = project.result ? Utils.formatMultilineText(project.result) : '';
      resultEl.style.display = project.result ? '' : 'none';
    }

    const navPrev = document.getElementById('work-modal-prev');
    const navNext = document.getElementById('work-modal-next');
    const navWrap = document.getElementById('work-modal-gallery-nav');
    const hasMultiple = project.images && project.images.length > 1;
    if (navPrev) navPrev.style.display = hasMultiple ? '' : 'none';
    if (navNext) navNext.style.display = hasMultiple ? '' : 'none';
    if (navWrap) navWrap.style.display = hasMultiple ? '' : 'none';

    WorkModal.updateGallery(project.images, projectId);
    DOM.workModal.classList.add('active');
    const content = DOM.workModal?.querySelector('.work-modal__content');
    if (content) content.scrollTop = 0;
    Utils.lockScroll('modal');
  },

  close() {
    DOM.workModal.classList.remove('active');
    Utils.unlockScroll('modal');
    document.getElementById('dynamic-zoom-modal')?.remove();
  },

  updateGallery(images, projectId) {
    if (DOM.workModalThumbnails) DOM.workModalThumbnails.innerHTML = '';
    if (DOM.workModalMainImg && images.length) {
      const firstUrl = window.getImageUrl(images[0].image_path);
      DOM.workModalMainImg.src = firstUrl;
      DOM.workModalMainImg.alt = `Фото проекта ${projectId}`;
      DOM.workModalMainImg.dataset.index = 0;
      DOM.workModalMainImg.decoding = 'async';
    }
    images.forEach((imgObj, index) => {
      const thumb = document.createElement('div');
      thumb.className = `work-modal-thumbnail ${index === 0 ? 'active' : ''}`;
      thumb.dataset.index = index;
      const img = document.createElement('img');
      const url = window.getImageUrl(imgObj.image_path);
      img.src = url;
      img.alt = `Миниатюра ${index + 1}`;
      img.loading = index < 4 ? 'eager' : 'lazy';
      img.decoding = 'async';
      thumb.appendChild(img);
      thumb.addEventListener('click', () => WorkModal.setActiveImage(index, images));
      DOM.workModalThumbnails?.appendChild(thumb);
      if (index < 3) Utils.preloadImage(url);
    });
    WorkModal.currentImages = images;
    WorkModal.currentImageIndex = 0;
  },

  setActiveImage(index, images) {
    if (!DOM.workModalMainImg || !images?.length) return;
    const url = window.getImageUrl(images[index].image_path);
    DOM.workModalMainImg.src = url;
    DOM.workModalMainImg.dataset.index = index;
    WorkModal.currentImageIndex = index;
    document.querySelectorAll('.work-modal-thumbnail').forEach((thumb, i) => {
      thumb.classList.toggle('active', i === index);
    });
    const next = (index + 1) % images.length;
    const prev = (index - 1 + images.length) % images.length;
    Utils.preloadImage(window.getImageUrl(images[next].image_path));
    Utils.preloadImage(window.getImageUrl(images[prev].image_path));
  },

  prevImage() {
    if (!WorkModal.currentImages?.length) return;
    let idx = WorkModal.currentImageIndex - 1;
    if (idx < 0) idx = WorkModal.currentImages.length - 1;
    WorkModal.setActiveImage(idx, WorkModal.currentImages);
  },

  nextImage() {
    if (!WorkModal.currentImages?.length) return;
    let idx = WorkModal.currentImageIndex + 1;
    if (idx >= WorkModal.currentImages.length) idx = 0;
    WorkModal.setActiveImage(idx, WorkModal.currentImages);
  },

  openZoomView(startIndex) {
    const images = WorkModal.currentImages;
    if (!images?.length || !DOM.workModalMainImg) return;

    let index = typeof startIndex === 'number' ? startIndex : WorkModal.currentImageIndex;
    document.getElementById('dynamic-zoom-modal')?.remove();

    const zoomModal = document.createElement('div');
    const hasMultiple = images.length > 1;
    zoomModal.className = 'zoom-modal active' + (hasMultiple ? '' : ' zoom-modal--single');
    zoomModal.id = 'dynamic-zoom-modal';
    zoomModal.innerHTML = `
      <div class="zoom-modal__overlay"></div>
      <button type="button" class="zoom-modal__close" aria-label="Закрыть"><i class="fas fa-times"></i></button>
      <div class="zoom-modal__image-area" id="zoom-image-area">
          <img class="zoom-modal__img" id="zoom-img" src="" alt="" decoding="async">
        </div>
        ${hasMultiple ? `
        <div class="zoom-modal__nav-row">
          <button type="button" class="zoom-modal__nav-btn zoom-modal__prev" aria-label="Предыдущее фото"><i class="fas fa-chevron-left"></i></button>
          <button type="button" class="zoom-modal__nav-btn zoom-modal__next" aria-label="Следующее фото"><i class="fas fa-chevron-right"></i></button>
        </div>` : ''}
    `;

    DOM.body.appendChild(zoomModal);

    const zoomImg = zoomModal.querySelector('#zoom-img');
    const imageArea = zoomModal.querySelector('#zoom-image-area');
    const closeBtn = zoomModal.querySelector('.zoom-modal__close');
    const prevBtn = zoomModal.querySelector('.zoom-modal__prev');
    const nextBtn = zoomModal.querySelector('.zoom-modal__next');

    const showIndex = (idx) => {
      index = idx;
      WorkModal.currentImageIndex = idx;
      if (zoomImg) {
        zoomImg.src = window.getImageUrl(images[idx].image_path);
        zoomImg.alt = DOM.workModalMainImg.alt || '';
      }
      WorkModal.setActiveImage(idx, images);
    };

    showIndex(index);

    const closeZoom = () => {
      zoomModal.remove();
      document.removeEventListener('keydown', escHandler);
    };

    const escHandler = (e) => {
      if (e.key === 'Escape') closeZoom();
      if (e.key === 'ArrowLeft' && hasMultiple) {
        let idx = index - 1;
        if (idx < 0) idx = images.length - 1;
        showIndex(idx);
      }
      if (e.key === 'ArrowRight' && hasMultiple) {
        let idx = index + 1;
        if (idx >= images.length) idx = 0;
        showIndex(idx);
      }
    };
    document.addEventListener('keydown', escHandler);

    closeBtn?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeZoom();
    });

    prevBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      let idx = index - 1;
      if (idx < 0) idx = images.length - 1;
      showIndex(idx);
    });

    nextBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      let idx = index + 1;
      if (idx >= images.length) idx = 0;
      showIndex(idx);
    });

    if (imageArea && hasMultiple) {
      let startX = 0;
      let startY = 0;
      let tracking = false;
      imageArea.addEventListener('touchstart', (e) => {
        if (e.touches.length !== 1) return;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        tracking = true;
      }, { passive: true });
      imageArea.addEventListener('touchmove', (e) => {
        if (!tracking || e.touches.length !== 1) return;
        const dx = Math.abs(e.touches[0].clientX - startX);
        const dy = Math.abs(e.touches[0].clientY - startY);
        if (dx > dy && dx > 12) e.preventDefault();
      }, { passive: false });
      imageArea.addEventListener('touchend', (e) => {
        if (!tracking) return;
        tracking = false;
        const diffX = startX - e.changedTouches[0].clientX;
        const diffY = startY - e.changedTouches[0].clientY;
        if (Math.abs(diffX) < 40 || Math.abs(diffX) < Math.abs(diffY)) return;
        if (diffX > 0) {
          let idx = index + 1;
          if (idx >= images.length) idx = 0;
          showIndex(idx);
        } else {
          let idx = index - 1;
          if (idx < 0) idx = images.length - 1;
          showIndex(idx);
        }
      }, { passive: true });
    }
  }
};

// ==================== МОДАЛЬНОЕ ОКНО КОМАНДЫ ====================
const TeamModal = {
  _initialized: false,

  init() {
    // Снимаем старые обработчики перед повторной привязкой
    DOM.teamMembers.forEach(member => {
      const clone = member.cloneNode(true);
      member.parentNode.replaceChild(clone, member);
    });
    // Обновляем NodeList после замены
    DOM.teamMembers = document.querySelectorAll('.team-member');

    DOM.teamMembers.forEach(member => {
      member.addEventListener('click', function(e) {
        e.preventDefault();
        const img = this.querySelector('img');
        if (img?.src) {
          DOM.teamModalImg.src = img.src;
          DOM.teamModalImg.alt = img.alt;
          TeamModal.open();
        }
      });
    });

    if (!TeamModal._initialized) {
      DOM.teamModalOverlay?.addEventListener('click', TeamModal.close);
      DOM.teamModalClose?.addEventListener('click', TeamModal.close);
      document.addEventListener('keydown', (e) => e.key === 'Escape' && TeamModal.close());
      TeamModal._initialized = true;
    }
  },

  reinit() {
    DOM.teamMembers = document.querySelectorAll('.team-member');
    TeamModal.init();
  },

  open() {
    Utils.closeAllMenus();
    DOM.teamModal.classList.add('active');
    Utils.lockScroll('modal');
  },
  close() {
    DOM.teamModal.classList.remove('active');
    Utils.unlockScroll('modal');
  }
};

// ==================== ПЛАВНЫЙ СКРОЛЛ ====================
const SmoothScroll = {
  init() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', SmoothScroll.handleClick);
    });
  },
  handleClick(e) {
    const href = this.getAttribute('href');
    if (href === '#' || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    const target = document.querySelector(href);
    if (target) {
      e.preventDefault();
      Utils.closeAllMenus();
      if (DOM.workModal?.classList.contains('active')) WorkModal.close();
      if (DOM.teamModal?.classList.contains('active')) TeamModal.close();
      Utils.smoothScrollTo(target);
    }
  }
};

// ==================== ИЗМЕНЕНИЕ ШАПКИ ПРИ СКРОЛЛЕ ====================
const HeaderScroll = {
  init() {
    window.addEventListener('scroll', HeaderScroll.requestUpdate);
    HeaderScroll.createScrollToTopBtn();
    HeaderScroll.requestUpdate();
  },

  createScrollToTopBtn() {
    const btn = document.createElement('button');
    btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
    btn.className = 'scroll-to-top';
    btn.setAttribute('aria-label', 'Наверх');
    DOM.body.appendChild(btn);
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  },

  requestUpdate() {
    if (!headerUpdatePending) {
      headerUpdatePending = true;
      requestAnimationFrame(HeaderScroll.update);
    }
  },

  update() {
    headerUpdatePending = false;
    if (!DOM.header || !DOM.heroSection) return;

    const heroHeight = DOM.heroSection.offsetHeight;
    const scrollY = window.scrollY;
    const headerHeight = DOM.header.offsetHeight;

    if (heroHeight > 0 && scrollY <= heroHeight - headerHeight - 10) {
      DOM.header.style.background = 'transparent';
      DOM.header.style.backdropFilter = 'blur(15px)';
      DOM.header.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
      DOM.header.classList.remove('scrolled');
    } else {
      DOM.header.style.background = 'rgba(10, 10, 10, 0.95)';
      DOM.header.style.backdropFilter = 'blur(15px)';
      DOM.header.style.borderBottom = '1px solid var(--border-color)';
      DOM.header.classList.add('scrolled');
    }

    const scrollBtn = document.querySelector('.scroll-to-top');
    if (scrollBtn) scrollBtn.classList.toggle('visible', scrollY > 500);
  }
};

// ==================== ГЛАВНЫЙ ЭКРАН ====================
const HeroSection = {
  init() {
    this.adjustHeight();
    this.adjustImages();
    this.setupMobileBehavior();
    document.querySelector('.hero__scroll')?.addEventListener('click', () => {
      const works = document.querySelector('#works');
      if (works) Utils.smoothScrollTo(works);
    });
    window.addEventListener('resize', Utils.debounce(() => {
      this.adjustHeight();
      this.adjustImages();
      this.setupMobileBehavior();
    }, 100));
  },
  adjustHeight() {
    const hero = document.querySelector('.hero');
    if (!hero) return;
    // На мобиле innerHeight меняется при скролле (адресная строка) — из‑за этого блок «прыгает»
    if (window.matchMedia('(max-width: 768px)').matches) {
      hero.style.height = '';
      hero.style.minHeight = '';
      hero.style.maxHeight = '';
      return;
    }
    hero.style.height = `${window.innerHeight}px`;
  },
  adjustImages() {},
  setupMobileBehavior() {}
};

// ==================== ВИДЕО ДЛЯ КАРТОЧЕК ====================
const VideoHandler = {
  init() {
    document.querySelectorAll('video[autoplay]').forEach(video => {
      video.muted = true;
      video.play().catch(() => VideoHandler.addPlayButton(video));
    });
  },
  addPlayButton(video) {
    const btn = document.createElement('button');
    btn.innerHTML = '<i class="fas fa-play"></i>';
    btn.className = 'video-play-button';
    btn.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:3;background:rgba(0,0,0,0.7);border:none;border-radius:50%;width:60px;height:60px;color:white;font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;';
    video.parentNode.style.position = 'relative';
    video.parentNode.appendChild(btn);
    btn.addEventListener('click', () => { video.play(); btn.style.display = 'none'; });
    video.addEventListener('play', () => btn.style.display = 'none');
    video.addEventListener('pause', () => btn.style.display = 'flex');
  }
};

// ==================== ВЫПАДАЮЩИЕ МЕНЮ (ДЕСКТОП) ====================
const Dropdowns = {
  init() {
    DOM.dropdownToggles.forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const dropdown = this.closest('.dropdown');
        if (!dropdown) return;
        const isActive = dropdown.classList.contains('active');
        DOM.dropdowns.forEach(d => d.classList.remove('active'));
        if (!isActive) dropdown.classList.add('active');
      });
    });
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        DOM.dropdowns.forEach(d => d.classList.remove('active'));
      }
    });
    if (!window.matchMedia('(hover: hover)').matches) {
      DOM.dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
    }
  }
};

// ==================== ТЕЛЕГРАМ ВЫПАДАЮЩЕЕ МЕНЮ ====================
const TelegramDropdown = {
  show() {
    DOM.telegramMenu.style.opacity = '1';
    DOM.telegramMenu.style.visibility = 'visible';
    DOM.telegramMenu.style.transform = 'translateY(0)';
  },
  hide() {
    DOM.telegramMenu.style.opacity = '0';
    DOM.telegramMenu.style.visibility = 'hidden';
    DOM.telegramMenu.style.transform = 'translateY(-10px)';
  },
  init() {
    if (!DOM.telegramDropdown || !DOM.telegramMenu) return;
    // На десктопе — ховер
    if (window.matchMedia('(hover: hover)').matches) {
      DOM.telegramDropdown.addEventListener('mouseenter', TelegramDropdown.show);
      DOM.telegramDropdown.addEventListener('mouseleave', TelegramDropdown.hide);
    }
    // На всех устройствах — клик по иконке тоггл
    const btn = DOM.telegramDropdown.querySelector('.header-social__link.telegram');
    btn?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const isVisible = DOM.telegramMenu.style.visibility === 'visible';
      isVisible ? TelegramDropdown.hide() : TelegramDropdown.show();
    });
    document.addEventListener('click', (e) => {
      if (DOM.telegramDropdown && DOM.telegramMenu && !DOM.telegramDropdown.contains(e.target) &&
          DOM.telegramMenu.style.visibility === 'visible') {
        TelegramDropdown.hide();
      }
    });
  }
};

// ==================== АДАПТИВНОСТЬ ====================
const Responsive = {
  init() {
    isTouchDevice = Utils.detectTouchDevice();
    if (isTouchDevice) DOM.body.classList.add('touch');
    window.addEventListener('resize', Utils.debounce(Responsive.handleResize, 250));
    Responsive.checkWindowSize();
    Responsive.setupOnsiteFeatures();
    Responsive.setupTeamGrid();
  },
  handleResize() {
    Responsive.checkWindowSize();
    HeroSection.adjustHeight();
    HeroSection.adjustImages();
    HeroSection.setupMobileBehavior();
    initCards();
    Responsive.setupTeamGrid();
    Responsive.setupOnsiteFeatures();
    if (DOM.worksCarouselTrack) WorksCarousel.handleResize();
    HeaderScroll.requestUpdate();
  },
  checkWindowSize() {
    if (window.innerWidth > 767) Utils.closeAllMenus();
  },
  setupTeamGrid() {
    const grid = document.querySelector('.team-grid');
    if (!grid) return;
    if (Utils.checkMobile()) {
      grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
      grid.style.gap = '12px';
    } else if (Utils.checkTablet()) {
      grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
      grid.style.gap = '15px';
    } else {
      grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
      grid.style.gap = '20px';
    }
  },
  setupOnsiteFeatures() {
    DOM.onsiteFeatures?.forEach(f => {
      f.classList.toggle('touch', Utils.checkMobile() || Utils.checkTablet() || isTouchDevice);
    });
  }
};

// ==================== ФУНКЦИИ РЕНДЕРИНГА ДИНАМИЧЕСКИХ ДАННЫХ ====================

function renderWorks(works) {
  const track = DOM.worksCarouselTrack;
  const dotsContainer = DOM.worksDotsContainer;
  if (!track) return;

  track.innerHTML = '';
  if (dotsContainer) dotsContainer.innerHTML = '';

  if (works.length === 0) {
    track.innerHTML = '<div class="no-results"><i class="fas fa-images"></i><h3>Нет работ</h3><p>Скоро здесь появятся наши проекты</p></div>';
    return;
  }

  worksData = {};
  works.forEach(work => {
    worksData[work.id] = work;
  });

  works.forEach(work => {
    const slide = document.createElement('div');
    slide.className = 'works-carousel__slide';
    slide.dataset.project = work.id;

    const firstImage = work.images && work.images.length > 0 
      ? window.getImageUrl(work.images[0].image_path) 
      : '/assets/img/default.jpg';

    slide.innerHTML = `
      <div class="work-card">
        <div class="work-card__image">
          <img src="${firstImage}" alt="${work.vessel}" loading="lazy" decoding="async">
          <div class="work-card__overlay work-card__overlay--top">
            <div class="work-card__details work-card__details--desktop">
              <div class="work-card__detail">
                <i class="fas fa-ship"></i>
                <span>${work.vessel}</span>
              </div>
              <div class="work-card__detail">
                <i class="fas fa-tools"></i>
                <span>${work.repair_type}</span>
              </div>
              <div class="work-card__detail">
                <i class="fas fa-calendar-alt"></i>
                <span>${work.duration || ''}</span>
              </div>
            </div>
          </div>
          <button class="work-card__view-btn">Смотреть <i class="fas fa-arrow-right"></i></button>
        </div>
      </div>
    `;
    track.appendChild(slide);
  });

  works.forEach((_, index) => {
    const dot = document.createElement('span');
    dot.className = `works-carousel__dot ${index === 0 ? 'active' : ''}`;
    dot.dataset.slide = index;
    if (dotsContainer) dotsContainer.appendChild(dot);
  });

  DOM.worksCarouselSlides = document.querySelectorAll('.works-carousel__slide');
  DOM.worksCarouselDots = document.querySelectorAll('.works-carousel__dot');

  WorksCarousel.rebuild();
}

function renderTeam(members) {
  const grid = DOM.teamGrid;
  if (!grid) return;

  grid.innerHTML = '';

  members.forEach((member, index) => {
    const memberDiv = document.createElement('div');
    memberDiv.className = 'team-member';
    memberDiv.dataset.member = index + 1;

    const img = document.createElement('img');
    img.src = window.getImageUrl(member.image_path);
    img.alt = `Участник команды ${index + 1}`;
    img.loading = 'lazy';

    const photoDiv = document.createElement('div');
    photoDiv.className = 'team-member__photo';
    photoDiv.appendChild(img);

    memberDiv.appendChild(photoDiv);
    grid.appendChild(memberDiv);
  });

  DOM.teamMembers = document.querySelectorAll('.team-member');
  TeamModal.reinit();
}

/**
 * Обновление контактной информации на всех страницах
 * @param {Object} contacts - объект с полями phone, telegram_channel_url, telegram_chat_url, whatsapp_url, address
 */
function updateContacts(contacts) {
  if (!contacts) return;

  // Обновление телефона в шапке
  if (DOM.headerPhone) {
    DOM.headerPhone.textContent = contacts.phone;
    DOM.headerPhone.href = `tel:${contacts.phone.replace(/\D/g, '')}`;
  }

  // Обновление ссылок Telegram в выпадающем меню
  if (DOM.telegramChannelLink) {
    DOM.telegramChannelLink.href = contacts.telegram_channel_url;
  }
  if (DOM.telegramChatLink) {
    DOM.telegramChatLink.href = contacts.telegram_chat_url;
  }

  // Обновление ссылки WhatsApp в шапке
  if (DOM.whatsappLink) {
    DOM.whatsappLink.href = contacts.whatsapp_url;
  }

  // Обновление адреса в блоке контактов
  if (DOM.contactAddress) {
    DOM.contactAddress.innerHTML = contacts.address.replace(/\n/g, '<br>');
  }

  // Обновление телефона в блоке контактов (нижняя часть страницы)
  const contactPhoneLink = document.querySelector('.contact-item__content a[href^="tel:"]');
  if (contactPhoneLink) {
    contactPhoneLink.textContent = contacts.phone;
    contactPhoneLink.href = `tel:${contacts.phone.replace(/\D/g, '')}`;
  }

  // Обновление телефона в футере (id="footer-phone")
  const footerPhone = document.getElementById('footer-phone');
  if (footerPhone) {
    footerPhone.textContent = contacts.phone;
    footerPhone.href = `tel:${contacts.phone.replace(/\D/g, '')}`;
  }

  // Обновление ссылки Telegram в футере (id="footer-telegram") — ведёт на менеджера
  const footerTelegram = document.getElementById('footer-telegram');
  if (footerTelegram) {
    footerTelegram.href = contacts.telegram_chat_url;
  }

  // Обновление ссылки WhatsApp в футере (id="footer-whatsapp")
  const footerWhatsapp = document.getElementById('footer-whatsapp');
  if (footerWhatsapp) {
    footerWhatsapp.href = contacts.whatsapp_url;
  }

  // Обновление телефона в модальном окне товара
  const modalPhone = document.getElementById('modal-phone');
  const modalPhoneText = document.getElementById('modal-phone-text');
  if (modalPhone) {
    modalPhone.href = `tel:${contacts.phone.replace(/\D/g, '')}`;
  }
  if (modalPhoneText) {
    modalPhoneText.textContent = contacts.phone;
  }
  const modalTelegram = document.getElementById('modal-telegram');
  if (modalTelegram) {
    modalTelegram.href = contacts.telegram_chat_url;
  }
  const modalWhatsapp = document.getElementById('modal-whatsapp');
  if (modalWhatsapp) {
    modalWhatsapp.href = contacts.whatsapp_url;
  }

  // Обновление мобильного телефона
  const mobilePhone = document.querySelector('.mobile-phone-number');
  if (mobilePhone) {
    mobilePhone.textContent = contacts.phone;
    const mobilePhoneLink = mobilePhone.closest('a');
    if (mobilePhoneLink) mobilePhoneLink.href = `tel:${contacts.phone.replace(/\D/g, '')}`;
  }

  // Обновление ссылок в мобильной кнопке контактов (телефон+иконка)
  const mobileDropdownLinks = document.querySelectorAll('.mobile-contact-dropdown .mobile-contact-link');
  if (mobileDropdownLinks.length >= 2) mobileDropdownLinks[1].href = contacts.telegram_channel_url;
  if (mobileDropdownLinks.length >= 3) mobileDropdownLinks[2].href = contacts.telegram_chat_url;
  if (mobileDropdownLinks.length >= 4) mobileDropdownLinks[3].href = contacts.whatsapp_url;

  // Обновление кнопки "Больше наших работ в Telegram"
  const btnTelegram = document.querySelector('.btn-telegram');
  if (btnTelegram) btnTelegram.href = contacts.telegram_channel_url;
}

/**
 * Рендер карточек услуг на главной из API (main_page_services)
 */
function renderMainServiceCards(cards) {
  const grid = document.getElementById('servicesGridCards');
  if (!grid || !cards || !cards.length) return;

  grid.innerHTML = cards.map(card => {
    // Определяем ссылку
    let href = card.link_url || '/uslugi';
    if (card.direction_id && card.direction_name) {
      href = '/uslugi#' + window.getSectionId(card.direction_name);
    }

    // Медиа контент
    let mediaHtml = '';
    if (card.media_path) {
      const adminBase = 'https://admin.boostmarine.ru/';
      const mediaSrc = adminBase + card.media_path;
      if (card.media_type === 'video') {
        mediaHtml = `<video muted loop playsinline preload="none" data-src="${mediaSrc}"></video>`;
      } else {
        mediaHtml = `<img src="${mediaSrc}" alt="${card.title}" loading="lazy">`;
      }
    }

    // Описание
    const descHtml = card.subtitle ? `<div class="desc">${card.subtitle}</div>` : '';

    return `<a class="card ${card.card_class || 'square'}" href="${href}">
      ${mediaHtml}
      ${card.card_class === 'card-equipment' ? '<div class="card-overlay-dark"></div>' : ''}
      <div class="card-content">
        <div><div class="title">${card.title}</div>${descHtml}</div>
        <div class="card-action"><span class="details-btn">${card.btn_text || 'Перечень работ'}</span></div>
      </div>
    </a>`;
  }).join('');

  // Подключаем lazy video loading для новых элементов
  if ('IntersectionObserver' in window) {
    const videoObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const v = entry.target;
        if (entry.isIntersecting) {
          if (!v.src && v.dataset.src) v.src = v.dataset.src;
          v.play().catch(() => {});
        } else {
          v.pause();
        }
      });
    }, { rootMargin: '300px' });
    grid.querySelectorAll('video[data-src]').forEach(v => videoObserver.observe(v));
  }
}

/**
 * Обновление выпадающих меню услуг на основе карточек главной (main_services)
 * Если карточки пусты — fallback на directions из API
 */
function updateServicesDropdownsFromCards(cards, directions) {
  // Определяем источник: если есть карточки — берём названия из них
  let items = [];
  if (cards && cards.length) {
    items = cards.filter(c => c.direction_id || c.link_url).map(c => {
      let href = c.link_url || '/uslugi';
      if (c.direction_id && c.direction_name) {
        href = '/uslugi#' + window.getSectionId(c.direction_name);
      }
      return { name: c.title, href: href };
    });
  }

  // Fallback на directions
  if (!items.length && directions && directions.length) {
    items = directions.map(d => ({
      name: d.name,
      href: '/uslugi#' + window.getSectionId(d.name)
    }));
  }

  if (!items.length) return;

  // Десктоп dropdown
  const desktopMenu = document.querySelector('.dropdown-menu');
  if (desktopMenu) {
    desktopMenu.innerHTML = '<a href="/uslugi" class="dropdown-item">Все услуги</a>' +
      items.map(i => `<a href="${i.href}" class="dropdown-item">${i.name}</a>`).join('');
  }

  // Мобильное бургер-меню dropdown
  const mobileMenu = document.querySelector('.mobile-dropdown-menu');
  if (mobileMenu) {
    mobileMenu.innerHTML = '<a href="/uslugi" class="mobile-dropdown-item">Все услуги</a>' +
      items.map(i => `<a href="${i.href}" class="mobile-dropdown-item">${i.name}</a>`).join('');
  }
}

/**
 * Обновление выпадающих меню услуг из API (fallback)
 */
function updateServicesDropdowns(directions) {
  if (!directions || !directions.length) return;

  // Десктоп dropdown
  const desktopMenu = document.querySelector('.dropdown-menu');
  if (desktopMenu) {
    desktopMenu.innerHTML = '<a href="/uslugi" class="dropdown-item">Все услуги</a>' +
      directions.map(d => {
        const slug = window.getSectionId(d.name);
        return `<a href="/uslugi#${slug}" class="dropdown-item">${d.name}</a>`;
      }).join('');
  }

  // Мобильное бургер-меню dropdown
  const mobileMenu = document.querySelector('.mobile-dropdown-menu');
  if (mobileMenu) {
    mobileMenu.innerHTML = '<a href="/uslugi" class="mobile-dropdown-item">Все услуги</a>' +
      directions.map(d => {
        const slug = window.getSectionId(d.name);
        return `<a href="/uslugi#${slug}" class="mobile-dropdown-item">${d.name}</a>`;
      }).join('');
  }
}

// ==================== ЗАГРУЗКА ВСЕХ ДАННЫХ ====================
async function loadAllData() {
  Utils.showLoading(DOM.worksCarouselTrack);
  Utils.showLoading(DOM.teamGrid);

  try {
    const [works, team, contacts, services, mainServices] = await Promise.all([
      window.fetchAPI('works'),
      window.fetchAPI('team'),
      window.fetchAPI('contacts'),
      window.fetchAPI('services'),
      window.fetchAPI('main_services').catch(() => [])
    ]);

    renderWorks(works);
    renderTeam(team);
    updateContacts(contacts);

    // Рендерим карточки и обновляем дропдауны
    if (mainServices && mainServices.length) {
      renderMainServiceCards(mainServices);
      updateServicesDropdownsFromCards(mainServices, services);
    } else {
      updateServicesDropdowns(services);
    }

  } catch (error) {
    console.error('Failed to load data:', error);
    if (DOM.worksCarouselTrack) {
      DOM.worksCarouselTrack.innerHTML = '<div class="error">Не удалось загрузить работы. Попробуйте позже.</div>';
    }
    if (DOM.teamGrid) {
      DOM.teamGrid.innerHTML = '<div class="error">Не удалось загрузить команду. Попробуйте позже.</div>';
    }
  } finally {
    Utils.hideLoading(DOM.worksCarouselTrack);
    Utils.hideLoading(DOM.teamGrid);
  }
}

// ==================== БЕГУЩАЯ СТРОКА (TICKER) ====================
const TICKER_GAP_PX = 64;

function buildTickerStrip(text) {
  const strip = document.createElement('div');
  strip.className = 'site-ticker-strip';

  const probe = document.createElement('span');
  probe.className = 'site-ticker-item';
  probe.textContent = text;
  probe.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;';
  document.body.appendChild(probe);
  const itemWidth = probe.offsetWidth + TICKER_GAP_PX;
  document.body.removeChild(probe);

  const minWidth = Math.max(window.innerWidth * 2, 1200);
  const count = Math.max(3, Math.ceil(minWidth / itemWidth) + 1);

  for (let i = 0; i < count; i++) {
    const item = document.createElement('span');
    item.className = 'site-ticker-item';
    item.textContent = text;
    strip.appendChild(item);
  }
  return strip;
}

function applyTickerAnimation(track, strip) {
  const stripWidth = strip.offsetWidth;
  if (!stripWidth) return;
  const duration = Math.max(22, stripWidth / 50);
  track.style.setProperty('--ticker-duration', `${duration}s`);
}

async function initTicker() {
  try {
    const data = await window.fetchAPI('ticker');
    if (!data || !data.ticker_enabled || data.ticker_enabled === '0' || !data.ticker_text) return;

    const text = data.ticker_text.trim();
    if (!text) return;

    const ticker = document.createElement('div');
    ticker.className = 'site-ticker';
    ticker.setAttribute('aria-hidden', 'true');

    const track = document.createElement('div');
    track.className = 'site-ticker-track';
    track.style.setProperty('--ticker-gap', `${TICKER_GAP_PX}px`);

    const stripA = buildTickerStrip(text);
    const stripB = stripA.cloneNode(true);
    track.appendChild(stripA);
    track.appendChild(stripB);
    ticker.appendChild(track);
    document.body.appendChild(ticker);
    document.body.classList.add('has-ticker');

    requestAnimationFrame(() => {
      applyTickerAnimation(track, stripA);
      ticker.classList.add('visible');
    });
  } catch (e) {
    console.error('Ticker error:', e);
  }
}

// ==================== ИНИЦИАЛИЗАЦИЯ ПРИЛОЖЕНИЯ ====================
const App = {
  async init() {
    await loadAllData();
    initTicker();

    BurgerMenu.init();
    ContactMenu.init();
    WorkModal.init();
    TeamModal.init();
    SmoothScroll.init();
    WorksCarousel.init();
    HeaderScroll.init();
    HeroSection.init();
    VideoHandler.init();
    Responsive.init();
    TelegramDropdown.init();
    Dropdowns.init();
    WorkCards.init();
    initCards();
    Responsive.setupTeamGrid();
    Responsive.setupOnsiteFeatures();

    setTimeout(() => DOM.body.classList.add('loaded'), 100);
    console.log('Boost Marine website loaded successfully with dynamic data!');
  }
};

document.addEventListener('DOMContentLoaded', App.init);
window.addEventListener('load', () => console.log('Все ресурсы загружены'));  