// tracker.js – сбор расширенной статистики для Boost Marine (версия 4.1)
(function() {
    'use strict';

    // ==================== ГЕНЕРАЦИЯ ИДЕНТИФИКАТОРА СЕССИИ ====================
    let sessionId = localStorage.getItem('boost_marine_session');
    if (!sessionId) {
        sessionId = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/[x]/g, () => (Math.random() * 16 | 0).toString(16));
        localStorage.setItem('boost_marine_session', sessionId);
    }

    // ==================== ОПРЕДЕЛЕНИЕ ПАРАМЕТРОВ УСТРОЙСТВА ====================
    function getDeviceType() {
        const ua = navigator.userAgent;
        if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) return 'tablet';
        if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) return 'mobile';
        return 'desktop';
    }

    function getOS() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Win') !== -1) return 'Windows';
        if (ua.indexOf('Mac') !== -1) return 'macOS';
        if (ua.indexOf('Linux') !== -1) return 'Linux';
        if (ua.indexOf('Android') !== -1) return 'Android';
        if (ua.indexOf('iOS') !== -1 || ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) return 'iOS';
        return 'unknown';
    }

    function getBrowser() {
        const ua = navigator.userAgent;
        if (ua.indexOf('Chrome') !== -1 && ua.indexOf('Edg') === -1) return 'Chrome';
        if (ua.indexOf('Firefox') !== -1) return 'Firefox';
        if (ua.indexOf('Safari') !== -1 && ua.indexOf('Chrome') === -1) return 'Safari';
        if (ua.indexOf('Edg') !== -1) return 'Edge';
        if (ua.indexOf('MSIE') !== -1 || ua.indexOf('Trident/') !== -1) return 'IE';
        return 'unknown';
    }

    function getScreenResolution() {
        return screen.width + 'x' + screen.height;
    }

    function getLanguage() {
        return navigator.language || navigator.userLanguage || 'unknown';
    }

    function getUTMParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            source: params.get('utm_source') || '',
            medium: params.get('utm_medium') || '',
            campaign: params.get('utm_campaign') || '',
            term: params.get('utm_term') || '',
            content: params.get('utm_content') || ''
        };
    }

    // ==================== ОТПРАВКА ДАННЫХ НА СЕРВЕР ====================
    function sendData(type, data = {}) {
        const payload = {
            session_id: sessionId,
            type: type,
            data: data,
            timestamp: Date.now(),
            device: type === 'pageview' ? {
                device_type: getDeviceType(),
                browser: getBrowser(),
                os: getOS(),
                screen_resolution: getScreenResolution(),
                language: getLanguage()
            } : undefined
        };

        if (type === 'pageview') {
            const utm = getUTMParams();
            if (utm.source) payload.utm = utm;
        }

        // Используем абсолютный URL для кросс-доменных запросов
        fetch('https://admin.boostmarine.ru/track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true,
            mode: 'cors'
        }).catch(err => console.warn('Tracker error:', err));
    }

    // ==================== ОТПРАВКА ПРОСМОТРА СТРАНИЦЫ ====================
    window.addEventListener('load', () => {
        sendData('pageview', {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer || null
        });
    });

    // ==================== ОТСЛЕЖИВАНИЕ КЛИКОВ ====================
    document.addEventListener('click', e => {
        const target = e.target.closest('a, button, [role="button"], input[type="submit"], .btn');
        if (!target) return;

        const eventData = {
            tag: target.tagName,
            text: target.innerText?.trim() || target.value || '',
            href: target.href || '',
            selector: getSelector(target)
        };
        sendData('click', eventData);
    });

    // ==================== ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ ДЛЯ СЕЛЕКТОРА ====================
    function getSelector(el) {
        if (el.id) return '#' + el.id;
        let path = [];
        while (el.parentElement) {
            let selector = el.tagName.toLowerCase();
            if (el.className) {
                selector += '.' + Array.from(el.classList).join('.');
            }
            path.unshift(selector);
            el = el.parentElement;
        }
        return path.join(' > ');
    }

    // ==================== ОТСЛЕЖИВАНИЕ УХОДА СО СТРАНИЦЫ ====================
    window.addEventListener('beforeunload', () => {
        sendData('visit_end');
    });

    // ==================== ПУБЛИЧНЫЙ МЕТОД ДЛЯ КАСТОМНЫХ СОБЫТИЙ ====================
    window.trackEvent = function(eventName, eventData = {}) {
        sendData('custom_event', {
            event_name: eventName,
            ...eventData
        });
    };

    console.log('Tracker initialized. Session ID:', sessionId);
})();