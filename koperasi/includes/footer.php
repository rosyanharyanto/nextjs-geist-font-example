</main>

    <footer class="bg-gray-800 text-gray-300 py-8 mt-auto">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-gold font-bold text-lg mb-2">Koperasi Sejahtera</h3>
                    <p class="text-sm">Melayani dengan Amanah dan Profesional</p>
                </div>
                
                <div class="text-center md:text-right">
                    <p class="text-sm">&copy; <?= date('Y') ?> Koperasi Sejahtera</p>
                    <p class="text-sm mt-1">All rights reserved</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Check if dark mode is enabled
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }

        // Toggle dark mode
        function toggleDarkMode() {
            if (localStorage.theme === 'dark') {
                localStorage.theme = 'light'
                document.documentElement.classList.remove('dark')
            } else {
                localStorage.theme = 'dark'
                document.documentElement.classList.add('dark')
            }
        }
    </script>
</body>
</html>
