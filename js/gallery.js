// Gallery Slider functionality - Fixed Version
class GallerySlider {
    constructor() {
        this.currentSlide = 0;
        this.slidesContainer = null;
        this.slides = [];
        this.totalSlides = 0;
        this.isAnimating = false;
        this.autoAdvanceInterval = null;
        this.touchStartX = 0;
        this.touchEndX = 0;
        
        this.init();
    }

    init() {
        this.cacheElements();
        this.setupEventListeners();
        this.renderPagination();
        this.updateSlider();
        this.startAutoAdvance();
        
        console.log('Gallery slider initialized with', this.totalSlides, 'slides');
    }

    cacheElements() {
        this.slidesContainer = document.querySelector('.slides-container');
        this.slides = document.querySelectorAll('.slide');
        this.totalSlides = this.slides.length;
        
        // Проверяем, что элементы найдены
        if (!this.slidesContainer) {
            console.error('Slides container not found');
            return;
        }
        
        if (this.slides.length === 0) {
            console.error('No slides found');
            return;
        }
    }

    setupEventListeners() {
        // Кнопки навигации
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.prevSlide());
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextSlide());
        }

        // Пагинация
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('pagination-dot')) {
                const index = parseInt(e.target.dataset.index);
                this.goToSlide(index);
            }
        });

        // Клавиатура
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.prevSlide();
            if (e.key === 'ArrowRight') this.nextSlide();
        });

        // Touch события
        this.setupTouchEvents();

        // Ресайз окна
        window.addEventListener('resize', () => this.handleResize());
    }

    setupTouchEvents() {
        const slider = document.querySelector('.gallery-slider');
        
        if (!slider) return;

        slider.addEventListener('touchstart', (e) => {
            this.touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        slider.addEventListener('touchend', (e) => {
            this.touchEndX = e.changedTouches[0].screenX;
            this.handleSwipe();
        }, { passive: true });
    }

    handleSwipe() {
        const swipeThreshold = 50;
        const diff = this.touchStartX - this.touchEndX;

        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                this.nextSlide();
            } else {
                this.prevSlide();
            }
        }
    }

    handleResize() {
        // Обновляем позицию слайдера при ресайзе
        this.updateSlider();
    }

    nextSlide() {
        if (this.isAnimating) return;
        
        this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
        this.updateSlider();
        this.restartAutoAdvance();
    }

    prevSlide() {
        if (this.isAnimating) return;
        
        this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.updateSlider();
        this.restartAutoAdvance();
    }

    goToSlide(index) {
        if (this.isAnimating || index < 0 || index >= this.totalSlides) return;
        
        this.currentSlide = index;
        this.updateSlider();
        this.restartAutoAdvance();
    }

    updateSlider() {
        if (!this.slidesContainer || this.slides.length === 0) return;
        
        this.isAnimating = true;
        
        const slideWidth = 100; // 100% per slide
        const translateX = -this.currentSlide * slideWidth;
        
        this.slidesContainer.style.transform = `translateX(${translateX}%)`;
        
        this.updateControls();
        this.renderPagination();
        
        // Сбрасываем анимацию
        setTimeout(() => {
            this.isAnimating = false;
        }, 500);
    }

    updateControls() {
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');
        const currentSlideEl = document.querySelector('.current-slide');
        const totalSlidesEl = document.querySelector('.total-slides');

        if (prevBtn) {
            prevBtn.disabled = this.currentSlide === 0;
        }
        
        if (nextBtn) {
            nextBtn.disabled = this.currentSlide === this.totalSlides - 1;
        }
        
        if (currentSlideEl) {
            currentSlideEl.textContent = this.currentSlide + 1;
        }
        
        if (totalSlidesEl) {
            totalSlidesEl.textContent = this.totalSlides;
        }
    }

    renderPagination() {
        const paginationDots = document.querySelector('.pagination-dots');
        
        if (!paginationDots) return;
        
        paginationDots.innerHTML = '';
        
        for (let i = 0; i < this.totalSlides; i++) {
            const dot = document.createElement('div');
            dot.className = `pagination-dot ${i === this.currentSlide ? 'active' : ''}`;
            dot.dataset.index = i;
            paginationDots.appendChild(dot);
        }
    }

    startAutoAdvance() {
        // Автопереключение каждые 5 секунд
        this.autoAdvanceInterval = setInterval(() => {
            this.nextSlide();
        }, 5000);
    }

    restartAutoAdvance() {
        if (this.autoAdvanceInterval) {
            clearInterval(this.autoAdvanceInterval);
            this.startAutoAdvance();
        }
    }

    stopAutoAdvance() {
        if (this.autoAdvanceInterval) {
            clearInterval(this.autoAdvanceInterval);
        }
    }

    // Публичные методы для внешнего управления
    play() {
        this.startAutoAdvance();
    }

    pause() {
        this.stopAutoAdvance();
    }

    destroy() {
        this.stopAutoAdvance();
        // Удаляем все обработчики событий
        const events = ['click', 'touchstart', 'touchend', 'keydown', 'resize'];
        events.forEach(event => {
            document.removeEventListener(event, this.boundHandlers[event]);
        });
    }
}

// Инициализация галереи при загрузке DOM
document.addEventListener('DOMContentLoaded', () => {
    // Ждем полной загрузки всех изображений
    window.addEventListener('load', () => {
        const gallerySlider = new GallerySlider();
        
        // Делаем галерею доступной глобально для отладки
        window.gallerySlider = gallerySlider;
        
        // Добавляем обработчики для паузы при наведении
        const galleryElement = document.querySelector('.gallery-slider');
        if (galleryElement) {
            galleryElement.addEventListener('mouseenter', () => {
                gallerySlider.pause();
            });
            
            galleryElement.addEventListener('mouseleave', () => {
                gallerySlider.play();
            });
        }
        
        console.log('Gallery slider ready');
    });
});