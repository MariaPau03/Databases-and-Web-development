// 1. Find the button in the HTML
const toggleBtn = document.getElementById('theme-toggle');

// 2. Tell the button to listen for a click
toggleBtn.addEventListener('click', () => {
    // 3. Toggle the "dark-theme" class on the body element
    document.body.classList.toggle('dark-theme');
});

// Change navbar background on scroll
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(13, 13, 13, 0.95)';
        navbar.style.borderBottom = '1px solid #9d4edd'; // Glow effect on scroll
    } else {
        navbar.style.background = 'rgba(13, 13, 13, 0.8)';
        navbar.style.borderBottom = '1px solid #333';
    }
});

// Create and array of objects
const myProjects = [
    {
        title: "My_project_1",
        description: "A high-end dark mode landing page.",
        link: "#",
        tags: ["HTML", "CSS"]
    },
    {
        title: "My_project_2",
        description: "A dynamic shop with a functional cart.",
        link: "#",
        tags: ["JS", "API"]
    },
    {
        title: "My_project_3",
        description: "My very first attempt at web design.",
        link: "#",
        tags: ["Static"]
    }
];

const projectGrid = document.querySelector('.project-grid');

function loadProjects() {
    // Clear the grid first
    projectGrid.innerHTML = '';

    // Loop through our data and create HTML for each one
    myProjects.forEach(project => {
        const card = `
            <div class="project-card">
                <h3>${project.title}</h3>
                <p>${project.description}</p>
                <div class="tags">${project.tags.map(t => `<span>${t}</span>`).join('')}</div>
                <a href="${project.link}">View Project →</a>
            </div>
        `;
        projectGrid.innerHTML += card;
    });
}

// Run the function
loadProjects();