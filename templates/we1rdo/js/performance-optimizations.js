/**
 * Spotweb Performance Optimizations
 * 
 * This script implements various client-side performance optimizations:
 * - Lazy loading for images
 * - Resource prioritization
 * - Content prefetching
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize performance optimizations
    initLazyLoading();
    initResourcePrioritization();
    initContentPrefetching();
});

/**
 * Initialize lazy loading for images
 */
function initLazyLoading() {
    // Check if IntersectionObserver is supported
    if ('IntersectionObserver' in window) {
        const lazyImages = document.querySelectorAll('img.lazy');
        
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove('lazy');
                    imageObserver.unobserve(lazyImage);
                    
                    console.log('Lazy loaded image: ' + lazyImage.alt);
                }
            });
        });
        
        lazyImages.forEach(function(image) {
            imageObserver.observe(image);
        });
        
        console.log('Lazy loading initialized for ' + lazyImages.length + ' images');
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        const lazyImages = document.querySelectorAll('img.lazy');
        lazyImages.forEach(function(img) {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }
}

/**
 * Initialize resource prioritization
 * Adds priority hints to critical resources
 */
function initResourcePrioritization() {
    // Add priority hints to critical CSS
    const criticalCss = document.querySelectorAll('link[rel="stylesheet"]');
    criticalCss.forEach(function(link) {
        if (link.href.includes('style.css') || link.href.includes('bootstrap')) {
            link.setAttribute('importance', 'high');
        }
    });
    
    // Add priority hints to critical scripts
    const criticalScripts = document.querySelectorAll('script[src]');
    criticalScripts.forEach(function(script) {
        if (script.src.includes('jquery') || script.src.includes('bootstrap')) {
            script.setAttribute('importance', 'high');
        } else {
            script.setAttribute('importance', 'low');
            script.setAttribute('async', 'true');
        }
    });
}

/**
 * Initialize content prefetching
 * Preloads content that users are likely to navigate to
 */
function initContentPrefetching() {
    // Wait until the page is fully loaded and idle
    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() {
            prefetchLinks();
        });
    } else {
        // Fallback for browsers without requestIdleCallback
        setTimeout(function() {
            prefetchLinks();
        }, 2000);
    }
}

/**
 * Prefetch links that users are likely to click
 */
function prefetchLinks() {
    // Find navigation links and spot links that are visible in the viewport
    const links = document.querySelectorAll('a.spotlink, a.navbar-brand, .navigation a');
    
    // Only prefetch a limited number of links to avoid excessive requests
    const maxPrefetch = 5;
    let prefetchCount = 0;
    
    links.forEach(function(link) {
        if (prefetchCount >= maxPrefetch) return;
        
        // Only prefetch internal links
        if (link.hostname === window.location.hostname && !link.href.includes('#') && !link.href.includes('getimage')) {
            const linkPosition = link.getBoundingClientRect();
            
            // Check if link is visible in the viewport
            if (linkPosition.top >= 0 && linkPosition.bottom <= window.innerHeight) {
                prefetchUrl(link.href);
                prefetchCount++;
            }
        }
    });
    
    console.log('Prefetched ' + prefetchCount + ' links');
}

/**
 * Prefetch a URL using rel=prefetch
 */
function prefetchUrl(url) {
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = url;
    document.head.appendChild(link);
}
