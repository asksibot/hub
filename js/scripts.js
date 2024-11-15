// Function to fetch resources from JSON
async function fetchResources(category) {
    try {
        const response = await fetch(`../../library/${category}/resources.json`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        // Get the first key dynamically
        const key = Object.keys(data)[0];
        return data[key];
    } catch (error) {
        console.error('Error fetching the resources:', error);
        return [];
    }
}

// Function to handle survey submission
async function submitSurvey() {
    const selectedInterest = document.getElementById('user-interest').value;

    if (!selectedInterest) {
        alert("Please select an option.");
        return;
    }

    // Map survey options to JSON categories
    const categoryMap = {
        'parent-guardian': 'parenting-children-autism-spectrum-resources',
        'education-learning': 'education-learning',
        'games-ideas': 'games-ideas',
        'mental-health-healing': 'mental-health-healing',
        'personal-growth-relationships': 'personal-growth-relationships',
        'professional-development-business': 'professional-development-business',
        'individual': 'individual-resources', // Ensure these directories and JSON files exist
        'neurodivergent': 'neurodivergent-resources',
        'caregiver': 'caregiver-resources',
        'therapist': 'therapist-resources',
        'teacher': 'teacher-resources'
        // Add other mappings as needed
    };

    const category = categoryMap[selectedInterest];

    if (!category) {
        alert("Selected category does not have a corresponding resources file.");
        return;
    }

    // Fetch resources from JSON
    const resources = await fetchResources(category);

    // Display recommendations in modal
    showModal(resources);
}

// Function to show modal with filtered resources
function showModal(resources) {
    const modalOverlay = document.getElementById('modal-overlay');
    const modal = document.getElementById('recommendation-modal');
    const modalContent = document.getElementById('modal-content');

    // Clear previous content
    modalContent.innerHTML = '';

    if (resources.length === 0) {
        modalContent.innerHTML = '<p>No recommendations match your criteria.</p>';
    } else {
        const resourceList = document.createElement('ul');
        resourceList.style.listStyleType = 'none';
        resourceList.style.padding = '0';

        resources.forEach(resource => {
            const listItem = document.createElement('li');
            listItem.style.marginBottom = '20px';

            const title = document.createElement('h3');
            title.textContent = resource.title;
            title.style.color = '#4e79a7';
            listItem.appendChild(title);

            const author = document.createElement('p');
            author.innerHTML = `<strong>Author:</strong> ${resource.author}`;
            listItem.appendChild(author);

            const description = document.createElement('p');
            description.textContent = resource.description;
            listItem.appendChild(description);

            // CTA Buttons
            const ctaDiv = document.createElement('div');
            ctaDiv.classList.add('cta-buttons');

            // Buy Now Button
            if (resource.amazon_link) {
                const buyButton = document.createElement('button');
                buyButton.classList.add('buy-now');
                buyButton.textContent = 'Buy Now';
                buyButton.onclick = () => window.open(resource.amazon_link, '_blank');
                ctaDiv.appendChild(buyButton);
            }

            // Read More Button
            if (resource.readMore) {
                const readMoreButton = document.createElement('button');
                readMoreButton.classList.add('read-more');
                readMoreButton.textContent = 'Read More';
                readMoreButton.onclick = () => window.open(resource.readMore, '_blank');
                ctaDiv.appendChild(readMoreButton);
            }

            // Wishlist Button
            const wishlistButton = document.createElement('button');
            wishlistButton.classList.add('wishlist');
            wishlistButton.textContent = 'Add to Wishlist';
            wishlistButton.onclick = () => addToWishlist(resource.title);
            ctaDiv.appendChild(wishlistButton);

            listItem.appendChild(ctaDiv);

            resourceList.appendChild(listItem);
        });

        modalContent.appendChild(resourceList);
    }

    // Show modal and overlay
    modalOverlay.style.display = 'block';
    modal.style.display = 'block';
}

// Function to close modal
function closeModal() {
    document.getElementById('modal-overlay').style.display = 'none';
    document.getElementById('recommendation-modal').style.display = 'none';
}

// Function to print recommendations
function printRecommendations() {
    const printContent = document.getElementById('modal-content').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload(); // Reload to restore event listeners
}

// Function to save recommendations as PDF (simple implementation)
function saveAsPDF() {
    window.print(); // For advanced PDF generation, consider using libraries like jsPDF
}

// Function to add a book to the wishlist
function addToWishlist(bookTitle) {
    // Simple implementation: alert the user
    alert(`${bookTitle} has been added to your wishlist!`);
    // In a real application, implement persistent storage (e.g., localStorage or backend)
}

// Event listener to close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('recommendation-modal');
    if (event.target == document.getElementById('modal-overlay')) {
        closeModal();
    }
}
