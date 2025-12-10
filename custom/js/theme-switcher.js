/**
 * Multi-Theme Switcher for Spotweb
 * Supports multiple beautiful themes with smooth transitions
 * Auto-detects all available themes from loaded CSS files
 */

// Theme metadata (icons and display names)
const themeMetadata = {
    'light': { name: 'Light (Default)', icon: '☀️' },
    'dark': { name: 'Dark', icon: '🌙' },
    'midnight-ocean': { name: 'Midnight Ocean', icon: '🌊' },
    'cyberpunk': { name: 'Cyberpunk', icon: '🎮' },
    'nord': { name: 'Nord', icon: '❄️' },
    'dracula': { name: 'Dracula', icon: '🧛' },
    'forest': { name: 'Forest', icon: '🌲' },
    'sunset': { name: 'Sunset', icon: '🌅' }
};

// Auto-detect available themes from loaded CSS files
function detectAvailableThemes() {
    const themes = [{ id: 'light', name: 'Light (Default)', icon: '☀️' }]; // Always include light
    
    // Scan all <link> tags for theme CSS files
    document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes('theme-') && href.endsWith('.css')) {
            // Extract theme ID from filename: theme-dark.css -> dark
            const match = href.match(/theme-([^\/]+)\.css$/);
            if (match) {
                const themeId = match[1];
                // Skip if already added
                if (!themes.find(t => t.id === themeId)) {
                    // Use metadata if available, otherwise generate nice name
                    const metadata = themeMetadata[themeId] || {
                        name: themeId.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '),
                        icon: '🎨'
                    };
                    themes.push({
                        id: themeId,
                        name: metadata.name,
                        icon: metadata.icon
                    });
                }
            }
        }
    });
    
    return themes;
}

const themes = detectAvailableThemes();

document.addEventListener('DOMContentLoaded', function() {
    // Add smooth transition to body
    document.body.style.transition = 'background 0.3s ease, color 0.3s ease';
    
    // Load saved theme
    const savedTheme = localStorage.getItem('spotwebTheme') || 'light';
    applyTheme(savedTheme);
    
    // Create theme switcher in toolbar
    const toolbar = document.querySelector('div#toolbar');
    if (toolbar) {
        createThemeSwitcher(toolbar, savedTheme);
    }
    
    // Handle AJAX navigation
    observePageChanges();
});

function createThemeSwitcher(toolbar, currentTheme) {
    const themeSwitcher = document.createElement('div');
    themeSwitcher.className = 'toolbarButton theme-switcher';
    themeSwitcher.innerHTML = `
        <div class="theme-dropdown">
            <p>
                <a id="theme-dropdown-toggle" class="theme-toggle">
                    <span class="theme-icon">${getThemeIcon(currentTheme)}</span>
                    <span class="theme-name">${getThemeName(currentTheme)}</span>
                    <span class="dropdown-arrow">▼</span>
                </a>
            </p>
            <div id="theme-menu" class="theme-menu">
                ${themes.map(theme => `
                    <div class="theme-option ${theme.id === currentTheme ? 'active' : ''}" data-theme="${theme.id}">
                        <span class="theme-icon">${theme.icon}</span>
                        <span>${theme.name}</span>
                        ${theme.id === currentTheme ? '<span class="checkmark">✓</span>' : ''}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    // Add CSS for dropdown
    addThemeSwitcherStyles();
    
    // Insert before other toolbar buttons
    toolbar.insertBefore(themeSwitcher, toolbar.firstChild);
    
    // Setup event listeners
    setupThemeSwitcherEvents(currentTheme);
}

