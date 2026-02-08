// 1. Find the button in the HTML
const toggleBtn = document.getElementById('theme-toggle');

// 2. Tell the button to listen for a click
toggleBtn.addEventListener('click', () => {
    // 3. Toggle the "dark-theme" class on the body element
    document.body.classList.toggle('dark-theme');
});