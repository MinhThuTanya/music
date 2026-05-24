// config.js – автоматическое определение пути к api.php
const API_BASE = (() => {
    // Получаем путь к текущей странице, например: /music/index.html
    const path = window.location.pathname;
    // Находим последний слеш – это разделитель между папкой и именем файла
    const lastSlash = path.lastIndexOf('/');
    // Отрезаем имя файла, оставляем путь к папке (с завершающим слешем)
    const baseDir = path.substring(0, lastSlash + 1);
    // Добавляем api.php
    return baseDir + 'api.php';
})();

// Для отладки (можно убрать)
console.log('API_BASE:', API_BASE);