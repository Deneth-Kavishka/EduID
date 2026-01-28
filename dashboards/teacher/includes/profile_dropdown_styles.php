<!-- Teacher Profile Dropdown Styles -->
<style>
    .profile-dropdown-container {
        position: relative;
    }
    
    .profile-trigger {
        background: none;
        border: none;
        padding: 2px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .profile-trigger:hover {
        background: var(--bg-secondary);
    }
    
    .profile-trigger .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .profile-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 320px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px) scale(0.95);
        transition: all 0.2s ease;
    }
    
    .profile-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }
    
    .profile-dropdown-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        text-align: left;
    }
    
    .profile-dropdown-avatar img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--bg-secondary);
    }
    
    .profile-dropdown-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 1rem;
    }
    
    .profile-dropdown-email {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .profile-dropdown-manage {
        padding: 0 1rem 1rem;
    }
    
    .manage-account-btn {
        display: block;
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 20px;
        text-align: center;
        color: #10b981;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .manage-account-btn:hover {
        background: rgba(16, 185, 129, 0.1);
    }
    
    .profile-dropdown-divider {
        height: 1px;
        background: var(--border-color);
        margin: 0;
    }
    
    .profile-dropdown-menu {
        padding: 0.5rem 0;
    }
    
    .profile-dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 1rem;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.9rem;
        transition: background 0.2s;
    }
    
    .profile-dropdown-item:hover {
        background: var(--bg-secondary);
    }
    
    .profile-dropdown-item i {
        width: 20px;
        text-align: center;
        color: var(--text-secondary);
    }
    
    .profile-dropdown-item.logout-item {
        color: #ef4444;
    }
    
    .profile-dropdown-item.logout-item i {
        color: #ef4444;
    }
    
    .theme-indicator {
        margin-left: auto;
        font-size: 0.75rem;
        color: var(--text-secondary);
        background: var(--bg-secondary);
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
    }
    
    .profile-dropdown-footer {
        padding: 0.75rem 1rem;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .profile-dropdown-footer a {
        color: var(--text-secondary);
        text-decoration: none;
    }
    
    .profile-dropdown-footer a:hover {
        color: #10b981;
    }
    
    /* Theme toggle button in header */
    .theme-toggle {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.2s;
    }
    
    .theme-toggle:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }
</style>

<script>
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        const notifDropdown = document.getElementById('notificationDropdown');
        
        // Close notification dropdown if open
        if (notifDropdown) {
            notifDropdown.classList.remove('show');
        }
        
        dropdown.classList.toggle('show');
    }
    
    document.addEventListener('click', function(event) {
        const profileContainer = document.querySelector('.profile-dropdown-container');
        const profileDropdown = document.getElementById('profileDropdown');
        const notifContainer = document.querySelector('.notification-dropdown-container');
        const notifDropdown = document.getElementById('notificationDropdown');
        
        if (profileContainer && profileDropdown && !profileContainer.contains(event.target)) {
            profileDropdown.classList.remove('show');
        }
        
        if (notifContainer && notifDropdown && !notifContainer.contains(event.target)) {
            notifDropdown.classList.remove('show');
        }
    });
    
    function toggleThemeDropdown() {
        // Use the global toggleTheme function if available (from theme.js)
        if (typeof toggleTheme === 'function') {
            toggleTheme();
            // Update local UI elements
            const currentTheme = localStorage.getItem('theme') || 'light';
            const indicator = document.getElementById('themeIndicator');
            if (indicator) {
                indicator.textContent = currentTheme === 'dark' ? 'On' : 'Off';
            }
            const dropdownIcon = document.getElementById('dropdownThemeIcon');
            if (dropdownIcon) {
                dropdownIcon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            return;
        }
        
        // Fallback if theme.js is not loaded
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        const indicator = document.getElementById('themeIndicator');
        if (indicator) {
            indicator.textContent = newTheme === 'dark' ? 'On' : 'Off';
        }
        
        const dropdownIcon = document.getElementById('dropdownThemeIcon');
        if (dropdownIcon) {
            dropdownIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        const themeIcon = document.querySelector('#themeToggleTop i');
        if (themeIcon) {
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
    
    // Initialize theme indicator on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = localStorage.getItem('theme') || 'light';
        const indicator = document.getElementById('themeIndicator');
        if (indicator) {
            indicator.textContent = currentTheme === 'dark' ? 'On' : 'Off';
        }
        
        const dropdownIcon = document.getElementById('dropdownThemeIcon');
        if (dropdownIcon) {
            dropdownIcon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Add click event listener to theme toggle button
        const themeToggleBtn = document.getElementById('themeToggleTop');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof toggleTheme === 'function') {
                    toggleTheme(e);
                } else {
                    toggleThemeDropdown();
                }
            });
        }
    });
</script>
