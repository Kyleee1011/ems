    </main>
</div> <!-- Closing .app -->

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Theme Toggle Logic
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;

    // Check for saved theme preference
    const savedMode = localStorage.getItem('theme-mode') || 'light';
    html.setAttribute('data-mode', savedMode);
    updateThemeUI(savedMode);

    themeToggle.addEventListener('click', () => {
        const currentMode = html.getAttribute('data-mode');
        const newMode = currentMode === 'light' ? 'dark' : 'light';
        
        html.setAttribute('data-mode', newMode);
        localStorage.setItem('theme-mode', newMode);
        updateThemeUI(newMode);
    });

    function updateThemeUI(mode) {
        const icon = themeToggle.querySelector('i');
        const text = themeToggle.querySelector('span');
        
        if (mode === 'dark') {
            icon.className = 'fa-solid fa-sun';
            text.textContent = 'Light';
        } else {
            icon.className = 'fa-solid fa-moon';
            text.textContent = 'Dark';
        }
    }

    // Global Select2 Initialization
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%'
        });
        
        // Auto-initialize cutoff and employee selectors if they exist
        $('select[name="cutoff"], select[name="search_ac"], select#deptFilter, select#hr_search, select#cutoff_select, select#dept_select').select2({
            width: '100%'
        });

        // Global CSRF Token Injection
        $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
            if (options.type && options.type.toUpperCase() === "POST") {
                const token = '<?php echo \App\Utils\AppHelpers::generateCsrfToken(); ?>';
                if (typeof options.data === "string") {
                    options.data += (options.data ? "&" : "") + "csrf_token=" + encodeURIComponent(token);
                } else if (options.data && typeof options.data === "object" && !(options.data instanceof FormData)) {
                    options.data.csrf_token = token;
                } else if (!options.data) {
                    options.data = "csrf_token=" + encodeURIComponent(token);
                }
            }
        });
    });

    // Nav sub-menu toggle fix
    document.querySelectorAll('.nav-item.has-sub').forEach(item => {
        // If child sub-item is active, keep it open
        if (item.nextElementSibling && item.nextElementSibling.querySelector('.active')) {
            item.nextElementSibling.classList.add('open');
        }
    });
</script>
</body>
</html>
