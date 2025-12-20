/**
 * Assets Optimization JavaScript
 * 
 * JavaScript for optimized asset loading and Core Web Vitals monitoring
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

(function() {
    'use strict';
    
    // Core Web Vitals monitoring
    const coreWebVitals = {
        lcp: null,
        cls: null,
        fid: null
    };
    
    // Initialize Core Web Vitals monitoring
    function initCoreWebVitals() {
        // LCP (Largest Contentful Paint)
        if ('PerformanceObserver' in window) {
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                coreWebVitals.lcp = lastEntry.startTime;
                updateLCPIndicator(lastEntry.startTime);
            });
            
            try {
                lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            } catch (e) {
                console.warn('LCP monitoring not supported');
            }
            
            // CLS (Cumulative Layout Shift)
            const clsObserver = new PerformanceObserver((list) => {
                let clsValue = 0;
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                }
                coreWebVitals.cls = clsValue;
                updateCLSIndicator(clsValue);
            });
            
            try {
                clsObserver.observe({ entryTypes: ['layout-shift'] });
            } catch (e) {
                console.warn('CLS monitoring not supported');
            }
            
            // FID (First Input Delay)
            const fidObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                for (const entry of entries) {
                    coreWebVitals.fid = entry.processingStart - entry.startTime;
                    updateFIDIndicator(coreWebVitals.fid);
                }
            });
            
            try {
                fidObserver.observe({ entryTypes: ['first-input'] });
            } catch (e) {
                console.warn('FID monitoring not supported');
            }
        }
    }
    
    // Update LCP indicator
    function updateLCPIndicator(lcp) {
        const indicator = document.querySelector('.lcp-indicator');
        if (indicator) {
            indicator.textContent = `LCP: ${Math.round(lcp)}ms`;
            indicator.style.background = lcp < 2500 ? '#46b450' : lcp < 4000 ? '#ffb900' : '#dc3232';
        }
    }
    
    // Update CLS indicator
    function updateCLSIndicator(cls) {
        const indicator = document.querySelector('.cls-indicator');
        if (indicator) {
            indicator.textContent = `CLS: ${cls.toFixed(3)}`;
            indicator.style.background = cls < 0.1 ? '#46b450' : cls < 0.25 ? '#ffb900' : '#dc3232';
        }
    }
    
    // Update FID indicator
    function updateFIDIndicator(fid) {
        const indicator = document.querySelector('.fid-indicator');
        if (indicator) {
            indicator.textContent = `FID: ${Math.round(fid)}ms`;
            indicator.style.background = fid < 100 ? '#46b450' : fid < 300 ? '#ffb900' : '#dc3232';
        }
    }
    
    // Font loading optimization
    function optimizeFontLoading() {
        // Check if fonts are loaded
        if ('fonts' in document) {
            document.fonts.ready.then(() => {
                document.body.classList.add('fonts-loaded');
            });
        }
        
        // Fallback for older browsers
        setTimeout(() => {
            document.body.classList.add('fonts-loaded');
        }, 3000);
    }
    
    // Lazy loading optimization
    function optimizeLazyLoading() {
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        // Validar que src existe y es una URL válida
                        if (src && src.trim() !== '' && src !== 'undefined' && src.indexOf('undefined') === -1) {
                            img.src = src;
                            img.classList.remove('lazy-loading');
                            img.classList.add('loaded');
                            observer.unobserve(img);
                        } else {
                            // Si data-src es inválido, remover el atributo para evitar errores
                            img.removeAttribute('data-src');
                            img.classList.remove('lazy-loading');
                            img.classList.add('error');
                            console.warn('Invalid data-src attribute removed:', src);
                        }
                    }
                });
            }, {
                rootMargin: '50px' // Cargar imágenes antes de que estén visibles
            });
            
            lazyImages.forEach(img => {
                // Validar antes de observar
                if (img.dataset.src && img.dataset.src.trim() !== '' && img.dataset.src !== 'undefined') {
                    imageObserver.observe(img);
                } else {
                    img.removeAttribute('data-src');
                    img.classList.add('error');
                }
            });
        } else {
            // Fallback for older browsers
            const lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(img => {
                const src = img.dataset.src;
                if (src && src.trim() !== '' && src !== 'undefined' && src.indexOf('undefined') === -1) {
                    img.src = src;
                    img.classList.remove('lazy-loading');
                    img.classList.add('loaded');
                } else {
                    img.removeAttribute('data-src');
                    img.classList.add('error');
                }
            });
        }
    }
    
    // CSS optimization
    function optimizeCSS() {
        // Remove unused CSS classes
        const unusedClasses = document.querySelectorAll('.unused-css');
        unusedClasses.forEach(el => el.remove());
        
        // Inject critical CSS if needed
        const criticalCSS = document.querySelector('#snn-critical-css');
        if (criticalCSS) {
            criticalCSS.classList.add('critical-css');
        }
    }
    
    // JavaScript optimization
    function optimizeJavaScript() {
        // Defer non-critical JavaScript
        const deferElements = document.querySelectorAll('.defer-js');
        deferElements.forEach(el => {
            el.style.display = 'block';
        });
        
        // Remove console logs in production
        if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            console.log = function() {};
            console.warn = function() {};
            console.error = function() {};
        }
    }
    
    // Third-party optimization
    function optimizeThirdParty() {
        // Optimize Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('config', 'GA_MEASUREMENT_ID', {
                'send_page_view': false
            });
        }
        
        // Optimize Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('track', 'PageView');
        }
    }
    
    // Resource hints optimization
    function optimizeResourceHints() {
        // DNS prefetch optimization
        const dnsPrefetchElements = document.querySelectorAll('.dns-prefetch');
        dnsPrefetchElements.forEach(el => {
            const link = document.createElement('link');
            link.rel = 'dns-prefetch';
            link.href = el.dataset.href;
            document.head.appendChild(link);
        });
        
        // Preconnect optimization
        const preconnectElements = document.querySelectorAll('.preconnect');
        preconnectElements.forEach(el => {
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = el.dataset.href;
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        });
    }
    
    // Initialize all optimizations
    function initOptimizations() {
        try {
            initCoreWebVitals();
            optimizeFontLoading();
            optimizeLazyLoading();
            optimizeCSS();
            optimizeJavaScript();
            optimizeThirdParty();
            optimizeResourceHints();
        } catch (e) {
            // Prevenir que errores en optimizaciones detengan la página
            if (typeof console !== 'undefined' && console.error) {
                console.error('Error en optimizaciones de assets:', e);
            }
        }
    }
    
    // Run optimizations when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOptimizations);
    } else {
        initOptimizations();
    }
    
    // Expose Core Web Vitals for external use
    window.snnCoreWebVitals = coreWebVitals;
    
})();
