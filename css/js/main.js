// Function to open modal
function openModal(content) {
    document.getElementById('modal-content').innerHTML = content;
    document.getElementById('modal-overlay').style.display = 'block';
    document.getElementById('recommendation-modal').style.display = 'block';
}

// Function to close modal
function closeModal() {
    document.getElementById('modal-overlay').style.display = 'none';
    document.getElementById('recommendation-modal').style.display = 'none';
}

// Event listener for modal close
window.onclick = function(event) {
    if (event.target == document.getElementById('modal-overlay')) {
        closeModal();
    }
}

// Additional Scripts...
