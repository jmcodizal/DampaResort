// === DOM Elements ===
const editBtn = document.getElementById("editProfileBtn");
const modal = document.getElementById("profileModal");
const closeBtn = document.querySelector(".close-btn");

const form = document.getElementById("personalForm");
const profileName = document.getElementById("profileName");
const profileImage = document.getElementById("profileImage");

const displayName = document.getElementById("displayName");
const displayEmail = document.getElementById("displayEmail");
const displayPhone = document.getElementById("displayPhone");

const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const phoneInput = document.getElementById("phone");
const uploadPic = document.getElementById("uploadPic");


// === Modal Controls ===
// Open modal
editBtn.addEventListener("click", () => {
  modal.style.display = "block";
});

// Close modal
closeBtn.addEventListener("click", () => {
  modal.style.display = "none";
});

// Close if clicked outside modal
window.addEventListener("click", (e) => {
  if (e.target === modal) {
    modal.style.display = "none";
  }
});


// === Update Info on Save ===
form.addEventListener("submit", function(e) {
  e.preventDefault();

  // Update display info (card view)
  displayName.textContent = nameInput.value;
  displayEmail.textContent = emailInput.value;
  displayPhone.textContent = phoneInput.value;

  // Update header name too
  profileName.textContent = nameInput.value;

  alert("Profile updated successfully!");
  modal.style.display = "none"; // close modal
});


// === Profile Picture Update ===
uploadPic.addEventListener("change", function() {
  const file = this.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      profileImage.src = e.target.result; // update header picture
    };
    reader.readAsDataURL(file);
  }
});
