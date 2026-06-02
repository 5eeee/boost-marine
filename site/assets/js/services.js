// services.js – финальная версия с гарантированным отображением изображений и обновлением контактов
document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  const servicesContent = document.getElementById('servicesContent');
  let desktopNavLinks = document.querySelectorAll('.services-sidebar-nav__link');
  let mobileNavLinks = document.querySelectorAll('.mobile-services-nav__link');
  const mobileServicesNav = document.querySelector('.mobile-services-nav');

  if (!servicesContent) return;

  // Используем глобальную функцию getImageUrl, если она есть, иначе создаём свою
  const getFullImageUrl = window.getImageUrl || function(path) {
    if (!path) return '/assets/img/default.jpg';
    if (path.startsWith('http')) return path;
    // Используем API_BASE, если задан, иначе корень сайта
    const base = window.API_BASE || '/admin/';
    const cleanPath = path.replace(/^\//, '');
    return base + cleanPath;
  };

  // Загрузка данных
  async function loadServices() {
    servicesContent.innerHTML = '<div class="services-loading"><div class="services-loading__spinner"></div></div>';

    try {
      const data = await window.fetchAPI('services');
      renderServices(data);
    } catch (error) {
      console.error('Ошибка загрузки услуг:', error);
      servicesContent.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Не удалось загрузить услуги.</div>';
    }
  }

  // Отрисовка
  function renderServices(directions) {
    if (!directions || directions.length === 0) {
      servicesContent.innerHTML = '<div class="no-results"><i class="fas fa-tools"></i><h3>Услуги временно отсутствуют</h3></div>';
      return;
    }

    let html = '';

    directions.forEach((direction, dirIndex) => {
      const sectionClass = dirIndex % 2 === 0 ? '' : 'section-dark';
      const sectionId = getSectionId(direction.name);

      html += `<section id="${sectionId}" class="service-section ${sectionClass}">`;
      html += `<h2 class="service-section__title">${escapeHtml(direction.name)}</h2>`;

      if (direction.subsections?.length) {
        html += '<div class="service-chess-grid">';

        direction.subsections.forEach((sub, subIndex) => {
          const reverseClass = subIndex % 2 !== 0 ? 'reverse' : '';
          // Формируем URL изображения
          const imageUrl = getFullImageUrl(sub.image_path);

          html += `
            <div class="service-chess-card ${reverseClass}">
              <div class="service-chess-card__image image-border">
                <img src="${imageUrl}"
                     alt="${escapeHtml(sub.name)}"
                     onerror="this.onerror=null; this.src='/assets/img/default.jpg';">
              </div>
              <div class="service-chess-card__content">
                <h3 class="service-chess-card__title">${escapeHtml(sub.name)}</h3>
                <p class="service-chess-card__description">${escapeHtml(sub.description || '')}</p>
              </div>
            </div>
          `;
        });

        html += '</div>';
      } else {
        html += '<p class="service-section__subtitle">Нет подразделов</p>';
      }

      html += '</section>';
    });

    servicesContent.innerHTML = html;
    updateSidebarNav(directions);
    updateMobileServiceNav(directions);
    initNavigation();
    updateMobileNavPosition();
    observeSections();
  }

  function getSectionId(name) {
    return window.getSectionId ? window.getSectionId(name) : name.toLowerCase().replace(/\s+/g, '');
  }

  // Иконки для slug-ов
  const slugIcons = {
    repair: 'fas fa-tools', upgrade: 'fas fa-cogs', diagnostics: 'fas fa-search',
    purchase: 'fas fa-shopping-cart', preservation: 'fas fa-snowflake',
    jetski: 'fas fa-water', other: 'fas fa-wrench'
  };

  function updateSidebarNav(directions) {
    const list = document.querySelector('.services-sidebar-nav__list');
    if (!list) return;
    list.innerHTML = directions.map((d, i) => {
      const slug = getSectionId(d.name);
      const icon = slugIcons[slug] || 'fas fa-wrench';
      return `<li class="services-sidebar-nav__item">
        <a href="#${slug}" class="services-sidebar-nav__link${i === 0 ? ' active' : ''}">
          <i class="${icon}"></i><span>${escapeHtml(d.name)}</span>
        </a></li>`;
    }).join('');
    desktopNavLinks = document.querySelectorAll('.services-sidebar-nav__link');
  }

  function updateMobileServiceNav(directions) {
    const scroller = document.querySelector('.mobile-services-nav__scroller');
    if (!scroller) return;
    scroller.innerHTML = directions.map((d, i) => {
      const slug = getSectionId(d.name);
      return `<a href="#${slug}" class="mobile-services-nav__link${i === 0 ? ' active' : ''}">${escapeHtml(d.name)}</a>`;
    }).join('');
    mobileNavLinks = document.querySelectorAll('.mobile-services-nav__link');
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Навигация
  function initNavigation() {
    [...desktopNavLinks, ...mobileNavLinks].forEach(link => {
      link.removeEventListener('click', handleSmoothScroll);
      link.addEventListener('click', handleSmoothScroll);
    });
  }

  function handleSmoothScroll(e) {
    e.preventDefault();
    const targetId = this.getAttribute('href');
    if (!targetId || targetId === '#') return;
    const target = document.querySelector(targetId);
    if (!target) return;

    const headerHeight = document.querySelector('.header').offsetHeight;
    let offset = target.offsetTop - headerHeight - 20;
    if (window.innerWidth <= 992 && mobileServicesNav) {
      offset -= mobileServicesNav.offsetHeight;
    }
    window.scrollTo({ top: offset, behavior: 'smooth' });
    history.pushState(null, null, targetId);
    updateActiveLinks(targetId);
  }

  function updateActiveLinks(targetId) {
    desktopNavLinks.forEach(link => {
      link.classList.toggle('active', link.getAttribute('href') === targetId);
    });
    mobileNavLinks.forEach(link => {
      link.classList.toggle('active', link.getAttribute('href') === targetId);
    });
  }

  // IntersectionObserver для подсветки активного раздела
  let observer;
  function observeSections() {
    const sections = document.querySelectorAll('.service-section');
    if (!sections.length) return;
    if (observer) observer.disconnect();

    observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          updateActiveLinks('#' + entry.target.id);
        }
      });
    }, { rootMargin: '-120px 0px -40% 0px', threshold: 0 });

    sections.forEach(section => observer.observe(section));
  }

  // Коррекция положения мобильного меню
  function updateMobileNavPosition() {
    if (!mobileServicesNav || window.innerWidth > 992) return;
    const header = document.querySelector('.header');
    if (!header) return;
    const headerBottom = header.getBoundingClientRect().bottom;
    mobileServicesNav.style.top = headerBottom > 0 ? header.offsetHeight + 'px' : '0';
  }

  // Загрузка контактов
  async function loadContacts() {
    try {
      const contacts = await window.fetchAPI('contacts');
      if (typeof window.updateContacts === 'function') {
        window.updateContacts(contacts);
        console.log('Контакты обновлены на странице услуг:', contacts);
      } else {
        console.warn('Функция updateContacts не найдена на странице услуг');
      }
    } catch (e) {
      console.error('Ошибка загрузки контактов на странице услуг:', e);
    }
  }

  // Инициализация
  async function init() {
    await loadServices();
    await loadContacts();

    window.addEventListener('scroll', () => requestAnimationFrame(updateMobileNavPosition));
    window.addEventListener('resize', updateMobileNavPosition);

    if (window.location.hash) {
      setTimeout(() => {
        const target = document.querySelector(window.location.hash);
        if (target) {
          const headerHeight = document.querySelector('.header').offsetHeight;
          let top = target.offsetTop - headerHeight - 20;
          if (window.innerWidth <= 992 && mobileServicesNav) top -= mobileServicesNav.offsetHeight;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      }, 300);
    }
  }

  init();
});