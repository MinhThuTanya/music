// Main JavaScript file 
document.addEventListener('DOMContentLoaded', function() {
    
    
    const dropdowns = document.querySelectorAll('.dropdown');
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    
    // Работа выпадающего меню на десктопе (при наведении)
    if (window.innerWidth > 768) {
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('mouseenter', function() {
                this.classList.add('active');
            });
            
            dropdown.addEventListener('mouseleave', function() {
                this.classList.remove('active');
            });
        });
    }
    
    // Работа выпадающего меню на мобильных (при клике)
    if (window.innerWidth <= 768) {
        dropdowns.forEach(dropdown => {
            const dropdownBtn = dropdown.querySelector('.dropdown-btn');
            
            dropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Закрываем все остальные dropdown
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.classList.remove('active');
                    }
                });
                
                // Открываем/закрываем текущий dropdown
                dropdown.classList.toggle('active');
            });
        });
    }
    
    // Закрытие dropdown при клике вне меню
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown') && !e.target.closest('.hamburger')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
        
        // Закрытие мобильного меню при клике на ссылку
        if (window.innerWidth <= 768 && e.target.closest('.nav-link')) {
            navMenu.classList.remove('active');
            hamburger.classList.remove('active');
        }
    });
    
    // Гамбургер меню
    hamburger.addEventListener('click', function() {
        this.classList.toggle('active');
        navMenu.classList.toggle('active');
        
        // Закрываем все dropdown при открытии мобильного меню
        if (!navMenu.classList.contains('active')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
    
   
    const contactBtn = document.getElementById('contact-btn');
    const heroContactBtn = document.getElementById('hero-contact-btn');
    const modal = document.getElementById('contactModal');
    const modalClose = document.getElementById('modalClose');
    
    function openModal() {
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }, 10);
    }
    
    function closeModal() {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }
    
    if (contactBtn) {
        contactBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openModal();
        });
    }
    
    if (heroContactBtn) {
        heroContactBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openModal();
        });
    }
    
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    
    // Закрытие модального окна при клике на фон
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Закрытие модального окна при нажатии Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });
    
    
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 100) {
            navbar.style.background = 'rgba(26, 26, 26, 0.98)';
            navbar.style.padding = '0.5rem 0';
        } else {
            navbar.style.background = 'rgba(26, 26, 26, 0.95)';
            navbar.style.padding = '1rem 0';
        }
    });
    
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            // Пропускаем ссылки выпадающего меню
            if (this.classList.contains('dropdown-btn') || 
                this.parentElement.classList.contains('dropdown-content')) {
                return;
            }
            
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
                
                // Закрываем мобильное меню после клика
                if (window.innerWidth <= 768) {
                    navMenu.classList.remove('active');
                    hamburger.classList.remove('active');
                }
            }
        });
    });
    
    
    window.addEventListener('resize', function() {
        // Закрываем все меню при изменении размера окна
        if (window.innerWidth > 768) {
            navMenu.classList.remove('active');
            hamburger.classList.remove('active');
        }
    });
    
    // ============= АНИМАЦИИ ПРИ СКРОЛЛЕ =============
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Наблюдаем за элементами для анимации
    document.querySelectorAll('.about-text, .stat-item, .discography-table').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
    
    
    const video = document.getElementById('headerVideo');
    if (video) {
        video.playbackRate = 0.8;
        
        video.addEventListener('error', function() {
            console.error('Ошибка загрузки видео');
            document.querySelector('.video-background').style.backgroundImage = 'url("assets/fallback-bg.jpg")';
        });
    }
    
    console.log('Chase Atlantic App загружен успешно!');
});