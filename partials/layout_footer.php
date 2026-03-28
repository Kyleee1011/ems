    </main>
</div> <!-- Closing .app -->

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
