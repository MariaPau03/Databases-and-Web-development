// =============================================
// MARIA PAU — Portfolio Script
// =============================================

// 1. Theme Toggle
const toggleBtn = document.getElementById('theme-toggle');
const themeIcon = toggleBtn?.querySelector('i');
const themeLabel = toggleBtn?.querySelector('span');

if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('light-theme');
        const isLight = document.body.classList.contains('light-theme');
        if (themeIcon) themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
        if (themeLabel) themeLabel.textContent = isLight ? 'Light Mode' : 'Dark Mode';
    });
}

// 2. Slideshow with dots
let currentSlide = 0;
let slideshowTimer;

function initSlideshow() {
    const track = document.getElementById('slide-track');
    const slides = document.querySelectorAll('.mySlides');
    const dotsContainer = document.getElementById('slide-dots');

    if (!track || slides.length === 0) return;

    // Create dots
    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.className = 'dot' + (i === 0 ? ' active' : '');
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer?.appendChild(dot);
    });

    function goToSlide(index) {
        currentSlide = index;
        track.style.transform = `translateX(-${currentSlide * 100}%)`;
        document.querySelectorAll('.dot').forEach((d, i) => {
            d.classList.toggle('active', i === currentSlide);
        });
        // Reset timer
        clearInterval(slideshowTimer);
        slideshowTimer = setInterval(nextSlide, 8000);
    }

    function nextSlide() {
        goToSlide((currentSlide + 1) % slides.length);
    }

    slideshowTimer = setInterval(nextSlide, 8000);
}

// 3. Scroll Reveal
function initScrollReveal() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
}

// 4. Active nav link on scroll
function initNavHighlight() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                navLinks.forEach(link => {
                    const href = link.getAttribute('href').replace('#', '');
                    link.classList.toggle('active', href === id);
                });
            }
        });
    }, { threshold: 0.4 });

    sections.forEach(s => observer.observe(s));
}

// 5. Copy email
function copyEmail() {
    const emailEl = document.getElementById('email-address');
    const hint = document.querySelector('.copy-hint');
    if (!emailEl || !hint) return;

    const email = emailEl.innerText.trim();
    navigator.clipboard.writeText(email).then(() => {
        hint.innerHTML = '<i class="fas fa-check"></i> Copied!';
        hint.style.color = '#4fc4cf';
        setTimeout(() => {
            hint.innerHTML = '<i class="fas fa-copy"></i> Click to copy';
            hint.style.color = '';
        }, 2500);
    }).catch(() => {
        // Fallback
        const temp = document.createElement('textarea');
        temp.value = email;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
    });
}

// 6. Subtle parallax on hero
function initParallax() {
    const hero = document.querySelector('.hero-full');
    if (!hero) return;
    window.addEventListener('scroll', () => {
        const y = window.scrollY;
        hero.style.backgroundPositionY = `calc(50% + ${y * 0.3}px)`;
    }, { passive: true });
}

// Init
window.addEventListener('DOMContentLoaded', () => {
    initSlideshow();
    initScrollReveal();
    initNavHighlight();
    initParallax();
});
