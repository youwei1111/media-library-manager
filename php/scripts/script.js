/**
 * Media Library Manager - Core Interaction Logic (Final Version)
 * Author: You Wei
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Core Configuration & Utility Functions ---
    
    const UI = {
        themeAttr: 'data-theme',
        storageKey: 'theme',
    };

    /**
     * Universal AJAX Data Synchronization
     * Sends updated item data to the backend without refreshing the page
     */
    async function updateData(params) {
        const query = new URLSearchParams({ ...params, ajax: 1 }).toString();
        try {
            const response = await fetch(`update_item.php?${query}`);
            if (!response.ok) throw new Error('Network response was not ok');
            console.log('✅ Sync Successful:', params);
            return true;
        } catch (error) {
            console.error('❌ AJAX Error:', error);
            return false;
        }
    }

    /**
     * Progress Bar UI Update
     * Dynamically calculates and updates the width of the progress bar
     */
    function updateProgressBar(card, current, total) {
        const bar = card.querySelector('.progress-bar');
        if (bar && total > 0) {
            const percent = Math.min((current / total) * 100, 100);
            bar.style.width = `${percent}%`;
        }
    }

    // --- 2. Theme Management ---
    
    /**
     * Initialize Theme
     * Handles Dark/Light mode based on local storage or system preference
     */
    function initTheme() {
        const savedTheme = localStorage.getItem(UI.storageKey);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const targetTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute(UI.themeAttr, targetTheme);
        
        const themeBtn = document.getElementById('theme-btn');
        if (themeBtn) {
            themeBtn.onclick = () => {
                const current = document.documentElement.getAttribute(UI.themeAttr);
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute(UI.themeAttr, next);
                localStorage.setItem(UI.storageKey, next);
            };
        }
    }

    // --- 3. Core Interaction Logic ---

    /**
     * Quick Progress Increment (+1 Logic)
     * Increments current episode and triggers auto-sync
     */
    async function handleQuickPlus(btn) {
        const card = btn.closest('.card');
        const id = card.querySelector('input[name="update_id"]').value;
        const curInput = card.querySelector('input[name="current_ep"]');
        const totInput = card.querySelector('input[name="total_eps"]');

        let cur = parseInt(curInput.value) || 0;
        let tot = parseInt(totInput.value) || 0;

        if (tot === 0 || cur < tot) {
            cur++;
            curInput.value = cur;
            updateProgressBar(card, cur, tot);
            
            // Sync to database
            const success = await updateData({ 
                update_id: id, 
                current_ep: cur, 
                total_eps: tot 
            });

            // Logic Linkage: Auto-update status to "Completed" if progress is full
            if (success && tot > 0 && cur >= tot) {
                const statusSelect = card.querySelector('select[name="new_status"]');
                if (statusSelect && statusSelect.value !== '已看') {
                    statusSelect.value = '已看';
                    handleAutoSave(statusSelect); // Trigger status visual update
                }
            }
        }
    }

    /**
     * Universal Auto-Save Logic
     * Handles updates for status, remarks, and links on input change
     */
    async function handleAutoSave(el) {
        const card = el.closest('.card');
        const idContainer = el.closest('form') || card;
        const id = idContainer.querySelector('input[name="update_id"]').value;
        
        const params = { update_id: id };
        
        if (el.classList.contains('ep-input')) {
            const cur = idContainer.querySelector('input[name="current_ep"]').value;
            const tot = idContainer.querySelector('input[name="total_eps"]').value;
            params.current_ep = cur;
            params.total_eps = tot;
            updateProgressBar(card, cur, tot);
        } else {
            params[el.name] = el.value;
        }

        const success = await updateData(params);

        // Update card visual state based on status selection
        if (success && el.name === 'new_status') {
            card.classList.remove('status-done', 'status-watching', 'status-uptodate');
            if (el.value === '已看') card.classList.add('status-done');
            if (el.value === '在看') card.classList.add('status-watching');
            if (el.value === '追平') card.classList.add('status-uptodate');
        }
    }

    // --- 4. Global Functions (Exposed to Window) ---

    /**
     * Delete Item with confirmation and scale-out animation
     */
    window.deleteItem = function(id, btn) {
        if (!confirm('Are you sure you want to delete this item?')) return;
        const card = btn.closest('.card');
        fetch('delete_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                // UI feedback: smooth removal animation
                card.style.transition = 'all 0.4s ease';
                card.style.transform = 'scale(0.8)';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 400);
            }
        });
    };

    /**
     * Add Item via Form Submission
     */
    window.addItem = function(event, form) {
        event.preventDefault();
        const btn = form.querySelector('button');
        btn.innerText = 'Adding...';
        btn.disabled = true;
        const formData = new FormData(form);
        fetch('confirm_add.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'SUCCESS') {
                btn.innerText = '✅ Success';
                setTimeout(() => {
                    // Refresh with timestamp to prevent cache issues
                    window.location.href = 'index.php?t=' + new Date().getTime();
                }, 600);
            }
        });
    };

    // --- 5. Event Delegation ---

    /**
     * Change Event Listener
     * Handles folder management and automatic data saving
     */
    document.addEventListener('change', (e) => {
        const el = e.target;
        
        // Folder management logic
        if (el.classList.contains('folder-logic')) {
            const id = el.closest('.card').querySelector('input[name="update_id"]').value;
            if (el.value === "NEW_FOLDER") {
                const name = prompt("Enter new folder name:");
                if (name?.trim()) {
                    window.location.href = `update_item.php?update_folder_id=${id}&new_folder=${encodeURIComponent(name)}`;
                }
                return;
            }
            updateData({ update_folder_id: id, new_folder: el.value });
            return;
        }

        // Auto-save trigger for specific inputs
        if (el.matches('select, input:not([type="text"]), .remarks-input, .link-input')) {
            handleAutoSave(el);
        }
    });

    /**
     * Click Event Listener
     * Handles Quick +1 and Link Toggle interactions
     */
    document.addEventListener('click', (e) => {
        const el = e.target;
        
        const plusBtn = el.closest('.mini-plus-btn');
        if (plusBtn) {
            handleQuickPlus(plusBtn);
        }

        if (el.closest('.link-toggle-btn')) {
            const wrapper = el.closest('.link-wrapper');
            const input = wrapper.querySelector('.link-input');
            input.classList.toggle('show');
            if (input.classList.contains('show')) input.focus();
        }
    });

    /**
     * Keyboard Event Listener
     * Blur link input and trigger save on Enter key
     */
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('link-input')) {
            e.target.blur(); // Triggers change event for saving
            e.target.classList.remove('show');
        }
    });

    /**
     * Dynamic Search Placeholder Handler
     * Updates the search input hint based on the selected media type.
     */
    document.addEventListener('DOMContentLoaded', () => {
        const typeSelect = document.getElementById('media-type-select');
        const searchInput = document.getElementById('search-input');
    
        // Ensure elements exist before adding listeners
        if (typeSelect && searchInput) {
            const placeholders = {
                'book': '🔍 Enter Title, Author, or ISBN (e.g., 978...)',
                'movie': '🔍 Enter Movie Title...',
                'tv': '🔍 Enter TV Show Title...',
                'anime': '🔍 Enter Anime Name...',
                'manga': '🔍 Enter Manga Title or Bangumi ID...'
            };
    
            // Listen for changes in the dropdown menu
            typeSelect.addEventListener('change', (e) => {
                const selectedType = e.target.value;
                // Apply the corresponding placeholder or fallback to a default string
                searchInput.placeholder = placeholders[selectedType] || '🔍 Search for media...';
            });
        }
    });

    // Initialize logic on load
    initTheme();
});
