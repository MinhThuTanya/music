const API_BASE = (() => {
    const path = window.location.pathname;
    if (path.includes('/project/')) return '/project/api.php';
    return '/api.php';
})();