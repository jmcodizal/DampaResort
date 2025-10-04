// ===============================
// DOM ELEMENT REFERENCES
// ===============================
const editBtn = document.getElementById("editProfileBtn");   // "Edit Profile" button
const modal = document.getElementById("profileModal");       // Modal container
const closeBtn = document.querySelector(".close-btn");       // Close (X) button in modal

const form = document.getElementById("personalForm");        // Form inside modal
const profileName = document.getElementById("profileName");  // Name in header
const profileImage = document.getElementById("profileImage");// Profile picture in header

// Displayed info on the Personal Information card
const displayName = document.getElementById("displayName");
const displayEmail = document.getElementById("displayEmail");
const displayPhone = document.getElementById("displayPhone");

// Input fields inside modal form
const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const phoneInput = document.getElementById("phone");
const uploadPic = document.getElementById("uploadPic");


// ===============================
// MODAL CONTROLS
// ===============================

// Open modal when "Edit Profile" button is clicked
editBtn.addEventListener("click", () => {
  modal.style.display = "block";
});

// Close modal when (X) is clicked
closeBtn.addEventListener("click", () => {
  modal.style.display = "none";
});

// Close modal if user clicks outside the modal box
window.addEventListener("click", (e) => {
  if (e.target === modal) {
    modal.style.display = "none";
  }
});


// ===============================
// UPDATE INFO ON SAVE
// ===============================
form.addEventListener("submit", function(e) {
  e.preventDefault(); // Prevent page reload

  // Update "Personal Information" card with form values
  displayName.textContent = nameInput.value;
  displayEmail.textContent = emailInput.value;
  displayPhone.textContent = phoneInput.value;

  // Update profile name in header too
  profileName.textContent = nameInput.value;

  // Confirmation
  alert("Profile updated successfully!");

  // Close modal after saving
  modal.style.display = "none";
});


// ===============================
// PROFILE PICTURE UPDATE
// ===============================
uploadPic.addEventListener("change", function() {
  const file = this.files[0]; // Get uploaded file

  if (file) {
    const reader = new FileReader();

    // Once file is loaded, update profile picture
    reader.onload = function(e) {
      profileImage.src = e.target.result; // Replace header picture
    };

    // Convert file into a data URL (base64 string)
    reader.readAsDataURL(file);
  }
});
