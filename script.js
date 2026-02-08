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
        title: "RXINSIGHT",
        description: "RxInsight is a patient-centered web application designed to bridge the gap between complex pharmaceutical data and everyday health safety",
        link: "#",
        tags: ["Python", "Flask", "MySQL"]
    },
    {
        title: "Ligand binding site predictor for proteins",
        description: "Launching soon ...",
        link: "#",
        tags: ["Python"]
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