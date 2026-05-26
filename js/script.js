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

// ==========================================
// Carrossel Infinito e Arrastável (Sem Pulos)
// ==========================================
const carousels = document.querySelectorAll('.carousel-track, .clients-carousel-track');

carousels.forEach(track => {
    // 1. Duplica os itens para criar a ilusão de infinito
    const items = Array.from(track.children);
    items.forEach(item => {
        const clone = item.cloneNode(true);
        track.appendChild(clone);
    });

    const parent = track.parentElement; // O container que tem o overflow: hidden
    let isDown = false;
    let startX;
    let scrollLeft;
    let animationId;

    // Velocidade do scroll automático (aumente para mais rápido)
    const autoScrollSpeed = 1;

    // 2. Função de Scroll Automático Suave
    function autoScroll() {
        if (!isDown) {
            parent.scrollLeft += autoScrollSpeed;

            // Se rolou até a metade (onde começam os clones), volta para o início sem o usuário notar
            if (parent.scrollLeft >= (track.scrollWidth / 2)) {
                parent.scrollLeft = 0;
            }
        }
        animationId = requestAnimationFrame(autoScroll);
    }

    // Inicia o movimento
    autoScroll();

    // 3. Controles de Mouse (Desktop)
    parent.addEventListener('mousedown', (e) => {
        isDown = true;
        parent.style.cursor = 'grabbing';
        startX = e.pageX - parent.offsetLeft;
        scrollLeft = parent.scrollLeft;
        cancelAnimationFrame(animationId); // Pausa o scroll automático
    });

    parent.addEventListener('mouseleave', () => {
        if(isDown) {
            isDown = false;
            parent.style.cursor = 'grab';
            autoScroll(); // Retoma o scroll automático
        }
    });

    parent.addEventListener('mouseup', () => {
        isDown = false;
        parent.style.cursor = 'grab';
        autoScroll(); // Retoma o scroll automático
    });

    parent.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - parent.offsetLeft;
        const walk = (x - startX) * 1.5; // O 1.5 é a sensibilidade do arrasto
        let newScrollLeft = scrollLeft - walk;

        // Lida com o loop infinito se o usuário arrastar rápido demais para os lados
        if (newScrollLeft >= (track.scrollWidth / 2)) {
            newScrollLeft -= (track.scrollWidth / 2);
            startX = e.pageX - parent.offsetLeft;
            scrollLeft = newScrollLeft;
        } else if (newScrollLeft <= 0) {
            newScrollLeft += (track.scrollWidth / 2);
            startX = e.pageX - parent.offsetLeft;
            scrollLeft = newScrollLeft;
        }

        parent.scrollLeft = newScrollLeft;
    });

    // 4. Controles de Touch (Mobile)
    parent.addEventListener('touchstart', (e) => {
        isDown = true;
        startX = e.touches[0].pageX - parent.offsetLeft;
        scrollLeft = parent.scrollLeft;
        cancelAnimationFrame(animationId);
    }, { passive: true });

    parent.addEventListener('touchend', () => {
        isDown = false;
        autoScroll();
    });

    parent.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        const x = e.touches[0].pageX - parent.offsetLeft;
        const walk = (x - startX) * 1.5;
        let newScrollLeft = scrollLeft - walk;

        if (newScrollLeft >= (track.scrollWidth / 2)) {
            newScrollLeft -= (track.scrollWidth / 2);
            startX = e.touches[0].pageX - parent.offsetLeft;
            scrollLeft = newScrollLeft;
        } else if (newScrollLeft <= 0) {
            newScrollLeft += (track.scrollWidth / 2);
            startX = e.touches[0].pageX - parent.offsetLeft;
            scrollLeft = newScrollLeft;
        }

        parent.scrollLeft = newScrollLeft;
    }, { passive: true });
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
