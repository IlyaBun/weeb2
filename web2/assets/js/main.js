/**
 * Основной JavaScript системы оценки успеваемости ПолесГУ
 * Логика графиков, модальных окон, взаимодействий
 */

// ============================================
// ГЛОБАЛЬНЫЕ ФУНКЦИИ
// ============================================

/**
 * Переключение мобильного меню
 */
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

/**
 * Закрытие модального окна при клике вне его
 */
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });
});

// ============================================
// ГРАФИКИ CHART.JS
// ============================================

/**
 * Инициализация графиков на главной панели
 */
document.addEventListener('DOMContentLoaded', function() {
    // Pie Chart - Распределение оценок
    const pieChartCanvas = document.getElementById('gradePieChart');
    if (pieChartCanvas && typeof gradeDistribution !== 'undefined') {
        new Chart(pieChartCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Неуд. (1-3)', 'Удовл. (4-6)', 'Хорошо (7-8)', 'Отлично (9-10)'],
                datasets: [{
                    data: [
                        gradeDistribution.poor || 0,
                        gradeDistribution.satisfactory || 0,
                        gradeDistribution.good || 0,
                        gradeDistribution.excellent || 0
                    ],
                    backgroundColor: [
                        '#e74c3c',
                        '#f1c40f',
                        '#3498db',
                        '#2ecc71'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Bar Chart - Рейтинг групп
    const barChartCanvas = document.getElementById('groupBarChart');
    if (barChartCanvas && typeof groupRating !== 'undefined') {
        new Chart(barChartCanvas, {
            type: 'bar',
            data: {
                labels: groupRating.map(g => g.name),
                datasets: [{
                    label: 'Средний балл',
                    data: groupRating.map(g => parseFloat(g.avg_grade)),
                    backgroundColor: 'rgba(78, 84, 200, 0.8)',
                    borderRadius: 6,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Средний балл: ${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 4,
                        max: 10,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Line Chart - Динамика успеваемости
    const lineChartCanvas = document.getElementById('dynamicsLineChart');
    if (lineChartCanvas && typeof monthlyDynamics !== 'undefined') {
        new Chart(lineChartCanvas, {
            type: 'line',
            data: {
                labels: monthlyDynamics.map(d => {
                    const month = d.month.split('-');
                    return month[1] + '.' + month[0].substr(2);
                }),
                datasets: [{
                    label: 'Средний балл',
                    data: monthlyDynamics.map(d => parseFloat(d.avg_grade)),
                    borderColor: '#4e54c8',
                    backgroundColor: 'rgba(78, 84, 200, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#4e54c8',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Средний балл: ${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 4,
                        max: 10,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Pie Chart для аналитики
    const analyticsPieChart = document.getElementById('analyticsPieChart');
    if (analyticsPieChart && typeof distributionData !== 'undefined') {
        new Chart(analyticsPieChart, {
            type: 'pie',
            data: {
                labels: ['Неуд. (1-3)', 'Удовл. (4-6)', 'Хорошо (7-8)', 'Отлично (9-10)'],
                datasets: [{
                    data: [
                        distributionData.poor || 0,
                        distributionData.satisfactory || 0,
                        distributionData.good || 0,
                        distributionData.excellent || 0
                    ],
                    backgroundColor: [
                        '#e74c3c',
                        '#f1c40f',
                        '#3498db',
                        '#2ecc71'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});

// ============================================
// УЛУЧШЕНИЯ UX
// ============================================

/**
 * Авто-скрытие сайдбара на мобильных при клике вне его
 */
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    }
});

/**
 * Подтверждение перед выходом при несохраненных изменениях
 */
let hasUnsavedChanges = false;

document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('change', () => {
        hasUnsavedChanges = true;
    });
});

document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
        hasUnsavedChanges = false;
    });
});

window.addEventListener('beforeunload', (e) => {
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
    }
});

/**
 * Плавная прокрутка к якорям
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href !== '#' && document.querySelector(href)) {
            e.preventDefault();
            document.querySelector(href).scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

/**
 * Tooltip инициализация (если нужно)
 */
document.querySelectorAll('[data-tooltip]').forEach(element => {
    element.addEventListener('mouseenter', function(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 9999;
            pointer-events: none;
        `;
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
        
        this._tooltip = tooltip;
    });
    
    element.addEventListener('mouseleave', function() {
        if (this._tooltip) {
            this._tooltip.remove();
            this._tooltip = null;
        }
    });
});

/**
 * Уведомления через SweetAlert2
 */
function showNotification(type, title, message) {
    Swal.fire({
        icon: type,
        title: title,
        text: message,
        confirmButtonColor: '#4e54c8',
        timer: type === 'success' ? 2000 : null,
        showConfirmButton: type !== 'success'
    });
}

/**
 * Форматирование чисел с разделителями
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
}

/**
 * Дебаунс функция для поиска
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Экспорт функций для глобального доступа
window.toggleMobileMenu = toggleMobileMenu;
window.showNotification = showNotification;
window.formatNumber = formatNumber;

console.log('%c🎓 Система оценки успеваемости ПолесГУ', 'color: #4e54c8; font-size: 16px; font-weight: bold;');
console.log('%cЗагрузка завершена успешно!', 'color: #2ecc71; font-size: 12px;');