function setupThemeSwitcherEvents(currentTheme) {
    const toggle = document.getElementById('theme-dropdown-toggle');
    const menu = document.getElementById('theme-menu');
    const options = document.querySelectorAll('.theme-option');
    
    // Toggle dropdown
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        menu.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.theme-switcher')) {
            menu.classList.remove('show');
        }
    });
    
    // Theme selection
    options.forEach(option => {
        option.addEventListener('click', function() {
            const themeId = this.dataset.theme;
            applyTheme(themeId);
            localStorage.setItem('spotwebTheme', themeId);
            
            // Update UI
            updateThemeSwitcherUI(themeId);
            menu.classList.remove('show');
            
            // Smooth transition effect
            document.body.style.opacity = '0.95';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    });
}

function applyTheme(themeId) {
    // Remove all theme classes
    document.body.className = document.body.className
        .replace(/theme-[\w-]+/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    
    // Apply new theme
    if (themeId !== 'light') {
        document.body.classList.add(`theme-${themeId}`);
    }
}

function updateThemeSwitcherUI(themeId) {
    // Update toggle button
    const toggle = document.getElementById('theme-dropdown-toggle');
    if (toggle) {
        toggle.querySelector('.theme-icon').textContent = getThemeIcon(themeId);
        toggle.querySelector('.theme-name').textContent = getThemeName(themeId);
    }
    
    // Update active state in menu
    document.querySelectorAll('.theme-option').forEach(option => {
        const isActive = option.dataset.theme === themeId;
        option.classList.toggle('active', isActive);
        
        // Update checkmark
        const checkmark = option.querySelector('.checkmark');
        if (checkmark) checkmark.remove();
        if (isActive) {
            option.insertAdjacentHTML('beforeend', '<span class="checkmark">✓</span>');
        }
    });
}

function getThemeIcon(themeId) {
    const theme = themes.find(t => t.id === themeId);
    return theme ? theme.icon : '🎨';
}

function getThemeName(themeId) {
    const theme = themes.find(t => t.id === themeId);
    return theme ? theme.name : 'Theme';
}

function addThemeSwitcherStyles() {
    if (document.getElementById('theme-switcher-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'theme-switcher-styles';
    style.textContent = `
        .theme-switcher {
            position: relative;
            z-index: 1000;
        }
        
        .theme-dropdown {
            position: relative;
        }
        
        .theme-toggle {
            display: flex !important;
            align-items: center;
            gap: 6px;
            padding: 4px 12px !important;
            cursor: pointer;
            user-select: none;
            background: none !important;
            transition: all 0.2s ease;
        }
        
        .theme-toggle:hover {
            opacity: 0.8;
        }
        
        .theme-icon {
            font-size: 16px;
        }
        
        .theme-name {
            font-size: 12px;
        }
        
        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.2s ease;
        }
        
        .theme-menu.show .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .theme-menu {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 200px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            margin-top: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .theme-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .theme-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: background 0.15s ease;
            color: #333;
            font-size: 13px;
        }
        
        .theme-option:hover {
            background: #f5f5f5;
        }
        
        .theme-option.active {
            background: #e8f4f8;
            font-weight: 600;
        }
        
        .theme-option .theme-icon {
            font-size: 18px;
        }
        
        .theme-option .checkmark {
            margin-left: auto;
            color: #4CAF50;
            font-weight: bold;
        }
        
        /* Dark theme adjustments for menu */
        body[class*="theme-"] .theme-menu {
            background: #2a2a2a;
            border-color: #444;
        }
        
        body[class*="theme-"] .theme-option {
            color: #e0e0e0;
        }
        
        body[class*="theme-"] .theme-option:hover {
            background: #333;
        }
        
        body[class*="theme-"] .theme-option.active {
            background: #3a3a3a;
        }
        
        /* Scrollbar styling */
        .theme-menu::-webkit-scrollbar {
            width: 6px;
        }
        
        .theme-menu::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .theme-menu::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .theme-menu::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        body[class*="theme-"] .theme-menu::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        body[class*="theme-"] .theme-menu::-webkit-scrollbar-thumb {
            background: #555;
        }
    `;
    
    document.head.appendChild(style);
}

function observePageChanges() {
    // Re-apply theme after AJAX navigation
    const observer = new MutationObserver(function() {
        const savedTheme = localStorage.getItem('spotwebTheme') || 'light';
        if (savedTheme !== 'light' && !document.body.classList.contains(`theme-${savedTheme}`)) {
            applyTheme(savedTheme);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Also check periodically (backup)
    setInterval(function() {
        const savedTheme = localStorage.getItem('spotwebTheme') || 'light';
        if (savedTheme !== 'light' && !document.body.classList.contains(`theme-${savedTheme}`)) {
            applyTheme(savedTheme);
        }
    }, 1000);
}
