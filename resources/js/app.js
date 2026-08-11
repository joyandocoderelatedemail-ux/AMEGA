import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Intersection Observer for Scroll Animations (.animate-on-scroll)
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.05
    };

    const observer = new IntersectionObserver((entries, observerInstance) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observerInstance.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    if (animatedElements.length > 0) {
        animatedElements.forEach(el => {
            observer.observe(el);
        });
    }

    // Fallback: If elements are still not animated after 500ms, reveal them
    setTimeout(() => {
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            el.classList.add('animate-in');
        });
    }, 500);

    // 2. Navbar Scroll Behavior
    const navbar = document.getElementById('navbar');
    if (navbar) {
        const checkScroll = () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        };
        checkScroll();
        window.addEventListener('scroll', checkScroll);
    }

    // 3. Back to Top Button
    const backToTopBtn = document.getElementById('back-to-top');
    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTopBtn.classList.remove('opacity-0', 'invisible');
                backToTopBtn.classList.add('opacity-100', 'visible');
            } else {
                backToTopBtn.classList.add('opacity-0', 'invisible');
                backToTopBtn.classList.remove('opacity-100', 'visible');
            }
        });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // 4. Mobile Menu Toggle
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        document.querySelectorAll('.mobile-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
            });
        });
    }

    // 5. Smooth Scroll Handler for internal hash links with header offset
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const navHeight = 80;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - navHeight;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // 6. Initialize Lucide Icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // 7. Auto-Scrolling Testimonial Carousel
    const testimonialTrack = document.getElementById('testimonial-track');
    const prevBtn = document.getElementById('testimonial-prev');
    const nextBtn = document.getElementById('testimonial-next');

    if (testimonialTrack) {
        let scrollTimer = null;
        const intervalTime = 1500;

        const getScrollStep = () => {
            const firstCard = testimonialTrack.firstElementChild;
            return firstCard ? firstCard.offsetWidth + 24 : 360;
        };

        const moveNext = () => {
            const step = getScrollStep();
            const maxScroll = testimonialTrack.scrollWidth - testimonialTrack.clientWidth;
            if (testimonialTrack.scrollLeft >= maxScroll - 15) {
                testimonialTrack.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                testimonialTrack.scrollBy({ left: step, behavior: 'smooth' });
            }
        };

        const movePrev = () => {
            const step = getScrollStep();
            if (testimonialTrack.scrollLeft <= 15) {
                testimonialTrack.scrollTo({ left: testimonialTrack.scrollWidth, behavior: 'smooth' });
            } else {
                testimonialTrack.scrollBy({ left: -step, behavior: 'smooth' });
            }
        };

        const startAutoPlay = () => {
            stopAutoPlay();
            scrollTimer = setInterval(moveNext, intervalTime);
        };

        const stopAutoPlay = () => {
            if (scrollTimer) {
                clearInterval(scrollTimer);
                scrollTimer = null;
            }
        };

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                moveNext();
                startAutoPlay();
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                movePrev();
                startAutoPlay();
            });
        }

        testimonialTrack.addEventListener('mouseenter', stopAutoPlay);
        testimonialTrack.addEventListener('mouseleave', startAutoPlay);
        testimonialTrack.addEventListener('touchstart', stopAutoPlay, { passive: true });
        testimonialTrack.addEventListener('touchend', startAutoPlay, { passive: true });

        startAutoPlay();
    }
});
