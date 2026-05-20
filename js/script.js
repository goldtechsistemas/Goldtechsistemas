// Mobile menu toggle
function toggleMenu() {
    const menu = document.getElementById('mobileMenu');
    const btn = document.querySelector('.hamburger');
    menu.classList.toggle('open');
    btn.classList.toggle('is-active');
}

// Scroll reveal animation
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Counter animation
function animateCounter(el) {
    const target = parseInt(el.dataset.target);
    const suffix = el.dataset.suffix || '+';
    const duration = 1800;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = Math.floor(current) + (current >= target ? suffix : '');
        if (current >= target) clearInterval(timer);
    }, 16);
}

const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            animateCounter(e.target);
            counterObserver.unobserve(e.target);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('[data-target]').forEach(el => counterObserver.observe(el));

// Form submission - Formspree integration
document.querySelector('form').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.textContent = 'Enviando...';
    btn.disabled = true;
});

// Carousel swipe/drag functionality
const carousels = document.querySelectorAll('.carousel-track, .clients-carousel-track');

carousels.forEach(carousel => {
    let isDown = false;
    let startX;
    let scrollLeft;

    carousel.addEventListener('mousedown', (e) => {
        isDown = true;
        carousel.style.animationPlayState = 'paused';
        startX = e.pageX - carousel.offsetLeft;
        scrollLeft = carousel.parentElement.scrollLeft;
        carousel.style.cursor = 'grabbing';
    });

    carousel.addEventListener('mouseleave', () => {
        isDown = false;
        carousel.style.animationPlayState = 'running';
        carousel.style.cursor = 'grab';
    });

    carousel.addEventListener('mouseup', () => {
        isDown = false;
        carousel.style.animationPlayState = 'running';
        carousel.style.cursor = 'grab';
    });

    carousel.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - carousel.offsetLeft;
        const walk = (x - startX) * 1;
        carousel.parentElement.scrollLeft = scrollLeft - walk;
    });

    // Touch events for mobile
    carousel.addEventListener('touchstart', (e) => {
        isDown = true;
        carousel.style.animationPlayState = 'paused';
        startX = e.touches[0].pageX - carousel.offsetLeft;
        scrollLeft = carousel.parentElement.scrollLeft;
    });

    carousel.addEventListener('touchend', () => {
        isDown = false;
        carousel.style.animationPlayState = 'running';
    });

    carousel.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        const x = e.touches[0].pageX - carousel.offsetLeft;
        const walk = (x - startX) * 1;
        carousel.parentElement.scrollLeft = scrollLeft - walk;
    });
});

// Phone mask formatting
function mascaraTelefone(input) {
    let value = input.value.replace(/\D/g, '');
    let formattedValue = '';

    if (value.length > 0) {
        formattedValue += '(' + value.substring(0, 2);
    }
    if (value.length > 2) {
        formattedValue += ') ' + value.substring(2, 7);
    }
    if (value.length > 7) {
        formattedValue += '-' + value.substring(7, 11);
    }
    input.value = formattedValue;
}

// Navigation scroll effect
window.addEventListener('scroll', () => {
    const nav = document.getElementById('mainNav');
    if (window.scrollY > 50) {
        nav.style.borderBottomColor = 'rgba(201,162,39,0.15)';
    } else {
        nav.style.borderBottomColor = 'rgba(255,255,255,0.06)';
    }
});

// Hero section reveal on load
document.querySelectorAll('#hero .reveal').forEach(el => {
    setTimeout(() => el.classList.add('visible'), 100);
});

// FAQ Accordion
document.querySelectorAll('.faq-item').forEach(item => {
    item.addEventListener('click', () => {
        document.querySelectorAll('.faq-item.active').forEach(activeItem => {
            if (activeItem !== item) {
                activeItem.classList.remove('active');
            }
        });
        item.classList.toggle('active');
    });
});
