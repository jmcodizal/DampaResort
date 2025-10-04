// Function to toggle password visibility
function toggleVisibility(toggleId, inputId) {
  // Get the toggle icon element and the input field element by their IDs
  const toggle = document.getElementById(toggleId);
  const input = document.getElementById(inputId);

  // Safety check: If either element doesn't exist, stop execution
  if (!toggle || !input) return;

  // Add click event listener to the toggle icon
  toggle.addEventListener("click", () => {
    // Check if the input type is currently "password"
    const isPassword = input.type === "password";

    // Toggle between password and text
    input.type = isPassword ? "text" : "password";

    // Toggle the icon between eye and eye-slash
    toggle.classList.toggle("bi-eye-fill");
    toggle.classList.toggle("bi-eye-slash-fill");

    // Optional: Toggle aria-label for accessibility
    toggle.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
  });
}

// Apply the toggle function to both password fields
toggleVisibility("togglePassword", "password");
toggleVisibility("toggleConfirm", "confirmPassword");
