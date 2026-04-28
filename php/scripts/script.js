/**
 * 我的收藏库 - 核心交互逻辑 (最终修正版)
 */
document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. 核心配置与工具函数 ---
    
    const UI = {
        themeAttr: 'data-theme',
        storageKey: 'theme',
    };

    /**
     * 通用 AJAX 数据提交
     */
    async function updateData(params) {
        const query = new URLSearchParams({ ...params, ajax: 1 }).toString();
        try {
            const response = await fetch(`update_item.php?${query}`);
            if (!response.ok) throw new Error('提交失败');
            console.log('✅ 同步成功:', params);
            return true;
        } catch (error) {
            console.error('❌ AJAX 错误:', error);
            return false;
        }
    }

    /**
     * 更新进度条
     */
    function updateProgressBar(card, current, total) {
        const bar = card.querySelector('.progress-bar');
        if (bar && total > 0) {
            const percent = Math.min((current / total) * 100, 100);
            bar.style.width = `${percent}%`;
        }
    }

    // --- 2. 主题管理 ---
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

    // --- 3. 核心交互逻辑函数 ---

    /**
     * [快速 +1 逻辑] - 补齐此功能
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
            
            // 提交数据
            const success = await updateData({ 
                update_id: id, 
                current_ep: cur, 
                total_eps: tot 
            });

            // 联动逻辑：如果满了，自动改状态为“已看”
            if (success && tot > 0 && cur >= tot) {
                const statusSelect = card.querySelector('select[name="new_status"]');
                if (statusSelect && statusSelect.value !== '已看') {
                    statusSelect.value = '已看';
                    handleAutoSave(statusSelect); // 触发变色逻辑
                }
            }
        }
    }

    /**
     * [通用自动保存逻辑]
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

        if (success && el.name === 'new_status') {
            card.classList.remove('status-done', 'status-watching', 'status-uptodate');
            if (el.value === '已看') card.classList.add('status-done');
            if (el.value === '在看') card.classList.add('status-watching');
            if (el.value === '追平') card.classList.add('status-uptodate');
        }
    }

    // --- 4. 全局挂载函数 ---

    window.deleteItem = function(id, btn) {
        if (!confirm('确定要删除这个作品吗？')) return;
        const card = btn.closest('.card');
        fetch('delete_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                card.style.transition = 'all 0.4s ease';
                card.style.transform = 'scale(0.8)';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 400);
            }
        });
    };

    window.addItem = function(event, form) {
        event.preventDefault();
        const btn = form.querySelector('button');
        btn.innerText = '正在添加...';
        btn.disabled = true;
        const formData = new FormData(form);
        fetch('confirm_add.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'SUCCESS') {
                btn.innerText = '✅ 已成功';
                setTimeout(() => {
                    window.location.href = 'index.php?t=' + new Date().getTime();
                }, 600);
            }
        });
    };

    // --- 5. 事件委托 ---

    document.addEventListener('change', (e) => {
        const el = e.target;
        
        if (el.classList.contains('folder-logic')) {
            const id = el.closest('.card').querySelector('input[name="update_id"]').value;
            if (el.value === "NEW_FOLDER") {
                const name = prompt("请输入新文件夹名称:");
                if (name?.trim()) {
                    window.location.href = `update_item.php?update_folder_id=${id}&new_folder=${encodeURIComponent(name)}`;
                }
                return;
            }
            updateData({ update_folder_id: id, new_folder: el.value });
            return;
        }

        // 包含链接输入框的自动保存
        if (el.matches('select, input:not([type="text"]), .remarks-input, .link-input')) {
            handleAutoSave(el);
        }
    });

    document.addEventListener('click', (e) => {
        const el = e.target;
        
        // --- 修正：真正调用加一逻辑 ---
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

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('link-input')) {
            e.target.blur(); 
            e.target.classList.remove('show');
        }
    });

    initTheme();
});