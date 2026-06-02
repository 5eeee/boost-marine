// admin.js – Клиентские скрипты для административной панели Boost Marine
// Версия: 5.0 (поддержка новых блоков статистики, улучшенный экспорт, совместимость с ботом)
// Дата: 2026-02-17

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ==================== ПОДТВЕРЖДЕНИЕ УДАЛЕНИЯ ====================
    document.querySelectorAll('form.delete-form, form[onsubmit*="confirm"]').forEach(form => {
        if (form.hasAttribute('onsubmit')) {
            form.removeAttribute('onsubmit');
        }
        form.addEventListener('submit', function(e) {
            if (!confirm('Вы уверены, что хотите удалить эту запись? Это действие нельзя отменить.')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.delete-btn, .btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите удалить эту запись?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    document.querySelectorAll('.delete-image-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Удалить это изображение?')) {
                e.preventDefault();
            }
        });
    });

    // ==================== ПРЕДПРОСМОТР ИЗОБРАЖЕНИЙ ====================
    document.querySelectorAll('input[type="file"][multiple]').forEach(input => {
        input.addEventListener('change', function() {
            const previewContainer = this.closest('.form-group')?.querySelector('.image-preview');
            if (!previewContainer) return;
            previewContainer.innerHTML = '';

            const files = Array.from(this.files);
            files.forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;

                const reader = new FileReader();
                reader.onload = function(ev) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';
                    previewItem.style.cssText = 'width:60px; height:60px; border-radius:4px; overflow:hidden; border:1px solid var(--border);';
                    previewItem.innerHTML = `<img src="${ev.target.result}" alt="preview-${index}" style="width:100%; height:100%; object-fit:cover;">`;
                    previewContainer.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
        });
    });

    document.querySelectorAll('input[type="file"]:not([multiple])').forEach(input => {
        input.addEventListener('change', function() {
            const previewContainer = this.closest('.form-group')?.querySelector('.image-preview');
            if (!previewContainer) return;
            previewContainer.innerHTML = '';

            const file = this.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';
                    previewItem.style.cssText = 'width:100px; height:100px; border-radius:8px; overflow:hidden; border:1px solid var(--border);';
                    previewItem.innerHTML = `<img src="${ev.target.result}" alt="preview" style="width:100%; height:100%; object-fit:cover;">`;
                    previewContainer.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // ==================== ВАЛИДАЦИЯ ФОРМ ====================
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ==================== УПРАВЛЕНИЕ МОДАЛЬНЫМИ ОКНАМИ ====================
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });

    // ==================== ФУНКЦИИ ДЛЯ РАБОТЫ С РАЗДЕЛАМИ ====================
    window.editWork = function(work, images) {
        document.getElementById('workModalTitle').innerText = 'Редактировать работу';
        document.getElementById('workAction').value = 'edit';
        document.getElementById('workId').value = work.id;
        document.getElementById('workVessel').value = work.vessel;
        document.getElementById('workRepairType').value = work.repair_type;
        document.getElementById('workDuration').value = work.duration || '';
        document.getElementById('workDescription').value = work.description || '';
        document.getElementById('workSortOrder').value = work.sort_order;

        let html = '<div style="margin-bottom:10px"><strong>Существующие изображения:</strong><div style="display:flex; gap:10px; flex-wrap:wrap;">';
        if (images && images.length) {
            images.forEach(img => {
                html += `<div style="position:relative; width:60px; height:60px; border:1px solid var(--border); border-radius:4px;">
                            <img src="../${img.image_path}" style="width:100%; height:100%; object-fit:cover;">
                        </div>`;
            });
        } else {
            html += '<p>Нет изображений</p>';
        }
        html += '</div></div>';
        document.getElementById('existingImages').innerHTML = html;

        openModal('workModal');
    };

    window.openAddWorkModal = function() {
        document.getElementById('workModalTitle').innerText = 'Добавить работу';
        document.getElementById('workAction').value = 'add';
        document.getElementById('workId').value = '0';
        document.getElementById('workVessel').value = '';
        document.getElementById('workRepairType').value = '';
        document.getElementById('workDuration').value = '';
        document.getElementById('workDescription').value = '';
        document.getElementById('workSortOrder').value = '0';
        document.getElementById('existingImages').innerHTML = '';
        openModal('workModal');
    };

    window.openProductModal = function(mode, product = null, images = []) {
        if (mode === 'add') {
            document.getElementById('productModalTitle').innerText = 'Добавить товар';
            document.getElementById('productAction').value = 'add';
            document.getElementById('productId').value = '0';
            document.getElementById('productName').value = '';
            document.getElementById('productDescription').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productCategory').value = 'parts';
            document.getElementById('productSortOrder').value = '0';
            document.getElementById('productExistingImages').innerHTML = '';
        } else if (mode === 'edit' && product) {
            document.getElementById('productModalTitle').innerText = 'Редактировать товар';
            document.getElementById('productAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productPrice').value = product.price || '';
            document.getElementById('productCategory').value = product.category;
            document.getElementById('productSortOrder').value = product.sort_order;

            let html = '<div style="margin-bottom:10px"><strong>Существующие изображения:</strong><div style="display:flex; gap:10px; flex-wrap:wrap;">';
            if (images.length) {
                images.forEach(img => {
                    html += `<div style="position:relative; width:60px; height:60px; border:1px solid var(--border); border-radius:4px;">
                                <img src="../${img.image_path}" style="width:100%; height:100%; object-fit:cover;">
                            </div>`;
                });
            } else {
                html += '<p>Нет изображений</p>';
            }
            html += '</div></div>';
            document.getElementById('productExistingImages').innerHTML = html;
        }
        openModal('productModal');
    };

    window.editProduct = function(product, images) {
        openProductModal('edit', product, images);
    };

    window.openTeamModal = function(mode, member = null) {
        if (mode === 'add') {
            document.getElementById('teamModalTitle').innerText = 'Добавить участника';
            document.getElementById('teamAction').value = 'add';
            document.getElementById('teamId').value = '0';
            document.getElementById('teamSortOrder').value = '0';
            document.getElementById('teamCurrentImage').innerHTML = '';
            document.getElementById('teamImage').required = true;
        } else if (mode === 'edit' && member) {
            document.getElementById('teamModalTitle').innerText = 'Редактировать участника';
            document.getElementById('teamAction').value = 'edit';
            document.getElementById('teamId').value = member.id;
            document.getElementById('teamSortOrder').value = member.sort_order;
            let html = `<div><strong>Текущее фото:</strong><br><img src="../${member.image_path}" style="max-width:100px; max-height:100px; border-radius:8px; border:1px solid var(--border);"></div>`;
            document.getElementById('teamCurrentImage').innerHTML = html;
            document.getElementById('teamImage').required = false;
        }
        openModal('teamModal');
    };

    window.editTeam = function(member) {
        openTeamModal('edit', member);
    };

    // ==================== СТАТИСТИКА: ГРАФИКИ И ЭКСПОРТ ====================
    window.toggleChartType = function(chartId) {
        const chart = window[chartId];
        if (chart) {
            chart.config.type = chart.config.type === 'line' ? 'bar' : 'line';
            chart.update();
        }
    };

    // Экспорт canvas в изображение
    window.exportChartAsImage = function(chartId, format) {
        const canvas = document.getElementById(chartId);
        if (!canvas) return;
        const dataURL = canvas.toDataURL('image/' + format);
        const link = document.createElement('a');
        link.download = 'chart.' + format;
        link.href = dataURL;
        link.click();
    };

    // ==================== УЛУЧШЕНИЯ ИНТЕРФЕЙСА ====================
    document.querySelectorAll('.remove-image-checkbox').forEach(checkbox => {
        const label = checkbox.closest('label');
        if (label) {
            checkbox.addEventListener('change', function() {
                label.style.opacity = this.checked ? '0.6' : '1';
            });
        }
    });

    // Автоскрытие уведомлений через 5 секунд
    document.querySelectorAll('.message, .error').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        }, 5000);
    });

    // Фикс для аккордеона: корректный toggle
    document.querySelectorAll('.accordion-button').forEach(function(button) {
        button.addEventListener('click', function() {
            var targetId = this.getAttribute('data-bs-target') || this.getAttribute('href');
            if (!targetId) return;
            var target = document.querySelector(targetId);
            if (!target) return;
            var isExpanded = this.getAttribute('aria-expanded') === 'true';
            target.classList.toggle('show');
            this.setAttribute('aria-expanded', String(!isExpanded));
            this.classList.toggle('collapsed', isExpanded);
        });
    });

    // Горизонтальный скролл для изображений в таблицах (услуги)
    document.querySelectorAll('.services-container .images-preview').forEach(container => {
        let isDown = false;
        let startX;
        let scrollLeft;

        container.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - container.offsetLeft;
            scrollLeft = container.scrollLeft;
        });
        container.addEventListener('mouseleave', () => {
            isDown = false;
        });
        container.addEventListener('mouseup', () => {
            isDown = false;
        });
        container.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - container.offsetLeft;
            const walk = (x - startX) * 2;
            container.scrollLeft = scrollLeft - walk;
        });
    });

    // ==================== ДОПОЛНИТЕЛЬНО: КОПИРОВАНИЕ ССЫЛОК ЭКСПОРТА ====================
    document.querySelectorAll('.copy-export-link').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const link = this.getAttribute('data-link');
            if (link) {
                navigator.clipboard.writeText(link).then(() => {
                    alert('Ссылка скопирована в буфер обмена');
                }).catch(() => {
                    prompt('Скопируйте ссылку вручную:', link);
                });
            }
        });
    });

    // ==================== ИНИЦИАЛИЗАЦИЯ ====================
    console.log('Admin panel scripts loaded (v5.0)');
});