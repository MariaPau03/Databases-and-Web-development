// 1. Theme Toggle
const toggleBtn = document.getElementById('theme-toggle');
if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('light-theme');
    });
}

// 2. Project Data & Loading
const myProjects = [
    {
        title: "RXINSIGHT",
        description: "Patient-centered web application designed to bridge the gap between complex pharmaceutical data.",
        link: "#",
        tags: ["Python", "Flask", "MySQL"]
    },
    {
        title: "Ligand binding site predictor",
        description: "Predictive modeling for protein binding sites. Launching soon...",
        link: "#",
        tags: ["Python", "PyTorch"]
    }
];

const projectGrid = document.querySelector('.project-grid');

function loadProjects() {
    if(!projectGrid) return;
    projectGrid.innerHTML = myProjects.map(project => `
        <div class="project-card">
            <h3>${project.title}</h3>
            <p>${project.description}</p>
            <div class="tags">${project.tags.map(t => `<span>${t}</span>`).join('')}</div>
            <a href="${project.link}" style="color: var(--accent-color); text-decoration: none; margin-top: 10px; display: inline-block;">View Project →</a>
        </div>
    `).join('');
}

// 3. Smooth Sliding Slideshow (ONLY THIS ONE)
let currentSlide = 0;

function moveSlides() {
    const track = document.getElementById('slide-track');
    const slides = document.querySelectorAll('.mySlides');
    
    if (!track || slides.length === 0) return;

    currentSlide++;

    // If we are at the end, go back to the first slide
    if (currentSlide >= slides.length) {
        currentSlide = 0;
    }

    // Move the track to the left
    const percentage = currentSlide * 100;
    track.style.transform = `translateX(-${percentage}%)`;
}

// 4. Initialize everything when the page is ready
window.addEventListener('DOMContentLoaded', () => {
    loadProjects();
    // Start the sliding timer (3 seconds)
    setInterval(moveSlides, 8000);
});
function copyEmail() {
    const email = document.getElementById("email-address").innerText;
    
    navigator.clipboard.writeText(email).then(() => {
        // Find the copy hint text and change it temporarily
        const hint = document.querySelector('.copy-hint');
        const originalText = hint.innerText;
        
        hint.innerText = "Copied! ✅";
        hint.style.color = "#4CAF50"; // Green for success
        
        setTimeout(() => {
            hint.innerText = originalText;
            hint.style.color = "var(--accent-color)";
        }, 2000);
    }).catch(err => {
        console.error('Could not copy text: ', err);
    });
}