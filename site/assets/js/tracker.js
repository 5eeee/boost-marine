// tracker.js – сбор статистики для Boost Marine
(function() {
    'use strict';

    // Генерируем или получаем session_id (хранится в localStorage)
    let sessionId = localStorage.getItem('boost_marine_session');
    if (!sessionId) {
        sessionId = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/[x]/g, () => (Math.random() * 16 | 0).toString(16));
        localStorage.setItem('boost_marine_session', sessionId);
    }

    // Данные о текущей странице
    const pageData = {
        url: window.location.href,
        title: document.title,
        referrer: document.referrer || null
    };

    // Функция отправки данных на сервер
    function sendData(type, data = {}) {
        const payload = {
            session_id: sessionId,
            type: type,
            data: data,
            timestamp: Date.now()
        };

        // Используем fetch с keepalive для надёжности при уходе со страницы
        fetch('https://admin.boostmarine.ru/track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true,
            mode: 'cors'
        }).catch(err => console.warn('Tracker error:', err));
    }

    // Отправляем просмотр страницы при загрузке
    window.addEventListener('load', () => {
        sendData('pageview', {
            url: pageData.url,
            title: pageData.title,
            referrer: pageData.referrer
        });
    });

    // Отслеживаем клики по ссылкам и кнопкам
    document.addEventListener('click', e => {
        const target = e.target.closest('a, button, [role="button"]');
        if (!target) return;

        const eventData = {
            tag: target.tagName,
            text: target.innerText?.trim() || '',
            href: target.href || '',
            selector: getSelector(target)
        };
        sendData('click', eventData);

        // Отслеживание кликов по телефону — цель Яндекс.Метрика + уведомление в Telegram
        if (target.href && target.href.startsWith('tel:')) {
            // Яндекс.Метрика reachGoal
            if (typeof ym === 'function') {
                ym(106842718, 'reachGoal', 'PHONE_CLICK');
            }
            // Уведомление в Telegram через бэкенд
            sendData('phone_click', {
                phone: target.href.replace('tel:', ''),
                page: window.location.href,
                referrer: document.referrer || ''
            });
        }
    });

    // Вспомогательная функция для получения CSS-селектора элемента
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

    // Отслеживаем уход со страницы (обновление визита)
    window.addEventListener('beforeunload', () => {
        sendData('visit_end');
    });
})();