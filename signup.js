function toggleVisibility(toggleId, inputId) {
  const toggle = document.getElementById(toggleId);
  const input = document.getElementById(inputId);

  toggle.addEventListener("click", () => {
    const type = input.type === "password" ? "text" : "password";
    input.type = type;

    // Change icon between eye and eye-slash
    toggle.classList.toggle("bi-eye-fill");
    toggle.classList.toggle("bi-eye-slash-fill");
  });
}

toggleVisibility("togglePassword", "password");
toggleVisibility("toggleConfirm", "confirmPassword");
