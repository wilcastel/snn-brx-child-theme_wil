/**
 * WebP Image Optimization JavaScript
 * 
 * Handles lazy loading and WebP detection
 * 
 * @package SNN-BRX-WIL
 * @since 1.0.0
 */

(function() {
    'use strict';
    
    // WebP Support Detection
    function detectWebPSupport() {
        return new Promise((resolve) => {
            const webP = new Image();
            webP.onload = webP.onerror = function() {
                resolve(webP.height === 2);
            };
            webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        });
    }
    
    // Lazy Loading Implementation
    class LazyImageLoader {
        constructor() {
            this.images = [];
            this.observer = null;
            this.init();
        }
        
        init() {
            // Detect WebP support
            detectWebPSupport().then((supported) => {
                document.documentElement.classList.add(supported ? 'webp' : 'no-webp');
            });
            
            // Initialize lazy loading
            this.initLazyLoading();
        }
        
        initLazyLoading() {
            // Check if Intersection Observer is supported
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadImage(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });
                
                // Observe all lazy images
                document.querySelectorAll('.snn-lazy-image').forEach(img => {
                    this.observer.observe(img);
                });
            } else {
                // Fallback for older browsers
                this.loadAllImages();
            }
        }
        
        loadImage(img) {
            const src = img.dataset.src;
            if (!src) return;
            
            // Add loading class
            img.classList.add('loading');
            
            // Create new image element
            const newImg = new Image();
            
            newImg.onload = () => {
                // Replace src
                img.src = src;
                img.classList.remove('loading');
                img.classList.add('loaded');
                
                // Remove data-src
                delete img.dataset.src;
                
                // Trigger custom event
                img.dispatchEvent(new CustomEvent('lazyLoaded', {
                    detail: { src: src }
                }));
            };
            
            newImg.onerror = () => {
                img.classList.remove('loading');
                img.classList.add('error');
                
                // Trigger custom event
                img.dispatchEvent(new CustomEvent('lazyError', {
                    detail: { src: src }
                }));
            };
            
            newImg.src = src;
        }
        
        loadAllImages() {
            // Fallback: load all images immediately
            document.querySelectorAll('.snn-lazy-image').forEach(img => {
                this.loadImage(img);
            });
        }
        
        // Public method to add new images
        addImage(img) {
            if (this.observer) {
                this.observer.observe(img);
            } else {
                this.loadImage(img);
            }
        }
    }
    
    // Critical Image Preloader
    class CriticalImagePreloader {
        constructor() {
            this.preloadedImages = new Set();
            this.init();
        }
        
        init() {
            // Preload critical images
            this.preloadCriticalImages();
        }
        
        preloadCriticalImages() {
            const criticalImages = document.querySelectorAll('link[rel="preload"][as="image"]');
            
            criticalImages.forEach(link => {
                const href = link.href;
                if (!this.preloadedImages.has(href)) {
                    this.preloadImage(href);
                    this.preloadedImages.add(href);
                }
            });
        }
        
        preloadImage(src) {
            const img = new Image();
            img.onload = () => {
                // Image preloaded successfully
                console.log('Critical image preloaded:', src);
            };
            img.onerror = () => {
                console.warn('Failed to preload critical image:', src);
            };
            img.src = src;
        }
    }
    
    // WebP Fallback Handler
    class WebPFallbackHandler {
        constructor() {
            this.init();
        }
        
        init() {
            // Handle WebP fallback for older browsers
            if (!document.documentElement.classList.contains('webp')) {
                this.handleFallback();
            }
        }
        
        handleFallback() {
            // Replace WebP images with fallback versions
            document.querySelectorAll('img[src*=".webp"]').forEach(img => {
                const fallbackSrc = img.src.replace('.webp', '.jpg');
                img.src = fallbackSrc;
            });
        }
    }
    
    // Image Optimization Utilities
    class ImageOptimizationUtils {
        constructor() {
            this.init();
        }
        
        init() {
            // Add optimization indicators
            this.addOptimizationIndicators();
            
            // Handle responsive images
            this.handleResponsiveImages();
        }
        
        addOptimizationIndicators() {
            // Add WebP badges to optimized images
            document.querySelectorAll('img[src*=".webp"]').forEach(img => {
                if (!img.parentElement.querySelector('.snn-webp-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'snn-webp-badge';
                    badge.textContent = 'WebP';
                    badge.setAttribute('aria-label', 'Optimized WebP image');
                    
                    img.parentElement.style.position = 'relative';
                    img.parentElement.appendChild(badge);
                }
            });
        }
        
        handleResponsiveImages() {
            // Ensure responsive behavior
            document.querySelectorAll('img').forEach(img => {
                if (!img.classList.contains('snn-responsive-image')) {
                    img.classList.add('snn-responsive-image');
                }
            });
        }
    }
    
    // Performance Monitor
    class ImagePerformanceMonitor {
        constructor() {
            this.metrics = {
                lazyLoaded: 0,
                preloaded: 0,
                errors: 0,
                totalLoadTime: 0
            };
            this.init();
        }
        
        init() {
            // Monitor lazy loading events
            document.addEventListener('lazyLoaded', (e) => {
                this.metrics.lazyLoaded++;
                this.logMetric('lazyLoaded', e.detail.src);
            });
            
            document.addEventListener('lazyError', (e) => {
                this.metrics.errors++;
                this.logMetric('lazyError', e.detail.src);
            });
            
            // Monitor performance
            this.monitorPerformance();
        }
        
        logMetric(type, src) {
            if (window.console && console.log) {
                console.log(`Image ${type}:`, src);
            }
        }
        
        monitorPerformance() {
            // Monitor Core Web Vitals impact
            if ('PerformanceObserver' in window) {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach(entry => {
                        if (entry.entryType === 'largest-contentful-paint') {
                            this.logMetric('LCP', entry.startTime);
                        }
                    });
                });
                
                observer.observe({ entryTypes: ['largest-contentful-paint'] });
            }
        }
        
        getMetrics() {
            return this.metrics;
        }
    }
    
    // Initialize when DOM is ready
    function init() {
        new LazyImageLoader();
        new CriticalImagePreloader();
        new WebPFallbackHandler();
        new ImageOptimizationUtils();
        new ImagePerformanceMonitor();
    }
    
    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Export for external use
    window.SNNImageOptimization = {
        LazyImageLoader,
        CriticalImagePreloader,
        WebPFallbackHandler,
        ImageOptimizationUtils,
        ImagePerformanceMonitor
    };
    
})();
